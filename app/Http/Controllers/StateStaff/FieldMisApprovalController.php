<?php

namespace App\Http\Controllers\StateStaff;

use App\Http\Controllers\Controller;
use App\Models\ServiceCase;
use App\Support\MisFieldActivityApproval;
use App\Services\MisFieldActivityWorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FieldMisApprovalController extends Controller
{
    public function __construct(
        private MisFieldActivityWorkflowService $workflow,
    ) {}

    public function index(Request $request): RedirectResponse
    {
        $this->approverOrAbort($request);

        return redirect()->route('spoc.service-cases.index', array_filter([
            'status' => $request->query('status', ServiceCase::STATUS_PENDING_APPROVAL),
            'service_id' => $request->query('module'),
            'q' => $request->query('q'),
        ]));
    }

    public function show(Request $request, string $module, int $record): View
    {
        $this->approverOrAbort($request);
        $meta = MisFieldActivityApproval::module($module);
        $model = MisFieldActivityApproval::findRecord($module, $record);
        $this->loadRecordRelations($module, $model);

        return view('spoc.field-mis-approvals.show', [
            'moduleKey' => $module,
            'moduleMeta' => $meta,
            'record' => $model,
            'row' => $model,
            'currentRole' => 'state_staff',
            'applicantSnapshots' => (array) ($model->selected_incubatees_snapshot ?? []),
        ]);
    }

    public function approve(Request $request, string $module, int $record): RedirectResponse
    {
        $approver = $this->approverOrAbort($request);
        $model = MisFieldActivityApproval::findRecord($module, $record);

        $this->workflow->approve($model, $approver);

        return $this->redirectAfterAction($request, 'Field MIS entry approved.');
    }

    public function sendBack(Request $request, string $module, int $record): RedirectResponse
    {
        $approver = $this->approverOrAbort($request);
        $validated = $request->validate([
            'note' => ['required', 'string', 'max:2000'],
        ]);

        $model = MisFieldActivityApproval::findRecord($module, $record);
        $this->workflow->sendBack($model, $approver, (string) $validated['note']);

        return $this->redirectAfterAction($request, 'Field MIS entry sent back to submitter.');
    }

    public function reject(Request $request, string $module, int $record): RedirectResponse
    {
        $approver = $this->approverOrAbort($request);
        $validated = $request->validate([
            'note' => ['required', 'string', 'max:2000'],
        ]);

        $model = MisFieldActivityApproval::findRecord($module, $record);
        $this->workflow->reject($model, $approver, (string) $validated['note']);

        return $this->redirectAfterAction($request, 'Field MIS entry rejected.');
    }

    private function approverOrAbort(Request $request)
    {
        $user = $request->user();
        abort_unless($user && MisFieldActivityApproval::isDedicatedApprover($user), 403);

        return $user;
    }

    private function loadRecordRelations(string $moduleKey, $model): void
    {
        match ($moduleKey) {
            'technical_training' => $model->loadMissing(['district:id,name', 'submitter:id,name', 'misFieldSpoc:id,name']),
            'lakhpati_technical_training' => $model->loadMissing(['district:id,name', 'submitter:id,name', 'districtBlock:id,name', 'gramPanchayat:id,name', 'misFieldSpoc:id,name']),
            'line_department_meeting' => $model->loadMissing(['submitter:id,name', 'misFieldSpoc:id,name']),
            'community_org_outreach' => $model->loadMissing(['district:id,name', 'hub:id,name', 'submitter:id,name', 'misFieldSpoc:id,name']),
            default => $model->loadMissing(['misFieldSpoc:id,name', 'submitter:id,name']),
        };
    }

    private function redirectAfterAction(Request $request, string $message): RedirectResponse
    {
        $status = (string) $request->input('_redirect_status', ServiceCase::STATUS_PENDING_APPROVAL);
        $module = (string) $request->input('_redirect_module', '');

        return redirect()
            ->route('spoc.service-cases.index', array_filter([
                'status' => $status !== '' ? $status : null,
                'service_id' => $module !== '' ? $module : null,
                'q' => $request->input('_redirect_q'),
            ]))
            ->with('status', $message);
    }
}
