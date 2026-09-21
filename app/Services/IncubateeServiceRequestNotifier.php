<?php

namespace App\Services;

use App\Models\IncubateeServiceRequest;
use App\Models\User;
use App\Notifications\IncubateeServiceRequestedNotification;
use Illuminate\Support\Facades\Notification;

class IncubateeServiceRequestNotifier
{
    public function notify(IncubateeServiceRequest $request): int
    {
        $request->load(['cfaSubmission.district.hub', 'requestedBy']);

        $submission = $request->cfaSubmission;
        $districtId = (int) ($submission->district_id ?? 0);
        $hubId = (int) ($submission->district?->hub_id ?? 0);

        $recipients = User::query()
            ->where('is_active', true)
            ->where(function ($q) use ($districtId, $hubId): void {
                $q->where('role', 'state_admin');
                if ($hubId > 0) {
                    $q->orWhere(function ($q2) use ($hubId): void {
                        $q2->where('role', 'hub_admin')->where('hub_id', $hubId);
                    });
                }
                if ($districtId > 0) {
                    $q->orWhere(function ($q2) use ($districtId): void {
                        $q2->where('role', 'district_staff')->where('district_id', $districtId);
                    });
                }
            })
            ->get()
            ->unique('id')
            ->values();

        if ($recipients->isEmpty()) {
            return 0;
        }

        Notification::send($recipients, new IncubateeServiceRequestedNotification($request));

        return $recipients->count();
    }
}
