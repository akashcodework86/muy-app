<?php

namespace App\Http\Controllers\Hub;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\CfaSubmission;
use App\Models\District;
use App\Models\FiscalYear;
use App\Models\Hub;
use App\Models\OnboardingBatch;
use App\Services\CfaSubmissionAuditSnapshot;
use App\Services\HubBatchService;
use App\Services\LegacyPhase2ApplicationDetailService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HubBatchController extends Controller
{
    public function __construct(
        private HubBatchService $batches
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $hub = Hub::query()->findOrFail((int) $user->hub_id);
        $districts = District::query()
            ->where('hub_id', $hub->id)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);
        [$selectedFiscalYearId, $fiscalYears] = FiscalYear::resolveIdForUi(
            $request->query('fiscal_year_id') ? (int) $request->query('fiscal_year_id') : null
        );

        return view('hub.batches.index', [
            'hub' => $hub,
            'districts' => $districts,
            'fiscalYears' => $fiscalYears,
            'selectedFiscalYearId' => $selectedFiscalYearId,
            'stats' => [
                'blocked' => $this->batches->hubWriteBlocked($hub->id),
                'overdue_cdo' => $this->batches->countOverdueBatches($hub->id),
                'pending_cdo' => $this->batches->countPendingCdo($hub->id),
            ],
        ]);
    }

    public function api(Request $request): JsonResponse
    {
        $action = (string) $request->input('action', '');
        $result = $this->batches->handleApi($action, $request->user(), $request->all(), $request);

        if (! $result['ok']) {
            return response()->json(['ok' => false, 'error' => $result['error'] ?? 'Error'], 422);
        }

        return response()->json(array_merge(['ok' => true], $result['data'] ?? []));
    }

    public function uploadCdo(Request $request): RedirectResponse
    {
        $request->validate([
            'onboarding_batch_id' => ['required', 'integer', 'exists:onboarding_batches,id'],
            'file' => ['required', 'file', 'mimes:pdf', 'max:20480'],
        ]);

        $user = $request->user();
        $hubId = (int) $user->hub_id;
        $batch = OnboardingBatch::query()->findOrFail((int) $request->input('onboarding_batch_id'));
        if ((int) $batch->hub_id !== $hubId || ! $batch->isLocked() || ! $batch->locked_at) {
            abort(403);
        }

        $this->batches->storeCdoDocument($batch, $user, $request->file('file'));

        return redirect()
            ->route('hub.batches.index')
            ->with('status', 'CDO signed PDF uploaded.');
    }

    public function showCfaSubmission(Request $request, CfaSubmission $cfa_submission): View
    {
        $hubId = (int) $request->user()->hub_id;
        $submissionDistrictHubId = (int) District::query()
            ->whereKey($cfa_submission->district_id)
            ->value('hub_id');
        if ($hubId <= 0 || $submissionDistrictHubId !== $hubId) {
            abort(403);
        }

        $cfa_submission->load(['district', 'referralUser', 'fiscalYear']);

        $cfaEditLogs = AuditLog::query()
            ->where('subject_type', CfaSubmission::class)
            ->where('subject_id', $cfa_submission->id)
            ->where('action', CfaSubmissionAuditSnapshot::ACTION_UPDATED)
            ->with('user')
            ->orderByDesc('created_at')
            ->get();

        $legacyDetail = app(LegacyPhase2ApplicationDetailService::class)->tryBuild($cfa_submission);
        if (is_array($legacyDetail) && isset($legacyDetail['error'])) {
            abort(403, $legacyDetail['message'] ?? 'Access denied.');
        }
        if (is_array($legacyDetail) && isset($legacyDetail['viewRow'])) {
            return view('admin.cfa.legacy-detail', [
                'submission' => $cfa_submission,
                'legacyDetail' => $legacyDetail,
                'cfaIndexUrl' => route('hub.batches.index'),
            ]);
        }

        return view('admin.cfa.show', [
            'submission' => $cfa_submission,
            'cfaIndexUrl' => route('hub.batches.index'),
            'cfaEditUrl' => null,
            'cfaEditLogs' => $cfaEditLogs,
        ]);
    }
}
