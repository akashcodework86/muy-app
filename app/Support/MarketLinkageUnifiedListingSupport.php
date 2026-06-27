<?php

namespace App\Support;

use App\Models\MarketLinkageSubmission;
use App\Models\Service;
use App\Models\ServiceCase;
use App\Services\LegacyApplicationServiceCaseSupport;
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
     * @param  list<int>|null  $districtIds  null = statewide
     * @return array{offline_incubatees: int, online_incubatees: int, total_incubatees: int}
     */
    public static function approvedIncubateeModeCounts(?array $districtIds, bool $approvedOnly = true): array
    {
        if ($districtIds === []) {
            return ['offline_incubatees' => 0, 'online_incubatees' => 0, 'total_incubatees' => 0];
        }

        return self::countIncubateeModeMap(
            self::buildUnifiedIncubateeModeMap($districtIds, $approvedOnly),
        );
    }

    /**
     * @param  list<int>|null  $districtIds
     * @return list<array{service: string, count: int, share_pct: int}>
     */
    public static function linkageModeBifurcationRows(?array $districtIds, bool $approvedOnly = true): array
    {
        $counts = self::approvedIncubateeModeCounts($districtIds, $approvedOnly);
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
    public static function unifiedApprovedIncubateeRecords(?array $districtIds, bool $approvedOnly = true, int $limit = 500): array
    {
        if ($districtIds === []) {
            return [];
        }

        $records = [];

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

            foreach ($query->orderBy('mls.incubatee_name')->orderBy('mlp.sort_order')->orderBy('mlp.id')->limit($limit)->get() as $row) {
                $records[] = [
                    'reference' => (string) ($row->application_no ?? '—'),
                    'applicant' => (string) ($row->incubatee_name ?: '—'),
                    'service' => (string) ($row->partner_name ?: '—'),
                    'linkage_mode' => self::linkageModeLabelFromPartnerMode((string) ($row->linkage_mode ?? '')),
                    'date' => $row->linkage_date
                        ? \Carbon\Carbon::parse((string) $row->linkage_date)->format('d M Y')
                        : '—',
                    'district' => (string) $row->district_name,
                    'hub' => (string) ($row->hub_name ?? ''),
                ];
            }
        }

        $remaining = max(0, $limit - count($records));
        if ($remaining > 0) {
            foreach (self::orphanServiceCaseRecordRows($districtIds, $approvedOnly, $remaining) as $row) {
                $records[] = $row;
            }
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
    private static function buildUnifiedIncubateeModeMap(?array $districtIds, bool $approvedOnly): array
    {
        $map = self::marketLinkageIncubateeModeMap($districtIds, $approvedOnly);

        foreach (self::orphanServiceCaseIncubateeModeMap($districtIds, $approvedOnly) as $key => $modes) {
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
    private static function marketLinkageIncubateeModeMap(?array $districtIds, bool $approvedOnly): array
    {
        if (! Schema::hasTable('market_linkage_submissions') || ! Schema::hasTable('market_linkage_partners')) {
            return [];
        }

        $keySql = self::INCUBATEE_KEY_SQL;

        $query = DB::table('market_linkage_submissions as mls')
            ->join('market_linkage_partners as mlp', 'mlp.market_linkage_submission_id', '=', 'mls.id')
            ->selectRaw("{$keySql} as incubatee_key, mlp.linkage_mode as linkage_mode");

        self::applyMarketLinkageDistrictAndStatusScopes($query, $districtIds, $approvedOnly);

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
    private static function orphanServiceCaseIncubateeModeMap(?array $districtIds, bool $approvedOnly): array
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

        self::applyOrphanExclusionAgainstMarketLinkage($query, $districtIds, $approvedOnly);

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
    private static function orphanServiceCaseRecordRows(?array $districtIds, bool $approvedOnly, int $limit): array
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

        self::applyOrphanExclusionAgainstMarketLinkage($query, $districtIds, $approvedOnly);

        $records = [];
        foreach ($query->orderBy('cs.applicant_name')->orderBy('sc.id')->limit($limit)->get() as $row) {
            $payload = is_string($row->payload) ? json_decode($row->payload, true) : [];
            $payload = is_array($payload) ? $payload : [];
            $modes = self::linkageModeLabelsFromServiceCasePayload($payload);
            if ($modes === []) {
                continue;
            }

            $dateRaw = $row->approved_at ?? $row->created_at;
            $records[] = [
                'reference' => (string) ($row->application_no ?? '—'),
                'applicant' => (string) ($row->applicant_name ?: '—'),
                'service' => trim((string) ($payload['p'] ?? '')) !== '' ? (string) $payload['p'] : '—',
                'linkage_mode' => implode(', ', $modes),
                'date' => $dateRaw
                    ? \Carbon\Carbon::parse((string) $dateRaw)->format('d M Y')
                    : '—',
                'district' => (string) ($row->district_name ?? ''),
                'hub' => (string) ($row->hub_name ?? ''),
            ];
        }

        return $records;
    }

    /**
     * @param  \Illuminate\Database\Query\Builder  $query
     * @param  list<int>|null  $districtIds
     */
    private static function applyOrphanExclusionAgainstMarketLinkage($query, ?array $districtIds, bool $approvedOnly): void
    {
        if (! Schema::hasTable('market_linkage_submissions')) {
            return;
        }

        $query->whereNotExists(function ($sub) use ($districtIds, $approvedOnly): void {
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
