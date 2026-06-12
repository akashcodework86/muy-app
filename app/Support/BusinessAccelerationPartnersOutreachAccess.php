<?php

namespace App\Support;

use App\Models\BusinessAccelerationPartnerOutreachEntry;
use App\Models\User;

final class BusinessAccelerationPartnersOutreachAccess
{
    public static function canSubmit(?User $user): bool
    {
        return self::isAllowlistedUser($user);
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

    public static function canDelete(?User $user, BusinessAccelerationPartnerOutreachEntry $row): bool
    {
        if (! self::canSubmit($user)) {
            return false;
        }

        return (int) $row->submitted_by_user_id === (int) $user->id;
    }

    private static function isAllowlistedUser(?User $user): bool
    {
        if (! $user || $user->role !== 'state_staff') {
            return false;
        }

        $ids = config('business_acceleration_partners_outreach.submitter_user_ids', []);
        if ($ids !== [] && in_array((int) $user->id, $ids, true)) {
            return true;
        }

        $names = config('business_acceleration_partners_outreach.submitter_names', []);
        if ($names === []) {
            return false;
        }

        $normalized = self::normalizeName((string) $user->name);

        foreach ($names as $allowed) {
            if ($normalized === self::normalizeName((string) $allowed)) {
                return true;
            }
        }

        return false;
    }

    private static function normalizeName(string $name): string
    {
        $name = trim(preg_replace('/\s+/u', ' ', $name) ?? '');

        return function_exists('mb_strtolower') ? mb_strtolower($name) : strtolower($name);
    }
}
