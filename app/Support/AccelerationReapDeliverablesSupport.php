<?php

namespace App\Support;

use App\Models\District;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Approved acceleration items "Support to MUY Incubatee through REAP" for MIS 8.2.
 *
 * Counted the same way as REAP service cases: one row per item, only approved
 * (not draft, pending, or sent back), dated with the same precedence as service
 * cases (approval timestamp, then the service date, then created_at), and limited
 * to the phase-3 floor and the selected district scope.
 */
final class AccelerationReapDeliverablesSupport
{
    public const SOURCE = 'acceleration';

    public const SOURCE_LABEL = 'REAP from acceleration';

    public const NORMAL_LABEL = 'Normal REAP';

    /**
     * @param  list<int>|null  $districtIds
     */
    public static function countItems(?array $districtIds, ?Carbon $periodFrom, ?Carbon $periodTo): int
    {
        $query = self::scopedQuery($districtIds, $periodFrom, $periodTo);
        if ($query === null) {
            return 0;
        }

        return (int) $query->count();
    }

    /**
     * @param  list<int>|null  $districtIds
     * @return array{
     *     total: int,
     *     by_district: list<array<string, mixed>>,
     *     by_month: list<array<string, mixed>>,
     *     records: list<array<string, mixed>>
     * }
     */
    public static function contribution(?array $districtIds, ?Carbon $periodFrom, ?Carbon $periodTo): array
    {
        $rows = self::fetchRows($districtIds, $periodFrom, $periodTo);
        if ($rows === []) {
            return [
                'total' => 0,
                'by_district' => [],
                'by_month' => [],
                'records' => [],
            ];
        }

        $byDistrict = [];
        $byMonth = [];
        $records = [];

        foreach ($rows as $row) {
            $district = (string) $row['district'];
            $monthKey = (string) $row['month_key'];
            $byDistrict[$district] = ($byDistrict[$district] ?? 0) + 1;
            if ($monthKey !== '') {
                $byMonth[$monthKey] = ($byMonth[$monthKey] ?? 0) + 1;
            }
            $records[] = $row['record'];
        }

        ksort($byMonth);

        $districtRows = [];
        foreach ($byDistrict as $district => $count) {
            $districtRows[] = [
                'district' => $district,
                'hub' => '—',
                'count' => $count,
            ];
        }

        $monthRows = [];
        foreach ($byMonth as $monthKey => $count) {
            try {
                $label = Carbon::createFromFormat('!Y-m', $monthKey)->format('M Y');
            } catch (\Throwable) {
                $label = $monthKey;
            }
            $monthRows[] = [
                'month' => $label,
                'month_key' => $monthKey,
                'count' => $count,
            ];
        }

        return [
            'total' => count($rows),
            'by_district' => $districtRows,
            'by_month' => $monthRows,
            'records' => $records,
        ];
    }

    /**
     * Rows shaped like the phase-3 8.2 detail sheet.
     *
     * @param  list<int>  $districtIds
     * @return list<array<string, mixed>>
     */
    public static function packDetailRows(array $districtIds, Carbon $periodFrom, Carbon $periodTo): array
    {
        $out = [];
        foreach (self::fetchRows($districtIds, $periodFrom, $periodTo) as $row) {
            $out[] = $row['pack'];
        }

        return $out;
    }

