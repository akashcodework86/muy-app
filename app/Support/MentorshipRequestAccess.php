<?php

namespace App\Support;

use App\Models\District;
use App\Models\DistrictServiceSpoc;
use App\Models\MentorshipRequest;
use App\Models\User;

final class MentorshipRequestAccess
{
    public static function isIncubationManager(?User $user): bool
    {
        if ($user?->role !== 'district_staff' || (int) ($user->district_id ?? 0) <= 0) {
            return false;
        }

        $user->loadMissing('designationRecord');
        $designation = strtolower(trim((string) ($user->designationRecord?->name ?? '')));

        return str_contains($designation, 'incubation manager');
    }

    public static function canViewDashboard(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        if (in_array($user->role, ['state_admin', 'hub_admin', 'district_staff'], true)) {
            if ($user->role === 'hub_admin') {
                return (int) ($user->hub_id ?? 0) > 0;
            }
            if ($user->role === 'district_staff') {
                return (int) ($user->district_id ?? 0) > 0;
            }

            return true;
        }

        return $user->role === 'state_staff';
    }

    public static function canHandle(?User $user): bool
    {
        return self::isIncubationManager($user);
    }

    public static function canHandleRequest(?User $user, MentorshipRequest $request): bool
    {
        if (! self::canHandle($user)) {
            return false;
        }

        $request->loadMissing('cfaSubmission');
        $districtId = (int) ($request->cfaSubmission?->district_id ?? 0);

        return $districtId > 0 && $districtId === (int) $user->district_id;
    }

    public static function routePrefixForUser(?User $user): string
    {
        return match ($user?->role) {
            'state_admin' => 'admin.',
            'hub_admin' => 'hub.',
            'district_staff' => 'staff.',
            default => 'spoc.',
        };
    }

    /**
     * District IDs this user may see. Empty array = none. Null = all districts.
     *
     * @return list<int>|null
     */
    public static function visibleDistrictIds(?User $user): ?array
    {
        if (! $user) {
            return [];
        }

        if ($user->role === 'state_admin') {
            return null;
        }

        if ($user->role === 'hub_admin') {
            $hubId = (int) ($user->hub_id ?? 0);
            if ($hubId <= 0) {
                return [];
            }

            return District::query()
                ->where('hub_id', $hubId)
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->filter(fn (int $id): bool => $id > 0)
                ->values()
                ->all();
        }

        if ($user->role === 'district_staff') {
            $districtId = (int) ($user->district_id ?? 0);

            return $districtId > 0 ? [$districtId] : [];
        }

        if ($user->role === 'state_staff') {
            return DistrictServiceSpoc::query()
                ->where('state_staff_user_id', (int) $user->id)
                ->pluck('district_id')
                ->map(fn ($id): int => (int) $id)
                ->filter(fn (int $id): bool => $id > 0)
                ->values()
                ->all();
        }

        return [];
    }
}
