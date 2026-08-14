<?php

namespace App\Services\Cfa;

use App\Models\CfaSubmission;
use App\Models\FiscalYear;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CfaSubmissionListQuery
{
    /**
     * Same CFA scope as the state dashboard hero counts.
     */
    public static function applyPhase3DashboardScope(Builder $query): Builder
    {
        $phase3FloorDate = Carbon::create(2026, 4, 1)->startOfDay();
        $activeFyId = (int) (optional(FiscalYear::phase3Default())->id ?? 0);

        return $query->when(
            $activeFyId > 0,
            fn ($q) => $q->where('fiscal_year_id', $activeFyId),
            fn ($q) => $q->where('created_at', '>=', $phase3FloorDate)
        );
    }

    public static function applyOnboardFilter(Builder $query, string $filter): void
    {
        if ($filter === 'onboarded') {
            $query->whereHas('onboardingBatchMembership');

            return;
        }

        if ($filter === 'non_onboarded') {
            $query->whereDoesntHave('onboardingBatchMembership');
        }
    }

    public static function normalizeOnboardParam(Request $request): string
    {
        return trim((string) $request->input('onboard', ''));
    }

    /**
     * @return list<string>
     */
    public static function filterParamNames(): array
    {
        return ['name', 'application_no', 'district_id', 'block', 'sector', 'caste', 'submitted_by', 'designation_id', 'from', 'to', 'onboard'];
    }

    public static function hasActiveFilters(Request $request): bool
    {
        return $request->hasAny(self::filterParamNames());
    }

    /**
     * @param  array{name: string, application_no: string, district_id: int|null, block: string, sector: string, caste: string, submitted_by: int|null, designation_id: int|null, from: string, to: string, onboard: string}  $filters
     */
    public static function applyFilters(Builder $query, array $filters, bool $includeOnboard = true): Builder
    {
        $searchByApplicationNo = ($filters['application_no'] ?? '') !== '';

        if (! $searchByApplicationNo) {
            self::applyPhase3DashboardScope($query);
        }

        $query
            ->when($searchByApplicationNo, function (Builder $q) use ($filters): void {
                $term = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $filters['application_no']);
                $q->where(function (Builder $inner) use ($term, $filters): void {
                    $inner->where('application_no', 'like', '%'.$term.'%');
                    if (ctype_digit($filters['application_no'])) {
                        $inner->orWhere('id', (int) $filters['application_no']);
                    }
                });
            })
            ->when(($filters['name'] ?? '') !== '', fn ($q) => $q->where('applicant_name', 'like', '%'.$filters['name'].'%'))
            ->when(! empty($filters['district_id']), fn ($q) => $q->where('district_id', (int) $filters['district_id']))
            ->when(($filters['block'] ?? '') !== '', fn ($q) => $q->whereRaw(
                self::payloadJsonExpr('$.block').' = ?',
                [$filters['block']]
            ))
            ->when(($filters['sector'] ?? '') !== '', fn ($q) => $q->whereRaw(
                self::payloadJsonExpr('$.business_category').' = ?',
                [$filters['sector']]
            ))
            ->when(self::casteFilterValues($filters['caste'] ?? '') !== [], function (Builder $q) use ($filters): void {
                self::applyCasteColumnFilter($q, self::payloadJsonExpr('$.caste'), $filters['caste']);
            })
            ->when(! empty($filters['submitted_by']), fn ($q) => $q->where('referral_user_id', (int) $filters['submitted_by']))
            ->when(! empty($filters['designation_id']), fn ($q) => $q->whereHas(
                'referralUser',
                fn (Builder $userQuery) => $userQuery->where('designation_id', (int) $filters['designation_id'])
            ))
            ->when(($filters['from'] ?? '') !== '', fn ($q) => $q->whereDate('created_at', '>=', $filters['from']))
            ->when(($filters['to'] ?? '') !== '', fn ($q) => $q->whereDate('created_at', '<=', $filters['to']));

        if ($includeOnboard) {
            $onboard = $filters['onboard'] ?? '';
            if (in_array($onboard, ['onboarded', 'non_onboarded'], true)) {
                self::applyOnboardFilter($query, $onboard);
            }
        }

        return $query;
    }

    /**
     * @return array{total: int, onboarded: int, non_onboarded: int, districts: int, blocks: int}
     */
    public static function scopeCounts(array $filters): array
    {
        $filtersNoOnboard = $filters;
        $filtersNoOnboard['onboard'] = '';

        $base = CfaSubmission::query();
        self::applyFilters($base, $filtersNoOnboard, includeOnboard: false);

        $total = (int) (clone $base)->count();
        $onboarded = (int) (clone $base)->whereHas('onboardingBatchMembership')->count();
        $districts = (int) (clone $base)->whereNotNull('district_id')->distinct()->count('district_id');

        $blockExpr = self::payloadJsonExpr('$.block');
        $blocks = (int) (clone $base)
            ->whereRaw('TRIM(COALESCE('.$blockExpr.", '')) <> ''")
            ->distinct()
            ->count(DB::raw($blockExpr));

        return [
            'total' => $total,
            'onboarded' => $onboarded,
            'non_onboarded' => max(0, $total - $onboarded),
            'districts' => $districts,
            'blocks' => $blocks,
        ];
    }

    public static function enrichSubmission(CfaSubmission $row): CfaSubmission
    {
        $row->onboard_status = $row->relationLoaded('onboardingBatchMembership') && $row->onboardingBatchMembership !== null
            ? 'onboarded'
            : 'non_onboarded';
        $row->onboard_label = $row->onboard_status === 'onboarded' ? 'Onboarded' : 'Non onboarded';
        $payload = is_array($row->payload) ? $row->payload : [];
        $block = trim((string) ($payload['block'] ?? ''));
        $row->block_name = $block !== '' ? $block : '—';

        return $row;
    }

    /**
     * @return array<string, string> filter value => label
     */
    public static function casteFilterOptions(): array
    {
        $labels = [
            'GEN' => 'General (GEN)',
            'EWS' => 'EWS',
            'OBC' => 'OBC',
            'SC' => 'SC',
            'ST' => 'ST',
            'OTH' => 'Other (OTH)',
        ];

        $options = [];
        foreach (config('cfa.castes', array_keys($labels)) as $code) {
            $code = strtoupper(trim((string) $code));
            if ($code === '') {
                continue;
            }
            $options[$code] = $labels[$code] ?? $code;
        }
        $options['ST_SC'] = 'ST / SC';

        return $options;
    }

    public static function normalizeCasteParam(Request $request): string
    {
        $caste = strtoupper(trim((string) $request->input('caste', '')));
        if ($caste === 'SC_ST') {
            $caste = 'ST_SC';
        }

        return array_key_exists($caste, self::casteFilterOptions()) ? $caste : '';
    }

    /**
     * @return list<string>
     */
    public static function casteFilterValues(string $caste): array
    {
        $caste = strtoupper(trim($caste));

        return match ($caste) {
            'GEN' => ['GEN', 'GENERAL'],
            'EWS' => ['EWS'],
            'OBC' => ['OBC'],
            'SC' => ['SC'],
            'ST' => ['ST'],
            'OTH' => ['OTH', 'OTHER'],
            'ST_SC', 'SC_ST' => ['SC', 'ST'],
            default => [],
        };
    }

    public static function applyCasteColumnFilter(mixed $query, string $columnExpr, string $caste): void
    {
        $values = self::casteFilterValues($caste);
        if ($values === []) {
            return;
        }

        $placeholders = implode(',', array_fill(0, count($values), '?'));
        $query->whereRaw('UPPER(TRIM('.$columnExpr.')) IN ('.$placeholders.')', $values);
    }

    public static function payloadJsonExpr(string $path, string $column = 'payload'): string
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return "json_extract({$column}, '{$path}')";
        }

        return "JSON_UNQUOTE(JSON_EXTRACT({$column}, '{$path}'))";
    }
}
