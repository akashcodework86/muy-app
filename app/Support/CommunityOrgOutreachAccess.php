<?php

namespace App\Support;

use App\Models\CommunityOrganizationOutreachVisit;
use App\Models\User;
use App\Support\MisFieldActivityApproval;

final class CommunityOrgOutreachAccess
{
    private static function normalizedDesignation(?User $user): string
    {
        $user?->loadMissing('designationRecord');

        return strtolower(trim((string) ($user?->designationRecord?->name ?? '')));
    }

    public static function isMuySpoke(?User $user): bool
    {
        return $user?->role === 'district_staff'
            && str_contains(self::normalizedDesignation($user), 'muy spoke');
    }

    public static function isIncubationManager(?User $user): bool
    {
        return $user?->role === 'district_staff'
            && str_contains(self::normalizedDesignation($user), 'incubation manager');
    }

    public static function isBpde(?User $user): bool
    {
        if ($user?->role !== 'district_staff') {
            return false;
        }

        $designation = self::normalizedDesignation($user);

        return str_contains($designation, 'bpde')
            || str_contains($designation, 'business planning');
    }

    public static function isDistrictOutreachSubmitter(?User $user): bool
    {
        return $user?->role === 'district_staff'
            && (self::isMuySpoke($user) || self::isIncubationManager($user) || self::isBpde($user))
            && (int) ($user->district_id ?? 0) > 0;
    }

    public static function canSubmit(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->role === 'hub_admin' && (int) ($user->hub_id ?? 0) > 0) {
            return true;
        }

        return self::isDistrictOutreachSubmitter($user);
    }

    public static function canViewDashboard(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->role === 'state_admin') {
            return true;
        }

        if (MisFieldActivityApproval::isDedicatedApprover($user)) {
            return true;
        }

        return self::canSubmit($user);
    }

    public static function canEdit(?User $user, CommunityOrganizationOutreachVisit $row): bool
    {
        if (! $user || ! self::canSubmit($user)) {
            return false;
        }

        return MisFieldActivityApproval::submitterCanEdit($user, $row);
    }

    public static function canDelete(?User $user, CommunityOrganizationOutreachVisit $row): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->role === 'state_admin') {
            return true;
        }

        return MisFieldActivityApproval::submitterCanWithdraw($user, $row);
    }

    public static function routePrefixForUser(?User $user): string
    {
        if (! $user) {
            return 'staff.';
        }

        return match ($user->role) {
            'hub_admin' => 'hub.',
            'district_staff' => 'staff.',
            default => 'hub.',
        };
    }
}
