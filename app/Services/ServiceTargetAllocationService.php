<?php

namespace App\Services;

use App\Models\Designation;
use App\Models\DistrictDeliverableAllocationSetting;
use App\Models\StaffMonthlyTarget;
use App\Models\User;

class ServiceTargetAllocationService
{
    /**
     * @return list<int>
     */
    public function splitInteger(int $total, int $parts): array
    {
        if ($parts <= 0) {
            return [];
        }

        $base = intdiv($total, $parts);
        $remainder = $total % $parts;
        $amounts = array_fill(0, $parts, $base);
        for ($i = 0; $i < $remainder; $i++) {
            $amounts[$i]++;
        }

        return $amounts;
    }

    /**
     * @param  array<string|int, int|float>  $percentByKey  Must sum to 100
     * @return array<string|int, int>
     */
    public function splitByPercentages(int $total, array $percentByKey): array
    {
        if ($total <= 0 || $percentByKey === []) {
            return array_map(fn () => 0, $percentByKey);
        }

        $keys = array_keys($percentByKey);
        $amounts = [];
        $allocated = 0;
        $lastKey = $keys[array_key_last($keys)];

        foreach ($percentByKey as $key => $percent) {
            if ($key === $lastKey) {
                $amounts[$key] = max(0, $total - $allocated);

                continue;
            }

            $share = (int) round($total * ((float) $percent / 100));
            $amounts[$key] = $share;
            $allocated += $share;
        }

        return $amounts;
    }

    /**
     * @return array<int, int> month_number => count
     */
    public function splitAnnualToMonths(int $annualTotal): array
    {
        $monthly = $this->splitInteger(max(0, $annualTotal), 12);
        $months = [];
        foreach (range(1, 12) as $index => $month) {
            $months[$month] = $monthly[$index] ?? 0;
        }

        return $months;
    }

