<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\District;
use App\Models\Hub;
use App\Models\OnboardingBatch;
use App\Models\OnboardingBatchEditRequest;
use App\Services\AdminAuditLogger;
use App\Services\HubBatchService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class HubBatchComplianceController extends Controller
{
    public function __construct(
        private HubBatchService $batches,
        private AdminAuditLogger $auditLogger,
    ) {}

    public function index(): View
    {
        $rows = OnboardingBatch::query()
            ->where('status', 'locked')
            ->whereNotNull('locked_at')
            ->with(['hub', 'district'])
            ->orderByDesc('id')
            ->limit(120)
            ->get()
            ->map(function (OnboardingBatch $b) {
                return [
                    'batch' => $b,
                    'has_cdo' => $this->batches->hasCdoPdf($b),
                    'overdue' => $this->batches->cdoIsOverdue($b),
                ];
            });

        $pendingEditRequests = OnboardingBatchEditRequest::query()
            ->where('status', 'pending')
            ->with(['batch.hub', 'batch.district', 'requester'])
            ->orderByDesc('id')
            ->limit(200)
            ->get();

        return view('admin.hub-batch-compliance.index', [
            'rows' => $rows,
            'pendingEditRequests' => $pendingEditRequests,
        ]);
    }

    public function requests(Request $request): View
    {
        $status = (string) $request->query('status', 'pending');
        if (! in_array($status, ['pending', 'approved', 'all'], true)) {
            $status = 'pending';
        }

        $hubId = (int) $request->query('hub_id', 0);
        $districtId = (int) $request->query('district_id', 0);

        $q = OnboardingBatchEditRequest::query()
            ->with(['batch.hub', 'batch.district', 'requester', 'approver', 'relocker'])
            ->when($status !== 'all', fn ($qq) => $qq->where('status', $status))
            ->when($hubId > 0, fn ($qq) => $qq->where('hub_id', $hubId))
            ->when($districtId > 0, fn ($qq) => $qq->where('district_id', $districtId))
            ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
            ->orderByDesc('id');

        return view('admin.hub-batch-compliance.requests', [
            'requests' => $q->paginate(50)->withQueryString(),
            'status' => $status,
            'hubId' => $hubId,
            'districtId' => $districtId,
            'hubs' => Hub::query()->orderBy('name')->get(['id', 'name']),
            'districts' => District::query()->orderBy('name')->get(['id', 'name']),
            'highlightRequestId' => (int) $request->query('request_id', 0),
        ]);
    }

    public function extend(Request $request): RedirectResponse
    {
        $request->validate([
            'onboarding_batch_id' => ['required', 'integer', 'exists:onboarding_batches,id'],
            'extended_until' => ['required', 'date'],
        ]);

        $batch = OnboardingBatch::query()->findOrFail((int) $request->input('onboarding_batch_id'));
        if (! $batch->isLocked() || ! $batch->locked_at) {
            abort(422);
        }
        $d = $request->date('extended_until');
        $batch->update([
            'pdf_deadline_extended_until' => $d ? $d->copy()->endOfDay() : null,
        ]);

        return back()->with('status', 'Deadline updated.');
    }

    public function waive(Request $request): RedirectResponse
    {
        $request->validate([
            'onboarding_batch_id' => ['required', 'integer', 'exists:onboarding_batches,id'],
        ]);

        $batch = OnboardingBatch::query()->findOrFail((int) $request->input('onboarding_batch_id'));
        if (! $batch->isLocked() || ! $batch->locked_at) {
            abort(422);
        }
        $batch->update(['pdf_compliance_waived' => true]);

        return back()->with('status', 'Compliance waived for this batch.');
    }

    public function undoReject(Request $request): RedirectResponse
    {
        $request->validate([
            'hub_id' => ['required', 'integer', 'exists:hubs,id'],
            'district_id' => ['required', 'integer', 'exists:districts,id'],
            'cfa_submission_id' => ['required', 'integer', 'exists:cfa_submissions,id'],
        ]);

        $this->batches->stateUndoReject(
            (int) $request->input('hub_id'),
            (int) $request->input('district_id'),
            (int) $request->input('cfa_submission_id'),
            $request->user()
        );

        return back()->with('status', 'Reject cleared for that CFA.');
    }

    public function approveEditRequest(Request $request): RedirectResponse
    {
        $request->validate([
            'request_id' => ['required', 'integer', 'exists:onboarding_batch_edit_requests,id'],
        ]);

        $row = OnboardingBatchEditRequest::query()->with('batch')->findOrFail((int) $request->input('request_id'));
        if ($row->status !== 'pending' || ! $row->batch || ! $row->batch->isLocked()) {
            abort(422);
        }

        DB::transaction(function () use ($row, $request): void {
            $row->update([
                'status' => 'approved',
                'approved_by' => (int) $request->user()->id,
                'approved_at' => now(),
            ]);

            $row->batch->update([
                'edit_unlocked_at' => now(),
                'edit_unlocked_by_request_id' => (int) $row->id,
            ]);
        });

        $this->auditLogger->record(
            $request,
            'hub_batch.unlock_approved',
            OnboardingBatch::class,
            (int) $row->onboarding_batch_id,
            null,
            [
                'request_id' => (int) $row->id,
                'requested_by' => (int) $row->requested_by,
                'reason' => (string) $row->reason,
            ],
            'State admin approved locked-batch edit request'
        );

        return back()->with('status', 'Batch edit request approved. Hub admin can now edit until manual re-lock.');
    }
}
