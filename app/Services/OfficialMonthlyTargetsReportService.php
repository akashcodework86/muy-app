<?php

namespace App\Services;

use App\Models\District;
use App\Models\Deliverable;
use App\Models\FiscalYear;
use App\Models\OfficialDistrictMonthlyTarget;
use App\Models\OfficialHubMonthlyTarget;
use App\Models\OfficialStateMonthlyTarget;
use App\Support\HubTargetDeliverablesSupport;
use Illuminate\Support\Facades\Schema;

class OfficialMonthlyTargetsReportService
{
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
    public function loadDistrictScopedTargets(FiscalYear $fiscalYear, array $districtIds, array $periodInfo, ?int $hubId = null): array
    {
        if ($districtIds === [] || ! $this->tablesExist()) {
            return [];
        }

        $hubTargetDistrictIds = HubTargetDeliverablesSupport::filterDistrictIdsForHubTargets($districtIds, $hubId);
        $districtTotals = $this->sumDistrictMonthly($fiscalYear, $periodInfo, $districtIds, [], $hubTargetDistrictIds);

        $hubIds = $hubTargetDistrictIds !== []
            ? District::query()
                ->whereIn('id', $hubTargetDistrictIds)
                ->distinct()
                ->pluck('hub_id')
                ->map(fn ($id) => (int) $id)
                ->filter(fn ($id) => $id > 0)
                ->values()
                ->all()
            : [];

        $hubTotals = $hubIds !== []
            ? $this->sumHubMonthly($fiscalYear, $periodInfo, $hubIds, [])
            : [];

        $stateTotals = $this->stateMonthlyFallbackForDistrictView(
            $fiscalYear,
            $periodInfo,
            $districtTotals,
            $hubTotals,
        );

        $merged = $this->mergeTotals($districtTotals, $hubTotals, $stateTotals);

        // Hub-only indicators are owned by the configured primary district line.
        // Prefer that scoped district total over a combined hub row when both exist.
        if ($hubTargetDistrictIds !== []) {
            $hubTargetDeliverableIds = Deliverable::query()
                ->whereIn('code', HubTargetDeliverablesSupport::deliverableCodes())
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();
            foreach ($hubTargetDeliverableIds as $deliverableId) {
                if (array_key_exists($deliverableId, $districtTotals)) {
                    $merged[$deliverableId] = $districtTotals[$deliverableId];
                }
            }
        }

        return $merged;
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
     * Per-district official monthly targets for the given deliverables and period.
     *
     * When several deliverable ids map to one indicator, the highest district total is kept
     * so MIS + svc_* rows are not double-counted.
     *
     * @param  list<int>  $deliverableIds
     * @param  list<int>|null  $districtIds
     * @return array<int, int> district_id => target
     */
    public function sumByDistrictForDeliverables(
        FiscalYear $fiscalYear,
        array $periodInfo,
        array $deliverableIds,
        ?array $districtIds,
        bool $sumDeliverables = false,
    ): array {
        if ($deliverableIds === [] || ! Schema::hasTable('official_district_monthly_targets')) {
            return [];
        }

        $query = OfficialDistrictMonthlyTarget::query()
            ->where('fiscal_year_id', $fiscalYear->id)
            ->whereIn('deliverable_id', $deliverableIds);

        if ($districtIds !== null) {
            $query->whereIn('district_id', $districtIds);
        }

        if (($periodInfo['has_narrowing'] ?? false) && ($periodInfo['weights'] ?? []) !== []) {
            $query->whereIn('month_number', array_keys($periodInfo['weights']));
        }

        $byDistrictDeliverable = [];
        foreach ($query->get(['district_id', 'deliverable_id', 'month_number', 'target_count']) as $row) {
            $weight = ($periodInfo['has_narrowing'] ?? false)
                ? (float) ($periodInfo['weights'][(int) $row->month_number] ?? 0.0)
                : 1.0;
            if (($periodInfo['has_narrowing'] ?? false) && $weight <= 0) {
                continue;
            }
            $districtId = (int) $row->district_id;
            $deliverableId = (int) $row->deliverable_id;
            $value = (int) round((int) $row->target_count * (($periodInfo['has_narrowing'] ?? false) ? $weight : 1));
            $byDistrictDeliverable[$districtId][$deliverableId] = ($byDistrictDeliverable[$districtId][$deliverableId] ?? 0) + $value;
        }

        $out = [];
        foreach ($byDistrictDeliverable as $districtId => $byDeliverable) {
            $out[(int) $districtId] = $sumDeliverables
                ? (int) array_sum($byDeliverable)
                : (int) max($byDeliverable);
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
        array $hubTargetDistrictIds = [],
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
        $hubTargetDeliverableIds = $hubTargetDistrictIds === []
            ? []
            : Deliverable::query()
                ->whereIn('code', HubTargetDeliverablesSupport::deliverableCodes())
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();

        foreach ($query->get(['deliverable_id', 'district_id', 'month_number', 'target_count']) as $row) {
            $deliverableId = (int) $row->deliverable_id;
            if (isset($excludeDeliverableIds[$deliverableId])) {
                continue;
            }
            if (in_array($deliverableId, $hubTargetDeliverableIds, true)
                && ! in_array((int) $row->district_id, $hubTargetDistrictIds, true)) {
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

        return $rawTotals;
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

        return $rawTotals;
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
