<?php

namespace App\Support;

use App\Models\StakeholderCapacityBuildingSession;
use App\Models\User;

final class CapacityBuildingStakeholdersAccess
{
    /**
     * @return list<string>
     */
    public static function submitterEmails(): array
    {
        $emails = config('muy.capacity_building_stakeholder_submitters', []);

        return array_values(array_filter(array_map(
            fn (mixed $email): string => strtolower(trim((string) $email)),
            is_array($emails) ? $emails : [],
        )));
    }

    public static function canSubmit(?User $user): bool
    {
        if (! $user || $user->role !== 'state_staff') {
            return false;
        }

        $allowed = self::submitterEmails();
        if ($allowed === []) {
            return false;
        }

        return in_array(strtolower(trim((string) $user->email)), $allowed, true);
    }

    public static function canViewDashboard(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->role === 'state_admin') {
            return true;
        }

        return self::canSubmit($user);
    }

    public static function canEdit(?User $user, StakeholderCapacityBuildingSession $row): bool
    {
        if (! $user || ! self::canSubmit($user)) {
            return false;
        }

        return (int) $row->submitted_by_user_id === (int) $user->id;
    }

    public static function canDelete(?User $user, StakeholderCapacityBuildingSession $row): bool
    {
        return self::canEdit($user, $row);
    }
}
