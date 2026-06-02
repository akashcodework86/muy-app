<?php

namespace App\Services;

use App\Models\OnboardingBatch;
use App\Models\ServiceCase;
use App\Models\DistrictServiceSpoc;
use App\Models\OnboardingBatchEditRequest;
use App\Models\StateTask;
use App\Models\StateTaskAssignment;
use App\Models\User;

class NotificationReminderService
{
    public function __construct(
        private HubBatchService $hubBatchService,
    ) {}

    /**
     * @return list<array{title:string, body:string, link:string, unread:bool}>
     */
    public function remindersFor(User $user): array
    {
        $out = [];

        if ($user->role === 'hub_admin' && (int) $user->hub_id > 0) {
            $overdue = $this->hubBatchService->countOverdueBatches((int) $user->hub_id);
            $pending = $this->hubBatchService->countPendingCdo((int) $user->hub_id);

            if ($overdue > 0) {
                $out[] = [
                    'title' => 'CDO letter overdue',
                    'body' => $overdue.' locked batch(es) are overdue for CDO PDF upload. Please upload now.',
                    'link' => route('hub.batches.index'),
                    'unread' => true,
                ];
            }
            if ($pending > 0) {
                $out[] = [
                    'title' => 'CDO letter pending',
                    'body' => $pending.' locked batch(es) still need CDO PDF before deadline.',
                    'link' => route('hub.batches.index'),
                    'unread' => true,
                ];
            }
        }

        if ($user->role === 'state_admin') {
            $locked = OnboardingBatch::query()
                ->where('status', 'locked')
                ->whereNotNull('locked_at')
                ->get();

            $overdue = $locked->filter(fn (OnboardingBatch $b) => $this->hubBatchService->cdoIsOverdue($b))->count();
            $pending = $locked->filter(fn (OnboardingBatch $b) => $this->hubBatchService->cdoIsPendingWithinWindow($b))->count();

            if ($overdue > 0) {
                $out[] = [
                    'title' => 'CDO compliance overdue',
                    'body' => $overdue.' batch(es) are overdue. You can extend timeline or waive from Batch CDO PDF screen.',
                    'link' => route('admin.hub-batch-compliance.index'),
                    'unread' => true,
                ];
            }
            if ($pending > 0) {
                $out[] = [
                    'title' => 'CDO upload reminders',
                    'body' => $pending.' locked batch(es) are still pending CDO PDF upload within timeline.',
                    'link' => route('admin.hub-batch-compliance.index'),
                    'unread' => true,
                ];
            }

            $newlyBuiltCount = OnboardingBatch::query()
                ->where('created_at', '>=', now()->subDays(3))
                ->count();

            if ($newlyBuiltCount > 0) {
                $out[] = [
                    'title' => 'Newly built batches',
                    'body' => $newlyBuiltCount.' new batch(es) were built in the last 3 days.',
                    'link' => route('admin.batches.index'),
                    'unread' => true,
                ];
            }

            $pendingUnlockCount = OnboardingBatchEditRequest::query()
                ->where('status', 'pending')
                ->count();
            if ($pendingUnlockCount > 0) {
                $out[] = [
                    'title' => 'Unlock approvals pending',
                    'body' => $pendingUnlockCount.' batch unlock request(s) are waiting for state admin approval.',
                    'link' => route('admin.hub-batch-compliance.requests', ['status' => 'pending']),
                    'unread' => true,
                ];
            }

            $submittedAssignments = StateTaskAssignment::query()
                ->where('status', StateTaskAssignment::STATUS_SUBMITTED)
                ->whereHas('task', fn ($q) => $q->where('status', StateTask::STATUS_PUBLISHED))
                ->count();

            if ($submittedAssignments > 0) {
                $out[] = [
                    'title' => 'State tasks awaiting review',
                    'body' => $submittedAssignments.' assignment(s) were submitted by state staff and need your review.',
                    'link' => route('admin.state-tasks.index'),
                    'unread' => true,
                ];
            }
        }

        if ($user->role === 'district_staff') {
            $pendingQuery = ServiceCase::query()
                ->where('submitted_by', (int) $user->id)
                ->where('status', ServiceCase::STATUS_PENDING_APPROVAL);
            $pendingCount = (clone $pendingQuery)->count();
            $pendingOverdue = (clone $pendingQuery)
                ->whereNotNull('submitted_at')
                ->where('submitted_at', '<', now()->subDays(3))
                ->count();
            if ($pendingCount > 0) {
                $body = $pendingCount.' service case(s) are waiting for SPOC review.';
                if ($pendingOverdue > 0) {
                    $body .= ' '.$pendingOverdue.' pending for more than 3 days.';
                }
                $out[] = [
                    'title' => 'SPOC review pending',
                    'body' => $body,
                    'link' => route('staff.services.index', ['status' => ServiceCase::STATUS_PENDING_APPROVAL]),
                    'unread' => true,
                ];
            }
        }

        if ($user->role === 'state_staff') {
            $districtIds = DistrictServiceSpoc::query()
                ->where('state_staff_user_id', (int) $user->id)
                ->pluck('district_id')
                ->map(fn ($id) => (int) $id)
                ->filter(fn (int $id) => $id > 0)
                ->values()
                ->all();

            if ($districtIds !== []) {
                $pendingQ = ServiceCase::query()
                    ->whereHas('cfaSubmission', fn ($qq) => $qq->whereIn('district_id', $districtIds))
                    ->where('status', ServiceCase::STATUS_PENDING_APPROVAL);
                $pending = (clone $pendingQ)->count();
                $overdue = (clone $pendingQ)
                    ->whereNotNull('sla_deadline_at')
                    ->where('sla_deadline_at', '<', now())
                    ->count();
                if ($pending > 0) {
                    $body = $pending.' service case(s) are waiting in your approval queue.';
                    if ($overdue > 0) {
                        $body .= ' '.$overdue.' are overdue.';
                    }
                    $out[] = [
                        'title' => 'Approval queue pending',
                        'body' => $body,
                        'link' => route('spoc.service-cases.index', ['status' => ServiceCase::STATUS_PENDING_APPROVAL]),
                        'unread' => true,
                    ];
                }
            }

            $openTasks = StateTaskAssignment::query()
                ->where('assignee_user_id', (int) $user->id)
                ->whereIn('status', [
                    StateTaskAssignment::STATUS_PENDING,
                    StateTaskAssignment::STATUS_IN_PROGRESS,
                    StateTaskAssignment::STATUS_SENT_BACK,
                ])
                ->whereHas('task', fn ($q) => $q
                    ->where('status', StateTask::STATUS_PUBLISHED)
                    ->where(function ($qq): void {
                        $qq->whereNull('due_date')
                            ->orWhereDate('due_date', '>=', now()->toDateString());
                    }))
                ->count();

            if ($openTasks > 0) {
                $out[] = [
                    'title' => 'Open state tasks',
                    'body' => $openTasks.' assigned task(s) need your progress update.',
                    'link' => route('spoc.state-tasks.index'),
                    'unread' => true,
                ];
            }

            $overdueTasks = StateTaskAssignment::query()
                ->where('assignee_user_id', (int) $user->id)
                ->whereNotIn('status', [StateTaskAssignment::STATUS_COMPLETED])
                ->whereHas('task', fn ($q) => $q
                    ->where('status', StateTask::STATUS_PUBLISHED)
                    ->whereNotNull('due_date')
                    ->whereDate('due_date', '<', now()->toDateString()))
                ->count();

            if ($overdueTasks > 0) {
                $out[] = [
                    'title' => 'Overdue state tasks',
                    'body' => $overdueTasks.' assigned task(s) are past the due date.',
                    'link' => route('spoc.state-tasks.index'),
                    'unread' => true,
                ];
            }
        }

        return $out;
    }
}

