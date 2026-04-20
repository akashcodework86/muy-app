<?php

namespace App\Services;

use App\Models\CfaSubmission;
use App\Models\Deliverable;
use App\Models\District;
use App\Models\DistrictDeliverableTarget;
use App\Models\FiscalYear;
use App\Models\Hub;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

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
        ];
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
