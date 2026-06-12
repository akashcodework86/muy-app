<?php

namespace App\Support;

use App\Models\StakeholderCapacityBuildingSession;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class CapacityBuildingStakeholdersDeliverablesSupport
{
    private const TABLE = 'stakeholder_capacity_building_sessions';

    public static function countSessions(?Carbon $periodFrom, ?Carbon $periodTo): int
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
    public static function sessionsBreakdown(?Carbon $periodFrom, ?Carbon $periodTo): array
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

        $sessions = self::scopedQuery($periodFrom, $periodTo)
            ->select([
                't.id',
                't.session_date',
                't.session_title',
                't.stakeholder_type',
                't.stakeholder_type_other',
                't.department_name',
                't.venue',
                't.staff_trained_total',
                't.submitted_by_name',
            ])
            ->orderByDesc('t.session_date')
            ->orderByDesc('t.id')
            ->get();

        $total = $sessions->count();
        $byMonth = [];
        $records = [];

        foreach ($sessions as $session) {
            $monthKey = '';
            $displayDate = '—';
            $rawDate = trim((string) ($session->session_date ?? ''));
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

            $typeLabel = (new StakeholderCapacityBuildingSession([
                'stakeholder_type' => (string) ($session->stakeholder_type ?? ''),
                'stakeholder_type_other' => (string) ($session->stakeholder_type_other ?? ''),
            ]))->stakeholderTypeLabel();

            $title = trim((string) ($session->session_title ?? '')) ?: 'Capacity building session';
            $staff = (int) ($session->staff_trained_total ?? 0);

            $records[] = [
                'id' => (int) ($session->id ?? 0),
                'reference' => 'Session #'.(int) ($session->id ?? 0),
                'applicant' => $typeLabel,
                'district' => 'State',
                'hub' => '—',
                'service' => $title,
                'status' => $staff > 0 ? $staff.' staff trained' : '—',
                'date' => $displayDate,
            ];
        }

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
            'by_district' => [],
            'by_hub' => [],
            'by_month' => $byMonthRows,
            'records' => $records,
        ];
    }

    private static function scopedQuery(?Carbon $periodFrom, ?Carbon $periodTo): Builder
    {
        $query = DB::table(self::TABLE.' as t');

        if ($periodFrom && $periodTo) {
            $query->whereBetween('t.session_date', [
                $periodFrom->toDateString(),
                $periodTo->toDateString(),
            ]);
        }

        return $query;
    }
}
