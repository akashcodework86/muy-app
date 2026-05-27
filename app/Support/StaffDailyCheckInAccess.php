<?php

namespace App\Support;

use App\Models\User;

final class StaffDailyCheckInAccess
{
    /** Roles that must mark daily attendance with GPS (all internal staff except state admin & incubatee). */
    private const EXCLUDED_ROLES = ['state_admin', 'incubatee'];

    public static function isRequired(?User $user): bool
    {
        if (! $user || ! $user->is_active) {
            return false;
        }

        return ! in_array((string) $user->role, self::EXCLUDED_ROLES, true);
    }

    public static function reminderHour(): int
    {
        return 9;
    }
}
