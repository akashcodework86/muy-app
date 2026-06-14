<?php

namespace App\Support;

use App\Models\StakeholderConsultationWorkshop;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class StakeholderConsultationWorkshopDeliverablesSupport
{
    private const TABLE = 'stakeholder_consultation_workshops';

    public static function countWorkshops(?Carbon $periodFrom, ?Carbon $periodTo): int
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
    public static function workshopsBreakdown(?Carbon $periodFrom, ?Carbon $periodTo): array
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

        $workshops = self::scopedQuery($periodFrom, $periodTo)
            ->select([
                't.id',
                't.workshop_date',
                't.workshop_title',
                't.organizing_level',
                't.hub_name',
                't.district_name',
                't.total_participants',
                't.primary_departments_json',
                't.submitted_by_name',
            ])
            ->orderByDesc('t.workshop_date')
            ->orderByDesc('t.id')
            ->get();

        $total = $workshops->count();
        $byMonth = [];
        $records = [];

        foreach ($workshops as $workshop) {
            $monthKey = '';
            $displayDate = '—';
            $rawDate = trim((string) ($workshop->workshop_date ?? ''));
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

            $model = new StakeholderConsultationWorkshop([
                'primary_departments_json' => json_decode((string) ($workshop->primary_departments_json ?? '[]'), true) ?: [],
            ]);

            $title = trim((string) ($workshop->workshop_title ?? '')) ?: 'Consultation workshop';
            $district = trim((string) ($workshop->district_name ?? ''));
            $hub = trim((string) ($workshop->hub_name ?? ''));

            $records[] = [
                'id' => (int) ($workshop->id ?? 0),
                'reference' => 'Workshop #'.(int) ($workshop->id ?? 0),
                'applicant' => $model->primaryDepartmentsLabel(),
                'district' => $district !== '' ? $district : ucfirst((string) ($workshop->organizing_level ?? 'State')),
                'hub' => $hub !== '' ? $hub : '—',
                'service' => $title,
                'status' => ((int) ($workshop->total_participants ?? 0)).' participants',
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
            $query->whereBetween('t.workshop_date', [
                $periodFrom->toDateString(),
                $periodTo->toDateString(),
            ]);
        }

        return $query;
    }
}
