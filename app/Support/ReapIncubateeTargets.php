<?php

namespace App\Support;

final class ReapIncubateeTargets
{
    public const BUCKET_FARM_1_LAKH = 'farm_1_lakh';

    public const BUCKET_FARM_3_LAKH = 'farm_3_lakh';

    public const BUCKET_NON_FARM_1_LAKH = 'non_farm_1_lakh';

    public const BUCKET_NON_FARM_3_LAKH = 'non_farm_3_lakh';

    /**
     * @return list<string>
     */
    public static function bucketKeys(): array
    {
        return [
            self::BUCKET_FARM_1_LAKH,
            self::BUCKET_FARM_3_LAKH,
            self::BUCKET_NON_FARM_1_LAKH,
            self::BUCKET_NON_FARM_3_LAKH,
        ];
    }

    public static function bucketLabel(string $bucket): string
    {
        return match ($bucket) {
            self::BUCKET_FARM_1_LAKH => 'Farm · 1 Lakh',
            self::BUCKET_FARM_3_LAKH => 'Farm · 3 Lakh',
            self::BUCKET_NON_FARM_1_LAKH => 'Non-farm · 1 Lakh',
            self::BUCKET_NON_FARM_3_LAKH => 'Non-farm · 3 Lakh',
            default => $bucket,
        };
    }

    public static function bucketShortLabel(string $bucket): string
    {
        return match ($bucket) {
            self::BUCKET_FARM_1_LAKH => '1 Lakh',
            self::BUCKET_FARM_3_LAKH => '3 Lakh',
            default => self::bucketLabel($bucket),
        };
    }

    public static function bucketRowLabel(string $bucket): string
    {
        return match ($bucket) {
            self::BUCKET_FARM_1_LAKH, self::BUCKET_FARM_3_LAKH => 'Farm',
            self::BUCKET_NON_FARM_1_LAKH, self::BUCKET_NON_FARM_3_LAKH => 'Non-farm',
            default => '—',
        };
    }

    public static function bucketFromPayload(?string $sector, ?string $amount): ?string
    {
        $sector = strtolower(trim((string) $sector));
        $amount = strtolower(trim((string) $amount));

        return match (true) {
            $sector === 'farm' && $amount === '1_lakh' => self::BUCKET_FARM_1_LAKH,
            $sector === 'farm' && $amount === '3_lakh' => self::BUCKET_FARM_3_LAKH,
            $sector === 'non_farm' && $amount === '1_lakh' => self::BUCKET_NON_FARM_1_LAKH,
            $sector === 'non_farm' && $amount === '3_lakh' => self::BUCKET_NON_FARM_3_LAKH,
            default => null,
        };
    }

    public static function configuredFiscalYearCode(): string
    {
        return (string) config('reap_incubatee_targets.fiscal_year_code', '2026-27');
    }

    /**
     * @return array<string, int>
     */
    public static function targetsForDistrictSlug(?string $slug): array
    {
        $slug = trim((string) $slug);
        if ($slug === '') {
            return self::emptyBucketCounts();
        }

        $row = (array) config('reap_incubatee_targets.districts.'.$slug, []);

        return self::normalizeTargets($row);
    }

    /**
     * @return array<string, int>
     */
    public static function emptyBucketCounts(): array
    {
        $out = [];
        foreach (self::bucketKeys() as $bucket) {
            $out[$bucket] = 0;
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, int>
     */
    public static function normalizeTargets(array $row): array
    {
        $out = self::emptyBucketCounts();
        foreach (self::bucketKeys() as $bucket) {
            $out[$bucket] = max(0, (int) ($row[$bucket] ?? 0));
        }

        return $out;
    }

    /**
     * @param  array<string, int>  $buckets
     */
    public static function sumBuckets(array $buckets): int
    {
        return array_sum(array_values($buckets));
    }

    /**
     * @param  array<string, int>  $targets
     * @param  array<string, int>  $approved
     * @return array{
     *     buckets: array<string, array{key: string, label: string, row_label: string, short_label: string, target: int, approved: int, remaining: int, pct: int|null}>,
     *     totals: array{target: int, approved: int, remaining: int, pct: int|null},
     *     balance_pct: int|null,
     *     weakest_bucket: string|null
     * }
     */
    public static function buildProgressSummary(array $targets, array $approved): array
    {
        $buckets = [];
        $weakestBucket = null;
        $weakestPct = null;

        foreach (self::bucketKeys() as $bucket) {
            $target = max(0, (int) ($targets[$bucket] ?? 0));
            $done = max(0, (int) ($approved[$bucket] ?? 0));
            $remaining = max(0, $target - $done);
            $pct = $target > 0 ? (int) min(100, round(($done / $target) * 100)) : null;

            $buckets[$bucket] = [
                'key' => $bucket,
                'label' => self::bucketLabel($bucket),
                'row_label' => self::bucketRowLabel($bucket),
                'short_label' => self::bucketShortLabel($bucket),
                'target' => $target,
                'approved' => $done,
                'remaining' => $remaining,
                'pct' => $pct,
            ];

            if ($target > 0) {
                $bucketPct = $pct ?? 0;
                if ($weakestPct === null || $bucketPct < $weakestPct) {
                    $weakestPct = $bucketPct;
                    $weakestBucket = $bucket;
                }
            }
        }

        $targetTotal = self::sumBuckets($targets);
        $approvedTotal = self::sumBuckets($approved);

        return [
            'buckets' => $buckets,
            'totals' => [
                'target' => $targetTotal,
                'approved' => $approvedTotal,
                'remaining' => max(0, $targetTotal - $approvedTotal),
                'pct' => $targetTotal > 0
                    ? (int) min(100, round(($approvedTotal / $targetTotal) * 100))
                    : null,
            ],
            'balance_pct' => $weakestPct,
            'weakest_bucket' => $weakestBucket,
        ];
    }
}
