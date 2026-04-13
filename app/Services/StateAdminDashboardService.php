<?php

namespace App\Services;

use App\Models\CfaSubmission;
use App\Models\Deliverable;
use App\Models\District;
use App\Models\DistrictDeliverableTarget;
use App\Models\FiscalYear;
use App\Models\StateDeliverableTarget;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class StateAdminDashboardService
{
    /**
     * @return array<string, mixed>
     */
    public function metrics(): array
    {
        $activeFy = FiscalYear::query()->where('is_active', true)->orderByDesc('starts_on')->first();
        $cfaDeliverable = Deliverable::query()->where('code', 'cfa')->first();

        $stateCfaTarget = null;
        $districtsCfaSum = null;
        if ($activeFy && $cfaDeliverable) {
            $stateCfaTarget = StateDeliverableTarget::query()
                ->where('fiscal_year_id', $activeFy->id)
                ->where('deliverable_id', $cfaDeliverable->id)
                ->value('target_total');
            $districtsCfaSum = (int) DistrictDeliverableTarget::query()
                ->where('fiscal_year_id', $activeFy->id)
                ->where('deliverable_id', $cfaDeliverable->id)
                ->sum('target_total');
        }

        $stateCfaThisFy = null;
        $stateProgressPct = null;
        $stateCfaTrend = ['labels' => [], 'values' => []];
        $stateBusinessStageMix = ['labels' => [], 'values' => []];

        if ($activeFy) {
            $fyId = (int) $activeFy->id;
            $stateCfaThisFy = (int) CfaSubmission::query()
                ->where('fiscal_year_id', $fyId)
                ->count();
            if ($stateCfaTarget !== null && (int) $stateCfaTarget > 0) {
                $stateProgressPct = (int) min(100, round($stateCfaThisFy / (int) $stateCfaTarget * 100));
            }
            $stateCfaTrend = $this->stateDailyTrend14($fyId);
            $stateBusinessStageMix = $this->stateStageMixAggregates($fyId);
        }

        $staffTotal = User::query()->where('role', 'district_staff')->count();
        $staffActive = User::query()->where('role', 'district_staff')->where('is_active', true)->count();

        $cfaTotal = CfaSubmission::query()->count();
        $cfaThisMonth = CfaSubmission::query()->whereYear('created_at', now()->year)->whereMonth('created_at', now()->month)->count();
        $cfaLast30 = CfaSubmission::query()->where('created_at', '>=', now()->subDays(30))->count();

        $seedCount = 0;
        $earlyCount = 0;
        $growthCount = 0;
        if ($activeFy) {
            $stageCounts = DB::table('cfa_submissions')
                ->where('fiscal_year_id', $activeFy->id)
                ->selectRaw("LOWER(TRIM(JSON_UNQUOTE(JSON_EXTRACT(payload, '$.form_stage')))) as stage_key")
                ->selectRaw('COUNT(*) as total')
                ->groupBy('stage_key')
                ->pluck('total', 'stage_key');
            $seedCount = (int) ($stageCounts['seed'] ?? 0);
            $earlyCount = (int) ($stageCounts['early'] ?? 0);
            $growthCount = (int) ($stageCounts['growth'] ?? 0);
        }

        $cfaByDistrict = $activeFy
            ? DB::table('cfa_submissions')
                ->join('districts', 'cfa_submissions.district_id', '=', 'districts.id')
                ->where('cfa_submissions.fiscal_year_id', $activeFy->id)
                ->select('districts.name', DB::raw('COUNT(*) as total'))
                ->groupBy('districts.id', 'districts.name')
                ->orderByDesc('total')
                ->limit(12)
                ->get()
            : collect();

        $staffByDistrict = DB::table('users')
            ->join('districts', 'users.district_id', '=', 'districts.id')
            ->where('users.role', 'district_staff')
            ->select('districts.name', DB::raw('COUNT(*) as total'))
            ->groupBy('districts.id', 'districts.name')
            ->orderByDesc('total')
            ->get();

        $businessMixChart = $this->businessCategoryMix($activeFy?->id);
        $businessMixChart['colors'] = $this->chartColorsForLabels($businessMixChart['labels']);

        $districtAllocPct = null;
        if ($stateCfaTarget !== null && (int) $stateCfaTarget > 0) {
            $districtAllocPct = (int) min(100, round(((int) ($districtsCfaSum ?? 0) / (int) $stateCfaTarget) * 100));
        }

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
            'cfaTrend' => $stateCfaTrend,
            'businessMix' => $businessMixChart,
        ];
    }

    /**
     * @return array{labels: list<string>, values: list<int>}
     */
    private function stateDailyTrend14(int $fiscalYearId): array
    {
        $labels = [];
        $values = [];
        for ($i = 13; $i >= 0; $i--) {
            $day = Carbon::now()->subDays($i)->startOfDay();
            $labels[] = $day->format('d M');
            $values[] = (int) CfaSubmission::query()
                ->where('fiscal_year_id', $fiscalYearId)
                ->whereDate('created_at', $day->toDateString())
                ->count();
        }

        return ['labels' => $labels, 'values' => $values];
    }

    /**
     * @return array{labels: list<string>, values: list<int>}
     */
    private function stateStageMixAggregates(int $fiscalYearId, int $limit = 2000): array
    {
        $stage = [];

        CfaSubmission::query()
            ->where('fiscal_year_id', $fiscalYearId)
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
    private function businessCategoryMix(?int $fiscalYearId = null): array
    {
        $counts = [];
        $q = CfaSubmission::query()
            ->whereNotNull('payload')
            ->orderByDesc('id')
            ->limit(1200);
        if ($fiscalYearId !== null) {
            $q->where('fiscal_year_id', $fiscalYearId);
        }
        $q->cursor()
            ->each(function (CfaSubmission $row) use (&$counts): void {
                $cat = $row->payload['business_category'] ?? null;
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
