<?php

namespace App\Support;

use App\Models\District;
use App\Models\PitchDeckPreparation;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class PitchDeckPreparationsDeliverablesSupport
{
    private const TABLE = 'pitch_deck_preparations';

    /**
     * @param  list<int>|null  $districtIds
     */
    public static function countPreparations(?Carbon $periodFrom, ?Carbon $periodTo, ?array $districtIds = null): int
    {
        if (! Schema::hasTable(self::TABLE)) {
            return 0;
        }

        return (int) self::scopedQuery($periodFrom, $periodTo, $districtIds)->count();
    }

    /**
     * @param  list<int>|null  $districtIds
     * @return array{
     *   total: int,
     *   by_district: list<array{district: string, hub: string, count: int, share_pct: int}>,
     *   by_hub: list<array{hub: string, count: int, share_pct: int}>,
     *   by_month: list<array{month: string, month_key: string, count: int, share_pct: int}>,
     *   records: list<array<string, mixed>>
     * }
     */
    public static function preparationsBreakdown(?Carbon $periodFrom, ?Carbon $periodTo, ?array $districtIds = null): array
    {
        if (! Schema::hasTable(self::TABLE)) {
            return [
                'total' => 0,
                'by_district' => [],
                'by_hub' => [],
                'by_month' => [],
                'records' => [],
            ];
        }

        $rows = self::scopedQuery($periodFrom, $periodTo, $districtIds)
            ->leftJoin('districts as d', 'd.id', '=', 't.district_id')
            ->leftJoin('hubs as h', 'h.id', '=', 'd.hub_id')
            ->select([
                't.id',
                't.prepared_on',
                't.incubatee_name',
                't.application_no',
                't.prepared_for',
                't.support_mode',
                't.entered_by_name',
                'd.name as district_name',
                'h.name as hub_name',
            ])
            ->orderByDesc('t.prepared_on')
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
            if ($hubName !== '—') {
                $byHub[$hubName] = ($byHub[$hubName] ?? 0) + 1;
            }

            $monthKey = '';
            $displayDate = '—';
            $rawDate = trim((string) ($row->prepared_on ?? ''));
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

            $name = trim((string) ($row->incubatee_name ?? '')) ?: 'Incubatee';
            $appNo = trim((string) ($row->application_no ?? ''));
            $preparedFor = trim((string) ($row->prepared_for ?? ''));

            $modeKey = strtolower(trim((string) ($row->support_mode ?? '')));
            $modes = config('pitch_deck_preparations.support_modes', []);
            $modeLabel = is_array($modes) ? ($modes[$modeKey] ?? ($modeKey !== '' ? ucfirst($modeKey) : '')) : '';

            $records[] = [
                'id' => (int) ($row->id ?? 0),
                'reference' => $appNo !== '' ? $appNo : 'Entry #'.(int) ($row->id ?? 0),
                'applicant' => $name,
                'district' => $districtName,
                'hub' => $hubName,
                'service' => $preparedFor !== '' ? $preparedFor : 'Pitch deck prepared',
                'status' => $modeLabel !== '' ? $modeLabel : ((string) ($row->entered_by_name ?? '—')),
                'date' => $displayDate,
            ];
        }

        $districtRows = [];
        foreach ($byDistrict as $district => $count) {
            $hub = District::query()->where('name', $district)->with('hub:id,name')->first()?->hub?->name ?? '—';
            $districtRows[] = [
                'district' => (string) $district,
                'hub' => (string) $hub,
                'count' => (int) $count,
                'share_pct' => $total > 0 ? (int) round(($count / $total) * 100) : 0,
            ];
        }
        usort($districtRows, fn (array $a, array $b): int => $b['count'] <=> $a['count']);

        $hubRows = [];
        foreach ($byHub as $hub => $count) {
            $hubRows[] = [
                'hub' => (string) $hub,
                'count' => (int) $count,
                'share_pct' => $total > 0 ? (int) round(($count / $total) * 100) : 0,
            ];
        }
        usort($hubRows, fn (array $a, array $b): int => $b['count'] <=> $a['count']);

        ksort($byMonth);
        $byMonthRows = [];
        foreach ($byMonth as $monthKey => $count) {
            $monthLabel = $monthKey;
            try {
                $monthLabel = Carbon::createFromFormat('Y-m', (string) $monthKey)->format('M Y');
            } catch (\Throwable) {
                // keep raw key
            }
            $byMonthRows[] = [
                'month' => $monthLabel,
                'month_key' => (string) $monthKey,
                'count' => (int) $count,
                'share_pct' => $total > 0 ? (int) round(($count / $total) * 100) : 0,
            ];
        }

        return [
            'total' => $total,
            'by_district' => $districtRows,
            'by_hub' => $hubRows,
            'by_month' => $byMonthRows,
            'records' => $records,
        ];
    }

    /**
     * @param  list<int>|null  $districtIds
     */
    private static function scopedQuery(?Carbon $periodFrom, ?Carbon $periodTo, ?array $districtIds): Builder
    {
        $query = DB::table(self::TABLE.' as t');

        if ($districtIds !== null) {
            if ($districtIds === []) {
                $query->whereRaw('0 = 1');
            } else {
                $query->whereIn('t.district_id', $districtIds);
            }
        }

        if ($periodFrom && $periodTo) {
            $query->whereBetween('t.prepared_on', [
                $periodFrom->toDateString(),
                $periodTo->toDateString(),
            ]);
        }

        return $query;
    }
}
