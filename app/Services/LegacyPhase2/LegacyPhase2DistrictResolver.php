<?php

namespace App\Services\LegacyPhase2;

use Illuminate\Database\Query\Builder;

class LegacyPhase2DistrictResolver
{
    /** @return array<string, list<string>> */
    public static function districtAliases(): array
    {
        /** @var array<string, list<string>> $aliases */
        $aliases = config('legacy_phase2.staff_import.district_aliases', []);

        return $aliases;
    }

    /** @return list<string> */
    public static function legacyNormsForCanonical(string $canonicalName): array
    {
        $base = [mb_strtolower(trim($canonicalName))];
        $extra = self::districtAliases()[$canonicalName] ?? [];

        return array_values(array_unique(array_merge($base, $extra)));
    }

    public static function applyDistrictFilter(Builder $query, string $canonicalDistrictName): void
    {
        $norms = self::legacyNormsForCanonical($canonicalDistrictName);
        $query->where(function (Builder $q) use ($norms): void {
            foreach ($norms as $norm) {
                $q->orWhereRaw('LOWER(TRIM(d.district)) = ?', [$norm]);
            }
        });
    }

    public static function isOnboardedFromStatus(?string $status): bool
    {
        return trim((string) $status) !== '';
    }

    public static function onboardLabel(?string $status): string
    {
        return self::isOnboardedFromStatus($status) ? 'Onboarded' : 'Non onboarded';
    }

    public static function applyOnboardFilter(Builder $query, string $filter): void
    {
        if ($filter === 'onboarded') {
            $query->whereNotNull('oa.status')->where('oa.status', '!=', '');

            return;
        }

        if ($filter === 'non_onboarded') {
            $query->where(function (Builder $q): void {
                $q->whereNull('oa.status')->orWhere('oa.status', '');
            });
        }
    }

    public static function countOnboarded(Builder $query): int
    {
        $clone = clone $query;

        return (int) $clone->whereNotNull('oa.status')->where('oa.status', '!=', '')->count();
    }
}
