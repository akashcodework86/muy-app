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

        $existsNew = CfaSubmission::query()->where('phone', $phone)->exists();

        $existsLegacy = false;
        if (config('database.connections.legacy.database', '') !== '') {
            try {
                $existsLegacy = DB::connection('legacy')
                    ->table('rbi_applicant_details')
                    ->where('phone', $phone)
                    ->exists();
            } catch (\Exception $e) {
                // Legacy DB unavailable — skip silently
            }
        }

        $exists = $existsNew || $existsLegacy;

        return response()->json([
            'ok'        => true,
            'available' => ! $exists,
            'message'   => $exists
                ? 'This mobile number is already registered for an application. / यह मोबाइल नंबर पहले से पंजीकृत है।'
                : null,
        ]);
    }

    public function store(
        Request $request,
        CfaSubmissionValidator $cfaValidator,
        CfaBusinessStageService $stageService,
        CfaApplicationNumberGenerator $applicationNumbers,
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

        CfaSubmission::query()->create([
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

        return redirect()
            ->route('cfa.thanks')
            ->with('application_no', $applicationNo);
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
