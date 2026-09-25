<?php

namespace App\Support;

use App\Models\FiscalYear;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * FY pace / cumulative charts for state and hub admin pulse widgets.
 */
class FyPaceChartBuilder
{
    /**
     * @param  array{labels: list<string>, values: list<int>}  $dailyCfaTrend
     * @param  array{labels: list<string>, values: list<int>}  $dailyOnboardingTrend
     * @param  list<int>  $districtIds  Empty = all districts (state scope).
     * @return array{
     *   labels: list<string>,
     *   cfa_cumulative: list<int>,
     *   onboarding_cumulative: list<int>,
     *   cfa_pace_expected: list<int>,
     *   onboarding_pace_expected: list<int>,
     *   cfa_pace_pct: list<float|null>,
     *   onboarding_pace_pct: list<float|null>,
     *   cfa_target: int|null,
     *   onboarding_target: int|null,
     *   daily: array{labels: list<string>, cfa: list<int>, onboarding: list<int>}
     * }
     */
    public function build(
        ?FiscalYear $activeFy,
        Carbon $phase3FloorDate,
        int $fiscalYearId,
        ?int $cfaTarget,
        ?int $onboardingTarget,
        array $dailyCfaTrend,
        array $dailyOnboardingTrend,
        array $districtIds = [],
    ): array {
        $empty = [
            'labels' => [],
            'cfa_cumulative' => [],
            'onboarding_cumulative' => [],
            'cfa_pace_expected' => [],
            'onboarding_pace_expected' => [],
            'cfa_pace_pct' => [],
            'onboarding_pace_pct' => [],
            'cfa_target' => $cfaTarget,
            'onboarding_target' => $onboardingTarget,
            'daily' => [
                'labels' => $dailyCfaTrend['labels'] ?? [],
                'cfa' => $dailyCfaTrend['values'] ?? [],
                'onboarding' => $dailyOnboardingTrend['values'] ?? [],
            ],
        ];

        $fyStart = $activeFy?->starts_on
            ? Carbon::parse($activeFy->starts_on)->startOfDay()
            : $phase3FloorDate->copy();
        $fyEnd = $activeFy?->ends_on
            ? Carbon::parse($activeFy->ends_on)->endOfDay()
            : now()->endOfDay();
        $reportThrough = now()->endOfDay()->lt($fyEnd) ? now()->endOfDay() : $fyEnd;

        if ($reportThrough->lt($fyStart)) {
            return $empty;
        }

        $monthKeys = [];
        $labels = [];
        $cursor = $fyStart->copy()->startOfMonth();
        while ($cursor->lte($reportThrough)) {
            $monthEnd = $cursor->copy()->endOfMonth();
            if ($monthEnd->gt($fyEnd)) {
                $monthEnd = $fyEnd->copy();
            }
            if ($monthEnd->gt($reportThrough)) {
                $monthEnd = $reportThrough->copy();
            }
            if ($monthEnd->gte($fyStart)) {
                $labels[] = $cursor->format('M y');
                $monthKeys[] = $cursor->format('Y-m');
            }
            $cursor->addMonth();
        }

        if ($monthKeys === []) {
            return $empty;
        }

        $cfaMonthly = $this->monthlyCfaMap($fyStart, $reportThrough, $fiscalYearId, $districtIds);
        $onboardingMonthly = $this->monthlyOnboardingMap($fyStart, $reportThrough, $districtIds);

        $cfaCumulative = [];
        $onboardingCumulative = [];
        $cfaPaceExpected = [];
        $onboardingPaceExpected = [];
        $cfaPacePct = [];
        $onboardingPacePct = [];
        $runningCfa = 0;
        $runningOnb = 0;

        foreach ($monthKeys as $i => $ym) {
            $runningCfa += (int) ($cfaMonthly[$ym] ?? 0);
            $runningOnb += (int) ($onboardingMonthly[$ym] ?? 0);
            $cfaCumulative[] = $runningCfa;
            $onboardingCumulative[] = $runningOnb;

            $monthIndex = $i + 1;
            $cfaExpected = ($cfaTarget !== null && $cfaTarget > 0)
                ? (int) round($cfaTarget * $monthIndex / 12)
                : 0;
            $onbExpected = ($onboardingTarget !== null && $onboardingTarget > 0)
                ? (int) round($onboardingTarget * $monthIndex / 12)
                : 0;

            $cfaPaceExpected[] = $cfaExpected;
            $onboardingPaceExpected[] = $onbExpected;

            $cfaPacePct[] = $cfaExpected > 0
                ? round(($runningCfa / $cfaExpected) * 100, 1)
                : null;
            $onboardingPacePct[] = $onbExpected > 0
                ? round(($runningOnb / $onbExpected) * 100, 1)
                : null;
        }

        return [
            'labels' => $labels,
            'cfa_cumulative' => $cfaCumulative,
            'onboarding_cumulative' => $onboardingCumulative,
            'cfa_pace_expected' => $cfaPaceExpected,
            'onboarding_pace_expected' => $onboardingPaceExpected,
            'cfa_pace_pct' => $cfaPacePct,
            'onboarding_pace_pct' => $onboardingPacePct,
            'cfa_target' => $cfaTarget,
            'onboarding_target' => $onboardingTarget,
            'daily' => [
                'labels' => $dailyCfaTrend['labels'] ?? [],
                'cfa' => $dailyCfaTrend['values'] ?? [],
                'onboarding' => $dailyOnboardingTrend['values'] ?? [],
            ],
        ];
    }

