<?php

namespace App\Support;

use App\Models\CaseStudyShortlist;
use App\Models\District;
use App\Models\User;

class CaseStudyShortlistAccess
{
    public static function canView(User $user): bool
    {
        return in_array($user->role, ['district_staff', 'hub_admin', 'state_admin'], true);
    }

    public static function canCreate(User $user): bool
    {
        return $user->role === 'district_staff' && (int) $user->district_id > 0;
    }

    public static function canRemark(User $user, CaseStudyShortlist $shortlist): bool
    {
        return in_array($user->role, ['hub_admin', 'state_admin'], true)
            && self::canAccessDistrict($user, (int) $shortlist->district_id);
    }

    public static function canRemove(User $user, CaseStudyShortlist $shortlist): bool
    {
        if (! self::canAccessDistrict($user, (int) $shortlist->district_id)) {
            return false;
        }

        return in_array($user->role, ['hub_admin', 'state_admin'], true)
            || ($user->role === 'district_staff' && (int) $shortlist->created_by_user_id === (int) $user->id);
    }

    public static function canAccessDistrict(User $user, int $districtId): bool
    {
        if ($user->role === 'state_admin') {
            return true;
        }

        if ($user->role === 'district_staff') {
            return $districtId > 0 && $districtId === (int) $user->district_id;
        }

        if ($user->role === 'hub_admin' && (int) $user->hub_id > 0) {
            return District::query()->whereKey($districtId)->where('hub_id', (int) $user->hub_id)->exists();
        }

        return false;
    }
}
