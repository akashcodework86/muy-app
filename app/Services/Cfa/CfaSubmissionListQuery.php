<?php

namespace App\Services\Cfa;

use App\Models\CfaSubmission;
use App\Models\FiscalYear;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

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
        return ['name', 'application_no', 'district_id', 'sector', 'from', 'to', 'onboard'];
    }

    public static function hasActiveFilters(Request $request): bool
    {
        return $request->hasAny(self::filterParamNames());
    }

    /**
     * @param  array{name: string, application_no: string, district_id: int|null, sector: string, from: string, to: string, onboard: string}  $filters
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
            ->when(($filters['sector'] ?? '') !== '', fn ($q) => $q->whereRaw(
                "JSON_UNQUOTE(JSON_EXTRACT(payload, '$.business_category')) = ?",
                [$filters['sector']]
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
     * @return array{total: int, onboarded: int, non_onboarded: int}
     */
    public static function scopeCounts(array $filters): array
    {
        $filtersNoOnboard = $filters;
        $filtersNoOnboard['onboard'] = '';

        $base = CfaSubmission::query();
        self::applyFilters($base, $filtersNoOnboard, includeOnboard: false);

        $total = (int) (clone $base)->count();
        $onboarded = (int) (clone $base)->whereHas('onboardingBatchMembership')->count();

        return [
            'total' => $total,
            'onboarded' => $onboarded,
            'non_onboarded' => max(0, $total - $onboarded),
        ];
    }

    public static function enrichSubmission(CfaSubmission $row): CfaSubmission
    {
        $row->onboard_status = $row->relationLoaded('onboardingBatchMembership') && $row->onboardingBatchMembership !== null
            ? 'onboarded'
            : 'non_onboarded';
        $row->onboard_label = $row->onboard_status === 'onboarded' ? 'Onboarded' : 'Non onboarded';

        return $row;
    }
}
