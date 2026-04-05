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

        $stateCfa = null;
        $districtsCfaSum = null;
        if ($activeFy && $cfaDeliverable) {
            $stateCfa = StateDeliverableTarget::query()
                ->where('fiscal_year_id', $activeFy->id)
                ->where('deliverable_id', $cfaDeliverable->id)
                ->value('target_total');
            $districtsCfaSum = (int) DistrictDeliverableTarget::query()
                ->where('fiscal_year_id', $activeFy->id)
                ->where('deliverable_id', $cfaDeliverable->id)
                ->sum('target_total');
        }

        $staffTotal = User::query()->where('role', 'district_staff')->count();
        $staffActive = User::query()->where('role', 'district_staff')->where('is_active', true)->count();

        $cfaTotal = CfaSubmission::query()->count();
        $cfaThisMonth = CfaSubmission::query()->whereYear('created_at', now()->year)->whereMonth('created_at', now()->month)->count();
        $cfaLast30 = CfaSubmission::query()->where('created_at', '>=', now()->subDays(30))->count();

        $cfaByDistrict = DB::table('cfa_submissions')
            ->join('districts', 'cfa_submissions.district_id', '=', 'districts.id')
            ->select('districts.name', DB::raw('COUNT(*) as total'))
            ->groupBy('districts.id', 'districts.name')
            ->orderByDesc('total')
            ->limit(12)
            ->get();

        $staffByDistrict = DB::table('users')
            ->join('districts', 'users.district_id', '=', 'districts.id')
            ->where('users.role', 'district_staff')
            ->select('districts.name', DB::raw('COUNT(*) as total'))
            ->groupBy('districts.id', 'districts.name')
            ->orderByDesc('total')
            ->get();

        $trendLabels = [];
        $trendValues = [];
        for ($i = 13; $i >= 0; $i--) {
            $day = Carbon::now()->subDays($i)->startOfDay();
            $trendLabels[] = $day->format('d M');
            $trendValues[] = (int) CfaSubmission::query()
                ->whereDate('created_at', $day->toDateString())
                ->count();
        }

        $businessMix = $this->businessCategoryMix();

        return [
            'activeFy' => $activeFy,
            'cfaDeliverable' => $cfaDeliverable,
            'stateCfaTarget' => $stateCfa !== null ? (int) $stateCfa : null,
            'districtsCfaSum' => $districtsCfaSum,
            'staffTotal' => $staffTotal,
            'staffActive' => $staffActive,
            'cfaTotal' => $cfaTotal,
            'cfaThisMonth' => $cfaThisMonth,
            'cfaLast30' => $cfaLast30,
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
            'cfaTrend' => [
                'labels' => $trendLabels,
                'values' => $trendValues,
            ],
            'businessMix' => $businessMix,
        ];
    }

    /**
     * @return array{labels: list<string>, values: list<int>}
     */
    private function businessCategoryMix(): array
    {
        $counts = [];
        CfaSubmission::query()
            ->whereNotNull('payload')
            ->orderByDesc('id')
            ->limit(800)
            ->cursor()
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
