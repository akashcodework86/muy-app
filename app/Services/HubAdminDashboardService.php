<?php

namespace App\Services;

use App\Models\CfaSubmission;
use App\Models\Deliverable;
use App\Models\District;
use App\Models\DistrictDeliverableTarget;
use App\Models\FiscalYear;
use App\Models\Hub;
use App\Models\MentorshipRequest;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class HubAdminDashboardService
{
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

        $stageQuery = DB::table('cfa_submissions')
            ->selectRaw("LOWER(TRIM(JSON_UNQUOTE(JSON_EXTRACT(payload, '$.form_stage')))) as stage_key")
            ->selectRaw('COUNT(*) as total');
        if ($districtIds !== []) {
            $stageQuery->whereIn('district_id', $districtIds);
        }
        if ($activeFyId > 0) {
            $stageQuery->where('fiscal_year_id', $activeFyId);
        }
        $stageCounts = $stageQuery->groupBy('stage_key')->pluck('total', 'stage_key');

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
            ->whereIn('id', $staffCfaByStaff->pluck('id')->filter()->map(fn ($id) => (int) $id)->filter(fn (int $id) => $id > 0)->all())
            ->get()
            ->keyBy('id');

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

        return [
            'hub' => $hub,
            'districtsInHub' => count($districtIds),
            'activeFy' => $activeFy,
            'cfaDeliverable' => $cfaDeliverable,
            'hubCfaTargetSum' => $hubCfaTargetSum,
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
     * @return list<int>
     */
    private function onboardingDeliverableIds(): array
    {
        return Deliverable::query()
            ->where(function ($q): void {
                $q->whereRaw('LOWER(code) = ?', ['onboarding'])
                    ->orWhere('sort_order', 4)
                    ->orWhere('name', 'like', '%Onboard%')
                    ->orWhere('mis_entry_label', 'like', '%Onboard%');
            })
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->values()
            ->all();
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
