<?php

namespace App\Http\Controllers\Spoc;

use App\Http\Controllers\Controller;
use App\Models\StateTask;
use App\Models\StateTaskAssignment;
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
    ) {}

    public function index(Request $request): View
    {
        $userId = (int) $request->user()->id;
        $status = (string) $request->query('status', '');

        $query = StateTaskAssignment::query()
            ->with(['task.creator:id,name', 'task.attachments'])
            ->where('assignee_user_id', $userId)
            ->whereHas('task', fn ($q) => $q->whereIn('status', [
                StateTask::STATUS_PUBLISHED,
                StateTask::STATUS_CLOSED,
            ]))
            ->orderByDesc('updated_at');

        if ($status !== '' && in_array($status, StateTaskAssignment::STATUSES, true)) {
            $query->where('status', $status);
        }

        $assignments = $query->paginate(20)->withQueryString();

        $stats = [
            'pending' => StateTaskAssignment::query()
                ->where('assignee_user_id', $userId)
                ->whereIn('status', [StateTaskAssignment::STATUS_PENDING, StateTaskAssignment::STATUS_SENT_BACK])
                ->whereHas('task', fn ($q) => $q->where('status', StateTask::STATUS_PUBLISHED))
                ->count(),
            'in_progress' => StateTaskAssignment::query()
                ->where('assignee_user_id', $userId)
                ->where('status', StateTaskAssignment::STATUS_IN_PROGRESS)
                ->whereHas('task', fn ($q) => $q->where('status', StateTask::STATUS_PUBLISHED))
                ->count(),
            'submitted' => StateTaskAssignment::query()
                ->where('assignee_user_id', $userId)
                ->where('status', StateTaskAssignment::STATUS_SUBMITTED)
                ->count(),
            'overdue' => StateTaskAssignment::query()
                ->where('assignee_user_id', $userId)
                ->whereNotIn('status', [StateTaskAssignment::STATUS_COMPLETED])
                ->whereHas('task', fn ($q) => $q
                    ->where('status', StateTask::STATUS_PUBLISHED)
                    ->whereNotNull('due_date')
                    ->whereDate('due_date', '<', now()->toDateString()))
                ->count(),
        ];

        return view('spoc.state-tasks.index', [
            'assignments' => $assignments,
            'stats' => $stats,
            'filters' => ['status' => $status],
        ]);
    }

    public function show(Request $request, StateTask $stateTask): View
    {
        $assignment = $this->resolveAssignment($request, $stateTask);

        $stateTask->load([
            'creator:id,name',
            'attachments.uploader:id,name',
        ]);
        $assignment->load(['progressLogs.user:id,name']);

        return view('spoc.state-tasks.show', [
            'task' => $stateTask,
            'assignment' => $assignment,
        ]);
    }

    public function updateProgress(Request $request, StateTask $stateTask): RedirectResponse
    {
        $assignment = $this->resolveAssignment($request, $stateTask);

        $validated = $request->validate([
            'progress_value' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'staff_note' => ['nullable', 'string', 'max:5000'],
        ]);

        $this->tasks->updateProgress($assignment, $request->user(), $validated);

        return back()->with('status', 'Progress saved.');
    }

    public function submit(Request $request, StateTask $stateTask): RedirectResponse
    {
        $assignment = $this->resolveAssignment($request, $stateTask);

        $validated = $request->validate([
            'staff_note' => ['nullable', 'string', 'max:5000'],
        ]);

        $this->tasks->submitProgress($assignment, $request->user(), $validated['staff_note'] ?? null);

        return back()->with('status', 'Progress submitted for admin review.');
    }

    public function downloadAttachment(Request $request, StateTask $stateTask, int $attachment): StreamedResponse
    {
        $this->resolveAssignment($request, $stateTask);

        $file = $stateTask->attachments()->whereKey($attachment)->firstOrFail();

        return Storage::disk($file->disk)->download(
            $file->path,
            $file->original_name,
        );
    }

    private function resolveAssignment(Request $request, StateTask $stateTask): StateTaskAssignment
    {
        return StateTaskAssignment::query()
            ->where('state_task_id', $stateTask->id)
            ->where('assignee_user_id', (int) $request->user()->id)
            ->firstOrFail();
    }
}
