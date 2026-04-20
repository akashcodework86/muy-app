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

        $activeFy = FiscalYear::query()->where('is_active', true)->orderByDesc('starts_on')->first();
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

        $cfaBase = CfaSubmission::query()->when($districtIds !== [], fn ($q) => $q->whereIn('district_id', $districtIds));
        $cfaTotal = (clone $cfaBase)->count();

        $hubCfaThisFy = null;
        if ($activeFy && $districtIds !== []) {
            $hubCfaThisFy = (int) CfaSubmission::query()
                ->whereIn('district_id', $districtIds)
                ->where('fiscal_year_id', $activeFy->id)
                ->count();
        }
        $cfaThisMonth = (clone $cfaBase)
            ->whereYear('created_at', now()->year)
            ->whereMonth('created_at', now()->month)
            ->count();
        $cfaLast30 = (clone $cfaBase)->where('created_at', '>=', now()->subDays(30))->count();

        $stageQuery = DB::table('cfa_submissions')
            ->selectRaw("LOWER(TRIM(JSON_UNQUOTE(JSON_EXTRACT(payload, '$.form_stage')))) as stage_key")
            ->selectRaw('COUNT(*) as total');
        if ($districtIds !== []) {
            $stageQuery->whereIn('district_id', $districtIds);
        }
        $stageCounts = $stageQuery->groupBy('stage_key')->pluck('total', 'stage_key');

        $cfaByDistrict = $districtIds === []
            ? collect()
            : DB::table('cfa_submissions')
                ->join('districts', 'cfa_submissions.district_id', '=', 'districts.id')
                ->whereIn('districts.id', $districtIds)
                ->select('districts.name', DB::raw('COUNT(*) as total'))
                ->groupBy('districts.id', 'districts.name')
                ->orderByDesc('total')
                ->get();

        $staffByDistrict = DB::table('users')
            ->join('districts', 'users.district_id', '=', 'districts.id')
            ->where('users.role', 'district_staff')
            ->where('users.hub_id', $hubId)
            ->select('districts.name', DB::raw('COUNT(*) as total'))
            ->groupBy('districts.id', 'districts.name')
            ->orderByDesc('total')
            ->get();

        $trendLabels = [];
        $trendValues = [];
        for ($i = 13; $i >= 0; $i--) {
            $day = Carbon::now()->subDays($i)->startOfDay();
            $trendLabels[] = $day->format('d M');
            $q = CfaSubmission::query()->whereDate('created_at', $day->toDateString());
            if ($districtIds !== []) {
                $q->whereIn('district_id', $districtIds);
            }
            $trendValues[] = (int) $q->count();
        }

        $businessMix = $this->businessCategoryMix($districtIds);

        $heroCfaToday = 0;
        $heroCfaYesterday = 0;
        if ($districtIds !== []) {
            $heroCfaToday = (int) CfaSubmission::query()
                ->whereIn('district_id', $districtIds)
                ->whereDate('created_at', now()->toDateString())
                ->count();
            $heroCfaYesterday = (int) CfaSubmission::query()
                ->whereIn('district_id', $districtIds)
                ->whereDate('created_at', now()->subDay()->toDateString())
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

        $heroSparkline30 = $this->hubDailyCfaSparkline($districtIds, 30);

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
        ];
    }

    /**
     * @param  list<int>  $districtIds
     * @return array{labels: list<string>, values: list<int>}
     */
    private function hubDailyCfaSparkline(array $districtIds, int $days): array
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
    private function businessCategoryMix(array $districtIds): array
    {
        if ($districtIds === []) {
            return ['labels' => [], 'values' => []];
        }

        $counts = [];
        CfaSubmission::query()
            ->whereIn('district_id', $districtIds)
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
