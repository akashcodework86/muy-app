<?php

namespace App\Support;

use App\Services\MarketLinkagePartnerCatalogService;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class FundingSchematicPartnersOutreachDeliverablesSupport
{
    private const TABLE = 'funding_schematic_partner_outreach_entries';

    public static function countUniquePartners(?Carbon $periodFrom, ?Carbon $periodTo): int
    {
        if (! Schema::hasTable(self::TABLE)) {
            return 0;
        }

        $names = self::scopedQuery($periodFrom, $periodTo)
            ->distinct()
            ->pluck('t.partner_name');

        return app(MarketLinkagePartnerCatalogService::class)->countUniquePartnerKeys($names);
    }

    /**
     * @return array<string, mixed>
     */
    public static function breakdown(?Carbon $periodFrom, ?Carbon $periodTo): array
    {
        if (! Schema::hasTable(self::TABLE)) {
            return self::emptyBreakdown();
        }

        $rows = self::scopedQuery($periodFrom, $periodTo)
            ->select([
                't.id',
                't.batch_id',
                't.outreach_date',
                't.outreach_mode',
                't.partner_name',
                't.partner_type',
                't.partner_type_other',
                't.contact_name',
                't.designation',
                't.poc_phone',
                't.submitted_by_name',
            ])
            ->orderByDesc('t.outreach_date')
            ->orderByDesc('t.id')
            ->limit(500)
            ->get();

        $catalog = app(MarketLinkagePartnerCatalogService::class);
        $seenKeys = [];
        $records = [];
        $byMonth = [];

        foreach ($rows as $row) {
            $key = strtolower(trim((string) $row->partner_name));
            if ($key === '' || isset($seenKeys[$key])) {
                continue;
            }
            $seenKeys[$key] = true;

            $rawDate = trim((string) ($row->outreach_date ?? ''));
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
                'reference' => $catalog->displayLabelFor((string) $row->partner_name),
                'applicant' => FundingSchematicPartnersOutreachOptions::partnerTypeLabel(
                    (string) $row->partner_type,
                    $row->partner_type_other
                ),
                'district' => 'State',
                'hub' => '—',
                'service' => FundingSchematicPartnersOutreachOptions::outreachModeLabel((string) $row->outreach_mode),
                'spoc' => (string) ($row->submitted_by_name ?: '—'),
                'status' => (string) ($row->contact_name ?: '—'),
                'date' => $displayDate,
            ];
        }

        $total = count($seenKeys);

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

    private static function scopedQuery(?Carbon $periodFrom, ?Carbon $periodTo): Builder
    {
        $query = DB::table(self::TABLE.' as t');

        if ($periodFrom && $periodTo) {
            $query->whereBetween('t.outreach_date', [
                $periodFrom->toDateString(),
                $periodTo->toDateString(),
            ]);
        }

        return $query;
    }
}
