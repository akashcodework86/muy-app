<?php

namespace App\Services\FieldCoordinatorReports;

use App\Models\District;
use App\Models\DistrictServiceSpoc;
use App\Models\FieldCoordinatorAttendanceReport;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class FieldCoordinatorReportScope
{
    /**
     * @param  list<int>|null  $districtIds  null = statewide (state admin)
     */
    public function __construct(
        public readonly string $role,
        public readonly ?int $hubId,
        public readonly ?array $districtIds,
        public readonly ?int $coordinatorUserId,
        public readonly bool $canFilterHub,
        public readonly bool $canFilterDistrict,
        public readonly bool $canFilterCoordinator,
        public readonly string $scopeLabel,
    ) {}

    public static function forUser(?User $user): self
    {
        if (! $user) {
            return new self('guest', null, [], null, false, false, false, 'No access');
        }

        if ($user->role === 'state_admin') {
            return new self(
                'state_admin',
                null,
                null,
                null,
                true,
                true,
                true,
                'Statewide — all field coordinator reports',
            );
        }

        if ($user->role === 'hub_admin') {
            $hubId = $user->hub_id ? (int) $user->hub_id : null;
            $districtIds = $hubId
                ? District::query()->where('hub_id', $hubId)->pluck('id')->map(fn ($id) => (int) $id)->all()
                : [];

            return new self(
                'hub_admin',
                $hubId,
                $districtIds,
                null,
                false,
                true,
                true,
                'Hub districts — all coordinators in your hub',
            );
        }

        if ($user->role === 'state_staff') {
            $districtIds = DistrictServiceSpoc::query()
                ->where('state_staff_user_id', (int) $user->id)
                ->pluck('district_id')
                ->map(fn ($id) => (int) $id)
                ->filter(fn (int $id) => $id > 0)
                ->unique()
                ->values()
                ->all();

            return new self(
                'state_staff',
                null,
                $districtIds,
                null,
                false,
                count($districtIds) > 1,
                true,
                'Assigned districts — field coordinators in your SPOC districts',
            );
        }

        if ($user->role === 'district_staff') {
            $districtId = $user->district_id ? (int) $user->district_id : null;
            $isFieldCoordinator = self::isFieldCoordinator($user);

            return new self(
                'district_staff',
                $user->hub_id ? (int) $user->hub_id : null,
                $districtId ? [$districtId] : [],
                $isFieldCoordinator ? (int) $user->id : null,
                false,
                false,
                ! $isFieldCoordinator,
                $isFieldCoordinator
                    ? 'Your field visit reports'
                    : 'District — all field coordinators in your district',
            );
        }

        return new self((string) $user->role, null, [], null, false, false, false, 'No access');
    }

    public static function isFieldCoordinator(User $user): bool
    {
        $user->loadMissing('designationRecord');
        $designation = strtolower(trim((string) ($user->designationRecord?->name ?? '')));

        return str_contains($designation, 'field coordinator')
            || str_contains($designation, 'field co-ordinator');
    }

    public function applyToQuery(Builder $query): void
    {
        if ($this->coordinatorUserId !== null) {
            $query->where('field_coordinator_user_id', $this->coordinatorUserId);

            return;
        }

        if ($this->districtIds === []) {
            $query->whereRaw('1 = 0');

            return;
        }

        if (is_array($this->districtIds)) {
            $query->whereIn('district_id', $this->districtIds);
        }
    }

    public function canViewReport(FieldCoordinatorAttendanceReport $report): bool
    {
        if ($this->coordinatorUserId !== null) {
            return (int) $report->field_coordinator_user_id === $this->coordinatorUserId;
        }

        if ($this->districtIds === []) {
            return false;
        }

        if (is_array($this->districtIds)) {
            return in_array((int) $report->district_id, $this->districtIds, true);
        }

        return true;
    }

    /**
     * @return list<int>
     */
    public function effectiveDistrictIds(?int $filterDistrictId): array
    {
        if ($filterDistrictId !== null && $filterDistrictId > 0) {
            if ($this->districtIds === null || in_array($filterDistrictId, $this->districtIds, true)) {
                return [$filterDistrictId];
            }

            return [];
        }

        if ($this->districtIds === null) {
            return [];
        }

        return $this->districtIds;
    }
}
