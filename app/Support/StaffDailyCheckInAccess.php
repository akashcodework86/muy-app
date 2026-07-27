<?php

namespace App\Support;

use App\Models\User;

final class StaffDailyCheckInAccess
{
    /** Roles that do not mark daily attendance with GPS (state admin, incubatee, SPOC). */
    public const EXCLUDED_ROLES = ['state_admin', 'incubatee', 'state_staff'];

    public const EXCLUDED_DESIGNATION = 'CDO';

    public static function isRequired(?User $user): bool
    {
        if (! $user || ! $user->is_active) {
            return false;
        }

        if (in_array((string) $user->role, self::EXCLUDED_ROLES, true)) {
            return false;
        }

        if ($user->designationRecord?->name === self::EXCLUDED_DESIGNATION) {
            return false;
        }

        return true;
    }

    public static function reminderHour(): int
    {
        return 9;
    }
}
