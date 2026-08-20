<?php

namespace App\Services\LegacyPhase2;

use App\Models\District;
use App\Models\FiscalYear;
use App\Services\Cfa\CfaSubmissionListQuery;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class LegacyPhase2ListQuery
{
    public const BLANK = '__blank__';

    public static function baseTable(): Builder
    {
        return DB::connection('legacy')->table('rbi_applications as a');
    }

    public static function listQueryForFyWindow(string $start, string $end): Builder
    {
        return self::queryWithLatestApplicantDetails()
            ->whereNotNull('a.submission_date')
            ->whereBetween(DB::raw('DATE(a.submission_date)'), [$start, $end])
            ->select(self::adminSelectColumns());
    }

    /**
     * Full export query: latest {@see rbi_applicant_details} + {@see rbi_applications} columns.
     */
    public static function exportQueryForFyWindow(string $start, string $end): Builder
    {
        $query = self::queryWithLatestApplicantDetails()
            ->leftJoin('rbi_onboarding_batches as ob', 'ob.id', '=', 'oa.onboarding_batch_id');

        if (self::legacyHasTable('rbi_enterprise_details')) {
            $query->leftJoin(DB::raw('(
                SELECT e1.application_id, e1.turnover_last_year
                FROM rbi_enterprise_details e1
                INNER JOIN (
                    SELECT application_id, MAX(id) AS max_id
                    FROM rbi_enterprise_details
                    GROUP BY application_id
                ) t ON t.application_id = e1.application_id AND t.max_id = e1.id
            ) as ed'), 'ed.application_id', '=', 'd.application_id');
        }

        return $query
            ->whereNotNull('a.submission_date')
            ->whereBetween(DB::raw('DATE(a.submission_date)'), [$start, $end])
            ->select(self::exportSelectColumns());
    }

    /**
     * @return list{array{key: string, label: string}}
     */
    public static function exportColumnDefinitions(): array
    {
        $columns = [
            ['key' => 'sr_no', 'label' => 'Sr No'],
        ];

        foreach (self::legacyTableColumns('rbi_applications') as $col) {
            $columns[] = ['key' => 'app_'.$col, 'label' => 'app_'.$col];
        }

        foreach (self::legacyTableColumns('rbi_applicant_details') as $col) {
            $columns[] = ['key' => 'detail_'.$col, 'label' => 'detail_'.$col];
        }

        $columns[] = ['key' => 'onboarding_batch_name', 'label' => 'onboarding_batch_name'];
        $columns[] = ['key' => 'onboard_status_raw', 'label' => 'onboard_status_raw'];
        $columns[] = ['key' => 'onboard_status', 'label' => 'onboard_status'];

        if (self::legacyHasTable('rbi_enterprise_details')) {
            $columns[] = ['key' => 'enterprise_turnover_last_year', 'label' => 'enterprise_turnover_last_year'];
        }

        return $columns;
    }

    /**
     * @return list<string|mixed>
     */
    public static function exportRowValues(object $row, int $srNo): array
    {
        $row = self::enrichRow($row);
        $data = (array) $row;
        $values = [(string) $srNo];

        foreach (self::exportColumnDefinitions() as $def) {
            if ($def['key'] === 'sr_no') {
                continue;
            }

            if ($def['key'] === 'onboard_status') {
                $values[] = (string) ($row->onboard_label ?? 'Non onboarded');

                continue;
            }

            if ($def['key'] === 'onboard_status_raw') {
                $values[] = self::csvCell($data['onboard_status_db'] ?? null);

                continue;
            }

            $values[] = self::csvCell($data[$def['key']] ?? null, $def['key']);
        }

        return $values;
    }

    /**
     * @return list<string|mixed>
     */
    public static function exportHeaderLabels(): array
    {
        return array_map(
            fn (array $def): string => $def['label'],
            self::exportColumnDefinitions()
        );
    }

    /**
     * @return list<string|Expression>
     */
    private static function exportSelectColumns(): array
    {
        $select = [];

        foreach (self::legacyTableColumns('rbi_applications') as $col) {
            $select[] = 'a.'.$col.' as app_'.$col;
        }

        foreach (self::legacyTableColumns('rbi_applicant_details') as $col) {
            $select[] = 'd.'.$col.' as detail_'.$col;
        }

        $select[] = 'ob.batch_name as onboarding_batch_name';
        $select[] = 'oa.status as onboard_status_db';

        if (self::legacyHasTable('rbi_enterprise_details')) {
            $select[] = 'ed.turnover_last_year as enterprise_turnover_last_year';
        }

        return $select;
    }

    /**
     * @return list<string>
     */
    private static function legacyTableColumns(string $table): array
    {
        static $cache = [];

        if (isset($cache[$table])) {
            return $cache[$table];
        }

        try {
            if ((string) config('database.connections.legacy.database', '') !== ''
                && Schema::connection('legacy')->hasTable($table)) {
                $cache[$table] = Schema::connection('legacy')->getColumnListing($table);

                return $cache[$table];
            }
        } catch (\Throwable) {
            // fall through to static fallback
        }

        $cache[$table] = match ($table) {
            'rbi_applicant_details' => self::fallbackApplicantDetailColumns(),
            'rbi_applications' => self::fallbackApplicationColumns(),
            default => [],
        };

        return $cache[$table];
    }

    private static function legacyHasTable(string $table): bool
    {
        try {
            return (string) config('database.connections.legacy.database', '') !== ''
                && Schema::connection('legacy')->hasTable($table);
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @return list<string>
     */
    private static function fallbackApplicantDetailColumns(): array
    {
        return [
            'id', 'application_id', 'applicant_name', 'guardian_name', 'gender', 'dob', 'education',
            'phone', 'alt_mobile', 'email', 'caste', 'is_shg_member', 'shg_name', 'lakhpati',
            'district', 'block', 'pincode', 'village', 'loan_taken', 'bank_loan', 'current_employment',
            'employed_count', 'id_proof_type', 'id_proof_number', 'expectations', 'training_mode',
            'challenges', 'expectation_other', 'migrated_for_employment', 'submitted_by_name',
            'submitted_by_mobile', 'department_name', 'info_source', 'resource_name',
        ];
    }

    /**
     * @return list<string>
     */
    private static function fallbackApplicationColumns(): array
    {
        return [
            'id', 'application_no', 'product', 'category', 'form_stage', 'business_category',
            'submission_date', 'created_at', 'updated_at',
        ];
    }

    private static function csvCell(mixed $value, ?string $key = null): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_scalar($value)) {
            $text = (string) $value;
            if ($key !== null && self::shouldTabPrefixForExcel($key, $text)) {
                return "\t".$text;
            }

            return $text;
        }

        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
    }

    private static function shouldTabPrefixForExcel(string $key, string $text): bool
    {
        if ($text === '') {
            return false;
        }

        $numericKeys = [
            'detail_phone', 'detail_alt_mobile', 'detail_submitted_by_mobile',
            'detail_id_proof_number', 'detail_pincode', 'app_application_no',
        ];

        if (in_array($key, $numericKeys, true)) {
            return preg_match('/^\d{6,}$/', $text) === 1;
        }

        return false;
    }

    public static function latestApplicantListQuery(): Builder
    {
        return self::queryWithLatestApplicantDetails()
            ->leftJoin('rbi_onboarding_batches as ob', 'ob.id', '=', 'oa.onboarding_batch_id')
            ->leftJoin(DB::raw('(
                SELECT e1.application_id, e1.turnover_last_year
                FROM rbi_enterprise_details e1
                INNER JOIN (
                    SELECT application_id, MAX(id) AS max_id
                    FROM rbi_enterprise_details
                    GROUP BY application_id
                ) t ON t.application_id = e1.application_id AND t.max_id = e1.id
            ) as ed'), 'ed.application_id', '=', 'd.application_id')
            ->select([
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

    public static function districtListQuery(string $canonicalDistrictName): Builder
    {
        $query = self::latestApplicantListQuery();
        LegacyPhase2DistrictResolver::applyDistrictFilter($query, $canonicalDistrictName);

        return $query;
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
        $params = ['search', 'category', 'form_stage', 'gender', 'onboard', 'caste'];
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
        CfaSubmissionListQuery::applyCasteColumnFilter($query, 'd.caste', CfaSubmissionListQuery::normalizeCasteParam($request));

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
     * One row per application using the latest {@see rbi_applicant_details} row (highest id).
     */
    private static function queryWithLatestApplicantDetails(): Builder
    {
        return DB::connection('legacy')
            ->table('rbi_applicant_details as d')
            ->join(DB::raw('(
                SELECT application_id, MAX(id) AS max_id
                FROM rbi_applicant_details
                GROUP BY application_id
            ) as d_pick'), 'd_pick.max_id', '=', 'd.id')
            ->join('rbi_applications as a', 'a.id', '=', 'd.application_id')
            ->leftJoin('rbi_onboarded_applicants as oa', 'oa.application_id', '=', 'd.application_id');
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
