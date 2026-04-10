<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\CfaSubmission;
use App\Models\DistrictBlock;
use App\Models\FiscalYear;
use App\Models\User;
use App\Services\CfaApplicationNumberGenerator;
use App\Services\CfaBusinessStageService;
use App\Services\CfaSubmissionValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class CfaApplyController extends Controller
{
    public function show(string $token): View
    {
        $staff = User::query()
            ->where('referral_token', $token)
            ->where('role', 'district_staff')
            ->where('is_active', true)
            ->with(['district', 'hub', 'designationRecord'])
            ->firstOrFail();

        $districtName = $staff->district?->name ?? '';
        $districtId = (int) $staff->district_id;
        $blocks = $districtId > 0
            ? DistrictBlock::orderedNamesForDistrict($districtId)
            : [];
        if ($blocks === []) {
            $blocks = config('cfa.blocks_by_district.'.$districtName, []);
        }

        return view('public.cfa.apply', [
            'staff' => $staff,
            'token' => $token,
            'districtName' => $districtName,
            'blocks' => $blocks,
            'productsByCategory' => config('cfa.products_by_category'),
        ]);
    }

    /**
     * Live check: mobile already used on any CFA submission (JSON for the public form).
     */
    public function checkPhone(Request $request, string $token): JsonResponse
    {
        $staff = User::query()
            ->where('referral_token', $token)
            ->where('role', 'district_staff')
            ->where('is_active', true)
            ->first();

        if (! $staff) {
            return response()->json(['ok' => false, 'message' => 'Invalid link.'], 403);
        }

        $validator = Validator::make($request->all(), [
            'phone' => ['required', 'regex:/^[6-9]\d{9}$/'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'ok' => false,
                'available' => null,
                'errors' => $validator->errors(),
            ], 422);
        }

        $phone = $validator->validated()['phone'];

        // Check new CFA submissions table
        $existsNew = CfaSubmission::query()->where('phone', $phone)->exists();

        // Check old Phase 2 legacy database (rbi_applicant_details)
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
            'ok' => true,
            'available' => ! $exists,
            'message' => $exists
                ? 'This mobile number is already registered for an application. / यह मोबाइल नंबर पहले से पंजीकृत है।'
                : null,
        ]);
    }

    public function store(
        Request $request,
        string $token,
        CfaSubmissionValidator $cfaValidator,
        CfaBusinessStageService $stageService,
        CfaApplicationNumberGenerator $applicationNumbers,
    ): RedirectResponse {
        $staff = User::query()
            ->where('referral_token', $token)
            ->where('role', 'district_staff')
            ->where('is_active', true)
            ->with('district')
            ->firstOrFail();

        $this->normalizeEmptySelects($request);

        $validated = $cfaValidator->validate($request, $staff);

        $turnover = CfaBusinessStageService::parseTurnover($validated['turnover_last_fy']);
        $stageInfo = $stageService->compute($validated['is_registered'], $turnover);

        $applicationNo = $applicationNumbers->generate($staff, $validated['block']);

        $regType = $validated['registration_type'] ?? null;
        if ($regType === 'Other' && ! empty($validated['registration_type_other'])) {
            $regType = 'Other: '.$validated['registration_type_other'];
        }

        $applicantDisplay = match ($validated['category']) {
            'Individual' => $validated['applicant_name'],
            default => $validated['shg_cbo_name'] ?? '',
        };

        $payload = array_merge($validated, [
            'form_stage' => $stageInfo['stage'],
            'criteria_matched' => $stageInfo['criteria_matched'],
            'stage_logic_lines' => $stageInfo['logic_lines'],
            'registration_type_resolved' => $validated['is_registered'] === 'Yes' ? $regType : null,
            'referral_staff_name' => $staff->name,
            'referral_staff_email' => $staff->email,
            'submitted_at' => now()->toIso8601String(),
        ]);
        $payload['consent'] = true;

        $fy = FiscalYear::query()->where('is_active', true)->orderByDesc('starts_on')->first();

        $blockRow = DistrictBlock::query()
            ->where('district_id', $staff->district_id)
            ->where('name', $validated['block'])
            ->first();

        CfaSubmission::query()->create([
            'application_no' => $applicationNo,
            'fiscal_year_id' => $fy?->id,
            'district_id' => $staff->district_id,
            'lgd_state_code' => config('cfa.lgd_state_code'),
            'lgd_district_code' => $staff->district?->lgd_district_code,
            'lgd_block_code' => $blockRow?->lgd_block_code,
            'referral_user_id' => $staff->id,
            'applicant_name' => $applicantDisplay,
            'phone' => $validated['phone'],
            'payload' => $payload,
        ]);

        $productDisplay = ($validated['product'] ?? '') === 'Others'
            ? trim((string) ($validated['other_product'] ?? ''))
            : trim((string) ($validated['product'] ?? ''));

        return redirect()
            ->route('cfa.thanks')
            ->with('application_no', $applicationNo)
            ->with('source', 'referral')
            ->with('referral_token', $token)
            ->with('thanks_name', $applicantDisplay)
            ->with('thanks_district', $validated['district'])
            ->with('thanks_block', $validated['block'])
            ->with('thanks_sector', $validated['business_category'])
            ->with('thanks_product', $productDisplay);
    }

    public function thanks(): View
    {
        return view('public.cfa.thanks', [
            'applicationNo'   => session('application_no'),
            'source'          => session('source', 'public'),
            'referralToken'   => session('referral_token'),
            'thanksName'      => session('thanks_name'),
            'thanksDistrict'  => session('thanks_district'),
            'thanksBlock'     => session('thanks_block'),
            'thanksSector'    => session('thanks_sector'),
            'thanksProduct'   => session('thanks_product'),
        ]);
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
