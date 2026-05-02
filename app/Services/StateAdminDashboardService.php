<?php

namespace App\Services;

use App\Models\CfaSubmission;
use App\Models\Deliverable;
use App\Models\District;
use App\Models\FiscalYear;
use App\Models\MentorshipRequest;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class StateAdminDashboardService
{
    /**
     * @return array<string, mixed>
     */
    public function metrics(): array
    {
        $phase3FloorDate = Carbon::create(2026, 4, 1)->startOfDay();
        $phase3FloorDateLabel = $phase3FloorDate->format('d M Y');

        $activeFy = FiscalYear::phase3Default();
        $activeFyId = (int) ($activeFy?->id ?? 0);
        $phase3Scope = CfaSubmission::query()
            ->when($activeFyId > 0, fn ($q) => $q->where('fiscal_year_id', $activeFyId), fn ($q) => $q->where('created_at', '>=', $phase3FloorDate));
        $cfaDeliverable = null;
        $stateCfaTarget = null;
        $districtsCfaSum = null;
        $stateOnboardingTarget = null;
        $stateOnboardingAchieved = 0;
        $stateOnboardingProgressPct = null;
        $stateOnboardingByDistrict = [];
        $stateCfaThisFy = (int) (clone $phase3Scope)->count();
        $stateProgressPct = null;
        $stateCfaTrend = $this->stateDailyTrend14($phase3FloorDate, $activeFyId);
        $stateBusinessStageMix = $this->stateStageMixAggregates($phase3FloorDate, $activeFyId);
        $estimatedSavings = [
            'total_till_date' => 0.0,
            'total_this_fy' => 0.0,
            'top_services' => [],
        ];

        try {
            if (
                Schema::hasTable('fiscal_years')
                && Schema::hasTable('deliverables')
                && Schema::hasTable('state_deliverable_targets')
                && Schema::hasTable('district_deliverable_targets')
            ) {
                $cfaDeliverable = Deliverable::query()
                    ->where(function ($q): void {
                        $q->whereRaw('LOWER(code) = ?', ['cfa'])
                            ->orWhere('sort_order', 3)
                            ->orWhere('name', 'like', '%Call for Application%')
                            ->orWhere('mis_entry_label', 'like', '%Call for Application%');
                    })
                    ->orderByDesc('id')
                    ->first();
                $cfaDeliverableIds = Deliverable::query()
                    ->where(function ($q): void {
                        $q->whereRaw('LOWER(code) = ?', ['cfa'])
                            ->orWhere('sort_order', 3)
                            ->orWhere('name', 'like', '%Call for Application%')
                            ->orWhere('mis_entry_label', 'like', '%Call for Application%');
                    })
                    ->pluck('id')
                    ->map(fn ($id) => (int) $id)
                    ->filter(fn (int $id) => $id > 0)
                    ->values()
                    ->all();
                if ($cfaDeliverableIds !== []) {
                    if (! $activeFy) {
                        $activeFy = FiscalYear::phase3Default();
                    }
                }

                if ($activeFy && $cfaDeliverableIds !== []) {
                    $stateCfaTarget = (int) DB::table('state_deliverable_targets')
                        ->where('fiscal_year_id', (int) $activeFy->id)
                        ->whereIn('deliverable_id', $cfaDeliverableIds)
                        ->sum('target_total');
                    if ($stateCfaTarget <= 0) {
                        $stateCfaTarget = null;
                    }

                    $districtsCfaSum = (int) DB::table('district_deliverable_targets')
                        ->where('fiscal_year_id', (int) $activeFy->id)
                        ->whereIn('deliverable_id', $cfaDeliverableIds)
                        ->sum('target_total');

                    // Progress uses the same scoped total shown on dashboard (Phase 3 onwards),
                    // independent of FY date windows.
                    $stateCfaThisFy = (int) (clone $phase3Scope)->count();

                    if ($stateCfaTarget !== null && $stateCfaTarget > 0) {
                        $stateProgressPct = (int) round(($stateCfaThisFy / $stateCfaTarget) * 100);
                    }
                }

                $onboardingDeliverableId = Deliverable::onboardingTargetDeliverableId();
                if ($onboardingDeliverableId !== null && $activeFy) {
                    $stateOnboardingTarget = (int) DB::table('state_deliverable_targets')
                        ->where('fiscal_year_id', (int) $activeFy->id)
                        ->where('deliverable_id', $onboardingDeliverableId)
                        ->value('target_total');
                    if ($stateOnboardingTarget <= 0) {
                        $stateOnboardingTarget = null;
                    }
                }
            }
        } catch (\Throwable) {
            // Keep dashboard resilient if target tables/columns are missing on any environment.
            $activeFy = null;
            $activeFyId = 0;
            $cfaDeliverable = null;
            $stateCfaTarget = null;
            $districtsCfaSum = null;
            $stateProgressPct = null;
            $stateOnboardingTarget = null;
        }

        if (Schema::hasTable('onboarding_batch_cfa') && Schema::hasTable('onboarding_batches')) {
            $stateOnboardingAchieved = (int) DB::table('onboarding_batch_cfa as obc')
                ->join('onboarding_batches as ob', 'ob.id', '=', 'obc.onboarding_batch_id')
                ->where('ob.status', 'locked')
                ->whereNotNull('ob.locked_at')
                ->where('ob.locked_at', '>=', $phase3FloorDate)
                ->count();

            if (Schema::hasTable('districts')) {
                $stateOnboardingByDistrict = DB::table('onboarding_batch_cfa as obc')
                    ->join('onboarding_batches as ob', 'ob.id', '=', 'obc.onboarding_batch_id')
                    ->join('districts as d', 'd.id', '=', 'ob.district_id')
                    ->where('ob.status', 'locked')
                    ->whereNotNull('ob.locked_at')
                    ->where('ob.locked_at', '>=', $phase3FloorDate)
                    ->groupBy('d.id', 'd.name')
                    ->orderByDesc(DB::raw('COUNT(obc.id)'))
                    ->orderBy('d.name')
                    ->select('d.name', DB::raw('COUNT(obc.id) as total'))
                    ->get()
                    ->map(fn ($row) => [
                        'district' => (string) $row->name,
                        'count' => (int) $row->total,
                    ])
                    ->all();
            }
        }
        if ($stateOnboardingTarget !== null && $stateOnboardingTarget > 0) {
            $stateOnboardingProgressPct = (int) round(($stateOnboardingAchieved / $stateOnboardingTarget) * 100);
        }

        $staffTotal = User::query()->where('role', 'district_staff')->count();
        $staffActive = User::query()->where('role', 'district_staff')->where('is_active', true)->count();

        $cfaTotal = (clone $phase3Scope)->count();
        $cfaThisMonth = (clone $phase3Scope)->whereYear('created_at', now()->year)->whereMonth('created_at', now()->month)->count();
        $cfaLast30 = (clone $phase3Scope)->where('created_at', '>=', now()->subDays(30))->count();
        $cfaLast7 = (int) (clone $phase3Scope)->where('created_at', '>=', now()->subDays(6)->startOfDay())->count();
        $cfaPrev7 = (int) (clone $phase3Scope)
            ->whereBetween('created_at', [
                now()->subDays(13)->startOfDay(),
                now()->subDays(7)->endOfDay(),
            ])
            ->count();
        $cfaWoWDeltaPct = $cfaPrev7 > 0
            ? (int) round((($cfaLast7 - $cfaPrev7) / $cfaPrev7) * 100)
            : ($cfaLast7 > 0 ? 100 : 0);

        $seedCount = 0;
        $earlyCount = 0;
        $growthCount = 0;
        $stageCounts = DB::table('cfa_submissions')
            ->selectRaw("LOWER(TRIM(JSON_UNQUOTE(JSON_EXTRACT(payload, '$.form_stage')))) as stage_key")
            ->selectRaw('COUNT(*) as total')
            ->when($activeFyId > 0, fn ($q) => $q->where('fiscal_year_id', $activeFyId), fn ($q) => $q->where('created_at', '>=', $phase3FloorDate))
            ->groupBy('stage_key')
            ->pluck('total', 'stage_key');
        $seedCount = (int) ($stageCounts['seed'] ?? 0);
        $earlyCount = (int) ($stageCounts['early'] ?? 0);
        $growthCount = (int) ($stageCounts['growth'] ?? 0);

        $cfaByDistrict = DB::table('districts')
            ->leftJoin('cfa_submissions', function ($join) use ($activeFyId, $phase3FloorDate): void {
                $join->on('cfa_submissions.district_id', '=', 'districts.id');
                if ($activeFyId > 0) {
                    $join->where('cfa_submissions.fiscal_year_id', $activeFyId);
                } else {
                    $join->where('cfa_submissions.created_at', '>=', $phase3FloorDate);
                }
            })
            ->select('districts.name', DB::raw('COUNT(cfa_submissions.id) as total'))
            ->groupBy('districts.id', 'districts.name')
            ->orderByDesc('total')
            ->orderBy('districts.name')
            ->get();
        $todayDistrictRows = DB::table('districts')
            ->leftJoin('cfa_submissions', function ($join) use ($activeFyId): void {
                $join->on('cfa_submissions.district_id', '=', 'districts.id')
                    ->whereDate('cfa_submissions.created_at', now()->toDateString());
                if ($activeFyId > 0) {
                    $join->where('cfa_submissions.fiscal_year_id', $activeFyId);
                }
            })
            ->select('districts.name', DB::raw('COUNT(cfa_submissions.id) as total'))
            ->groupBy('districts.id', 'districts.name')
            ->orderByDesc('total')
            ->orderBy('districts.name')
            ->get();
        $todayTopDistrict = $todayDistrictRows->first();
        $todayNonZero = $todayDistrictRows->filter(fn ($r) => (int) $r->total > 0)->values();
        $todayLowestActiveDistrict = $todayNonZero->sortBy('total')->first();
        $todayZeroDistricts = max(0, (int) $todayDistrictRows->count() - (int) $todayNonZero->count());

        $staffByDistrict = DB::table('users')
            ->join('districts', 'users.district_id', '=', 'districts.id')
            ->where('users.role', 'district_staff')
            ->select('districts.name', DB::raw('COUNT(*) as total'))
            ->groupBy('districts.id', 'districts.name')
            ->orderByDesc('total')
            ->get();

        $staffCfaByStaff = DB::table('users')
            ->leftJoin('districts', 'users.district_id', '=', 'districts.id')
            ->leftJoin('cfa_submissions as cs', function ($join) use ($activeFyId, $phase3FloorDate): void {
                $join->on('cs.referral_user_id', '=', 'users.id');
                // Keep staff leaderboard district-aligned with "Applications by district".
                $join->on('cs.district_id', '=', 'users.district_id');
                if ($activeFyId > 0) {
                    $join->where('cs.fiscal_year_id', $activeFyId);
                } else {
                    $join->where('cs.created_at', '>=', $phase3FloorDate);
                }
            })
            ->where('users.role', 'district_staff')
            ->select(
                'users.id',
                'users.name',
                DB::raw('COALESCE(districts.name, "Unassigned") as district_name'),
                DB::raw('COUNT(cs.id) as cfa_total')
            )
            ->groupBy('users.id', 'users.name', 'districts.name')
            ->orderByDesc('cfa_total')
            ->orderBy('users.name')
            ->get();

        // Add a synthetic row for applications with no referral user so district totals reconcile.
        $notLinkedByDistrict = DB::table('cfa_submissions as cs')
            ->join('districts as d', 'd.id', '=', 'cs.district_id')
            ->whereNull('cs.referral_user_id')
            ->when($activeFyId > 0, fn ($q) => $q->where('cs.fiscal_year_id', $activeFyId), fn ($q) => $q->where('cs.created_at', '>=', $phase3FloorDate))
            ->select(
                DB::raw('NULL as id'),
                DB::raw("'Not linked to referral' as name"),
                DB::raw('d.name as district_name'),
                DB::raw('COUNT(cs.id) as cfa_total')
            )
            ->groupBy('d.id', 'd.name')
            ->havingRaw('COUNT(cs.id) > 0')
            ->get();

        $staffCfaByStaff = $staffCfaByStaff
            ->concat($notLinkedByDistrict)
            ->sortByDesc(fn ($row) => (int) $row->cfa_total)
            ->values();

        $staffAvatarMap = User::query()
            ->whereIn('id', $staffCfaByStaff->pluck('id')->filter()->map(fn ($id) => (int) $id)->filter(fn (int $id) => $id > 0)->all())
            ->get()
            ->keyBy('id');

        $businessMixChart = $this->businessCategoryMix($phase3FloorDate, $activeFyId);
        $businessMixChart['colors'] = $this->chartColorsForLabels($businessMixChart['labels']);

        $heroCfaToday = (int) (clone $phase3Scope)->whereDate('created_at', now()->toDateString())->count();
        $heroCfaYesterday = (int) (clone $phase3Scope)->whereDate('created_at', now()->subDay()->toDateString())->count();
        $heroCfaTodayDelta = $heroCfaToday - $heroCfaYesterday;

        $heroMentorshipPending = 0;
        try {
            if (Schema::hasTable('mentorship_requests')) {
                $heroMentorshipPending = (int) MentorshipRequest::query()
                    ->where('status', MentorshipRequest::STATUS_PENDING)
                    ->count();
            }
        } catch (\Throwable $e) {
            $heroMentorshipPending = 0;
        }

        $heroStaffOnlineNow = 0;
        if (Schema::hasColumn('users', 'last_seen_at')) {
            $heroStaffOnlineNow = (int) User::query()
                ->where('last_seen_at', '>=', now()->subMinutes(3))
                ->count();
        }

        $heroSparkline30 = $this->dailyCfaSparkline(30, $phase3FloorDate, $activeFyId);

        $districtAllocPct = ($stateCfaTarget !== null && $stateCfaTarget > 0 && $districtsCfaSum !== null)
            ? (int) round(($districtsCfaSum / $stateCfaTarget) * 100)
            : null;

        $estimatedSavings = $this->estimatedSavingsMetrics($activeFy, $phase3FloorDate);

        return [
            'activeFy' => $activeFy,
            'cfaDeliverable' => $cfaDeliverable,
            'stateCfaTarget' => $stateCfaTarget !== null ? (int) $stateCfaTarget : null,
            'districtsCfaSum' => $districtsCfaSum,
            'districtAllocPct' => $districtAllocPct,
            'stateOnboardingTarget' => $stateOnboardingTarget,
            'stateOnboardingAchieved' => $stateOnboardingAchieved,
            'stateOnboardingProgressPct' => $stateOnboardingProgressPct,
            'stateOnboardingByDistrict' => $stateOnboardingByDistrict,
            'stateCfaThisFy' => $stateCfaThisFy,
            'stateProgressPct' => $stateProgressPct,
            'stateCfaTrend' => $stateCfaTrend,
            'stateBusinessStageMix' => $stateBusinessStageMix,
            'staffTotal' => $staffTotal,
            'staffActive' => $staffActive,
            'cfaTotal' => $cfaTotal,
            'cfaThisMonth' => $cfaThisMonth,
            'cfaLast30' => $cfaLast30,
            'cfaLast7' => $cfaLast7,
            'cfaPrev7' => $cfaPrev7,
            'cfaWoWDeltaPct' => $cfaWoWDeltaPct,
            'seedCount' => $seedCount,
            'earlyCount' => $earlyCount,
            'growthCount' => $growthCount,
            'hubsCount' => DB::table('hubs')->count(),
            'districtsCount' => District::query()->count(),
            'deliverablesCount' => Deliverable::query()->where('is_active', true)->count(),
            'cfaByDistrict' => [
                'labels' => $cfaByDistrict->pluck('name')->all(),
                'values' => $cfaByDistrict->pluck('total')->map(fn ($v) => (int) $v)->all(),
            ],
            'todayTopDistrict' => $todayTopDistrict ? [
                'name' => (string) $todayTopDistrict->name,
                'count' => (int) $todayTopDistrict->total,
            ] : null,
            'todayLowestActiveDistrict' => $todayLowestActiveDistrict ? [
                'name' => (string) $todayLowestActiveDistrict->name,
                'count' => (int) $todayLowestActiveDistrict->total,
            ] : null,
            'todayZeroDistricts' => $todayZeroDistricts,
            'staffByDistrict' => [
                'labels' => $staffByDistrict->pluck('name')->all(),
                'values' => $staffByDistrict->pluck('total')->map(fn ($v) => (int) $v)->all(),
            ],
            'staffCfaByStaff' => $staffCfaByStaff->map(fn ($row) => [
                'id' => (int) $row->id,
                'name' => (string) $row->name,
                'district' => (string) $row->district_name,
                'cfa_total' => (int) $row->cfa_total,
                'avatar_url' => $staffAvatarMap->get((int) $row->id)?->avatarUrl(),
            ])->all(),
            'cfaTrend' => $stateCfaTrend,
            'businessMix' => $businessMixChart,
            'heroCfaToday' => $heroCfaToday,
            'heroCfaYesterday' => $heroCfaYesterday,
            'heroCfaTodayDelta' => $heroCfaTodayDelta,
            'heroMentorshipPending' => $heroMentorshipPending,
            'heroStaffOnlineNow' => $heroStaffOnlineNow,
            'heroSparkline30' => $heroSparkline30,
            'phase3FloorDateLabel' => $phase3FloorDateLabel,
            'estimatedSavings' => $estimatedSavings,
        ];
    }

    /**
     * @return array{
     *   total_till_date: float,
     *   total_this_fy: float,
     *   top_services: list<array{name: string, avg_price: float, approved_count: int, savings: float}>
     * }
     */
    private function estimatedSavingsMetrics(?FiscalYear $activeFy, Carbon $phase3FloorDate): array
    {
        if (! Schema::hasTable('service_cases') || ! Schema::hasTable('services')) {
            return [
                'total_till_date' => 0.0,
                'total_this_fy' => 0.0,
                'top_services' => [],
            ];
        }
        if (
            ! Schema::hasColumn('services', 'estimated_market_price_avg')
            || ! Schema::hasColumn('service_cases', 'status')
            || ! Schema::hasColumn('service_cases', 'service_id')
        ) {
            return [
                'total_till_date' => 0.0,
                'total_this_fy' => 0.0,
                'top_services' => [],
            ];
        }

        $baseRows = DB::table('service_cases as sc')
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

        $fyRows = DB::table('service_cases as sc')
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
     * @param  Collection<int, object>  $rows
     * @return list<array{name: string, avg_price: float, approved_count: int, savings: float}>
     */
    private function mapSavingsRows(Collection $rows, ?int $limit = null): array
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

    /**
     * @return array{labels: list<string>, values: list<int>}
     */
    private function dailyCfaSparkline(int $days, Carbon $phase3FloorDate, int $fiscalYearId = 0): array
    {
        $start = now()->subDays($days - 1)->startOfDay();
        if ($start->lt($phase3FloorDate)) {
            $start = $phase3FloorDate->copy();
        }
        $rows = DB::table('cfa_submissions')
            ->selectRaw('DATE(created_at) as d, COUNT(*) as total')
            ->where('created_at', '>=', $start)
            ->when($fiscalYearId > 0, fn ($q) => $q->where('fiscal_year_id', $fiscalYearId))
            ->groupBy('d')
            ->pluck('total', 'd');

        $labels = [];
        $values = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $day = now()->subDays($i);
            $key = $day->toDateString();
            $labels[] = $day->format('d M');
            $values[] = (int) ($rows[$key] ?? 0);
        }

        return ['labels' => $labels, 'values' => $values];
    }

    /**
     * @return array{labels: list<string>, values: list<int>}
     */
    private function stateDailyTrend14(Carbon $phase3FloorDate, int $fiscalYearId = 0): array
    {
        $labels = [];
        $values = [];
        for ($i = 13; $i >= 0; $i--) {
            $day = Carbon::now()->subDays($i)->startOfDay();
            $labels[] = $day->format('d M');
            if ($day->lt($phase3FloorDate)) {
                $values[] = 0;
            } else {
                $values[] = (int) CfaSubmission::query()
                    ->whereDate('created_at', $day->toDateString())
                    ->when($fiscalYearId > 0, fn ($q) => $q->where('fiscal_year_id', $fiscalYearId))
                    ->count();
            }
        }

        return ['labels' => $labels, 'values' => $values];
    }

    /**
     * @return array{labels: list<string>, values: list<int>}
     */
    private function stateStageMixAggregates(Carbon $phase3FloorDate, int $fiscalYearId = 0, int $limit = 2000): array
    {
        $stage = [];

        CfaSubmission::query()
            ->when($fiscalYearId > 0, fn ($q) => $q->where('fiscal_year_id', $fiscalYearId), fn ($q) => $q->where('created_at', '>=', $phase3FloorDate))
            ->whereNotNull('payload')
            ->orderByDesc('id')
            ->limit($limit)
            ->cursor()
            ->each(function (CfaSubmission $row) use (&$stage): void {
                $p = $row->payload;
                if (! is_array($p)) {
                    return;
                }

                $st = $p['form_stage'] ?? null;
                if (! is_string($st) || $st === '') {
                    $st = 'Not specified';
                }
                $stage[$st] = ($stage[$st] ?? 0) + 1;
            });

        if ($stage === []) {
            return ['labels' => [], 'values' => []];
        }

        arsort($stage);

        return [
            'labels' => array_keys($stage),
            'values' => array_map(intval(...), array_values($stage)),
        ];
    }

    /**
     * @return array{labels: list<string>, values: list<int>}
     */
    private function businessCategoryMix(Carbon $phase3FloorDate, int $fiscalYearId = 0): array
    {
        $counts = [];
        $q = CfaSubmission::query()
            ->when($fiscalYearId > 0, fn ($inner) => $inner->where('fiscal_year_id', $fiscalYearId), fn ($inner) => $inner->where('created_at', '>=', $phase3FloorDate))
            ->whereNotNull('payload')
            ->orderByDesc('id');
        $q->cursor()
            ->each(function (CfaSubmission $row) use (&$counts): void {
                $cat = $row->payload['business_category'] ?? null;
                if (is_string($cat)) {
                    $cat = trim($cat);
                }
                if (! is_string($cat) || $cat === '') {
                    $cat = 'Not specified';
                }
                $counts[$cat] = ($counts[$cat] ?? 0) + 1;
            });

        if ($counts === []) {
            return ['labels' => [], 'values' => []];
        }

        arsort($counts);
        $labels = array_keys($counts);
        $values = array_map(intval(...), array_values($counts));

        if (count($labels) > 8) {
            $topLabels = array_slice($labels, 0, 7);
            $topValues = array_slice($values, 0, 7);
            $otherSum = (int) array_sum(array_slice($values, 7));
            if ($otherSum > 0) {
                $topLabels[] = 'Other';
                $topValues[] = $otherSum;
            }

            return ['labels' => $topLabels, 'values' => $topValues];
        }

        return ['labels' => $labels, 'values' => $values];
    }

    /**
     * @param  list<string>  $labels
     * @return list<string>
     */
    private function chartColorsForLabels(array $labels): array
    {
        $palette = [
            '#4f46e5', '#0d9488', '#ea580c', '#7c3aed', '#0891b2',
            '#db2777', '#ca8a04', '#16a34a', '#e11d48', '#2563eb',
            '#059669', '#d946ef', '#f97316',
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
}
