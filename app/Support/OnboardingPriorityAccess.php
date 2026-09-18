<?php

namespace App\Support;

use App\Models\District;
use App\Models\User;

final class OnboardingPriorityAccess
{
    public static function canView(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return match ($user->role) {
            'state_admin' => true,
            'hub_admin' => (int) ($user->hub_id ?? 0) > 0,
            'district_staff' => CommunityOrgOutreachAccess::isIncubationManager($user),
            default => false,
        };
    }

    public static function routePrefix(?User $user): string
    {
        return match ($user?->role) {
            'state_admin' => 'admin.onboarding-priority',
            'hub_admin' => 'hub.onboarding-priority',
            'district_staff' => 'staff.onboarding-priority',
            default => 'staff.onboarding-priority',
        };
    }

    /**
     * @return list<int>|null Null = all districts (state admin).
     */
    public static function visibleDistrictIds(?User $user): ?array
    {
        if (! $user || ! self::canView($user)) {
            return [];
        }

        if ($user->role === 'state_admin') {
            return null;
        }

        if ($user->role === 'hub_admin') {
            return District::query()
                ->where('hub_id', (int) $user->hub_id)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();
        }

        $districtId = (int) ($user->district_id ?? 0);

        return $districtId > 0 ? [$districtId] : [];
    }

    public static function scopeLabel(?User $user): string
    {
        if (! $user) {
            return '';
        }

        return match ($user->role) {
            'state_admin' => 'Statewide · non-onboarded Phase 3 CFA',
            'hub_admin' => trim((string) ($user->hub?->name ?? 'Hub')).' · hub districts',
            'district_staff' => trim((string) ($user->district?->name ?? 'District')).' · your district',
            default => '',
        };
    }
}
