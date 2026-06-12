<?php

namespace App\Support;

use App\Models\MarketingPartnerOutreachEntry;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class MarketingPartnerOutreachDeliverablesSupport
{
    private const TABLE = 'marketing_partner_outreach_entries';

    public static function countOutreach(?Carbon $periodFrom, ?Carbon $periodTo): int
    {
        if (! Schema::hasTable(self::TABLE)) {
            return 0;
        }

        return (int) self::outreachQuery($periodFrom, $periodTo)->count();
    }

    public static function countOnboarded(?Carbon $periodFrom, ?Carbon $periodTo): int
    {
        if (! Schema::hasTable(self::TABLE)) {
            return 0;
        }

        return (int) self::onboardedQuery($periodFrom, $periodTo)->count();
    }

    /**
     * @return array<string, mixed>
     */
    public static function outreachBreakdown(?Carbon $periodFrom, ?Carbon $periodTo): array
    {
        return self::buildBreakdown(self::outreachQuery($periodFrom, $periodTo), 'outreach_date', false);
    }

    /**
     * @return array<string, mixed>
     */
    public static function onboardedBreakdown(?Carbon $periodFrom, ?Carbon $periodTo): array
    {
        return self::buildBreakdown(self::onboardedQuery($periodFrom, $periodTo), 'onboarding_date', true);
    }

    private static function outreachQuery(?Carbon $periodFrom, ?Carbon $periodTo): Builder
    {
        $query = DB::table(self::TABLE.' as t')
            ->whereIn('t.status', MarketingPartnerOutreachEntry::STATUSES);

        self::applyPeriodFilter($query, 't.outreach_date', $periodFrom, $periodTo);

        return $query;
    }

    private static function onboardedQuery(?Carbon $periodFrom, ?Carbon $periodTo): Builder
    {
        $query = DB::table(self::TABLE.' as t')
            ->whereIn('t.status', MarketingPartnerOutreachEntry::ONBOARDED_STATUSES);

        self::applyPeriodFilter($query, 't.onboarding_date', $periodFrom, $periodTo);

        return $query;
    }

    /**
     * @return array<string, mixed>
     */
    private static function buildBreakdown(Builder $query, string $dateColumn, bool $onboarded): array
    {
        if (! Schema::hasTable(self::TABLE)) {
            return self::emptyBreakdown();
        }

        $rows = (clone $query)
            ->select([
                't.id',
                't.outreach_date',
                't.onboarding_date',
                't.partner_name',
                't.partner_designation',
                't.cohort_or_sector',
                't.cohort_or_sector_other',
                't.status',
                't.poc_name',
                't.poc_phone',
                't.submitted_by_name',
            ])
            ->orderByDesc($dateColumn === 'onboarding_date' ? 't.onboarding_date' : 't.outreach_date')
            ->orderByDesc('t.id')
            ->limit(500)
            ->get();

        $total = $rows->count();
        $byMonth = [];
        $records = [];

        foreach ($rows as $row) {
            $rawDate = trim((string) ($onboarded ? ($row->onboarding_date ?? '') : ($row->outreach_date ?? '')));
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
                'reference' => 'Partner #'.$row->id,
                'applicant' => (string) ($row->partner_name ?: '—'),
                'district' => PartnerOutreachOptions::cohortOrSectorDisplay(
                    (string) $row->cohort_or_sector,
                    $row->cohort_or_sector_other
                ),
                'hub' => 'State',
                'service' => (string) ($row->partner_designation ?: '—'),
                'spoc' => (string) ($row->submitted_by_name ?: '—'),
                'status' => PartnerOutreachOptions::statusLabel((string) $row->status),
                'date' => $displayDate,
            ];
        }

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
}
