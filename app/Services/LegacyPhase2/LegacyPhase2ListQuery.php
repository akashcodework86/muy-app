<?php

namespace App\Services\LegacyPhase2;

use App\Models\District;
use App\Models\FiscalYear;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LegacyPhase2ListQuery
{
    public const BLANK = '__blank__';

    public static function baseTable(): Builder
    {
        return DB::connection('legacy')->table('rbi_applications as a');
    }

    public static function listQueryForFyWindow(string $start, string $end): Builder
    {
        return self::baseTable()
            ->leftJoin('rbi_applicant_details as d', 'd.application_id', '=', 'a.id')
            ->leftJoin('rbi_onboarded_applicants as oa', 'oa.application_id', '=', 'a.id')
            ->whereNotNull('a.submission_date')
            ->whereBetween(DB::raw('DATE(a.submission_date)'), [$start, $end])
            ->select(self::adminSelectColumns());
    }

    public static function districtListQuery(string $canonicalDistrictName): Builder
    {
        $query = DB::connection('legacy')
            ->table('rbi_applicant_details as d')
            ->leftJoin('rbi_applications as a', 'a.id', '=', 'd.application_id')
            ->leftJoin('rbi_onboarded_applicants as oa', 'oa.application_id', '=', 'd.application_id')
            ->leftJoin('rbi_onboarding_batches as ob', 'ob.id', '=', 'oa.onboarding_batch_id')
            ->leftJoin(DB::raw('(
                SELECT e1.application_id, e1.turnover_last_year
                FROM rbi_enterprise_details e1
                INNER JOIN (
                    SELECT application_id, MAX(id) AS max_id
                    FROM rbi_enterprise_details
                    GROUP BY application_id
                ) t ON t.application_id = e1.application_id AND t.max_id = e1.id
            ) as ed'), 'ed.application_id', '=', 'd.application_id');

        LegacyPhase2DistrictResolver::applyDistrictFilter($query, $canonicalDistrictName);

        return $query->select([
            'd.application_id',
            'd.applicant_name',
            'd.phone',
            'd.district',
            'd.block',
            'd.village',
            'd.gender',
            'd.is_shg_member',
            'd.caste',
            'd.loan_taken',
            'd.bank_loan',
            'a.application_no',
            'a.product',
            'a.category as app_category',
            'a.form_stage',
            'a.submission_date',
            'a.created_at',
            'a.business_category',
            'ob.batch_name as cohort_name',
            'oa.status as onboard_status_db',
            'ed.turnover_last_year as turnover_last_year',
        ]);
    }

    /**
     * @return list<string>
     */
    public static function adminSelectColumns(): array
    {
        return [
            'a.id as legacy_id',
            'a.application_no',
            'a.category',
            'a.form_stage',
            'a.submission_date',
            'd.applicant_name',
            'd.phone',
            'd.district',
            'd.block',
            'd.village',
            'd.gender',
            'd.submitted_by_name',
            'oa.status as onboard_status_db',
        ];
    }

    /**
     * @return list<string>
     */
    public static function filterParamNames(bool $includeDistrict = true): array
    {
        $params = ['search', 'category', 'form_stage', 'gender', 'onboard'];
        if ($includeDistrict) {
            array_unshift($params, 'district');
        }

        return $params;
    }

    public static function hasActiveFilters(Request $request, bool $includeDistrict = true): bool
    {
        return $request->hasAny(self::filterParamNames($includeDistrict));
    }

    public static function normalizeOnboardParam(Request $request): string
    {
        $onboard = trim((string) $request->input('onboard', ''));
        if ($onboard === '') {
            $legacy = trim((string) $request->input('onboarding_status', ''));
            if ($legacy === 'yes') {
                return 'onboarded';
            }
            if ($legacy === 'no') {
                return 'non_onboarded';
            }
        }

        return $onboard;
    }

    public static function applyFilters(Builder $query, Request $request, ?string $lockDistrict = null, bool $includeOnboard = true): void
    {
        if ($lockDistrict !== null && $lockDistrict !== '') {
            LegacyPhase2DistrictResolver::applyDistrictFilter($query, $lockDistrict);
        } else {
            $districtFilter = trim((string) $request->input('district', ''));
            if ($districtFilter !== '') {
                LegacyPhase2DistrictResolver::applyDistrictFilter($query, $districtFilter);
            }
        }

        if ($request->filled('category')) {
            $query->where('a.category', (string) $request->input('category'));
        }

        if ($request->filled('form_stage')) {
            $query->where('a.form_stage', (string) $request->input('form_stage'));
        }

        self::applyColumnFilter($query, 'd.gender', trim((string) $request->input('gender', '')));

        if ($request->filled('search')) {
            $raw = trim((string) $request->input('search'));
            $search = '%'.$raw.'%';
            $query->where(function (Builder $q) use ($search, $raw): void {
                $q->where('d.applicant_name', 'like', $search)
                    ->orWhere('d.phone', 'like', $search)
                    ->orWhere('a.application_no', 'like', $search);
                if ($raw !== '' && ctype_digit($raw)) {
                    $id = (int) $raw;
                    $q->orWhere('a.id', $id)->orWhere('d.application_id', $id);
                }
            });
        }

        if ($includeOnboard) {
            $onboard = self::normalizeOnboardParam($request);
            if (in_array($onboard, ['onboarded', 'non_onboarded'], true)) {
                LegacyPhase2DistrictResolver::applyOnboardFilter($query, $onboard);
            }
        }
    }

    public static function applyColumnFilter(Builder $query, string $column, string $value): void
    {
        if ($value === '') {
            return;
        }

        if ($value === self::BLANK) {
            $query->where(function (Builder $q) use ($column): void {
                $q->whereNull($column)->orWhere($column, '');
            });

            return;
        }

        $query->whereRaw('TRIM('.$column.') = ?', [trim($value)]);
    }

    /**
     * @return array{
     *     categories: list<string>,
     *     form_stages: list<string>,
     *     genders: list<string>
     * }
     */
    /**
     * @return array{categories: list<string>, form_stages: list<string>, genders: list<string>}
     */
    public static function filterOptionsForDistrict(string $lockDistrict): array
    {
        if ((string) config('database.connections.legacy.database', '') === '') {
            return ['categories' => [], 'form_stages' => [], 'genders' => []];
        }

        try {
            $base = self::districtListQuery($lockDistrict);

            return [
                'categories' => self::distinctOn($base, 'a.category'),
                'form_stages' => self::distinctOn($base, 'a.form_stage'),
                'genders' => self::distinctOn($base, 'd.gender'),
            ];
        } catch (\Throwable) {
            return ['categories' => [], 'form_stages' => [], 'genders' => []];
        }
    }

    public static function filterOptions(string $start, string $end, ?string $lockDistrict = null): array
    {
        if ((string) config('database.connections.legacy.database', '') === '') {
            return ['categories' => [], 'form_stages' => [], 'genders' => []];
        }

        try {
            $base = self::listQueryForFyWindow($start, $end);
            if ($lockDistrict !== null && $lockDistrict !== '') {
                LegacyPhase2DistrictResolver::applyDistrictFilter($base, $lockDistrict);
            }

            return [
                'categories' => self::distinctOn($base, 'a.category'),
                'form_stages' => self::distinctOn($base, 'a.form_stage'),
                'genders' => self::distinctOn($base, 'd.gender'),
            ];
        } catch (\Throwable) {
            return ['categories' => [], 'form_stages' => [], 'genders' => []];
        }
    }

    /**
     * @return list<string>
     */
    public static function canonicalDistricts(): array
    {
        return District::query()->orderBy('name')->pluck('name')->all();
    }

    /**
     * @return array{total: int, onboarded: int, non_onboarded: int}
     */
    public static function scopeCounts(Request $request, string $start, string $end, ?string $lockDistrict = null): array
    {
        $countQuery = self::listQueryForFyWindow($start, $end);
        self::applyFilters($countQuery, $request, $lockDistrict, includeOnboard: false);

        return self::countOnboardFromQuery($countQuery);
    }

    /**
     * @return array{total: int, onboarded: int, non_onboarded: int}
     */
    public static function scopeCountsForDistrict(Request $request, string $lockDistrict): array
    {
        $countQuery = self::districtListQuery($lockDistrict);
        self::applyFilters($countQuery, $request, $lockDistrict, includeOnboard: false);

        return self::countOnboardFromQuery($countQuery);
    }

    /**
     * @return array{total: int, onboarded: int, non_onboarded: int}
     */
    private static function countOnboardFromQuery(Builder $query): array
    {
        $total = (int) $query->count();
        $onboarded = LegacyPhase2DistrictResolver::countOnboarded(clone $query);

        return [
            'total' => $total,
            'onboarded' => $onboarded,
            'non_onboarded' => max(0, $total - $onboarded),
        ];
    }

    public static function enrichRow(object $row): object
    {
        $status = $row->onboard_status_db ?? null;
        $row->onboard_status = LegacyPhase2DistrictResolver::isOnboardedFromStatus(
            is_string($status) ? $status : null
        ) ? 'onboarded' : 'non_onboarded';
        $row->onboard_label = LegacyPhase2DistrictResolver::onboardLabel(is_string($status) ? $status : null);

        return $row;
    }

    /**
     * @return array{0: string, 1: string}
     */
    public static function fyWindowDates(FiscalYear $fiscalYear): array
    {
        return [
            $fiscalYear->starts_on->toDateString(),
            $fiscalYear->ends_on->toDateString(),
        ];
    }

    /**
     * @return list<string>
     */
    private static function distinctOn(Builder $base, string $column): array
    {
        $clone = clone $base;

        return $clone
            ->whereNotNull($column)
            ->where($column, '!=', '')
            ->selectRaw('DISTINCT TRIM('.$column.') as v')
            ->orderBy('v')
            ->pluck('v')
            ->map(fn ($v) => trim((string) $v))
            ->filter(fn ($v) => $v !== '')
            ->values()
            ->all();
    }
}
