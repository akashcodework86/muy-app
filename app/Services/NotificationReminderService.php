<?php

namespace App\Services;

use App\Models\OnboardingBatch;
use App\Models\OnboardingBatchEditRequest;
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
        }

        return $out;
    }
}

