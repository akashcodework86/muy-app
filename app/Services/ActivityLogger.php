<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class ActivityLogger
{
    /**
     * @param  array<string, mixed>|null  $meta
     */
    public function log(
        string $type,
        string $title,
        ?User $actor = null,
        ?Model $subject = null,
        ?int $districtId = null,
        ?int $hubId = null,
        ?array $meta = null,
    ): Activity {
        $payload = [
            'type' => $type,
            'title' => mb_substr($title, 0, 240),
            'actor_user_id' => $actor?->id,
            'actor_role' => $actor?->role,
            'actor_name' => $actor?->name,
            'subject_type' => $subject ? $subject::class : null,
            'subject_id' => $subject?->getKey(),
            'district_id' => $districtId ?? (isset($actor->district_id) ? (int) $actor->district_id : null),
            'hub_id' => $hubId ?? (isset($actor->hub_id) ? (int) $actor->hub_id : null),
            'meta' => $meta,
        ];

        try {
            return Activity::query()->create($payload);
        } catch (\Throwable $e) {
            // Failing to log an activity must not break a business flow.
            report($e);

            return tap(new Activity($payload), function ($a): void {
                $a->exists = false;
            });
        }
    }
}
