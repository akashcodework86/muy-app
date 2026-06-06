<?php

namespace App\Services;

use App\Models\CfaSubmission;
use App\Models\Deliverable;
use App\Models\FiscalYear;
use App\Models\MentorshipRequest;
use App\Models\ServiceCase;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class StaffDashboardService
{
    public function __construct(
        private StaffDeliverableMonthlyTargetService $monthlyTargets,
        private AdminDashboardInsightsService $insightsService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function metrics(User $user): array
    {
        if ($user->role !== 'district_staff') {
            abort(403);
        }

        $user->load(['district.hub', 'hub', 'designationRecord']);

        $activeFy = FiscalYear::phase3Default();
        $activeFyId = (int) ($activeFy?->id ?? 0);
        $cfaDeliverable = Deliverable::query()->where('code', 'cfa')->first();

        $staffAnnualTarget = null;
        if ($activeFy && $cfaDeliverable) {
            $staffAnnualTarget = $this->monthlyTargets->userAnnualTotal(
                (int) $activeFy->id,
                (int) $user->id,
                (int) $cfaDeliverable->id
            );
        }

        $districtCfaTarget = null;
        if ($activeFy && $cfaDeliverable && $user->district_id) {
            $districtCfaTarget = $this->monthlyTargets->districtTargetTotal(
                (int) $activeFy->id,
                (int) $user->district_id,
                (int) $cfaDeliverable->id
            );
        }

        $districtCfaTotal = null;
        $districtCfaTrend = ['labels' => [], 'values' => []];
        $districtBusinessStageMix = ['labels' => [], 'values' => []];
        $districtCfaByReferrer = ['rows' => [], 'total' => 0];
        if ($user->district_id) {
            $districtCfaTotal = CfaSubmission::query()
                ->where('district_id', (int) $user->district_id)
                ->when($activeFyId > 0, fn ($q) => $q->where('fiscal_year_id', $activeFyId))
                ->count();
            $districtCfaTrend = $this->districtDailyTrend14((int) $user->district_id, $activeFyId);
            $districtBusinessStageMix = $this->districtStageMixAggregates((int) $user->district_id, $activeFyId);
            $districtCfaByReferrer = $this->districtCfaBreakdownByReferrer(
                (int) $user->district_id,
                (int) $user->id,
                $activeFyId
            );
        }

        $districtProgressPct = null;
        if ($districtCfaTarget !== null && (int) $districtCfaTarget > 0 && $districtCfaTotal !== null) {
            $districtProgressPct = (int) min(100, round(($districtCfaTotal / (int) $districtCfaTarget) * 100));
        }

        $districtOnboardingTarget = null;
        $districtOnboardingAchieved = 0;
        $districtOnboardingProgressPct = null;
        $districtOnboardingByBatch = [];
        if ($activeFy && $user->district_id) {
            $onboardingDeliverableId = Deliverable::onboardingTargetDeliverableId();
            if ($onboardingDeliverableId !== null) {
                $districtOnboardingTarget = (int) DB::table('district_deliverable_targets')
                    ->where('fiscal_year_id', (int) $activeFy->id)
                    ->where('district_id', (int) $user->district_id)
                    ->where('deliverable_id', $onboardingDeliverableId)
                    ->value('target_total');
                if ($districtOnboardingTarget <= 0) {
                    $districtOnboardingTarget = null;
                }
            }

            $phase3FloorDate = Carbon::create(2026, 4, 1)->startOfDay();
            $districtOnboardingAchieved = (int) DB::table('onboarding_batch_cfa as obc')
                ->join('onboarding_batches as ob', 'ob.id', '=', 'obc.onboarding_batch_id')
                ->where('ob.district_id', (int) $user->district_id)
                ->where('ob.status', 'locked')
                ->whereNotNull('ob.locked_at')
                ->where('ob.locked_at', '>=', $phase3FloorDate)
                ->count();

            $districtOnboardingByBatch = DB::table('onboarding_batch_cfa as obc')
                ->join('onboarding_batches as ob', 'ob.id', '=', 'obc.onboarding_batch_id')
                ->where('ob.district_id', (int) $user->district_id)
                ->where('ob.status', 'locked')
                ->whereNotNull('ob.locked_at')
                ->where('ob.locked_at', '>=', $phase3FloorDate)
                ->groupBy('ob.id', 'ob.name')
                ->orderByDesc(DB::raw('COUNT(obc.id)'))
                ->orderByDesc('ob.id')
                ->select('ob.name', DB::raw('COUNT(obc.id) as total'))
                ->limit(8)
                ->get()
                ->map(fn ($row) => [
                    'batch' => (string) $row->name,
                    'count' => (int) $row->total,
                ])
                ->all();

            if ($districtOnboardingTarget !== null && $districtOnboardingTarget > 0) {
                $districtOnboardingProgressPct = (int) round(($districtOnboardingAchieved / $districtOnboardingTarget) * 100);
            }
        }

        $base = CfaSubmission::query()
            ->where('referral_user_id', $user->id)
            ->when($activeFyId > 0, fn ($q) => $q->where('fiscal_year_id', $activeFyId));

        $cfaTotal = (clone $base)->count();
        $cfaThisMonth = (clone $base)
            ->whereYear('created_at', now()->year)
            ->whereMonth('created_at', now()->month)
            ->count();
        $cfaLast30 = (clone $base)->where('created_at', '>=', now()->subDays(30))->count();

        $cfaThisFy = 0;
        if ($activeFy) {
            $cfaThisFy = (clone $base)->where('fiscal_year_id', $activeFy->id)->count();
        }

        $staffShareOfDistrictPct = null;
        if ($districtCfaTotal !== null && (int) $districtCfaTotal > 0) {
            $staffShareOfDistrictPct = (int) min(100, round(($cfaTotal / (int) $districtCfaTotal) * 100));
        }

        $recent7 = (clone $base)->where('created_at', '>=', now()->copy()->subDays(7))->count();
        $recent7Prev = (clone $base)
            ->where('created_at', '>=', now()->copy()->subDays(14))
            ->where('created_at', '<', now()->copy()->subDays(7))
            ->count();
        $velocityChangePct = null;
        if ($recent7Prev > 0) {
            $velocityChangePct = (int) round((($recent7 - $recent7Prev) / $recent7Prev) * 100);
        } elseif ($recent7 > 0) {
            $velocityChangePct = 100;
        } elseif ($recent7 === 0 && $recent7Prev === 0) {
            $velocityChangePct = 0;
        }

        $heatmap30 = $this->referralHeatmap30((int) $user->id, $activeFyId);
        $submissionStreakDays = 0;
        for ($i = count($heatmap30) - 1; $i >= 0; $i--) {
            if (($heatmap30[$i]['count'] ?? 0) > 0) {
                $submissionStreakDays++;
            } else {
                break;
            }
        }

        $overallTargetPct = null;
        if ($staffAnnualTarget !== null && (int) $staffAnnualTarget > 0) {
            $overallTargetPct = (int) min(100, round(($cfaThisFy / (int) $staffAnnualTarget) * 100));
        }

        $daysToTargetAtPace = null;
        if ($activeFy && $staffAnnualTarget !== null && (int) $staffAnnualTarget > 0) {
            $remaining = max(0, (int) $staffAnnualTarget - $cfaThisFy);
            if ($remaining === 0) {
                $daysToTargetAtPace = 0;
            } else {
                $start = $activeFy->starts_on
                    ? Carbon::parse($activeFy->starts_on)->startOfDay()
                    : now()->copy()->startOfYear();
                $elapsed = max(1, $start->diffInDays(now()->copy()->startOfDay()) + 1);
                $perDay = $cfaThisFy / $elapsed;
                if ($perDay > 0.0001) {
                    $daysToTargetAtPace = (int) ceil($remaining / $perDay);
                }
            }
        }

        $trendLabels = [];
        $trendValues = [];
        for ($i = 13; $i >= 0; $i--) {
            $day = Carbon::now()->subDays($i)->startOfDay();
            $trendLabels[] = $day->format('d M');
            $trendValues[] = (int) (clone $base)
                ->whereDate('created_at', $day->toDateString())
                ->count();
        }

        $recentSubmissions = (clone $base)
            ->orderByDesc('created_at')
            ->limit(12)
            ->get(['id', 'application_no', 'applicant_name', 'phone', 'created_at']);

        $payloadCharts = $this->referralPayloadChartAggregates((int) $user->id);
        $registrationRate = $payloadCharts['registrationRate'];
        $registrationNoRate = $payloadCharts['registrationNoRate'];
        $trainingRate = $payloadCharts['trainingRate'];

        $performanceScore = null;
        if ($staffAnnualTarget !== null && (int) $staffAnnualTarget > 0) {
            $tp = min(100, ($cfaThisFy / (int) $staffAnnualTarget) * 100);
            $rp = $registrationRate ?? 50.0;
            $tr = $trainingRate ?? 50.0;
            $performanceScore = (int) round(min(100, $tp * 0.45 + $rp * 0.275 + $tr * 0.275));
        }

        $monthlyTargetsByMonth = [];
        if ($activeFy && $cfaDeliverable) {
            $raw = DB::table('staff_monthly_targets')
                ->where('fiscal_year_id', $activeFy->id)
                ->where('user_id', $user->id)
                ->where('deliverable_id', $cfaDeliverable->id)
                ->pluck('target_count', 'month_number')
                ->all();
            for ($m = 1; $m <= 12; $m++) {
                $monthlyTargetsByMonth[$m] = (int) ($raw[$m] ?? 0);
            }
        }

        $prevCalendarMonth = now()->copy()->subMonth();
        $cfaPrevCalendarMonth = (clone $base)
            ->whereYear('created_at', $prevCalendarMonth->year)
            ->whereMonth('created_at', $prevCalendarMonth->month)
            ->count();
        $monthOverMonthTrendPct = null;
        if ($cfaPrevCalendarMonth > 0) {
            $monthOverMonthTrendPct = (int) round((($cfaThisMonth - $cfaPrevCalendarMonth) / $cfaPrevCalendarMonth) * 100);
        } elseif ($cfaThisMonth > 0) {
            $monthOverMonthTrendPct = 100;
        }

        $projectionLabels = [];
        for ($i = 1; $i <= 7; $i++) {
            $projectionLabels[] = now()->copy()->addDays($i)->format('d M');
        }
        $avgDailyRecent = $recent7 > 0 ? $recent7 / 7.0 : 0.0;
        $velAdj = ($velocityChangePct ?? 0) / 100.0;
        $projFactor = max(0.2, min(2.2, 1 + $velAdj * 0.4));
        $projectionValues = [];
        for ($i = 0; $i < 7; $i++) {
            $projectionValues[] = max(0, (int) round($avgDailyRecent * $projFactor));
        }
        $forecastLabels = array_merge($trendLabels, $projectionLabels);
        $forecastHistorical = array_merge($trendValues, array_fill(0, 7, null));
        $forecastProjected = array_merge(array_fill(0, 14, null), $projectionValues);

        $topBusinessCategory = null;
        $bm = $payloadCharts['businessMix'];
        if (($bm['labels'] ?? []) !== [] && ($bm['values'] ?? []) !== []) {
            $bVals = $bm['values'];
            $maxVal = max($bVals);
            $maxIdx = array_search($maxVal, $bVals, true);
            if ($maxIdx !== false) {
                $bLbl = (string) ($bm['labels'][$maxIdx] ?? '');
                $bSum = (int) array_sum($bVals);
                $topBusinessCategory = [
                    'label' => $bLbl,
                    'count' => (int) $maxVal,
                    'share_pct' => $bSum > 0 ? (int) round(100 * $maxVal / $bSum) : 0,
                    'hue' => abs(crc32($bLbl)) % 360,
                ];
            }
        }

        $heroCfaToday = (int) (clone $base)->whereDate('created_at', now()->toDateString())->count();
        $heroCfaYesterday = (int) (clone $base)->whereDate('created_at', now()->subDay()->toDateString())->count();
        $heroCfaTodayDelta = $heroCfaToday - $heroCfaYesterday;

        $heroMentorshipPending = 0;
        try {
            if (Schema::hasTable('mentorship_requests') && $user->district_id) {
                $heroMentorshipPending = (int) MentorshipRequest::query()
                    ->where('status', MentorshipRequest::STATUS_PENDING)
                    ->whereHas('cfaSubmission', function ($q) use ($user): void {
                        $q->where('district_id', (int) $user->district_id);
                    })
                    ->count();
            }
        } catch (\Throwable $e) {
            $heroMentorshipPending = 0;
        }

        $heroDistrictOnlineNow = 0;
        if (Schema::hasColumn('users', 'last_seen_at') && $user->district_id) {
            $heroDistrictOnlineNow = (int) User::query()
                ->where('district_id', (int) $user->district_id)
                ->where('last_seen_at', '>=', now()->subMinutes(3))
                ->count();
        }

        $heroSparkline30 = $this->staffDailyCfaSparkline((int) $user->id, 30, $activeFyId);

        $phase3FloorDate = Carbon::create(2026, 4, 1)->startOfDay();
        $districtId = (int) ($user->district_id ?? 0);
        $districtIds = $districtId > 0 ? [$districtId] : [];
        $hubId = $user->hub_id ? (int) $user->hub_id : null;
        $phase3Scope = CfaSubmission::query()
            ->when($districtIds !== [], fn ($q) => $q->whereIn('district_id', $districtIds))
            ->when($activeFyId > 0, fn ($q) => $q->where('fiscal_year_id', $activeFyId), fn ($q) => $q->where('created_at', '>=', $phase3FloorDate));
        $cfaDeliverableIds = $cfaDeliverable ? [(int) $cfaDeliverable->id] : [];
        $cfaByDistrict = [
            'labels' => $user->district?->name ? [(string) $user->district->name] : [],
            'values' => $districtCfaTotal !== null ? [(int) $districtCfaTotal] : [],
        ];
        $servicesDelivered = $this->districtServicesDeliveredCount($districtIds);

        try {
            $insights = $this->insightsService->build(
                phase3Scope: $phase3Scope,
                districtIds: $districtIds,
                hubId: $hubId,
                phase3FloorDate: $phase3FloorDate,
                activeFyId: $activeFyId,
                cfaDeliverableIds: $cfaDeliverableIds,
                activeFy: $activeFy,
                cfaByDistrict: $cfaByDistrict,
                onboardedCount: $districtOnboardingAchieved,
                servicesDelivered: $servicesDelivered,
            );
        } catch (\Throwable) {
            $insights = $this->insightsService->emptyInsights();
        }

        $estimatedSavings = $this->insightsService->estimatedSavings($activeFy, $phase3FloorDate, $districtIds);

        return [
            'staff' => $user,
            'activeFy' => $activeFy,
            'cfaDeliverable' => $cfaDeliverable,
            'referralUrl' => $user->referralApplyUrl(),
            'staffAnnualTarget' => $staffAnnualTarget,
            'districtCfaTarget' => $districtCfaTarget,
            'districtCfaTotal' => $districtCfaTotal,
            'districtCfaByReferrer' => $districtCfaByReferrer,
            'districtCfaTrend' => $districtCfaTrend,
            'districtBusinessStageMix' => $districtBusinessStageMix,
            'districtProgressPct' => $districtProgressPct,
            'districtOnboardingTarget' => $districtOnboardingTarget,
            'districtOnboardingAchieved' => $districtOnboardingAchieved,
            'districtOnboardingProgressPct' => $districtOnboardingProgressPct,
            'districtOnboardingByBatch' => $districtOnboardingByBatch,
            'staffShareOfDistrictPct' => $staffShareOfDistrictPct,
            'cfaTotal' => $cfaTotal,
            'cfaThisMonth' => $cfaThisMonth,
            'cfaLast30' => $cfaLast30,
            'cfaThisFy' => $cfaThisFy,
            'cfaTrend' => [
                'labels' => $trendLabels,
                'values' => $trendValues,
            ],
            'cfaTrendForecast' => [
                'labels' => $forecastLabels,
                'historical' => $forecastHistorical,
                'projected' => $forecastProjected,
                'projection_sum' => array_sum($projectionValues),
            ],
            'monthOverMonthTrendPct' => $monthOverMonthTrendPct,
            'topBusinessCategory' => $topBusinessCategory,
            'businessMix' => $payloadCharts['businessMix'],
            'applicantCategoryMix' => $payloadCharts['applicantCategoryMix'],
            'genderMix' => $payloadCharts['genderMix'],
            'businessStageMix' => $payloadCharts['businessStageMix'],
            'casteMix' => $payloadCharts['casteMix'],
            'blockMix' => $payloadCharts['blockMix'],
            'trainingMix' => $payloadCharts['trainingMix'],
            'educationMix' => $payloadCharts['educationMix'],
            'registeredMix' => $payloadCharts['registeredMix'],
            'recentSubmissions' => $recentSubmissions,
            'monthlyTargetsByMonth' => $monthlyTargetsByMonth,
            'recent7' => $recent7,
            'recent7Prev' => $recent7Prev,
            'velocityChangePct' => $velocityChangePct,
            'heatmap30' => $heatmap30,
            'submissionStreakDays' => $submissionStreakDays,
            'registrationRate' => $registrationRate,
            'registrationNoRate' => $registrationNoRate,
            'trainingRate' => $trainingRate,
            'overallTargetPct' => $overallTargetPct,
            'daysToTargetAtPace' => $daysToTargetAtPace,
            'performanceScore' => $performanceScore,
            'heroCfaToday' => $heroCfaToday,
            'heroCfaYesterday' => $heroCfaYesterday,
            'heroCfaTodayDelta' => $heroCfaTodayDelta,
            'heroMentorshipPending' => $heroMentorshipPending,
            'heroDistrictOnlineNow' => $heroDistrictOnlineNow,
            'heroSparkline30' => $heroSparkline30,
            'insights' => $insights,
            'estimatedSavings' => $estimatedSavings,
            'stateCfaTrend' => $districtCfaTrend,
            'cfaByDistrict' => $cfaByDistrict,
            'phase3FloorDateLabel' => $phase3FloorDate->format('d M Y'),
            'districtsCount' => 1,
        ];
    }

    /**
     * @param  list<int>  $districtIds
     */
    private function districtServicesDeliveredCount(array $districtIds): int
    {
        if ($districtIds === [] || ! Schema::hasTable('service_cases') || ! Schema::hasColumn('service_cases', 'status')) {
            return 0;
        }

        return (int) ServiceCase::query()
            ->where('status', ServiceCase::STATUS_APPROVED)
            ->whereHas('cfaSubmission', fn ($q) => $q->whereIn('district_id', $districtIds))
            ->count();
    }

    /**
     * @return array{labels: list<string>, values: list<int>}
     */
    private function staffDailyCfaSparkline(int $staffUserId, int $days, int $fiscalYearId = 0): array
    {
        $start = now()->subDays($days - 1)->startOfDay();
        $rows = DB::table('cfa_submissions')
            ->selectRaw('DATE(created_at) as d, COUNT(*) as total')
            ->where('referral_user_id', $staffUserId)
            ->where('created_at', '>=', $start)
            ->when($fiscalYearId > 0, fn ($q) => $q->where('fiscal_year_id', $fiscalYearId))
            ->groupBy('d')
            ->pluck('total', 'd');

        $labels = [];
        $values = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $day = now()->subDays($i);
            $labels[] = $day->format('d M');
            $values[] = (int) ($rows[$day->toDateString()] ?? 0);
        }

        return ['labels' => $labels, 'values' => $values];
    }

    /**
     * District CFA totals split by referral link owner (who attributed each application).
     *
     * @return array{rows: list<array{user_id: int|null, name: string, count: int, is_you: bool, share_pct: int, avatar_url: string|null}>, total: int}
     */
    private function districtCfaBreakdownByReferrer(int $districtId, int $viewerUserId, int $fiscalYearId = 0): array
    {
        /** @var Collection<int, object{referral_user_id: int|null, cnt: int|string}> $aggregates */
        $aggregates = DB::table('cfa_submissions')
            ->where('district_id', $districtId)
            ->when($fiscalYearId > 0, fn ($q) => $q->where('fiscal_year_id', $fiscalYearId))
            ->selectRaw('referral_user_id, COUNT(*) as cnt')
            ->groupBy('referral_user_id')
            ->orderByDesc('cnt')
            ->orderBy('referral_user_id')
            ->get();

        if ($aggregates->isEmpty()) {
            return ['rows' => [], 'total' => 0];
        }

        $total = (int) $aggregates->sum(fn ($r) => (int) $r->cnt);
        $userIds = $aggregates
            ->pluck('referral_user_id')
            ->filter(fn ($id) => $id !== null)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
        /** @var Collection<int, User> $refUsers */
        $refUsers = $userIds === []
            ? collect()
            : User::query()->whereIn('id', $userIds)->get()->keyBy('id');

        $rows = [];
        foreach ($aggregates as $row) {
            $rid = $row->referral_user_id;
            $cnt = (int) $row->cnt;
            $sharePct = $total > 0 ? (int) min(100, round(100 * $cnt / $total)) : 0;

            if ($rid === null) {
                $rows[] = [
                    'user_id' => null,
                    'name' => 'Not linked to a referral',
                    'count' => $cnt,
                    'is_you' => false,
                    'share_pct' => $sharePct,
                    'avatar_url' => null,
                ];
            } else {
                $rid = (int) $rid;
                /** @var User|null $refUser */
                $refUser = $refUsers->get($rid);
                $rows[] = [
                    'user_id' => $rid,
                    'name' => (string) ($refUser?->name ?? ('User #'.$rid)),
                    'count' => $cnt,
                    'is_you' => $rid === $viewerUserId,
                    'share_pct' => $sharePct,
                    'avatar_url' => $refUser?->avatarUrl(),
                ];
            }
        }

        usort($rows, function (array $a, array $b): int {
            if ($a['is_you'] !== $b['is_you']) {
                return $a['is_you'] ? -1 : 1;
            }

            return $b['count'] <=> $a['count'];
        });

        $maxDisplay = 10;
        if (count($rows) > $maxDisplay) {
            $head = array_slice($rows, 0, $maxDisplay - 1);
            $tail = array_slice($rows, $maxDisplay - 1);
            $mergedCount = (int) array_sum(array_column($tail, 'count'));
            $nPeople = count($tail);
            $head[] = [
                'user_id' => null,
                'name' => 'Other referrers ('.$nPeople.')',
                'count' => $mergedCount,
                'is_you' => false,
                'share_pct' => $total > 0 ? (int) min(100, round(100 * $mergedCount / $total)) : 0,
                'avatar_url' => null,
            ];
            $rows = $head;
        }

        return ['rows' => $rows, 'total' => $total];
    }

    /**
     * Last 30 calendar days (oldest → newest) with submission counts per day.
     *
     * @return list<array{date: string, count: int}>
     */
    private function referralHeatmap30(int $userId, int $fiscalYearId = 0): array
    {
        $start = now()->copy()->subDays(29)->startOfDay();
        /** @var array<string, int|string> $counts */
        $counts = CfaSubmission::query()
            ->where('referral_user_id', $userId)
            ->where('created_at', '>=', $start)
            ->when($fiscalYearId > 0, fn ($q) => $q->where('fiscal_year_id', $fiscalYearId))
            ->selectRaw('DATE(created_at) as heat_day, COUNT(*) as cnt')
            ->groupBy(DB::raw('DATE(created_at)'))
            ->pluck('cnt', 'heat_day')
            ->all();

        $out = [];
        for ($i = 29; $i >= 0; $i--) {
            $d = now()->copy()->subDays($i)->toDateString();
            $out[] = [
                'date' => $d,
                'count' => (int) ($counts[$d] ?? 0),
            ];
        }

        return $out;
    }

    /**
     * One pass over recent submissions: mixes for dashboard charts.
     *
     * @return array<string, array{labels: list<string>, values: list<int>}>
     */
    private function referralPayloadChartAggregates(int $userId, int $limit = 600): array
    {
        $business = [];
        $applicantCategory = [];
        $gender = [];
        $stage = [];
        $caste = [];
        $block = [];
        $training = [];
        $education = [];
        $registered = [];
        $regAnswered = 0;
        $regYes = 0;
        $trAnswered = 0;
        $trYes = 0;

        CfaSubmission::query()
            ->where('referral_user_id', $userId)
            ->whereNotNull('payload')
            ->orderByDesc('id')
            ->limit($limit)
            ->cursor()
            ->each(function (CfaSubmission $row) use (
                &$business,
                &$applicantCategory,
                &$gender,
                &$stage,
                &$caste,
                &$block,
                &$training,
                &$education,
                &$registered,
                &$regAnswered,
                &$regYes,
                &$trAnswered,
                &$trYes
            ): void {
                $p = $row->payload;
                if (! is_array($p)) {
                    return;
                }

                $bc = $p['business_category'] ?? null;
                if (! is_string($bc) || $bc === '') {
                    $bc = 'Not specified';
                }
                $business[$bc] = ($business[$bc] ?? 0) + 1;

                $cat = $p['category'] ?? null;
                if (! is_string($cat) || $cat === '') {
                    $cat = 'Not specified';
                }
                $applicantCategory[$cat] = ($applicantCategory[$cat] ?? 0) + 1;

                $g = $p['gender'] ?? null;
                if (! is_string($g) || $g === '') {
                    $g = 'Not specified';
                }
                $gender[$g] = ($gender[$g] ?? 0) + 1;

                $st = $p['form_stage'] ?? null;
                if (! is_string($st) || $st === '') {
                    $st = 'Not specified';
                }
                $stage[$st] = ($stage[$st] ?? 0) + 1;

                $c = $p['caste'] ?? null;
                if (! is_string($c) || $c === '') {
                    $c = 'Not specified';
                }
                $caste[$c] = ($caste[$c] ?? 0) + 1;

                $bl = $p['block'] ?? null;
                if (! is_string($bl) || $bl === '') {
                    $bl = 'Not specified';
                }
                $block[$bl] = ($block[$bl] ?? 0) + 1;

                $tr = $p['training_received'] ?? null;
                if (! is_string($tr) || $tr === '') {
                    $tr = 'Not specified';
                }
                $training[$tr] = ($training[$tr] ?? 0) + 1;

                $trRec = $p['training_received'] ?? null;
                $trNorm = is_string($trRec) ? strtolower(trim($trRec)) : '';
                if (in_array($trNorm, ['yes', 'no'], true)) {
                    $trAnswered++;
                    if ($trNorm === 'yes') {
                        $trYes++;
                    }
                }

                $ed = $p['education'] ?? null;
                if (! is_string($ed) || $ed === '') {
                    $ed = 'Not specified';
                }
                $education[$ed] = ($education[$ed] ?? 0) + 1;

                $reg = $p['is_registered'] ?? null;
                if (! is_string($reg) || $reg === '') {
                    $reg = 'Not specified';
                }
                $registered[$reg] = ($registered[$reg] ?? 0) + 1;

                $regRaw = $p['is_registered'] ?? null;
                $regNorm = is_string($regRaw) ? strtolower(trim($regRaw)) : '';
                if (in_array($regNorm, ['yes', 'no'], true)) {
                    $regAnswered++;
                    if ($regNorm === 'yes') {
                        $regYes++;
                    }
                }
            });

        $registrationRate = $regAnswered > 0 ? round(100 * $regYes / $regAnswered) : null;
        $registrationNoRate = $regAnswered > 0 ? (100 - (int) $registrationRate) : null;
        $trainingRate = $trAnswered > 0 ? round(100 * $trYes / $trAnswered) : null;

        $businessMixChart = $this->sortAndCapChart($business, 8);
        $businessMixChart['colors'] = $this->chartColorsForLabels($businessMixChart['labels']);

        return [
            'businessMix' => $businessMixChart,
            'applicantCategoryMix' => $this->sortCountMapToChart($applicantCategory),
            'genderMix' => $this->sortCountMapToChart($gender),
            'businessStageMix' => $this->sortCountMapToChart($stage),
            'casteMix' => $this->sortCountMapToChart($caste),
            'blockMix' => $this->sortAndCapChart($block, 10),
            'trainingMix' => $this->sortCountMapToChart($training),
            'educationMix' => $this->sortAndCapChart($education, 8),
            'registeredMix' => $this->sortCountMapToChart($registered),
            'registrationRate' => $registrationRate,
            'registrationNoRate' => $registrationNoRate,
            'trainingRate' => $trainingRate,
        ];
    }

    /**
     * Distinct colors per label so legend / bar charts match chart segments (aligned by index).
     *
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

    /**
     * @param  array<string, int>  $counts
     * @return array{labels: list<string>, values: list<int>}
     */
    private function sortCountMapToChart(array $counts): array
    {
        if ($counts === []) {
            return ['labels' => [], 'values' => []];
        }
        arsort($counts);

        return [
            'labels' => array_keys($counts),
            'values' => array_map(intval(...), array_values($counts)),
        ];
    }

    /**
     * @param  array<string, int>  $counts
     * @return array{labels: list<string>, values: list<int>}
     */
    private function sortAndCapChart(array $counts, int $maxLabels): array
    {
        if ($counts === []) {
            return ['labels' => [], 'values' => []];
        }
        arsort($counts);
        $labels = array_keys($counts);
        $values = array_map(intval(...), array_values($counts));

        if (count($labels) <= $maxLabels) {
            return ['labels' => $labels, 'values' => $values];
        }

        $topLabels = array_slice($labels, 0, $maxLabels - 1);
        $topValues = array_slice($values, 0, $maxLabels - 1);
        $otherSum = (int) array_sum(array_slice($values, $maxLabels - 1));
        if ($otherSum > 0) {
            $topLabels[] = 'Other';
            $topValues[] = $otherSum;
        }

        return ['labels' => $topLabels, 'values' => $topValues];
    }

    /**
     * District-wide CFA submissions per calendar day for the last 14 days.
     *
     * @return array{labels: list<string>, values: list<int>}
     */
    private function districtDailyTrend14(int $districtId, int $fiscalYearId = 0): array
    {
        $labels = [];
        $values = [];
        for ($i = 13; $i >= 0; $i--) {
            $day = Carbon::now()->subDays($i)->startOfDay();
            $labels[] = $day->format('d M');
            $values[] = (int) CfaSubmission::query()
                ->where('district_id', $districtId)
                ->whereDate('created_at', $day->toDateString())
                ->when($fiscalYearId > 0, fn ($q) => $q->where('fiscal_year_id', $fiscalYearId))
                ->count();
        }

        return ['labels' => $labels, 'values' => $values];
    }

    /**
     * Stage distribution from stored payloads for all CFA in this district (capped scan).
     *
     * @return array{labels: list<string>, values: list<int>}
     */
    private function districtStageMixAggregates(int $districtId, int $fiscalYearId = 0, int $limit = 2000): array
    {
        $stage = [];

        CfaSubmission::query()
            ->where('district_id', $districtId)
            ->when($fiscalYearId > 0, fn ($q) => $q->where('fiscal_year_id', $fiscalYearId))
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

        return $this->sortCountMapToChart($stage);
    }
}
