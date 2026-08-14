<?php

namespace App\Services\LegacyPhase1;

use App\Services\Cfa\CfaSubmissionListQuery;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LegacyPhase1ListQuery
{
    public const BLANK = '__blank__';

    /** @var list<string> */
    public const FILTERABLE_COLUMNS = ['application_status', 'gender', 'education'];

    public static function baseTable(): Builder
    {
        return DB::connection('legacy_phase1')->table('tblapplication');
    }

    public static function listQuery(): Builder
    {
        return self::baseTable()->select(LegacyPhase1DistrictResolver::applicationSelectColumns());
    }

    /**
     * @return list<string>
     */
    public static function filterParamNames(bool $includeDistrict = true): array
    {
        $params = ['region', 'onboard', 'search', 'application_status', 'gender', 'education', 'caste'];
        if ($includeDistrict) {
            array_unshift($params, 'district');
        }

        return $params;
    }

    public static function hasActiveFilters(Request $request, bool $includeDistrict = true): bool
    {
        return $request->hasAny(self::filterParamNames($includeDistrict));
    }

    /**
     * @param  string|null  $lockDistrict  When set, always filter to this canonical district (staff view).
     */
    public static function applyFilters(Builder $query, Request $request, ?string $lockDistrict = null, bool $includeOnboard = true): void
    {
        if ($lockDistrict !== null && $lockDistrict !== '') {
            LegacyPhase1DistrictResolver::applyDistrictFilter($query, $lockDistrict);
        } else {
            $districtFilter = trim((string) $request->input('district', ''));
            if ($districtFilter !== '' && in_array($districtFilter, LegacyPhase1DistrictResolver::canonicalDistricts(), true)) {
                LegacyPhase1DistrictResolver::applyDistrictFilter($query, $districtFilter);
            }
        }

        $regionFilter = trim((string) $request->input('region', ''));
        if ($regionFilter !== '') {
            LegacyPhase1DistrictResolver::applyLegacyRegionFilter($query, $regionFilter);
        }

        self::applyColumnFilter($query, 'application_status', trim((string) $request->input('application_status', '')));
        self::applyColumnFilter($query, 'gender', trim((string) $request->input('gender', '')));
        self::applyColumnFilter($query, 'education', trim((string) $request->input('education', '')));
        CfaSubmissionListQuery::applyCasteColumnFilter($query, '`cast`', CfaSubmissionListQuery::normalizeCasteParam($request));

        if ($request->filled('search')) {
            $search = '%'.trim((string) $request->input('search')).'%';
            $query->where(function (Builder $q) use ($search): void {
                $q->where('FullName', 'like', $search)
                    ->orWhere('MobileNumber', 'like', $search)
                    ->orWhere('ApplicationNumber', 'like', $search);
            });
        }

        if ($includeOnboard) {
            $onboard = trim((string) $request->input('onboard', ''));
            if (in_array($onboard, ['onboarded', 'non_onboarded'], true)) {
                LegacyPhase1DistrictResolver::applyOnboardFilter($query, $onboard);
            }
        }
    }

    public static function applyColumnFilter(Builder $query, string $column, string $value): void
    {
        if ($value === '' || ! in_array($column, self::FILTERABLE_COLUMNS, true)) {
            return;
        }

        if ($value === self::BLANK) {
            $query->where(function (Builder $q) use ($column): void {
                $q->whereNull($column)->orWhere($column, '');
            });

            return;
        }

        $query->whereRaw('TRIM(`'.$column.'`) = ?', [trim($value)]);
    }

    /**
     * @return array{
     *     legacyRegions: list<string>,
     *     applicationStatuses: list<string>,
     *     genders: list<string>,
     *     educations: list<string>
     * }
     */
    public static function filterOptions(): array
    {
        if ((string) config('database.connections.legacy_phase1.database', '') === '') {
            return [
                'legacyRegions' => [],
                'applicationStatuses' => [],
                'genders' => [],
                'educations' => [],
            ];
        }

        try {
            return [
                'legacyRegions' => LegacyPhase1DistrictResolver::distinctLegacyRegionsFromDb(),
                'applicationStatuses' => self::distinctColumnValues('application_status'),
                'genders' => self::distinctColumnValues('gender'),
                'educations' => self::distinctColumnValues('education'),
            ];
        } catch (\Throwable) {
            return [
                'legacyRegions' => [],
                'applicationStatuses' => [],
                'genders' => [],
                'educations' => [],
            ];
        }
    }

    /**
     * @return list<string>
     */
    public static function distinctColumnValues(string $column, int $limit = 40): array
    {
        if (! in_array($column, self::FILTERABLE_COLUMNS, true)) {
            return [];
        }

        return self::baseTable()
            ->whereNotNull($column)
            ->where($column, '!=', '')
            ->selectRaw('TRIM(`'.$column.'`) as v')
            ->distinct()
            ->orderBy('v')
            ->limit($limit)
            ->pluck('v')
            ->map(fn ($v) => trim((string) $v))
            ->filter(fn ($v) => $v !== '')
            ->values()
            ->all();
    }

    /**
     * @return array{total: int, onboarded: int, non_onboarded: int}
     */
    public static function scopeCounts(Request $request, ?string $lockDistrict = null): array
    {
        $countQuery = self::baseTable();
        self::applyFilters($countQuery, $request, $lockDistrict, includeOnboard: false);

        $total = (int) $countQuery->count();
        $onboarded = LegacyPhase1DistrictResolver::countOnboarded(clone $countQuery);

        return [
            'total' => $total,
            'onboarded' => $onboarded,
            'non_onboarded' => max(0, $total - $onboarded),
        ];
    }
}
