<?php

namespace App\Support;

use App\Models\LineDepartmentMeeting;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class LineDepartmentMeetingAccess
{
    public static function isStateStaffSpoc(?User $user): bool
    {
        return $user !== null && $user->role === 'state_staff';
    }

    public static function isDistrictStaffSubmitter(?User $user): bool
    {
        return $user?->role === 'district_staff' && (int) ($user->district_id ?? 0) > 0;
    }

    public static function canSubmit(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        if (self::isStateStaffSpoc($user)) {
            return true;
        }

        if ($user->role === 'hub_admin' && (int) ($user->hub_id ?? 0) > 0) {
            return true;
        }

        return self::isDistrictStaffSubmitter($user);
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

    public static function canEdit(?User $user, LineDepartmentMeeting $row): bool
    {
        if (! $user || ! self::canSubmit($user)) {
            return false;
        }

        return MisFieldActivityApproval::submitterCanEdit($user, $row);
    }

    public static function canDelete(?User $user, LineDepartmentMeeting $row): bool
    {
        if ($user?->role === 'state_admin') {
            return true;
        }

        return MisFieldActivityApproval::submitterCanWithdraw($user, $row);
    }

    public static function routePrefixForUser(?User $user): string
    {
        if (! $user) {
            return 'spoc.';
        }

        return match ($user->role) {
            'hub_admin' => 'hub.',
            'district_staff' => 'staff.',
            default => 'spoc.',
        };
    }

    /**
     * District staff may log hub-level meetings without a district_id; include those via submitter.
     *
     * @param  Builder<LineDepartmentMeeting>  $query
     */
    public static function applyDistrictStaffVisibilityScope(Builder $query, User $user): void
    {
        if (! self::isDistrictStaffSubmitter($user)) {
            return;
        }

        $districtId = (int) $user->district_id;
        $submitterIds = self::districtStaffSubmitterIds($districtId);

        $query->where(function (Builder $q) use ($districtId, $submitterIds): void {
            $q->where('district_id', $districtId);
            if ($submitterIds->isNotEmpty()) {
                $q->orWhereIn('submitted_by_user_id', $submitterIds);
            }
        });
    }

    public static function districtStaffCanViewRecord(User $user, LineDepartmentMeeting $row): bool
    {
        if (! self::isDistrictStaffSubmitter($user)) {
            return false;
        }

        if ((int) $row->submitted_by_user_id === (int) $user->id) {
            return true;
        }

        if ((int) $row->district_id === (int) $user->district_id) {
            return true;
        }

        if ($row->district_id === null || (int) $row->district_id === 0) {
            $submitterDistrict = User::query()
                ->whereKey($row->submitted_by_user_id)
                ->value('district_id');

            return (int) $submitterDistrict === (int) $user->district_id;
        }

        return false;
    }

    /**
     * @return Collection<int, int>
     */
    public static function districtStaffSubmitterIds(int $districtId): Collection
    {
        if ($districtId <= 0) {
            return collect();
        }

        return User::query()
            ->where('district_id', $districtId)
            ->where('role', 'district_staff')
            ->pluck('id');
    }
}
