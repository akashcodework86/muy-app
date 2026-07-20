<?php

namespace App\Services;

use App\Models\CfaSubmission;
use App\Models\Deliverable;
use App\Models\District;
use App\Models\DistrictDeliverableTarget;
use App\Models\FiscalYear;
use App\Models\Hub;
use App\Models\MentorshipRequest;
use App\Models\ServiceCase;
use App\Models\User;
use App\Services\Cfa\CfaSubmissionListQuery;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class HubAdminDashboardService
{
    public function __construct(
        private readonly StaffCheckInService $staffCheckIns,
        private readonly StaffMonthlyTargetsDashboardService $staffTargetsDashboard,
        private readonly AdminDashboardInsightsService $insightsService,
        private readonly HubFieldActivityHighlightsService $fieldHighlightsService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function metrics(User $user): array
    {
        if ($user->role !== 'hub_admin' || ! $user->hub_id) {
            abort(403);
        }

        $hubId = (int) $user->hub_id;
        $hub = Hub::query()->findOrFail($hubId);
        $districtIds = District::query()->where('hub_id', $hubId)->pluck('id')->map(fn ($id) => (int) $id)->all();

        $activeFy = FiscalYear::phase3Default();
        $activeFyId = (int) ($activeFy?->id ?? 0);
        $cfaDeliverable = Deliverable::query()->where('code', 'cfa')->first();

        $hubCfaTargetSum = null;
        if ($activeFy && $cfaDeliverable && $districtIds !== []) {
            $hubCfaTargetSum = (int) DistrictDeliverableTarget::query()
                ->where('fiscal_year_id', $activeFy->id)
                ->where('deliverable_id', $cfaDeliverable->id)
                ->whereIn('district_id', $districtIds)
                ->sum('target_total');
        }

        $staffTotal = User::query()->where('role', 'district_staff')->where('hub_id', $hubId)->count();
        $staffActive = User::query()->where('role', 'district_staff')->where('hub_id', $hubId)->where('is_active', true)->count();

        $cfaBase = CfaSubmission::query()
            ->when($districtIds !== [], fn ($q) => $q->whereIn('district_id', $districtIds))
            ->when($activeFyId > 0, fn ($q) => $q->where('fiscal_year_id', $activeFyId));
        $cfaTotal = (clone $cfaBase)->count();

        $hubCfaThisFy = null;
        if ($activeFyId > 0 && $districtIds !== []) {
            $hubCfaThisFy = (int) CfaSubmission::query()
                ->whereIn('district_id', $districtIds)
                ->where('fiscal_year_id', $activeFyId)
                ->count();
        }
        $cfaThisMonth = (clone $cfaBase)
            ->whereYear('created_at', now()->year)
            ->whereMonth('created_at', now()->month)
            ->count();
        $cfaLast30 = (clone $cfaBase)->where('created_at', '>=', now()->subDays(30))->count();
        $cfaLast7 = (int) (clone $cfaBase)->where('created_at', '>=', now()->subDays(6)->startOfDay())->count();
        $cfaPrev7 = (int) (clone $cfaBase)
            ->whereBetween('created_at', [now()->subDays(13)->startOfDay(), now()->subDays(7)->endOfDay()])
            ->count();
        $cfaWoWDeltaPct = $cfaPrev7 > 0
            ? (int) round((($cfaLast7 - $cfaPrev7) / $cfaPrev7) * 100)
            : ($cfaLast7 > 0 ? 100 : 0);

        $stageExpr = CfaSubmissionListQuery::payloadJsonExpr('$.form_stage');
        $stageQuery = DB::table('cfa_submissions')
            ->selectRaw('LOWER(TRIM('.$stageExpr.')) as stage_key')
            ->selectRaw('COUNT(*) as total');
        if ($districtIds !== []) {
            $stageQuery->whereIn('district_id', $districtIds);
        }
        if ($activeFyId > 0) {
            $stageQuery->where('fiscal_year_id', $activeFyId);
        }
        try {
            $stageCounts = $stageQuery->groupByRaw('LOWER(TRIM('.$stageExpr.'))')->pluck('total', 'stage_key');
        } catch (\Throwable) {
            $stageCounts = collect();
        }

        $cfaByDistrict = $districtIds === []
            ? collect()
            : DB::table('cfa_submissions')
                ->join('districts', 'cfa_submissions.district_id', '=', 'districts.id')
                ->whereIn('districts.id', $districtIds)
                ->when($activeFyId > 0, fn ($q) => $q->where('cfa_submissions.fiscal_year_id', $activeFyId))
                ->select('districts.name', DB::raw('COUNT(*) as total'))
                ->groupBy('districts.id', 'districts.name')
                ->orderByDesc('total')
                ->get();
        $todayDistrictRows = $districtIds === []
            ? collect()
            : DB::table('districts')
                ->leftJoin('cfa_submissions', function ($join): void {
                    $join->on('cfa_submissions.district_id', '=', 'districts.id')
                        ->whereDate('cfa_submissions.created_at', now()->toDateString());
                })
                ->whereIn('districts.id', $districtIds)
                ->when($activeFyId > 0, fn ($q) => $q->where('cfa_submissions.fiscal_year_id', $activeFyId))
                ->select('districts.name', DB::raw('COUNT(cfa_submissions.id) as total'))
                ->groupBy('districts.id', 'districts.name')
                ->orderByDesc('total')
                ->orderBy('districts.name')
                ->get();
        $todayTopDistrict = $todayDistrictRows->first();
        $todayZeroDistricts = max(0, (int) $todayDistrictRows->count() - (int) $todayDistrictRows->filter(fn ($r) => (int) $r->total > 0)->count());

        $onboardingDeliverableIds = $this->onboardingDeliverableIds();
        $hubOnboardingTarget = null;
        if ($activeFy && $districtIds !== [] && $onboardingDeliverableIds !== []) {
            $hubOnboardingTarget = (int) DistrictDeliverableTarget::query()
                ->where('fiscal_year_id', (int) $activeFy->id)
                ->whereIn('deliverable_id', $onboardingDeliverableIds)
                ->whereIn('district_id', $districtIds)
                ->sum('target_total');
            if ($hubOnboardingTarget <= 0) {
                $hubOnboardingTarget = null;
            }
        }
        $phase3FloorDate = Carbon::create(2026, 4, 1)->startOfDay();
        $hubOnboardingAchieved = (int) DB::table('onboarding_batch_cfa as obc')
            ->join('onboarding_batches as ob', 'ob.id', '=', 'obc.onboarding_batch_id')
            ->where('ob.hub_id', $hubId)
            ->where('ob.status', 'locked')
            ->whereNotNull('ob.locked_at')
            ->where('ob.locked_at', '>=', $phase3FloorDate)
            ->count();
        $hubOnboardingProgressPct = ($hubOnboardingTarget !== null && $hubOnboardingTarget > 0)
            ? (int) round(($hubOnboardingAchieved / $hubOnboardingTarget) * 100)
            : null;
        $hubOnboardingByDistrict = DB::table('onboarding_batch_cfa as obc')
            ->join('onboarding_batches as ob', 'ob.id', '=', 'obc.onboarding_batch_id')
            ->join('districts as d', 'd.id', '=', 'ob.district_id')
            ->where('ob.hub_id', $hubId)
            ->where('ob.status', 'locked')
            ->whereNotNull('ob.locked_at')
            ->where('ob.locked_at', '>=', $phase3FloorDate)
            ->groupBy('d.id', 'd.name')
            ->orderByDesc(DB::raw('COUNT(obc.id)'))
            ->orderBy('d.name')
            ->select('d.name', DB::raw('COUNT(obc.id) as total'))
            ->get()
            ->map(fn ($row) => ['district' => (string) $row->name, 'count' => (int) $row->total])
            ->all();

        $staffByDistrict = DB::table('users')
            ->join('districts', 'users.district_id', '=', 'districts.id')
            ->where('users.role', 'district_staff')
            ->where('users.hub_id', $hubId)
            ->select('districts.name', DB::raw('COUNT(*) as total'))
            ->groupBy('districts.id', 'districts.name')
            ->orderByDesc('total')
            ->get();

        $staffCfaByStaff = DB::table('users')
            ->leftJoin('districts', 'users.district_id', '=', 'districts.id')
            ->leftJoin('cfa_submissions as cs', function ($join) use ($activeFyId): void {
                $join->on('cs.referral_user_id', '=', 'users.id')
                    ->on('cs.district_id', '=', 'users.district_id');
                if ($activeFyId > 0) {
                    $join->where('cs.fiscal_year_id', $activeFyId);
                }
            })
            ->where('users.role', 'district_staff')
            ->where('users.hub_id', $hubId)
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

        $notLinkedByDistrict = $districtIds === []
            ? collect()
            : DB::table('cfa_submissions as cs')
                ->join('districts as d', 'd.id', '=', 'cs.district_id')
                ->whereIn('cs.district_id', $districtIds)
                ->whereNull('cs.referral_user_id')
                ->when($activeFyId > 0, fn ($q) => $q->where('cs.fiscal_year_id', $activeFyId))
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
            ->where('role', 'district_staff')
            ->where('hub_id', $hubId)
            ->get(['id', 'avatar_path'])
            ->keyBy('id');

        $staffPerformanceCards = $activeFy
            ? $this->buildHubStaffPerformanceCards($hubId, $activeFy, $staffAvatarMap)
            : [];

        $trendLabels = [];
        $trendValues = [];
        for ($i = 13; $i >= 0; $i--) {
            $day = Carbon::now()->subDays($i)->startOfDay();
            $trendLabels[] = $day->format('d M');
            $q = CfaSubmission::query()->whereDate('created_at', $day->toDateString());
            if ($districtIds !== []) {
                $q->whereIn('district_id', $districtIds);
            }
            if ($activeFyId > 0) {
                $q->where('fiscal_year_id', $activeFyId);
            }
            $trendValues[] = (int) $q->count();
        }

        $businessMix = $this->businessCategoryMix($districtIds, $activeFyId);

        $heroCfaToday = 0;
        $heroCfaYesterday = 0;
        if ($districtIds !== []) {
            $heroCfaToday = (int) CfaSubmission::query()
                ->whereIn('district_id', $districtIds)
                ->whereDate('created_at', now()->toDateString())
                ->when($activeFyId > 0, fn ($q) => $q->where('fiscal_year_id', $activeFyId))
                ->count();
            $heroCfaYesterday = (int) CfaSubmission::query()
                ->whereIn('district_id', $districtIds)
                ->whereDate('created_at', now()->subDay()->toDateString())
                ->when($activeFyId > 0, fn ($q) => $q->where('fiscal_year_id', $activeFyId))
                ->count();
        }
        $heroCfaTodayDelta = $heroCfaToday - $heroCfaYesterday;

        $heroMentorshipPending = 0;
        try {
            if (Schema::hasTable('mentorship_requests') && $districtIds !== []) {
                $heroMentorshipPending = (int) MentorshipRequest::query()
                    ->where('status', MentorshipRequest::STATUS_PENDING)
                    ->whereHas('cfaSubmission', function ($q) use ($districtIds): void {
                        $q->whereIn('district_id', $districtIds);
                    })
                    ->count();
            }
        } catch (\Throwable $e) {
            $heroMentorshipPending = 0;
        }

        $heroStaffOnlineNow = 0;
        if (Schema::hasColumn('users', 'last_seen_at')) {
            $heroStaffOnlineNow = (int) User::query()
                ->where('hub_id', $hubId)
                ->where('last_seen_at', '>=', now()->subMinutes(3))
                ->count();
        }

        $heroSparkline30 = $this->hubDailyCfaSparkline($districtIds, 30, $activeFyId);

        $heroProgressPct = null;
        $heroRemaining = null;
        if ($hubCfaTargetSum !== null && (int) $hubCfaTargetSum > 0) {
            $heroProgressPct = (int) min(100, round(((int) ($hubCfaThisFy ?? 0) / (int) $hubCfaTargetSum) * 100));
            $heroRemaining = max(0, (int) $hubCfaTargetSum - (int) ($hubCfaThisFy ?? 0));
        }

        $hubServicesTargetSum = null;
        $hubTargetPlan = $this->emptyHubTargetPlan();
        $servicesDeliveredCounts = ['till_date' => 0, 'this_fy' => 0];
        $heroServicesProgressPct = null;
        $heroServicesRemaining = null;

        if ($activeFy && $districtIds !== []) {
            $hubServicesTargetSum = $this->hubServicesTargetSum((int) $activeFy->id, $districtIds);
            $cfaDeliverableId = $cfaDeliverable ? (int) $cfaDeliverable->id : 0;
            $hubTargetPlan = $this->hubTargetPlanMetrics((int) $activeFy->id, $districtIds, $cfaDeliverableId);
            $servicesDeliveredCounts = $this->hubServicesDeliveredCounts($activeFy, $districtIds);

            if ($hubServicesTargetSum !== null && $hubServicesTargetSum > 0) {
                $heroServicesProgressPct = (int) min(
                    100,
                    round(($servicesDeliveredCounts['this_fy'] / $hubServicesTargetSum) * 100)
                );
                $heroServicesRemaining = max(0, $hubServicesTargetSum - $servicesDeliveredCounts['this_fy']);
            }
        }

        $phase3FloorDateLabel = $phase3FloorDate->format('d M Y');
        $phase3Scope = CfaSubmission::query()
            ->when($districtIds !== [], fn ($q) => $q->whereIn('district_id', $districtIds))
            ->when($activeFyId > 0, fn ($q) => $q->where('fiscal_year_id', $activeFyId), fn ($q) => $q->where('created_at', '>=', $phase3FloorDate));

        $cfaDeliverableIds = $cfaDeliverable ? [(int) $cfaDeliverable->id] : [];

        try {
            $insights = $this->insightsService->build(
                phase3Scope: $phase3Scope,
                districtIds: $districtIds,
                hubId: $hubId,
                phase3FloorDate: $phase3FloorDate,
                activeFyId: $activeFyId,
                cfaDeliverableIds: $cfaDeliverableIds,
                activeFy: $activeFy,
                cfaByDistrict: [
                    'labels' => $cfaByDistrict->pluck('name')->all(),
                    'values' => $cfaByDistrict->pluck('total')->map(fn ($v) => (int) $v)->all(),
                ],
                onboardedCount: (int) $hubOnboardingAchieved,
                servicesDelivered: (int) ($servicesDeliveredCounts['till_date'] ?? 0),
            );
        } catch (\Throwable) {
            $insights = $this->insightsService->emptyInsights();
        }

        $estimatedSavings = $this->insightsService->estimatedSavings($activeFy, $phase3FloorDate, $districtIds);
        if (($businessMix['labels'] ?? []) !== []) {
            $palette = ['#26a69a', '#42a5f5', '#ff8a65', '#ffca28', '#f06292', '#66bb6a', '#ab47bc', '#78909c'];
            $businessMix['colors'] = array_map(
                fn ($i) => $palette[$i % count($palette)],
                array_keys($businessMix['labels'])
            );
        }

        $fieldHighlights = $this->fieldHighlightsService->forHub(
            $hubId,
            $districtIds,
            $activeFy,
            $phase3FloorDate,
        );

        return [
            'hub' => $hub,
            'districtsInHub' => count($districtIds),
            'activeFy' => $activeFy,
            'cfaDeliverable' => $cfaDeliverable,
            'hubCfaTargetSum' => $hubCfaTargetSum,
            'hubServicesTargetSum' => $hubServicesTargetSum,
            'hubTargetPlan' => $hubTargetPlan,
            'servicesDeliveredTillDate' => $servicesDeliveredCounts['till_date'],
            'servicesDeliveredThisFy' => $servicesDeliveredCounts['this_fy'],
            'heroServicesProgressPct' => $heroServicesProgressPct,
            'heroServicesRemaining' => $heroServicesRemaining,
            'staffTotal' => $staffTotal,
            'staffActive' => $staffActive,
            'cfaTotal' => $cfaTotal,
            'hubCfaThisFy' => $hubCfaThisFy,
            'cfaThisMonth' => $cfaThisMonth,
            'cfaLast30' => $cfaLast30,
            'cfaLast7' => $cfaLast7,
            'cfaPrev7' => $cfaPrev7,
            'cfaWoWDeltaPct' => $cfaWoWDeltaPct,
            'seedCount' => (int) ($stageCounts['seed'] ?? 0),
            'earlyCount' => (int) ($stageCounts['early'] ?? 0),
            'growthCount' => (int) ($stageCounts['growth'] ?? 0),
            'cfaByDistrict' => [
                'labels' => $cfaByDistrict->pluck('name')->all(),
                'values' => $cfaByDistrict->pluck('total')->map(fn ($v) => (int) $v)->all(),
            ],
            'staffByDistrict' => [
                'labels' => $staffByDistrict->pluck('name')->all(),
                'values' => $staffByDistrict->pluck('total')->map(fn ($v) => (int) $v)->all(),
            ],
            'staffCfaByStaff' => $staffCfaByStaff->map(fn ($row) => [
                'id' => $row->id ? (int) $row->id : null,
                'name' => (string) $row->name,
                'district' => (string) $row->district_name,
                'cfa_total' => (int) $row->cfa_total,
                'avatar_url' => $row->id ? $staffAvatarMap->get((int) $row->id)?->avatarUrl() : null,
            ])->all(),
            'staffPerformanceCards' => $staffPerformanceCards,
            'cfaTrend' => [
                'labels' => $trendLabels,
                'values' => $trendValues,
            ],
            'businessMix' => $businessMix,
            'heroCfaToday' => $heroCfaToday,
            'heroCfaYesterday' => $heroCfaYesterday,
            'heroCfaTodayDelta' => $heroCfaTodayDelta,
            'heroMentorshipPending' => $heroMentorshipPending,
            'heroStaffOnlineNow' => $heroStaffOnlineNow,
            'heroSparkline30' => $heroSparkline30,
            'heroProgressPct' => $heroProgressPct,
            'heroRemaining' => $heroRemaining,
            'todayTopDistrict' => $todayTopDistrict ? ['name' => (string) $todayTopDistrict->name, 'count' => (int) $todayTopDistrict->total] : null,
            'todayZeroDistricts' => $todayZeroDistricts,
            'hubOnboardingTarget' => $hubOnboardingTarget,
            'hubOnboardingAchieved' => $hubOnboardingAchieved,
            'hubOnboardingProgressPct' => $hubOnboardingProgressPct,
            'hubOnboardingByDistrict' => $hubOnboardingByDistrict,
            'attendance' => $this->staffCheckIns->hubAttendanceMetrics($hubId),
            'insights' => $insights,
            'estimatedSavings' => $estimatedSavings,
            'fieldHighlights' => $fieldHighlights,
            'phase3FloorDateLabel' => $phase3FloorDateLabel,
            'stateCfaTrend' => [
                'labels' => $trendLabels,
                'values' => $trendValues,
            ],
            'districtsCount' => count($districtIds),
            'deliverablesCount' => Deliverable::query()->where('is_active', true)->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function onboardingDistrictInsight(User $user): array
    {
        if ($user->role !== 'hub_admin' || ! $user->hub_id) {
            abort(403);
        }

        $hubId = (int) $user->hub_id;
        $hub = Hub::query()->findOrFail($hubId);
        $districts = District::query()
            ->where('hub_id', $hubId)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name']);
        $districtIds = $districts->pluck('id')->map(fn ($id) => (int) $id)->all();

        $activeFy = FiscalYear::phase3Default();
        $onboardingDeliverableIds = $this->onboardingDeliverableIds();
        $phase3FloorDate = Carbon::create(2026, 4, 1)->startOfDay();
        $today = now()->startOfDay();
        $fyStart = $activeFy?->starts_on ? Carbon::parse($activeFy->starts_on)->startOfDay() : null;
        $fyEnd = $activeFy?->ends_on ? Carbon::parse($activeFy->ends_on)->endOfDay() : null;
        $expectedPctByNow = null;
        if ($fyStart && $fyEnd && $fyEnd->greaterThan($fyStart)) {
            if ($today->lt($fyStart)) {
                $expectedPctByNow = 0;
            } elseif ($today->gt($fyEnd)) {
                $expectedPctByNow = 100;
            } else {
                $totalDays = max(1, $fyStart->diffInDays($fyEnd));
                $elapsedDays = max(0, $fyStart->diffInDays($today));
                $expectedPctByNow = (int) round(min(100, max(0, ($elapsedDays / $totalDays) * 100)));
            }
        }

        $targetsByDistrict = collect();
        if ($activeFy && $districtIds !== [] && $onboardingDeliverableIds !== []) {
            $targetsByDistrict = DistrictDeliverableTarget::query()
                ->where('fiscal_year_id', (int) $activeFy->id)
                ->whereIn('deliverable_id', $onboardingDeliverableIds)
                ->whereIn('district_id', $districtIds)
                ->selectRaw('district_id, SUM(target_total) as target_total')
                ->groupBy('district_id')
                ->pluck('target_total', 'district_id');
        }

        $achievedByDistrict = $districtIds === []
            ? collect()
            : DB::table('onboarding_batch_cfa as obc')
                ->join('onboarding_batches as ob', 'ob.id', '=', 'obc.onboarding_batch_id')
                ->where('ob.hub_id', $hubId)
                ->where('ob.status', 'locked')
                ->whereNotNull('ob.locked_at')
                ->where('ob.locked_at', '>=', $phase3FloorDate)
                ->selectRaw('ob.district_id, COUNT(obc.id) as achieved_total')
                ->groupBy('ob.district_id')
                ->pluck('achieved_total', 'ob.district_id');

        $rows = $districts->map(function (District $district) use ($targetsByDistrict, $achievedByDistrict, $expectedPctByNow): array {
            $districtId = (int) $district->id;
            $target = (int) ($targetsByDistrict[$districtId] ?? 0);
            $achieved = (int) ($achievedByDistrict[$districtId] ?? 0);
            $progressPct = $target > 0 ? (int) round(($achieved / $target) * 100) : null;
            $gap = max(0, $target - $achieved);
            $expectedAchievedByNow = ($target > 0 && $expectedPctByNow !== null)
                ? (int) round(($target * $expectedPctByNow) / 100)
                : null;
            $paceDelta = ($target > 0 && $expectedAchievedByNow !== null)
                ? ($achieved - $expectedAchievedByNow)
                : null;

            if ($target <= 0) {
                $smartAnalysis = 'Target not configured. Add district onboarding target to track this district.';
            } elseif ($expectedPctByNow === null) {
                $smartAnalysis = 'FY timeline is unavailable. Progress is shown against full-year target only.';
            } elseif ($achieved >= $target) {
                $smartAnalysis = 'Target achieved ahead of deadline. Keep quality checks tight while scaling.';
            } elseif ($paceDelta !== null && $paceDelta >= 0) {
                $smartAnalysis = 'On track for FY timeline. Current onboarding is meeting expected pace by now.';
            } elseif ($achieved === 0) {
                $smartAnalysis = 'No onboarding achieved yet. Pace is behind FY expectation; activate at least one locked batch.';
            } else {
                $smartAnalysis = 'Behind FY pace. Current onboarding is below expected level for this point in the year.';
            }

            return [
                'district_id' => $districtId,
                'district_name' => (string) $district->name,
                'target' => $target,
                'achieved' => $achieved,
                'progress_pct' => $progressPct,
                'gap' => $gap,
                'expected_pct' => $expectedPctByNow,
                'expected_achieved' => $expectedAchievedByNow,
                'pace_delta' => $paceDelta,
                'smart_analysis' => $smartAnalysis,
            ];
        })->sortByDesc('achieved')->values();

        $totalTarget = (int) $rows->sum('target');
        $totalAchieved = (int) $rows->sum('achieved');
        $totalGap = max(0, $totalTarget - $totalAchieved);
        $overallProgressPct = $totalTarget > 0 ? (int) round(($totalAchieved / $totalTarget) * 100) : null;
        $expectedAchievedByNow = ($totalTarget > 0 && $expectedPctByNow !== null)
            ? (int) round(($totalTarget * $expectedPctByNow) / 100)
            : null;
        $overallPaceDelta = $expectedAchievedByNow !== null ? ($totalAchieved - $expectedAchievedByNow) : null;
        $districtsWithoutTarget = (int) $rows->filter(fn (array $row) => (int) $row['target'] <= 0)->count();
        $districtsWithZeroAchieved = (int) $rows->filter(fn (array $row) => (int) $row['target'] > 0 && (int) $row['achieved'] === 0)->count();

        return [
            'hub' => $hub,
            'activeFy' => $activeFy,
            'rows' => $rows,
            'totalTarget' => $totalTarget,
            'totalAchieved' => $totalAchieved,
            'totalGap' => $totalGap,
            'overallProgressPct' => $overallProgressPct,
            'expectedPctByNow' => $expectedPctByNow,
            'expectedAchievedByNow' => $expectedAchievedByNow,
            'overallPaceDelta' => $overallPaceDelta,
            'districtsWithoutTarget' => $districtsWithoutTarget,
            'districtsWithZeroAchieved' => $districtsWithZeroAchieved,
        ];
    }

    /**
     * @return array{till_date: int, this_fy: int}
     */
    private function hubServicesDeliveredCounts(FiscalYear $activeFy, array $districtIds): array
    {
        if (
            $districtIds === []
            || ! Schema::hasTable('service_cases')
            || ! Schema::hasColumn('service_cases', 'status')
        ) {
            return ['till_date' => 0, 'this_fy' => 0];
        }

        $base = DB::table('service_cases as sc')
            ->join('cfa_submissions as cs', 'cs.id', '=', 'sc.cfa_submission_id')
            ->whereIn('cs.district_id', $districtIds)
            ->where('sc.status', ServiceCase::STATUS_APPROVED);

        $tillDate = (int) (clone $base)->count();

        $fyStart = $activeFy->starts_on
            ? Carbon::parse($activeFy->starts_on)->startOfDay()
            : now()->startOfYear();
        $fyEnd = $activeFy->ends_on
            ? Carbon::parse($activeFy->ends_on)->endOfDay()
            : now()->endOfDay();

        $approvedAtExpr = Schema::hasColumn('service_cases', 'approved_at')
            ? 'COALESCE(sc.approved_at, sc.completed_at, sc.created_at)'
            : (Schema::hasColumn('service_cases', 'completed_at') ? 'COALESCE(sc.completed_at, sc.created_at)' : 'sc.created_at');

        $thisFy = (int) (clone $base)
            ->whereBetween(DB::raw($approvedAtExpr), [$fyStart, $fyEnd])
            ->count();

        return ['till_date' => $tillDate, 'this_fy' => $thisFy];
    }

    private function hubServicesTargetSum(int $fiscalYearId, array $districtIds): ?int
    {
        if ($districtIds === [] || ! Schema::hasTable('district_deliverable_targets')) {
            return null;
        }

        $serviceDeliverableIds = Deliverable::query()
            ->where('is_active', true)
            ->where('code', 'like', 'svc_%')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($serviceDeliverableIds === []) {
            return null;
        }

        $sum = (int) DB::table('district_deliverable_targets')
            ->where('fiscal_year_id', $fiscalYearId)
            ->whereIn('district_id', $districtIds)
            ->whereIn('deliverable_id', $serviceDeliverableIds)
            ->sum('target_total');

        return $sum > 0 ? $sum : null;
    }

    /**
     * District target vs staff monthly roll-up (hub districts only).
     *
     * @param  list<int>  $districtIds
     * @return array<string, mixed>
     */
    private function emptyHubTargetPlan(): array
    {
        return [
            'pct' => null,
            'aligned_count' => 0,
            'tracked_count' => 0,
            'all_aligned' => false,
            'cfa' => ['district_target' => 0, 'staff_sum' => 0, 'aligned' => false, 'tracked' => false],
            'services' => ['district_target' => 0, 'staff_sum' => 0, 'aligned_count' => 0, 'tracked_count' => 0, 'all_aligned' => false],
            'misaligned' => [],
        ];
    }

    /**
     * @param  list<int>  $districtIds
     * @return array<string, mixed>
     */
    private function hubTargetPlanMetrics(int $fiscalYearId, array $districtIds, int $cfaDeliverableId): array
    {
        $result = $this->emptyHubTargetPlan();
        if ($districtIds === [] || ! Schema::hasTable('district_deliverable_targets')) {
            return $result;
        }

        $onboardingId = Deliverable::onboardingTargetDeliverableId();
        $serviceDeliverableIds = Deliverable::query()
            ->where('is_active', true)
            ->where('code', 'like', 'svc_%')
            ->when($onboardingId !== null, fn ($q) => $q->where('id', '!=', $onboardingId))
            ->when($cfaDeliverableId > 0, fn ($q) => $q->where('id', '!=', $cfaDeliverableId))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $deliverableIds = collect($cfaDeliverableId > 0 ? [$cfaDeliverableId] : [])
            ->merge($serviceDeliverableIds)
            ->unique()
            ->values()
            ->all();

        if ($deliverableIds === []) {
            return $result;
        }

        $deliverables = Deliverable::query()
            ->whereIn('id', $deliverableIds)
            ->orderBy('sort_order')
            ->get(['id', 'code', 'name', 'mis_entry_label']);

        $districtTargets = DB::table('district_deliverable_targets')
            ->where('fiscal_year_id', $fiscalYearId)
            ->whereIn('district_id', $districtIds)
            ->whereIn('deliverable_id', $deliverableIds)
            ->get(['district_id', 'deliverable_id', 'target_total']);

        $districtNames = District::query()
            ->whereIn('id', $districtIds)
            ->pluck('name', 'id');

        $hasStaffMonthlies = Schema::hasTable('staff_monthly_targets');
        $cfaIdSet = $cfaDeliverableId > 0 ? [$cfaDeliverableId => true] : [];
        $alignedCount = 0;
        $trackedCount = 0;
        $misaligned = [];
        $cfaDistrictTarget = 0;
        $cfaStaffSum = 0;
        $cfaTracked = false;
        $cfaAligned = true;
        $svcDistrictTarget = 0;
        $svcStaffSum = 0;
        $svcAlignedCount = 0;
        $svcTrackedCount = 0;

        foreach ($districtTargets as $row) {
            $districtId = (int) $row->district_id;
            $deliverableId = (int) $row->deliverable_id;
            $districtTarget = (int) $row->target_total;
            if ($districtTarget <= 0) {
                continue;
            }

            $deliverable = $deliverables->firstWhere('id', $deliverableId);
            $label = $deliverable
                ? (string) ($deliverable->mis_entry_label ?: $deliverable->name)
                : 'Deliverable';
            $isCfa = isset($cfaIdSet[$deliverableId]);

            $staffSum = 0;
            if ($hasStaffMonthlies) {
                $staffSum = (int) DB::table('staff_monthly_targets as smt')
                    ->join('users as u', 'u.id', '=', 'smt.user_id')
                    ->where('smt.fiscal_year_id', $fiscalYearId)
                    ->where('smt.deliverable_id', $deliverableId)
                    ->where('u.district_id', $districtId)
                    ->where('u.role', 'district_staff')
                    ->sum('smt.target_count');
            }

            if ($isCfa) {
                $cfaDistrictTarget += $districtTarget;
                $cfaStaffSum += $staffSum;
            } else {
                $svcDistrictTarget += $districtTarget;
                $svcStaffSum += $staffSum;
            }

            $trackedCount++;
            if ($isCfa) {
                $cfaTracked = true;
            } else {
                $svcTrackedCount++;
            }

            $isAligned = $staffSum === $districtTarget;
            if ($isAligned) {
                $alignedCount++;
                if (! $isCfa) {
                    $svcAlignedCount++;
                }
            } else {
                if ($isCfa) {
                    $cfaAligned = false;
                }
                $misaligned[] = [
                    'district' => (string) ($districtNames[$districtId] ?? 'District'),
                    'name' => $label,
                    'district_target' => $districtTarget,
                    'staff_sum' => $staffSum,
                    'gap' => abs($districtTarget - $staffSum),
                    'kind' => $isCfa ? 'cfa' : 'service',
                ];
            }
        }

        $result['aligned_count'] = $alignedCount;
        $result['tracked_count'] = $trackedCount;
        $result['all_aligned'] = $trackedCount > 0 && $alignedCount === $trackedCount;
        $result['pct'] = $trackedCount > 0 ? (int) round(($alignedCount / $trackedCount) * 100) : null;
        $result['cfa'] = [
            'district_target' => $cfaDistrictTarget,
            'staff_sum' => $cfaStaffSum,
            'aligned' => $cfaTracked && $cfaAligned,
            'tracked' => $cfaTracked,
        ];
        $result['services'] = [
            'district_target' => $svcDistrictTarget,
            'staff_sum' => $svcStaffSum,
            'aligned_count' => $svcAlignedCount,
            'tracked_count' => $svcTrackedCount,
            'all_aligned' => $svcTrackedCount > 0 && $svcAlignedCount === $svcTrackedCount,
        ];
        $result['misaligned'] = $misaligned;

        return $result;
    }

    /**
     * @return list<int>
     */
    private function onboardingDeliverableIds(): array
    {
        $id = Deliverable::onboardingTargetDeliverableId();

        return $id !== null ? [$id] : [];
    }

    /**
     * @param  list<int>  $districtIds
     * @return array{labels: list<string>, values: list<int>}
     */
    private function hubDailyCfaSparkline(array $districtIds, int $days, int $fiscalYearId = 0): array
    {
        $labels = [];
        $values = [];
        if ($districtIds === []) {
            for ($i = $days - 1; $i >= 0; $i--) {
                $labels[] = now()->subDays($i)->format('d M');
                $values[] = 0;
            }
            return ['labels' => $labels, 'values' => $values];
        }

        $start = now()->subDays($days - 1)->startOfDay();
        $rows = DB::table('cfa_submissions')
            ->selectRaw('DATE(created_at) as d, COUNT(*) as total')
            ->whereIn('district_id', $districtIds)
            ->where('created_at', '>=', $start)
            ->when($fiscalYearId > 0, fn ($q) => $q->where('fiscal_year_id', $fiscalYearId))
            ->groupBy('d')
            ->pluck('total', 'd');

        for ($i = $days - 1; $i >= 0; $i--) {
            $day = now()->subDays($i);
            $labels[] = $day->format('d M');
            $values[] = (int) ($rows[$day->toDateString()] ?? 0);
        }

        return ['labels' => $labels, 'values' => $values];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, User>  $staffAvatarMap
     * @return list<array<string, mixed>>
     */
    private function buildHubStaffPerformanceCards(int $hubId, FiscalYear $fy, $staffAvatarMap): array
    {
        $staffUsers = User::query()
            ->where('role', 'district_staff')
            ->where('hub_id', $hubId)
            ->where('is_active', true)
            ->with(['district:id,name'])
            ->orderBy('name')
            ->get();

        $cards = [];
        foreach ($staffUsers as $user) {
            $deliverableRows = [];
            $targetSum = 0;
            $achievedSum = 0;
            $scoreParts = 0;
            $scoreWeight = 0;
            $cfaTotal = 0;
            $servicesActive = 0;

            foreach ($this->staffTargetsDashboard->buildRows($user, $fy) as $row) {
                if (! $row['tracksAchievement']) {
                    continue;
                }

                $target = (int) $row['monthlySum'];
                $achieved = (int) $row['achievementAnnual'];
                if ($target <= 0 && $achieved <= 0) {
                    continue;
                }

                /** @var Deliverable $deliverable */
                $deliverable = $row['deliverable'];
                $code = (string) $deliverable->code;
                $pct = $target > 0 ? (int) min(100, round(($achieved / $target) * 100)) : null;

                if ($code === 'cfa') {
                    $cfaTotal = $achieved;
                }
                if (str_starts_with($code, 'svc_') && $achieved > 0) {
                    $servicesActive++;
                }

                if ($target > 0) {
                    $targetSum += $target;
                    $achievedSum += $achieved;
                    $scoreParts += ($achieved / $target) * 100;
                    $scoreWeight++;
                }

                $deliverableRows[] = [
                    'name' => (string) ($deliverable->mis_entry_label ?: $deliverable->name),
                    'code' => $code,
                    'target' => $target,
                    'achieved' => $achieved,
                    'pct' => $pct,
                    'is_service' => str_starts_with($code, 'svc_'),
                ];
            }

            usort($deliverableRows, function (array $a, array $b): int {
                if ($a['achieved'] !== $b['achieved']) {
                    return $b['achieved'] <=> $a['achieved'];
                }

                return strcasecmp((string) $a['name'], (string) $b['name']);
            });

            $performancePct = $scoreWeight > 0 ? (int) round($scoreParts / $scoreWeight) : null;

            $cards[] = [
                'id' => (int) $user->id,
                'name' => (string) $user->name,
                'district' => (string) ($user->district?->name ?? 'Unassigned'),
                'avatar_url' => $staffAvatarMap->get((int) $user->id)?->avatarUrl(),
                'cfa_total' => $cfaTotal,
                'services_active' => $servicesActive,
                'target_total' => $targetSum,
                'achieved_total' => $achievedSum,
                'performance_pct' => $performancePct,
                'deliverables' => $deliverableRows,
            ];
        }

        usort($cards, function (array $a, array $b): int {
            $pa = $a['performance_pct'] ?? -1;
            $pb = $b['performance_pct'] ?? -1;
            if ($pa !== $pb) {
                return $pb <=> $pa;
            }

            return ((int) $b['cfa_total']) <=> ((int) $a['cfa_total']);
        });

        return $cards;
    }

    /**
     * @param  list<int>  $districtIds
     * @return array{labels: list<string>, values: list<int>}
     */
    private function businessCategoryMix(array $districtIds, int $fiscalYearId = 0): array
    {
        if ($districtIds === []) {
            return ['labels' => [], 'values' => []];
        }

        $counts = [];
        CfaSubmission::query()
            ->whereIn('district_id', $districtIds)
            ->when($fiscalYearId > 0, fn ($q) => $q->where('fiscal_year_id', $fiscalYearId))
            ->whereNotNull('payload')
            ->orderByDesc('id')
            ->cursor()
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
        $values = array_values($counts);

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
}
