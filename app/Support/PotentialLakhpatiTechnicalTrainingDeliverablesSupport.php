<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Support\MisFieldActivityApproval;

final class PotentialLakhpatiTechnicalTrainingDeliverablesSupport
{
    private const TABLE = 'potential_lakhpati_technical_trainings';

    /**
     * @param  list<int>|null  $districtIds
     */
    public static function countSessions(?array $districtIds, ?Carbon $periodFrom, ?Carbon $periodTo): int
    {
        if (! Schema::hasTable(self::TABLE)) {
            return 0;
        }

        return (int) self::scopedQuery($districtIds, $periodFrom, $periodTo)->count();
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
    public static function sessionsBreakdown(?array $districtIds, ?Carbon $periodFrom, ?Carbon $periodTo): array
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

        $query = self::scopedQuery($districtIds, $periodFrom, $periodTo)
            ->leftJoin('districts as d', 'd.id', '=', 't.district_id')
            ->leftJoin('hubs as h', 'h.id', '=', 'd.hub_id');

        $sessions = (clone $query)
            ->select([
                't.id',
                't.session_date',
                't.session_title',
                't.requesting_agency_type',
                't.block',
                't.area',
                't.male_participants',
                't.female_participants',
                't.participants_total',
                't.submitted_by_name',
                DB::raw("COALESCE(d.name, t.district_name, 'Unknown') as district_name"),
                DB::raw("COALESCE(h.name, '—') as hub_name"),
            ])
            ->orderByDesc('t.session_date')
            ->orderByDesc('t.id')
            ->get();

        $total = $sessions->count();
        $byDistrict = [];
        $hubByDistrict = [];
        $byHub = [];
        $byMonth = [];
        $records = [];

        foreach ($sessions as $session) {
            $district = trim((string) ($session->district_name ?? 'Unknown')) ?: 'Unknown';
            $hub = trim((string) ($session->hub_name ?? '')) ?: '—';

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

            $byDistrict[$district] = ($byDistrict[$district] ?? 0) + 1;
            $hubByDistrict[$district] = $hub;
            $byHub[$hub] = ($byHub[$hub] ?? 0) + 1;
            if ($monthKey !== '') {
                $byMonth[$monthKey] = ($byMonth[$monthKey] ?? 0) + 1;
            }

            $agencyKey = strtolower(trim((string) ($session->requesting_agency_type ?? '')));
            $agencyLabel = \App\Models\LakhpatiTechnicalTraining::AGENCY_TYPES[$agencyKey]
                ?? ucfirst(str_replace('_', ' ', $agencyKey));

            $title = trim((string) ($session->session_title ?? '')) ?: 'Technical training session';
            $participants = (int) ($session->participants_total ?? 0);
            if ($participants <= 0) {
                $participants = (int) ($session->male_participants ?? 0) + (int) ($session->female_participants ?? 0);
            }

            $records[] = [
                'id' => (int) ($session->id ?? 0),
                'reference' => 'Session #'.(int) ($session->id ?? 0),
                'applicant' => $agencyLabel,
                'district' => $district,
                'hub' => $hub,
                'service' => $title,
                'status' => $participants > 0 ? $participants.' participants' : 'Attendance pending',
                'date' => $displayDate,
            ];
        }

        $byDistrictRows = [];
        foreach ($byDistrict as $district => $count) {
            $byDistrictRows[] = [
                'district' => (string) $district,
                'hub' => (string) ($hubByDistrict[$district] ?? '—'),
                'count' => (int) $count,
                'share_pct' => $total > 0 ? (int) round(($count / $total) * 100) : 0,
            ];
        }
        usort($byDistrictRows, fn (array $a, array $b): int => $b['count'] <=> $a['count']);

        $byHubRows = [];
        foreach ($byHub as $hub => $count) {
            $byHubRows[] = [
                'hub' => (string) $hub,
                'count' => (int) $count,
                'share_pct' => $total > 0 ? (int) round(($count / $total) * 100) : 0,
            ];
        }
        usort($byHubRows, fn (array $a, array $b): int => $b['count'] <=> $a['count']);

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
            'by_district' => $byDistrictRows,
            'by_hub' => $byHubRows,
            'by_month' => $byMonthRows,
            'records' => $records,
        ];
    }

    /**
     * @param  list<int>|null  $districtIds
     */
    private static function scopedQuery(?array $districtIds, ?Carbon $periodFrom, ?Carbon $periodTo): Builder
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
            $query->whereBetween('t.session_date', [
                $periodFrom->toDateString(),
                $periodTo->toDateString(),
            ]);
        }

        MisFieldActivityApproval::applyApprovedOnlyFilter($query, self::TABLE, 't');

        return $query;
    }
}
