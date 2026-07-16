<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class AccelerationServicesDeliverablesSupport
{
    public static function countUniqueInitiations(?Carbon $periodFrom, ?Carbon $periodTo): int
    {
        if (! Schema::hasTable('acceleration_service_sessions')) {
            return 0;
        }

        // With the approval workflow, count unique approved incubatees directly
        // so a rejected first entry doesn't block a later approved one.
        if (Schema::hasColumn('acceleration_service_sessions', 'status')) {
            return (int) self::scopedSessionsQuery($periodFrom, $periodTo)
                ->distinct()
                ->count('s.incubatee_key');
        }

        $query = self::scopedSessionsQuery($periodFrom, $periodTo)
            ->where('s.counts_for_7_2', true);

        return (int) $query->distinct()->count('s.incubatee_key');
    }

    public static function countBuyerSellerMeets(?Carbon $periodFrom, ?Carbon $periodTo): int
    {
        if (! Schema::hasTable('acceleration_service_items') || ! Schema::hasTable('acceleration_service_sessions')) {
            return 0;
        }

        return (int) self::scopedItemsQuery($periodFrom, $periodTo)
            ->where('i.is_buyer_seller_meet', true)
            ->count();
    }

    /**
     * @return array<string, mixed>
     */
    public static function initiationBreakdown(?Carbon $periodFrom, ?Carbon $periodTo): array
    {
        if (! Schema::hasTable('acceleration_service_sessions')) {
            return self::emptyBreakdown();
        }

        $hasStatus = Schema::hasColumn('acceleration_service_sessions', 'status');

        $rows = self::scopedSessionsQuery($periodFrom, $periodTo)
            ->when(! $hasStatus, fn ($q) => $q->where('s.counts_for_7_2', true))
            ->select([
                's.id',
                's.incubatee_key',
                's.service_date',
                's.applicant_name',
                's.application_no',
                's.district_name',
                's.onboard_label',
                's.submitted_by_name',
            ])
            ->orderBy('s.service_date')
            ->orderBy('s.id')
            ->get();

        // One record per incubatee — the first approved session in the period.
        if ($hasStatus) {
            $rows = $rows->unique(fn ($row) => (string) $row->incubatee_key)->values();
        }

        $rows = $rows->sortByDesc(fn ($row) => [(string) $row->service_date, (int) $row->id])->values();

        $records = [];
        $byMonth = [];

        foreach ($rows as $row) {
            $rawDate = trim((string) ($row->service_date ?? ''));
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

            $records[] = [
                'id' => (int) $row->id,
                'reference' => (string) ($row->application_no ?: '—'),
                'applicant' => (string) ($row->applicant_name ?: '—'),
                'district' => (string) ($row->district_name ?: 'State'),
                'hub' => '—',
                'service' => 'Acceleration initiation (7.2)',
                'spoc' => (string) ($row->submitted_by_name ?: '—'),
                'status' => (string) ($row->onboard_label ?: '—'),
                'date' => $displayDate,
            ];
        }

        $total = count($records);
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

        return [
            'total' => $total,
            'by_district' => [],
            'by_hub' => [],
            'by_month' => $byMonthRows,
            'records' => $records,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function buyerSellerRecords(?Carbon $periodFrom, ?Carbon $periodTo): array
    {
        if (! Schema::hasTable('acceleration_service_items') || ! Schema::hasTable('acceleration_service_sessions')) {
            return [];
        }

        $rows = self::scopedItemsQuery($periodFrom, $periodTo)
            ->where('i.is_buyer_seller_meet', true)
            ->select([
                'i.id',
                'i.remarks',
                's.id as session_id',
                's.service_date',
                's.applicant_name',
                's.application_no',
                's.district_name',
                's.submitted_by_name',
            ])
            ->orderByDesc('s.service_date')
            ->orderByDesc('i.id')

            ->get();

        $records = [];
        foreach ($rows as $row) {
            $displayDate = '—';
            if ($row->service_date) {
                try {
                    $displayDate = Carbon::parse((string) $row->service_date)->format('d M Y');
                } catch (\Throwable) {
                    $displayDate = (string) $row->service_date;
                }
            }

            $records[] = [
                'id' => (int) $row->id,
                'reference' => (string) ($row->application_no ?: '—'),
                'applicant' => (string) ($row->applicant_name ?: '—'),
                'district' => (string) ($row->district_name ?: 'State'),
                'hub' => '—',
                'service' => 'Buyer Seller Meet (Acceleration 7.2)',
                'spoc' => (string) ($row->submitted_by_name ?: '—'),
                'status' => trim((string) ($row->remarks ?? '')) !== '' ? (string) $row->remarks : '—',
                'date' => $displayDate,
                'source' => 'acceleration_7_2',
                'session_id' => (int) $row->session_id,
            ];
        }

        return $records;
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

    private static function scopedSessionsQuery(?Carbon $periodFrom, ?Carbon $periodTo): Builder
    {
        $query = DB::table('acceleration_service_sessions as s');

        if (Schema::hasColumn('acceleration_service_sessions', 'is_draft')) {
            $query->where(function ($q): void {
                $q->where('s.is_draft', false)->orWhereNull('s.is_draft');
            });
        }

        // Maker–checker workflow: only fully approved entries count.
        if (Schema::hasColumn('acceleration_service_sessions', 'status')) {
            $query->where('s.status', AccelerationServicesApproval::STATUS_APPROVED);
        }

        if ($periodFrom && $periodTo) {
            $query->whereBetween('s.service_date', [
                $periodFrom->toDateString(),
                $periodTo->toDateString(),
            ]);
        }

        return $query;
    }

    private static function scopedItemsQuery(?Carbon $periodFrom, ?Carbon $periodTo): Builder
    {
        $query = DB::table('acceleration_service_items as i')
            ->join('acceleration_service_sessions as s', 's.id', '=', 'i.session_id');

        if (Schema::hasColumn('acceleration_service_sessions', 'is_draft')) {
            $query->where(function ($q): void {
                $q->where('s.is_draft', false)->orWhereNull('s.is_draft');
            });
        }

        if (Schema::hasColumn('acceleration_service_sessions', 'status')) {
            $query->where('s.status', AccelerationServicesApproval::STATUS_APPROVED);
        }

        if ($periodFrom && $periodTo) {
            $query->whereBetween('s.service_date', [
                $periodFrom->toDateString(),
                $periodTo->toDateString(),
            ]);
        }

        return $query;
    }
}
