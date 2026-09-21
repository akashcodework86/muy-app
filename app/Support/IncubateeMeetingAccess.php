<?php

namespace App\Support;

use App\Models\IncubateeMeeting;
use App\Models\User;

final class IncubateeMeetingAccess
{
    public static function isIncubationManager(?User $user): bool
    {
        return MentorshipRequestAccess::isIncubationManager($user);
    }

    public static function isBpde(?User $user): bool
    {
        return CommunityOrgOutreachAccess::isBpde($user)
            && (int) ($user?->district_id ?? 0) > 0;
    }

    public static function canViewDashboard(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->role === 'state_admin' || $user->role === 'state_staff') {
            return true;
        }

        if ($user->role === 'hub_admin') {
            return (int) ($user->hub_id ?? 0) > 0;
        }

        if ($user->role === 'district_staff') {
            return self::isIncubationManager($user) || self::isBpde($user);
        }

        return false;
    }

    public static function canCreate(?User $user): bool
    {
        return self::canViewDashboard($user);
    }

    public static function canManage(?User $user, IncubateeMeeting $meeting): bool
    {
        if (! $user || ! self::canViewDashboard($user)) {
            return false;
        }

        return (int) $meeting->created_by_user_id === (int) $user->id
            && $meeting->isScheduled();
    }

    public static function routePrefixForUser(?User $user): string
    {
        return MentorshipRequestAccess::routePrefixForUser($user);
    }
}