    /**
     * @param  list<int>|null  $districtIds
     * @return list<array{district: string, month_key: string, record: array<string, mixed>, pack: array<string, mixed>}>
     */
    private static function fetchRows(?array $districtIds, ?Carbon $periodFrom, ?Carbon $periodTo): array
    {
        $query = self::scopedQuery($districtIds, $periodFrom, $periodTo);
        if ($query === null) {
            return [];
        }

        $dateExpr = self::achievementDateExpression();
        $selected = $query
            ->select([
                'i.id',
                'i.item_key',
                'i.item_label',
                'i.payload',
                's.applicant_name',
                's.application_no',
                's.district_name',
                's.incubatee_key',
                's.status',
            ])
            ->selectRaw($dateExpr.' as achievement_date')
            ->orderByDesc(DB::raw($dateExpr))
            ->orderByDesc('i.id')
            ->get();

        $canonical = self::canonicalDistrictNames();
        $out = [];

        foreach ($selected as $row) {
            $payload = self::payloadArray($row->payload ?? null);
            $rawDistrict = trim((string) ($row->district_name ?? ''));
            $district = $canonical[mb_strtolower($rawDistrict)] ?? ($rawDistrict !== '' ? $rawDistrict : 'Unknown');
            $achievementDate = $row->achievement_date ?? null;
            $monthKey = '';
            $displayDate = '—';
            if ($achievementDate) {
                try {
                    $parsed = Carbon::parse((string) $achievementDate);
                    $monthKey = $parsed->format('Y-m');
                    $displayDate = $parsed->format('d M Y');
                } catch (\Throwable) {
                    $displayDate = (string) $achievementDate;
                }
            }

            $sector = trim((string) ($payload[ConvergenceReapSupport::REAP_SECTOR_KEY] ?? ''));
            $amount = trim((string) ($payload[ConvergenceReapSupport::REAP_AMOUNT_KEY] ?? ''));
            $activity = trim((string) ($payload[ConvergenceReapSupport::REAP_ACTIVITY_KEY] ?? ''));
            $bucketKey = ReapIncubateeTargets::bucketFromPayload(
                $sector !== '' ? $sector : null,
                $amount !== '' ? $amount : null,
            );
            $reference = trim((string) ($row->application_no ?? ''));
            $applicant = trim((string) ($row->applicant_name ?? ''));
            $service = trim((string) ($row->item_label ?? ''));
            if ($service === '') {
                $service = 'Support to MUY Incubatee through REAP';
            }

            $out[] = [
                'district' => $district,
                'month_key' => $monthKey,
                'record' => [
                    'id' => (int) $row->id,
                    'reference' => $reference !== '' ? $reference : '—',
                    'applicant' => $applicant !== '' ? $applicant : '—',
                    'gender' => '',
                    'district' => $district,
                    'hub' => '—',
                    'service' => $service,
                    'spoc' => '—',
                    'status' => 'Approved',
                    'date' => $displayDate,
                    '_sort_date' => $achievementDate ? (string) $achievementDate : '',
                    'reap_source' => self::SOURCE,
                    'reap_source_label' => self::SOURCE_LABEL,
                ],
                'pack' => [
                    'case_id' => (int) $row->id,
                    'cfa_id' => 0,
                    'application_no' => $reference !== '' ? $reference : '—',
                    'applicant' => $applicant !== '' ? $applicant : '—',
                    'district' => $district,
                    'hub' => '—',
                    'service' => $service,
                    'service_code' => (string) ($row->item_key ?? ''),
                    'sector' => ConvergenceReapSupport::reapSectorLabel($sector !== '' ? $sector : null),
                    'amount' => ConvergenceReapSupport::reapAmountLabel($amount !== '' ? $amount : null),
                    'bucket_key' => $bucketKey ?? '',
                    'bucket' => $bucketKey ? ReapIncubateeTargets::bucketLabel($bucketKey) : 'Unbucketed',
                    'activity' => $activity !== '' ? $activity : '—',
                    'status' => 'approved',
                    'date' => $displayDate,
                    'source' => self::SOURCE_LABEL,
                    'incubatee_key' => (string) ($row->incubatee_key ?? ''),
                ],
            ];
        }

        return $out;
    }

    /**
     * @param  list<int>|null  $districtIds
     */
    private static function scopedQuery(?array $districtIds, ?Carbon $periodFrom, ?Carbon $periodTo): ?Builder
    {
        if ($districtIds === [] || ! self::tablesReady()) {
            return null;
        }

        $query = DB::table('acceleration_service_items as i')
            ->join('acceleration_service_sessions as s', 's.id', '=', 'i.session_id');

        self::applyItemKeyScope($query);

        if (Schema::hasColumn('acceleration_service_sessions', 'is_draft')) {
            $query->where(function ($q): void {
                $q->where('s.is_draft', false)->orWhereNull('s.is_draft');
            });
        }

        if (Schema::hasColumn('acceleration_service_sessions', 'status')) {
            $query->where('s.status', AccelerationServicesApproval::STATUS_APPROVED);
        }

        self::applyDistrictScope($query, $districtIds);
        self::applyDateScope($query, self::achievementDateExpression(), $periodFrom, $periodTo);

        return $query;
    }

    private static function tablesReady(): bool
    {
        return Schema::hasTable('acceleration_service_items')
            && Schema::hasTable('acceleration_service_sessions');
    }

