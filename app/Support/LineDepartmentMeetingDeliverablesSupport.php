<?php

namespace App\Support;

use App\Models\LineDepartmentMeeting;
use App\Support\MisFieldActivityApproval;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class LineDepartmentMeetingDeliverablesSupport
{
    private const TABLE = 'line_department_meetings';

    private const EFFECTIVE_DISTRICT_SQL = 'COALESCE(NULLIF(t.district_id, 0), NULLIF(u.district_id, 0))';

    private const EFFECTIVE_HUB_SQL = 'COALESCE(NULLIF(t.hub_id, 0), d.hub_id)';

    /**
     * @param  list<int>|null  $districtIds
     */
    public static function countMeetings(?Carbon $periodFrom, ?Carbon $periodTo, ?array $districtIds = null): int
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
    public static function meetingsBreakdown(?Carbon $periodFrom, ?Carbon $periodTo, ?array $districtIds = null): array
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

        $meetings = self::scopedQuery($periodFrom, $periodTo, $districtIds)
            ->select([
                't.id',
                't.meeting_date',
                't.meeting_level',
                't.department_name',
                't.official_name',
                't.hub_name',
                't.district_name',
                't.submitted_by_name',
                'd.name as resolved_district_name',
                'h.name as resolved_hub_name',
            ])
            ->orderByDesc('t.meeting_date')
            ->orderByDesc('t.id')
            ->get();

        $total = $meetings->count();
        $byMonth = [];
        $byDistrict = [];
        $byHub = [];
        $records = [];

        foreach ($meetings as $meeting) {
            $monthKey = '';
            $displayDate = '—';
            $rawDate = trim((string) ($meeting->meeting_date ?? ''));
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

            $model = new LineDepartmentMeeting([
                'meeting_level' => (string) ($meeting->meeting_level ?? ''),
            ]);

            $district = trim((string) ($meeting->resolved_district_name ?? ''));
            if ($district === '') {
                $district = trim((string) ($meeting->district_name ?? ''));
            }
            if ($district === '') {
                $district = $model->meetingLevelLabel();
            }

            $hub = trim((string) ($meeting->resolved_hub_name ?? ''));
            if ($hub === '') {
                $hub = trim((string) ($meeting->hub_name ?? ''));
            }

            $districtKey = $district;
            if (! isset($byDistrict[$districtKey])) {
                $byDistrict[$districtKey] = ['count' => 0, 'hub' => $hub !== '' ? $hub : '—'];
            }
            $byDistrict[$districtKey]['count']++;
            if ($hub !== '' && $byDistrict[$districtKey]['hub'] === '—') {
                $byDistrict[$districtKey]['hub'] = $hub;
            }

            if ($hub !== '') {
                $byHub[$hub] = ($byHub[$hub] ?? 0) + 1;
            }

            $records[] = [
                'id' => (int) ($meeting->id ?? 0),
                'reference' => 'Meeting #'.(int) ($meeting->id ?? 0),
                'applicant' => trim((string) ($meeting->department_name ?? '')),
                'district' => $district,
                'hub' => $hub !== '' ? $hub : '—',
                'service' => trim((string) ($meeting->official_name ?? '')) ?: 'Line department meeting',
                'status' => $model->meetingLevelLabel(),
                'date' => $displayDate,
            ];
        }

        ksort($byMonth);

        return [
            'total' => $total,
            'by_district' => self::formatDistrictBreakdownList($byDistrict, $total),
            'by_hub' => self::formatHubBreakdownList($byHub, $total),
            'by_month' => self::formatMonthBreakdownList($byMonth, $total),
            'records' => $records,
        ];
    }

    /**
     * @param  array<string, array{count: int, hub: string}>  $counts
     * @return list<array{district: string, hub: string, count: int, share_pct: int}>
     */
    private static function formatDistrictBreakdownList(array $counts, int $total): array
    {
        uasort($counts, fn (array $a, array $b): int => $b['count'] <=> $a['count']);
        $rows = [];
        foreach ($counts as $name => $meta) {
            $rows[] = [
                'district' => (string) $name,
                'hub' => (string) ($meta['hub'] ?? '—'),
                'count' => (int) ($meta['count'] ?? 0),
                'share_pct' => $total > 0 ? (int) round(((int) ($meta['count'] ?? 0) / $total) * 100) : 0,
            ];
        }

        return $rows;
    }

    /**
     * @param  array<string, int>  $counts
     * @return list<array{hub: string, count: int, share_pct: int}>
     */
    private static function formatHubBreakdownList(array $counts, int $total): array
    {
        arsort($counts);
        $rows = [];
        foreach ($counts as $name => $count) {
            $rows[] = [
                'hub' => (string) $name,
                'count' => (int) $count,
                'share_pct' => $total > 0 ? (int) round(($count / $total) * 100) : 0,
            ];
        }

        return $rows;
    }

    /**
     * @param  array<string, int>  $counts
     * @return list<array{month: string, month_key: string, count: int, share_pct: int}>
     */
    private static function formatMonthBreakdownList(array $counts, int $total): array
    {
        $rows = [];
        foreach ($counts as $monthKey => $count) {
            $monthLabel = $monthKey;
            try {
                $monthLabel = Carbon::createFromFormat('Y-m', (string) $monthKey)->format('M Y');
            } catch (\Throwable) {
                // keep raw key
            }
            $rows[] = [
                'month' => $monthLabel,
                'month_key' => (string) $monthKey,
                'count' => (int) $count,
                'share_pct' => $total > 0 ? (int) round(($count / $total) * 100) : 0,
            ];
        }

        return $rows;
    }

    /**
     * @param  list<int>|null  $districtIds
     */
    private static function scopedQuery(?Carbon $periodFrom, ?Carbon $periodTo, ?array $districtIds): Builder
    {
        $query = DB::table(self::TABLE.' as t')
            ->leftJoin('users as u', 'u.id', '=', 't.submitted_by_user_id')
            ->leftJoin('districts as d', DB::raw('d.id'), '=', DB::raw(self::EFFECTIVE_DISTRICT_SQL))
            ->leftJoin('hubs as h', DB::raw('h.id'), '=', DB::raw(self::EFFECTIVE_HUB_SQL));

        if ($periodFrom && $periodTo) {
            $query->whereBetween('t.meeting_date', [
                $periodFrom->toDateString(),
                $periodTo->toDateString(),
            ]);
        }

        MisFieldActivityApproval::applyApprovedOnlyFilter($query, self::TABLE, 't');
        self::applyDistrictScope($query, $districtIds);

        return $query;
    }

    /**
     * Hub-level meetings logged by district staff have no district_id; attribute via submitter.
     *
     * @param  list<int>|null  $districtIds
     */
    private static function applyDistrictScope(Builder $query, ?array $districtIds): void
    {
        if ($districtIds === null) {
            return;
        }

        if ($districtIds === []) {
            $query->whereRaw('0 = 1');

            return;
        }

        $query->where(function (Builder $scoped) use ($districtIds): void {
            $scoped->whereIn('t.district_id', $districtIds)
                ->orWhere(function (Builder $fallback) use ($districtIds): void {
                    $fallback->where(function (Builder $noDistrict): void {
                        $noDistrict->whereNull('t.district_id')
                            ->orWhere('t.district_id', 0);
                    })->whereIn('u.district_id', $districtIds);
                });
        });
    }
}
