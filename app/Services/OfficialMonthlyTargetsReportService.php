<?php

namespace App\Services;

use App\Models\Deliverable;
use App\Models\District;
use App\Models\FiscalYear;
use App\Models\OfficialDistrictMonthlyTarget;
use App\Models\OfficialHubMonthlyTarget;
use App\Models\OfficialStateMonthlyTarget;
use Illuminate\Support\Facades\Schema;

class OfficialMonthlyTargetsReportService
{
    public function __construct(
        private readonly DistrictHubMonthlyTargetsService $districtHubMonthlyTargets,
    ) {}

    /**
     * @param  array{weights: array<int, float>, year_fraction: float, has_narrowing: bool}  $periodInfo
     * @return array<int, int> deliverable_id => target total
     */
    public function loadStateAdminTargets(FiscalYear $fiscalYear, array $periodInfo): array
    {
        if (! $this->tablesExist()) {
            return [];
        }

        $stateDeliverableIds = $this->deliverableIdsWithOfficialStateMonthly($fiscalYear);

        $stateTotals = $this->sumStateMonthly($fiscalYear, $periodInfo, null);

        $districtTotals = $this->omitDeliverablesWithStateOfficialData(
            $this->sumDistrictMonthly($fiscalYear, $periodInfo, null, []),
            $stateDeliverableIds,
        );
        $hubTotals = $this->sumHubMonthly($fiscalYear, $periodInfo, null, $stateDeliverableIds);

        return $this->mergeTotals($stateTotals, $districtTotals, $hubTotals);
    }

