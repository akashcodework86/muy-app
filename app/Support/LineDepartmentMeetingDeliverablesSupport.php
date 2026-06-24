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

    public static function countMeetings(?Carbon $periodFrom, ?Carbon $periodTo): int
    {
        if (! Schema::hasTable(self::TABLE)) {
            return 0;
        }

        return (int) self::scopedQuery($periodFrom, $periodTo)->count();
    }

    /**
     * @return array{
     *   total: int,
     *   by_district: list<array{district: string, hub: string, count: int, share_pct: int}>,
     *   by_hub: list<array{hub: string, count: int, share_pct: int}>,
     *   by_month: list<array{month: string, month_key: string, count: int, share_pct: int}>,
     *   records: list<array<string, mixed>>
     * }
     */
    public static function meetingsBreakdown(?Carbon $periodFrom, ?Carbon $periodTo): array
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

        $meetings = self::scopedQuery($periodFrom, $periodTo)
            ->select([
                't.id',
                't.meeting_date',
                't.meeting_level',
                't.department_name',
                't.official_name',
                't.hub_name',
                't.district_name',
                't.submitted_by_name',
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

            $district = trim((string) ($meeting->district_name ?? ''));
            $hub = trim((string) ($meeting->hub_name ?? ''));
            if ($district !== '') {
                $byDistrict[$district] = ($byDistrict[$district] ?? 0) + 1;
            }
            if ($hub !== '') {
                $byHub[$hub] = ($byHub[$hub] ?? 0) + 1;
            }

            $model = new LineDepartmentMeeting([
                'meeting_level' => (string) ($meeting->meeting_level ?? ''),
            ]);

            $records[] = [
                'id' => (int) ($meeting->id ?? 0),
                'reference' => 'Meeting #'.(int) ($meeting->id ?? 0),
                'applicant' => trim((string) ($meeting->department_name ?? '')),
                'district' => $district !== '' ? $district : $model->meetingLevelLabel(),
                'hub' => $hub !== '' ? $hub : '—',
                'service' => trim((string) ($meeting->official_name ?? '')) ?: 'Line department meeting',
                'status' => $model->meetingLevelLabel(),
                'date' => $displayDate,
            ];
        }

        ksort($byMonth);

        return [
            'total' => $total,
            'by_district' => self::formatBreakdownList($byDistrict, $total),
            'by_hub' => self::formatHubBreakdownList($byHub, $total),
            'by_month' => self::formatMonthBreakdownList($byMonth, $total),
            'records' => $records,
        ];
    }

    /**
     * @param  array<string, int>  $counts
     * @return list<array{district: string, hub: string, count: int, share_pct: int}>
     */
    private static function formatBreakdownList(array $counts, int $total): array
    {
        arsort($counts);
        $rows = [];
        foreach ($counts as $name => $count) {
            $rows[] = [
                'district' => (string) $name,
                'hub' => '—',
                'count' => (int) $count,
                'share_pct' => $total > 0 ? (int) round(($count / $total) * 100) : 0,
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

    private static function scopedQuery(?Carbon $periodFrom, ?Carbon $periodTo): Builder
    {
        $query = DB::table(self::TABLE.' as t');

        if ($periodFrom && $periodTo) {
            $query->whereBetween('t.meeting_date', [
                $periodFrom->toDateString(),
                $periodTo->toDateString(),
            ]);
        }

        MisFieldActivityApproval::applyApprovedOnlyFilter($query, self::TABLE, 't');

        return $query;
    }
}
