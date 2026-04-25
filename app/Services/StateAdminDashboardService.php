<?php

namespace App\Services;

use App\Models\CfaSubmission;
use App\Models\Deliverable;
use App\Models\District;
use App\Models\MentorshipRequest;
use App\Models\User;
use Carbon\Carbon;
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

        $phase3Scope = CfaSubmission::query()
            ->where('created_at', '>=', $phase3FloorDate);

        $activeFy = null;
        $cfaDeliverable = null;
        $stateCfaTarget = null;
        $districtsCfaSum = null;
        $stateCfaThisFy = (int) (clone $phase3Scope)->count();
        $stateProgressPct = null;
        $stateCfaTrend = $this->stateDailyTrend14($phase3FloorDate);
        $stateBusinessStageMix = $this->stateStageMixAggregates($phase3FloorDate);

        $activeFy = FiscalYear::query()
            ->where('is_active', true)
            ->orderByDesc('starts_on')
            ->first();
        $cfaDeliverable = Deliverable::query()
            ->where('code', 'cfa')
            ->first();
        if ($activeFy && $cfaDeliverable) {
            $stateTargetRow = DB::table('state_deliverable_targets')
                ->where('fiscal_year_id', (int) $activeFy->id)
                ->where('deliverable_id', (int) $cfaDeliverable->id)
                ->first();
            $stateCfaTarget = $stateTargetRow ? (int) $stateTargetRow->target_total : null;

            $districtsCfaSum = (int) DB::table('district_deliverable_targets')
                ->where('fiscal_year_id', (int) $activeFy->id)
                ->where('deliverable_id', (int) $cfaDeliverable->id)
                ->sum('target_total');

            if ($stateCfaTarget !== null && $stateCfaTarget > 0) {
                $stateProgressPct = (int) round(($stateCfaThisFy / $stateCfaTarget) * 100);
            }
        }

        $staffTotal = User::query()->where('role', 'district_staff')->count();
        $staffActive = User::query()->where('role', 'district_staff')->where('is_active', true)->count();

        $cfaTotal = (clone $phase3Scope)->count();
        $phase1CfaTotal = 0;
        $phase2CfaTotal = 0;
        $phase3CfaTotal = (int) $cfaTotal;
        try {
            if (Schema::connection('legacy_phase1')->hasTable('tblapplication')) {
                $phase1CfaTotal = (int) DB::connection('legacy_phase1')->table('tblapplication')->count();
            }
        } catch (\Throwable $e) {
            $phase1CfaTotal = 0;
        }
        try {
            if (Schema::connection('legacy')->hasTable('rbi_applications')) {
                $phase2CfaTotal = (int) DB::connection('legacy')->table('rbi_applications')->count();
            }
        } catch (\Throwable $e) {
            $phase2CfaTotal = 0;
        }
        $allPhasesCfaTotal = $phase1CfaTotal + $phase2CfaTotal + $phase3CfaTotal;
        $cfaThisMonth = (clone $phase3Scope)->whereYear('created_at', now()->year)->whereMonth('created_at', now()->month)->count();
        $cfaLast30 = (clone $phase3Scope)->where('created_at', '>=', now()->subDays(30))->count();

        $seedCount = 0;
        $earlyCount = 0;
        $growthCount = 0;
        $stageCounts = DB::table('cfa_submissions')
            ->selectRaw("LOWER(TRIM(JSON_UNQUOTE(JSON_EXTRACT(payload, '$.form_stage')))) as stage_key")
            ->selectRaw('COUNT(*) as total')
            ->where('created_at', '>=', $phase3FloorDate)
            ->groupBy('stage_key')
            ->pluck('total', 'stage_key');
        $seedCount = (int) ($stageCounts['seed'] ?? 0);
        $earlyCount = (int) ($stageCounts['early'] ?? 0);
        $growthCount = (int) ($stageCounts['growth'] ?? 0);

        $cfaByDistrict = DB::table('districts')
            ->leftJoin('cfa_submissions', 'cfa_submissions.district_id', '=', 'districts.id')
            ->where(function ($q) use ($phase3FloorDate): void {
                $q->whereNull('cfa_submissions.id')
                    ->orWhere('cfa_submissions.created_at', '>=', $phase3FloorDate);
            })
            ->select('districts.name', DB::raw('COUNT(cfa_submissions.id) as total'))
            ->groupBy('districts.id', 'districts.name')
            ->orderByDesc('total')
            ->orderBy('districts.name')
            ->get();

        $staffByDistrict = DB::table('users')
            ->join('districts', 'users.district_id', '=', 'districts.id')
            ->where('users.role', 'district_staff')
            ->select('districts.name', DB::raw('COUNT(*) as total'))
            ->groupBy('districts.id', 'districts.name')
            ->orderByDesc('total')
            ->get();

        $staffCfaByStaff = DB::table('users')
            ->leftJoin('districts', 'users.district_id', '=', 'districts.id')
            ->leftJoin('cfa_submissions as cs', function ($join): void {
                $join->on('cs.referral_user_id', '=', 'users.id');
            })->where(function ($q) use ($phase3FloorDate): void {
                $q->whereNull('cs.id')
                    ->orWhere('cs.created_at', '>=', $phase3FloorDate);
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
        $staffAvatarMap = User::query()
            ->whereIn('id', $staffCfaByStaff->pluck('id')->map(fn ($id) => (int) $id)->all())
            ->get()
            ->keyBy('id');

        $businessMixChart = $this->businessCategoryMix($phase3FloorDate);
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

        $heroSparkline30 = $this->dailyCfaSparkline(30, $phase3FloorDate);

        $districtAllocPct = ($stateCfaTarget !== null && $stateCfaTarget > 0 && $districtsCfaSum !== null)
            ? (int) round(($districtsCfaSum / $stateCfaTarget) * 100)
            : null;

        return [
            'activeFy' => $activeFy,
            'cfaDeliverable' => $cfaDeliverable,
            'stateCfaTarget' => $stateCfaTarget !== null ? (int) $stateCfaTarget : null,
            'districtsCfaSum' => $districtsCfaSum,
            'districtAllocPct' => $districtAllocPct,
            'stateCfaThisFy' => $stateCfaThisFy,
            'stateProgressPct' => $stateProgressPct,
            'stateCfaTrend' => $stateCfaTrend,
            'stateBusinessStageMix' => $stateBusinessStageMix,
            'staffTotal' => $staffTotal,
            'staffActive' => $staffActive,
            'cfaTotal' => $cfaTotal,
            'phase1CfaTotal' => $phase1CfaTotal,
            'phase2CfaTotal' => $phase2CfaTotal,
            'phase3CfaTotal' => $phase3CfaTotal,
            'allPhasesCfaTotal' => $allPhasesCfaTotal,
            'cfaThisMonth' => $cfaThisMonth,
            'cfaLast30' => $cfaLast30,
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
        ];
    }

    /**
     * @return array{labels: list<string>, values: list<int>}
     */
    private function dailyCfaSparkline(int $days, Carbon $phase3FloorDate): array
    {
        $start = now()->subDays($days - 1)->startOfDay();
        if ($start->lt($phase3FloorDate)) {
            $start = $phase3FloorDate->copy();
        }
        $rows = DB::table('cfa_submissions')
            ->selectRaw('DATE(created_at) as d, COUNT(*) as total')
            ->where('created_at', '>=', $start)
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
    private function stateDailyTrend14(Carbon $phase3FloorDate): array
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
                    ->count();
            }
        }

        return ['labels' => $labels, 'values' => $values];
    }

    /**
     * @return array{labels: list<string>, values: list<int>}
     */
    private function stateStageMixAggregates(Carbon $phase3FloorDate, int $limit = 2000): array
    {
        $stage = [];

        CfaSubmission::query()
            ->where('created_at', '>=', $phase3FloorDate)
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
    private function businessCategoryMix(Carbon $phase3FloorDate): array
    {
        $counts = [];
        $q = CfaSubmission::query()
            ->where('created_at', '>=', $phase3FloorDate)
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
