<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\CfaSubmission;
use App\Models\Deliverable;
use App\Models\DistrictBlock;
use App\Models\FiscalYear;
use App\Services\AdminAuditLogger;
use App\Services\CfaBusinessStageService;
use App\Services\CfaSubmissionAuditSnapshot;
use App\Services\CfaSubmissionValidator;
use App\Services\StaffMonthlyTargetsDashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class StaffPortalController extends Controller
{
    public function monthlyTargets(Request $request, StaffMonthlyTargetsDashboardService $dashboard): View
    {
        $user = $request->user()->load(['district', 'hub', 'designationRecord']);

        if (! $user->district_id) {
            return view('staff.monthly-targets', [
                'noDistrict' => true,
                'user' => $user,
            ]);
        }

        if (! Deliverable::query()->where('is_active', true)->exists()) {
            return view('staff.monthly-targets', [
                'missingDeliverables' => true,
                'user' => $user,
            ]);
        }

        $fiscalYears = FiscalYear::query()->orderByDesc('starts_on')->get();
        $fiscalYearId = (int) ($request->query('fiscal_year_id')
            ?: FiscalYear::query()->where('is_active', true)->value('id')
            ?: $fiscalYears->first()?->id);

        $fiscalYear = FiscalYear::query()->findOrFail($fiscalYearId);
        $rows = $dashboard->buildRows($user, $fiscalYear);
        $monthLabels = $dashboard->fiscalMonthLabels($fiscalYear);

        return view('staff.monthly-targets', [
            'user' => $user,
            'fiscalYears' => $fiscalYears,
            'fiscalYearId' => $fiscalYearId,
            'fiscalYear' => $fiscalYear,
            'rows' => $rows,
            'monthLabels' => $monthLabels,
            'applyUrl' => $user->referralApplyUrl(),
        ]);
    }

    public function applications(Request $request): View
    {
        $submissions = CfaSubmission::query()
            ->where('referral_user_id', $request->user()->id)
            ->with(['district'])
            ->orderByDesc('created_at')
            ->paginate(25);

        return view('staff.applications', [
            'submissions' => $submissions,
        ]);
    }

    public function showCfaSubmission(Request $request, CfaSubmission $cfa_submission): View
    {
        $this->assertOwnReferral($request, $cfa_submission);
        $cfa_submission->load(['district', 'referralUser', 'fiscalYear']);

        $cfaEditLogs = AuditLog::query()
            ->where('subject_type', CfaSubmission::class)
            ->where('subject_id', $cfa_submission->id)
            ->where('action', CfaSubmissionAuditSnapshot::ACTION_UPDATED)
            ->with('user')
            ->orderByDesc('created_at')
            ->get();

        return view('admin.cfa.show', [
            'submission' => $cfa_submission,
            'cfaIndexUrl' => route('staff.applications'),
            'cfaEditUrl' => route('staff.applications.edit', $cfa_submission),
            'cfaEditLogs' => $cfaEditLogs,
        ]);
    }

    public function editCfaSubmission(Request $request, CfaSubmission $cfa_submission): View
    {
        $this->assertOwnReferral($request, $cfa_submission);

        $staff = $request->user()->load(['district', 'hub', 'designationRecord']);
        $token = $staff->referral_token ?? '';
        if ($token === '') {
            abort(403, 'Referral token missing on your account.');
        }

        $districtName = $staff->district?->name ?? '';
        $districtId = (int) $staff->district_id;
        $blocks = $districtId > 0
            ? DistrictBlock::orderedNamesForDistrict($districtId)
            : [];
        if ($blocks === []) {
            $blocks = config('cfa.blocks_by_district.'.$districtName, []);
        }

        if ($districtName === '' || $blocks === []) {
            return view('public.cfa.apply', [
                'staff' => $staff,
                'token' => $token,
                'districtName' => '',
                'blocks' => [],
                'productsByCategory' => config('cfa.products_by_category'),
                'cfaEditingSubmission' => $cfa_submission,
            ]);
        }

        $prefill = $this->cfaOldInputFromSubmission($cfa_submission);
        $request->session()->now('_old_input', $prefill);

        return view('public.cfa.apply', [
            'staff' => $staff,
            'token' => $token,
            'districtName' => $districtName,
            'blocks' => $blocks,
            'productsByCategory' => config('cfa.products_by_category'),
            'cfaEditingSubmission' => $cfa_submission,
        ]);
    }

    public function updateCfaSubmission(
        Request $request,
        CfaSubmission $cfa_submission,
        CfaSubmissionValidator $cfaValidator,
        CfaBusinessStageService $stageService,
        AdminAuditLogger $auditLogger,
    ): RedirectResponse {
        $this->assertOwnReferral($request, $cfa_submission);

        $staff = $request->user()->load('district');
        $this->normalizeEmptySelects($request);

        $beforeSnapshot = CfaSubmissionAuditSnapshot::compact($cfa_submission);

        $validated = $cfaValidator->validate($request, $staff, $cfa_submission);

        $turnover = CfaBusinessStageService::parseTurnover($validated['turnover_last_fy']);
        $stageInfo = $stageService->compute($validated['is_registered'], $turnover);

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

        $blockRow = DistrictBlock::query()
            ->where('district_id', $staff->district_id)
            ->where('name', $validated['block'])
            ->first();

        $cfa_submission->update([
            'lgd_block_code' => $blockRow?->lgd_block_code,
            'applicant_name' => $applicantDisplay,
            'phone' => $validated['phone'],
            'payload' => $payload,
        ]);

        $cfa_submission->refresh();
        $afterSnapshot = CfaSubmissionAuditSnapshot::compact($cfa_submission);
        $auditLogger->record(
            $request,
            CfaSubmissionAuditSnapshot::ACTION_UPDATED,
            CfaSubmission::class,
            $cfa_submission->id,
            $beforeSnapshot,
            $afterSnapshot,
            CfaSubmissionAuditSnapshot::describeDiff($beforeSnapshot, $afterSnapshot),
        );

        return redirect()
            ->route('staff.applications.show', $cfa_submission)
            ->with('status', 'Application updated.');
    }

    public function checkPhoneForEdit(Request $request, CfaSubmission $cfa_submission): JsonResponse
    {
        $this->assertOwnReferral($request, $cfa_submission);

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
        $exists = CfaSubmission::query()
            ->where('phone', $phone)
            ->where('id', '!=', $cfa_submission->id)
            ->exists();

        return response()->json([
            'ok' => true,
            'available' => ! $exists,
            'message' => $exists
                ? 'This mobile number is already registered for an application. / यह मोबाइल नंबर पहले से पंजीकृत है।'
                : null,
        ]);
    }

    private function assertOwnReferral(Request $request, CfaSubmission $submission): void
    {
        abort_unless(
            (int) $submission->referral_user_id === (int) $request->user()->id,
            403,
            'You can only access applications submitted through your referral link.'
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function cfaOldInputFromSubmission(CfaSubmission $submission): array
    {
        $p = is_array($submission->payload) ? $submission->payload : [];

        $rt = $p['registration_type'] ?? null;
        if (is_string($rt) && str_starts_with($rt, 'Other:')) {
            $p['registration_type'] = 'Other';
            $rest = trim(substr($rt, strlen('Other:')));
            if ($rest !== '' && empty($p['registration_type_other'])) {
                $p['registration_type_other'] = $rest;
            }
        }

        $p['phone'] = $submission->phone;
        $p['consent'] = '1';

        return $p;
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
