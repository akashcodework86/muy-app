<?php

namespace App\Services\Deliverables;

use App\Models\District;
use App\Models\Hub;
use App\Models\User;
use Illuminate\Support\Collection;

class ProgramDeliverablesScope
{
    /**
     * @param  list<int>|null  $districtIds  null = statewide (state admin), [] = no access
     */
    public function __construct(
        public readonly string $role,
        public readonly ?int $hubId,
        public readonly ?array $districtIds,
        public readonly bool $usesStateTargets,
    ) {}

    public static function forUser(?User $user): self
    {
        if (! $user) {
            return new self('guest', null, [], false);
        }

        return match ($user->role) {
            'state_admin' => new self('state_admin', null, null, true),
            'hub_admin' => new self(
                'hub_admin',
                $user->hub_id ? (int) $user->hub_id : null,
                $user->hub_id
                    ? District::query()->where('hub_id', (int) $user->hub_id)->pluck('id')->map(fn ($id) => (int) $id)->all()
                    : [],
                false,
            ),
            'district_staff' => new self(
                'district_staff',
                $user->hub_id ? (int) $user->hub_id : null,
                $user->district_id ? [(int) $user->district_id] : [],
                false,
            ),
            'state_staff' => new self('state_staff', null, null, true),
            default => new self((string) $user->role, null, [], false),
        };
    }

    /**
     * District ids used for achievement queries after applying user filter.
     *
     * @return list<int>|null  null = all Uttarakhand (state admin, no district/hub filter)
     */
    public function effectiveDistrictIds(?int $filterDistrictId, ?int $filterHubId = null): ?array
    {
        $ids = $this->districtIds;

        if ($filterHubId !== null && $filterHubId > 0) {
            $hubDistrictIds = District::query()
                ->where('hub_id', $filterHubId)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();

            if ($ids === null) {
                $ids = $hubDistrictIds;
            } else {
                $ids = array_values(array_intersect($ids, $hubDistrictIds));
            }
        }

        if ($filterDistrictId !== null && $filterDistrictId > 0) {
            if ($ids === null || in_array($filterDistrictId, $ids, true)) {
                return [$filterDistrictId];
            }

            return [];
        }

        return $ids;
    }

    public function canPickHub(): bool
    {
        return $this->districtIds === null;
    }

    public function canPickDistrict(): bool
    {
        if ($this->districtIds === null) {
            return true;
        }

        return count($this->districtIds) > 1;
    }

    public function isHubInScope(int $hubId): bool
    {
        if ($hubId <= 0 || ! Hub::query()->whereKey($hubId)->exists()) {
            return false;
        }

        if ($this->districtIds === null) {
            return true;
        }

        if ($this->hubId !== null) {
            return $this->hubId === $hubId;
        }

        return District::query()
            ->where('hub_id', $hubId)
            ->whereIn('id', $this->districtIds)
            ->exists();
    }

    /**
     * @return Collection<int, Hub>
     */
    public function hubsForDropdown(): Collection
    {
        if (! $this->canPickHub()) {
            return collect();
        }

        return Hub::query()->orderBy('sort_order')->orderBy('name')->get(['id', 'name']);
    }

    /**
     * @return Collection<int, District>
     */
    public function districtsForDropdown(): Collection
    {
        if ($this->districtIds === null) {
            return District::query()->with('hub:id,name')->orderBy('name')->get();
        }

        if ($this->districtIds === []) {
            return collect();
        }

        return District::query()
            ->with('hub:id,name')
            ->whereIn('id', $this->districtIds)
            ->orderBy('name')
            ->get();
    }

    public function districtBelongsToHub(int $districtId, int $hubId): bool
    {
        if ($districtId <= 0 || $hubId <= 0) {
            return false;
        }

        return District::query()
            ->whereKey($districtId)
            ->where('hub_id', $hubId)
            ->exists();
    }

    public function scopeLabel(?int $filterDistrictId, ?int $filterHubId = null): string
    {
        if ($filterDistrictId) {
            $name = District::query()->whereKey($filterDistrictId)->value('name');

            return $name ? (string) $name.' district' : 'Selected district';
        }

        if ($filterHubId) {
            $name = Hub::query()->whereKey($filterHubId)->value('name');

            return $name ? (string) $name : 'Selected hub';
        }

        return match ($this->role) {
            'state_admin' => 'All districts (state)',
            'hub_admin' => 'All districts in your hub',
            'district_staff' => 'Your district',
            'state_staff' => 'All districts (state)',
            default => 'Scoped view',
        };
    }
}
