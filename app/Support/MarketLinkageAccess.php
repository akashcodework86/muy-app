<?php

namespace App\Support;

use App\Models\District;
use App\Models\MarketLinkageSubmission;
use App\Models\User;

class MarketLinkageAccess
{
    public static function canSubmit(?User $user): bool
    {
        return $user !== null && $user->role === 'district_staff' && (int) $user->district_id > 0;
    }

    public static function canViewDashboard(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        if ($user->role === 'hub_admin') {
            return (int) ($user->hub_id ?? 0) > 0;
        }

        return in_array($user->role, ['state_admin', 'district_staff'], true);
    }

    public static function canAccessSubmission(User $user, MarketLinkageSubmission $submission): void
    {
        if ($user->role === 'state_admin') {
            return;
        }

        if ($user->role === 'hub_admin' && (int) ($user->hub_id ?? 0) > 0) {
            $inHub = District::query()
                ->where('hub_id', (int) $user->hub_id)
                ->where('id', (int) $submission->district_id)
                ->exists();
            abort_unless($inHub, 403);

            return;
        }

        if ($user->role === 'district_staff' && (int) $submission->district_id === (int) $user->district_id) {
            return;
        }

        abort(403);
    }
}
