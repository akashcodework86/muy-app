<?php

namespace App\Support;

use App\Models\Deliverable;
use App\Models\District;
use App\Models\Service;
use App\Models\ServiceCase;
use App\Services\LegacyApplicationServiceCaseSupport;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class MarketingPartnerOnboardedCombinedDeliverablesSupport
{
    public const LABEL_PARTNER_OUTREACH = 'Partner outreach (state team)';

    public const LABEL_SERVICES = 'Service cases (district staff)';

    public static function combinedCount(?Carbon $periodFrom, ?Carbon $periodTo, ?array $districtIds = null): int
    {
        return MarketingPartnerOutreachDeliverablesSupport::countOnboarded($periodFrom, $periodTo)
            + self::serviceCasesCount($periodFrom, $periodTo, $districtIds);
    }

    /**
     * @param  list<int>|null  $districtIds
     * @return array<string, mixed>
     */
    public static function combinedBreakdown(?Carbon $periodFrom, ?Carbon $periodTo, ?array $districtIds = null): array
    {
        return self::combinedBreakdownFromParts(
            self::serviceCasesBreakdown($periodFrom, $periodTo, $districtIds),
            MarketingPartnerOutreachDeliverablesSupport::onboardedBreakdown($periodFrom, $periodTo),
        );
    }

    /**
     * @param  array<string, mixed>  $service
     * @param  array<string, mixed>  $outreach
     * @return array<string, mixed>
     */
    public static function combinedBreakdownFromParts(array $service, array $outreach): array
    {
        $servicesTotal = (int) ($service['total'] ?? 0);
        $outreachTotal = (int) ($outreach['total'] ?? 0);
        $total = $servicesTotal + $outreachTotal;

        $serviceRecords = array_map(static function (array $row): array {
            $row['channel'] = self::LABEL_SERVICES;
            $row['filled_by'] = trim((string) ($row['spoc'] ?? $row['filled_by'] ?? '')) !== '' && ($row['spoc'] ?? '') !== '—'
                ? (string) ($row['spoc'] ?? $row['filled_by'])
                : self::LABEL_SERVICES;
            $row['service'] = self::LABEL_SERVICES;
            $row['status'] = self::LABEL_SERVICES;

            return $row;
        }, $service['records'] ?? []);

        $outreachRecords = array_map(static function (array $row): array {
            $row['channel'] = self::LABEL_PARTNER_OUTREACH;
            $row['filled_by'] = trim((string) ($row['spoc'] ?? $row['filled_by'] ?? '')) !== '' && ($row['spoc'] ?? '') !== '—'
                ? (string) ($row['spoc'] ?? $row['filled_by'])
                : self::LABEL_PARTNER_OUTREACH;
            $row['service'] = self::LABEL_PARTNER_OUTREACH;
            $row['status'] = (string) ($row['status'] ?? self::LABEL_PARTNER_OUTREACH);

            return $row;
        }, $outreach['records'] ?? []);

        $records = array_merge($serviceRecords, $outreachRecords);
        usort($records, static function (array $a, array $b): int {
            return strcmp((string) ($b['_sort_date'] ?? $b['date'] ?? ''), (string) ($a['_sort_date'] ?? $a['date'] ?? ''));
        });
        $records = array_map(static function (array $row): array {
            unset($row['_sort_date']);

            return $row;
        }, $records);

        return [
            'total' => $total,
            'services_total' => $servicesTotal,
            'partner_outreach_total' => $outreachTotal,
            'by_district' => self::mergeDistrictRows($service['by_district'] ?? [], $outreach['by_district'] ?? [], $total),
            'by_hub' => self::mergeHubRows($service['by_hub'] ?? [], $outreach['by_hub'] ?? [], $total),
            'by_month' => self::mergeMonthRows($service['by_month'] ?? [], $outreach['by_month'] ?? [], $total),
            'by_service' => self::channelBifurcationRows($servicesTotal, $outreachTotal, $total),
            'records' => $records,
        ];
    }

    /**
     * @param  list<int>|null  $districtIds
     */
    public static function serviceCasesCount(?Carbon $periodFrom, ?Carbon $periodTo, ?array $districtIds = null): int
    {
        if ($districtIds === []) {
            return 0;
        }

        $serviceIds = self::onboardedServiceIds();
        if ($serviceIds === []) {
            return 0;
        }

        $query = self::serviceCasesBaseQuery($serviceIds);
        self::applyDistrictScope($query, $districtIds);
        self::applyPeriodScope($query, $periodFrom, $periodTo);

        return (int) $query->count();
    }

    /**
     * @return list<int>
     */
    public static function onboardedServiceIds(): array
    {
        $ids = array_values(array_unique(array_filter(array_map(
            'intval',
            (array) config('partner_outreach.onboarded_service_ids', []),
        ))));

        if (! Schema::hasTable('services')) {
            return $ids;
        }

        $candidateCodes = array_values(array_unique(array_filter(array_map(
            static fn (string $code): string => strtolower(trim($code)),
            array_merge(
                ['marketing_partners_onboarded', 'marketing_partners_onboarded_through_lo_a_lo_i_mo_u'],
                (array) config('program_deliverables.target_code_aliases.marketing_partners_onboarded', []),
            ),
        ))));

        $deliverableIds = [];
        if (Schema::hasTable('deliverables') && $candidateCodes !== []) {
            $deliverableIds = Deliverable::query()
                ->whereIn('code', $candidateCodes)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();

            $linkedDeliverableIds = Service::query()
                ->where(function ($query) use ($candidateCodes): void {
                    foreach ($candidateCodes as $candidate) {
                        $query->orWhere('code', $candidate);
                    }
                    $query->orWhereHas('deliverable', function ($dq) use ($candidateCodes): void {
                        $dq->whereIn('code', $candidateCodes);
                    });
                })
                ->pluck('deliverable_id')
                ->filter()
                ->map(fn ($id) => (int) $id)
                ->all();

            $deliverableIds = array_values(array_unique(array_merge($deliverableIds, $linkedDeliverableIds)));
        }

        $keywords = (array) config('program_deliverables.achievement_deliverable_keywords.marketing_partners_onboarded', [
            'marketing partners onboarded',
            'loa/loi/mou',
        ]);

        $resolved = Service::query()
            ->where(function ($query) use ($candidateCodes, $deliverableIds, $keywords): void {
                if ($candidateCodes !== []) {
                    foreach ($candidateCodes as $candidate) {
                        $query->orWhere('code', $candidate);
                    }
                }
                if ($deliverableIds !== []) {
                    $query->orWhereIn('deliverable_id', $deliverableIds);
                }
                foreach ($keywords as $keyword) {
                    $keyword = trim((string) $keyword);
                    if ($keyword !== '') {
                        $query->orWhere('name', 'like', '%'.$keyword.'%');
                    }
                }
            })
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return array_values(array_unique(array_merge($ids, $resolved)));
    }

    /**
     * @param  list<int>|null  $districtIds
     * @return array<string, mixed>
     */
    private static function serviceCasesBreakdown(?Carbon $periodFrom, ?Carbon $periodTo, ?array $districtIds): array
    {
        if ($districtIds === []) {
            return ['total' => 0, 'by_district' => [], 'by_hub' => [], 'by_month' => [], 'records' => []];
        }

        $serviceIds = self::onboardedServiceIds();
        if ($serviceIds === []) {
            return ['total' => 0, 'by_district' => [], 'by_hub' => [], 'by_month' => [], 'records' => []];
        }

        $dateExpr = self::achievementDateExpression();
        $monthExpr = self::monthKeySql($dateExpr);
        $legacySupport = app(LegacyApplicationServiceCaseSupport::class);

        $query = DB::table('service_cases as sc')
            ->join('services as s', 's.id', '=', 'sc.service_id')
            ->leftJoin('cfa_submissions as cs', 'cs.id', '=', 'sc.cfa_submission_id')
            ->leftJoin('districts as d', 'd.id', '=', 'cs.district_id')
            ->leftJoin('hubs as h', 'h.id', '=', 'd.hub_id')
            ->leftJoin('users as submitter', 'submitter.id', '=', 'sc.submitted_by')
            ->whereIn('sc.service_id', $serviceIds)
            ->whereIn('sc.status', [ServiceCase::STATUS_APPROVED, ServiceCase::STATUS_COMPLETED]);

        self::applyDistrictScope($query, $districtIds);
        self::applyPeriodScope($query, $periodFrom, $periodTo, $dateExpr);

        $cases = (clone $query)
            ->select([
                'sc.id',
                'sc.legacy_application_id',
                'sc.reference_number',
                'sc.status',
                'cs.applicant_name',
                'cs.application_no',
                'd.name as district_name',
                'h.name as hub_name',
                's.name as service_name',
                'submitter.name as submitted_by_name',
            ])
            ->selectRaw("{$dateExpr} as achievement_date")
            ->selectRaw("{$monthExpr} as month_key")
            ->orderByDesc(DB::raw($dateExpr))
            ->orderByDesc('sc.id')
            ->get();

        if ($cases->isEmpty()) {
            return ['total' => 0, 'by_district' => [], 'by_hub' => [], 'by_month' => [], 'records' => []];
        }

        $legacyApplicationIds = $cases
            ->pluck('legacy_application_id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();

        $legacySnapshots = $legacySupport->applicantSnapshotsByLegacyApplicationIds($legacyApplicationIds);
        $legacyDistrictCache = [];

        $groupRows = [];
        $records = [];

        foreach ($cases as $row) {
            $districtName = trim((string) ($row->district_name ?? ''));
            $hubName = trim((string) ($row->hub_name ?? ''));

            $legacyApplicationId = (int) ($row->legacy_application_id ?? 0);
            if ($districtName === '' && $legacyApplicationId > 0) {
                $resolved = self::districtForLegacyApplication($legacyApplicationId, $legacySupport, $legacyDistrictCache);
                $districtName = (string) ($resolved?->name ?? '');
                $hubName = (string) ($resolved?->hub?->name ?? '');
            }

            $districtName = $districtName !== '' ? $districtName : '—';
            $hubName = $hubName !== '' ? $hubName : '—';

            $monthKey = trim((string) ($row->month_key ?? ''));
            $bucketKey = implode('|', [$districtName, $hubName, $monthKey]);
            if (! isset($groupRows[$bucketKey])) {
                $groupRows[$bucketKey] = (object) [
                    'district_name' => $districtName,
                    'hub_name' => $hubName,
                    'month_key' => $monthKey,
                    'total' => 0,
                ];
            }
            $groupRows[$bucketKey]->total++;

            $snapshot = $legacySnapshots[$legacyApplicationId] ?? null;
            $applicant = trim((string) ($row->applicant_name ?? ''));
            if ($applicant === '' && is_array($snapshot)) {
                $applicant = trim((string) ($snapshot['name'] ?? ''));
            }

            $applicationNo = trim((string) ($row->application_no ?? ''));
            if ($applicationNo === '' && is_array($snapshot)) {
                $applicationNo = trim((string) ($snapshot['application_no'] ?? ''));
            }

            $reference = trim((string) ($row->reference_number ?? ''));
            if ($reference === '') {
                $reference = $applicationNo !== '' ? $applicationNo : '—';
            }

            $date = trim((string) ($row->achievement_date ?? ''));
            $displayDate = '—';
            if ($date !== '') {
                try {
                    $displayDate = Carbon::parse($date)->format('d M Y');
                } catch (\Throwable) {
                    $displayDate = $date;
                }
            }

            $records[] = [
                'id' => (int) ($row->id ?? 0),
                'reference' => $reference,
                'applicant' => $applicant !== '' ? $applicant : '—',
                'district' => $districtName,
                'hub' => $hubName,
                'service' => (string) ($row->service_name ?: 'Marketing partner onboarded'),
                'status' => self::LABEL_SERVICES,
                'date' => $displayDate,
                '_sort_date' => $date,
                'filled_by' => trim((string) ($row->submitted_by_name ?? '')) !== ''
                    ? (string) $row->submitted_by_name
                    : self::LABEL_SERVICES,
                'spoc' => trim((string) ($row->submitted_by_name ?? '')) !== ''
                    ? (string) $row->submitted_by_name
                    : '—',
            ];
        }

        return self::aggregateSimpleRows(collect(array_values($groupRows)), $records);
    }

    /**
     * @param  array<int, District|null>  $cache
     */
    private static function districtForLegacyApplication(
        int $legacyApplicationId,
        LegacyApplicationServiceCaseSupport $legacySupport,
        array &$cache,
    ): ?District {
        if (array_key_exists($legacyApplicationId, $cache)) {
            return $cache[$legacyApplicationId];
        }

        $preview = $legacySupport->incubateePreview($legacyApplicationId);
        $districtId = $preview !== null
            ? $legacySupport->laravelDistrictIdForLegacyDistrictName((string) ($preview['district'] ?? ''))
            : null;

        $district = $districtId !== null
            ? District::query()->with('hub')->find($districtId)
            : null;

        return $cache[$legacyApplicationId] = $district;
    }

    /**
     * @param  list<int>  $serviceIds
     */
    private static function serviceCasesBaseQuery(array $serviceIds)
    {
        return DB::table('service_cases as sc')
            ->join('services as s', 's.id', '=', 'sc.service_id')
            ->leftJoin('cfa_submissions as cs', 'cs.id', '=', 'sc.cfa_submission_id')
            ->whereIn('sc.service_id', $serviceIds)
            ->whereIn('sc.status', [ServiceCase::STATUS_APPROVED, ServiceCase::STATUS_COMPLETED]);
    }

    /**
     * @param  list<int>|null  $districtIds
     */
    private static function applyDistrictScope($query, ?array $districtIds, string $column = 'cs.district_id'): void
    {
        if ($districtIds === null) {
            return;
        }

        if ($districtIds === []) {
            $query->whereRaw('0 = 1');

            return;
        }

        app(LegacyApplicationServiceCaseSupport::class)
            ->applyAchievementDistrictScopeToServiceCaseQuery($query, $districtIds);
    }

    private static function applyPeriodScope($query, ?Carbon $periodFrom, ?Carbon $periodTo, ?string $dateExpr = null): void
    {
        $dateExpr ??= self::achievementDateExpression();

        if ($periodFrom && $periodTo) {
            $query->whereBetween(DB::raw($dateExpr), [
                $periodFrom->copy()->startOfDay()->toDateTimeString(),
                $periodTo->copy()->endOfDay()->toDateTimeString(),
            ]);
        }
    }

    private static function achievementDateExpression(): string
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

    private static function monthKeySql(string $columnExpression): string
    {
        return match (DB::connection()->getDriverName()) {
            'sqlite' => "strftime('%Y-%m', {$columnExpression})",
            'pgsql' => "to_char({$columnExpression}, 'YYYY-MM')",
            default => "DATE_FORMAT({$columnExpression}, '%Y-%m')",
        };
    }

    /**
     * @param  \Illuminate\Support\Collection<int, object>  $groupRows
     * @param  list<array<string, mixed>>  $records
     * @return array<string, mixed>
     */
    private static function aggregateSimpleRows($groupRows, array $records): array
    {
        $total = 0;
        $byDistrict = [];
        $byHub = [];
        $byMonth = [];

        foreach ($groupRows as $row) {
            $count = (int) ($row->total ?? 0);
            $total += $count;

            $district = trim((string) ($row->district_name ?? '')) ?: '—';
            $hub = trim((string) ($row->hub_name ?? '')) ?: '—';
            $monthKey = trim((string) ($row->month_key ?? ''));

            $byDistrict[$district] = ($byDistrict[$district] ?? 0) + $count;
            if ($hub !== '—') {
                $byHub[$hub] = ($byHub[$hub] ?? 0) + $count;
            }
            if ($monthKey !== '') {
                $byMonth[$monthKey] = ($byMonth[$monthKey] ?? 0) + $count;
            }
        }

        return [
            'total' => $total,
            'by_district' => self::formatDistrictRows($byDistrict, $total),
            'by_hub' => self::formatHubRows($byHub, $total),
            'by_month' => self::formatMonthRows($byMonth, $total),
            'records' => $records,
        ];
    }

    /**
     * @param  array<string, int>  $counts
     * @return list<array{district: string, hub: string, count: int, share_pct: int}>
     */
    private static function formatDistrictRows(array $counts, int $total): array
    {
        $rows = [];
        foreach ($counts as $district => $count) {
            $rows[] = [
                'district' => (string) $district,
                'hub' => '—',
                'count' => (int) $count,
                'share_pct' => $total > 0 ? (int) round(($count / $total) * 100) : 0,
            ];
        }
        usort($rows, fn (array $a, array $b): int => $b['count'] <=> $a['count']);

        return $rows;
    }

    /**
     * @param  array<string, int>  $counts
     * @return list<array{hub: string, count: int, share_pct: int}>
     */
    private static function formatHubRows(array $counts, int $total): array
    {
        $rows = [];
        foreach ($counts as $hub => $count) {
            $rows[] = [
                'hub' => (string) $hub,
                'count' => (int) $count,
                'share_pct' => $total > 0 ? (int) round(($count / $total) * 100) : 0,
            ];
        }
        usort($rows, fn (array $a, array $b): int => $b['count'] <=> $a['count']);

        return $rows;
    }

    /**
     * @param  array<string, int>  $counts
     * @return list<array{month: string, month_key: string, count: int, share_pct: int}>
     */
    private static function formatMonthRows(array $counts, int $total): array
    {
        ksort($counts);
        $rows = [];
        foreach ($counts as $monthKey => $count) {
            $label = $monthKey;
            try {
                $label = Carbon::createFromFormat('Y-m', (string) $monthKey)->format('M Y');
            } catch (\Throwable) {
                // keep key
            }
            $rows[] = [
                'month' => $label,
                'month_key' => (string) $monthKey,
                'count' => (int) $count,
                'share_pct' => $total > 0 ? (int) round(($count / $total) * 100) : 0,
            ];
        }

        return $rows;
    }

    /**
     * @param  list<array{district: string, hub: string, count: int, share_pct: int}>  $left
     * @param  list<array{district: string, hub: string, count: int, share_pct: int}>  $right
     * @return list<array{district: string, hub: string, count: int, share_pct: int}>
     */
    private static function mergeDistrictRows(array $left, array $right, int $total): array
    {
        $merged = [];
        foreach (array_merge($left, $right) as $row) {
            $district = (string) ($row['district'] ?? '—');
            $merged[$district] = ($merged[$district] ?? 0) + (int) ($row['count'] ?? 0);
        }

        return self::formatDistrictRows($merged, $total);
    }

    /**
     * @param  list<array{hub: string, count: int, share_pct: int}>  $left
     * @param  list<array{hub: string, count: int, share_pct: int}>  $right
     * @return list<array{hub: string, count: int, share_pct: int}>
     */
    private static function mergeHubRows(array $left, array $right, int $total): array
    {
        $merged = [];
        foreach (array_merge($left, $right) as $row) {
            $hub = (string) ($row['hub'] ?? '—');
            if ($hub === '—') {
                continue;
            }
            $merged[$hub] = ($merged[$hub] ?? 0) + (int) ($row['count'] ?? 0);
        }

        return self::formatHubRows($merged, $total);
    }

    /**
     * @param  list<array{month: string, month_key: string, count: int, share_pct: int}>  $left
     * @param  list<array{month: string, month_key: string, count: int, share_pct: int}>  $right
     * @return list<array{month: string, month_key: string, count: int, share_pct: int}>
     */
    private static function mergeMonthRows(array $left, array $right, int $total): array
    {
        $merged = [];
        foreach (array_merge($left, $right) as $row) {
            $key = (string) ($row['month_key'] ?? '');
            if ($key === '') {
                continue;
            }
            $merged[$key] = ($merged[$key] ?? 0) + (int) ($row['count'] ?? 0);
        }

        return self::formatMonthRows($merged, $total);
    }

    /**
     * @return list<array{service: string, count: int, share_pct: int}>
     */
    private static function channelBifurcationRows(int $servicesTotal, int $outreachTotal, int $total): array
    {
        return [
            [
                'service' => self::LABEL_SERVICES,
                'count' => $servicesTotal,
                'share_pct' => $total > 0 ? (int) round(($servicesTotal / $total) * 100) : 0,
            ],
            [
                'service' => self::LABEL_PARTNER_OUTREACH,
                'count' => $outreachTotal,
                'share_pct' => $total > 0 ? (int) round(($outreachTotal / $total) * 100) : 0,
            ],
        ];
    }
}
