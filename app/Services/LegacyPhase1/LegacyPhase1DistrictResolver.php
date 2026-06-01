<?php

namespace App\Services\LegacyPhase1;

use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class LegacyPhase1DistrictResolver
{
    /** @return array<string, list<string>> */
    public static function districtAliases(): array
    {
        /** @var array<string, list<string>> $aliases */
        $aliases = config('legacy_phase1.district_aliases', []);

        return $aliases;
    }

    /** @return list<string> */
    public static function canonicalDistricts(): array
    {
        return array_keys(self::districtAliases());
    }

    /** @return list<string> */
    public static function legacyKeysForDistrict(string $canonicalName): array
    {
        $keys = self::districtAliases()[$canonicalName] ?? [mb_strtolower(trim($canonicalName))];

        return array_values(array_unique($keys));
    }

    public static function canonicalNameForLegacyFatherName(?string $fatherName): ?string
    {
        $norm = mb_strtolower(trim((string) $fatherName));
        if ($norm === '') {
            return null;
        }

        foreach (self::districtAliases() as $canonical => $keys) {
            if (in_array($norm, $keys, true)) {
                return $canonical;
            }
        }

        return null;
    }

    public static function applyDistrictFilter(Builder $query, string $canonicalDistrictName): void
    {
        $keys = self::legacyKeysForDistrict($canonicalDistrictName);
        $query->whereIn(DB::raw('LOWER(TRIM(FatherName))'), $keys);
    }

    public static function applyLegacyRegionFilter(Builder $query, string $legacyRegion): void
    {
        $norm = mb_strtolower(trim($legacyRegion));
        if ($norm === '') {
            return;
        }

        $query->whereRaw('LOWER(TRIM(hub)) = ?', [$norm]);
    }

    public static function isOnboardedRaw(?string $onboard): bool
    {
        return mb_strtolower(trim((string) $onboard)) === 'yes';
    }

    public static function onboardLabel(?string $onboard): string
    {
        return self::isOnboardedRaw($onboard) ? 'Onboarded' : 'Non onboarded';
    }

    /**
     * @param  'onboarded'|'non_onboarded'  $filter
     */
    public static function applyOnboardFilter(Builder $query, string $filter): void
    {
        if ($filter === 'onboarded') {
            $query->whereRaw('LOWER(TRIM(onboard)) = ?', ['yes']);

            return;
        }

        if ($filter === 'non_onboarded') {
            $query->where(function (Builder $q): void {
                $q->whereNull('onboard')
                    ->orWhere('onboard', '')
                    ->orWhereRaw('LOWER(TRIM(onboard)) <> ?', ['yes']);
            });
        }
    }

    public static function countOnboarded(Builder $query): int
    {
        $clone = clone $query;

        return (int) $clone->whereRaw('LOWER(TRIM(onboard)) = ?', ['yes'])->count();
    }

    /**
     * @return list<string> Distinct legacy hub values (e.g. Garhwal, kumaon) present in DB.
     */
    public static function distinctLegacyRegionsFromDb(): array
    {
        if ((string) config('database.connections.legacy_phase1.database', '') === '') {
            return [];
        }

        try {
            return DB::connection('legacy_phase1')
                ->table('tblapplication')
                ->whereNotNull('hub')
                ->where('hub', '!=', '')
                ->selectRaw('DISTINCT hub as region')
                ->orderBy('region')
                ->pluck('region')
                ->map(fn ($v) => (string) $v)
                ->values()
                ->all();
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Standard select list for Phase 1 application rows (aliases for views).
     *
     * @return list<string|Expression>
     */
    public static function applicationSelectColumns(): array
    {
        return [
            'ID as legacy_id',
            'ApplicationNumber as application_no',
            'FullName as full_name',
            'MobileNumber as mobile_number',
            'FatherName as father_name_legacy',
            'hub as legacy_region',
            'City as city_name',
            'application_status',
            'gender',
            'education',
            'onboard as onboard_raw',
            'ApplicationDate as application_date',
        ];
    }

    /**
     * @param  object  $row  Row with father_name_legacy when present.
     */
    public static function enrichRow(object $row): object
    {
        $legacy = $row->father_name_legacy ?? $row->FatherName ?? null;
        $row->district_name = self::canonicalNameForLegacyFatherName(
            is_string($legacy) ? $legacy : null
        ) ?? (trim((string) $legacy) !== '' ? trim((string) $legacy) : null);

        $onboardRaw = $row->onboard_raw ?? $row->onboard ?? null;
        $row->onboard_status = self::isOnboardedRaw(is_string($onboardRaw) ? $onboardRaw : null)
            ? 'onboarded'
            : 'non_onboarded';
        $row->onboard_label = self::onboardLabel(is_string($onboardRaw) ? $onboardRaw : null);

        return $row;
    }
}
