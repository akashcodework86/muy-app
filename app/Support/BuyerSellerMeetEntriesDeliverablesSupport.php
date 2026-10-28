<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class BuyerSellerMeetEntriesDeliverablesSupport
{
    private const TABLE = 'buyer_seller_meet_entries';

    public static function countEntries(?Carbon $periodFrom, ?Carbon $periodTo, ?array $districtIds = null): int
    {
        if (! Schema::hasTable(self::TABLE)) {
            return 0;
        }

        return (int) self::baseQuery($periodFrom, $periodTo, $districtIds)->count();
    }

    /**
     * @param  list<int>|null  $districtIds
     * @return array<string, mixed>
     */
    public static function breakdown(?Carbon $periodFrom, ?Carbon $periodTo, ?array $districtIds = null): array
    {
        if (! Schema::hasTable(self::TABLE)) {
            return self::emptyBreakdown();
        }

        $rows = self::baseQuery($periodFrom, $periodTo, $districtIds)
            ->leftJoin('districts as d', 'd.id', '=', 't.district_id')
            ->leftJoin('hubs as h', 'h.id', '=', 'd.hub_id')
            ->select([
                't.id',
                't.meet_date',
                't.title',
                't.venue',
                't.total_participants',
                't.submitted_by_name',
                'd.name as district_name',
                'h.name as hub_name',
            ])
            ->orderByDesc('t.meet_date')
            ->orderByDesc('t.id')
            ->get();

        $total = $rows->count();
        $byDistrict = [];
        $byHub = [];
        $byMonth = [];
        $records = [];

        foreach ($rows as $row) {
            $districtName = trim((string) ($row->district_name ?? '')) ?: '—';
            $hubName = trim((string) ($row->hub_name ?? '')) ?: '—';
            $byDistrict[$districtName] = ($byDistrict[$districtName] ?? 0) + 1;
            $byHub[$hubName] = ($byHub[$hubName] ?? 0) + 1;

            $rawDate = trim((string) ($row->meet_date ?? ''));
            if ($rawDate !== '') {
                try {
                    $monthKey = Carbon::parse($rawDate)->format('Y-m');
                    $byMonth[$monthKey] = ($byMonth[$monthKey] ?? 0) + 1;
                } catch (\Throwable) {
                    // ignore
                }
            }

            $records[] = [
                'id' => (int) $row->id,
                'reference' => 'Meet #'.$row->id,
                'applicant' => (string) ($row->title ?: '—'),
                'district' => $districtName,
                'hub' => $hubName,
                'service' => (string) ($row->venue ?: '—'),
                'spoc' => (string) ($row->submitted_by_name ?: '—'),
                'status' => 'Logged · '.number_format((int) ($row->total_participants ?? 0)).' participants',
                'date' => self::formatDate($row->meet_date),
            ];
        }

        return [
            'total' => $total,
            'by_district' => self::mapCountRows($byDistrict, $total),
            'by_hub' => self::mapCountRows($byHub, $total),
            'by_month' => collect($byMonth)
                ->sortKeys()
                ->map(fn (int $count, string $monthKey) => [
                    'month' => Carbon::createFromFormat('Y-m', $monthKey)->format('M Y'),
                    'month_key' => $monthKey,
                    'count' => $count,
                    'share_pct' => $total > 0 ? (int) round(($count / $total) * 100) : 0,
                ])
                ->values()
                ->all(),
            'records' => $records,
        ];
    }

    /**
     * @param  list<int>|null  $districtIds
     */
    private static function baseQuery(?Carbon $periodFrom, ?Carbon $periodTo, ?array $districtIds): Builder
    {
        $query = DB::table(self::TABLE.' as t');
        self::applyPeriodFilter($query, 't.meet_date', $periodFrom, $periodTo);

        if ($districtIds !== null && $districtIds !== []) {
            $query->whereIn('t.district_id', $districtIds);
        }

        return $query;
    }

    /**
     * @return array<string, mixed>
     */
    private static function emptyBreakdown(): array
    {
        return [
            'total' => 0,
            'by_district' => [],
            'by_hub' => [],
            'by_month' => [],
            'records' => [],
        ];
    }

    private static function applyPeriodFilter(Builder $query, string $column, ?Carbon $periodFrom, ?Carbon $periodTo): void
    {
        if (! $periodFrom || ! $periodTo) {
            return;
        }

        $query->whereDate($column, '>=', $periodFrom->toDateString())
            ->whereDate($column, '<=', $periodTo->toDateString());
    }

    private static function formatDate(mixed $value): string
    {
        $raw = trim((string) ($value ?? ''));
        if ($raw === '') {
            return '—';
        }

        try {
            return Carbon::parse($raw)->format('d M Y');
        } catch (\Throwable) {
            return $raw;
        }
    }

    /**
     * @param  array<string, int>  $counts
     * @return list<array<string, mixed>>
     */
    private static function mapCountRows(array $counts, int $total): array
    {
        return collect($counts)
            ->sortByDesc(fn (int $c) => $c)
            ->map(fn (int $count, string $label) => [
                'label' => $label,
                'count' => $count,
                'share_pct' => $total > 0 ? (int) round(($count / $total) * 100) : 0,
            ])
            ->values()
            ->all();
    }
}
