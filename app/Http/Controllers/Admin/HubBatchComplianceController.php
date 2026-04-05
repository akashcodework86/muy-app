<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\OnboardingBatch;
use App\Services\HubBatchService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HubBatchComplianceController extends Controller
{
    public function __construct(
        private HubBatchService $batches
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

        return view('admin.hub-batch-compliance.index', ['rows' => $rows]);
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
}
