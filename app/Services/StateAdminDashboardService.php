<?php

namespace App\Services;

use App\Models\CfaSubmission;
use App\Models\Deliverable;
use App\Models\District;
use App\Models\FieldCoordinatorAttendanceReport;
use App\Models\FiscalYear;
use App\Models\MentorshipRequest;
use App\Models\ServiceCase;
use App\Models\User;
use App\Services\Cfa\CfaSubmissionListQuery;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class StateAdminDashboardService
{
    public function __construct(
        private readonly AdminDashboardInsightsService $insightsService,
    ) {}

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
        $cfaDeliverableIds = [];
        $stateCfaTarget = null;
        $districtsCfaSum = null;
        $districtPlanAlignment = $this->emptyDistrictPlanAlignment();
        $stateOnboardingTarget = null;
        $stateOnboardingAchieved = 0;
        $onbEarlyCount = 0;
        $onbSeedCount = 0;
        $onbGrowthCount = 0;
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

                if ($activeFy) {
                    $districtPlanAlignment = $this->districtPlanAlignmentMetrics($activeFy, $cfaDeliverableIds);
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
            $districtPlanAlignment = $this->emptyDistrictPlanAlignment();
        }

        if (Schema::hasTable('onboarding_batch_cfa') && Schema::hasTable('onboarding_batches')) {
            $stateOnboardingAchieved = (int) DB::table('onboarding_batch_cfa as obc')
                ->join('onboarding_batches as ob', 'ob.id', '=', 'obc.onboarding_batch_id')
                ->where('ob.status', 'locked')
                ->whereNotNull('ob.locked_at')
                ->where('ob.locked_at', '>=', $phase3FloorDate)
                ->count();

            $onboardingStageExpr = CfaSubmissionListQuery::payloadJsonExpr('$.form_stage', 'cs.payload');
            try {
                $onboardingStageCounts = DB::table('onboarding_batch_cfa as obc')
                    ->join('onboarding_batches as ob', 'ob.id', '=', 'obc.onboarding_batch_id')
                    ->join('cfa_submissions as cs', 'cs.id', '=', 'obc.cfa_submission_id')
                    ->where('ob.status', 'locked')
                    ->whereNotNull('ob.locked_at')
                    ->where('ob.locked_at', '>=', $phase3FloorDate)
                    ->selectRaw('LOWER(TRIM('.$onboardingStageExpr.')) as stage_key, COUNT(*) as total')
                    ->groupBy(DB::raw('LOWER(TRIM('.$onboardingStageExpr.'))'))
                    ->pluck('total', 'stage_key');
                $onbEarlyCount = (int) ($onboardingStageCounts['early'] ?? 0);
                $onbSeedCount = (int) ($onboardingStageCounts['seed'] ?? 0);
                $onbGrowthCount = (int) ($onboardingStageCounts['growth'] ?? 0);
            } catch (\Throwable) {
                $onbEarlyCount = 0;
                $onbSeedCount = 0;
                $onbGrowthCount = 0;
            }

            if (Schema::hasTable('districts')) {
                // Start from districts so every district is included (zeros for those
                // without locked hub batches in the Phase 3 window).
                $stateOnboardingByDistrict = DB::table('districts as d')
                    ->leftJoin('onboarding_batches as ob', function ($join) use ($phase3FloorDate): void {
                        $join->on('ob.district_id', '=', 'd.id')
                            ->where('ob.status', '=', 'locked')
                            ->whereNotNull('ob.locked_at')
                            ->where('ob.locked_at', '>=', $phase3FloorDate);
                    })
                    ->leftJoin('onboarding_batch_cfa as obc', 'obc.onboarding_batch_id', '=', 'ob.id')
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
        $stageExpr = CfaSubmissionListQuery::payloadJsonExpr('$.form_stage');
        try {
            $stageCounts = DB::table('cfa_submissions')
                ->selectRaw('LOWER(TRIM('.$stageExpr.')) as stage_key')
                ->selectRaw('COUNT(*) as total')
                ->when($activeFyId > 0, fn ($q) => $q->where('fiscal_year_id', $activeFyId), fn ($q) => $q->where('created_at', '>=', $phase3FloorDate))
                ->groupBy(DB::raw('LOWER(TRIM('.$stageExpr.'))'))
                ->pluck('total', 'stage_key');
        } catch (\Throwable) {
            $stageCounts = collect();
        }
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
        $heroSectorMix = $this->businessCategoryMix($phase3FloorDate, $activeFyId, null);
        $heroSectorMix['colors'] = $this->chartColorsForLabels($heroSectorMix['labels']);

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

        $districtAllocPct = $districtPlanAlignment['pct'];

        $estimatedSavings = $this->estimatedSavingsMetrics($activeFy, $phase3FloorDate);
        $servicesDeliveredCounts = $this->servicesDeliveredCounts($activeFy, $phase3FloorDate);
        try {
            $insights = $this->insightsService->build(
                phase3Scope: $phase3Scope,
                districtIds: [],
                hubId: null,
                phase3FloorDate: $phase3FloorDate,
                activeFyId: $activeFyId,
                cfaDeliverableIds: $cfaDeliverableIds,
                activeFy: $activeFy,
                cfaByDistrict: [
                    'labels' => $cfaByDistrict->pluck('name')->all(),
                    'values' => $cfaByDistrict->pluck('total')->map(fn ($v) => (int) $v)->all(),
                ],
                onboardedCount: $stateOnboardingAchieved,
                servicesDelivered: $servicesDeliveredCounts['till_date'] ?? 0,
            );
        } catch (\Throwable) {
            $insights = $this->insightsService->emptyInsights();
        }

        $groundActivityTicker = $this->groundActivityTickerMessages(
            cfaTotal: $cfaTotal,
            heroCfaToday: $heroCfaToday,
            servicesDeliveredTillDate: $servicesDeliveredCounts['till_date'],
            topServices: $estimatedSavings['top_services'] ?? [],
            stateOnboardingAchieved: $stateOnboardingAchieved,
            heroStaffOnlineNow: $heroStaffOnlineNow,
            todayTopDistrict: $todayTopDistrict ? [
                'name' => (string) $todayTopDistrict->name,
                'count' => (int) $todayTopDistrict->total,
            ] : null,
            districtsCount: (int) District::query()->count(),
            blocksCount: (int) ($insights['geo']['blocks'] ?? 0),
            staffActive: $staffActive,
            staffTotal: $staffTotal,
            cfaLast30: $cfaLast30,
        );

        $fieldHighlights = $this->approvedFieldActivityHighlights($activeFy, $phase3FloorDate);

        $stateFyPaceChart = $this->stateFyPaceChart(
            $activeFy,
            $phase3FloorDate,
            $activeFyId,
            $stateCfaTarget,
            $stateOnboardingTarget,
            $stateCfaTrend,
            $insights['onboardingTrend'] ?? ['labels' => [], 'values' => []],
        );

        return [
            'activeFy' => $activeFy,
            'cfaDeliverable' => $cfaDeliverable,
            'stateCfaTarget' => $stateCfaTarget !== null ? (int) $stateCfaTarget : null,
            'districtsCfaSum' => $districtsCfaSum,
            'districtAllocPct' => $districtAllocPct,
            'districtPlanAlignment' => $districtPlanAlignment,
            'stateOnboardingTarget' => $stateOnboardingTarget,
            'stateOnboardingAchieved' => $stateOnboardingAchieved,
            'onbEarlyCount' => $onbEarlyCount,
            'onbSeedCount' => $onbSeedCount,
            'onbGrowthCount' => $onbGrowthCount,
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
            'heroSectorMix' => $heroSectorMix,
            'heroCfaToday' => $heroCfaToday,
            'heroCfaYesterday' => $heroCfaYesterday,
            'heroCfaTodayDelta' => $heroCfaTodayDelta,
            'heroMentorshipPending' => $heroMentorshipPending,
            'servicesDeliveredTillDate' => $servicesDeliveredCounts['till_date'],
            'servicesDeliveredThisFy' => $servicesDeliveredCounts['this_fy'],
            'heroStaffOnlineNow' => $heroStaffOnlineNow,
            'heroSparkline30' => $heroSparkline30,
            'phase3FloorDateLabel' => $phase3FloorDateLabel,
            'estimatedSavings' => $estimatedSavings,
            'insights' => $insights,
            'groundActivityTicker' => $groundActivityTicker,
            'fieldHighlights' => $fieldHighlights,
            'stateFyPaceChart' => $stateFyPaceChart,
        ];
    }

    /**
     * Random approved, browser-displayable field activity photos for the dashboard carousel.
     *
     * @return list<array{module: string, title: string, district: string, date: string, image_url: string, detail_url: string}>
     */
    private function approvedFieldActivityHighlights(?FiscalYear $activeFy, Carbon $phase3FloorDate): array
    {
        $from = $activeFy?->starts_on
            ? Carbon::parse($activeFy->starts_on)->startOfDay()
            : $phase3FloorDate->copy()->startOfDay();
        $fyEnd = $activeFy?->ends_on
            ? Carbon::parse($activeFy->ends_on)->endOfDay()
            : now()->endOfDay();
        $to = $fyEnd->min(now()->endOfDay());

        $sources = [
            [
                'table' => 'technical_trainings',
                'date' => 'event_date',
                'title' => 'session_name',
                'module' => 'Technical Training',
                'collections' => ['attendance_media_json'],
                'image_route' => 'admin.technical-trainings.attachment',
                'image_param' => 'technicalTraining',
                'detail_route' => 'admin.technical-trainings.show',
                'detail_param' => 'technicalTraining',
            ],
            [
                'table' => 'potential_lakhpati_technical_trainings',
                'date' => 'session_date',
                'title' => 'session_title',
                'module' => 'Lakhpati Technical Training',
                'collections' => ['workshop_photos_json'],
                'image_route' => 'admin.lakhpati-technical-trainings.attachment',
                'image_param' => 'lakhpatiTechnicalTraining',
                'detail_route' => 'admin.lakhpati-technical-trainings.show',
                'detail_param' => 'lakhpatiTechnicalTraining',
                'image_query' => ['collection' => 'photos'],
            ],
            [
                'table' => 'line_department_meetings',
                'date' => 'meeting_date',
                'title' => 'department_name',
                'module' => 'Line Department Meeting',
                'collections' => ['proof_media_json', 'photos_json'],
                'image_route' => 'admin.line-department-meetings.attachment',
                'image_param' => 'ldmMeeting',
                'detail_route' => 'admin.line-department-meetings.show',
                'detail_param' => 'ldmMeeting',
            ],
            [
                'table' => 'community_organization_outreach_visits',
                'date' => 'visit_date',
                'title' => 'organization_name',
                'module' => 'Community Outreach',
                'collections' => ['photos_json'],
                'image_route' => 'admin.community-org-outreach.photo',
                'image_param' => 'communityOrgOutreach',
                'detail_route' => 'admin.community-org-outreach.show',
                'detail_param' => 'communityOrgOutreach',
            ],
        ];

        $highlights = collect();

        foreach ($sources as $source) {
            $table = $source['table'];
            if (! Schema::hasTable($table)
                || ! Schema::hasColumn($table, 'status')
                || ! Schema::hasColumn($table, $source['date'])) {
                continue;
            }

            $columns = array_values(array_unique(array_filter([
                'id', 'district_name', $source['date'], $source['title'], ...$source['collections'],
            ], fn (string $column): bool => Schema::hasColumn($table, $column))));

            try {
                $rows = DB::table($table)
                    ->where('status', ServiceCase::STATUS_APPROVED)
                    ->whereBetween($source['date'], [$from->toDateString(), $to->toDateString()])
                    ->inRandomOrder()
                    ->limit(75)
                    ->get($columns);
            } catch (\Throwable) {
                continue;
            }

            $sourcePhotoCount = 0;
            foreach ($rows as $row) {
                $routeIndex = 0;
                foreach ($source['collections'] as $collection) {
                    $items = $this->decodeDashboardMediaList($row->{$collection} ?? null);
                    foreach ($items as $item) {
                        $currentRouteIndex = $routeIndex++;
                        if (! $this->isDashboardImage($item)) {
                            continue;
                        }

                        $path = trim((string) ($item['path'] ?? ''));
                        if ($path === '' || ! Storage::exists($path)) {
                            continue;
                        }

                        $imageQuery = array_merge(
                            (array) ($source['image_query'] ?? []),
                            ['index' => $currentRouteIndex, 'inline' => 1],
                        );
                        $activityDate = Carbon::parse($row->{$source['date']});

                        $highlights->push([
                            'module' => $source['module'],
                            'title' => trim((string) ($row->{$source['title']} ?? '')) ?: $source['module'],
                            'district' => trim((string) ($row->district_name ?? '')) ?: 'State-wide',
                            'date' => $activityDate->format('d M Y'),
                            'image_url' => route($source['image_route'], array_merge([
                                $source['image_param'] => (int) $row->id,
                            ], $imageQuery)),
                            'detail_url' => route($source['detail_route'], [
                                $source['detail_param'] => (int) $row->id,
                            ]),
                        ]);
                        $sourcePhotoCount++;
                        if ($sourcePhotoCount >= 24) {
                            break 3;
                        }
                    }
                }
            }
        }

        return $highlights
            ->shuffle()
            ->values()
            ->all();
    }

    /** @return list<array<string, mixed>> */
    private function decodeDashboardMediaList(mixed $value): array
    {
        if (is_string($value)) {
            $value = json_decode($value, true);
        }

        return collect(is_array($value) ? $value : [])
            ->filter(fn ($item): bool => is_array($item))
            ->values()
            ->all();
    }

    /** @param array<string, mixed> $item */
    private function isDashboardImage(array $item): bool
    {
        $mime = strtolower(trim((string) ($item['mime'] ?? '')));
        if (str_starts_with($mime, 'image/')) {
            return ! in_array($mime, ['image/heic', 'image/heif'], true);
        }

        $name = strtolower((string) ($item['original_name'] ?? $item['path'] ?? ''));

        return (bool) preg_match('/\.(jpe?g|png|webp|gif)$/i', $name);
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<CfaSubmission>  $phase3Scope
     * @param  list<int>  $cfaDeliverableIds
     * @param  array{labels: list<string>, values: list<int>}  $cfaByDistrict
     * @return array<string, mixed>
     */
    private function buildInsights(
        $phase3Scope,
        Carbon $phase3FloorDate,
        int $activeFyId,
        array $cfaDeliverableIds,
        ?FiscalYear $activeFy,
        array $cfaByDistrict,
        int $onboardedCount,
        int $servicesDelivered,
    ): array {
        $categoryMix = $this->payloadLabelCounts('$.category', $phase3FloorDate, $activeFyId, [
            'individual' => 'Individual',
            'shg' => 'SHG',
            'cbo' => 'CBO',
        ]);
        $genderMix = $this->payloadLabelCounts('$.gender', $phase3FloorDate, $activeFyId);
        $registrationMix = $this->payloadLabelCounts('$.is_registered', $phase3FloorDate, $activeFyId, [
            'yes' => 'Registered',
            'no' => 'Not registered',
        ]);
        $lakhpatiMix = $this->payloadLabelCounts('$.lakhpati', $phase3FloorDate, $activeFyId, [
            'yes' => 'Lakhpati Yes',
            'no' => 'Lakhpati No',
        ]);
        $sourceMix = $this->cfaSourceMix($phase3FloorDate, $activeFyId);
        $topBlocks = $this->topBlocksMix($phase3FloorDate, $activeFyId, 12);
        $districtTargetComparison = $this->districtCfaTargetComparison($cfaDeliverableIds, $activeFy, $cfaByDistrict);
        $onboardingTrend = $this->onboardingDailyTrend14($phase3FloorDate);
        $staffTopChart = $this->staffCfaTopChart($phase3FloorDate, $activeFyId, 10);

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
                $stageValues[$idx] = (int) DB::table('cfa_submissions')
                    ->when($activeFyId > 0, fn ($q) => $q->where('fiscal_year_id', $activeFyId), fn ($q) => $q->where('created_at', '>=', $phase3FloorDate))
                    ->whereRaw('LOWER(TRIM('.$stageExpr.')) = ?', [$stageName])
                    ->count();
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
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyInsights(): array
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
     * @param  array<string, string>  $labelMap
     * @return array{labels: list<string>, values: list<int>}
     */
    private function payloadLabelCounts(
        string $jsonPath,
        Carbon $phase3FloorDate,
        int $fiscalYearId,
        array $labelMap = [],
    ): array {
        $expr = CfaSubmissionListQuery::payloadJsonExpr($jsonPath);
        try {
            $rows = DB::table('cfa_submissions')
                ->when($fiscalYearId > 0, fn ($q) => $q->where('fiscal_year_id', $fiscalYearId), fn ($q) => $q->where('created_at', '>=', $phase3FloorDate))
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
     * @return array{labels: list<string>, values: list<int>}
     */
    private function cfaSourceMix(Carbon $phase3FloorDate, int $fiscalYearId): array
    {
        try {
            $rows = DB::table('cfa_submissions')
                ->when($fiscalYearId > 0, fn ($q) => $q->where('fiscal_year_id', $fiscalYearId), fn ($q) => $q->where('created_at', '>=', $phase3FloorDate))
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
     * @return array{labels: list<string>, values: list<int>}
     */
    private function topBlocksMix(Carbon $phase3FloorDate, int $fiscalYearId, int $limit = 12): array
    {
        $expr = CfaSubmissionListQuery::payloadJsonExpr('$.block');
        try {
            $rows = DB::table('cfa_submissions')
                ->when($fiscalYearId > 0, fn ($q) => $q->where('fiscal_year_id', $fiscalYearId), fn ($q) => $q->where('created_at', '>=', $phase3FloorDate))
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
     * @param  array{labels: list<string>, values: list<int>}  $cfaByDistrict
     * @return array{labels: list<string>, achieved: list<int>, targets: list<int>}
     */
    private function districtCfaTargetComparison(array $cfaDeliverableIds, ?FiscalYear $activeFy, array $cfaByDistrict): array
    {
        $labels = $cfaByDistrict['labels'] ?? [];
        $achieved = $cfaByDistrict['values'] ?? [];
        $targets = array_fill(0, count($labels), 0);

        if ($activeFy === null || $cfaDeliverableIds === []) {
            return compact('labels', 'achieved', 'targets');
        }

        try {
            $targetRows = DB::table('district_deliverable_targets as ddt')
                ->join('districts as d', 'd.id', '=', 'ddt.district_id')
                ->where('ddt.fiscal_year_id', (int) $activeFy->id)
                ->whereIn('ddt.deliverable_id', $cfaDeliverableIds)
                ->selectRaw('d.name as district_name, SUM(ddt.target_total) as target_total')
                ->groupBy('d.id', 'd.name')
                ->pluck('target_total', 'district_name');
        } catch (\Throwable) {
            return compact('labels', 'achieved', 'targets');
        }

        foreach ($labels as $i => $name) {
            $targets[$i] = (int) ($targetRows[$name] ?? 0);
        }

        return compact('labels', 'achieved', 'targets');
    }

    /**
     * @return array{labels: list<string>, values: list<int>}
     */
    private function onboardingDailyTrend14(Carbon $phase3FloorDate): array
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
                    ->where('ob.status', 'locked')
                    ->whereNotNull('ob.locked_at')
                    ->whereDate('ob.locked_at', $day->toDateString())
                    ->count();
            }
        }

        return ['labels' => $labels, 'values' => $values];
    }

    /**
     * @return array{labels: list<string>, values: list<int>}
     */
    private function staffCfaTopChart(Carbon $phase3FloorDate, int $fiscalYearId, int $limit = 10): array
    {
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
     * @return array{
     *   pct: int|null,
     *   aligned_count: int,
     *   tracked_count: int,
     *   all_aligned: bool,
     *   state_total: int,
     *   district_total: int,
     *   cfa: array{state: int, district: int, aligned: bool, tracked: bool},
     *   services: array{state: int, district: int, aligned_count: int, tracked_count: int, all_aligned: bool},
     *   misaligned: list<array{name: string, state: int, district: int, gap: int, kind: string}>
     * }
     */
    private function emptyDistrictPlanAlignment(): array
    {
        return [
            'pct' => null,
            'aligned_count' => 0,
            'tracked_count' => 0,
            'all_aligned' => false,
            'state_total' => 0,
            'district_total' => 0,
            'cfa' => ['state' => 0, 'district' => 0, 'aligned' => false, 'tracked' => false],
            'services' => ['state' => 0, 'district' => 0, 'aligned_count' => 0, 'tracked_count' => 0, 'all_aligned' => false],
            'misaligned' => [],
        ];
    }

    /**
     * Per-deliverable check: district target sum must equal state target (CFA + active service deliverables).
     *
     * @param  list<int>  $cfaDeliverableIds
     * @return array<string, mixed>
     */
    private function districtPlanAlignmentMetrics(FiscalYear $activeFy, array $cfaDeliverableIds): array
    {
        $result = $this->emptyDistrictPlanAlignment();
        $fyId = (int) $activeFy->id;
        $onboardingId = Deliverable::onboardingTargetDeliverableId();

        $serviceDeliverableIds = Deliverable::query()
            ->where('is_active', true)
            ->where('code', 'like', 'svc_%')
            ->when($onboardingId !== null, fn ($q) => $q->where('id', '!=', $onboardingId))
            ->when($cfaDeliverableIds !== [], fn ($q) => $q->whereNotIn('id', $cfaDeliverableIds))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->values()
            ->all();

        $deliverableIds = collect($cfaDeliverableIds)
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
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'mis_entry_label']);

        $stateByDeliverable = DB::table('state_deliverable_targets')
            ->where('fiscal_year_id', $fyId)
            ->whereIn('deliverable_id', $deliverableIds)
            ->pluck('target_total', 'deliverable_id')
            ->map(fn ($v) => (int) $v);

        $districtByDeliverable = DB::table('district_deliverable_targets')
            ->where('fiscal_year_id', $fyId)
            ->whereIn('deliverable_id', $deliverableIds)
            ->selectRaw('deliverable_id, SUM(target_total) as district_sum')
            ->groupBy('deliverable_id')
            ->pluck('district_sum', 'deliverable_id')
            ->map(fn ($v) => (int) $v);

        $cfaIdsSet = array_fill_keys($cfaDeliverableIds, true);
        $alignedCount = 0;
        $trackedCount = 0;
        $misaligned = [];
        $cfaState = 0;
        $cfaDistrict = 0;
        $cfaTracked = false;
        $cfaAligned = true;
        $svcState = 0;
        $svcDistrict = 0;
        $svcAlignedCount = 0;
        $svcTrackedCount = 0;

        foreach ($deliverables as $deliverable) {
            $id = (int) $deliverable->id;
            $state = (int) ($stateByDeliverable[$id] ?? 0);
            $district = (int) ($districtByDeliverable[$id] ?? 0);
            $isCfa = isset($cfaIdsSet[$id]);
            $label = (string) ($deliverable->mis_entry_label ?: $deliverable->name);

            if ($isCfa) {
                $cfaState += $state;
                $cfaDistrict += $district;
            } else {
                $svcState += $state;
                $svcDistrict += $district;
            }

            if ($state <= 0) {
                continue;
            }

            $trackedCount++;
            if ($isCfa) {
                $cfaTracked = true;
            } else {
                $svcTrackedCount++;
            }

            $isAligned = $district === $state;
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
                    'name' => $label,
                    'state' => $state,
                    'district' => $district,
                    'gap' => abs($state - $district),
                    'kind' => $isCfa ? 'cfa' : 'service',
                ];
            }
        }

        $result['aligned_count'] = $alignedCount;
        $result['tracked_count'] = $trackedCount;
        $result['all_aligned'] = $trackedCount > 0 && $alignedCount === $trackedCount;
        $result['pct'] = $trackedCount > 0 ? (int) round(($alignedCount / $trackedCount) * 100) : null;
        $result['state_total'] = $cfaState + $svcState;
        $result['district_total'] = $cfaDistrict + $svcDistrict;
        $result['cfa'] = [
            'state' => $cfaState,
            'district' => $cfaDistrict,
            'aligned' => $cfaTracked && $cfaAligned,
            'tracked' => $cfaTracked,
        ];
        $result['services'] = [
            'state' => $svcState,
            'district' => $svcDistrict,
            'aligned_count' => $svcAlignedCount,
            'tracked_count' => $svcTrackedCount,
            'all_aligned' => $svcTrackedCount > 0 && $svcAlignedCount === $svcTrackedCount,
        ];
        $result['misaligned'] = $misaligned;

        return $result;
    }

    /**
     * Approved service cases = delivered in Phase 3 service workflow.
     *
     * @return array{till_date: int, this_fy: int}
     */
    private function servicesDeliveredCounts(?FiscalYear $activeFy, Carbon $phase3FloorDate): array
    {
        if (! Schema::hasTable('service_cases') || ! Schema::hasColumn('service_cases', 'status')) {
            return ['till_date' => 0, 'this_fy' => 0];
        }

        $tillDate = (int) DB::table('service_cases')
            ->where('status', ServiceCase::STATUS_APPROVED)
            ->count();

        $fyStart = $activeFy?->starts_on
            ? Carbon::parse($activeFy->starts_on)->startOfDay()
            : $phase3FloorDate;
        $fyEnd = $activeFy?->ends_on
            ? Carbon::parse($activeFy->ends_on)->endOfDay()
            : now()->endOfDay();

        $approvedAtExpr = Schema::hasColumn('service_cases', 'approved_at')
            ? 'COALESCE(approved_at, completed_at, created_at)'
            : (Schema::hasColumn('service_cases', 'completed_at') ? 'COALESCE(completed_at, created_at)' : 'created_at');

        $thisFy = (int) DB::table('service_cases')
            ->where('status', ServiceCase::STATUS_APPROVED)
            ->whereBetween(DB::raw($approvedAtExpr), [$fyStart, $fyEnd])
            ->count();

        return ['till_date' => $tillDate, 'this_fy' => $thisFy];
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
     * FY month-wise cumulative achievement vs prorated state targets (for State pulse tabs).
     *
     * @param  array{labels: list<string>, values: list<int>}  $dailyCfaTrend
     * @param  array{labels: list<string>, values: list<int>}  $dailyOnboardingTrend
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
    private function stateFyPaceChart(
        ?FiscalYear $activeFy,
        Carbon $phase3FloorDate,
        int $fiscalYearId,
        ?int $cfaTarget,
        ?int $onboardingTarget,
        array $dailyCfaTrend,
        array $dailyOnboardingTrend,
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

        $cfaMonthly = $this->monthlyCountMap('cfa_submissions', 'created_at', $fyStart, $reportThrough, $fiscalYearId);
        $onboardingMonthly = $this->monthlyOnboardingCountMap($fyStart, $reportThrough);

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
     * @return array<string, int>
     */
    private function monthlyCountMap(
        string $table,
        string $dateColumn,
        Carbon $from,
        Carbon $to,
        int $fiscalYearId = 0,
    ): array {
        if (! Schema::hasTable($table)) {
            return [];
        }

        $monthExpr = $this->monthKeySql($dateColumn);
        try {
            $query = DB::table($table)
                ->whereBetween($dateColumn, [$from, $to]);

            if ($table === 'cfa_submissions' && $fiscalYearId > 0) {
                $query->where('fiscal_year_id', $fiscalYearId);
            }

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
     * @return array<string, int>
     */
    private function monthlyOnboardingCountMap(Carbon $from, Carbon $to): array
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
    private function businessCategoryMix(Carbon $phase3FloorDate, int $fiscalYearId = 0, ?int $labelLimit = 8): array
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

        if ($labelLimit !== null && count($labels) > $labelLimit) {
            $topLabels = array_slice($labels, 0, $labelLimit - 1);
            $topValues = array_slice($values, 0, $labelLimit - 1);
            $otherSum = (int) array_sum(array_slice($values, $labelLimit - 1));
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

    /**
     * Rotating headline lines for the state dashboard welcome strip.
     *
     * @param  list<array{name: string, avg_price: float, approved_count: int, savings: float}>  $topServices
     * @param  array{name: string, count: int}|null  $todayTopDistrict
     * @return list<string>
     */
    private function groundActivityTickerMessages(
        int $cfaTotal,
        int $heroCfaToday,
        int $servicesDeliveredTillDate,
        array $topServices,
        int $stateOnboardingAchieved,
        int $heroStaffOnlineNow,
        ?array $todayTopDistrict,
        int $districtsCount,
        int $blocksCount,
        int $staffActive,
        int $staffTotal,
        int $cfaLast30,
    ): array {
        $messages = [];
        $fmt = fn (int $n): string => number_format($n);

        if ($cfaTotal > 0) {
            $messages[] = 'We have reached '.$fmt($cfaTotal).' CFA applications till date across Uttarakhand — Mukhyamantri Udyamshala Yojana is actively supporting entrepreneurs at block and gram panchayat level.';
        }

        if ($heroCfaToday > 0) {
            $messages[] = $fmt($heroCfaToday).' new CFA applications received today statewide — field teams and district staff are driving registrations on the ground every day.';
        }

        if ($cfaLast30 > 0) {
            $messages[] = $fmt($cfaLast30).' CFA applications recorded in the last 30 days — consistent outreach and awareness activities are running across all districts under Phase 3.';
        }

        if ($servicesDeliveredTillDate > 0) {
            $messages[] = $fmt($servicesDeliveredTillDate).' approved business support services delivered to incubatees till date — registration, marketing, mentorship and more reaching beneficiaries at village level.';
        }

        foreach (array_slice($topServices, 0, 4) as $service) {
            $name = trim((string) ($service['name'] ?? ''));
            $count = (int) ($service['approved_count'] ?? 0);
            if ($name === '' || $count <= 0) {
                continue;
            }
            $messages[] = $name.' support delivered to '.$fmt($count).' entrepreneurs through MUY — scheme services are reaching incubatees where they live and work.';
        }

        if ($stateOnboardingAchieved > 0) {
            $messages[] = $fmt($stateOnboardingAchieved).' incubatees onboarded through hub batches till date — building a strong pipeline from CFA to handholding, block by block across the state.';
        }

        $fieldToday = $this->todayFieldActivitySnapshot(now()->toDateString());
        if ($fieldToday['blocks'] !== []) {
            $messages[] = 'Today field teams are active in '.$this->formatTickerBlockList($fieldToday['blocks'], 6).' — workshops, visits and outreach continue at ground level under Phase 3.';
        }

        if ($fieldToday['visit_count'] > 0) {
            $messages[] = $fmt($fieldToday['visit_count']).' field visit and workshop reports submitted today from blocks across Uttarakhand — daily evidence that the scheme is working on the ground.';
        }

        if ($fieldToday['participants'] > 0) {
            $messages[] = $fmt($fieldToday['participants']).' participants reached in today\'s field programmes — entrepreneurs and community members engaged through block-level activities today.';
        }

        if ($heroStaffOnlineNow > 0) {
            $messages[] = $fmt($heroStaffOnlineNow).' district staff active on the portal right now, with '.$fmt($staffActive).' of '.$fmt($staffTotal).' field staff deployed statewide — teams are on the move.';
        } elseif ($staffTotal > 0) {
            $messages[] = $fmt($staffActive).' of '.$fmt($staffTotal).' district staff active across Uttarakhand — a statewide field network delivering MUY at block and village level.';
        }

        if ($todayTopDistrict !== null && ($todayTopDistrict['count'] ?? 0) > 0) {
            $messages[] = $todayTopDistrict['name'].' district leads today\'s CFA registrations with '.$fmt((int) $todayTopDistrict['count']).' applications — strong momentum on the ground in this district today.';
        }

        if ($districtsCount > 0 && $blocksCount > 0) {
            $messages[] = 'MUY Phase 3 covers '.$fmt($districtsCount).' districts and '.$fmt($blocksCount).' blocks in Uttarakhand — the scheme is delivering entrepreneurship support at scale, village by village.';
        }

        if ($messages === []) {
            $messages[] = 'Mukhyamantri Udyamshala Yojana Phase 3 — building entrepreneurs across Uttarakhand through CFA, services, and field outreach at block and gram panchayat level.';
        }

        return array_values(array_unique($messages));
    }

    /**
     * @return array{blocks: list<string>, visit_count: int, participants: int}
     */
    private function todayFieldActivitySnapshot(string $today): array
    {
        $blocks = collect();
        $visitCount = 0;
        $participants = 0;

        if (Schema::hasTable('field_coordinator_attendance_reports')) {
            $query = DB::table('field_coordinator_attendance_reports')
                ->whereDate('visit_date', $today);

            if (FieldCoordinatorAttendanceReport::supportsDraftWorkflow()) {
                $query->where(function ($q): void {
                    $q->where('status', FieldCoordinatorAttendanceReport::STATUS_SUBMITTED)
                        ->orWhereNull('status');
                });
            }

            $rows = $query->get([
                'block',
                'participants_total',
                'participants_male_count',
                'participants_female_count',
            ]);

            foreach ($rows as $row) {
                $visitCount++;
                $blockName = trim((string) ($row->block ?? ''));
                if ($blockName !== '') {
                    $blocks->push($blockName);
                }
                $participants += $this->resolveParticipantTotal($row);
            }
        }

        if (Schema::hasTable('block_workshops')) {
            $query = DB::table('block_workshops as bw')
                ->leftJoin('district_blocks as db', 'db.id', '=', 'bw.district_block_id')
                ->whereDate('bw.visit_date', $today);

            if (Schema::hasColumn('block_workshops', 'status')) {
                $query->where(function ($q): void {
                    $q->where('bw.status', 'submitted')
                        ->orWhereNull('bw.status');
                });
            }

            $rows = $query->get([
                'bw.block',
                'db.name as district_block_name',
                'bw.participants_total',
                'bw.participants_male_count',
                'bw.participants_female_count',
            ]);

            foreach ($rows as $row) {
                $visitCount++;
                $blockName = trim((string) ($row->district_block_name ?? $row->block ?? ''));
                if ($blockName !== '') {
                    $blocks->push($blockName);
                }
                $participants += $this->resolveParticipantTotal($row);
            }
        }

        return [
            'blocks' => $blocks->filter()->unique()->values()->all(),
            'visit_count' => $visitCount,
            'participants' => $participants,
        ];
    }

    private function resolveParticipantTotal(object $row): int
    {
        $total = (int) ($row->participants_total ?? 0);
        if ($total > 0) {
            return $total;
        }

        return max(0, (int) ($row->participants_male_count ?? 0) + (int) ($row->participants_female_count ?? 0));
    }

    /**
     * @param  list<string>  $blocks
     */
    private function formatTickerBlockList(array $blocks, int $maxShown = 4): string
    {
        $blocks = array_values(array_filter(array_map('trim', $blocks)));
        if ($blocks === []) {
            return 'multiple blocks';
        }

        if (count($blocks) <= $maxShown) {
            return implode(', ', $blocks);
        }

        $shown = array_slice($blocks, 0, $maxShown);

        return implode(', ', $shown).' and '.(count($blocks) - $maxShown).' more blocks';
    }
}
