<?php

namespace App\Services;

use App\Models\StateTask;
use App\Models\StateTaskAssignment;
use App\Models\StateTaskAttachment;
use App\Models\StateTaskProgressLog;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StateTaskService
{
    /**
     * @param  array<string, mixed>  $data
     * @param  list<int>  $assigneeIds
     * @param  list<UploadedFile>  $attachments
     */
    public function create(User $admin, array $data, array $assigneeIds, array $attachments = [], bool $publish = false): StateTask
    {
        $this->assertAssigneesAreStateStaff($assigneeIds);

        return DB::transaction(function () use ($admin, $data, $assigneeIds, $attachments, $publish): StateTask {
            $task = StateTask::query()->create([
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'start_date' => $data['start_date'] ?? null,
                'due_date' => $data['due_date'] ?? null,
                'target_value' => $data['target_value'] ?? null,
                'status' => $publish ? StateTask::STATUS_PUBLISHED : StateTask::STATUS_DRAFT,
                'created_by' => $admin->id,
                'published_at' => $publish ? now() : null,
            ]);

            $this->syncAssignments($task, $assigneeIds);
            $this->storeAttachments($task, $attachments, (int) $admin->id);

            return $task->load(['assignments.assignee', 'attachments']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<int>  $assigneeIds
     * @param  list<UploadedFile>  $attachments
     */
    public function update(StateTask $task, array $data, array $assigneeIds, array $attachments = []): StateTask
    {
        if (! $task->isEditable()) {
            throw ValidationException::withMessages([
                'title' => 'This task can no longer be edited.',
            ]);
        }

        $this->assertAssigneesAreStateStaff($assigneeIds);

        return DB::transaction(function () use ($task, $data, $assigneeIds, $attachments): StateTask {
            $task->update([
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'start_date' => $data['start_date'] ?? null,
                'due_date' => $data['due_date'] ?? null,
                'target_value' => $data['target_value'] ?? null,
            ]);

            $this->syncAssignments($task, $assigneeIds);
            $this->storeAttachments($task, $attachments, (int) auth()->id());

            return $task->fresh(['assignments.assignee', 'attachments']);
        });
    }

    public function publish(StateTask $task): StateTask
    {
        if ($task->status !== StateTask::STATUS_DRAFT) {
            throw ValidationException::withMessages([
                'status' => 'Only draft tasks can be published.',
            ]);
        }

        if ($task->assignments()->count() < 1) {
            throw ValidationException::withMessages([
                'assignee_ids' => 'Assign at least one state staff member before publishing.',
            ]);
        }

        $task->update([
            'status' => StateTask::STATUS_PUBLISHED,
            'published_at' => now(),
        ]);

        return $task->fresh(['assignments.assignee', 'attachments']);
    }

    public function close(StateTask $task): StateTask
    {
        if (! in_array($task->status, [StateTask::STATUS_PUBLISHED], true)) {
            throw ValidationException::withMessages([
                'status' => 'Only published tasks can be closed.',
            ]);
        }

        $task->update([
            'status' => StateTask::STATUS_CLOSED,
            'closed_at' => now(),
        ]);

        return $task->fresh(['assignments.assignee', 'attachments']);
    }

    public function cancel(StateTask $task): StateTask
    {
        if (in_array($task->status, [StateTask::STATUS_CLOSED, StateTask::STATUS_CANCELLED], true)) {
            throw ValidationException::withMessages([
                'status' => 'This task is already closed or cancelled.',
            ]);
        }

        $task->update([
            'status' => StateTask::STATUS_CANCELLED,
            'closed_at' => now(),
        ]);

        return $task->fresh(['assignments.assignee', 'attachments']);
    }

    /**
     * @param  array{progress_value?:int|null, staff_note?:string|null, status?:string|null}  $data
     */
    public function updateProgress(StateTaskAssignment $assignment, User $staff, array $data): StateTaskAssignment
    {
        $this->assertStaffCanWorkOn($assignment, $staff);

        $task = $assignment->task;
        if ($task->status !== StateTask::STATUS_PUBLISHED) {
            throw ValidationException::withMessages([
                'status' => 'This task is not open for updates.',
            ]);
        }

        $progressValue = array_key_exists('progress_value', $data)
            ? max(0, (int) $data['progress_value'])
            : (int) $assignment->progress_value;

        if ($task->target_value !== null && $progressValue > $task->target_value) {
            throw ValidationException::withMessages([
                'progress_value' => 'Progress cannot exceed the target of '.$task->target_value.'.',
            ]);
        }

        $status = $data['status'] ?? StateTaskAssignment::STATUS_IN_PROGRESS;
        if (! in_array($status, [
            StateTaskAssignment::STATUS_IN_PROGRESS,
            StateTaskAssignment::STATUS_PENDING,
        ], true)) {
            $status = StateTaskAssignment::STATUS_IN_PROGRESS;
        }

        if ($progressValue > 0) {
            $status = StateTaskAssignment::STATUS_IN_PROGRESS;
        }

        $assignment->update([
            'status' => $status,
            'progress_value' => $progressValue,
            'staff_note' => $data['staff_note'] ?? $assignment->staff_note,
            'admin_note' => null,
        ]);

        $this->logProgress($assignment, (int) $staff->id, 'updated', $progressValue, $data['staff_note'] ?? null);

        return $assignment->fresh(['task', 'assignee', 'progressLogs.user']);
    }

    public function submitProgress(StateTaskAssignment $assignment, User $staff, ?string $note = null): StateTaskAssignment
    {
        $this->assertStaffCanWorkOn($assignment, $staff);

        $task = $assignment->task;
        if ($task->status !== StateTask::STATUS_PUBLISHED) {
            throw ValidationException::withMessages([
                'status' => 'This task is not open for submission.',
            ]);
        }

        if (! in_array($assignment->status, [
            StateTaskAssignment::STATUS_PENDING,
            StateTaskAssignment::STATUS_IN_PROGRESS,
            StateTaskAssignment::STATUS_SENT_BACK,
        ], true)) {
            throw ValidationException::withMessages([
                'status' => 'This assignment cannot be submitted in its current state.',
            ]);
        }

        $assignment->update([
            'status' => StateTaskAssignment::STATUS_SUBMITTED,
            'staff_note' => $note ?? $assignment->staff_note,
            'submitted_at' => now(),
            'admin_note' => null,
        ]);

        $this->logProgress($assignment, (int) $staff->id, 'submitted', (int) $assignment->progress_value, $note);

        return $assignment->fresh(['task', 'assignee', 'progressLogs.user']);
    }

    public function completeAssignment(StateTaskAssignment $assignment, User $admin, ?string $note = null): StateTaskAssignment
    {
        if ($assignment->status !== StateTaskAssignment::STATUS_SUBMITTED) {
            throw ValidationException::withMessages([
                'status' => 'Only submitted assignments can be marked complete.',
            ]);
        }

        $assignment->update([
            'status' => StateTaskAssignment::STATUS_COMPLETED,
            'completed_at' => now(),
            'completed_by' => $admin->id,
            'admin_note' => $note,
        ]);

        $this->logProgress($assignment, (int) $admin->id, 'completed', (int) $assignment->progress_value, $note);

        return $assignment->fresh(['task', 'assignee', 'progressLogs.user']);
    }

    public function sendBackAssignment(StateTaskAssignment $assignment, User $admin, string $note): StateTaskAssignment
    {
        if ($assignment->status !== StateTaskAssignment::STATUS_SUBMITTED) {
            throw ValidationException::withMessages([
                'status' => 'Only submitted assignments can be sent back.',
            ]);
        }

        $assignment->update([
            'status' => StateTaskAssignment::STATUS_SENT_BACK,
            'admin_note' => $note,
            'submitted_at' => null,
        ]);

        $this->logProgress($assignment, (int) $admin->id, 'sent_back', (int) $assignment->progress_value, $note);

        return $assignment->fresh(['task', 'assignee', 'progressLogs.user']);
    }

    public function deleteAttachment(StateTaskAttachment $attachment): void
    {
        $attachment->deleteFileIfLocal();
        $attachment->delete();
    }

    /**
     * @return Collection<int, User>
     */
    public function activeStateStaff(): Collection
    {
        return User::query()
            ->where('role', 'state_staff')
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'designation_id']);
    }

    /**
     * @param  list<int>  $assigneeIds
     */
    private function syncAssignments(StateTask $task, array $assigneeIds): void
    {
        $assigneeIds = collect($assigneeIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($assigneeIds === []) {
            throw ValidationException::withMessages([
                'assignee_ids' => 'Select at least one state staff member.',
            ]);
        }

        $existing = $task->assignments()->pluck('assignee_user_id')->map(fn ($id) => (int) $id)->all();
        $toRemove = array_diff($existing, $assigneeIds);

        if ($toRemove !== []) {
            $hasCompleted = $task->assignments()
                ->whereIn('assignee_user_id', $toRemove)
                ->where('status', StateTaskAssignment::STATUS_COMPLETED)
                ->exists();

            if ($hasCompleted) {
                throw ValidationException::withMessages([
                    'assignee_ids' => 'Cannot remove assignees who have already completed this task.',
                ]);
            }

            $task->assignments()->whereIn('assignee_user_id', $toRemove)->delete();
        }

        foreach ($assigneeIds as $assigneeId) {
            StateTaskAssignment::query()->firstOrCreate(
                [
                    'state_task_id' => $task->id,
                    'assignee_user_id' => $assigneeId,
                ],
                [
                    'status' => StateTaskAssignment::STATUS_PENDING,
                    'progress_value' => 0,
                ],
            );
        }
    }

    /**
     * @param  list<int>  $assigneeIds
     */
    private function assertAssigneesAreStateStaff(array $assigneeIds): void
    {
        $assigneeIds = collect($assigneeIds)->map(fn ($id) => (int) $id)->filter(fn (int $id) => $id > 0)->unique()->values();

        if ($assigneeIds->isEmpty()) {
            throw ValidationException::withMessages([
                'assignee_ids' => 'Select at least one state staff member.',
            ]);
        }

        $validCount = User::query()
            ->whereIn('id', $assigneeIds)
            ->where('role', 'state_staff')
            ->where('is_active', true)
            ->count();

        if ($validCount !== $assigneeIds->count()) {
            throw ValidationException::withMessages([
                'assignee_ids' => 'All assignees must be active state staff users.',
            ]);
        }
    }

    /**
     * @param  list<UploadedFile>  $files
     */
    private function storeAttachments(StateTask $task, array $files, int $uploadedBy): void
    {
        foreach ($files as $file) {
            if (! $file instanceof UploadedFile || ! $file->isValid()) {
                continue;
            }

            $path = $file->store('state-task-attachments/'.$task->id, 'local');

            StateTaskAttachment::query()->create([
                'state_task_id' => $task->id,
                'disk' => 'local',
                'path' => $path,
                'original_name' => $file->getClientOriginalName() ?: 'upload',
                'mime_type' => $file->getMimeType(),
                'size_bytes' => (int) $file->getSize(),
                'uploaded_by' => $uploadedBy,
            ]);
        }
    }

    private function assertStaffCanWorkOn(StateTaskAssignment $assignment, User $staff): void
    {
        abort_unless($staff->role === 'state_staff', 403);
        abort_unless((int) $assignment->assignee_user_id === (int) $staff->id, 403);
    }

    private function logProgress(
        StateTaskAssignment $assignment,
        int $userId,
        string $action,
        ?int $progressValue,
        ?string $note,
    ): void {
        StateTaskProgressLog::query()->create([
            'state_task_assignment_id' => $assignment->id,
            'user_id' => $userId,
            'action' => $action,
            'progress_value' => $progressValue,
            'note' => $note,
            'created_at' => now(),
        ]);
    }
}
