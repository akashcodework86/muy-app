<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StateTask;
use App\Models\StateTaskAssignment;
use App\Models\StateTaskAttachment;
use App\Services\AdminAuditLogger;
use App\Services\StateTaskService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StateTaskController extends Controller
{
    public function __construct(
        private readonly StateTaskService $tasks,
        private readonly AdminAuditLogger $auditLogger,
    ) {}

    public function index(Request $request): View
    {
        $status = (string) $request->query('status', '');
        $assigneeId = (int) $request->query('assignee_id', 0);
        $overdueOnly = $request->boolean('overdue');

        $query = StateTask::query()
            ->with(['creator:id,name', 'assignments.assignee:id,name'])
            ->withCount([
                'assignments',
                'assignments as completed_count' => fn ($q) => $q->where('status', StateTaskAssignment::STATUS_COMPLETED),
                'assignments as submitted_count' => fn ($q) => $q->where('status', StateTaskAssignment::STATUS_SUBMITTED),
            ])
            ->orderByDesc('id');

        if ($status !== '' && in_array($status, StateTask::STATUSES, true)) {
            $query->where('status', $status);
        }

        if ($assigneeId > 0) {
            $query->whereHas('assignments', fn ($q) => $q->where('assignee_user_id', $assigneeId));
        }

        if ($overdueOnly) {
            $query->where('status', StateTask::STATUS_PUBLISHED)
                ->whereNotNull('due_date')
                ->whereDate('due_date', '<', now()->toDateString());
        }

        $tasks = $query->paginate(20)->withQueryString();

        return view('admin.state-tasks.index', [
            'tasks' => $tasks,
            'stateStaff' => $this->tasks->activeStateStaff(),
            'filters' => [
                'status' => $status,
                'assignee_id' => $assigneeId,
                'overdue' => $overdueOnly,
            ],
        ]);
    }

    public function create(): View
    {
        return view('admin.state-tasks.create', [
            'stateStaff' => $this->tasks->activeStateStaff(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateTask($request);

        $task = $this->tasks->create(
            $request->user(),
            $validated,
            $validated['assignee_ids'],
            $request->file('attachments', []) ?? [],
            $request->boolean('publish'),
        );

        $this->auditLogger->record(
            $request,
            'state_task.created',
            StateTask::class,
            $task->id,
            null,
            ['title' => $task->title, 'status' => $task->status],
            'State task created',
        );

        return redirect()
            ->route('admin.state-tasks.show', $task)
            ->with('status', $task->status === StateTask::STATUS_PUBLISHED
                ? 'Task published and assigned to state staff.'
                : 'Task saved as draft.');
    }

    public function show(StateTask $stateTask): View
    {
        $stateTask->load([
            'creator:id,name',
            'attachments.uploader:id,name',
            'assignments.assignee:id,name,email',
            'assignments.completedByUser:id,name',
            'assignments.progressLogs.user:id,name',
        ]);

        return view('admin.state-tasks.show', [
            'task' => $stateTask,
        ]);
    }

    public function edit(StateTask $stateTask): View|RedirectResponse
    {
        if (! $stateTask->isEditable()) {
            return redirect()
                ->route('admin.state-tasks.show', $stateTask)
                ->with('status', 'This task can no longer be edited.');
        }

        $stateTask->load(['assignments', 'attachments']);

        return view('admin.state-tasks.edit', [
            'task' => $stateTask,
            'stateStaff' => $this->tasks->activeStateStaff(),
        ]);
    }

    public function update(Request $request, StateTask $stateTask): RedirectResponse
    {
        $validated = $this->validateTask($request);

        $before = $stateTask->only(['title', 'status', 'due_date']);
        $task = $this->tasks->update(
            $stateTask,
            $validated,
            $validated['assignee_ids'],
            $request->file('attachments', []) ?? [],
        );

        $this->auditLogger->record(
            $request,
            'state_task.updated',
            StateTask::class,
            $task->id,
            $before,
            $task->only(['title', 'status', 'due_date']),
            'State task updated',
        );

        return redirect()
            ->route('admin.state-tasks.show', $task)
            ->with('status', 'Task updated.');
    }

    public function publish(Request $request, StateTask $stateTask): RedirectResponse
    {
        $task = $this->tasks->publish($stateTask);

        $this->auditLogger->record(
            $request,
            'state_task.published',
            StateTask::class,
            $task->id,
            null,
            ['status' => $task->status],
            'State task published',
        );

        return back()->with('status', 'Task published.');
    }

    public function close(Request $request, StateTask $stateTask): RedirectResponse
    {
        $task = $this->tasks->close($stateTask);

        $this->auditLogger->record(
            $request,
            'state_task.closed',
            StateTask::class,
            $task->id,
            null,
            ['status' => $task->status],
            'State task closed',
        );

        return back()->with('status', 'Task closed.');
    }

    public function cancel(Request $request, StateTask $stateTask): RedirectResponse
    {
        $task = $this->tasks->cancel($stateTask);

        $this->auditLogger->record(
            $request,
            'state_task.cancelled',
            StateTask::class,
            $task->id,
            null,
            ['status' => $task->status],
            'State task cancelled',
        );

        return back()->with('status', 'Task cancelled.');
    }

    public function completeAssignment(Request $request, StateTask $stateTask, StateTaskAssignment $stateTaskAssignment): RedirectResponse
    {
        abort_unless((int) $stateTaskAssignment->state_task_id === (int) $stateTask->id, 404);

        $validated = $request->validate([
            'admin_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->tasks->completeAssignment($stateTaskAssignment, $request->user(), $validated['admin_note'] ?? null);

        return back()->with('status', 'Assignment marked complete.');
    }

    public function sendBackAssignment(Request $request, StateTask $stateTask, StateTaskAssignment $stateTaskAssignment): RedirectResponse
    {
        abort_unless((int) $stateTaskAssignment->state_task_id === (int) $stateTask->id, 404);

        $validated = $request->validate([
            'admin_note' => ['required', 'string', 'max:2000'],
        ]);

        $this->tasks->sendBackAssignment($stateTaskAssignment, $request->user(), $validated['admin_note']);

        return back()->with('status', 'Assignment sent back to state staff.');
    }

    public function destroyAttachment(StateTask $stateTask, StateTaskAttachment $attachment): RedirectResponse
    {
        abort_unless((int) $attachment->state_task_id === (int) $stateTask->id, 404);

        if (! $stateTask->isEditable()) {
            return back()->withErrors(['attachment' => 'Attachments cannot be removed from a closed task.']);
        }

        $this->tasks->deleteAttachment($attachment);

        return back()->with('status', 'Attachment removed.');
    }

    public function downloadAttachment(StateTask $stateTask, StateTaskAttachment $attachment): StreamedResponse
    {
        abort_unless((int) $attachment->state_task_id === (int) $stateTask->id, 404);

        return Storage::disk($attachment->disk)->download(
            $attachment->path,
            $attachment->original_name,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function validateTask(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'start_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'target_value' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'assignee_ids' => ['required', 'array', 'min:1'],
            'assignee_ids.*' => ['integer', 'exists:users,id'],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => ['file', 'max:10240', 'mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png,zip'],
            'publish' => ['nullable', 'boolean'],
        ]);
    }
}
