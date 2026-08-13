<?php

namespace App\Support;

use App\Models\District;
use App\Models\DistrictServiceSpoc;
use App\Models\User;

final class LegacyDataAccess
{
    public static function canView(?User $user): bool
    {
        return $user !== null && in_array($user->role, [
            'state_admin', 'hub_admin', 'district_staff', 'state_staff',
        ], true);
    }

    public static function canManage(?User $user): bool
    {
        return $user?->role === 'state_admin';
    }

    public static function routePrefix(User $user): string
    {
        return match ($user->role) {
            'hub_admin' => 'hub.legacy-data',
            'district_staff' => 'staff.legacy-data',
            'state_staff' => 'spoc.legacy-data',
            default => 'admin.legacy-data',
        };
    }

    /**
     * Null means statewide access. An empty array means no assigned scope.
     *
     * @return list<string>|null
     */
    public static function districtNames(User $user): ?array
    {
        if ($user->role === 'state_admin') {
            return null;
        }

        $query = District::query();

        if ($user->role === 'hub_admin') {
            if (! $user->hub_id) {
                return [];
            }

            return $query->where('hub_id', (int) $user->hub_id)->pluck('name')->values()->all();
        }

        if ($user->role === 'district_staff') {
            if (! $user->district_id) {
                return [];
            }

            return $query->whereKey((int) $user->district_id)->pluck('name')->values()->all();
        }

        if ($user->role === 'state_staff') {
            $districtIds = DistrictServiceSpoc::query()
                ->where('state_staff_user_id', (int) $user->id)
                ->pluck('district_id');

            return $query->whereIn('id', $districtIds)->pluck('name')->values()->all();
        }

        return [];
    }

    public static function scopeLabel(User $user, ?array $districtNames): string
    {
        if ($districtNames === null) {
            return 'Statewide scope';
        }

        if ($districtNames === []) {
            return 'No district scope assigned';
        }

        return match ($user->role) {
            'hub_admin' => 'Hub scope: '.implode(', ', $districtNames),
            'state_staff' => 'Assigned districts: '.implode(', ', $districtNames),
            default => 'District scope: '.implode(', ', $districtNames),
        };
    }
}
