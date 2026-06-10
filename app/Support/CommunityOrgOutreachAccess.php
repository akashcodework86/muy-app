<?php

namespace App\Support;

use App\Models\CommunityOrganizationOutreachVisit;
use App\Models\User;

final class CommunityOrgOutreachAccess
{
    public static function canSubmit(?User $user): bool
    {
        return $user
            && $user->role === 'hub_admin'
            && (int) ($user->hub_id ?? 0) > 0;
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

    public static function canDelete(?User $user, CommunityOrganizationOutreachVisit $row): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->role === 'state_admin') {
            return true;
        }

        return self::canSubmit($user)
            && (int) $row->hub_id === (int) $user->hub_id
            && (int) $row->submitted_by_user_id === (int) $user->id;
    }
}
