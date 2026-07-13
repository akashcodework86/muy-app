<?php

namespace App\Support;

use App\Models\District;
use App\Models\MarketLinkageSubmission;
use App\Models\Service;
use App\Models\ServiceCase;
use App\Services\LegacyApplicationServiceCaseSupport;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class MarketLinkageUnifiedListingSupport
{
    /** @var list<string> */
    public const MARKET_LINK_SERVICE_CODES = [
        'incubatees_linked_to_online_offline_market',
        'market_link',
        'offline_connect',
    ];

    public const INCUBATEE_KEY_SQL = <<<'SQL'
CASE
    WHEN mls.cfa_submission_id IS NOT NULL THEN CONCAT('c:', mls.cfa_submission_id)
    WHEN mls.legacy_application_id IS NOT NULL THEN CONCAT('l:', mls.legacy_application_id)
    ELSE CONCAT('s:', mls.id)
END
SQL;

    public static function isMarketLinkServiceId(int $serviceId): bool
    {
        if ($serviceId < 1) {
            return false;
        }

        return in_array($serviceId, self::marketLinkServiceIds(), true);
    }

    /**
     * @return list<int>
     */
    public static function marketLinkServiceIds(): array
    {
        if (! Schema::hasTable('services')) {
            return [];
        }

        return Service::query()
            ->whereIn('code', self::MARKET_LINK_SERVICE_CODES)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();
    }

    public static function incubateeKeyForServiceCase(ServiceCase $case): ?string
    {
        return self::incubateeKeyFromIds(
            (int) ($case->cfa_submission_id ?? 0) ?: null,
            (int) ($case->legacy_application_id ?? 0) ?: null,
        );
    }

    public static function incubateeKeyForMarketLinkage(MarketLinkageSubmission $submission): string
    {
        $key = self::incubateeKeyFromIds(
            (int) ($submission->cfa_submission_id ?? 0) ?: null,
            (int) ($submission->legacy_application_id ?? 0) ?: null,
            (int) $submission->id,
        );

        return $key ?? 's:'.(int) $submission->id;
    }

    public static function incubateeKeyFromIds(?int $cfaSubmissionId, ?int $legacyApplicationId, ?int $submissionFallbackId = null): ?string
    {
        if ($cfaSubmissionId !== null && $cfaSubmissionId > 0) {
            return 'c:'.$cfaSubmissionId;
        }

        if ($legacyApplicationId !== null && $legacyApplicationId > 0) {
            return 'l:'.$legacyApplicationId;
        }

        if ($submissionFallbackId !== null && $submissionFallbackId > 0) {
            return 's:'.$submissionFallbackId;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<string>
     */
    public static function linkageModeLabelsFromServiceCasePayload(array $payload): array
    {
        $raw = $payload['m'] ?? null;
        $values = is_array($raw) ? $raw : ($raw !== null && $raw !== '' ? [(string) $raw] : []);
        $labels = [];

        foreach ($values as $value) {
            $label = self::normalizeLinkageModeLabel((string) $value);
            if ($label !== '' && ! in_array($label, $labels, true)) {
                $labels[] = $label;
            }
        }

        return $labels;
    }

    public static function linkageModeLabelFromPartnerMode(?string $mode): string
    {
        return self::normalizeLinkageModeLabel($mode);
    }

    public static function linkageModeDisplay(?string $mode): string
    {
        $label = self::normalizeLinkageModeLabel($mode);

        return $label !== '' ? $label : '—';
    }

    /**
     * Unified approved incubatee counts: market linkage + orphan service cases (each incubatee once).
     *
     * When $periodFrom/$periodTo are provided, only linkages within that window are counted
     * (market linkages by linkage_date, orphan service cases by approved/created date). When they
     * are null the count is cumulative (all approved linkages regardless of date).
     *
     * @param  list<int>|null  $districtIds  null = statewide
     * @return array{offline_incubatees: int, online_incubatees: int, total_incubatees: int}
     */
    public static function approvedIncubateeModeCounts(?array $districtIds, bool $approvedOnly = true, ?Carbon $periodFrom = null, ?Carbon $periodTo = null, bool $includeServiceCaseOrphans = true): array
    {
        if ($districtIds === []) {
            return ['offline_incubatees' => 0, 'online_incubatees' => 0, 'total_incubatees' => 0];
        }

        return self::countIncubateeModeMap(
            self::buildUnifiedIncubateeModeMap($districtIds, $approvedOnly, $periodFrom, $periodTo, $includeServiceCaseOrphans),
        );
    }

    /**
     * @param  list<int>|null  $districtIds
     * @return list<array{service: string, count: int, share_pct: int}>
     */
    public static function linkageModeBifurcationRows(?array $districtIds, bool $approvedOnly = true, ?Carbon $periodFrom = null, ?Carbon $periodTo = null, bool $includeServiceCaseOrphans = true): array
    {
        $counts = self::approvedIncubateeModeCounts($districtIds, $approvedOnly, $periodFrom, $periodTo, $includeServiceCaseOrphans);
        $rows = [];

        foreach ([
            'Offline' => $counts['offline_incubatees'],
            'Online' => $counts['online_incubatees'],
        ] as $label => $count) {
            $rows[] = [
                'service' => $label,
                'count' => $count,
                'share_pct' => $counts['total_incubatees'] > 0
                    ? (int) round(($count / $counts['total_incubatees']) * 100)
                    : 0,
            ];
        }

        return $rows;
    }

    /**
     * @param  list<int>|null  $districtIds
     * @return list<array{
     *     reference: string,
     *     applicant: string,
     *     service: string,
     *     linkage_mode: string,
     *     date: string,
     *     district: string,
     *     hub: string
     * }>
     */
    public static function unifiedApprovedIncubateeRecords(?array $districtIds, bool $approvedOnly = true, int $limit = 500, ?Carbon $periodFrom = null, ?Carbon $periodTo = null, bool $includeServiceCaseOrphans = true): array
    {
        if ($districtIds === []) {
            return [];
        }

        // Aggregate to ONE row per incubatee (an incubatee may have several partner
        // linkages) so the number of rows matches the incubatee achievement total.
        $incubatees = [];

        if (Schema::hasTable('market_linkage_submissions') && Schema::hasTable('market_linkage_partners')) {
            $keySql = self::INCUBATEE_KEY_SQL;

            $query = DB::table('market_linkage_submissions as mls')
                ->join('districts as d', 'd.id', '=', 'mls.district_id')
                ->leftJoin('hubs as h', 'h.id', '=', 'd.hub_id')
                ->join('market_linkage_partners as mlp', 'mlp.market_linkage_submission_id', '=', 'mls.id')
                ->selectRaw('
                    '.$keySql.' as incubatee_key,
                    mls.application_no,
                    mls.incubatee_name,
                    d.name as district_name,
                    h.name as hub_name,
                    mlp.partner_name,
                    mlp.linkage_mode,
                    mlp.linkage_date
                ');

            self::applyMarketLinkageDistrictAndStatusScopes($query, $districtIds, $approvedOnly);
            self::applyMarketLinkagePartnerPeriodScope($query, $periodFrom, $periodTo);

            foreach ($query->orderBy('mls.incubatee_name')->orderBy('mlp.sort_order')->orderBy('mlp.id')->get() as $row) {
                $key = (string) ($row->incubatee_key ?? '');
                if ($key === '') {
                    continue;
                }

                if (! isset($incubatees[$key])) {
                    $incubatees[$key] = self::newIncubateeAggregate(
                        (string) ($row->application_no ?? '—'),
                        (string) ($row->incubatee_name ?: '—'),
                        (string) $row->district_name,
                        (string) ($row->hub_name ?? ''),
                    );
                }

                self::pushIncubateeAggregate(
                    $incubatees[$key],
                    (string) ($row->partner_name ?? ''),
                    [self::linkageModeLabelFromPartnerMode((string) ($row->linkage_mode ?? ''))],
                    $row->linkage_date ? (string) $row->linkage_date : null,
                );
            }
        }

        if ($includeServiceCaseOrphans) {
            foreach (self::orphanServiceCaseIncubateeAggregates($districtIds, $approvedOnly, $periodFrom, $periodTo) as $key => $agg) {
                // A Market Linkage module record for the same incubatee always wins.
                if (! isset($incubatees[$key])) {
                    $incubatees[$key] = $agg;
                }
            }
        }

        $records = array_map(
            static fn (array $agg): array => self::formatIncubateeRecord($agg),
            array_values($incubatees),
        );

        usort($records, static fn (array $a, array $b): int => strcasecmp((string) $a['applicant'], (string) $b['applicant']));

        if ($limit > 0 && count($records) > $limit) {
            $records = array_slice($records, 0, $limit);
        }

        return $records;
    }

    /**
     * @param  Collection<int, MarketLinkageSubmission>  $submissions
     * @return list<string>
     */
    public static function incubateeKeysFromSubmissions(Collection $submissions): array
    {
        return $submissions
            ->map(fn (MarketLinkageSubmission $submission) => self::incubateeKeyForMarketLinkage($submission))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  list<int>|null  $districtIds
     * @return array<string, list<string>>
     */
    private static function buildUnifiedIncubateeModeMap(?array $districtIds, bool $approvedOnly, ?Carbon $periodFrom = null, ?Carbon $periodTo = null, bool $includeServiceCaseOrphans = true): array
    {
        $map = self::marketLinkageIncubateeModeMap($districtIds, $approvedOnly, $periodFrom, $periodTo);

        if (! $includeServiceCaseOrphans) {
            return $map;
        }

        foreach (self::orphanServiceCaseIncubateeModeMap($districtIds, $approvedOnly, $periodFrom, $periodTo) as $key => $modes) {
            if (! isset($map[$key])) {
                $map[$key] = $modes;
            }
        }

        return $map;
    }

    /**
     * @param  list<int>|null  $districtIds
     * @return array<string, list<string>>
     */
    private static function marketLinkageIncubateeModeMap(?array $districtIds, bool $approvedOnly, ?Carbon $periodFrom = null, ?Carbon $periodTo = null): array
    {
        if (! Schema::hasTable('market_linkage_submissions') || ! Schema::hasTable('market_linkage_partners')) {
            return [];
        }

        $keySql = self::INCUBATEE_KEY_SQL;

        $query = DB::table('market_linkage_submissions as mls')
            ->join('market_linkage_partners as mlp', 'mlp.market_linkage_submission_id', '=', 'mls.id')
            ->selectRaw("{$keySql} as incubatee_key, mlp.linkage_mode as linkage_mode");

        self::applyMarketLinkageDistrictAndStatusScopes($query, $districtIds, $approvedOnly);
        self::applyMarketLinkagePartnerPeriodScope($query, $periodFrom, $periodTo);

        $map = [];
        foreach ($query->get() as $row) {
            $key = (string) ($row->incubatee_key ?? '');
            if ($key === '') {
                continue;
            }

            $label = self::linkageModeLabelFromPartnerMode((string) ($row->linkage_mode ?? ''));
            if ($label === '') {
                continue;
            }

            if (! isset($map[$key])) {
                $map[$key] = [];
            }

            if (! in_array($label, $map[$key], true)) {
                $map[$key][] = $label;
            }
        }

        return $map;
    }

    /**
     * @param  list<int>|null  $districtIds
     * @return array<string, list<string>>
     */
    private static function orphanServiceCaseIncubateeModeMap(?array $districtIds, bool $approvedOnly, ?Carbon $periodFrom = null, ?Carbon $periodTo = null): array
    {
        $serviceIds = self::marketLinkServiceIds();
        if ($serviceIds === [] || ! Schema::hasTable('service_cases')) {
            return [];
        }

        $query = DB::table('service_cases as sc')
            ->leftJoin('cfa_submissions as cs', 'cs.id', '=', 'sc.cfa_submission_id')
            ->whereIn('sc.service_id', $serviceIds)
            ->where(function ($q): void {
                $q->whereNotNull('sc.cfa_submission_id')
                    ->orWhereNotNull('sc.legacy_application_id');
            })
            ->select('sc.cfa_submission_id', 'sc.legacy_application_id', 'sc.payload');

        if ($approvedOnly) {
            $query->where('sc.status', ServiceCase::STATUS_APPROVED);
        }

        if ($districtIds !== null) {
            app(LegacyApplicationServiceCaseSupport::class)
                ->applyAchievementDistrictScopeToServiceCaseQuery($query, $districtIds);
        }

        self::applyOrphanServiceCasePeriodScope($query, $periodFrom, $periodTo);
        self::applyOrphanExclusionAgainstMarketLinkage($query, $districtIds, $approvedOnly, $periodFrom, $periodTo);

        $map = [];
        foreach ($query->get() as $row) {
            $key = self::incubateeKeyFromIds(
                isset($row->cfa_submission_id) ? (int) $row->cfa_submission_id : null,
                isset($row->legacy_application_id) ? (int) $row->legacy_application_id : null,
            );
            if ($key === null) {
                continue;
            }

            $payload = is_string($row->payload) ? json_decode($row->payload, true) : [];
            $payload = is_array($payload) ? $payload : [];
            $modes = self::linkageModeLabelsFromServiceCasePayload($payload);
            if ($modes === []) {
                continue;
            }

            $map[$key] = $modes;
        }

        return $map;
    }

    /**
     * Orphan (legacy service-case) market linkages aggregated to one entry per incubatee.
     *
     * @param  list<int>|null  $districtIds
     * @return array<string, array{reference: string, applicant: string, district: string, hub: string, partners: list<string>, modes: list<string>, latest_date: ?string}>
     */
    private static function orphanServiceCaseIncubateeAggregates(?array $districtIds, bool $approvedOnly, ?Carbon $periodFrom = null, ?Carbon $periodTo = null): array
    {
        $serviceIds = self::marketLinkServiceIds();
        if ($serviceIds === [] || ! Schema::hasTable('service_cases')) {
            return [];
        }

        $query = DB::table('service_cases as sc')
            ->leftJoin('cfa_submissions as cs', 'cs.id', '=', 'sc.cfa_submission_id')
            ->leftJoin('districts as d', 'd.id', '=', 'cs.district_id')
            ->leftJoin('hubs as h', 'h.id', '=', 'd.hub_id')
            ->whereIn('sc.service_id', $serviceIds)
            ->where(function ($q): void {
                $q->whereNotNull('sc.cfa_submission_id')
                    ->orWhereNotNull('sc.legacy_application_id');
            })
            ->select(
                'sc.cfa_submission_id',
                'sc.legacy_application_id',
                'sc.payload',
                'sc.approved_at',
                'sc.created_at',
                'cs.application_no',
                'cs.applicant_name',
                'd.name as district_name',
                'h.name as hub_name',
            );

        if ($approvedOnly) {
            $query->where('sc.status', ServiceCase::STATUS_APPROVED);
        }

        if ($districtIds !== null) {
            app(LegacyApplicationServiceCaseSupport::class)
                ->applyAchievementDistrictScopeToServiceCaseQuery($query, $districtIds);
        }

        self::applyOrphanServiceCasePeriodScope($query, $periodFrom, $periodTo);
        self::applyOrphanExclusionAgainstMarketLinkage($query, $districtIds, $approvedOnly, $periodFrom, $periodTo);

        $aggregates = [];
        foreach ($query->orderBy('cs.applicant_name')->orderBy('sc.id')->get() as $row) {
            $key = self::incubateeKeyFromIds(
                isset($row->cfa_submission_id) ? (int) $row->cfa_submission_id : null,
                isset($row->legacy_application_id) ? (int) $row->legacy_application_id : null,
            );
            if ($key === null) {
                continue;
            }

            $payload = is_string($row->payload) ? json_decode($row->payload, true) : [];
            $payload = is_array($payload) ? $payload : [];
            $modes = self::linkageModeLabelsFromServiceCasePayload($payload);
            if ($modes === []) {
                continue;
            }

            if (! isset($aggregates[$key])) {
                $aggregates[$key] = self::newIncubateeAggregate(
                    (string) ($row->application_no ?? '—'),
                    (string) ($row->applicant_name ?: '—'),
                    (string) ($row->district_name ?? ''),
                    (string) ($row->hub_name ?? ''),
                );
            }

            $dateRaw = $row->approved_at ?? $row->created_at;
            self::pushIncubateeAggregate(
                $aggregates[$key],
                trim((string) ($payload['p'] ?? '')),
                $modes,
                $dateRaw ? Carbon::parse((string) $dateRaw)->toDateString() : null,
            );
        }

        return $aggregates;
    }

    /**
     * @return array{reference: string, applicant: string, district: string, hub: string, partners: list<string>, modes: list<string>, latest_date: ?string}
     */
    private static function newIncubateeAggregate(string $reference, string $applicant, string $district, string $hub): array
    {
        return [
            'reference' => $reference !== '' ? $reference : '—',
            'applicant' => $applicant !== '' ? $applicant : '—',
            'district' => $district,
            'hub' => $hub,
            'partners' => [],
            'modes' => [],
            'latest_date' => null,
        ];
    }

    /**
     * @param  array{reference: string, applicant: string, district: string, hub: string, partners: list<string>, modes: list<string>, latest_date: ?string}  $aggregate
     * @param  list<string>  $modeLabels
     */
    private static function pushIncubateeAggregate(array &$aggregate, string $partnerName, array $modeLabels, ?string $dateRaw): void
    {
        $partnerName = trim($partnerName);
        if ($partnerName !== '' && ! in_array($partnerName, $aggregate['partners'], true)) {
            $aggregate['partners'][] = $partnerName;
        }

        foreach ($modeLabels as $label) {
            if ($label !== '' && ! in_array($label, $aggregate['modes'], true)) {
                $aggregate['modes'][] = $label;
            }
        }

        if ($dateRaw !== null && ($aggregate['latest_date'] === null || $dateRaw > $aggregate['latest_date'])) {
            $aggregate['latest_date'] = $dateRaw;
        }
    }

    /**
     * @param  array{reference: string, applicant: string, district: string, hub: string, partners: list<string>, modes: list<string>, latest_date: ?string}  $aggregate
     * @return array{reference: string, applicant: string, service: string, linkage_mode: string, date: string, district: string, hub: string}
     */
    private static function formatIncubateeRecord(array $aggregate): array
    {
        // Present modes in a stable order (Offline, Online) regardless of insertion order.
        $modes = [];
        foreach (['Offline', 'Online'] as $mode) {
            if (in_array($mode, $aggregate['modes'], true)) {
                $modes[] = $mode;
            }
        }

        return [
            'reference' => $aggregate['reference'] !== '' ? $aggregate['reference'] : '—',
            'applicant' => $aggregate['applicant'] !== '' ? $aggregate['applicant'] : '—',
            'service' => $aggregate['partners'] !== [] ? implode(', ', $aggregate['partners']) : '—',
            'linkage_mode' => $modes !== [] ? implode(', ', $modes) : '—',
            'date' => $aggregate['latest_date']
                ? Carbon::parse($aggregate['latest_date'])->format('d M Y')
                : '—',
            'district' => $aggregate['district'],
            'hub' => $aggregate['hub'],
        ];
    }

    /**
     * @param  \Illuminate\Database\Query\Builder  $query
     * @param  list<int>|null  $districtIds
     */
    private static function applyOrphanExclusionAgainstMarketLinkage($query, ?array $districtIds, bool $approvedOnly, ?Carbon $periodFrom = null, ?Carbon $periodTo = null): void
    {
        if (! Schema::hasTable('market_linkage_submissions')) {
            return;
        }

        $query->whereNotExists(function ($sub) use ($districtIds, $approvedOnly, $periodFrom, $periodTo): void {
            $sub->from('market_linkage_submissions as mls')
                ->where(function ($match): void {
                    $match->where(function ($cfa): void {
                        $cfa->whereColumn('mls.cfa_submission_id', 'sc.cfa_submission_id')
                            ->whereNotNull('sc.cfa_submission_id');
                    })->orWhere(function ($legacy): void {
                        $legacy->whereColumn('mls.legacy_application_id', 'sc.legacy_application_id')
                            ->whereNotNull('sc.legacy_application_id');
                    });
                });

            self::applyMarketLinkageDistrictAndStatusScopes($sub, $districtIds, $approvedOnly);

            // When a period is active, only treat an incubatee as "already linked" if the
            // market linkage falls within the same window — so an in-period orphan service
            // case is not wrongly excluded by an out-of-period market linkage.
            if ($periodFrom && $periodTo && Schema::hasTable('market_linkage_partners')) {
                $sub->join('market_linkage_partners as mlp', 'mlp.market_linkage_submission_id', '=', 'mls.id');
                self::applyMarketLinkagePartnerPeriodScope($sub, $periodFrom, $periodTo);
            }
        });
    }

    /**
     * @param  \Illuminate\Database\Query\Builder  $query
     * @param  list<int>|null  $districtIds
     */
    private static function applyMarketLinkageDistrictAndStatusScopes($query, ?array $districtIds, bool $approvedOnly): void
    {
        if ($approvedOnly && MarketLinkageSubmission::supportsWorkflow()) {
            $query->where('mls.status', ServiceCase::STATUS_APPROVED);
        }

        if ($districtIds !== null) {
            $query->whereIn('mls.district_id', $districtIds);
        }
    }

    /**
     * Approved orphan market-link service cases (no matching Market Linkage module record),
     * shaped for the market-linkage dashboard list (one group per incubatee).
     *
     * @param  list<int>|null  $districtIds  null = statewide
     * @param  array{q?: string, from?: string, to?: string, linkage_mode?: string}  $filters
     * @return list<array{
     *   key: string,
     *   incubatee_name: string,
     *   application_no: ?string,
     *   district_id: int,
     *   district_name: ?string,
     *   cfa_submission_id: ?int,
     *   legacy_application_id: ?int,
     *   service_case_id: int,
     *   partner_count: int,
     *   submission_count: int,
     *   last_recorded_at: ?Carbon,
     *   partners: list<array<string, mixed>>,
     *   source: string
     * }>
     */
    public static function orphanDashboardGroups(?array $districtIds, array $filters = []): array
    {
        if ($districtIds === []) {
            return [];
        }

        $serviceIds = self::marketLinkServiceIds();
        if ($serviceIds === [] || ! Schema::hasTable('service_cases')) {
            return [];
        }

        $periodFrom = ! empty($filters['from']) ? Carbon::parse((string) $filters['from'])->startOfDay() : null;
        $periodTo = ! empty($filters['to']) ? Carbon::parse((string) $filters['to'])->endOfDay() : null;
        $q = trim((string) ($filters['q'] ?? ''));
        $linkageMode = strtolower(trim((string) ($filters['linkage_mode'] ?? '')));

        $query = DB::table('service_cases as sc')
            ->leftJoin('cfa_submissions as cs', 'cs.id', '=', 'sc.cfa_submission_id')
            ->leftJoin('districts as d', 'd.id', '=', 'cs.district_id')
            ->whereIn('sc.service_id', $serviceIds)
            ->where('sc.status', ServiceCase::STATUS_APPROVED)
            ->where(function ($w): void {
                $w->whereNotNull('sc.cfa_submission_id')
                    ->orWhereNotNull('sc.legacy_application_id');
            })
            ->select(
                'sc.id as service_case_id',
                'sc.cfa_submission_id',
                'sc.legacy_application_id',
                'sc.payload',
                'sc.approved_at',
                'sc.created_at',
                'sc.submitted_at',
                'cs.application_no',
                'cs.applicant_name',
                'cs.district_id as cfa_district_id',
                'd.name as district_name',
            );

        if ($districtIds !== null) {
            app(LegacyApplicationServiceCaseSupport::class)
                ->applyAchievementDistrictScopeToServiceCaseQuery($query, $districtIds);
        }

        self::applyOrphanServiceCasePeriodScope($query, $periodFrom, $periodTo);
        self::applyOrphanExclusionAgainstMarketLinkage($query, $districtIds, true, $periodFrom, $periodTo);

        if ($q !== '') {
            $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q).'%';
            $query->where(function ($w) use ($like): void {
                $w->where('cs.applicant_name', 'like', $like)
                    ->orWhere('cs.application_no', 'like', $like)
                    ->orWhere('sc.reference_number', 'like', $like)
                    ->orWhere('sc.payload', 'like', $like);
            });
        }

        $groups = [];
        foreach ($query->orderByDesc('sc.approved_at')->orderByDesc('sc.id')->get() as $row) {
            $key = self::incubateeKeyFromIds(
                isset($row->cfa_submission_id) ? (int) $row->cfa_submission_id : null,
                isset($row->legacy_application_id) ? (int) $row->legacy_application_id : null,
            );
            if ($key === null) {
                continue;
            }

            $payload = is_string($row->payload) ? json_decode($row->payload, true) : [];
            $payload = is_array($payload) ? $payload : [];
            $modes = self::linkageModeLabelsFromServiceCasePayload($payload);
            if ($modes === []) {
                continue;
            }

            if ($linkageMode !== '') {
                $wanted = match ($linkageMode) {
                    MarketLinkageSubmission::LINKAGE_ONLINE, 'online' => 'Online',
                    MarketLinkageSubmission::LINKAGE_OFFLINE, 'offline' => 'Offline',
                    default => '',
                };
                if ($wanted !== '' && ! in_array($wanted, $modes, true)) {
                    continue;
                }
            }

            $partnerName = trim((string) ($payload['p'] ?? ''));
            if ($partnerName === '') {
                $partnerName = '—';
            }

            $recorded = $row->approved_at ?? $row->submitted_at ?? $row->created_at;
            $recordedAt = $recorded ? Carbon::parse((string) $recorded) : null;
            // One partner detail row per mode so online/offline filters and stats stay accurate.
            $partnerRows = [];
            foreach ($modes as $modeLabel) {
                $modeCode = $modeLabel === 'Online'
                    ? MarketLinkageSubmission::LINKAGE_ONLINE
                    : MarketLinkageSubmission::LINKAGE_OFFLINE;
                if ($linkageMode !== ''
                    && $linkageMode !== $modeCode
                    && strtolower($linkageMode) !== strtolower($modeLabel)
                ) {
                    continue;
                }
                $partnerRows[] = [
                    'partner_name' => $partnerName,
                    'linkage_mode' => $modeCode,
                    'linkage_mode_label' => $modeLabel,
                    'linkage_date' => $recordedAt?->toDateString() ?? '',
                    'linkage_date_display' => $recordedAt?->format('d M Y') ?? '',
                    'link_url' => null,
                    'link_href' => null,
                    'has_document' => false,
                    'document_url' => null,
                    'recorded_at' => $recordedAt?->timezone(config('app.timezone'))->format('d M Y H:i') ?? '',
                    'recorded_by' => 'Service case',
                    'submission_id' => null,
                    'show_url' => null,
                    'service_case_id' => (int) $row->service_case_id,
                ];
            }

            if ($partnerRows === []) {
                continue;
            }

            if (! isset($groups[$key])) {
                $groups[$key] = [
                    'key' => $key,
                    'incubatee_name' => (string) ($row->applicant_name ?: '—'),
                    'application_no' => $row->application_no ? (string) $row->application_no : null,
                    'district_id' => (int) ($row->cfa_district_id ?? 0),
                    'district_name' => $row->district_name ? (string) $row->district_name : null,
                    'cfa_submission_id' => $row->cfa_submission_id ? (int) $row->cfa_submission_id : null,
                    'legacy_application_id' => $row->legacy_application_id ? (int) $row->legacy_application_id : null,
                    'service_case_id' => (int) $row->service_case_id,
                    'partner_count' => 0,
                    'submission_count' => 1,
                    'last_recorded_at' => $recordedAt,
                    'partners' => [],
                    'source' => 'service_case',
                ];
            }

            foreach ($partnerRows as $partnerRow) {
                $groups[$key]['partners'][] = $partnerRow;
                $groups[$key]['partner_count']++;
            }

            if ($recordedAt && (
                $groups[$key]['last_recorded_at'] === null
                || $recordedAt->gt($groups[$key]['last_recorded_at'])
            )) {
                $groups[$key]['last_recorded_at'] = $recordedAt;
            }
        }

        // Resolve legacy district when CFA district is missing.
        $legacySupport = app(LegacyApplicationServiceCaseSupport::class);
        foreach ($groups as &$group) {
            if (($group['district_id'] ?? 0) > 0) {
                continue;
            }
            $legacyId = (int) ($group['legacy_application_id'] ?? 0);
            if ($legacyId < 1) {
                continue;
            }
            $laravelDistrictId = $legacySupport->laravelDistrictIdForLegacyApplication($legacyId);
            if ($laravelDistrictId !== null && $laravelDistrictId > 0) {
                $group['district_id'] = $laravelDistrictId;
                if (($group['district_name'] ?? null) === null || $group['district_name'] === '') {
                    $group['district_name'] = District::query()->where('id', $laravelDistrictId)->value('name');
                }
            }
        }
        unset($group);

        return array_values($groups);
    }

    /**
     * Restrict market linkage partner rows to a date window (no-op when either bound is null).
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     */
    private static function applyMarketLinkagePartnerPeriodScope($query, ?Carbon $periodFrom, ?Carbon $periodTo, string $column = 'mlp.linkage_date'): void
    {
        if (! $periodFrom || ! $periodTo) {
            return;
        }

        $query->whereBetween($column, [$periodFrom->toDateString(), $periodTo->toDateString()]);
    }

    /**
     * Restrict orphan service cases to a date window by approved/created date (no-op when null).
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     */
    private static function applyOrphanServiceCasePeriodScope($query, ?Carbon $periodFrom, ?Carbon $periodTo): void
    {
        if (! $periodFrom || ! $periodTo) {
            return;
        }

        $query->whereBetween(
            DB::raw('COALESCE(sc.approved_at, sc.created_at)'),
            [$periodFrom->toDateTimeString(), $periodTo->toDateTimeString()],
        );
    }

    /**
     * @param  array<string, list<string>>  $modeMap
     * @return array{offline_incubatees: int, online_incubatees: int, total_incubatees: int}
     */
    private static function countIncubateeModeMap(array $modeMap): array
    {
        $offline = 0;
        $online = 0;

        foreach ($modeMap as $modes) {
            if (in_array('Offline', $modes, true)) {
                $offline++;
            }
            if (in_array('Online', $modes, true)) {
                $online++;
            }
        }

        return [
            'offline_incubatees' => $offline,
            'online_incubatees' => $online,
            'total_incubatees' => count($modeMap),
        ];
    }

    private static function normalizeLinkageModeLabel(?string $mode): string
    {
        $value = strtolower(trim((string) $mode));

        return match ($value) {
            MarketLinkageSubmission::LINKAGE_OFFLINE, 'offline' => 'Offline',
            MarketLinkageSubmission::LINKAGE_ONLINE, 'online' => 'Online',
            default => '',
        };
    }
}
