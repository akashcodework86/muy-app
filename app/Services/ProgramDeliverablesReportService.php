<?php

namespace App\Services;

use App\Models\Deliverable;
use App\Models\DistrictDeliverableTarget;
use App\Models\DistrictMonthlyTarget;
use App\Models\HubMonthlyTarget;
use App\Models\FieldCoordinatorAttendanceReport;
use App\Models\FiscalYear;
use App\Models\MarketLinkageSubmission;
use App\Models\Service;
use App\Models\ServiceCase;
use App\Models\StateDeliverableTarget;
use App\Models\StateMonthlyTarget;
use App\Models\User;
use App\Services\Deliverables\ProgramDeliverableReportingTier;
use App\Services\Deliverables\ProgramDeliverableRowMetadataService;
use App\Services\Deliverables\ProgramDeliverablesFilter;
use App\Services\Deliverables\ProgramDeliverablesScope;
use App\Support\DemoDaysDeliverablesSupport;
use App\Support\FundingSchematicPartnersOutreachDeliverablesSupport;
use App\Support\BusinessAccelerationPartnersOutreachDeliverablesSupport;
use App\Support\AccelerationServicesDeliverablesSupport;
use App\Support\ConvergenceReapSupportDeliverablesSupport;
use App\Support\CapacityBuildingStakeholdersDeliverablesSupport;
use App\Support\StakeholderConsultationWorkshopDeliverablesSupport;
use App\Support\LineDepartmentMeetingDeliverablesSupport;
use App\Support\MarketingPartnerOnboardedCombinedDeliverablesSupport;
use App\Support\MarketingPartnerOutreachDeliverablesSupport;
use App\Support\PitchDeckCombinedDeliverablesSupport;
use App\Support\PitchDeckPreparationsDeliverablesSupport;
use App\Support\BstTrainingDeliverablesSupport;
use App\Support\BstTrainingMonthPlanTargetSupport;
use App\Support\MarketLinkageUnifiedListingSupport;
use App\Support\HubTargetDeliverablesSupport;
use App\Support\NeedBasedDeliverablesSupport;
use App\Support\StateTargetLabelDeliverablesSupport;
use App\Support\PotentialLakhpatiOnboardingSql;
use App\Support\PotentialLakhpatiTechnicalTrainingDeliverablesSupport;
use App\Support\MisFieldActivityApproval;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ProgramDeliverablesReportService
{
    public function __construct(
        private readonly ServiceTargetDeliverableSyncService $serviceTargetDeliverables,
        private readonly LegacyApplicationServiceCaseSupport $legacyServiceCases,
        private readonly MarketLinkagePartnerCatalogService $marketLinkagePartners,
        private readonly DistrictHubMonthlyTargetsService $districtHubMonthlyTargets,
        private readonly StateMonthlyTargetIndicatorBootstrapService $stateMonthlyIndicators,
        private readonly OfficialMonthlyTargetsReportService $officialMonthlyTargets,
        private readonly ProgramDeliverableRowMetadataService $rowMetadata,
    ) {}

    /** @var array<string, int> */
    private array $deliverableIdsByCode = [];

    /** @var array<int, int> */
    private array $achievementByServiceId = [];

    /** @var array<string, int> MIS deliverable code => approved service case count */
    private array $achievementByMisCode = [];

    /** @var array<int, int> */
    private array $targetsByDeliverableId = [];

    /** @var array<string, int> normalized deliverable name => target */
    private array $targetsByNameNorm = [];

    /** @var array<string, int> deliverable / service code => state target (incl. svc_* rows) */
    private array $stateTargetsByLookupCode = [];

    /** @var array<string, int> */
    private array $serviceIdsByCode = [];

    /** @var list<int>|null */
    private ?array $districtIds = null;

    private ?Carbon $periodFrom = null;

    private ?Carbon $periodTo = null;

    private bool $useStateTargets = true;

    private bool $useOfficialMonthlyTargets = false;

    private ?ProgramDeliverablesFilter $filter = null;

    private string $viewerRole = 'guest';

    /**
     * @return array{
     *     fiscalYear: ?FiscalYear,
     *     rows: list<array{
     *         row_type: string,
     *         serial: string,
     *         name: string,
     *         indicator_type: string,
     *         level: string,
     *         target: ?int,
     *         achievement: int,
     *         achievement_pct: ?int,
     *         performance_tone: ?string
     *     }>
     * }
     */
    public function build(ProgramDeliverablesFilter $filter, ProgramDeliverablesScope $scope): array
    {
        $fiscalYears = FiscalYear::forUiDropdown();
        [$resolvedFyId] = FiscalYear::resolveIdForUi($filter->fiscalYearId);
        $fiscalYear = $fiscalYears->firstWhere('id', $resolvedFyId);

        $this->filter = $filter;
        $this->viewerRole = $scope->role;
        $this->activeFiscalYear = $fiscalYear;
        $this->districtIds = $scope->effectiveDistrictIds($filter->districtId);
        // State-wide targets for state admin; when a district is selected, use that district's targets.
        $this->useStateTargets = $scope->usesStateTargets && ($filter->districtId === null || $filter->districtId <= 0);
        $this->useOfficialMonthlyTargets = false;
        [$this->periodFrom, $this->periodTo] = $filter->resolvePeriod($fiscalYear);

        $this->targetsByDeliverableId = $this->loadTargets($fiscalYear);
        $this->loadServiceCaseAchievements();
        $this->deliverableIdsByCode = Deliverable::query()
            ->pluck('id', 'code')
            ->map(fn ($id) => (int) $id)
            ->all();
        $this->serviceIdsByCode = Service::query()
            ->where('is_active', true)
            ->pluck('id', 'code')
            ->map(fn ($id) => (int) $id)
            ->all();

        $rows = [];
        $pillarIndex = 0;
        foreach (config('program_deliverables.matrix', []) as $pillar) {
            $pillarIndex++;
            $this->appendMatrixRows($rows, $pillar, [(string) $pillarIndex]);
        }

        if ($filter->hasRowMetadataFilter()) {
            $rows = $this->filterRowsByMetadata($rows, $filter);
        }

        return [
            'fiscalYear' => $fiscalYear,
            'rows' => $rows,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function filterRowsByMetadata(array $rows, ProgramDeliverablesFilter $filter): array
    {
        $visibleLeafSerials = [];

        foreach ($rows as $row) {
            if (in_array($row['row_type'] ?? '', ['pillar', 'subcategory'], true)) {
                continue;
            }

            if ($filter->indicatorType !== null && ($row['indicator_type'] ?? '') !== $filter->indicatorType) {
                continue;
            }

            if ($filter->level !== null && ($row['level'] ?? '') !== $filter->level) {
                continue;
            }

            $visibleLeafSerials[] = (string) $row['serial'];
        }

        if ($visibleLeafSerials === []) {
            return [];
        }

        return array_values(array_filter($rows, function (array $row) use ($visibleLeafSerials): bool {
            if (! in_array($row['row_type'] ?? '', ['pillar', 'subcategory'], true)) {
                return in_array((string) $row['serial'], $visibleLeafSerials, true);
            }

            $headingSerial = (string) $row['serial'];
            $prefix = $headingSerial.'.';

            foreach ($visibleLeafSerials as $leafSerial) {
                if ($leafSerial === $headingSerial || str_starts_with($leafSerial, $prefix)) {
                    return true;
                }
            }

            return false;
        }));
    }

    /**
     * @return array<int, int>
     */
    private function loadTargets(?FiscalYear $fiscalYear): array
    {
        $this->targetsByNameNorm = [];
        $this->stateTargetsByLookupCode = [];

        if (! $fiscalYear) {
            return [];
        }

        $periodInfo = $this->periodMonthWeights($fiscalYear);

        if ($this->officialMonthlyTargets->hasAnyForFiscalYear($fiscalYear)) {
            $this->useOfficialMonthlyTargets = true;

            if ($this->useStateTargets) {
                $targets = $this->officialMonthlyTargets->loadStateAdminTargets($fiscalYear, $periodInfo);
                $this->buildTargetIndexesFromTotals($targets);

                return $targets;
            }

            $districtIds = $this->districtIds ?? [];
            $targets = $this->officialMonthlyTargets->loadDistrictScopedTargets($fiscalYear, $districtIds, $periodInfo);
            $this->buildTargetIndexesFromTotals($targets);

            return $targets;
        }

        $this->useOfficialMonthlyTargets = false;

        return $this->loadLegacyTargets($fiscalYear, $periodInfo);
    }

    /**
     * @param  array{weights: array<int, float>, year_fraction: float, has_narrowing: bool}  $periodInfo
     * @return array<int, int>
     */
    private function loadLegacyTargets(FiscalYear $fiscalYear, array $periodInfo): array
    {
        if ($this->useStateTargets) {
            $targets = [];
            $rows = StateDeliverableTarget::query()
                ->where('fiscal_year_id', $fiscalYear->id)
                ->pluck('target_total', 'deliverable_id');

            foreach ($rows as $deliverableId => $total) {
                $value = (int) $total;
                if ($periodInfo['has_narrowing']) {
                    $value = (int) round($value * $periodInfo['year_fraction']);
                }
                $targets[(int) $deliverableId] = $value;
            }

            $targets = $this->mergeStateScopedMonthlyTargets($fiscalYear, $targets, $periodInfo);
            $this->buildTargetIndexesFromTotals($targets);

            return $targets;
        }

        return $this->loadDistrictScopedTargets($fiscalYear, $periodInfo);
    }

    /**
     * District / hub / SPOC: sum district_deliverable_targets for scope, with svc_* code mapping.
     * Monthly breakdown comes from district_monthly_targets (official plan).
     *
     * When the filter narrows to a month / date range, prefer the weighted sum of
     * district_monthly_targets for the selected fiscal months, and pro-rate the district
     * FY total for deliverables that have no monthly rows yet.
     *
     * @param  array{weights: array<int, float>, year_fraction: float, has_narrowing: bool}  $periodInfo
     * @return array<int, int>
     */
    private function loadDistrictScopedTargets(FiscalYear $fiscalYear, array $periodInfo): array
    {
        if ($this->districtIds === []) {
            return [];
        }

        $districtFy = [];

        $districtQuery = DistrictDeliverableTarget::query()
            ->where('fiscal_year_id', $fiscalYear->id);

        if ($this->districtIds !== null) {
            $districtQuery->whereIn('district_id', $this->districtIds);
        }

        foreach ($districtQuery->get(['deliverable_id', 'target_total']) as $row) {
            $id = (int) $row->deliverable_id;
            $districtFy[$id] = ($districtFy[$id] ?? 0) + (int) $row->target_total;
        }

        if ($periodInfo['has_narrowing']) {
            return $this->buildNarrowedDistrictTargets(
                $fiscalYear,
                $districtFy,
                $periodInfo,
            );
        }

        $targets = $districtFy;

        $targets = $this->mergeDistrictScopedMonthlyTargets($fiscalYear, $targets, $periodInfo);

        $this->buildTargetIndexesFromTotals($targets);

        return $targets;
    }

    /**
     * Prefer hub / district monthly plan totals on the state-wide deliverables view when present.
     *
     * @param  array<int, int>  $targets
     * @param  array{weights: array<int, float>, year_fraction: float, has_narrowing: bool}  $periodInfo
     * @return array<int, int>
     */
    private function mergeStateScopedMonthlyTargets(FiscalYear $fiscalYear, array $targets, array $periodInfo): array
    {
        if (! Schema::hasTable('hub_monthly_targets')
            && ! Schema::hasTable('district_monthly_targets')
            && ! Schema::hasTable('state_monthly_targets')) {
            return $targets;
        }

        $deliverables = Deliverable::query()
            ->whereIn('id', array_keys($targets))
            ->get(['id', 'code'])
            ->keyBy('id');

        $this->stateMonthlyIndicators->ensureDeliverables();
        $stateMonthlyCodes = array_flip($this->stateMonthlyIndicators->allowedDeliverableCodes());

        $stateMonthlyDeliverables = $stateMonthlyCodes === []
            ? collect()
            : Deliverable::query()
                ->whereIn('code', array_keys($stateMonthlyCodes))
                ->get(['id', 'code'])
                ->keyBy('id');

        /** @var array<int, int> */
        $stateMonthlyDeliverableIds = array_flip(
            $stateMonthlyDeliverables->keys()->map(fn ($id) => (int) $id)->all(),
        );

        if (Schema::hasTable('hub_monthly_targets')) {
            $hubQuery = HubMonthlyTarget::query()->where('fiscal_year_id', $fiscalYear->id);
            if ($periodInfo['has_narrowing'] && $periodInfo['weights'] !== []) {
                $hubQuery->whereIn('month_number', array_keys($periodInfo['weights']));
            }
            $hubRows = $hubQuery->get(['deliverable_id', 'month_number', 'target_count']);

            $hubTotals = [];
            foreach ($hubRows as $row) {
                $deliverableId = (int) $row->deliverable_id;
                $weight = $periodInfo['has_narrowing']
                    ? ($periodInfo['weights'][(int) $row->month_number] ?? 0.0)
                    : 1.0;
                if ($periodInfo['has_narrowing'] && $weight <= 0) {
                    continue;
                }
                $hubTotals[$deliverableId] = ($hubTotals[$deliverableId] ?? 0.0)
                    + ((int) $row->target_count) * ($periodInfo['has_narrowing'] ? $weight : 1.0);
            }

            foreach ($hubTotals as $deliverableId => $total) {
                if (isset($stateMonthlyDeliverableIds[(int) $deliverableId])) {
                    continue;
                }
                $deliverable = $deliverables->get((int) $deliverableId);
                if (! $deliverable || $this->districtHubMonthlyTargets->resolveScopeForDeliverable($deliverable) !== DistrictHubMonthlyTargetsService::SCOPE_HUB) {
                    continue;
                }
                $value = $periodInfo['has_narrowing']
                    ? (int) round($total)
                    : (int) round($total);
                if ($value > 0) {
                    $targets[(int) $deliverableId] = $value;
                }
            }
        }

        if (Schema::hasTable('district_monthly_targets')) {
            $districtQuery = DistrictMonthlyTarget::query()->where('fiscal_year_id', $fiscalYear->id);
            if ($periodInfo['has_narrowing'] && $periodInfo['weights'] !== []) {
                $districtQuery->whereIn('month_number', array_keys($periodInfo['weights']));
            }
            $districtRows = $districtQuery->get(['deliverable_id', 'month_number', 'target_count']);

            $districtTotals = [];
            foreach ($districtRows as $row) {
                $deliverableId = (int) $row->deliverable_id;
                $weight = $periodInfo['has_narrowing']
                    ? ($periodInfo['weights'][(int) $row->month_number] ?? 0.0)
                    : 1.0;
                if ($periodInfo['has_narrowing'] && $weight <= 0) {
                    continue;
                }
                $districtTotals[$deliverableId] = ($districtTotals[$deliverableId] ?? 0.0)
                    + ((int) $row->target_count) * ($periodInfo['has_narrowing'] ? $weight : 1.0);
            }

            foreach ($districtTotals as $deliverableId => $total) {
                if (isset($stateMonthlyDeliverableIds[(int) $deliverableId])) {
                    continue;
                }
                $deliverable = $deliverables->get((int) $deliverableId);
                if (! $deliverable || $this->districtHubMonthlyTargets->resolveScopeForDeliverable($deliverable) !== DistrictHubMonthlyTargetsService::SCOPE_DISTRICT) {
                    continue;
                }
                $value = (int) round($total);
                if ($value > 0) {
                    $targets[(int) $deliverableId] = $value;
                }
            }
        }

        if (Schema::hasTable('state_monthly_targets')) {
            $deliverablesWithMonthlyPlan = StateMonthlyTarget::query()
                ->where('fiscal_year_id', $fiscalYear->id)
                ->distinct()
                ->pluck('deliverable_id')
                ->map(fn ($id) => (int) $id)
                ->all();

            $stateQuery = StateMonthlyTarget::query()
                ->where('fiscal_year_id', $fiscalYear->id);

            if ($periodInfo['has_narrowing'] && $periodInfo['weights'] !== []) {
                $stateQuery->whereIn('month_number', array_keys($periodInfo['weights']));
            }

            $stateRows = $stateQuery->get(['deliverable_id', 'month_number', 'target_count']);

            $stateTotals = [];
            foreach ($stateRows as $row) {
                $deliverableId = (int) $row->deliverable_id;
                $weight = $periodInfo['has_narrowing']
                    ? ($periodInfo['weights'][(int) $row->month_number] ?? 0.0)
                    : 1.0;
                if ($periodInfo['has_narrowing'] && $weight <= 0) {
                    continue;
                }
                $stateTotals[$deliverableId] = ($stateTotals[$deliverableId] ?? 0.0)
                    + ((int) $row->target_count) * ($periodInfo['has_narrowing'] ? $weight : 1.0);
            }

            foreach ($deliverablesWithMonthlyPlan as $deliverableId) {
                $value = (int) round($stateTotals[$deliverableId] ?? 0);
                if ($periodInfo['has_narrowing'] || $value > 0) {
                    $targets[$deliverableId] = $value;
                }
            }
        }

        return $targets;
    }

    /**
     * @param  array<int, int>  $targets
     * @param  array{weights: array<int, float>, year_fraction: float, has_narrowing: bool}  $periodInfo
     * @return array<int, int>
     */
    private function mergeDistrictScopedMonthlyTargets(FiscalYear $fiscalYear, array $targets, array $periodInfo): array
    {
        if ($this->districtIds === null || $this->districtIds === [] || ! Schema::hasTable('district_monthly_targets')) {
            return $targets;
        }

        $query = DistrictMonthlyTarget::query()
            ->where('fiscal_year_id', $fiscalYear->id)
            ->whereIn('district_id', $this->districtIds);

        if ($periodInfo['has_narrowing'] && $periodInfo['weights'] !== []) {
            $query->whereIn('month_number', array_keys($periodInfo['weights']));
        }

        $rows = $query->get(['deliverable_id', 'month_number', 'target_count']);
        $totals = [];

        foreach ($rows as $row) {
            $deliverableId = (int) $row->deliverable_id;
            $weight = $periodInfo['has_narrowing']
                ? ($periodInfo['weights'][(int) $row->month_number] ?? 0.0)
                : 1.0;
            if ($periodInfo['has_narrowing'] && $weight <= 0) {
                continue;
            }
            $totals[$deliverableId] = ($totals[$deliverableId] ?? 0.0)
                + ((int) $row->target_count) * ($periodInfo['has_narrowing'] ? $weight : 1.0);
        }

        foreach ($totals as $deliverableId => $total) {
            $value = (int) round($total);
            if ($value > 0) {
                $targets[(int) $deliverableId] = $value;
            }
        }

        return $targets;
    }

    /**
     * Per-deliverable target narrowed to the selected month / date window:
     *  - if district_monthly_targets has any non-zero row for the FY → use weighted month sum
     *  - else → pro-rate the district FY total by the fraction of year selected.
     *
     * @param  array<int, int>  $districtFy
     * @param  array{weights: array<int, float>, year_fraction: float, has_narrowing: bool}  $periodInfo
     * @return array<int, int>
     */
    private function buildNarrowedDistrictTargets(
        FiscalYear $fiscalYear,
        array $districtFy,
        array $periodInfo,
    ): array {
        $weightedMonthly = [];
        $deliverablesWithMonthlyData = [];

        if (Schema::hasTable('district_monthly_targets') && $this->districtIds !== null && $this->districtIds !== []) {
            $deliverablesWithMonthlyData = DistrictMonthlyTarget::query()
                ->where('fiscal_year_id', $fiscalYear->id)
                ->whereIn('district_id', $this->districtIds)
                ->where('target_count', '>', 0)
                ->distinct()
                ->pluck('deliverable_id')
                ->map(fn ($id) => (int) $id)
                ->all();
        }

        if ($deliverablesWithMonthlyData !== [] && $periodInfo['weights'] !== []) {
            $rows = DistrictMonthlyTarget::query()
                ->where('fiscal_year_id', $fiscalYear->id)
                ->whereIn('district_id', $this->districtIds ?? [])
                ->whereIn('month_number', array_keys($periodInfo['weights']))
                ->selectRaw('deliverable_id, month_number, SUM(target_count) as total')
                ->groupBy('deliverable_id', 'month_number')
                ->get();

            foreach ($rows as $row) {
                $deliverableId = (int) $row->deliverable_id;
                $weight = $periodInfo['weights'][(int) $row->month_number] ?? 0.0;
                $weightedMonthly[$deliverableId] = ($weightedMonthly[$deliverableId] ?? 0.0)
                    + ((int) $row->total) * $weight;
            }
        }

        $monthlyDataSet = array_flip($deliverablesWithMonthlyData);

        $targets = [];
        $allIds = array_values(array_unique(array_merge(
            array_keys($districtFy),
            $deliverablesWithMonthlyData,
        )));

        foreach ($allIds as $deliverableId) {
            $deliverableId = (int) $deliverableId;

            if (isset($monthlyDataSet[$deliverableId])) {
                $value = (int) round($weightedMonthly[$deliverableId] ?? 0);
                if ($value > 0) {
                    $targets[$deliverableId] = $value;
                }

                continue;
            }

            $fyTotal = (int) ($districtFy[$deliverableId] ?? 0);
            if ($fyTotal > 0) {
                $targets[$deliverableId] = (int) round($fyTotal * $periodInfo['year_fraction']);
            }
        }

        $targets = $this->mergeDistrictScopedMonthlyTargets($fiscalYear, $targets, $periodInfo);

        $this->buildTargetIndexesFromTotals($targets);

        return $targets;
    }

    /**
     * Translate the active filter window into per-FY-month weights and an overall year fraction.
     *
     * - Keys in `weights` are fiscal-month indexes (M1..M12), matching official monthly targets.
     * - Each included fiscal month gets weight 1.0 (full month plan), because monthly targets are
     *   stored per fiscal month, not pro-rated by calendar days inside the month.
     * - `year_fraction` = (total days covered) / (days in fiscal year), used to pro-rate FY totals
     *   where no monthly breakdown exists (state targets, or district deliverables without monthly rows).
     *
     * @return array{weights: array<int, float>, year_fraction: float, has_narrowing: bool}
     */
    private function periodMonthWeights(?FiscalYear $fiscalYear): array
    {
        $hasNarrowing = $this->filter?->hasExplicitDateFilter() ?? false;

        if (! $hasNarrowing
            || ! $this->periodFrom
            || ! $this->periodTo
            || ! $fiscalYear?->starts_on
            || ! $fiscalYear?->ends_on
        ) {
            return ['weights' => [], 'year_fraction' => 1.0, 'has_narrowing' => false];
        }

        $fyStart = $fiscalYear->starts_on->copy()->startOfDay();
        $fyEnd = $fiscalYear->ends_on->copy()->startOfDay();
        $daysInFy = (int) $fyStart->diffInDays($fyEnd) + 1;

        $fromDate = $this->periodFrom->copy()->startOfDay();
        $toDate = $this->periodTo->copy()->startOfDay();

        if ($this->filter?->quarter !== null
            && $this->filter->quarter >= 1
            && $this->filter->quarter <= 4
        ) {
            $weights = array_fill_keys($fiscalYear->fiscalMonthNumbersForQuarter($this->filter->quarter), 1.0);
            $overlapDays = $fromDate->lte($toDate)
                ? (int) $fromDate->diffInDays($toDate) + 1
                : 0;
            $yearFraction = $daysInFy > 0 ? min(1.0, $overlapDays / $daysInFy) : 0.0;

            return [
                'weights' => $weights,
                'year_fraction' => $yearFraction,
                'has_narrowing' => true,
            ];
        }

        // Single calendar month: one full fiscal month at weight 1.0.
        if ($this->filter?->month !== null
            && $this->filter->month >= 1
            && $this->filter->month <= 12
        ) {
            $year = $this->filter->year ?? (int) ($fiscalYear->starts_on->year ?? now()->year);
            $anchor = Carbon::create($year, $this->filter->month, 15)->startOfDay();
            $fyMonthIdx = $fiscalYear->fiscalMonthIndex($anchor);
            if ($fyMonthIdx !== null) {
                $overlapDays = $fromDate->lte($toDate)
                    ? (int) $fromDate->diffInDays($toDate) + 1
                    : 0;
                $yearFraction = $daysInFy > 0 ? min(1.0, $overlapDays / $daysInFy) : 0.0;

                return [
                    'weights' => [$fyMonthIdx => 1.0],
                    'year_fraction' => $yearFraction,
                    'has_narrowing' => true,
                ];
            }
        }

        $weights = [];
        $totalOverlapDays = 0;

        $fyFirstCalendarMonth = $fyStart->copy()->startOfMonth();

        for ($m = 1; $m <= 12; $m++) {
            $calendarMonthStart = $fyFirstCalendarMonth->copy()->addMonths($m - 1)->startOfMonth();
            $calendarMonthEnd = $calendarMonthStart->copy()->endOfMonth()->startOfDay();

            $monthStartInFy = $calendarMonthStart->lt($fyStart) ? $fyStart->copy() : $calendarMonthStart->copy();
            $monthEndInFy = $calendarMonthEnd->gt($fyEnd) ? $fyEnd->copy() : $calendarMonthEnd->copy();

            if ($monthStartInFy->gt($monthEndInFy)) {
                continue;
            }

            $overlapStart = $monthStartInFy->gt($fromDate) ? $monthStartInFy : $fromDate;
            $overlapEnd = $monthEndInFy->lt($toDate) ? $monthEndInFy : $toDate;

            if ($overlapEnd->gte($overlapStart)) {
                $weights[$m] = 1.0;
                $totalOverlapDays += (int) $overlapStart->diffInDays($overlapEnd) + 1;
            }
        }

        $yearFraction = $daysInFy > 0 ? min(1.0, $totalOverlapDays / $daysInFy) : 0.0;

        return [
            'weights' => $weights,
            'year_fraction' => $yearFraction,
            'has_narrowing' => true,
        ];
    }

    /**
     * Map deliverable ids to MIS / service codes for target resolution (state + district).
     *
     * @param  array<int, int>  $targets
     */
    private function buildTargetIndexesFromTotals(array $targets): void
    {
        if ($targets === []) {
            return;
        }

        $deliverableIds = array_map('intval', array_keys($targets));
        $deliverables = Deliverable::query()
            ->whereIn('id', $deliverableIds)
            ->get(['id', 'code', 'name'])
            ->keyBy('id');

        $serviceCodesByDeliverableId = Service::query()
            ->where('is_active', true)
            ->whereNotNull('deliverable_id')
            ->whereIn('deliverable_id', $deliverableIds)
            ->get(['deliverable_id', 'code'])
            ->groupBy('deliverable_id')
            ->map(fn ($group) => $group->pluck('code')->map(fn ($c) => strtolower((string) $c))->all());

        foreach ($targets as $deliverableId => $total) {
            $total = (int) $total;
            $deliverable = $deliverables->get((int) $deliverableId);
            if (! $deliverable) {
                continue;
            }

            $deliverableCode = strtolower(trim((string) $deliverable->code));
            $this->indexStateTargetLookupCode($deliverableCode, $total);
            if (str_starts_with($deliverableCode, 'svc_')) {
                $this->indexStateTargetLookupCode(substr($deliverableCode, 4), $total);
            }

            foreach ($serviceCodesByDeliverableId[(int) $deliverableId] ?? [] as $serviceCode) {
                $this->indexStateTargetLookupCode($serviceCode, $total);
                $this->indexStateTargetLookupCode(
                    $this->serviceTargetDeliverables->deliverableCodeForServiceCode($serviceCode),
                    $total,
                );
            }

            $norm = $this->normalizeLabel((string) $deliverable->name);
            if ($norm !== '' && $total > 0) {
                $this->targetsByNameNorm[$norm] = max($this->targetsByNameNorm[$norm] ?? 0, $total);
            }
        }
    }

    /**
     * Pillar / subcategory rows are headings (blank target & achievement); leaves carry metrics.
     *
     * @param  list<string>  $serialParts
     */
    private function appendMatrixRows(array &$rows, array $node, array $serialParts): void
    {
        $children = $node['children'] ?? [];
        $rowType = (string) ($node['row_type'] ?? 'leaf');
        $serial = implode('.', $serialParts);

        if ($rowType === 'pillar' || $rowType === 'subcategory') {
            $rows[] = $this->formatHeadingRow($node, $serial);
        } elseif (isset($node['source'])) {
            $metrics = $this->resolveNodeMetrics($node);
            $rows[] = $this->formatRow($node, $serial, $metrics);
        }

        if ($children === []) {
            return;
        }

        $childIndex = 0;
        foreach ($children as $child) {
            $childIndex++;
            $this->appendMatrixRows($rows, $child, [...$serialParts, (string) $childIndex]);
        }
    }

    /**
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>
     */
    private function formatHeadingRow(array $node, string $serial): array
    {
        return [
            'row_type' => (string) ($node['row_type'] ?? 'pillar'),
            'serial' => $serial,
            'name' => (string) ($node['name'] ?? ''),
            'indicator_type' => '',
            'level' => '',
            'target' => null,
            'achievement' => null,
            'achievement_pct' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $node
     * @return array{target: ?int, achievement: int}
     */
    private function resolveNodeMetrics(array $node): array
    {
        $source = $node['source'] ?? ['type' => 'none'];
        $achievement = $this->achievementForSource($source);
        $target = $this->targetForSource($source, (string) ($node['name'] ?? ''));

        return [
            'target' => $target,
            'achievement' => $achievement,
        ];
    }

    /**
     * @param  array<string, mixed>  $source
     */
    private function achievementForSource(array $source): int
    {
        return match ($source['type'] ?? 'none') {
            'deliverable' => $this->achievementForDeliverableCode((string) ($source['code'] ?? '')),
            'service' => $this->achievementForServiceCode((string) ($source['code'] ?? '')),
            'services' => $this->achievementForServiceCodes((array) ($source['codes'] ?? [])),
            'cfa_count' => $this->cfaCount(),
            'onboarding_count' => $this->onboardingCount(),
            'potential_lakhpati_onboarding_count' => $this->potentialLakhpatiOnboardingCount(),
            'field_work_workshops' => $this->fieldWorkWorkshopsCount(),
            'field_work_participants' => $this->fieldWorkParticipantsCount(),
            'field_visit_sessions' => $this->fieldWorkWorkshopsCount(),
            'field_visit_participants' => $this->fieldWorkParticipantsCount(),
            'district_workshop_sessions' => $this->districtWorkshopSessionsCount(),
            'edp_sessions' => $this->edpSessionsCount(),
            'bst_sessions' => $this->bstSessionsCount(),
            'bst_participants' => $this->bstParticipantsCount(),
            'technical_training_sessions' => $this->technicalTrainingSessionsCount(),
            'technical_training_potential_lakhpati_sessions' => $this->technicalTrainingPotentialLakhpatiSessionsCount(),
            'market_linkage_unique_partners' => $this->marketLinkageUniquePartnersCount(),
            'market_linkage_incubatees' => $this->marketLinkageIncubateesCount(),
            'marketing_partner_outreach_count' => $this->marketingPartnerOutreachCount(),
            'marketing_partner_onboarded_count' => $this->marketingPartnerOnboardedCount(),
            'business_acceleration_partners_outreach_count' => $this->businessAccelerationPartnersOutreachCount(),
            'acceleration_services_initiation_count' => $this->accelerationServicesInitiationCount(),
            'demo_days_count' => $this->demoDaysCount(),
            'funding_schematic_partners_outreach_count' => $this->fundingSchematicPartnersOutreachCount(),
            'muy_newsletter_count' => $this->muyNewsletterEntriesCount(),
            'media_campaigns_count' => $this->mediaCampaignEntriesCount(),
            'community_org_outreach_count' => $this->communityOrgOutreachVisitsCount(),
            'capacity_building_stakeholder_sessions' => $this->capacityBuildingStakeholderSessionsCount(),
            'stakeholder_consultation_workshop_sessions' => $this->stakeholderConsultationWorkshopSessionsCount(),
            'line_department_meeting_sessions' => $this->lineDepartmentMeetingSessionsCount(),
            'reap_support_services' => $this->reapSupportServicesCount(),
            'schematic_convergence_services' => $this->schematicConvergenceServicesCount(),
            'pitch_deck_preparations' => $this->pitchDeckPreparationsCount(),
            'pitch_deck_combined' => $this->pitchDeckCombinedCount(),
            default => 0,
        };
    }

    /**
     * @param  array<string, mixed>  $source
     */
    private function targetForSource(array $source, string $indicatorName = ''): ?int
    {
        $target = match ($source['type'] ?? 'none') {
            'deliverable', 'service' => $this->resolveStateTargetForCodes([
                (string) ($source['code'] ?? ''),
            ]),
            'services' => $this->resolveStateTargetForCodes(
                array_map('strval', (array) ($source['codes'] ?? [])),
                sumServiceTargets: true,
            ),
            'bst_sessions' => $this->useOfficialMonthlyTargets
                ? $this->resolveStateTargetForCodes([(string) ($source['deliverable_code'] ?? 'bst_sessions')])
                : $this->bstSessionsPlannedTargetCount(),
            'cfa_count', 'onboarding_count', 'potential_lakhpati_onboarding_count', 'district_workshop_sessions', 'edp_sessions', 'bst_participants', 'field_work_workshops', 'field_work_participants', 'technical_training_sessions', 'technical_training_potential_lakhpati_sessions', 'capacity_building_stakeholder_sessions', 'stakeholder_consultation_workshop_sessions', 'line_department_meeting_sessions', 'pitch_deck_preparations', 'pitch_deck_combined', 'schematic_convergence_services', 'marketing_partner_outreach_count', 'marketing_partner_onboarded_count', 'business_acceleration_partners_outreach_count', 'acceleration_services_initiation_count', 'demo_days_count', 'funding_schematic_partners_outreach_count', 'muy_newsletter_count', 'media_campaigns_count' => $this->resolveStateTargetForCodes([
                (string) ($source['deliverable_code'] ?? ''),
            ]),
            'none' => ($source['deliverable_code'] ?? '') !== ''
                ? $this->resolveStateTargetForCodes([(string) $source['deliverable_code']])
                : null,
            'target_name' => $this->resolveStateTargetByNameKeyword((string) ($source['match'] ?? '')),
            default => null,
        };

        if ($target !== null) {
            return $target;
        }

        return $this->resolveStateTargetByIndicatorName($indicatorName);
    }

    /**
     * State targets are stored on MIS codes and/or per-service rows (svc_* from catalog sync).
     *
     * @param  list<string>  $codes
     */
    private function resolveStateTargetForCodes(array $codes, bool $sumServiceTargets = false): ?int
    {
        $deliverableIds = [];
        foreach ($codes as $code) {
            $deliverableIds = array_merge($deliverableIds, $this->deliverableIdsForLookupCode((string) $code));
        }
        $deliverableIds = array_values(array_unique(array_filter($deliverableIds)));

        if ($deliverableIds === []) {
            return null;
        }

        if ($sumServiceTargets) {
            $sum = 0;
            $hasAny = false;
            foreach ($deliverableIds as $id) {
                if (! array_key_exists($id, $this->targetsByDeliverableId)) {
                    continue;
                }
                $sum += (int) $this->targetsByDeliverableId[$id];
                $hasAny = true;
            }

            if ($hasAny) {
                return $sum;
            }
        } else {
            $fromIds = $this->bestTargetForDeliverableIds($deliverableIds);
            if ($fromIds !== null) {
                return $fromIds;
            }
        }

        return $this->bestTargetForLookupCodes($codes);
    }

    /**
     * @param  list<string>  $codes
     */
    private function bestTargetForLookupCodes(array $codes): ?int
    {
        $best = null;
        foreach ($codes as $code) {
            foreach ($this->candidateCodesForLookup((string) $code) as $candidate) {
                if (! isset($this->stateTargetsByLookupCode[$candidate])) {
                    continue;
                }
                $value = (int) $this->stateTargetsByLookupCode[$candidate];
                if ($best === null || $value > $best) {
                    $best = $value;
                }
            }
        }

        return $best;
    }

    private function indexStateTargetLookupCode(string $code, int $total): void
    {
        $code = strtolower(trim($code));
        if ($code === '' || $total <= 0) {
            return;
        }

        $this->stateTargetsByLookupCode[$code] = max($this->stateTargetsByLookupCode[$code] ?? 0, $total);
    }

    /**
     * @return list<int>
     */
    private function deliverableIdsForLookupCode(string $code): array
    {
        $code = strtolower(trim($code));
        if ($code === '') {
            return [];
        }

        $ids = [];
        foreach ($this->candidateCodesForLookup($code) as $candidate) {
            if (isset($this->deliverableIdsByCode[$candidate])) {
                $ids[] = (int) $this->deliverableIdsByCode[$candidate];
            }
        }

        $candidateCodes = $this->candidateCodesForLookup($code);
        $serviceIds = Service::query()
            ->where('is_active', true)
            ->where(function ($q) use ($candidateCodes): void {
                foreach ($candidateCodes as $candidate) {
                    $q->orWhere('code', $candidate);
                }
                $q->orWhereHas('deliverable', function ($dq) use ($candidateCodes): void {
                    $dq->whereIn('code', $candidateCodes);
                });
            })
            ->pluck('deliverable_id');

        foreach ($serviceIds as $id) {
            if ($id) {
                $ids[] = (int) $id;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @return list<string>
     */
    private function candidateCodesForLookup(string $code): array
    {
        $codes = array_values(array_unique(array_filter([
            $code,
            $this->serviceTargetDeliverables->deliverableCodeForServiceCode($code),
        ])));

        $aliases = config('program_deliverables.target_code_aliases.'.$code, []);
        if (is_array($aliases)) {
            foreach ($aliases as $alias) {
                $alias = strtolower(trim((string) $alias));
                if ($alias === '') {
                    continue;
                }
                $codes[] = $alias;
                $codes[] = $this->serviceTargetDeliverables->deliverableCodeForServiceCode($alias);
            }
        }

        return array_values(array_unique(array_filter($codes)));
    }

    /**
     * @param  list<int>  $deliverableIds
     */
    private function bestTargetForDeliverableIds(array $deliverableIds): ?int
    {
        $best = null;
        foreach ($deliverableIds as $id) {
            if (! array_key_exists($id, $this->targetsByDeliverableId)) {
                continue;
            }
            $value = (int) $this->targetsByDeliverableId[$id];
            if ($best === null || $value > $best) {
                $best = $value;
            }
        }

        return $best;
    }

    private function resolveStateTargetByNameKeyword(string $keyword): ?int
    {
        $keyword = $this->normalizeLabel($keyword);
        if ($keyword === '') {
            return null;
        }

        $best = null;
        foreach ($this->targetsByNameNorm as $name => $target) {
            if (! str_contains($name, $keyword)) {
                continue;
            }
            if ($best === null || $target > $best) {
                $best = $target;
            }
        }

        return $best;
    }

    private function resolveStateTargetByIndicatorName(string $indicatorName): ?int
    {
        $indicator = $this->normalizeLabel($indicatorName);
        if ($indicator === '') {
            return null;
        }

        if (isset($this->targetsByNameNorm[$indicator])) {
            return $this->targetsByNameNorm[$indicator];
        }

        $best = null;
        foreach ($this->targetsByNameNorm as $name => $target) {
            if (strlen($name) < 3) {
                continue;
            }
            if (str_contains($indicator, $name) || str_contains($name, $indicator)) {
                if ($best === null || $target > $best) {
                    $best = $target;
                }
            }
        }

        return $best;
    }

    private function normalizeLabel(string $value): string
    {
        $value = strtolower(trim($value));

        return trim(preg_replace('/\s+/', ' ', $value) ?? '');
    }

    private function achievementForDeliverableCode(string $code): int
    {
        if ($code === '') {
            return 0;
        }

        if ($code === 'social_media') {
            return $this->socialMediaPostsCount();
        }

        if ($code === 'case_studies') {
            return $this->caseStudyEntriesCount();
        }

        if ($code === 'buyer_seller_meets') {
            return $this->deliverableAchievementFromServiceCases($code)
                + AccelerationServicesDeliverablesSupport::countBuyerSellerMeets($this->periodFrom, $this->periodTo);
        }

        if ($code === 'acceleration_services') {
            return AccelerationServicesDeliverablesSupport::countUniqueInitiations($this->periodFrom, $this->periodTo);
        }

        return $this->deliverableAchievementFromServiceCases($code);
    }

    private function deliverableAchievementFromServiceCases(string $code): int
    {
        foreach ($this->candidateCodesForLookup($code) as $candidate) {
            $keys = [$candidate];
            if (str_starts_with($candidate, 'svc_')) {
                $keys[] = substr($candidate, 4);
            }
            foreach ($keys as $key) {
                if ($key !== '' && isset($this->achievementByMisCode[$key])) {
                    return (int) $this->achievementByMisCode[$key];
                }
            }
        }

        $total = 0;
        $countedServiceIds = [];

        $directServiceId = $this->serviceIdsByCode[$code] ?? null;
        if ($directServiceId) {
            $total += (int) ($this->achievementByServiceId[$directServiceId] ?? 0);
            $countedServiceIds[$directServiceId] = true;
        }

        foreach ($this->candidateCodesForLookup($code) as $candidate) {
            $candidateServiceId = $this->serviceIdsByCode[$candidate] ?? null;
            if ($candidateServiceId && ! isset($countedServiceIds[$candidateServiceId])) {
                $total += (int) ($this->achievementByServiceId[$candidateServiceId] ?? 0);
                $countedServiceIds[$candidateServiceId] = true;
            }
        }

        foreach ($this->deliverableIdsForLookupCode($code) as $deliverableId) {
            $serviceIds = Service::query()
                ->where('deliverable_id', $deliverableId)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();

            foreach ($serviceIds as $serviceId) {
                if (isset($countedServiceIds[$serviceId])) {
                    continue;
                }
                $total += (int) ($this->achievementByServiceId[$serviceId] ?? 0);
                $countedServiceIds[$serviceId] = true;
            }
        }

        return $total;
    }

    private function achievementForServiceCode(string $code): int
    {
        $serviceId = $this->serviceIdsByCode[$code] ?? null;

        return $serviceId ? (int) ($this->achievementByServiceId[$serviceId] ?? 0) : 0;
    }

    /**
     * @param  list<string>  $codes
     */
    private function achievementForServiceCodes(array $codes): int
    {
        $total = 0;
        foreach ($codes as $code) {
            $total += $this->achievementForServiceCode((string) $code);
        }

        return $total;
    }

    private function cfaCount(): int
    {
        if (! Schema::hasTable('cfa_submissions')) {
            return 0;
        }

        $query = DB::table('cfa_submissions');
        $this->applyDistrictScope($query, 'district_id');
        $this->applyCfaAchievementScope($query);

        return (int) $query->count();
    }

    private function socialMediaPostsCount(): int
    {
        if (! Schema::hasTable('social_media_posts')) {
            return 0;
        }

        $query = DB::table('social_media_posts');
        $this->applySocialMediaPostsAchievementScope($query);

        return (int) $query->count();
    }

    private function caseStudyEntriesCount(): int
    {
        return \App\Support\CaseStudyEntriesDeliverablesSupport::countEntries(
            $this->periodFrom,
            $this->periodTo,
        );
    }

    private function muyNewsletterEntriesCount(): int
    {
        return \App\Support\MuyNewsletterEntriesDeliverablesSupport::countEntries(
            $this->periodFrom,
            $this->periodTo,
        );
    }

    private function mediaCampaignEntriesCount(): int
    {
        return \App\Support\MediaCampaignEntriesDeliverablesSupport::countEntries(
            $this->periodFrom,
            $this->periodTo,
        );
    }

    private function marketLinkageTablesReady(): bool
    {
        return Schema::hasTable('market_linkage_submissions') && Schema::hasTable('market_linkage_partners');
    }

    private function marketLinkageUniquePartnersCount(): int
    {
        if (! $this->marketLinkageTablesReady() || $this->districtIds === []) {
            return 0;
        }

        $query = DB::table('market_linkage_partners as mlp')
            ->join('market_linkage_submissions as mls', 'mls.id', '=', 'mlp.market_linkage_submission_id');

        $this->applyMarketLinkageApprovedScope($query);
        $this->applyDistrictScope($query, 'mls.district_id');

        $names = $query->distinct()->pluck('mlp.partner_name');

        return $this->marketLinkagePartners->countUniquePartnerKeys($names);
    }

    private function marketLinkageIncubateesCount(): int
    {
        if ($this->districtIds === []) {
            return 0;
        }

        if (! $this->marketLinkageTablesReady() && MarketLinkageUnifiedListingSupport::marketLinkServiceIds() === []) {
            return 0;
        }

        [$from, $to] = $this->explicitMarketLinkagePeriod();

        // Market Linkage module + approved orphan market-link service cases (each incubatee once).
        return MarketLinkageUnifiedListingSupport::approvedIncubateeModeCounts($this->districtIds, true, $from, $to, true)['total_incubatees'];
    }

    /**
     * Market linkage counts are cumulative by default; only apply a date window when the user
     * explicitly narrows the period (quarter/month/date range).
     *
     * @return array{0: ?Carbon, 1: ?Carbon}
     */
    private function explicitMarketLinkagePeriod(): array
    {
        if (($this->filter?->hasExplicitDateFilter() ?? false) && $this->periodFrom && $this->periodTo) {
            return [$this->periodFrom, $this->periodTo];
        }

        return [null, null];
    }

    /**
     * @param  Builder  $query
     */
    private function applyMarketLinkageApprovedScope($query): void
    {
        if (MarketLinkageSubmission::supportsWorkflow()) {
            $query->where('mls.status', ServiceCase::STATUS_APPROVED);
        }
    }

    /**
     * @param  Builder  $query
     */
    private function applyMarketLinkagePartnerDateScope($query, string $column = 'mlp.linkage_date'): void
    {
        $floor = $this->phase3FloorDate();

        if ($this->periodFrom && $this->periodTo) {
            $from = $this->periodFrom->copy();
            if ($from->lt($floor)) {
                $from = $floor->copy();
            }
            $query->whereBetween($column, [$from->toDateString(), $this->periodTo->toDateString()]);

            return;
        }

        $query->where($column, '>=', $floor->toDateString());
    }

    /**
     * State-level log: count posts by `posted_on` within the active fiscal / filter window.
     *
     * @param  Builder  $query
     */
    private function applySocialMediaPostsAchievementScope($query): void
    {
        $floor = $this->phase3FloorDate();

        if ($this->periodFrom && $this->periodTo) {
            $from = $this->periodFrom->copy();
            if ($from->lt($floor)) {
                $from = $floor->copy();
            }
            $query->whereBetween('posted_on', [$from->toDateString(), $this->periodTo->toDateString()]);

            return;
        }

        $query->where('posted_on', '>=', $floor->toDateString());
    }

    private function onboardingCount(): int
    {
        if (! Schema::hasTable('onboarding_batch_cfa') || ! Schema::hasTable('onboarding_batches')) {
            return 0;
        }

        $query = DB::table('onboarding_batch_cfa as obc')
            ->join('onboarding_batches as ob', 'ob.id', '=', 'obc.onboarding_batch_id')
            ->join('cfa_submissions as cs', 'cs.id', '=', 'obc.cfa_submission_id')
            ->where('ob.status', 'locked')
            ->whereNotNull('ob.locked_at');

        $this->applyDistrictScope($query, 'cs.district_id');
        $this->applyOnboardingAchievementScope($query);

        return (int) $query->count();
    }

    private function potentialLakhpatiOnboardingCount(): int
    {
        if (! Schema::hasTable('onboarding_batch_cfa') || ! Schema::hasTable('onboarding_batches')) {
            return 0;
        }

        $query = DB::table('onboarding_batch_cfa as obc')
            ->join('onboarding_batches as ob', 'ob.id', '=', 'obc.onboarding_batch_id')
            ->join('cfa_submissions as cs', 'cs.id', '=', 'obc.cfa_submission_id')
            ->where('ob.status', 'locked')
            ->whereNotNull('ob.locked_at');

        $this->applyDistrictScope($query, 'cs.district_id');
        $this->applyOnboardingAchievementScope($query);
        $query->whereRaw(PotentialLakhpatiOnboardingSql::qualifiesSql());

        return (int) $query->count();
    }

    /**
     * Align with state dashboard: CFA achievement = submissions tagged to the FY (`fiscal_year_id`),
     * not every row whose `created_at` falls in the FY calendar window.
     *
     * @param  Builder  $query
     */
    private function applyCfaAchievementScope($query): void
    {
        $fyId = (int) ($this->activeFiscalYear?->id ?? 0);

        if ($fyId > 0 && Schema::hasColumn('cfa_submissions', 'fiscal_year_id')) {
            $query->where('fiscal_year_id', $fyId);

            if ($this->filter?->hasExplicitDateFilter()) {
                $this->applyPeriodFilter($query, 'created_at');
            }

            return;
        }

        $floor = $this->phase3FloorDate();
        if ($this->periodFrom && $this->periodTo) {
            $from = $this->periodFrom->copy();
            if ($from->lt($floor)) {
                $from = $floor->copy();
            }
            $query->whereBetween('created_at', [$from->toDateTimeString(), $this->periodTo->toDateTimeString()]);
        } else {
            $query->where('created_at', '>=', $floor->toDateTimeString());
        }
    }

    /**
     * @param  Builder  $query
     */
    private function applyOnboardingAchievementScope($query): void
    {
        // Count onboardings by the real day the people were onboarded (ob.onboarding_date),
        // not by ob.locked_at — admin can lock a batch days/weeks after the actual onboarding,
        // which previously misattributed members to the wrong month.
        $floor = $this->phase3FloorDate();
        $query->where('ob.onboarding_date', '>=', $floor->toDateString());

        if ($this->filter?->hasExplicitDateFilter() && $this->periodFrom && $this->periodTo) {
            $from = $this->periodFrom->copy();
            if ($from->lt($floor)) {
                $from = $floor->copy();
            }
            $query->whereBetween('ob.onboarding_date', [$from->toDateString(), $this->periodTo->toDateString()]);
        }
    }

    private function phase3FloorDate(): Carbon
    {
        $raw = (string) config('program_deliverables.phase3_floor_date', '2026-04-01');

        return Carbon::parse($raw)->startOfDay();
    }

    /**
     * MIS 1.3 — Field work visits (field_coordinator_attendance_reports) +
     *           Block workshops (block_workshops).
     */
    private function fieldWorkWorkshopsCount(): int
    {
        $total = 0;

        if (Schema::hasTable('field_coordinator_attendance_reports')) {
            $query = FieldCoordinatorAttendanceReport::query();
            $this->applyFieldWorkAchievementScope($query);
            $total += (int) $query->count();
        }

        if (Schema::hasTable('block_workshops')) {
            $query = DB::table('block_workshops');
            $this->applyBlockWorkshopCountScope($query);
            $total += (int) $query->count();
        }

        return $total;
    }

    /**
     * MIS 1.3.1 — female participants only, across both tables.
     */
    private function fieldWorkParticipantsCount(): int
    {
        $total = 0;

        if (Schema::hasTable('field_coordinator_attendance_reports')) {
            $query = FieldCoordinatorAttendanceReport::query();
            $this->applyFieldWorkAchievementScope($query);
            $total += $this->sumFemaleParticipants($query, 'field_coordinator_attendance_reports');
        }

        if (Schema::hasTable('block_workshops')) {
            $query = DB::table('block_workshops');
            $this->applyBlockWorkshopCountScope($query);
            if (Schema::hasColumn('block_workshops', 'participants_female_count')) {
                $total += (int) $query->sum(DB::raw('COALESCE(participants_female_count, 0)'));
            }
        }

        return $total;
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder|Builder  $query
     */
    private function sumFemaleParticipants($query, string $table): int
    {
        if (! Schema::hasColumn($table, 'participants_female_count')) {
            return 0;
        }

        $prefix = str_contains($table, '.') ? $table : $table;

        return (int) $query->sum(DB::raw('COALESCE('.$prefix.'.participants_female_count, 0)'));
    }

    /**
     * @param  Builder  $query
     */
    private function applyBlockWorkshopCountScope($query): void
    {
        $this->applyDistrictScope($query, 'district_id');

        $query->where(function ($q): void {
            $q->where('status', 'submitted')->orWhereNull('status');
        });

        $floor = $this->phase3FloorDate();

        if ($this->filter?->hasExplicitDateFilter() && $this->periodFrom && $this->periodTo) {
            $from = $this->periodFrom->copy();
            if ($from->lt($floor)) {
                $from = $floor->copy();
            }
            $query->whereBetween('visit_date', [$from->toDateString(), $this->periodTo->toDateString()]);

            return;
        }

        $query->where('visit_date', '>=', $floor->toDateString());

        if ($this->periodTo) {
            $query->where('visit_date', '<=', $this->periodTo->toDateString());
        }
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<FieldCoordinatorAttendanceReport>  $query
     */
    private function applyFieldWorkAchievementScope($query): void
    {
        $this->applyDistrictScopeOnModel($query, 'district_id');

        if (FieldCoordinatorAttendanceReport::supportsDraftWorkflow()) {
            $query->submitted();
        }

        $floor = $this->phase3FloorDate();

        if ($this->filter?->hasExplicitDateFilter() && $this->periodFrom && $this->periodTo) {
            $from = $this->periodFrom->copy();
            if ($from->lt($floor)) {
                $from = $floor->copy();
            }
            $query->whereBetween('visit_date', [$from->toDateString(), $this->periodTo->toDateString()]);

            return;
        }

        $query->where('visit_date', '>=', $floor->toDateString());

        if ($this->periodTo) {
            $query->where('visit_date', '<=', $this->periodTo->toDateString());
        }
    }

    private function districtWorkshopSessionsCount(): int
    {
        if (! Schema::hasTable('district_workshop_sessions')) {
            return 0;
        }

        $query = DB::table('district_workshop_sessions');
        $dateCol = Schema::hasColumn('district_workshop_sessions', 'event_date')
            ? 'event_date'
            : (Schema::hasColumn('district_workshop_sessions', 'session_date') ? 'session_date' : 'created_at');
        $this->applyDistrictScope($query, 'district_id');
        $this->applyPeriodFilter($query, $dateCol);

        return (int) $query->count();
    }

    private function communityOrgOutreachVisitsCount(): int
    {
        if (! Schema::hasTable('community_organization_outreach_visits')) {
            return 0;
        }

        $query = DB::table('community_organization_outreach_visits');
        $this->applyDistrictScope($query, 'district_id');
        $this->applyPeriodFilter($query, 'visit_date');
        MisFieldActivityApproval::applyApprovedOnlyFilter($query, 'community_organization_outreach_visits');

        return (int) $query->count();
    }

    private function marketingPartnerOutreachCount(): int
    {
        return MarketingPartnerOutreachDeliverablesSupport::countOutreach(
            $this->periodFrom,
            $this->periodTo,
        );
    }

    private function marketingPartnerOnboardedCount(): int
    {
        return MarketingPartnerOnboardedCombinedDeliverablesSupport::combinedCount(
            $this->periodFrom,
            $this->periodTo,
            $this->districtIds,
        );
    }

    private function businessAccelerationPartnersOutreachCount(): int
    {
        return BusinessAccelerationPartnersOutreachDeliverablesSupport::countUniquePartners(
            $this->periodFrom,
            $this->periodTo,
        );
    }

    private function accelerationServicesInitiationCount(): int
    {
        return AccelerationServicesDeliverablesSupport::countUniqueInitiations(
            $this->periodFrom,
            $this->periodTo,
        );
    }

    private function demoDaysCount(): int
    {
        return DemoDaysDeliverablesSupport::countEvents(
            $this->periodFrom,
            $this->periodTo,
        );
    }

    private function fundingSchematicPartnersOutreachCount(): int
    {
        return FundingSchematicPartnersOutreachDeliverablesSupport::countUniquePartners(
            $this->periodFrom,
            $this->periodTo,
        );
    }

    private function edpSessionsCount(): int
    {
        if (! Schema::hasTable('eap_edp_sessions')) {
            return 0;
        }

        $query = DB::table('eap_edp_sessions');
        $dateCol = Schema::hasColumn('eap_edp_sessions', 'session_date') ? 'session_date' : 'event_date';
        if (! Schema::hasColumn('eap_edp_sessions', $dateCol)) {
            $dateCol = 'created_at';
        }
        $this->applyDistrictScope($query, 'district_id');
        $this->applyPeriodFilter($query, $dateCol);

        return (int) $query->count();
    }

    private function bstSessionsCount(): int
    {
        if (! BstTrainingDeliverablesSupport::tableReady()) {
            return 0;
        }

        return (int) BstTrainingDeliverablesSupport::scopedPackagesQuery(
            $this->districtIds,
            $this->periodFrom,
            $this->periodTo,
        )->count();
    }

    private function bstSessionsPlannedTargetCount(): int
    {
        return BstTrainingMonthPlanTargetSupport::plannedRequiredSessionCount(
            $this->districtIds,
            $this->periodFrom,
            $this->periodTo,
        );
    }

    private function bstParticipantsCount(): int
    {
        return BstTrainingDeliverablesSupport::countUniqueParticipants(
            $this->districtIds,
            $this->periodFrom,
            $this->periodTo,
        );
    }

    private function technicalTrainingSessionsCount(): int
    {
        if (Schema::hasTable('technical_training_sessions')) {
            $query = DB::table('technical_training_sessions');
            $dateCol = Schema::hasColumn('technical_training_sessions', 'session_date') ? 'session_date' : 'created_at';
            if (Schema::hasColumn('technical_training_sessions', 'district_id')) {
                $this->applyDistrictScope($query, 'district_id');
            }
            $this->applyPeriodFilter($query, $dateCol);

            return (int) $query->count();
        }

        if (Schema::hasTable('technical_trainings')) {
            $query = DB::table('technical_trainings');
            $dateCol = Schema::hasColumn('technical_trainings', 'event_date') ? 'event_date' : 'created_at';
            $this->applyDistrictScope($query, 'district_id');
            $this->applyPeriodFilter($query, $dateCol);
            MisFieldActivityApproval::applyApprovedOnlyFilter($query, 'technical_trainings');

            return (int) $query->count();
        }

        return 0;
    }

    private function technicalTrainingPotentialLakhpatiSessionsCount(): int
    {
        return PotentialLakhpatiTechnicalTrainingDeliverablesSupport::countSessions(
            $this->districtIds,
            $this->periodFrom,
            $this->periodTo,
        );
    }

    private function capacityBuildingStakeholderSessionsCount(): int
    {
        return CapacityBuildingStakeholdersDeliverablesSupport::countSessions(
            $this->periodFrom,
            $this->periodTo,
        );
    }

    private function stakeholderConsultationWorkshopSessionsCount(): int
    {
        return StakeholderConsultationWorkshopDeliverablesSupport::countWorkshops(
            $this->periodFrom,
            $this->periodTo,
        );
    }

    private function lineDepartmentMeetingSessionsCount(): int
    {
        return LineDepartmentMeetingDeliverablesSupport::countMeetings(
            $this->periodFrom,
            $this->periodTo,
            $this->districtIds,
        );
    }

    private function reapSupportServicesCount(): int
    {
        return ConvergenceReapSupportDeliverablesSupport::countCases(
            $this->districtIds,
            $this->periodFrom,
            $this->periodTo,
        );
    }

    private function schematicConvergenceServicesCount(): int
    {
        return ConvergenceReapSupportDeliverablesSupport::countSchematicConvergenceCases(
            $this->districtIds,
            $this->periodFrom,
            $this->periodTo,
        );
    }

    private function pitchDeckPreparationsCount(): int
    {
        return PitchDeckPreparationsDeliverablesSupport::countPreparations(
            $this->periodFrom,
            $this->periodTo,
            $this->districtIds,
        );
    }

    private function pitchDeckCombinedCount(): int
    {
        return PitchDeckCombinedDeliverablesSupport::combinedCount(
            $this->periodFrom,
            $this->periodTo,
            $this->districtIds,
        );
    }

    private ?FiscalYear $activeFiscalYear = null;

    /**
     * @param  Builder  $query
     */
    private function applyDistrictScope($query, string $column): void
    {
        if ($this->districtIds === null) {
            return;
        }

        if ($this->districtIds === []) {
            $query->whereRaw('0 = 1');

            return;
        }

        $query->whereIn($column, $this->districtIds);
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<FieldCoordinatorAttendanceReport>  $query
     */
    private function applyDistrictScopeOnModel($query, string $column): void
    {
        if ($this->districtIds === null) {
            return;
        }

        if ($this->districtIds === []) {
            $query->whereRaw('0 = 1');

            return;
        }

        $query->whereIn($column, $this->districtIds);
    }

    /**
     * @param  Builder  $query
     */
    private function applyPeriodFilter($query, string $column): void
    {
        if (! $this->periodFrom || ! $this->periodTo) {
            return;
        }

        $query->whereBetween($column, [
            $this->periodFrom->toDateTimeString(),
            $this->periodTo->toDateTimeString(),
        ]);
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<FieldCoordinatorAttendanceReport>  $query
     */
    private function applyPeriodFilterOnModel($query, string $column): void
    {
        if (! $this->periodFrom || ! $this->periodTo) {
            return;
        }

        $query->whereBetween($column, [
            $this->periodFrom->toDateString(),
            $this->periodTo->toDateString(),
        ]);
    }

    /**
     * @param  Builder  $query
     */
    private function applyBstMonthSessionPeriod($query): void
    {
        if (! $this->periodFrom || ! $this->periodTo) {
            return;
        }

        $query->where(function ($q): void {
            $cursor = $this->periodFrom->copy()->startOfMonth();
            $end = $this->periodTo->copy()->startOfMonth();
            while ($cursor->lte($end)) {
                $q->orWhere(function ($q2) use ($cursor): void {
                    $q2->where('calendar_year', $cursor->year)
                        ->where('calendar_month', $cursor->month);
                });
                $cursor->addMonth();
            }
        });
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  array{target: ?int, achievement: int}  $metrics
     * @return array<string, mixed>
     */
    private function formatRow(array $node, string $serial, array $metrics): array
    {
        $target = $metrics['target'];
        $achievement = $metrics['achievement'];
        $targetLabel = null;
        $source = is_array($node['source'] ?? null) ? $node['source'] : [];
        $level = $this->rowMetadata->resolveLevel($node, $serial);

        if ($this->viewerRole === 'district_staff'
            && HubTargetDeliverablesSupport::isHubTargetRow($serial, $source)) {
            $target = null;
            $targetLabel = HubTargetDeliverablesSupport::LABEL;
        } elseif ($this->isDistrictScopedNonPrimaryHubTargetRow($serial, $source)) {
            $target = 0;
        } elseif ($this->viewerRole === 'district_staff'
            && StateTargetLabelDeliverablesSupport::isStateTargetLabelRow($serial, $source)) {
            $target = null;
            $targetLabel = StateTargetLabelDeliverablesSupport::LABEL;
        } elseif ($target === null && NeedBasedDeliverablesSupport::isNeedBasedRow($serial, $source)) {
            $targetLabel = NeedBasedDeliverablesSupport::LABEL;
        } elseif ($level === 'State' && ! $this->viewerUsesStateTargetNumbers()) {
            $target = null;
            $targetLabel = 'State';
        }

        $achievementPct = $targetLabel !== null ? null : $this->percent($target, $achievement);

        return [
            'row_type' => (string) ($node['row_type'] ?? 'leaf'),
            'serial' => $serial,
            'name' => (string) ($node['name'] ?? ''),
            'indicator_type' => $this->rowMetadata->resolveIndicatorType($node, $serial),
            'level' => $level,
            'target' => $target,
            'target_label' => $targetLabel,
            'achievement' => $achievement,
            'achievement_pct' => $achievementPct,
            'performance_tone' => $achievementPct !== null ? $this->performanceTone($achievementPct) : null,
            'source_type' => (string) ($source['type'] ?? 'none'),
            'drilldown' => ($source['type'] ?? 'none') !== 'none',
        ];
    }

    /**
     * State admin / state staff see numeric state targets on State-level indicators;
     * district and hub viewers see the "State" label instead.
     */
    private function viewerUsesStateTargetNumbers(): bool
    {
        return in_array($this->viewerRole, ['state_admin', 'state_staff'], true);
    }

    /**
     * Hub monthly targets apply only on Almora and Pauri Garhwal district lines.
     */
    private function scopeReceivesHubTargets(): bool
    {
        $districtIds = $this->districtIds ?? [];

        return HubTargetDeliverablesSupport::filterDistrictIdsForHubTargets($districtIds) !== [];
    }

    /**
     * District-scoped views, but not Almora / Pauri Garhwal — show target 0.
     */
    private function isDistrictScopedNonPrimaryHubTargetRow(string $serial, array $source): bool
    {
        if ($this->districtIds === null || $this->districtIds === []) {
            return false;
        }

        return HubTargetDeliverablesSupport::isHubTargetRow($serial, $source)
            && ! $this->scopeReceivesHubTargets();
    }

    /**
     * @return array<int, int>
     */
    private function loadServiceCaseAchievements(): void
    {
        $this->achievementByServiceId = [];
        $this->achievementByMisCode = [];

        if ($this->districtIds === []) {
            return;
        }

        if (! Schema::hasTable('service_cases') || ! Schema::hasTable('services')) {
            return;
        }

        $dateExpr = $this->serviceCaseAchievementDateExpression();

        $statuses = [ServiceCase::STATUS_APPROVED, ServiceCase::STATUS_COMPLETED];

        $query = DB::table('service_cases as sc')
            ->join('services as s', 's.id', '=', 'sc.service_id')
            ->leftJoin('deliverables as d', 'd.id', '=', 's.deliverable_id')
            ->leftJoin('cfa_submissions as cs', 'cs.id', '=', 'sc.cfa_submission_id')
            ->whereIn('sc.status', $statuses)
            ->whereNotNull('sc.service_id');

        if ($this->districtIds !== null) {
            $this->legacyServiceCases->applyAchievementDistrictScopeToServiceCaseQuery($query, $this->districtIds);
        }

        $floor = $this->phase3FloorDate();
        if ($this->periodFrom && $this->periodTo) {
            $from = $this->periodFrom->copy();
            if ($this->periodTo->gte($floor) && $from->lt($floor)) {
                $from = $floor->copy();
            }
            $query->whereBetween(DB::raw($dateExpr), [$from->toDateTimeString(), $this->periodTo->toDateTimeString()]);
        } elseif ($this->periodTo && $this->periodTo->gte($floor)) {
            $query->where(DB::raw($dateExpr), '>=', $floor->toDateTimeString());
        }

        $rows = $query
            ->selectRaw('s.code as service_code, s.name as service_name, d.code as deliverable_code, d.name as deliverable_name, d.mis_entry_label as deliverable_label, sc.service_id, COUNT(*) as total')
            ->groupBy('s.code', 's.name', 'd.code', 'd.name', 'd.mis_entry_label', 'sc.service_id')
            ->get();

        foreach ($rows as $row) {
            $total = (int) $row->total;
            $serviceId = (int) $row->service_id;
            $this->achievementByServiceId[$serviceId] = ($this->achievementByServiceId[$serviceId] ?? 0) + $total;

            foreach ($this->misCodesForAchievementKeys(
                (string) ($row->service_code ?? ''),
                (string) ($row->deliverable_code ?? ''),
                (string) ($row->service_name ?? ''),
                (string) ($row->deliverable_name ?? ''),
                (string) ($row->deliverable_label ?? ''),
            ) as $misCode) {
                $this->achievementByMisCode[$misCode] = ($this->achievementByMisCode[$misCode] ?? 0) + $total;
            }
        }
    }

    private function serviceCaseAchievementDateExpression(): string
    {
        $parts = [];
        foreach (['approved_at', 'completed_at', 'delivered_on', 'submitted_at', 'created_at'] as $column) {
            if (Schema::hasColumn('service_cases', $column)) {
                $parts[] = 'sc.'.$column;
            }
        }

        if ($parts === []) {
            return 'sc.created_at';
        }

        return count($parts) === 1 ? $parts[0] : 'COALESCE('.implode(', ', $parts).')';
    }

    /**
     * @return list<string>
     */
    private function misCodesForAchievementKeys(
        string $serviceCode,
        string $deliverableCode,
        string $serviceName = '',
        string $deliverableName = '',
        string $deliverableLabel = '',
    ): array {
        $codes = [];
        $serviceCode = strtolower(trim($serviceCode));
        $deliverableCode = strtolower(trim($deliverableCode));

        if ($deliverableCode !== '') {
            $codes[] = $deliverableCode;
            if (str_starts_with($deliverableCode, 'svc_')) {
                $codes[] = substr($deliverableCode, 4);
            }
        }

        if ($serviceCode !== '') {
            $codes[] = $serviceCode;
        }

        foreach (config('program_deliverables.target_code_aliases', []) as $misCode => $aliases) {
            if (! is_array($aliases)) {
                continue;
            }
            $misCode = strtolower(trim((string) $misCode));
            if ($serviceCode === $misCode || in_array($serviceCode, $aliases, true)) {
                $codes[] = $misCode;
            }
            if ($serviceCode !== '' && str_contains($serviceCode, $misCode)) {
                $codes[] = $misCode;
            }
            foreach ($aliases as $alias) {
                $alias = strtolower(trim((string) $alias));
                if ($alias === '') {
                    continue;
                }
                if ($serviceCode === $alias || ($serviceCode !== '' && str_contains($serviceCode, $alias))) {
                    $codes[] = $misCode;
                }
            }
        }

        $labelHaystack = $this->normalizeLabel(implode(' ', array_filter([
            $serviceName,
            $deliverableName,
            $deliverableLabel,
            $serviceCode,
            $deliverableCode,
        ])));

        foreach (config('program_deliverables.achievement_deliverable_keywords', []) as $misCode => $keywords) {
            if (! is_array($keywords)) {
                continue;
            }
            $misCode = strtolower(trim((string) $misCode));
            foreach ($keywords as $keyword) {
                $keyword = $this->normalizeLabel((string) $keyword);
                if ($keyword !== '' && str_contains($labelHaystack, $keyword)) {
                    $codes[] = $misCode;
                    break;
                }
            }
        }

        return array_values(array_unique(array_filter($codes)));
    }

    private function percent(?int $target, int $achievement): ?int
    {
        if ($target === null || $target <= 0) {
            return null;
        }

        return (int) round(($achievement / $target) * 100);
    }

    private function performanceTone(?int $achievementPct): ?string
    {
        if ($achievementPct === null) {
            return null;
        }

        if ($achievementPct >= 90) {
            return 'good';
        }

        if ($achievementPct >= 60) {
            return 'warn';
        }

        return 'critical';
    }
}