    /**
     * @return array<int, array{key: string, designation_id: ?int, designation_name: string, staff: list<array{id: int, name: string}>, staff_count: int}>
     */
    public function designationGroupsForDistrict(int $districtId): array
    {
        $staff = User::query()
            ->where('role', 'district_staff')
            ->where('is_active', true)
            ->where('district_id', $districtId)
            ->with('designationRecord:id,name,sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'designation_id']);

        $grouped = $staff->groupBy(fn (User $user) => $user->designation_id ?? 'unassigned');

        $groups = [];
        foreach ($grouped as $key => $members) {
            $designationId = $key === 'unassigned' ? null : (int) $key;
            $designationName = $designationId
                ? (string) ($members->first()?->designationRecord?->name ?? 'Designation #'.$designationId)
                : 'Unassigned (no designation)';

            $groups[] = [
                'key' => $this->designationKey($designationId),
                'designation_id' => $designationId,
                'designation_name' => $designationName,
                'staff' => $members->map(fn (User $user) => [
                    'id' => (int) $user->id,
                    'name' => (string) $user->name,
                ])->values()->all(),
                'staff_count' => $members->count(),
            ];
        }

        usort($groups, function (array $a, array $b): int {
            if ($a['designation_id'] === null) {
                return 1;
            }
            if ($b['designation_id'] === null) {
                return -1;
            }

            return strcasecmp($a['designation_name'], $b['designation_name']);
        });

        return $groups;
    }

    public function designationKey(?int $designationId): string
    {
        return $designationId ? 'd_'.$designationId : 'unassigned';
    }

    /**
     * @param  array<int, array{key: string, designation_id: ?int, designation_name: string, staff: list<array{id: int, name: string}>, staff_count: int}>  $designationGroups
     * @param  array<string, int|float>  $percentByKey
     * @return list<array{user_id: int, user_name: string, designation_name: string, annual_total: int, months: array<int, int>}>
     */
    public function buildStaffAllocations(int $districtTarget, array $designationGroups, array $percentByKey): array
    {
        if ($districtTarget <= 0) {
            return [];
        }

        $amountByKey = $this->splitByPercentages($districtTarget, $percentByKey);
        $rows = [];

        foreach ($designationGroups as $group) {
            $key = $group['key'];
            $percent = (float) ($percentByKey[$key] ?? 0);
            if ($percent <= 0) {
                continue;
            }

            $staff = $group['staff'];
            $count = count($staff);
            if ($count === 0) {
                throw new \InvalidArgumentException(
                    'Designation "'.$group['designation_name'].'" has '.$percent.'% but no staff in this district.'
                );
            }

            $designationTotal = (int) ($amountByKey[$key] ?? 0);
            $perStaffTotals = $this->splitInteger($designationTotal, $count);

            foreach ($staff as $index => $member) {
                $annual = $perStaffTotals[$index] ?? 0;
                $rows[] = [
                    'user_id' => (int) $member['id'],
                    'user_name' => (string) $member['name'],
                    'designation_name' => (string) $group['designation_name'],
                    'annual_total' => $annual,
                    'months' => $this->splitAnnualToMonths($annual),
                ];
            }
        }

        return $rows;
    }

    /**
     * @param  list<array{user_id: int, user_name: string, designation_name: string, annual_total: int, months: array<int, int>}>  $allocations
     */
    public function applyAllocations(int $fiscalYearId, int $deliverableId, array $allocations): int
    {
        $updated = 0;

        foreach ($allocations as $row) {
            foreach ($row['months'] as $monthNumber => $count) {
                StaffMonthlyTarget::query()->updateOrCreate(
                    [
                        'fiscal_year_id' => $fiscalYearId,
                        'user_id' => (int) $row['user_id'],
                        'deliverable_id' => $deliverableId,
                        'month_number' => (int) $monthNumber,
                    ],
                    ['target_count' => (int) $count]
                );
                $updated++;
            }
        }

        return $updated;
    }

    /**
     * @param  array<int, array{key: string, designation_id: ?int, designation_name: string, staff: list<array{id: int, name: string}>, staff_count: int}>  $designationGroups
     * @return array<string, int>
     */
    public function defaultEqualPercents(array $designationGroups): array
    {
        $eligible = array_values(array_filter(
            $designationGroups,
            fn (array $group): bool => ($group['staff_count'] ?? 0) > 0
        ));

        if ($eligible === []) {
            return [];
        }

        $count = count($eligible);
        $base = intdiv(100, $count);
        $remainder = 100 % $count;
        $percents = [];

        foreach ($eligible as $index => $group) {
            $percents[$group['key']] = $base + ($index < $remainder ? 1 : 0);
        }

        return $percents;
    }

    /**
     * @param  array<string, mixed>  $percentInput
     * @return array<string, float>
     */
    public function normalizePercentInput(array $percentInput): array
    {
        $normalized = [];
        foreach ($percentInput as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $normalized[(string) $key] = (float) $value;
        }

        return $normalized;
    }

    public function percentSum(array $percents): float
    {
        return (float) array_sum($percents);
    }

    /**
     * @return array<string, float>|null
     */
    public function savedDesignationPercents(int $fiscalYearId, int $districtId, int $deliverableId): ?array
    {
        $row = DistrictDeliverableAllocationSetting::query()
            ->where('fiscal_year_id', $fiscalYearId)
            ->where('district_id', $districtId)
            ->where('deliverable_id', $deliverableId)
            ->first();

        if (! $row || ! is_array($row->designation_percents) || $row->designation_percents === []) {
            return null;
        }

        $percents = [];
        foreach ($row->designation_percents as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $percents[(string) $key] = (float) $value;
        }

        return $percents === [] ? null : $percents;
    }

    /**
     * @param  array<int, array{key: string, designation_id: ?int, designation_name: string, staff: list<array{id: int, name: string}>, staff_count: int}>  $designationGroups
     * @param  array<string, float>  $defaultPercents
     * @param  array<string, float>|null  $savedPercents
     * @return array<string, float>
     */
    public function resolvePercentValues(array $designationGroups, array $defaultPercents, ?array $savedPercents): array
    {
        if ($savedPercents === null) {
            $values = [];
            foreach ($designationGroups as $group) {
                $key = $group['key'];
                $values[$key] = (float) ($defaultPercents[$key] ?? 0);
            }

            return $values;
        }

        $values = [];
        foreach ($designationGroups as $group) {
            $key = $group['key'];
            $values[$key] = array_key_exists($key, $savedPercents)
                ? (float) $savedPercents[$key]
                : 0.0;
        }

        return $values;
    }

    /**
     * @param  array<string, float>  $percents
     */
    public function saveDesignationPercents(
        int $fiscalYearId,
        int $districtId,
        int $deliverableId,
        array $percents,
    ): void {
        DistrictDeliverableAllocationSetting::query()->updateOrCreate(
            [
                'fiscal_year_id' => $fiscalYearId,
                'district_id' => $districtId,
                'deliverable_id' => $deliverableId,
            ],
            [
                'designation_percents' => $percents,
            ]
        );
    }

    /**
     * @param  array<string|int, array<string|int, mixed>>  $monthInput
     * @return array<int, array<int, int>>
     */
    public function normalizeMonthInput(array $monthInput): array
    {
        $normalized = [];

        foreach ($monthInput as $userId => $months) {
            if (! is_array($months)) {
                continue;
            }

            $uid = (int) $userId;
            $normalized[$uid] = [];

            foreach (range(1, 12) as $month) {
                $value = $months[$month] ?? $months[(string) $month] ?? 0;
                $normalized[$uid][$month] = max(0, (int) $value);
            }
        }

        return $normalized;
    }

    /**
     * @param  list<array{user_id: int, user_name: string, designation_name: string, annual_total: int, months: array<int, int>}>  $allocations
     * @param  array<int, array<int, int>>  $manualMonths
     * @return list<array{user_id: int, user_name: string, designation_name: string, annual_total: int, months: array<int, int>}>
     */
    public function applyManualMonths(array $allocations, array $manualMonths): array
    {
        if ($manualMonths === []) {
            return $allocations;
        }

        return array_map(function (array $row) use ($manualMonths): array {
            $userId = (int) $row['user_id'];
            if (! isset($manualMonths[$userId])) {
                return $row;
            }

            $months = $manualMonths[$userId];
            $row['months'] = $months;
            $row['annual_total'] = array_sum($months);

            return $row;
        }, $allocations);
    }

    /**
     * @param  list<array{user_id: int, user_name: string, designation_name: string, annual_total: int, months: array<int, int>}>  $allocations
     */
    public function allocationsDistrictTotal(array $allocations): int
    {
        $total = 0;
        foreach ($allocations as $row) {
            $total += array_sum($row['months']);
        }

        return $total;
    }

    /**
     * @param  list<array{user_id: int, user_name: string, designation_name: string, annual_total: int, months: array<int, int>}>  $allocations
     */
    public function districtTotalMismatchMessage(array $allocations, int $districtTarget): ?string
    {
        $actual = $this->allocationsDistrictTotal($allocations);
        if ($actual === $districtTarget) {
            return null;
        }

        $diff = abs($districtTarget - $actual);
        if ($actual < $districtTarget) {
            return 'Staff monthly totals add up to '.number_format($actual).' but district target is '
                .number_format($districtTarget).' ('.number_format($diff).' short). Adjust M1–M12 below.';
        }

        return 'Staff monthly totals add up to '.number_format($actual).' but district target is '
            .number_format($districtTarget).' ('.number_format($diff).' over). Adjust M1–M12 below.';
    }
}