    /**
     * @param  list<int>  $districtIds
     * @param  array{weights: array<int, float>, year_fraction: float, has_narrowing: bool}  $periodInfo
     * @return array<int, int>
     */
    public function loadDistrictScopedTargets(FiscalYear $fiscalYear, array $districtIds, array $periodInfo): array
    {
        if ($districtIds === [] || ! $this->tablesExist()) {
            return [];
        }

        $districtTotals = $this->sumDistrictMonthly($fiscalYear, $periodInfo, $districtIds, []);

        $hubIds = District::query()
            ->whereIn('id', $districtIds)
            ->distinct()
            ->pluck('hub_id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->values()
            ->all();

        $hubTotals = $hubIds !== []
            ? $this->sumHubMonthly($fiscalYear, $periodInfo, $hubIds, [])
            : [];

        $stateTotals = $this->stateMonthlyFallbackForDistrictView(
            $fiscalYear,
            $periodInfo,
            $districtTotals,
            $hubTotals,
        );

        return $this->mergeTotals($districtTotals, $hubTotals, $stateTotals);
    }

    public function hasAnyForFiscalYear(FiscalYear $fiscalYear): bool
    {
        if (! $this->tablesExist()) {
            return false;
        }

        return OfficialStateMonthlyTarget::query()->where('fiscal_year_id', $fiscalYear->id)->exists()
            || OfficialDistrictMonthlyTarget::query()->where('fiscal_year_id', $fiscalYear->id)->exists()
            || OfficialHubMonthlyTarget::query()->where('fiscal_year_id', $fiscalYear->id)->exists();
    }

    /**
     * @return array<int, true> deliverable_id => true
     */
    private function deliverableIdsWithOfficialStateMonthly(FiscalYear $fiscalYear): array
    {
        $ids = OfficialStateMonthlyTarget::query()
            ->where('fiscal_year_id', $fiscalYear->id)
            ->distinct()
            ->pluck('deliverable_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return array_fill_keys($ids, 1);
    }

    /**
     * @param  list<int>|null  $districtIds  null = all districts
     * @param  array<int, true>  $excludeDeliverableIds
     * @return array<int, int>
     */
    private function sumStateMonthly(FiscalYear $fiscalYear, array $periodInfo, ?array $districtIds): array
    {
        unset($districtIds);

        $query = OfficialStateMonthlyTarget::query()
            ->where('fiscal_year_id', $fiscalYear->id);

        if ($periodInfo['has_narrowing'] && $periodInfo['weights'] !== []) {
            $query->whereIn('month_number', array_keys($periodInfo['weights']));
        }

        $totals = [];
        foreach ($query->get(['deliverable_id', 'month_number', 'target_count']) as $row) {
            $deliverableId = (int) $row->deliverable_id;
            $weight = $periodInfo['has_narrowing']
                ? ($periodInfo['weights'][(int) $row->month_number] ?? 0.0)
                : 1.0;
            if ($periodInfo['has_narrowing'] && $weight <= 0) {
                continue;
            }
            $totals[$deliverableId] = ($totals[$deliverableId] ?? 0)
                + (int) round((int) $row->target_count * ($periodInfo['has_narrowing'] ? $weight : 1));
        }

        return $totals;
    }

    /**
     * State official monthly rows win on the state admin view; district sums are fallback-only.
     *
     * @param  array<int, int>  $districtTotals
     * @param  array<int, true>  $stateDeliverableIds
     * @return array<int, int>
     */
    private function omitDeliverablesWithStateOfficialData(array $districtTotals, array $stateDeliverableIds): array
    {
        if ($districtTotals === [] || $stateDeliverableIds === []) {
            return $districtTotals;
        }

        $out = [];
        foreach ($districtTotals as $deliverableId => $total) {
            if (isset($stateDeliverableIds[(int) $deliverableId])) {
                continue;
            }
            $out[(int) $deliverableId] = (int) $total;
        }

        return $out;
    }

    /**
     * @param  list<int>|null  $districtIds
     * @param  array<int, true>  $excludeDeliverableIds
     * @return array<int, int>
     */
    private function sumDistrictMonthly(
        FiscalYear $fiscalYear,
        array $periodInfo,
        ?array $districtIds,
        array $excludeDeliverableIds,
    ): array {
        $query = OfficialDistrictMonthlyTarget::query()
            ->where('fiscal_year_id', $fiscalYear->id);

        if ($districtIds !== null) {
            $query->whereIn('district_id', $districtIds);
        }

        if ($periodInfo['has_narrowing'] && $periodInfo['weights'] !== []) {
            $query->whereIn('month_number', array_keys($periodInfo['weights']));
        }

        $rawTotals = [];
        foreach ($query->get(['deliverable_id', 'month_number', 'target_count']) as $row) {
            $deliverableId = (int) $row->deliverable_id;
            if (isset($excludeDeliverableIds[$deliverableId])) {
                continue;
            }
            $weight = $periodInfo['has_narrowing']
                ? ($periodInfo['weights'][(int) $row->month_number] ?? 0.0)
                : 1.0;
            if ($periodInfo['has_narrowing'] && $weight <= 0) {
                continue;
            }
            $rawTotals[$deliverableId] = ($rawTotals[$deliverableId] ?? 0)
                + (int) round((int) $row->target_count * ($periodInfo['has_narrowing'] ? $weight : 1));
        }

        return $this->filterByScope($rawTotals, DistrictHubMonthlyTargetsService::SCOPE_DISTRICT);
    }

    /**
     * State / hub-only official rows for deliverables not already covered by district or hub sums.
     *
     * @param  array<int, int>  $districtTotals
     * @param  array<int, int>  $hubTotals
     * @return array<int, int>
     */
    private function stateMonthlyFallbackForDistrictView(
        FiscalYear $fiscalYear,
        array $periodInfo,
        array $districtTotals,
        array $hubTotals,
    ): array {
        $covered = array_fill_keys(
            array_map('intval', array_merge(array_keys($districtTotals), array_keys($hubTotals))),
            true,
        );

        $out = [];
        foreach ($this->sumStateMonthly($fiscalYear, $periodInfo, null) as $deliverableId => $total) {
            $deliverableId = (int) $deliverableId;
            if (isset($covered[$deliverableId])) {
                continue;
            }
            $out[$deliverableId] = (int) $total;
        }

        return $out;
    }

    /**
     * @param  list<int>|null  $hubIds
     * @param  array<int, true>  $excludeDeliverableIds
     * @return array<int, int>
     */
    private function sumHubMonthly(
        FiscalYear $fiscalYear,
        array $periodInfo,
        ?array $hubIds,
        array $excludeDeliverableIds,
    ): array {
        $query = OfficialHubMonthlyTarget::query()
            ->where('fiscal_year_id', $fiscalYear->id);

        if ($hubIds !== null) {
            $query->whereIn('hub_id', $hubIds);
        }

        if ($periodInfo['has_narrowing'] && $periodInfo['weights'] !== []) {
            $query->whereIn('month_number', array_keys($periodInfo['weights']));
        }

        $rawTotals = [];
        foreach ($query->get(['deliverable_id', 'month_number', 'target_count']) as $row) {
            $deliverableId = (int) $row->deliverable_id;
            if (isset($excludeDeliverableIds[$deliverableId])) {
                continue;
            }
            $weight = $periodInfo['has_narrowing']
                ? ($periodInfo['weights'][(int) $row->month_number] ?? 0.0)
                : 1.0;
            if ($periodInfo['has_narrowing'] && $weight <= 0) {
                continue;
            }
            $rawTotals[$deliverableId] = ($rawTotals[$deliverableId] ?? 0)
                + (int) round((int) $row->target_count * ($periodInfo['has_narrowing'] ? $weight : 1));
        }

        return $this->filterByScope($rawTotals, DistrictHubMonthlyTargetsService::SCOPE_HUB);
    }

    /**
     * @param  array<int, int>  $rawTotals
     * @return array<int, int>
     */
    private function filterByScope(array $rawTotals, string $requiredScope): array
    {
        if ($rawTotals === []) {
            return [];
        }

        $deliverables = Deliverable::query()
            ->whereIn('id', array_keys($rawTotals))
            ->get(['id', 'code', 'name', 'mis_entry_label'])
            ->keyBy('id');

        $out = [];
        foreach ($rawTotals as $deliverableId => $total) {
            $deliverable = $deliverables->get((int) $deliverableId);
            if (! $deliverable) {
                continue;
            }
            if ($this->districtHubMonthlyTargets->resolveScopeForDeliverable($deliverable) !== $requiredScope) {
                continue;
            }
            if ($total > 0 || ! isset($out[(int) $deliverableId])) {
                $out[(int) $deliverableId] = (int) $total;
            }
        }

        return $out;
    }

    /**
     * @param  array<int, int>  ...$maps
     * @return array<int, int>
     */
    private function mergeTotals(array ...$maps): array
    {
        $merged = [];
        foreach ($maps as $map) {
            foreach ($map as $deliverableId => $total) {
                $merged[(int) $deliverableId] = (int) $total;
            }
        }

        return $merged;
    }

    private function tablesExist(): bool
    {
        return Schema::hasTable('official_state_monthly_targets')
            && Schema::hasTable('official_district_monthly_targets')
            && Schema::hasTable('official_hub_monthly_targets');
    }
}
