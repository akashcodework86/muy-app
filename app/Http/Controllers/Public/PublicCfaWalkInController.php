<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\CfaSubmission;
use App\Models\District;
use App\Models\DistrictBlock;
use App\Models\FiscalYear;
use App\Services\CfaApplicationNumberGenerator;
use App\Services\CfaBusinessStageService;
use App\Services\CfaSubmissionValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class PublicCfaWalkInController extends Controller
{
    public function show(): View
    {
        $districts = District::query()->orderBy('name')->get();

        return view('public.cfa.public', [
            'districts'          => $districts,
            'productsByCategory' => config('cfa.products_by_category'),
        ]);
    }

    /**
     * Return block names for a district as JSON (used by the dynamic block dropdown).
     */
    public function blocks(Request $request): JsonResponse
    {
        $districtId = (int) $request->query('district_id', 0);
        if ($districtId < 1) {
            return response()->json([]);
        }

        $blocks = DistrictBlock::orderedNamesForDistrict($districtId);

        if ($blocks === []) {
            $district = District::find($districtId);
            if ($district) {
                $blocks = config('cfa.blocks_by_district.'.$district->name, []);
            }
        }

        return response()->json($blocks);
    }

    /**
     * Live phone-duplicate check (no token required for public form).
     */
    public function checkPhone(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'phone' => ['required', 'regex:/^[6-9]\d{9}$/'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'ok'        => false,
                'available' => null,
                'errors'    => $validator->errors(),
            ], 422);
        }

        $phone = $validator->validated()['phone'];

        $duplicate = null;

        $newRow = CfaSubmission::query()
            ->where('phone', $phone)
            ->orderByDesc('id')
            ->first();
        if ($newRow) {
            $fyName = $newRow->fiscal_year_id
                ? FiscalYear::query()->whereKey($newRow->fiscal_year_id)->value('name')
                : null;
            $duplicate = [
                'name' => $newRow->applicant_name ?: null,
                'phase' => 'Current MUY',
                'fy' => $fyName,
                'source' => 'cfa_submissions',
            ];
        }

        $legacyRow = null;
        if (config('database.connections.legacy.database', '') !== '') {
            try {
                $legacyRow = DB::connection('legacy')
                    ->table('rbi_applicant_details')
                    ->where('phone', $phone)
                    ->select(['applicant_name'])
                    ->orderByDesc('application_id')
                    ->first();
            } catch (\Exception $e) {
                // Legacy DB unavailable — skip silently
            }
        }

        if ($duplicate === null && $legacyRow) {
            $duplicate = [
                'name' => $legacyRow->applicant_name ?: null,
                'phase' => 'Legacy Phase 2',
                'fy' => '2025-26',
                'source' => 'rbi_applicant_details',
            ];
        }

        $phase1Row = null;
        if (config('database.connections.legacy_phase1.database', '') !== '') {
            try {
                $phase1Row = DB::connection('legacy_phase1')
                    ->table('tblapplication')
                    ->where('MobileNumber', $phone)
                    ->select(['FullName'])
                    ->orderByDesc('ID')
                    ->first();
            } catch (\Exception $e) {
                // Phase 1 DB unavailable — skip silently
            }
        }

        if ($duplicate === null && $phase1Row) {
            $duplicate = [
                'name' => $phase1Row->FullName ?: null,
                'phase' => 'Legacy Phase 1',
                'fy' => '2024-25',
                'source' => 'tblapplication',
            ];
        }

        $exists = $duplicate !== null;

        return response()->json([
            'ok'        => true,
            'available' => ! $exists,
            'message'   => $exists
                ? 'This mobile number is already registered for an application. / यह मोबाइल नंबर पहले से पंजीकृत है।'
                : null,
            'duplicate' => $duplicate,
        ]);
    }

    public function store(
        Request $request,
        CfaSubmissionValidator $cfaValidator,
        CfaBusinessStageService $stageService,
        CfaApplicationNumberGenerator $applicationNumbers,
        \App\Services\ActivityLogger $activity,
    ): RedirectResponse {
        $this->normalizeEmptySelects($request);

        $validated = $cfaValidator->validatePublic($request);

        // Resolve district model from validated district name
        $district = District::query()->where('name', $validated['district'])->firstOrFail();

        $turnover  = CfaBusinessStageService::parseTurnover($validated['turnover_last_fy']);
        $stageInfo = $stageService->compute($validated['is_registered'], $turnover);

        $applicationNo = $applicationNumbers->generateForDistrict($district, $validated['block']);

        $regType = $validated['registration_type'] ?? null;
        if ($regType === 'Other' && ! empty($validated['registration_type_other'])) {
            $regType = 'Other: '.$validated['registration_type_other'];
        }

        $applicantDisplay = match ($validated['category']) {
            'Individual' => $validated['applicant_name'],
            default      => $validated['shg_cbo_name'] ?? '',
        };

        $payload = array_merge($validated, [
            'form_stage'                => $stageInfo['stage'],
            'criteria_matched'          => $stageInfo['criteria_matched'],
            'stage_logic_lines'         => $stageInfo['logic_lines'],
            'registration_type_resolved' => $validated['is_registered'] === 'Yes' ? $regType : null,
            'referral_staff_name'       => null,
            'referral_staff_email'      => null,
            'submitted_at'              => now()->toIso8601String(),
            'source'                    => 'public_form',
        ]);
        $payload['consent'] = true;

        $fy = FiscalYear::query()->where('is_active', true)->orderByDesc('starts_on')->first();

        $blockRow = DistrictBlock::query()
            ->where('district_id', $district->id)
            ->where('name', $validated['block'])
            ->first();

        $submission = CfaSubmission::query()->create([
            'application_no'    => $applicationNo,
            'fiscal_year_id'    => $fy?->id,
            'district_id'       => $district->id,
            'lgd_state_code'    => config('cfa.lgd_state_code'),
            'lgd_district_code' => $district->lgd_district_code,
            'lgd_block_code'    => $blockRow?->lgd_block_code,
            'referral_user_id'  => null,
            'source'            => 'public_form',
            'applicant_name'    => $applicantDisplay,
            'phone'             => $validated['phone'],
            'payload'           => $payload,
        ]);

        $activity->log(
            type: 'cfa.created',
            title: 'Walk-in CFA '.$applicationNo.' submitted ('.$applicantDisplay.')',
            actor: null,
            subject: $submission,
            districtId: (int) $district->id,
            meta: [
                'application_no' => $applicationNo,
                'block' => $validated['block'] ?? null,
                'source' => 'public_form',
            ],
        );

        $productDisplay = ($validated['product'] ?? '') === 'Others'
            ? trim((string) ($validated['other_product'] ?? ''))
            : trim((string) ($validated['product'] ?? ''));

        return redirect()
            ->route('cfa.thanks')
            ->with('application_no', $applicationNo)
            ->with('source', 'public')
            ->with('thanks_name', $applicantDisplay)
            ->with('thanks_district', $validated['district'])
            ->with('thanks_block', $validated['block'])
            ->with('thanks_sector', $validated['business_category'])
            ->with('thanks_product', $productDisplay);
    }

    private function normalizeEmptySelects(Request $request): void
    {
        $merge = [];
        foreach (['regular_buyer', 'financial_support', 'id_proof_type'] as $key) {
            $v = $request->input($key);
            if ($v === '' || $v === null) {
                $merge[$key] = null;
            }
        }
        if ($merge !== []) {
            $request->merge($merge);
        }
    }
}
