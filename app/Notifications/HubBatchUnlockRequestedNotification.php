<?php

namespace App\Notifications;

use App\Models\OnboardingBatchEditRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class HubBatchUnlockRequestedNotification extends Notification
{
    use Queueable;

    public function __construct(public OnboardingBatchEditRequest $editRequest) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        $this->editRequest->loadMissing(['batch.hub', 'batch.district', 'requester']);

        $batch = $this->editRequest->batch;
        $requester = $this->editRequest->requester;

        return [
            'title' => 'Batch unlock request',
            'body' => ($requester?->name ?? 'Hub admin').' requested unlock for '.($batch?->name ?? ('Batch #'.$this->editRequest->onboarding_batch_id)).'.',
            'unlock_request_id' => (int) $this->editRequest->id,
            'onboarding_batch_id' => (int) $this->editRequest->onboarding_batch_id,
            'hub_name' => $batch?->hub?->name,
            'district_name' => $batch?->district?->name,
            'requester_name' => $requester?->name,
            'reason' => (string) $this->editRequest->reason,
            'expected_changes' => (string) $this->editRequest->expected_changes,
        ];
    }
}