    /**
     * @param  list<int>  $districtIds
     * @return array<string, int>
     */
    private function monthlyCfaMap(Carbon $from, Carbon $to, int $fiscalYearId, array $districtIds): array
    {
        if (! Schema::hasTable('cfa_submissions')) {
            return [];
        }

        $monthExpr = $this->monthKeySql('created_at');

        try {
            $query = DB::table('cfa_submissions')
                ->whereBetween('created_at', [$from, $to])
                ->when($fiscalYearId > 0, fn ($q) => $q->where('fiscal_year_id', $fiscalYearId))
                ->when($districtIds !== [], fn ($q) => $q->whereIn('district_id', $districtIds));

            return $query
                ->selectRaw($monthExpr.' as ym, COUNT(*) as total')
                ->groupBy('ym')
                ->pluck('total', 'ym')
                ->map(fn ($v) => (int) $v)
                ->all();
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @param  list<int>  $districtIds
     * @return array<string, int>
     */
    private function monthlyOnboardingMap(Carbon $from, Carbon $to, array $districtIds): array
    {
        if (! Schema::hasTable('onboarding_batch_cfa') || ! Schema::hasTable('onboarding_batches')) {
            return [];
        }

        $monthExpr = $this->monthKeySql('ob.locked_at');

        try {
            return DB::table('onboarding_batch_cfa as obc')
                ->join('onboarding_batches as ob', 'ob.id', '=', 'obc.onboarding_batch_id')
                ->where('ob.status', 'locked')
                ->whereNotNull('ob.locked_at')
                ->whereBetween('ob.locked_at', [$from, $to])
                ->when($districtIds !== [], fn ($q) => $q->whereIn('ob.district_id', $districtIds))
                ->selectRaw($monthExpr.' as ym, COUNT(*) as total')
                ->groupBy('ym')
                ->pluck('total', 'ym')
                ->map(fn ($v) => (int) $v)
                ->all();
        } catch (\Throwable) {
            return [];
        }
    }

    private function monthKeySql(string $columnExpression): string
    {
        return match (DB::connection()->getDriverName()) {
            'sqlite' => "strftime('%Y-%m', {$columnExpression})",
            'pgsql' => "to_char({$columnExpression}, 'YYYY-MM')",
            default => "DATE_FORMAT({$columnExpression}, '%Y-%m')",
        };
    }
}
