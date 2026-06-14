<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class MediaCampaignEntriesDeliverablesSupport
{
    private const TABLE = 'media_campaign_entries';

    public static function countEntries(?Carbon $periodFrom, ?Carbon $periodTo): int
    {
        if (! Schema::hasTable(self::TABLE)) {
            return 0;
        }

        return (int) self::baseQuery($periodFrom, $periodTo)->count();
    }

    /**
     * @return array<string, mixed>
     */
    public static function breakdown(?Carbon $periodFrom, ?Carbon $periodTo): array
    {
        if (! Schema::hasTable(self::TABLE)) {
            return self::emptyBreakdown();
        }

        $rows = self::baseQuery($periodFrom, $periodTo)
            ->select([
                't.id',
                't.campaign_date',
                't.media_type',
                't.channel_name',
                't.coverage_area',
                't.campaign_title',
                't.submitted_by_name',
            ])
            ->orderByDesc('t.campaign_date')
            ->orderByDesc('t.id')
            ->limit(500)
            ->get();

        return self::buildBreakdown($rows, 'campaign_date', function ($row): array {
            return [
                'id' => (int) $row->id,
                'reference' => 'Campaign #'.$row->id,
                'applicant' => (string) ($row->campaign_title ?: '—'),
                'district' => (string) ($row->coverage_area ?: 'State'),
                'hub' => BrandingCommunicationOptions::mediaTypeLabel((string) $row->media_type),
                'service' => (string) ($row->channel_name ?: '—'),
                'spoc' => (string) ($row->submitted_by_name ?: '—'),
                'status' => 'Logged',
                'date' => self::formatDate($row->campaign_date),
            ];
        });
    }

    private static function baseQuery(?Carbon $periodFrom, ?Carbon $periodTo): Builder
    {
        $query = DB::table(self::TABLE.' as t');
        self::applyPeriodFilter($query, 't.campaign_date', $periodFrom, $periodTo);

        return $query;
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

    private static function formatDate(mixed $value): string
    {
        $raw = trim((string) ($value ?? ''));
        if ($raw === '') {
            return '—';
        }

        try {
            return Carbon::parse($raw)->format('d M Y');
        } catch (\Throwable) {
            return $raw;
        }
    }

    /**
     * @param  \Illuminate\Support\Collection<int, object>  $rows
     * @param  callable(object): array<string, mixed>  $recordMapper
     * @return array<string, mixed>
     */
    private static function buildBreakdown($rows, string $dateField, callable $recordMapper): array
    {
        $total = $rows->count();
        $byMonth = [];
        $records = [];

        foreach ($rows as $row) {
            $rawDate = trim((string) ($row->{$dateField} ?? ''));
            if ($rawDate !== '') {
                try {
                    $monthKey = Carbon::parse($rawDate)->format('Y-m');
                    $byMonth[$monthKey] = ($byMonth[$monthKey] ?? 0) + 1;
                } catch (\Throwable) {
                    // ignore
                }
            }

            $records[] = $recordMapper($row);
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
}
