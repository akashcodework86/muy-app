<?php

namespace App\Services\Deliverables;

use App\Models\Deliverable;
use App\Models\FieldCoordinatorAttendanceReport;
use App\Models\FiscalYear;
use App\Models\MarketLinkageSubmission;
use App\Models\District;
use App\Models\Service;
use App\Models\ServiceCase;
use App\Services\LegacyApplicationServiceCaseSupport;
use App\Services\ServiceTargetDeliverableSyncService;
use Illuminate\Support\Collection;
use Carbon\Carbon;
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
            'field_work_workshops', 'field_visit_sessions' => $this->fieldWorkBreakdown(false),
            'field_work_participants', 'field_visit_participants' => $this->fieldWorkBreakdown(true),
            'district_workshop_sessions' => $this->simpleTableBreakdown('district_workshop_sessions', 'event_date', 'created_at'),
            'edp_sessions' => $this->edpBreakdown(),
            'bst_sessions' => $this->bstSessionsBreakdown(),
            'bst_participants' => $this->bstParticipantsBreakdown(),
            'technical_training_sessions' => $this->technicalTrainingBreakdown(),
            'market_linkage_unique_partners' => $this->marketLinkagePartnersBreakdown(),
            'market_linkage_incubatees' => $this->marketLinkageIncubateesBreakdown(),
            default => ['total' => 0, 'by_district' => [], 'by_hub' => [], 'by_month' => [], 'by_service' => [], 'records' => []],
        };

        $total = (int) ($breakdown['total'] ?? 0);
        $byDistrict = $breakdown['by_district'] ?? [];
        $byMonth = $breakdown['by_month'] ?? [];

        $sourceTypeLabel = $this->sourceTypeLabel($sourceType, $source);

        return [
            'serial' => $serial,
            'name' => (string) ($leaf['name'] ?? ''),
            'indicator_type' => (string) ($leaf['indicator_type'] ?? ''),
            'level' => (string) ($leaf['level'] ?? ''),
            'source_type' => $sourceType,
            'source_type_label' => $sourceTypeLabel,
            'total' => $total,
            'by_district' => $byDistrict,
            'by_hub' => $breakdown['by_hub'] ?? [],
            'by_month' => $byMonth,
            'by_service' => $breakdown['by_service'] ?? [],
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
                s.name as service_name,
                {$monthExpr} as month_key,
                COUNT(*) as total
            ")
            ->groupBy('d.id', 'd.name', 'h.name', 's.id', 's.name', DB::raw($monthExpr))
            ->get();

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

        [$legacyRows, $legacyRecords] = $this->legacyServiceCaseBreakdownContributions(
            $serviceIds,
            $dateExpr,
            $monthExpr,
            $statuses,
        );

        $rows = $this->mergeServiceCaseAggregateRows($cfaRows, $legacyRows);
        $records = $this->mergeServiceCaseBreakdownRecords($cfaRecords, $legacyRecords, 100);

        return $this->aggregateGroupedRows($rows, includeService: true, records: $records);
    }

    /**
     * @param  list<int>  $serviceIds
     * @param  list<string>  $statuses
     * @return array{0: Collection<int, object>, 1: list<array<string, mixed>>}
     */
    private function legacyServiceCaseBreakdownContributions(
        array $serviceIds,
        string $dateExpr,
        string $monthExpr,
        array $statuses,
    ): array {
        if (! ServiceCase::supportsLegacyApplicationLink()) {
            return [collect(), []];
        }

        if ($this->districtIds === []) {
            return [collect(), []];
        }

        $legacyIds = $this->districtIds === null
            ? null
            : $this->legacyServiceCases->legacyApplicationIdsForLaravelDistrictIds($this->districtIds);

        if ($legacyIds !== null && $legacyIds === []) {
            return [collect(), []];
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

        $cases = (clone $query)
            ->select([
                'sc.id',
                'sc.legacy_application_id',
                'sc.reference_number',
                'sc.status',
                's.name as service_name',
                'spoc.name as spoc_name',
            ])
            ->selectRaw("{$dateExpr} as achievement_date")
            ->selectRaw("{$monthExpr} as month_key")
            ->get();

        if ($cases->isEmpty()) {
            return [collect(), []];
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

            $bucketKey = implode('|', [$districtName, $hubName, $serviceName, $monthKey]);
            if (! isset($aggregateBuckets[$bucketKey])) {
                $aggregateBuckets[$bucketKey] = (object) [
                    'district_id' => (int) ($district?->id ?? 0),
                    'district_name' => $districtName,
                    'hub_name' => $hubName,
                    'service_id' => 0,
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

        return [collect(array_values($aggregateBuckets)), $records];
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
     * @param  object  $row
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
                (string) ($row->service_name ?? 'Unknown'),
                (string) ($row->month_key ?? ''),
            ]);

            if (! isset($merged[$key])) {
                $merged[$key] = (object) [
                    'district_id' => (int) ($row->district_id ?? 0),
                    'district_name' => (string) ($row->district_name ?? 'Unknown'),
                    'hub_name' => (string) ($row->hub_name ?? 'Unassigned'),
                    'service_id' => (int) ($row->service_id ?? 0),
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

            $countExpr = $participants
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

            if ($participants) {
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
            } else {
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

            $countExpr = $participants
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

            if ($participants) {
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
            } else {
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
     * @param  \Illuminate\Database\Query\Builder  $query
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
        if (Schema::hasTable('training_package_month_sessions')) {
            return $this->bstMonthSessionsBreakdown();
        }

        if (Schema::hasTable('training_packages')) {
            $dateCol = Schema::hasColumn('training_packages', 'event_date') ? 'event_date' : 'created_at';

            return $this->simpleTableBreakdown('training_packages', $dateCol, 'created_at', label: 'BST Session');
        }

        return $this->emptyBreakdown();
    }

    /**
     * @return array<string, mixed>
     */
    private function bstParticipantsBreakdown(): array
    {
        if (! Schema::hasTable('training_packages')) {
            return $this->emptyBreakdown();
        }

        $dateCol = Schema::hasColumn('training_packages', 'event_date') ? 'event_date' : 'created_at';
        $query = DB::table('training_packages as t')
            ->join('districts as d', 'd.id', '=', 't.district_id')
            ->leftJoin('hubs as h', 'h.id', '=', 'd.hub_id');

        $this->applyDistrictScope($query, 't.district_id');
        $this->applyPeriodFilter($query, 't.'.$dateCol);
        $monthExpr = $this->monthKeySql("t.{$dateCol}");

        $participantExpr = Schema::hasColumn('training_packages', 'participants_total')
            ? 'COALESCE(t.participants_total, 0)'
            : 'COALESCE(t.male_participants, 0) + COALESCE(t.female_participants, 0)';

        $rows = (clone $query)
            ->selectRaw("
                d.id as district_id,
                d.name as district_name,
                h.name as hub_name,
                {$monthExpr} as month_key,
                SUM({$participantExpr}) as total
            ")
            ->groupBy('d.id', 'd.name', 'h.name', DB::raw($monthExpr))
            ->get();

        return $this->aggregateGroupedRows($rows, includeService: false);
    }

    /**
     * @return array<string, mixed>
     */
    private function bstMonthSessionsBreakdown(): array
    {
        $query = DB::table('training_package_month_sessions as t')
            ->join('districts as d', 'd.id', '=', 't.district_id')
            ->leftJoin('hubs as h', 'h.id', '=', 'd.hub_id');

        $this->applyDistrictScope($query, 't.district_id');
        $this->applyBstMonthSessionPeriod($query);

        $rows = (clone $query)
            ->selectRaw('
                d.id as district_id,
                d.name as district_name,
                h.name as hub_name,
                CONCAT(t.calendar_year, "-", LPAD(t.calendar_month, 2, "0")) as month_key,
                COUNT(*) as total
            ')
            ->groupBy('d.id', 'd.name', 'h.name', 't.calendar_year', 't.calendar_month')
            ->get();

        return $this->aggregateGroupedRows($rows, includeService: false);
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
     * @param  \Illuminate\Support\Collection<int, object>  $rows
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
            'records' => $records,
        ];
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
     * @param  \Illuminate\Support\Collection<int, object>  $rows
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
            'field_work_workshops', 'field_visit_sessions' => 'Field work visits & block workshops',
            'field_work_participants', 'field_visit_participants' => 'Female outreach participants',
            'district_workshop_sessions' => 'District workshops',
            'edp_sessions' => 'EAP/EDP sessions',
            'bst_sessions' => 'Business skills training sessions',
            'bst_participants' => 'BST participants',
            'technical_training_sessions' => 'Technical training sessions',
            'market_linkage_unique_partners' => 'Market linkage partners',
            'market_linkage_incubatees' => 'Market linkage incubatees',
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
            ->selectRaw('d.name as district_name, h.name as hub_name, mlp.partner_name, mlp.linkage_mode, mlp.linkage_date, mlp.link_url, mls.incubatee_name, mls.application_no');

        $this->applyMarketLinkageApprovedScope($query);
        $this->applyDistrictScope($query, 'mls.district_id');
        $this->applyMarketLinkagePartnerDateScope($query);

        $records = $query
            ->orderByDesc('mlp.linkage_date')
            ->orderBy('mlp.partner_name')
            ->limit(500)
            ->get()
            ->map(fn ($row) => [
                'district' => (string) $row->district_name,
                'hub' => (string) ($row->hub_name ?? ''),
                'partner_name' => (string) $row->partner_name,
                'linkage_mode' => (string) $row->linkage_mode,
                'linkage_date' => (string) $row->linkage_date,
                'link_url' => (string) ($row->link_url ?? ''),
                'incubatee_name' => (string) $row->incubatee_name,
                'application_no' => (string) ($row->application_no ?? ''),
            ])
            ->all();

        $byDistrict = DB::table('market_linkage_partners as mlp')
            ->join('market_linkage_submissions as mls', 'mls.id', '=', 'mlp.market_linkage_submission_id')
            ->join('districts as d', 'd.id', '=', 'mls.district_id')
            ->selectRaw('d.name as label, COUNT(DISTINCT LOWER(TRIM(mlp.partner_name))) as total');
        $this->applyMarketLinkageApprovedScope($byDistrict);
        $this->applyDistrictScope($byDistrict, 'mls.district_id');
        $this->applyMarketLinkagePartnerDateScope($byDistrict);
        $byDistrictRows = $byDistrict->groupBy('d.name')->orderByDesc('total')->get()
            ->map(fn ($r) => ['label' => (string) $r->label, 'total' => (int) $r->total])->all();

        $totalQuery = DB::table('market_linkage_partners as mlp')
            ->join('market_linkage_submissions as mls', 'mls.id', '=', 'mlp.market_linkage_submission_id');
        $this->applyMarketLinkageApprovedScope($totalQuery);
        $this->applyDistrictScope($totalQuery, 'mls.district_id');
        $this->applyMarketLinkagePartnerDateScope($totalQuery);
        $total = (int) $totalQuery->selectRaw('COUNT(DISTINCT LOWER(TRIM(mlp.partner_name))) as aggregate')->value('aggregate');

        return [
            'total' => $total,
            'by_district' => $byDistrictRows,
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
                $this->applyMarketLinkagePartnerDateScope($sub);
            })
            ->selectRaw("{$incubateeKeySql} as incubatee_key, mls.incubatee_name, mls.application_no, d.name as district_name, h.name as hub_name, COUNT(mlp.id) as partner_count")
            ->join('market_linkage_partners as mlp', 'mlp.market_linkage_submission_id', '=', 'mls.id');

        $this->applyMarketLinkageApprovedScope($query);
        $this->applyDistrictScope($query, 'mls.district_id');
        $this->applyMarketLinkagePartnerDateScope($query, 'mlp.linkage_date');

        $records = $query
            ->groupBy('incubatee_key', 'mls.incubatee_name', 'mls.application_no', 'd.name', 'h.name')
            ->orderBy('mls.incubatee_name')
            ->limit(500)
            ->get()
            ->map(fn ($row) => [
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
                $this->applyMarketLinkagePartnerDateScope($sub);
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
     * @param  \Illuminate\Database\Query\Builder  $query
     */
    private function applyMarketLinkageApprovedScope($query): void
    {
        if (MarketLinkageSubmission::supportsWorkflow()) {
            $query->where('mls.status', ServiceCase::STATUS_APPROVED);
        }
    }

    /**
     * @param  \Illuminate\Database\Query\Builder  $query
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
     * @param  \Illuminate\Database\Query\Builder  $query
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
     * @param  \Illuminate\Database\Eloquent\Builder<\App\Models\FieldCoordinatorAttendanceReport>  $query
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
     * @param  \Illuminate\Database\Eloquent\Builder<\App\Models\FieldCoordinatorAttendanceReport>  $query
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
     * @param  \Illuminate\Database\Query\Builder  $query
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
     * @param  \Illuminate\Database\Query\Builder  $query
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
     * @param  \Illuminate\Database\Query\Builder  $query
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
     * @param  \Illuminate\Database\Query\Builder  $query
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
     * @param  \Illuminate\Database\Query\Builder  $query
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
     * @param  \Illuminate\Database\Query\Builder  $query
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
