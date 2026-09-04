<?php

namespace App\Support;

use App\Models\MentorshipRequest;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class MentorshipOnlinePortalDeliverablesSupport
{
    /**
     * Unique incubatees with at least one Done portal mentorship in the period (by Done date).
     *
     * @param  list<int>|null  $districtIds
     */
    public static function countUniqueIncubatees(?Carbon $periodFrom, ?Carbon $periodTo, ?array $districtIds = null): int
    {
        if (! Schema::hasTable('mentorship_requests')) {
            return 0;
        }

        $query = DB::table('mentorship_requests as t')
            ->join('cfa_submissions as cs', 'cs.id', '=', 't.cfa_submission_id')
            ->where('t.status', MentorshipRequest::STATUS_DONE)
            ->whereNotNull('t.done_at');

        self::applyPeriod($query, $periodFrom, $periodTo);
        self::applyDistricts($query, $districtIds);

        return (int) $query->selectRaw('COUNT(DISTINCT t.cfa_submission_id) as aggregate')->value('aggregate');
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
    public static function uniqueIncubateesBreakdown(?Carbon $periodFrom, ?Carbon $periodTo, ?array $districtIds = null): array
    {
        $empty = [
            'total' => 0,
            'by_district' => [],
            'by_hub' => [],
            'by_month' => [],
            'records' => [],
        ];

        if (! Schema::hasTable('mentorship_requests')) {
            return $empty;
        }

        $rows = DB::table('mentorship_requests as t')
            ->join('cfa_submissions as cs', 'cs.id', '=', 't.cfa_submission_id')
            ->leftJoin('districts as d', 'd.id', '=', 'cs.district_id')
            ->leftJoin('hubs as h', 'h.id', '=', 'd.hub_id')
            ->where('t.status', MentorshipRequest::STATUS_DONE)
            ->whereNotNull('t.done_at')
            ->when($periodFrom, fn ($q) => $q->where('t.done_at', '>=', $periodFrom->copy()->startOfDay()))
            ->when($periodTo, fn ($q) => $q->where('t.done_at', '<=', $periodTo->copy()->endOfDay()))
            ->when($districtIds !== null && $districtIds !== [], fn ($q) => $q->whereIn('cs.district_id', $districtIds))
            ->select([
                't.id',
                't.cfa_submission_id',
                't.category',
                't.done_at',
                'cs.applicant_name',
                'cs.application_no',
                'cs.district_id',
                'd.name as district_name',
                'h.name as hub_name',
            ])
            ->orderByDesc('t.done_at')
            ->orderByDesc('t.id')
            ->get();

        $seen = [];
        $unique = [];
        foreach ($rows as $row) {
            $cfaId = (int) $row->cfa_submission_id;
            if (isset($seen[$cfaId])) {
                continue;
            }
            $seen[$cfaId] = true;
            $unique[] = $row;
        }

        $total = count($unique);
        $byDistrict = [];
        $byHub = [];
        $byMonth = [];
        $records = [];

        foreach ($unique as $row) {
            $district = trim((string) ($row->district_name ?? '')) ?: '—';
            $hub = trim((string) ($row->hub_name ?? '')) ?: '—';
            $doneAt = $row->done_at ? Carbon::parse($row->done_at) : null;
            $monthKey = $doneAt ? $doneAt->format('Y-m') : '';
            $monthLabel = $doneAt ? $doneAt->format('M Y') : '—';

            $byDistrict[$district] = ($byDistrict[$district] ?? ['district' => $district, 'hub' => $hub, 'count' => 0]) ;
            $byDistrict[$district]['count']++;
            $byHub[$hub] = ($byHub[$hub] ?? ['hub' => $hub, 'count' => 0]);
            $byHub[$hub]['count']++;
            if ($monthKey !== '') {
                $byMonth[$monthKey] = ($byMonth[$monthKey] ?? ['month' => $monthLabel, 'month_key' => $monthKey, 'count' => 0]);
                $byMonth[$monthKey]['count']++;
            }

            $records[] = [
                'applicant' => (string) ($row->applicant_name ?? '—'),
                'application_no' => (string) ($row->application_no ?? '—'),
                'district' => $district,
                'hub' => $hub,
                'category' => (string) ($row->category ?? ''),
                'done_on' => $doneAt?->format('d M Y') ?? '—',
            ];
        }

        $share = static function (array $items, int $totalCount): array {
            $out = [];
            foreach ($items as $item) {
                $count = (int) ($item['count'] ?? 0);
                $item['share_pct'] = $totalCount > 0 ? (int) round(100 * $count / $totalCount) : 0;
                $out[] = $item;
            }
            usort($out, fn ($a, $b) => ($b['count'] ?? 0) <=> ($a['count'] ?? 0));

            return $out;
        };

        ksort($byMonth);

        return [
            'total' => $total,
            'by_district' => $share(array_values($byDistrict), $total),
            'by_hub' => $share(array_values($byHub), $total),
            'by_month' => $share(array_values($byMonth), $total),
            'records' => $records,
        ];
    }

    private static function applyPeriod($query, ?Carbon $periodFrom, ?Carbon $periodTo): void
    {
        if ($periodFrom) {
            $query->where('t.done_at', '>=', $periodFrom->copy()->startOfDay());
        }
        if ($periodTo) {
            $query->where('t.done_at', '<=', $periodTo->copy()->endOfDay());
        }
    }

    /**
     * @param  list<int>|null  $districtIds
     */
    private static function applyDistricts($query, ?array $districtIds): void
    {
        if ($districtIds !== null && $districtIds !== []) {
            $query->whereIn('cs.district_id', $districtIds);
        }
    }
}