    /**
     * @param  Builder  $query
     */
    private static function applyItemKeyScope($query): void
    {
        $query->where(function ($q): void {
            $q->where('i.item_key', 'support_muy_incubatee_reap');
            if (DB::connection()->getDriverName() === 'sqlite') {
                $q->orWhereRaw("i.item_key GLOB 'support_muy_incubatee_reap__*'");
            } else {
                $q->orWhereRaw("i.item_key LIKE 'support\\_muy\\_incubatee\\_reap\\_\\_%'");
            }
        });
    }

    /**
     * @param  Builder  $query
     * @param  list<int>|null  $districtIds
     */
    private static function applyDistrictScope($query, ?array $districtIds): void
    {
        if ($districtIds === null) {
            return;
        }

        $names = District::query()
            ->whereIn('id', $districtIds)
            ->pluck('name')
            ->map(fn ($name) => mb_strtolower(trim((string) $name)))
            ->filter(fn (string $name) => $name !== '')
            ->unique()
            ->values()
            ->all();

        if ($names === []) {
            $query->whereRaw('0 = 1');

            return;
        }

        $placeholders = implode(',', array_fill(0, count($names), '?'));
        $query->whereRaw('LOWER(TRIM(s.district_name)) IN ('.$placeholders.')', $names);
    }

    /**
     * @param  Builder  $query
     */
    private static function applyDateScope($query, string $dateExpr, ?Carbon $periodFrom, ?Carbon $periodTo): void
    {
        $floor = Carbon::parse((string) config('program_deliverables.phase3_floor_date', '2026-04-01'))->startOfDay();
        if ($periodFrom && $periodTo) {
            $from = $periodFrom->copy();
            if ($periodTo->gte($floor) && $from->lt($floor)) {
                $from = $floor->copy();
            }
            $query->whereBetween(DB::raw($dateExpr), [$from->toDateTimeString(), $periodTo->toDateTimeString()]);
        } elseif ($periodTo && $periodTo->gte($floor)) {
            $query->where(DB::raw($dateExpr), '>=', $floor->toDateTimeString());
        }
    }

    /**
     * Same order as service-case achievement dates: approved_at, then the
     * service date, then created_at.
     */
    private static function achievementDateExpression(): string
    {
        $parts = [];
        if (Schema::hasColumn('acceleration_service_sessions', 'final_approved_at')) {
            $parts[] = 's.final_approved_at';
        }
        if (Schema::hasColumn('acceleration_service_items', 'payload')) {
            $parts[] = self::dateOnlyDateTimeSql(self::jsonTextSql('i.payload', 'service_item_date'));
        }
        $parts[] = self::dateOnlyDateTimeSql('s.service_date');
        $parts[] = 's.created_at';

        return 'COALESCE('.implode(', ', $parts).')';
    }

    private static function jsonTextSql(string $column, string $key): string
    {
        $driver = DB::connection()->getDriverName();
        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            return "JSON_UNQUOTE(JSON_EXTRACT({$column}, '$.\"{$key}\"'))";
        }
        if ($driver === 'sqlite') {
            return "json_extract({$column}, '$.{$key}')";
        }

        return "{$column}->>'{$key}'";
    }

    private static function dateOnlyDateTimeSql(string $expr): string
    {
        $driver = DB::connection()->getDriverName();
        if ($driver === 'sqlite') {
            return "CASE WHEN {$expr} IS NULL OR TRIM(CAST({$expr} AS TEXT)) = '' OR LOWER(TRIM(CAST({$expr} AS TEXT))) = 'null' THEN NULL ELSE substr(CAST({$expr} AS TEXT), 1, 10) || ' 00:00:00' END";
        }

        return "CASE WHEN {$expr} IS NULL OR TRIM(CAST({$expr} AS CHAR)) = '' OR LOWER(TRIM(CAST({$expr} AS CHAR))) = 'null' THEN NULL ELSE CONCAT(LEFT(CAST({$expr} AS CHAR), 10), ' 00:00:00') END";
    }

    /**
     * @return array<string, string> lowercase name => canonical name
     */
    private static function canonicalDistrictNames(): array
    {
        if (! Schema::hasTable('districts')) {
            return [];
        }

        $map = [];
        foreach (District::query()->pluck('name') as $name) {
            $trimmed = trim((string) $name);
            if ($trimmed === '') {
                continue;
            }
            $map[mb_strtolower($trimmed)] = $trimmed;
        }

        return $map;
    }

    /**
     * @return array<string, mixed>
     */
    private static function payloadArray(mixed $raw): array
    {
        if (is_array($raw)) {
            return $raw;
        }
        if (! is_string($raw) || $raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }
}
