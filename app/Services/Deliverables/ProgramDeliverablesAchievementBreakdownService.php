<?php

namespace App\Services\Deliverables;

use App\Models\Deliverable;
use App\Models\District;
use App\Models\FieldCoordinatorAttendanceReport;
use App\Models\FiscalYear;
use App\Models\MarketLinkageSubmission;
use App\Models\Service;
use App\Models\ServiceCase;
use App\Services\LegacyApplicationServiceCaseSupport;
use App\Services\MarketLinkagePartnerCatalogService;
use App\Services\ServiceTargetDeliverableSyncService;
use App\Support\CapacityBuildingStakeholdersDeliverablesSupport;
use App\Support\BstTrainingDeliverablesSupport;
use App\Support\PotentialLakhpatiOnboardingSql;
use App\Support\PotentialLakhpatiTechnicalTrainingDeliverablesSupport;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ProgramDeliverablesAchievementBreakdownService
{
    /** @var list<int>|null */
    private ?array $districtIds = null;

    private ?Carbon $periodFrom = null;

    private ?Carbon $periodTo = null;

    private ?ProgramDeliverablesFilter $filter = null;

    private ?FiscalYear $activeFiscalYear = null;

    public function __construct(
        private readonly ServiceTargetDeliverableSyncService $serviceTargetDeliverables,
        private readonly LegacyApplicationServiceCaseSupport $legacyServiceCases,
        private readonly MarketLinkagePartnerCatalogService $marketLinkagePartners,
    ) {}

    /** @var array<int, ?District> */
    private array $legacyDistrictCache = [];

    /**
     * @return array<string, mixed>
     */
    public function build(ProgramDeliverablesFilter $filter, ProgramDeliverablesScope $scope, string $serial): array
    {
        $leaf = ProgramDeliverablesMatrix::findLeafBySerial($serial);
        if ($leaf === null) {
            abort(404, 'Indicator not found.');
        }

        $source = $leaf['source'] ?? ['type' => 'none'];
        $sourceType = (string) ($source['type'] ?? 'none');
        if ($sourceType === 'none') {
            abort(422, 'This indicator does not support drill-down.');
        }

        $fiscalYears = FiscalYear::forUiDropdown();
        [$resolvedFyId] = FiscalYear::resolveIdForUi($filter->fiscalYearId);
        $this->activeFiscalYear = $fiscalYears->firstWhere('id', $resolvedFyId);
        $this->filter = $filter;
        $this->legacyDistrictCache = [];
        $this->districtIds = $scope->effectiveDistrictIds($filter->districtId);
        [$this->periodFrom, $this->periodTo] = $filter->resolvePeriod($this->activeFiscalYear);

        $breakdown = match ($sourceType) {
            'deliverable' => $this->deliverableBreakdown($source),
            'service', 'services' => $this->serviceCaseBreakdown($source),
            'cfa_count' => $this->cfaBreakdown(),
            'onboarding_count' => $this->onboardingBreakdown(),
            'potential_lakhpati_onboarding_count' => $this->potentialLakhpatiOnboardingBreakdown(),
            'field_work_workshops', 'field_visit_sessions' => $this->fieldWorkBreakdown(false),
            'field_work_participants', 'field_visit_participants' => $this->fieldWorkBreakdown(true),
            'district_workshop_sessions' => $this->simpleTableBreakdown('district_workshop_sessions', 'event_date', 'created_at'),
            'edp_sessions' => $this->edpBreakdown(),
            'bst_sessions' => $this->bstSessionsBreakdown(),
            'bst_participants' => $this->bstParticipantsBreakdown(),
            'technical_training_sessions' => $this->technicalTrainingBreakdown(),
            'technical_training_potential_lakhpati_sessions' => $this->technicalTrainingPotentialLakhpatiSessionsBreakdown(),
            'market_linkage_unique_partners' => $this->marketLinkagePartnersBreakdown(),
            'market_linkage_incubatees' => $this->marketLinkageIncubateesBreakdown(),
            'community_org_outreach_count' => $this->communityOrgOutreachBreakdown(),
            'capacity_building_stakeholder_sessions' => $this->capacityBuildingStakeholderSessionsBreakdown(),
            'pitch_deck_preparations' => $this->pitchDeckPreparationsBreakdown(),
            'pitch_deck_combined' => $this->pitchDeckCombinedBreakdown(),
            default => ['total' => 0, 'by_district' => [], 'by_hub' => [], 'by_month' => [], 'by_service' => [], 'records' => []],
        };

        $total = (int) ($breakdown['total'] ?? 0);
        $byDistrict = $breakdown['by_district'] ?? [];
        $byMonth = $breakdown['by_month'] ?? [];

        $sourceTypeLabel = $this->sourceTypeLabel($sourceType, $source);

        return [
            'serial' => $serial,
            'name' => (string) ($leaf['name'] ?? ''),
            'indicator_type' => ProgramDeliverableReportingTier::indicatorTypeLabel($leaf),
            'level' => (string) ($leaf['level'] ?? ''),
            'source_type' => $sourceType,
            'source_type_label' => $sourceTypeLabel,
            'total' => $total,
            'by_district' => $byDistrict,
            'by_hub' => $breakdown['by_hub'] ?? [],
            'by_month' => $byMonth,
            'by_service' => $breakdown['by_service'] ?? [],
            'applied_amount_total' => $breakdown['applied_amount_total'] ?? null,
            'sanctioned_amount_total' => $breakdown['sanctioned_amount_total'] ?? null,
            'records' => $breakdown['records'] ?? [],
            'insights' => $this->buildInsights($total, $byDistrict, $byMonth, $breakdown['by_service'] ?? []),
        ];
    }

    /**
     * @param  array<string, mixed>  $source
     * @return array<string, mixed>
     */
    private function deliverableBreakdown(array $source): array
    {
        if (strtolower(trim((string) ($source['code'] ?? ''))) === 'social_media') {
            return $this->socialMediaPostsBreakdown();
        }

        return $this->serviceCaseBreakdown($source);
    }

    /**
     * @return array<string, mixed>
     */
    private function socialMediaPostsBreakdown(): array
    {
        if (! Schema::hasTable('social_media_posts')) {
            return $this->emptyBreakdown();
        }

        $query = DB::table('social_media_posts as smp');
        $this->applySocialMediaPostsAchievementScope($query, 'smp.posted_on');
        $monthExpr = $this->monthKeySql('smp.posted_on');

        $rows = (clone $query)
            ->selectRaw("
                0 as district_id,
                'Statewide' as district_name,
                '—' as hub_name,
                {$monthExpr} as month_key,
                COUNT(*) as total
            ")
            ->groupBy(DB::raw($monthExpr))
            ->get();

        $records = (clone $query)
            ->select(['smp.id', 'smp.posted_on', 'smp.post_url', 'smp.submitted_by_name'])
            ->orderByDesc('smp.posted_on')
            ->limit(100)
            ->get()
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'reference' => 'Post #'.$row->id,
                'applicant' => (string) ($row->submitted_by_name ?: '—'),
                'district' => 'State',
                'hub' => '—',
                'service' => (string) ($row->post_url ?: '—'),
                'spoc' => '—',
                'status' => 'Logged',
                'date' => $row->posted_on ? Carbon::parse($row->posted_on)->format('d M Y') : '—',
            ])
            ->all();

        return $this->aggregateGroupedRows($rows, includeService: false, records: $records);
    }

    /**
     * @return array<string, mixed>
     */
    private function communityOrgOutreachBreakdown(): array
    {
        if (! Schema::hasTable('community_organization_outreach_visits')) {
            return $this->emptyBreakdown();
        }

        $query = DB::table('community_organization_outreach_visits as v')
            ->join('districts as d', 'd.id', '=', 'v.district_id')
            ->leftJoin('hubs as h', 'h.id', '=', 'v.hub_id');
        $this->applyDistrictScope($query, 'v.district_id');
        $this->applyPeriodFilter($query, 'v.visit_date');
        $monthExpr = $this->monthKeySql('v.visit_date');

        $rows = (clone $query)
            ->selectRaw("
                d.id as district_id,
                d.name as district_name,
                h.name as hub_name,
                {$monthExpr} as month_key,
                COUNT(*) as total
            ")
            ->groupBy('d.id', 'd.name', 'h.name', DB::raw($monthExpr))
            ->get();

        $records = (clone $query)
            ->select([
                'v.id',
                'v.visit_date',
                'v.organization_name',
                'v.district_name',
                'v.hub_name',
                'v.submitted_by_name',
            ])
            ->orderByDesc('v.visit_date')
            ->limit(100)
            ->get()
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'reference' => 'Visit #'.$row->id,
                'applicant' => (string) ($row->organization_name ?: '—'),
                'district' => (string) ($row->district_name ?: '—'),
                'hub' => (string) ($row->hub_name ?: '—'),
                'service' => 'Community outreach',
                'spoc' => (string) ($row->submitted_by_name ?: '—'),
                'status' => 'Logged',
                'date' => $row->visit_date ? Carbon::parse($row->visit_date)->format('d M Y') : '—',
            ])
            ->all();

        return $this->aggregateGroupedRows($rows, includeService: false, records: $records);
    }

    /**
     * @param  array<string, mixed>  $source
     * @return array<string, mixed>
     */
    private function serviceCaseBreakdown(array $source): array
    {
        if (! Schema::hasTable('service_cases') || ! Schema::hasTable('services')) {
            return $this->emptyBreakdown();
        }

        $serviceIds = $this->resolveServiceIdsForSource($source);
        if ($serviceIds === []) {
            return $this->emptyBreakdown();
        }

        $dateExpr = $this->serviceCaseAchievementDateExpression();
        $monthExpr = $this->monthKeySql($dateExpr);
        $statuses = [ServiceCase::STATUS_APPROVED, ServiceCase::STATUS_COMPLETED];

        $cfaQuery = DB::table('service_cases as sc')
            ->join('services as s', 's.id', '=', 'sc.service_id')
            ->join('cfa_submissions as cs', 'cs.id', '=', 'sc.cfa_submission_id')
            ->join('districts as d', 'd.id', '=', 'cs.district_id')
            ->leftJoin('hubs as h', 'h.id', '=', 'd.hub_id')
            ->leftJoin('users as spoc', 'spoc.id', '=', 'sc.spoc_user_id')
            ->whereIn('sc.status', $statuses)
            ->whereIn('sc.service_id', $serviceIds)
            ->whereNotNull('sc.cfa_submission_id');

        $this->applyDistrictScope($cfaQuery, 'cs.district_id');
        $this->applyServiceCaseDateScope($cfaQuery, $dateExpr);

        $cfaRows = (clone $cfaQuery)
            ->selectRaw("
                d.id as district_id,
                d.name as district_name,
                h.name as hub_name,
                s.id as service_id,
                s.code as service_code,
                s.name as service_name,
                {$monthExpr} as month_key,
                COUNT(*) as total
            ")
            ->groupBy('d.id', 'd.name', 'h.name', 's.id', 's.code', 's.name', DB::raw($monthExpr))
            ->get();

        $cfaAmountTotals = $this->amountTotalsByServiceIdFromServiceCaseQuery($cfaQuery);

        $cfaRecords = (clone $cfaQuery)
            ->select([
                'sc.id',
                'sc.reference_number',
                'sc.status',
                'cs.applicant_name',
                'd.name as district_name',
                'h.name as hub_name',
                's.name as service_name',
                'spoc.name as spoc_name',
            ])
            ->selectRaw("{$dateExpr} as achievement_date")
            ->orderByDesc(DB::raw($dateExpr))
            ->limit(100)
            ->get()
            ->map(fn ($row) => $this->mapServiceCaseBreakdownRecord($row))
            ->all();

        [$legacyRows, $legacyRecords, $legacyAmountTotals] = $this->legacyServiceCaseBreakdownContributions(
            $serviceIds,
            $dateExpr,
            $monthExpr,
            $statuses,
        );

        $rows = $this->mergeServiceCaseAggregateRows($cfaRows, $legacyRows);
        $records = $this->mergeServiceCaseBreakdownRecords($cfaRecords, $legacyRecords, 100);
        $amountTotalsByServiceId = $this->mergeAmountTotalsByServiceId($cfaAmountTotals, $legacyAmountTotals);
        $breakdown = $this->aggregateGroupedRows($rows, includeService: true, records: $records);
        $breakdown['by_service'] = $this->bifurcationRowsForSource(
            $source,
            $rows,
            (int) ($breakdown['total'] ?? 0),
            $amountTotalsByServiceId,
            $breakdown['by_service'] ?? [],
        );
        $breakdown['applied_amount_total'] = $this->sumAmountMetric($amountTotalsByServiceId, 'applied');
        $breakdown['sanctioned_amount_total'] = $this->sumAmountMetric($amountTotalsByServiceId, 'sanctioned');

        return $breakdown;
    }

    /**
     * @param  list<int>  $serviceIds
     * @param  list<string>  $statuses
     * @return array{0: Collection<int, object>, 1: list<array<string, mixed>>, 2: array<int, array{applied: float, sanctioned: float}>}
     */
    private function legacyServiceCaseBreakdownContributions(
        array $serviceIds,
        string $dateExpr,
        string $monthExpr,
        array $statuses,
    ): array {
        if (! ServiceCase::supportsLegacyApplicationLink()) {
            return [collect(), [], []];
        }

        if ($this->districtIds === []) {
            return [collect(), [], []];
        }

        $legacyIds = $this->districtIds === null
            ? null
            : $this->legacyServiceCases->legacyApplicationIdsForLaravelDistrictIds($this->districtIds);

        if ($legacyIds !== null && $legacyIds === []) {
            return [collect(), [], []];
        }

        $query = DB::table('service_cases as sc')
            ->join('services as s', 's.id', '=', 'sc.service_id')
            ->leftJoin('users as spoc', 'spoc.id', '=', 'sc.spoc_user_id')
            ->whereIn('sc.status', $statuses)
            ->whereIn('sc.service_id', $serviceIds)
            ->whereNull('sc.cfa_submission_id')
            ->whereNotNull('sc.legacy_application_id');

        if ($legacyIds !== null) {
            $query->whereIn('sc.legacy_application_id', $legacyIds);
        }

        $this->applyServiceCaseDateScope($query, $dateExpr);
        $amountTotalsByServiceId = $this->amountTotalsByServiceIdFromServiceCaseQuery($query);

        $cases = (clone $query)
            ->select([
                'sc.id',
                'sc.legacy_application_id',
                'sc.service_id',
                'sc.reference_number',
                'sc.status',
                's.code as service_code',
                's.name as service_name',
                'spoc.name as spoc_name',
            ])
            ->selectRaw("{$dateExpr} as achievement_date")
            ->selectRaw("{$monthExpr} as month_key")
            ->get();

        if ($cases->isEmpty()) {
            return [collect(), [], $amountTotalsByServiceId];
        }

        $legacyApplicationIds = $cases
            ->pluck('legacy_application_id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();

        $snapshots = $this->legacyServiceCases->applicantSnapshotsByLegacyApplicationIds($legacyApplicationIds);

        $aggregateBuckets = [];
        $records = [];

        foreach ($cases as $case) {
            $legacyApplicationId = (int) ($case->legacy_application_id ?? 0);
            $district = $this->districtForLegacyApplication($legacyApplicationId);
            $districtName = (string) ($district?->name ?? 'Unknown');
            $hubName = (string) ($district?->hub?->name ?? 'Unassigned');
            $serviceName = (string) ($case->service_name ?? 'Unknown');
            $monthKey = (string) ($case->month_key ?? '');

            $bucketKey = implode('|', [$districtName, $hubName, (string) ((int) ($case->service_id ?? 0)), $serviceName, $monthKey]);
            if (! isset($aggregateBuckets[$bucketKey])) {
                $aggregateBuckets[$bucketKey] = (object) [
                    'district_id' => (int) ($district?->id ?? 0),
                    'district_name' => $districtName,
                    'hub_name' => $hubName,
                    'service_id' => (int) ($case->service_id ?? 0),
                    'service_code' => (string) ($case->service_code ?? ''),
                    'service_name' => $serviceName,
                    'month_key' => $monthKey,
                    'total' => 0,
                ];
            }
            $aggregateBuckets[$bucketKey]->total++;

            $snapshot = $snapshots[$legacyApplicationId] ?? null;
            $records[] = $this->mapServiceCaseBreakdownRecord($case, [
                'applicant_name' => (string) ($snapshot['name'] ?? ''),
                'district_name' => $districtName,
                'hub_name' => $hubName,
            ]);
        }

        return [collect(array_values($aggregateBuckets)), $records, $amountTotalsByServiceId];
    }

    private function districtForLegacyApplication(int $legacyApplicationId): ?District
    {
        if ($legacyApplicationId < 1) {
            return null;
        }

        if (array_key_exists($legacyApplicationId, $this->legacyDistrictCache)) {
            return $this->legacyDistrictCache[$legacyApplicationId];
        }

        $preview = $this->legacyServiceCases->incubateePreview($legacyApplicationId);
        $districtId = $preview !== null
            ? $this->legacyServiceCases->laravelDistrictIdForLegacyDistrictName((string) ($preview['district'] ?? ''))
            : null;

        $district = $districtId !== null
            ? District::query()->with('hub')->find($districtId)
            : null;

        return $this->legacyDistrictCache[$legacyApplicationId] = $district;
    }

    /**
     * @param  array<string, string>  $overrides
     * @return array<string, mixed>
     */
    private function mapServiceCaseBreakdownRecord(object $row, array $overrides = []): array
    {
        $applicant = array_key_exists('applicant_name', $overrides)
            ? $overrides['applicant_name']
            : (string) ($row->applicant_name ?? '');
        $districtName = array_key_exists('district_name', $overrides)
            ? $overrides['district_name']
            : (string) ($row->district_name ?? '');
        $hubName = array_key_exists('hub_name', $overrides)
            ? $overrides['hub_name']
            : (string) ($row->hub_name ?? '');

        $achievementDate = $row->achievement_date ?? null;

        return [
            'id' => (int) ($row->id ?? 0),
            'reference' => (string) ($row->reference_number ?: '—'),
            'applicant' => $applicant !== '' ? $applicant : '—',
            'district' => $districtName !== '' ? $districtName : '—',
            'hub' => $hubName !== '' ? $hubName : '—',
            'service' => (string) ($row->service_name ?? '—'),
            'spoc' => (string) ($row->spoc_name ?? '—'),
            'status' => ucfirst(str_replace('_', ' ', (string) ($row->status ?? ''))),
            'date' => $achievementDate ? Carbon::parse($achievementDate)->format('d M Y') : '—',
            '_sort_date' => $achievementDate ? (string) $achievementDate : '',
        ];
    }

    /**
     * @param  Collection<int, object>  $cfaRows
     * @param  Collection<int, object>  $legacyRows
     * @return Collection<int, object>
     */
    private function mergeServiceCaseAggregateRows(Collection $cfaRows, Collection $legacyRows): Collection
    {
        $merged = [];

        foreach ($cfaRows->concat($legacyRows) as $row) {
            $key = implode('|', [
                (string) ($row->district_name ?? 'Unknown'),
                (string) ($row->hub_name ?? 'Unassigned'),
                (string) ((int) ($row->service_id ?? 0)),
                (string) ($row->service_name ?? 'Unknown'),
                (string) ($row->month_key ?? ''),
            ]);

            if (! isset($merged[$key])) {
                $merged[$key] = (object) [
                    'district_id' => (int) ($row->district_id ?? 0),
                    'district_name' => (string) ($row->district_name ?? 'Unknown'),
                    'hub_name' => (string) ($row->hub_name ?? 'Unassigned'),
                    'service_id' => (int) ($row->service_id ?? 0),
                    'service_code' => (string) ($row->service_code ?? ''),
                    'service_name' => (string) ($row->service_name ?? 'Unknown'),
                    'month_key' => (string) ($row->month_key ?? ''),
                    'total' => 0,
                ];
            }

            $merged[$key]->total += (int) ($row->total ?? 0);
        }

        return collect(array_values($merged));
    }

    /**
     * @param  list<array<string, mixed>>  $cfaRecords
     * @param  list<array<string, mixed>>  $legacyRecords
     * @return list<array<string, mixed>>
     */
    private function mergeServiceCaseBreakdownRecords(array $cfaRecords, array $legacyRecords, int $limit): array
    {
        $combined = array_merge($cfaRecords, $legacyRecords);
        usort($combined, function (array $a, array $b): int {
            return strcmp((string) ($b['_sort_date'] ?? ''), (string) ($a['_sort_date'] ?? ''));
        });

        return array_map(function (array $record): array {
            unset($record['_sort_date']);

            return $record;
        }, array_slice($combined, 0, $limit));
    }

    /**
     * @return array<string, mixed>
     */
    private function cfaBreakdown(): array
    {
        if (! Schema::hasTable('cfa_submissions')) {
            return $this->emptyBreakdown();
        }

        $query = DB::table('cfa_submissions as cs')
            ->join('districts as d', 'd.id', '=', 'cs.district_id')
            ->leftJoin('hubs as h', 'h.id', '=', 'd.hub_id');

        $this->applyDistrictScope($query, 'cs.district_id');
        $this->applyCfaAchievementScope($query);
        $monthExpr = $this->monthKeySql('cs.created_at');

        $rows = (clone $query)
            ->selectRaw("
                d.id as district_id,
                d.name as district_name,
                h.name as hub_name,
                {$monthExpr} as month_key,
                COUNT(*) as total
            ")
            ->groupBy('d.id', 'd.name', 'h.name', DB::raw($monthExpr))
            ->get();

        $records = (clone $query)
            ->select(['cs.id', 'cs.application_no', 'cs.applicant_name', 'cs.phone', 'd.name as district_name', 'h.name as hub_name', 'cs.created_at'])
            ->orderByDesc('cs.created_at')
            ->limit(100)
            ->get()
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'reference' => (string) ($row->application_no ?: '—'),
                'applicant' => (string) ($row->applicant_name ?: '—'),
                'district' => (string) ($row->district_name ?: '—'),
                'hub' => (string) ($row->hub_name ?: '—'),
                'service' => 'CFA',
                'spoc' => (string) ($row->phone ?: '—'),
                'status' => 'Submitted',
                'date' => $row->created_at ? Carbon::parse($row->created_at)->format('d M Y') : '—',
            ])
            ->all();

        return $this->aggregateGroupedRows($rows, includeService: false, records: $records);
    }

    /**
     * @return array<string, mixed>
     */
    private function onboardingBreakdown(): array
    {
        if (! Schema::hasTable('onboarding_batch_cfa') || ! Schema::hasTable('onboarding_batches')) {
            return $this->emptyBreakdown();
        }

        $query = DB::table('onboarding_batch_cfa as obc')
            ->join('onboarding_batches as ob', 'ob.id', '=', 'obc.onboarding_batch_id')
            ->join('cfa_submissions as cs', 'cs.id', '=', 'obc.cfa_submission_id')
            ->join('districts as d', 'd.id', '=', 'cs.district_id')
            ->leftJoin('hubs as h', 'h.id', '=', 'd.hub_id')
            ->where('ob.status', 'locked')
            ->whereNotNull('ob.locked_at');

        $this->applyDistrictScope($query, 'cs.district_id');
        $this->applyOnboardingAchievementScope($query);
        $monthExpr = $this->monthKeySql('ob.onboarding_date');

        $rows = (clone $query)
            ->selectRaw("
                d.id as district_id,
                d.name as district_name,
                h.name as hub_name,
                {$monthExpr} as month_key,
                COUNT(*) as total
            ")
            ->groupBy('d.id', 'd.name', 'h.name', DB::raw($monthExpr))
            ->get();

        $records = (clone $query)
            ->select([
                'cs.id',
                'cs.application_no',
                'cs.applicant_name',
                'd.name as district_name',
                'h.name as hub_name',
                'ob.onboarding_date',
                'ob.name as batch_name',
            ])
            ->orderByDesc('ob.onboarding_date')
            ->limit(100)
            ->get()
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'reference' => (string) ($row->application_no ?: $row->batch_name ?: '—'),
                'applicant' => (string) ($row->applicant_name ?: '—'),
                'district' => (string) ($row->district_name ?: '—'),
                'hub' => (string) ($row->hub_name ?: '—'),
                'service' => 'Onboarding',
                'spoc' => '—',
                'status' => 'Locked',
                'date' => $row->onboarding_date ? Carbon::parse($row->onboarding_date)->format('d M Y') : '—',
            ])
            ->all();

        return $this->aggregateGroupedRows($rows, includeService: false, records: $records);
    }

    /**
     * MIS 2.1.1 — onboarded SHG/CBO, or Individual with SHG/CBO member Yes (Phase 3 CFA only).
     *
     * @return array<string, mixed>
     */
    private function potentialLakhpatiOnboardingBreakdown(): array
    {
        if (! Schema::hasTable('onboarding_batch_cfa') || ! Schema::hasTable('onboarding_batches')) {
            return $this->emptyBreakdown();
        }

        $query = DB::table('onboarding_batch_cfa as obc')
            ->join('onboarding_batches as ob', 'ob.id', '=', 'obc.onboarding_batch_id')
            ->join('cfa_submissions as cs', 'cs.id', '=', 'obc.cfa_submission_id')
            ->join('districts as d', 'd.id', '=', 'cs.district_id')
            ->leftJoin('hubs as h', 'h.id', '=', 'd.hub_id')
            ->where('ob.status', 'locked')
            ->whereNotNull('ob.locked_at');

        $this->applyDistrictScope($query, 'cs.district_id');
        $this->applyOnboardingAchievementScope($query);
        $query->whereRaw(PotentialLakhpatiOnboardingSql::qualifiesSql());

        $monthExpr = $this->monthKeySql('ob.onboarding_date');

        $rows = (clone $query)
            ->selectRaw("
                d.id as district_id,
                d.name as district_name,
                h.name as hub_name,
                {$monthExpr} as month_key,
                COUNT(*) as total
            ")
            ->groupBy('d.id', 'd.name', 'h.name', DB::raw($monthExpr))
            ->get();

        $records = (clone $query)
            ->select([
                'cs.id',
                'cs.application_no',
                'cs.applicant_name',
                'd.name as district_name',
                'h.name as hub_name',
                'ob.onboarding_date',
                'ob.name as batch_name',
            ])
            ->orderByDesc('ob.onboarding_date')
            ->limit(100)
            ->get()
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'reference' => (string) ($row->application_no ?: $row->batch_name ?: '—'),
                'applicant' => (string) ($row->applicant_name ?: '—'),
                'district' => (string) ($row->district_name ?: '—'),
                'hub' => (string) ($row->hub_name ?: '—'),
                'service' => 'Potential Lakhpati / SHG / CBO onboarding',
                'spoc' => '—',
                'status' => 'Locked',
                'date' => $row->onboarding_date ? Carbon::parse($row->onboarding_date)->format('d M Y') : '—',
            ])
            ->all();

        return $this->aggregateGroupedRows($rows, includeService: false, records: $records);
    }

    /**
     * MIS 1.3 / 1.3.1 — field_coordinator_attendance_reports + block_workshops combined.
     * When $participants = true  → sum female counts + individual female participant records.
     * When $participants = false → count workshop submissions + workshop-level records.
     *
     * @return array<string, mixed>
     */
    private function fieldWorkBreakdown(bool $participants): array
    {
        $allGroupedRows = collect();
        $allRecords = [];

        // ── field_coordinator_attendance_reports ─────────────────────────────
        if (Schema::hasTable('field_coordinator_attendance_reports')) {
            $fcQuery = FieldCoordinatorAttendanceReport::query()
                ->leftJoin('districts as d', 'd.id', '=', 'field_coordinator_attendance_reports.district_id')
                ->leftJoin('hubs as h', 'h.id', '=', 'd.hub_id');

            $this->applyFieldWorkAchievementScope($fcQuery);
            $monthExpr = $this->monthKeySql('field_coordinator_attendance_reports.visit_date');

            $hasFemaleCol = Schema::hasColumn('field_coordinator_attendance_reports', 'participants_female_count');
            $countExpr = ($participants && $hasFemaleCol)
                ? 'COALESCE(field_coordinator_attendance_reports.participants_female_count, 0)'
                : '1';

            $fcRows = (clone $fcQuery)
                ->selectRaw("
                    d.id as district_id,
                    d.name as district_name,
                    h.name as hub_name,
                    {$monthExpr} as month_key,
                    SUM({$countExpr}) as total
                ")
                ->groupBy('d.id', 'd.name', 'h.name', DB::raw($monthExpr))
                ->get();

            $allGroupedRows = $allGroupedRows->concat($fcRows);

            if ($participants && $hasFemaleCol) {
                $fcWithJson = (clone $fcQuery)
                    ->select([
                        'field_coordinator_attendance_reports.id',
                        'field_coordinator_attendance_reports.visit_date',
                        'field_coordinator_attendance_reports.participants_json',
                        'field_coordinator_attendance_reports.participants_male_count',
                        'field_coordinator_attendance_reports.participants_female_count',
                        'd.name as district_name',
                        'h.name as hub_name',
                    ])
                    ->where('field_coordinator_attendance_reports.participants_female_count', '>', 0)
                    ->orderByDesc('field_coordinator_attendance_reports.visit_date')
                    ->get();

                foreach ($fcWithJson as $row) {
                    $this->extractFemaleParticipantRecords($row, $allRecords, 'field_visit');
                }
            } elseif (! $participants) {
                $fcRecords = (clone $fcQuery)
                    ->select([
                        'field_coordinator_attendance_reports.id',
                        'field_coordinator_attendance_reports.visit_date',
                        'field_coordinator_attendance_reports.area',
                        'field_coordinator_attendance_reports.block',
                        'd.name as district_name',
                        'h.name as hub_name',
                    ])
                    ->orderByDesc('field_coordinator_attendance_reports.visit_date')
                    ->get()
                    ->map(fn ($row) => [
                        'id' => (int) $row->id,
                        'reference' => 'Visit #'.$row->id,
                        'applicant' => (string) (trim(($row->area ?? '').($row->block ? ', '.$row->block : '')) ?: 'Field visit'),
                        'district' => (string) ($row->district_name ?: '—'),
                        'hub' => (string) ($row->hub_name ?: '—'),
                        'service' => 'Field work visit',
                        'spoc' => '—',
                        'status' => 'Recorded',
                        'date' => $row->visit_date ? Carbon::parse($row->visit_date)->format('d M Y') : '—',
                    ])
                    ->all();

                $allRecords = array_merge($allRecords, $fcRecords);
            }
        }

        // ── block_workshops ──────────────────────────────────────────────────
        if (Schema::hasTable('block_workshops')) {
            $bwQuery = DB::table('block_workshops as bw')
                ->leftJoin('districts as d', 'd.id', '=', 'bw.district_id')
                ->leftJoin('hubs as h', 'h.id', '=', 'd.hub_id');

            $this->applyBlockWorkshopBreakdownScope($bwQuery);
            $monthExpr = $this->monthKeySql('bw.visit_date');

            $hasBwFemaleCol = Schema::hasColumn('block_workshops', 'participants_female_count');
            $countExpr = ($participants && $hasBwFemaleCol)
                ? 'COALESCE(bw.participants_female_count, 0)'
                : '1';

            $bwRows = (clone $bwQuery)
                ->selectRaw("
                    d.id as district_id,
                    d.name as district_name,
                    h.name as hub_name,
                    {$monthExpr} as month_key,
                    SUM({$countExpr}) as total
                ")
                ->groupBy('d.id', 'd.name', 'h.name', DB::raw($monthExpr))
                ->get();

            $allGroupedRows = $allGroupedRows->concat($bwRows);

            if ($participants && $hasBwFemaleCol) {
                $bwWithJson = (clone $bwQuery)
                    ->select([
                        'bw.id',
                        'bw.visit_date',
                        'bw.participants_json',
                        'bw.participants_male_count',
                        'bw.participants_female_count',
                        'd.name as district_name',
                        'h.name as hub_name',
                    ])
                    ->where('bw.participants_female_count', '>', 0)
                    ->orderByDesc('bw.visit_date')
                    ->get();

                foreach ($bwWithJson as $row) {
                    $this->extractFemaleParticipantRecords($row, $allRecords, 'block_workshop');
                }
            } elseif (! $participants) {
                $bwRecords = (clone $bwQuery)
                    ->select([
                        'bw.id',
                        'bw.visit_date',
                        'bw.area',
                        'bw.block',
                        'd.name as district_name',
                        'h.name as hub_name',
                    ])
                    ->orderByDesc('bw.visit_date')
                    ->get()
                    ->map(fn ($row) => [
                        'id' => (int) $row->id,
                        'reference' => 'Workshop #'.$row->id,
                        'applicant' => (string) (trim(($row->area ?? '').($row->block ? ', '.$row->block : '')) ?: 'Block workshop'),
                        'district' => (string) ($row->district_name ?: '—'),
                        'hub' => (string) ($row->hub_name ?: '—'),
                        'service' => 'Block workshop',
                        'spoc' => '—',
                        'status' => 'Recorded',
                        'date' => $row->visit_date ? Carbon::parse($row->visit_date)->format('d M Y') : '—',
                    ])
                    ->all();

                $allRecords = array_merge($allRecords, $bwRecords);
            }
        }

        if ($allGroupedRows->isEmpty()) {
            return $this->emptyBreakdown();
        }

        // Sort records newest-first (no hard cap — exports receive all, drawer paginates client-side)
        usort($allRecords, fn ($a, $b) => strcmp((string) ($b['date'] ?? ''), (string) ($a['date'] ?? '')));

        return $this->aggregateGroupedRows($allGroupedRows, includeService: false, records: $allRecords);
    }

    /**
     * One breakdown row per female participant (`participants_female_count`).
     * Details come from participants_json when available; remaining slots are placeholders.
     *
     * @param  object  $row  id, visit_date, participants_json, participants_male_count, participants_female_count, district_name, hub_name
     * @param  list<array<string, mixed>>  $records
     */
    private function extractFemaleParticipantRecords(object $row, array &$records, string $sourceKind): void
    {
        $femaleTarget = (int) ($row->participants_female_count ?? 0);
        if ($femaleTarget <= 0) {
            return;
        }

        $json = $row->participants_json ?? null;
        $participants = is_string($json) ? json_decode($json, true) : (is_array($json) ? $json : null);
        $participants = is_array($participants) ? array_values($participants) : [];

        $maleCount = (int) ($row->participants_male_count ?? 0);

        $femaleDetails = [];
        foreach ($participants as $i => $p) {
            if (! is_array($p)) {
                continue;
            }
            if ($this->participantRowIsFemale($p, $i, $maleCount, $femaleTarget)) {
                $femaleDetails[] = $p;
            }
        }
        $femaleDetails = array_slice($femaleDetails, 0, $femaleTarget);

        $refLabel = $sourceKind === 'block_workshop' ? 'Workshop' : 'Visit';
        $visitDate = $row->visit_date ? Carbon::parse($row->visit_date)->format('d M Y') : '—';
        $districtName = (string) ($row->district_name ?: '—');
        $hubName = (string) ($row->hub_name ?: '—');

        for ($i = 0; $i < $femaleTarget; $i++) {
            $p = $femaleDetails[$i] ?? null;
            $name = is_array($p) ? trim((string) ($p['name'] ?? '')) : '';
            $gpAndMobile = is_array($p)
                ? trim(implode(', ', array_filter([
                    (string) ($p['gram_panchayat_name'] ?? ''),
                    (string) ($p['mobile'] ?? ''),
                ])))
                : '';
            $mobile = is_array($p) ? trim((string) ($p['mobile'] ?? '')) : '';
            $pDistrict = is_array($p) ? trim((string) ($p['district_name'] ?? '')) : '';

            $records[] = [
                'id' => (int) $row->id,
                'reference' => $refLabel.' #'.$row->id,
                'applicant' => $name !== '' ? $name : '—',
                'district' => $pDistrict !== '' ? $pDistrict : $districtName,
                'hub' => $hubName,
                'service' => $gpAndMobile !== '' ? $gpAndMobile : '—',
                'spoc' => $mobile !== '' ? $mobile : '—',
                'status' => 'Female',
                'gender' => 'F',
                'date' => $visitDate,
            ];
        }
    }

    /**
     * Match staff participant registry: explicit gender, else default slot by male/female counts.
     *
     * @param  array<string, mixed>  $row
     */
    private function participantRowIsFemale(array $row, int $index, int $maleCount, int $femaleCount): bool
    {
        $gender = strtoupper(trim((string) ($row['gender'] ?? '')));
        if ($gender === 'F') {
            return true;
        }
        if ($gender === 'M') {
            return false;
        }

        return $index >= $maleCount && $index < $maleCount + $femaleCount;
    }

    /**
     * @param  Builder  $query
     */
    private function applyBlockWorkshopBreakdownScope($query): void
    {
        $this->applyDistrictScope($query, 'bw.district_id');

        $query->where(function ($q): void {
            $q->where('bw.status', 'submitted')->orWhereNull('bw.status');
        });

        $floor = $this->phase3FloorDate();

        if ($this->filter?->hasExplicitDateFilter() && $this->periodFrom && $this->periodTo) {
            $from = $this->periodFrom->copy();
            if ($from->lt($floor)) {
                $from = $floor->copy();
            }
            $query->whereBetween('bw.visit_date', [$from->toDateString(), $this->periodTo->toDateString()]);

            return;
        }

        $query->where('bw.visit_date', '>=', $floor->toDateString());

        if ($this->periodTo) {
            $query->where('bw.visit_date', '<=', $this->periodTo->toDateString());
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function edpBreakdown(): array
    {
        if (! Schema::hasTable('eap_edp_sessions')) {
            return $this->emptyBreakdown();
        }

        $dateCol = Schema::hasColumn('eap_edp_sessions', 'session_date') ? 'session_date' : 'event_date';
        if (! Schema::hasColumn('eap_edp_sessions', $dateCol)) {
            $dateCol = 'created_at';
        }

        return $this->simpleTableBreakdown('eap_edp_sessions', $dateCol, 'created_at', label: 'EDP Session');
    }

    /**
     * @return array<string, mixed>
     */
    private function bstSessionsBreakdown(): array
    {
        if (! BstTrainingDeliverablesSupport::tableReady()) {
            return $this->emptyBreakdown();
        }

        $dateCol = BstTrainingDeliverablesSupport::eventDateColumn();
        $query = BstTrainingDeliverablesSupport::scopedPackagesQuery(
            $this->districtIds,
            $this->periodFrom,
            $this->periodTo,
        );

        $monthExpr = $this->monthKeySql("tp.{$dateCol}");
        $hasLegacyModule = Schema::hasColumn('training_packages', 'training_package');
        $moduleSelect = $hasLegacyModule ? 'tp.training_package' : 'NULL as training_package';
        $districtNameExpr = "COALESCE(d.name, tp.district_name, 'Unknown')";
        $hubNameExpr = "COALESCE(h.name, '—')";

        $rows = (clone $query)
            ->selectRaw("
                d.id as district_id,
                {$districtNameExpr} as district_name,
                {$hubNameExpr} as hub_name,
                {$monthExpr} as month_key,
                COUNT(*) as total
            ")
            ->groupBy('d.id', DB::raw($districtNameExpr), DB::raw($hubNameExpr), DB::raw($monthExpr))
            ->get();

        $records = (clone $query)
            ->select([
                'tp.id',
                'tp.event_date',
                'tp.training_batch_name',
                'tp.submitted_by_name',
                'tp.training_packages',
                DB::raw($moduleSelect),
                DB::raw("{$districtNameExpr} as district_name"),
                DB::raw("{$hubNameExpr} as hub_name"),
            ])
            ->when(
                Schema::hasColumn('training_packages', 'selected_incubatee_ids'),
                fn ($q) => $q->addSelect('tp.selected_incubatee_ids')
            )
            ->orderByDesc('tp.'.$dateCol)
            ->limit(100)
            ->get()
            ->map(function ($row) use ($dateCol): array {
                $participantCount = BstTrainingDeliverablesSupport::parseIncubateeIds($row->selected_incubatee_ids ?? null);

                return [
                    'id' => (int) $row->id,
                    'reference' => trim((string) ($row->training_batch_name ?? '')) !== ''
                        ? (string) $row->training_batch_name
                        : 'BST #'.$row->id,
                    'applicant' => (string) ($row->submitted_by_name ?: '—'),
                    'district' => (string) ($row->district_name ?: '—'),
                    'hub' => (string) ($row->hub_name ?: '—'),
                    'service' => BstTrainingDeliverablesSupport::modulesLabel(
                        $row->training_packages ?? null,
                        $row->training_package ?? null,
                    ).($participantCount !== [] ? ' · '.count($participantCount).' participants' : ''),
                    'spoc' => '—',
                    'status' => 'Recorded',
                    'date' => $row->{$dateCol} ? Carbon::parse($row->{$dateCol})->format('d M Y') : '—',
                ];
            })
            ->all();

        return $this->aggregateGroupedRows($rows, includeService: false, records: $records);
    }

    /**
     * @return array<string, mixed>
     */
    private function bstParticipantsBreakdown(): array
    {
        $data = BstTrainingDeliverablesSupport::participantBreakdown(
            $this->districtIds,
            $this->periodFrom,
            $this->periodTo,
        );

        return [
            'total' => (int) $data['total'],
            'by_district' => $data['by_district'],
            'by_hub' => $data['by_hub'],
            'by_month' => $data['by_month'],
            'by_service' => [],
            'records' => $data['records'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function technicalTrainingBreakdown(): array
    {
        if (Schema::hasTable('technical_training_sessions')) {
            $dateCol = Schema::hasColumn('technical_training_sessions', 'session_date') ? 'session_date' : 'created_at';

            return $this->simpleTableBreakdown('technical_training_sessions', $dateCol, 'created_at', label: 'Technical training', districtOptional: ! Schema::hasColumn('technical_training_sessions', 'district_id'));
        }

        if (Schema::hasTable('technical_trainings')) {
            $dateCol = Schema::hasColumn('technical_trainings', 'event_date') ? 'event_date' : 'created_at';

            return $this->simpleTableBreakdown('technical_trainings', $dateCol, 'created_at', label: 'Technical training');
        }

        return $this->emptyBreakdown();
    }

    /**
     * @return array<string, mixed>
     */
    private function technicalTrainingPotentialLakhpatiSessionsBreakdown(): array
    {
        $data = PotentialLakhpatiTechnicalTrainingDeliverablesSupport::sessionsBreakdown(
            $this->districtIds,
            $this->periodFrom,
            $this->periodTo,
        );

        return [
            'total' => (int) ($data['total'] ?? 0),
            'by_district' => $data['by_district'] ?? [],
            'by_hub' => $data['by_hub'] ?? [],
            'by_month' => $data['by_month'] ?? [],
            'by_service' => [],
            'records' => $data['records'] ?? [],
        ];
    }

    private function capacityBuildingStakeholderSessionsBreakdown(): array
    {
        $data = CapacityBuildingStakeholdersDeliverablesSupport::sessionsBreakdown(
            $this->periodFrom,
            $this->periodTo,
        );

        return [
            'total' => (int) ($data['total'] ?? 0),
            'by_district' => $data['by_district'] ?? [],
            'by_hub' => $data['by_hub'] ?? [],
            'by_month' => $data['by_month'] ?? [],
            'by_service' => [],
            'records' => $data['records'] ?? [],
        ];
    }

    private function pitchDeckPreparationsBreakdown(): array
    {
        $data = \App\Support\PitchDeckPreparationsDeliverablesSupport::preparationsBreakdown(
            $this->periodFrom,
            $this->periodTo,
            $this->districtIds,
        );

        return [
            'total' => (int) ($data['total'] ?? 0),
            'by_district' => $data['by_district'] ?? [],
            'by_hub' => $data['by_hub'] ?? [],
            'by_month' => $data['by_month'] ?? [],
            'by_service' => [],
            'records' => $data['records'] ?? [],
        ];
    }

    private function pitchDeckCombinedBreakdown(): array
    {
        $service = $this->serviceCaseBreakdown([
            'type' => 'deliverable',
            'code' => 'pitch_deck_prep',
        ]);

        $data = \App\Support\PitchDeckCombinedDeliverablesSupport::combinedBreakdownFromParts(
            $service,
            \App\Support\PitchDeckPreparationsDeliverablesSupport::preparationsBreakdown(
                $this->periodFrom,
                $this->periodTo,
                $this->districtIds,
            ),
        );

        return [
            'total' => (int) ($data['total'] ?? 0),
            'by_district' => $data['by_district'] ?? [],
            'by_hub' => $data['by_hub'] ?? [],
            'by_month' => $data['by_month'] ?? [],
            'by_service' => $data['by_service'] ?? [],
            'records' => $data['records'] ?? [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function simpleTableBreakdown(string $table, string $primaryDateCol, string $fallbackDateCol, string $label = 'Session', bool $districtOptional = false): array
    {
        if (! Schema::hasTable($table)) {
            return $this->emptyBreakdown();
        }

        $dateCol = Schema::hasColumn($table, $primaryDateCol) ? $primaryDateCol : $fallbackDateCol;
        $hasDistrict = ! $districtOptional && Schema::hasColumn($table, 'district_id');

        $query = DB::table($table.' as t');
        if ($hasDistrict) {
            $query->join('districts as d', 'd.id', '=', 't.district_id')
                ->leftJoin('hubs as h', 'h.id', '=', 'd.hub_id');
            $this->applyDistrictScope($query, 't.district_id');
        }
        $this->applyPeriodFilter($query, 't.'.$dateCol);
        $monthExpr = $this->monthKeySql("t.{$dateCol}");

        if ($hasDistrict) {
            $rows = (clone $query)
                ->selectRaw("
                    d.id as district_id,
                    d.name as district_name,
                    h.name as hub_name,
                    {$monthExpr} as month_key,
                    COUNT(*) as total
                ")
                ->groupBy('d.id', 'd.name', 'h.name', DB::raw($monthExpr))
                ->get();
        } else {
            $rows = (clone $query)
                ->selectRaw("
                    0 as district_id,
                    'Statewide' as district_name,
                    '—' as hub_name,
                    {$monthExpr} as month_key,
                    COUNT(*) as total
                ")
                ->groupBy(DB::raw($monthExpr))
                ->get();
        }

        $records = (clone $query)
            ->select(['t.id', 't.'.$dateCol.' as event_date'])
            ->when($hasDistrict, fn ($q) => $q->addSelect(['d.name as district_name', 'h.name as hub_name']))
            ->orderByDesc('t.'.$dateCol)
            ->limit(100)
            ->get()
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'reference' => $label.' #'.$row->id,
                'applicant' => '—',
                'district' => (string) ($row->district_name ?? 'Statewide'),
                'hub' => (string) ($row->hub_name ?? '—'),
                'service' => $label,
                'spoc' => '—',
                'status' => 'Recorded',
                'date' => $row->event_date ? Carbon::parse($row->event_date)->format('d M Y') : '—',
            ])
            ->all();

        return $this->aggregateGroupedRows($rows, includeService: false, records: $records);
    }

    /**
     * @param  Collection<int, object>  $rows
     * @param  list<array<string, mixed>>  $records
     * @return array<string, mixed>
     */
    private function aggregateGroupedRows($rows, bool $includeService, array $records = []): array
    {
        $byDistrict = [];
        $byHub = [];
        $byMonth = [];
        $byService = [];
        $total = 0;

        foreach ($rows as $row) {
            $count = (int) $row->total;
            $total += $count;

            $districtKey = (string) ($row->district_name ?? 'Unknown');
            $hubKey = (string) ($row->hub_name ?: 'Unassigned');
            $monthKey = (string) ($row->month_key ?? '');

            $byDistrict[$districtKey] = ($byDistrict[$districtKey] ?? 0) + $count;
            $byHub[$hubKey] = ($byHub[$hubKey] ?? 0) + $count;
            if ($monthKey !== '') {
                $byMonth[$monthKey] = ($byMonth[$monthKey] ?? 0) + $count;
            }

            if ($includeService) {
                $serviceKey = (string) ($row->service_name ?? 'Unknown');
                $byService[$serviceKey] = ($byService[$serviceKey] ?? 0) + $count;
            }
        }

        arsort($byDistrict);
        arsort($byHub);
        ksort($byMonth);
        arsort($byService);

        return [
            'total' => $total,
            'by_district' => $this->formatBreakdownList($byDistrict, $total, fn ($name) => [
                'district' => $name,
                'hub' => $this->hubForDistrictName($rows, $name),
            ]),
            'by_hub' => $this->formatBreakdownList($byHub, $total, fn ($name) => ['hub' => $name]),
            'by_month' => $this->formatMonthBreakdown($byMonth, $total),
            'by_service' => $this->formatBreakdownList($byService, $total, fn ($name) => ['service' => $name]),
            'applied_amount_total' => null,
            'sanctioned_amount_total' => null,
            'records' => $records,
        ];
    }

    /**
     * @param  array<string, mixed>  $source
     * @param  Collection<int, object>  $rows
     * @param  array<int, array{applied: float, sanctioned: float}>  $amountTotalsByServiceId
     * @param  list<array<string, mixed>>  $fallbackByService
     * @return list<array<string, mixed>>
     */
    private function bifurcationRowsForSource(array $source, Collection $rows, int $total, array $amountTotalsByServiceId, array $fallbackByService): array
    {
        $bifurcation = $source['bifurcation'] ?? [];
        if (! is_array($bifurcation) || $bifurcation === []) {
            return $this->enrichServiceRowsWithAmounts($fallbackByService, $rows, $amountTotalsByServiceId, $total);
        }

        $countByServiceId = [];
        foreach ($rows as $row) {
            $serviceId = (int) ($row->service_id ?? 0);
            if ($serviceId < 1) {
                continue;
            }
            $countByServiceId[$serviceId] = ($countByServiceId[$serviceId] ?? 0) + (int) ($row->total ?? 0);
        }

        $out = [];
        foreach ($bifurcation as $item) {
            if (! is_array($item)) {
                continue;
            }
            $label = trim((string) ($item['name'] ?? ''));
            $codes = array_values(array_filter(array_map('strval', (array) ($item['codes'] ?? []))));
            if ($label === '' || $codes === []) {
                continue;
            }

            $serviceIds = $this->resolveServiceIdsForSource([
                'type' => 'services',
                'codes' => $codes,
            ]);
            $count = 0;
            $applied = 0.0;
            $sanctioned = 0.0;
            foreach ($serviceIds as $serviceId) {
                $count += (int) ($countByServiceId[$serviceId] ?? 0);
                $applied += (float) (($amountTotalsByServiceId[$serviceId]['applied'] ?? 0.0));
                $sanctioned += (float) (($amountTotalsByServiceId[$serviceId]['sanctioned'] ?? 0.0));
            }

            $out[] = [
                'service' => $label,
                'count' => $count,
                'share_pct' => $total > 0 ? (int) round(($count / $total) * 100) : 0,
                'applied_amount' => round($applied, 2),
                'sanctioned_amount' => round($sanctioned, 2),
            ];
        }

        return $out !== [] ? $out : $this->enrichServiceRowsWithAmounts($fallbackByService, $rows, $amountTotalsByServiceId, $total);
    }

    /**
     * @param  Builder  $query
     * @return array<int, array{applied: float, sanctioned: float}>
     */
    private function amountTotalsByServiceIdFromServiceCaseQuery($query): array
    {
        $appliedExpr = $this->serviceCasePayloadAmountSql('applied_amount');
        $sanctionedExpr = $this->serviceCasePayloadAmountSql('sanctioned_amount');

        $rows = (clone $query)
            ->selectRaw('sc.service_id as service_id, COALESCE(SUM('.$appliedExpr.'), 0) as applied_total, COALESCE(SUM('.$sanctionedExpr.'), 0) as sanctioned_total')
            ->groupBy('sc.service_id')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $serviceId = (int) ($row->service_id ?? 0);
            if ($serviceId < 1) {
                continue;
            }
            $out[$serviceId] = [
                'applied' => (float) ($row->applied_total ?? 0),
                'sanctioned' => (float) ($row->sanctioned_total ?? 0),
            ];
        }

        return $out;
    }

    private function serviceCasePayloadAmountSql(string $key): string
    {
        $jsonExtract = match (DB::connection()->getDriverName()) {
            'sqlite' => "json_extract(sc.payload, '$.\"{$key}\"')",
            'pgsql' => "sc.payload::jsonb ->> '{$key}'",
            default => "JSON_UNQUOTE(JSON_EXTRACT(sc.payload, '$.\"{$key}\"'))",
        };
        $normalized = "REPLACE(REPLACE(REPLACE({$jsonExtract}, ',', ''), '₹', ''), ' ', '')";

        return "COALESCE(CAST(NULLIF({$normalized}, '') AS DECIMAL(18,2)), 0)";
    }

    /**
     * @param  array<int, array{applied: float, sanctioned: float}>  $left
     * @param  array<int, array{applied: float, sanctioned: float}>  $right
     * @return array<int, array{applied: float, sanctioned: float}>
     */
    private function mergeAmountTotalsByServiceId(array $left, array $right): array
    {
        $merged = $left;
        foreach ($right as $serviceId => $totals) {
            $merged[$serviceId]['applied'] = (float) (($merged[$serviceId]['applied'] ?? 0.0) + (float) ($totals['applied'] ?? 0.0));
            $merged[$serviceId]['sanctioned'] = (float) (($merged[$serviceId]['sanctioned'] ?? 0.0) + (float) ($totals['sanctioned'] ?? 0.0));
        }

        return $merged;
    }

    /**
     * @param  array<int, array{applied: float, sanctioned: float}>  $amountTotalsByServiceId
     */
    private function sumAmountMetric(array $amountTotalsByServiceId, string $metric): float
    {
        $total = 0.0;
        foreach ($amountTotalsByServiceId as $totals) {
            $total += (float) ($totals[$metric] ?? 0.0);
        }

        return round($total, 2);
    }

    /**
     * @param  list<array<string, mixed>>  $serviceRows
     * @param  Collection<int, object>  $rows
     * @param  array<int, array{applied: float, sanctioned: float}>  $amountTotalsByServiceId
     * @return list<array<string, mixed>>
     */
    private function enrichServiceRowsWithAmounts(array $serviceRows, Collection $rows, array $amountTotalsByServiceId, int $total): array
    {
        $serviceIdsByName = [];
        foreach ($rows as $row) {
            $serviceName = (string) ($row->service_name ?? '');
            $serviceId = (int) ($row->service_id ?? 0);
            if ($serviceName === '' || $serviceId < 1) {
                continue;
            }
            $serviceIdsByName[$serviceName][$serviceId] = true;
        }

        $out = [];
        foreach ($serviceRows as $row) {
            $serviceName = (string) ($row['service'] ?? '');
            $count = (int) ($row['count'] ?? 0);
            $applied = 0.0;
            $sanctioned = 0.0;
            foreach (array_keys($serviceIdsByName[$serviceName] ?? []) as $serviceId) {
                $applied += (float) ($amountTotalsByServiceId[$serviceId]['applied'] ?? 0.0);
                $sanctioned += (float) ($amountTotalsByServiceId[$serviceId]['sanctioned'] ?? 0.0);
            }

            $out[] = [
                'service' => $serviceName,
                'count' => $count,
                'share_pct' => $total > 0 ? (int) round(($count / $total) * 100) : (int) ($row['share_pct'] ?? 0),
                'applied_amount' => round($applied, 2),
                'sanctioned_amount' => round($sanctioned, 2),
            ];
        }

        return $out;
    }

    /**
     * @param  array<string, int>  $counts
     * @param  callable(string): array<string, string>  $extra
     * @return list<array<string, mixed>>
     */
    private function formatBreakdownList(array $counts, int $total, callable $extra): array
    {
        $out = [];
        foreach ($counts as $label => $count) {
            $out[] = array_merge($extra((string) $label), [
                'count' => $count,
                'share_pct' => $total > 0 ? (int) round(($count / $total) * 100) : 0,
            ]);
        }

        return $out;
    }

    /**
     * @param  array<string, int>  $counts
     * @return list<array<string, mixed>>
     */
    private function formatMonthBreakdown(array $counts, int $total): array
    {
        $out = [];
        foreach ($counts as $monthKey => $count) {
            try {
                $label = Carbon::createFromFormat('Y-m', $monthKey)->format('M Y');
            } catch (\Throwable) {
                $label = $monthKey;
            }
            $out[] = [
                'month' => $label,
                'month_key' => $monthKey,
                'count' => $count,
                'share_pct' => $total > 0 ? (int) round(($count / $total) * 100) : 0,
            ];
        }

        return $out;
    }

    /**
     * @param  Collection<int, object>  $rows
     */
    private function hubForDistrictName($rows, string $districtName): string
    {
        $match = $rows->first(fn ($row) => (string) ($row->district_name ?? '') === $districtName);

        return (string) ($match->hub_name ?? '—');
    }

    /**
     * @param  list<array<string, mixed>>  $byDistrict
     * @param  list<array<string, mixed>>  $byMonth
     * @param  list<array<string, mixed>>  $byService
     * @return list<array{label: string, value: string, tone: string}>
     */
    private function buildInsights(int $total, array $byDistrict, array $byMonth, array $byService): array
    {
        if ($total <= 0) {
            return [
                ['label' => 'Status', 'value' => 'No achievements in this period', 'tone' => 'muted'],
            ];
        }

        $insights = [];
        $topDistrict = $byDistrict[0] ?? null;
        if ($topDistrict) {
            $insights[] = [
                'label' => 'Top district',
                'value' => $topDistrict['district'].' ('.number_format((int) $topDistrict['count']).')',
                'tone' => 'primary',
            ];
        }

        $topMonth = collect($byMonth)->sortByDesc('count')->first();
        if ($topMonth) {
            $insights[] = [
                'label' => 'Peak month',
                'value' => (string) $topMonth['month'].' ('.number_format((int) $topMonth['count']).')',
                'tone' => 'success',
            ];
        }

        if ($byService !== []) {
            $topService = $byService[0];
            $insights[] = [
                'label' => 'Leading service',
                'value' => (string) $topService['service'].' ('.number_format((int) $topService['count']).')',
                'tone' => 'info',
            ];
        } else {
            $districtCount = count($byDistrict);
            if ($districtCount > 0) {
                $insights[] = [
                    'label' => 'District coverage',
                    'value' => $districtCount.' district'.($districtCount === 1 ? '' : 's'),
                    'tone' => 'info',
                ];
            }
        }

        return $insights;
    }

    /**
     * @param  array<string, mixed>  $source
     * @return list<int>
     */
    private function resolveServiceIdsForSource(array $source): array
    {
        $ids = [];
        $type = (string) ($source['type'] ?? '');

        if ($type === 'deliverable') {
            $ids = $this->serviceIdsForDeliverableCode((string) ($source['code'] ?? ''));
        } elseif ($type === 'service') {
            $ids = $this->serviceIdsForServiceCode((string) ($source['code'] ?? ''));
        } elseif ($type === 'services') {
            foreach ((array) ($source['codes'] ?? []) as $code) {
                $ids = array_merge($ids, $this->serviceIdsForServiceCode((string) $code));
            }
        }

        return array_values(array_unique(array_filter(array_map('intval', $ids))));
    }

    /**
     * @return list<int>
     */
    private function serviceIdsForServiceCode(string $code): array
    {
        $code = strtolower(trim($code));
        if ($code === '') {
            return [];
        }

        $codes = $this->candidateCodesForLookup($code);

        return Service::query()
            ->where('is_active', true)
            ->where(function ($q) use ($codes): void {
                foreach ($codes as $candidate) {
                    $q->orWhere('code', $candidate);
                }
            })
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * @return list<int>
     */
    private function serviceIdsForDeliverableCode(string $code): array
    {
        $code = strtolower(trim($code));
        if ($code === '') {
            return [];
        }

        $deliverableIds = [];
        $deliverableIdsByCode = Deliverable::query()->pluck('id', 'code')->map(fn ($id) => (int) $id)->all();

        foreach ($this->candidateCodesForLookup($code) as $candidate) {
            if (isset($deliverableIdsByCode[$candidate])) {
                $deliverableIds[] = (int) $deliverableIdsByCode[$candidate];
            }
        }

        $candidateCodes = $this->candidateCodesForLookup($code);
        $linkedDeliverableIds = Service::query()
            ->where('is_active', true)
            ->where(function ($q) use ($candidateCodes): void {
                foreach ($candidateCodes as $candidate) {
                    $q->orWhere('code', $candidate);
                }
                $q->orWhereHas('deliverable', function ($dq) use ($candidateCodes): void {
                    $dq->whereIn('code', $candidateCodes);
                });
            })
            ->pluck('deliverable_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->all();

        $deliverableIds = array_values(array_unique(array_merge($deliverableIds, $linkedDeliverableIds)));

        $serviceIds = Service::query()
            ->where('is_active', true)
            ->where(function ($q) use ($code, $candidateCodes, $deliverableIds): void {
                foreach ($candidateCodes as $candidate) {
                    $q->orWhere('code', $candidate);
                }
                if ($deliverableIds !== []) {
                    $q->orWhereIn('deliverable_id', $deliverableIds);
                }
                $keywords = config('program_deliverables.achievement_deliverable_keywords.'.$code, []);
                if (is_array($keywords)) {
                    foreach ($keywords as $keyword) {
                        $keyword = trim((string) $keyword);
                        if ($keyword !== '') {
                            $q->orWhere('name', 'like', '%'.$keyword.'%');
                        }
                    }
                }
            })
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return $serviceIds;
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
     * @return array<string, mixed>
     */
    private function emptyBreakdown(): array
    {
        return [
            'total' => 0,
            'by_district' => [],
            'by_hub' => [],
            'by_month' => [],
            'by_service' => [],
            'applied_amount_total' => null,
            'sanctioned_amount_total' => null,
            'records' => [],
        ];
    }

    private function sourceTypeLabel(string $type, array $source = []): string
    {
        if ($type === 'deliverable' && strtolower(trim((string) ($source['code'] ?? ''))) === 'social_media') {
            return 'Logged social media posts';
        }

        return match ($type) {
            'deliverable', 'service', 'services' => 'Approved service cases',
            'cfa_count' => 'CFA submissions',
            'onboarding_count' => 'Onboarded incubatees',
            'potential_lakhpati_onboarding_count' => 'Potential Lakhpati Didi/ SHG/CBO onboardings',
            'field_work_workshops', 'field_visit_sessions' => 'Field work visits & block workshops',
            'field_work_participants', 'field_visit_participants' => 'Female outreach participants',
            'district_workshop_sessions' => 'District workshops',
            'edp_sessions' => 'EAP/EDP sessions',
            'bst_sessions' => 'Business skills training sessions (conducted)',
            'bst_participants' => 'Unique BST participants',
            'technical_training_sessions' => 'Technical training sessions',
            'technical_training_potential_lakhpati_sessions' => '3.3.1 Technical Trainings to Potential Lakhpati Didis/ SHG Members/ CBOs',
            'market_linkage_unique_partners' => 'Market linkage partners',
            'market_linkage_incubatees' => 'Market linkage incubatees',
            'community_org_outreach_count' => 'Community organization outreach visits',
            'capacity_building_stakeholder_sessions' => '3.4 Capacity building of stakeholders',
            'pitch_deck_preparations', 'pitch_deck_combined' => '8.3 Incubatees Pitch Deck Preparation',
            default => 'Achievement records',
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function marketLinkagePartnersBreakdown(): array
    {
        if (! Schema::hasTable('market_linkage_submissions') || ! Schema::hasTable('market_linkage_partners')) {
            return $this->emptyBreakdown();
        }

        if ($this->districtIds === []) {
            return $this->emptyBreakdown();
        }

        $query = DB::table('market_linkage_partners as mlp')
            ->join('market_linkage_submissions as mls', 'mls.id', '=', 'mlp.market_linkage_submission_id')
            ->join('districts as d', 'd.id', '=', 'mls.district_id')
            ->leftJoin('hubs as h', 'h.id', '=', 'd.hub_id')
            ->selectRaw('
                mlp.partner_name,
                mlp.linkage_mode,
                mlp.linkage_date,
                mlp.link_url,
                mls.incubatee_name,
                mls.application_no,
                d.name as district_name,
                h.name as hub_name
            ');

        $this->applyMarketLinkageApprovedScope($query);
        $this->applyDistrictScope($query, 'mls.district_id');

        $grouped = [];
        foreach ($query->get() as $row) {
            $partnerName = trim((string) ($row->partner_name ?? ''));
            if ($partnerName === '') {
                continue;
            }

            $key = $this->marketLinkagePartners->normalizePartnerKey($partnerName);
            if ($key === '') {
                continue;
            }

            if (! isset($grouped[$key])) {
                $grouped[$key] = [
                    'partner_name' => $this->marketLinkagePartners->displayLabelFor($partnerName),
                    'linkage_mode' => (string) $row->linkage_mode,
                    'linkage_date' => $row->linkage_date,
                    'link_url' => (string) ($row->link_url ?? ''),
                    'incubatee_name' => (string) $row->incubatee_name,
                    'application_no' => (string) ($row->application_no ?? ''),
                    'district_name' => (string) $row->district_name,
                    'hub_name' => (string) ($row->hub_name ?? ''),
                    'partner_count' => 0,
                ];
            }

            $grouped[$key]['partner_count']++;
            if ($row->linkage_date && (
                ! $grouped[$key]['linkage_date']
                || Carbon::parse($row->linkage_date)->gt(Carbon::parse($grouped[$key]['linkage_date']))
            )) {
                $grouped[$key]['linkage_date'] = $row->linkage_date;
            }
        }

        $records = collect($grouped)
            ->sortBy('partner_name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->map(fn (array $row) => [
                'reference' => (string) ($row['application_no'] ?: '—'),
                'applicant' => (string) ($row['incubatee_name'] ?: '—'),
                'service' => (string) ($row['partner_name'] ?: '—'),
                'date' => $row['linkage_date'] ? Carbon::parse($row['linkage_date'])->format('d M Y') : '—',
                'district' => (string) $row['district_name'],
                'hub' => (string) ($row['hub_name'] ?? ''),
                'partner_name' => (string) $row['partner_name'],
                'linkage_mode' => (string) $row['linkage_mode'],
                'linkage_date' => (string) ($row['linkage_date'] ?? ''),
                'link_url' => (string) ($row['link_url'] ?? ''),
                'incubatee_name' => (string) $row['incubatee_name'],
                'application_no' => (string) ($row['application_no'] ?? ''),
            ])
            ->all();

        $byDistrict = DB::table('market_linkage_partners as mlp')
            ->join('market_linkage_submissions as mls', 'mls.id', '=', 'mlp.market_linkage_submission_id')
            ->join('districts as d', 'd.id', '=', 'mls.district_id')
            ->selectRaw('d.name as label, mlp.partner_name');
        $this->applyMarketLinkageApprovedScope($byDistrict);
        $this->applyDistrictScope($byDistrict, 'mls.district_id');
        $byDistrictCounts = [];
        foreach ($byDistrict->get() as $row) {
            $label = (string) ($row->label ?? '');
            $key = $this->marketLinkagePartners->normalizePartnerKey((string) ($row->partner_name ?? ''));
            if ($label === '' || $key === '') {
                continue;
            }
            $byDistrictCounts[$label][$key] = true;
        }
        $byDistrictRows = collect($byDistrictCounts)
            ->map(fn (array $keys, string $label) => ['label' => $label, 'total' => count($keys)])
            ->sortByDesc('total')
            ->values()
            ->all();

        $totalQuery = DB::table('market_linkage_partners as mlp')
            ->join('market_linkage_submissions as mls', 'mls.id', '=', 'mlp.market_linkage_submission_id');
        $this->applyMarketLinkageApprovedScope($totalQuery);
        $this->applyDistrictScope($totalQuery, 'mls.district_id');
        $total = $this->marketLinkagePartners->countUniquePartnerKeys(
            $totalQuery->distinct()->pluck('mlp.partner_name')
        );

        $byDistrictCounts = [];
        foreach ($byDistrictRows as $row) {
            $label = (string) ($row['label'] ?? '');
            if ($label === '') {
                continue;
            }
            $byDistrictCounts[$label] = (int) ($row['total'] ?? 0);
        }

        return [
            'total' => $total,
            'by_district' => $this->formatBreakdownList($byDistrictCounts, $total, fn ($name) => ['district' => $name, 'hub' => '—']),
            'by_hub' => [],
            'by_month' => [],
            'by_service' => [],
            'records' => $records,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function marketLinkageIncubateesBreakdown(): array
    {
        if (! Schema::hasTable('market_linkage_submissions') || ! Schema::hasTable('market_linkage_partners')) {
            return $this->emptyBreakdown();
        }

        if ($this->districtIds === []) {
            return $this->emptyBreakdown();
        }

        $incubateeKeySql = <<<'SQL'
CASE
    WHEN mls.cfa_submission_id IS NOT NULL THEN CONCAT('c:', mls.cfa_submission_id)
    WHEN mls.legacy_application_id IS NOT NULL THEN CONCAT('l:', mls.legacy_application_id)
    ELSE CONCAT('s:', mls.id)
END
SQL;

        $query = DB::table('market_linkage_submissions as mls')
            ->join('districts as d', 'd.id', '=', 'mls.district_id')
            ->leftJoin('hubs as h', 'h.id', '=', 'd.hub_id')
            ->whereExists(function ($sub): void {
                $sub->select(DB::raw('1'))
                    ->from('market_linkage_partners as mlp')
                    ->whereColumn('mlp.market_linkage_submission_id', 'mls.id');
            })
            ->selectRaw("{$incubateeKeySql} as incubatee_key, mls.incubatee_name, mls.application_no, d.name as district_name, h.name as hub_name, COUNT(mlp.id) as partner_count")
            ->join('market_linkage_partners as mlp', 'mlp.market_linkage_submission_id', '=', 'mls.id');

        $this->applyMarketLinkageApprovedScope($query);
        $this->applyDistrictScope($query, 'mls.district_id');

        $records = $query
            ->groupBy('incubatee_key', 'mls.incubatee_name', 'mls.application_no', 'd.name', 'h.name')
            ->orderBy('mls.incubatee_name')
            ->limit(500)
            ->get()
            ->map(fn ($row) => [
                'reference' => (string) ($row->application_no ?? '—'),
                'applicant' => (string) ($row->incubatee_name ?: '—'),
                'service' => 'Partners linked: '.(int) $row->partner_count,
                'date' => '—',
                'district' => (string) $row->district_name,
                'hub' => (string) ($row->hub_name ?? ''),
                'incubatee_name' => (string) $row->incubatee_name,
                'application_no' => (string) ($row->application_no ?? ''),
                'partner_count' => (int) $row->partner_count,
            ])
            ->all();

        $totalQuery = DB::table('market_linkage_submissions as mls')
            ->whereExists(function ($sub): void {
                $sub->select(DB::raw('1'))
                    ->from('market_linkage_partners as mlp')
                    ->whereColumn('mlp.market_linkage_submission_id', 'mls.id');
            });
        $this->applyMarketLinkageApprovedScope($totalQuery);
        $this->applyDistrictScope($totalQuery, 'mls.district_id');
        $total = (int) $totalQuery->selectRaw("COUNT(DISTINCT {$incubateeKeySql}) as aggregate")->value('aggregate');

        return [
            'total' => $total,
            'by_district' => [],
            'by_hub' => [],
            'by_month' => [],
            'by_service' => [],
            'records' => $records,
        ];
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

    private function phase3FloorDate(): Carbon
    {
        $raw = (string) config('program_deliverables.phase3_floor_date', '2026-04-01');

        return Carbon::parse($raw)->startOfDay();
    }

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
    private function applyFieldWorkAchievementScope($query): void
    {
        $this->applyDistrictScopeOnModel($query, 'field_coordinator_attendance_reports.district_id');

        if (FieldCoordinatorAttendanceReport::supportsDraftWorkflow()) {
            $query->where(function ($q): void {
                $q->where('field_coordinator_attendance_reports.status', FieldCoordinatorAttendanceReport::STATUS_SUBMITTED)
                    ->orWhereNull('field_coordinator_attendance_reports.status');
            });
        }

        $floor = $this->phase3FloorDate();

        if ($this->filter?->hasExplicitDateFilter() && $this->periodFrom && $this->periodTo) {
            $from = $this->periodFrom->copy();
            if ($from->lt($floor)) {
                $from = $floor->copy();
            }
            $query->whereBetween('field_coordinator_attendance_reports.visit_date', [$from->toDateString(), $this->periodTo->toDateString()]);

            return;
        }

        $query->where('field_coordinator_attendance_reports.visit_date', '>=', $floor->toDateString());

        if ($this->periodTo) {
            $query->where('field_coordinator_attendance_reports.visit_date', '<=', $this->periodTo->toDateString());
        }
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
    private function applySocialMediaPostsAchievementScope($query, string $postedOnColumn = 'posted_on'): void
    {
        $floor = $this->phase3FloorDate();

        if ($this->periodFrom && $this->periodTo) {
            $from = $this->periodFrom->copy();
            if ($from->lt($floor)) {
                $from = $floor->copy();
            }
            $query->whereBetween($postedOnColumn, [$from->toDateString(), $this->periodTo->toDateString()]);

            return;
        }

        $query->where($postedOnColumn, '>=', $floor->toDateString());
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
     * @param  Builder  $query
     */
    private function applyServiceCaseDateScope($query, string $dateExpr): void
    {
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
    }

    /**
     * @param  Builder  $query
     */
    private function applyCfaAchievementScope($query): void
    {
        $fyId = (int) ($this->activeFiscalYear?->id ?? 0);

        if ($fyId > 0 && Schema::hasColumn('cfa_submissions', 'fiscal_year_id')) {
            $query->where('cs.fiscal_year_id', $fyId);

            if ($this->filter?->hasExplicitDateFilter()) {
                $this->applyPeriodFilter($query, 'cs.created_at');
            }

            return;
        }

        $floor = $this->phase3FloorDate();
        if ($this->periodFrom && $this->periodTo) {
            $from = $this->periodFrom->copy();
            if ($from->lt($floor)) {
                $from = $floor->copy();
            }
            $query->whereBetween('cs.created_at', [$from->toDateTimeString(), $this->periodTo->toDateTimeString()]);
        } else {
            $query->where('cs.created_at', '>=', $floor->toDateTimeString());
        }
    }

    /**
     * @param  Builder  $query
     */
    private function applyOnboardingAchievementScope($query): void
    {
        // Bucket onboardings by ob.onboarding_date (the real onboarded day), not by
        // ob.locked_at — keeps the breakdown drawer aligned with the matrix totals.
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
                    $q2->where('t.calendar_year', $cursor->year)
                        ->where('t.calendar_month', $cursor->month);
                });
                $cursor->addMonth();
            }
        });
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

    private function monthKeySql(string $columnExpression): string
    {
        return match (DB::connection()->getDriverName()) {
            'sqlite' => "strftime('%Y-%m', {$columnExpression})",
            'pgsql' => "to_char({$columnExpression}, 'YYYY-MM')",
            default => "DATE_FORMAT({$columnExpression}, '%Y-%m')",
        };
    }
}
