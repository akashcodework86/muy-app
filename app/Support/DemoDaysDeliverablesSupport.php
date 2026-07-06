<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class DemoDaysDeliverablesSupport
{
    private const TABLE = 'demo_days';

    public static function countEvents(?Carbon $periodFrom, ?Carbon $periodTo): int
    {
        if (! Schema::hasTable(self::TABLE)) {
            return 0;
        }

        return (int) self::scopedQuery($periodFrom, $periodTo)->count();
    }

    /**
     * @return array<string, mixed>
     */
    public static function breakdown(?Carbon $periodFrom, ?Carbon $periodTo): array
    {
        if (! Schema::hasTable(self::TABLE)) {
            return self::emptyBreakdown();
        }

        $rows = self::scopedQuery($periodFrom, $periodTo)
            ->leftJoin('districts as d', 'd.id', '=', 't.district_id')
            ->select([
                't.id',
                't.event_date',
                't.event_name',
                't.event_type',
                't.event_type_other',
                't.investor_name',
                't.incubatee_name',
                't.application_no',
                't.mode',
                't.entered_by_name',
                'd.name as district_name',
            ])
            ->orderByDesc('t.event_date')
            ->orderByDesc('t.id')

            ->get();

        $records = [];
        $byMonth = [];
        $byDistrict = [];

        foreach ($rows as $row) {
            $rawDate = trim((string) ($row->event_date ?? ''));
            $displayDate = '—';
            $monthKey = '';

            if ($rawDate !== '') {
                try {
                    $parsed = Carbon::parse($rawDate);
                    $monthKey = $parsed->format('Y-m');
                    $displayDate = $parsed->format('d M Y');
                } catch (\Throwable) {
                    $displayDate = $rawDate;
                }
            }

            if ($monthKey !== '') {
                $byMonth[$monthKey] = ($byMonth[$monthKey] ?? 0) + 1;
            }

            $district = trim((string) ($row->district_name ?? ''));
            if ($district !== '') {
                $byDistrict[$district] = ($byDistrict[$district] ?? 0) + 1;
            }

            $records[] = [
                'id' => (int) $row->id,
                'reference' => (string) ($row->event_name ?: 'Demo day'),
                'applicant' => (string) ($row->incubatee_name ?: '—'),
                'district' => $district !== '' ? $district : 'State',
                'hub' => DemoDayOptions::eventTypeLabel((string) $row->event_type, $row->event_type_other),
                'service' => (string) ($row->application_no ?: '—'),
                'spoc' => (string) ($row->entered_by_name ?: '—'),
                'status' => DemoDayOptions::modeLabel($row->mode) ?: '—',
                'date' => $displayDate,
            ];
        }

        $total = count($rows);

        $byMonthRows = collect($byMonth)
            ->sortKeys()
            ->map(fn (int $count, string $monthKey) => [
                'month' => Carbon::createFromFormat('Y-m', $monthKey)->format('M Y'),
                'month_key' => $monthKey,
                'count' => $count,
                'share_pct' => $total > 0 ? (int) round(($count / $total) * 100) : 0,
            ])
            ->values()
            ->all();

        $byDistrictRows = collect($byDistrict)
            ->sortKeys()
            ->map(fn (int $count, string $name) => [
                'district' => $name,
                'count' => $count,
                'share_pct' => $total > 0 ? (int) round(($count / $total) * 100) : 0,
            ])
            ->values()
            ->all();

        return [
            'total' => $total,
            'by_district' => $byDistrictRows,
            'by_hub' => [],
            'by_month' => $byMonthRows,
            'records' => $records,
        ];
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

    private static function scopedQuery(?Carbon $periodFrom, ?Carbon $periodTo): Builder
    {
        $query = DB::table(self::TABLE.' as t');

        if ($periodFrom && $periodTo) {
            $query->whereBetween('t.event_date', [
                $periodFrom->toDateString(),
                $periodTo->toDateString(),
            ]);
        }

        return $query;
    }
}
