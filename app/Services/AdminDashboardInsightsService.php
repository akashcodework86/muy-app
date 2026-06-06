<?php

namespace App\Services;

use App\Models\FiscalYear;
use App\Services\Cfa\CfaSubmissionListQuery;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AdminDashboardInsightsService
{
    /**
     * @param  Builder<\App\Models\CfaSubmission>  $phase3Scope
     * @param  list<int>  $districtIds  Empty = all districts (state scope).
     * @param  list<int>  $cfaDeliverableIds
     * @param  array{labels: list<string>, values: list<int>}  $cfaByDistrict
     * @return array<string, mixed>
     */
    public function build(
        Builder $phase3Scope,
        array $districtIds,
        ?int $hubId,
        Carbon $phase3FloorDate,
        int $activeFyId,
        array $cfaDeliverableIds,
        ?FiscalYear $activeFy,
        array $cfaByDistrict,
        int $onboardedCount,
        int $servicesDelivered,
    ): array {
        try {
            $categoryMix = $this->payloadLabelCounts('$.category', $phase3FloorDate, $activeFyId, $districtIds, [
                'individual' => 'Individual',
                'shg' => 'SHG',
                'cbo' => 'CBO',
            ]);
            $genderMix = $this->payloadLabelCounts('$.gender', $phase3FloorDate, $activeFyId, $districtIds);
            $registrationMix = $this->payloadLabelCounts('$.is_registered', $phase3FloorDate, $activeFyId, $districtIds, [
                'yes' => 'Registered',
                'no' => 'Not registered',
            ]);
            $lakhpatiMix = $this->payloadLabelCounts('$.lakhpati', $phase3FloorDate, $activeFyId, $districtIds, [
                'yes' => 'Lakhpati Yes',
                'no' => 'Lakhpati No',
            ]);
            $sourceMix = $this->cfaSourceMix($phase3FloorDate, $activeFyId, $districtIds);
            $topBlocks = $this->topBlocksMix($phase3FloorDate, $activeFyId, $districtIds, 12);
            $districtTargetComparison = $this->districtCfaTargetComparison($cfaDeliverableIds, $activeFy, $cfaByDistrict, $districtIds);
            $onboardingTrend = $this->onboardingDailyTrend14($phase3FloorDate, $districtIds);
            $staffTopChart = $this->staffCfaTopChart($phase3FloorDate, $activeFyId, $hubId, $districtIds, 10);

            $geoDistricts = (int) (clone $phase3Scope)->whereNotNull('district_id')->distinct()->count('district_id');
            $blockExpr = CfaSubmissionListQuery::payloadJsonExpr('$.block');
            try {
                $geoBlocks = (int) (clone $phase3Scope)
                    ->whereRaw('TRIM(COALESCE('.$blockExpr.", '')) <> ''")
                    ->selectRaw('COUNT(DISTINCT '.$blockExpr.') as block_count')
                    ->value('block_count');
            } catch (\Throwable) {
                $geoBlocks = 0;
            }

            $cfaTotal = (int) (clone $phase3Scope)->count();
            $stageExpr = CfaSubmissionListQuery::payloadJsonExpr('$.form_stage');
            $stageValues = [0, 0, 0];
            foreach (['seed' => 0, 'early' => 1, 'growth' => 2] as $stageName => $idx) {
                try {
                    $stageQuery = DB::table('cfa_submissions')
                        ->when($activeFyId > 0, fn ($q) => $q->where('fiscal_year_id', $activeFyId), fn ($q) => $q->where('created_at', '>=', $phase3FloorDate))
                        ->when($districtIds !== [], fn ($q) => $q->whereIn('district_id', $districtIds))
                        ->whereRaw('LOWER(TRIM('.$stageExpr.')) = ?', [$stageName]);
                    $stageValues[$idx] = (int) $stageQuery->count();
                } catch (\Throwable) {
                    $stageValues[$idx] = 0;
                }
            }

            return [
                'geo' => [
                    'districts' => $geoDistricts,
                    'blocks' => $geoBlocks,
                ],
                'funnel' => [
                    'labels' => ['CFA submitted', 'Onboarded', 'Services delivered'],
                    'values' => [$cfaTotal, $onboardedCount, $servicesDelivered],
                ],
                'categoryMix' => $this->attachChartColors($categoryMix),
                'genderMix' => $this->attachChartColors($genderMix),
                'registrationMix' => $this->attachChartColors($registrationMix),
                'lakhpatiMix' => $this->attachChartColors($lakhpatiMix),
                'sourceMix' => $this->attachChartColors($sourceMix),
                'stageDonut' => $this->attachChartColors([
                    'labels' => ['Seed', 'Early', 'Growth'],
                    'values' => $stageValues,
                ]),
                'topBlocks' => $this->attachChartColors($topBlocks),
                'districtTargetComparison' => $districtTargetComparison,
                'onboardingTrend' => $onboardingTrend,
                'staffTopChart' => $staffTopChart,
            ];
        } catch (\Throwable) {
            return $this->emptyInsights();
        }
    }

    /**
     * @param  list<int>  $districtIds
     * @return array{
     *   total_till_date: float,
     *   total_this_fy: float,
     *   top_services: list<array{name: string, avg_price: float, approved_count: int, savings: float}>
     * }
     */
    public function estimatedSavings(?FiscalYear $activeFy, Carbon $phase3FloorDate, array $districtIds = []): array
    {
        if (! Schema::hasTable('service_cases') || ! Schema::hasTable('services')) {
            return ['total_till_date' => 0.0, 'total_this_fy' => 0.0, 'top_services' => []];
        }
        if (
            ! Schema::hasColumn('services', 'estimated_market_price_avg')
            || ! Schema::hasColumn('service_cases', 'status')
            || ! Schema::hasColumn('service_cases', 'service_id')
        ) {
            return ['total_till_date' => 0.0, 'total_this_fy' => 0.0, 'top_services' => []];
        }

        $scopeCases = function ($query) use ($districtIds) {
            if ($districtIds === [] || ! Schema::hasTable('cfa_submissions') || ! Schema::hasColumn('service_cases', 'cfa_submission_id')) {
                return $query;
            }

            return $query->whereExists(function ($sub) use ($districtIds): void {
                $sub->selectRaw('1')
                    ->from('cfa_submissions as cs')
                    ->whereColumn('cs.id', 'sc.cfa_submission_id')
                    ->whereIn('cs.district_id', $districtIds);
            });
        };

        $baseRows = $scopeCases(DB::table('service_cases as sc'))
            ->join('services as s', 's.id', '=', 'sc.service_id')
            ->where('sc.status', 'approved')
            ->where('s.is_active', true)
            ->whereNotNull('s.estimated_market_price_avg')
            ->where('s.estimated_market_price_avg', '>', 0)
            ->selectRaw('s.id, s.name, s.estimated_market_price_avg, COUNT(sc.id) as approved_count')
            ->groupBy('s.id', 's.name', 's.estimated_market_price_avg')
            ->orderByDesc('approved_count')
            ->get();

        $topServices = $this->mapSavingsRows($baseRows, 5);
        $totalTillDate = array_sum(array_map(fn (array $row) => (float) $row['savings'], $this->mapSavingsRows($baseRows)));

        $fyStart = $activeFy?->starts_on
            ? Carbon::parse($activeFy->starts_on)->startOfDay()
            : $phase3FloorDate;
        $fyEnd = $activeFy?->ends_on
            ? Carbon::parse($activeFy->ends_on)->endOfDay()
            : now()->endOfDay();

        $approvedAtExpr = Schema::hasColumn('service_cases', 'approved_at')
            ? 'COALESCE(sc.approved_at, sc.completed_at, sc.created_at)'
            : (Schema::hasColumn('service_cases', 'completed_at') ? 'COALESCE(sc.completed_at, sc.created_at)' : 'sc.created_at');

        $fyRows = $scopeCases(DB::table('service_cases as sc'))
            ->join('services as s', 's.id', '=', 'sc.service_id')
            ->where('sc.status', 'approved')
            ->whereBetween(DB::raw($approvedAtExpr), [$fyStart, $fyEnd])
            ->where('s.is_active', true)
            ->whereNotNull('s.estimated_market_price_avg')
            ->where('s.estimated_market_price_avg', '>', 0)
            ->selectRaw('s.id, s.estimated_market_price_avg, COUNT(sc.id) as approved_count')
            ->groupBy('s.id', 's.estimated_market_price_avg')
            ->get();

        $totalThisFy = array_sum(array_map(
            fn (object $row) => (float) $row->approved_count * (float) $row->estimated_market_price_avg,
            $fyRows->all()
        ));

        return [
            'total_till_date' => round((float) $totalTillDate, 2),
            'total_this_fy' => round((float) $totalThisFy, 2),
            'top_services' => $topServices,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function emptyInsights(): array
    {
        $emptyChart = ['labels' => [], 'values' => [], 'colors' => []];

        return [
            'geo' => ['districts' => 0, 'blocks' => 0],
            'funnel' => [
                'labels' => ['CFA submitted', 'Onboarded', 'Services delivered'],
                'values' => [0, 0, 0],
            ],
            'categoryMix' => $emptyChart,
            'genderMix' => $emptyChart,
            'registrationMix' => $emptyChart,
            'lakhpatiMix' => $emptyChart,
            'sourceMix' => $emptyChart,
            'stageDonut' => $emptyChart,
            'topBlocks' => $emptyChart,
            'districtTargetComparison' => ['labels' => [], 'achieved' => [], 'targets' => []],
            'onboardingTrend' => ['labels' => [], 'values' => []],
            'staffTopChart' => ['labels' => [], 'values' => []],
        ];
    }

    /**
     * @param  list<int>  $districtIds
     * @param  array<string, string>  $labelMap
     * @return array{labels: list<string>, values: list<int>}
     */
    private function payloadLabelCounts(
        string $jsonPath,
        Carbon $phase3FloorDate,
        int $fiscalYearId,
        array $districtIds,
        array $labelMap = [],
    ): array {
        $expr = CfaSubmissionListQuery::payloadJsonExpr($jsonPath);
        try {
            $rows = DB::table('cfa_submissions')
                ->when($fiscalYearId > 0, fn ($q) => $q->where('fiscal_year_id', $fiscalYearId), fn ($q) => $q->where('created_at', '>=', $phase3FloorDate))
                ->when($districtIds !== [], fn ($q) => $q->whereIn('district_id', $districtIds))
                ->whereRaw('TRIM(COALESCE('.$expr.", '')) <> ''")
                ->selectRaw($expr.' as label_key, COUNT(*) as total')
                ->groupByRaw($expr)
                ->orderByDesc('total')
                ->get();
        } catch (\Throwable) {
            return ['labels' => [], 'values' => []];
        }

        $labels = [];
        $values = [];
        foreach ($rows as $row) {
            $raw = trim((string) ($row->label_key ?? ''));
            if ($raw === '') {
                continue;
            }
            $key = strtolower($raw);
            $labels[] = $labelMap[$key] ?? ucfirst($raw);
            $values[] = (int) ($row->total ?? 0);
        }

        return ['labels' => $labels, 'values' => $values];
    }

    /**
     * @param  list<int>  $districtIds
     * @return array{labels: list<string>, values: list<int>}
     */
    private function cfaSourceMix(Carbon $phase3FloorDate, int $fiscalYearId, array $districtIds): array
    {
        try {
            $rows = DB::table('cfa_submissions')
                ->when($fiscalYearId > 0, fn ($q) => $q->where('fiscal_year_id', $fiscalYearId), fn ($q) => $q->where('created_at', '>=', $phase3FloorDate))
                ->when($districtIds !== [], fn ($q) => $q->whereIn('district_id', $districtIds))
                ->selectRaw('COALESCE(source, "") as src, COUNT(*) as total')
                ->groupBy('src')
                ->orderByDesc('total')
                ->get();
        } catch (\Throwable) {
            return ['labels' => [], 'values' => []];
        }

        $labels = [];
        $values = [];
        foreach ($rows as $row) {
            $src = strtolower(trim((string) ($row->src ?? '')));
            $labels[] = match ($src) {
                'public_form' => 'Public / walk-in',
                'legacy_phase2', 'rbiphase2' => 'Legacy Phase 2',
                '' => 'District staff',
                default => ucfirst(str_replace('_', ' ', $src)),
            };
            $values[] = (int) ($row->total ?? 0);
        }

        return ['labels' => $labels, 'values' => $values];
    }

    /**
     * @param  list<int>  $districtIds
     * @return array{labels: list<string>, values: list<int>}
     */
    private function topBlocksMix(Carbon $phase3FloorDate, int $fiscalYearId, array $districtIds, int $limit = 12): array
    {
        $expr = CfaSubmissionListQuery::payloadJsonExpr('$.block');
        try {
            $rows = DB::table('cfa_submissions')
                ->when($fiscalYearId > 0, fn ($q) => $q->where('fiscal_year_id', $fiscalYearId), fn ($q) => $q->where('created_at', '>=', $phase3FloorDate))
                ->when($districtIds !== [], fn ($q) => $q->whereIn('district_id', $districtIds))
                ->whereRaw('TRIM(COALESCE('.$expr.", '')) <> ''")
                ->selectRaw($expr.' as block_name, COUNT(*) as total')
                ->groupByRaw($expr)
                ->orderByDesc('total')
                ->limit($limit)
                ->get();
        } catch (\Throwable) {
            return ['labels' => [], 'values' => []];
        }

        return [
            'labels' => $rows->pluck('block_name')->map(fn ($v) => (string) $v)->all(),
            'values' => $rows->pluck('total')->map(fn ($v) => (int) $v)->all(),
        ];
    }

    /**
     * @param  list<int>  $cfaDeliverableIds
     * @param  list<int>  $districtIds
     * @param  array{labels: list<string>, values: list<int>}  $cfaByDistrict
     * @return array{labels: list<string>, achieved: list<int>, targets: list<int>}
     */
    private function districtCfaTargetComparison(
        array $cfaDeliverableIds,
        ?FiscalYear $activeFy,
        array $cfaByDistrict,
        array $districtIds,
    ): array {
        $labels = $cfaByDistrict['labels'] ?? [];
        $achieved = $cfaByDistrict['values'] ?? [];
        $targets = array_fill(0, count($labels), 0);

        if ($activeFy === null || $cfaDeliverableIds === []) {
            return compact('labels', 'achieved', 'targets');
        }

        try {
            $targetQuery = DB::table('district_deliverable_targets as ddt')
                ->join('districts as d', 'd.id', '=', 'ddt.district_id')
                ->where('ddt.fiscal_year_id', (int) $activeFy->id)
                ->whereIn('ddt.deliverable_id', $cfaDeliverableIds)
                ->when($districtIds !== [], fn ($q) => $q->whereIn('d.id', $districtIds))
                ->selectRaw('d.name as district_name, SUM(ddt.target_total) as target_total')
                ->groupBy('d.id', 'd.name');
            $targetRows = $targetQuery->pluck('target_total', 'district_name');
        } catch (\Throwable) {
            return compact('labels', 'achieved', 'targets');
        }

        foreach ($labels as $i => $name) {
            $targets[$i] = (int) ($targetRows[$name] ?? 0);
        }

        return compact('labels', 'achieved', 'targets');
    }

    /**
     * @param  list<int>  $districtIds
     * @return array{labels: list<string>, values: list<int>}
     */
    private function onboardingDailyTrend14(Carbon $phase3FloorDate, array $districtIds): array
    {
        $labels = [];
        $values = [];
        if (! Schema::hasTable('onboarding_batch_cfa') || ! Schema::hasTable('onboarding_batches')) {
            return ['labels' => $labels, 'values' => $values];
        }

        for ($i = 13; $i >= 0; $i--) {
            $day = Carbon::now()->subDays($i)->startOfDay();
            $labels[] = $day->format('d M');
            if ($day->lt($phase3FloorDate)) {
                $values[] = 0;
            } else {
                $values[] = (int) DB::table('onboarding_batch_cfa as obc')
                    ->join('onboarding_batches as ob', 'ob.id', '=', 'obc.onboarding_batch_id')
                    ->when($districtIds !== [], fn ($q) => $q->whereIn('ob.district_id', $districtIds))
                    ->where('ob.status', 'locked')
                    ->whereNotNull('ob.locked_at')
                    ->whereDate('ob.locked_at', $day->toDateString())
                    ->count();
            }
        }

        return ['labels' => $labels, 'values' => $values];
    }

    /**
     * @param  list<int>  $districtIds
     * @return array{labels: list<string>, values: list<int>}
     */
    private function staffCfaTopChart(
        Carbon $phase3FloorDate,
        int $fiscalYearId,
        ?int $hubId,
        array $districtIds,
        int $limit = 10,
    ): array {
        try {
            $rows = DB::table('users')
                ->leftJoin('cfa_submissions as cs', function ($join) use ($fiscalYearId, $phase3FloorDate): void {
                    $join->on('cs.referral_user_id', '=', 'users.id')
                        ->on('cs.district_id', '=', 'users.district_id');
                    if ($fiscalYearId > 0) {
                        $join->where('cs.fiscal_year_id', $fiscalYearId);
                    } else {
                        $join->where('cs.created_at', '>=', $phase3FloorDate);
                    }
                })
                ->where('users.role', 'district_staff')
                ->when($hubId !== null, fn ($q) => $q->where('users.hub_id', $hubId))
                ->when($districtIds !== [] && $hubId === null, fn ($q) => $q->whereIn('users.district_id', $districtIds))
                ->select('users.name', DB::raw('COUNT(cs.id) as total'))
                ->groupBy('users.id', 'users.name')
                ->orderByDesc('total')
                ->limit($limit)
                ->get();
        } catch (\Throwable) {
            return ['labels' => [], 'values' => []];
        }

        return [
            'labels' => $rows->pluck('name')->map(fn ($v) => (string) $v)->all(),
            'values' => $rows->pluck('total')->map(fn ($v) => (int) $v)->all(),
        ];
    }

    /**
     * @param  array{labels: list<string>, values: list<int>}  $chart
     * @return array{labels: list<string>, values: list<int>, colors: list<string>}
     */
    private function attachChartColors(array $chart): array
    {
        $chart['colors'] = $this->chartColorsForLabels($chart['labels'] ?? []);

        return $chart;
    }

    /**
     * @param  list<string>  $labels
     * @return list<string>
     */
    private function chartColorsForLabels(array $labels): array
    {
        $palette = [
            '#26a69a', '#42a5f5', '#ff8a65', '#ffca28', '#f06292', '#66bb6a',
            '#ab47bc', '#78909c', '#4db6ac', '#64b5f6', '#ffb74d', '#81c784', '#ce93d8',
        ];
        $out = [];
        foreach ($labels as $i => $label) {
            if ($i < count($palette)) {
                $out[] = $palette[$i];
            } else {
                $h = abs(crc32((string) $label)) % 360;
                $out[] = sprintf('hsl(%d, 62%%, 48%%)', $h);
            }
        }

        return $out;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, object>  $rows
     * @return list<array{name: string, avg_price: float, approved_count: int, savings: float}>
     */
    private function mapSavingsRows($rows, ?int $limit = null): array
    {
        $mapped = $rows
            ->map(fn (object $row) => [
                'name' => (string) $row->name,
                'avg_price' => round((float) $row->estimated_market_price_avg, 2),
                'approved_count' => (int) $row->approved_count,
                'savings' => round((float) $row->approved_count * (float) $row->estimated_market_price_avg, 2),
            ])
            ->sortByDesc('savings')
            ->values();

        if ($limit !== null && $limit > 0) {
            $mapped = $mapped->take($limit)->values();
        }

        return $mapped->all();
    }
}
