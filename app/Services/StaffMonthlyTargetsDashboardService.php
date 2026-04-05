<?php

namespace App\Services;

use App\Models\CfaSubmission;
use App\Models\Deliverable;
use App\Models\FiscalYear;
use App\Models\StaffMonthlyTarget;
use App\Models\User;
use App\Services\LegacyPhase2\LegacyRbiServicesAssignedAchievementService;
use App\Services\LegacyPhase2\Phase2TargetsPhpAchievementService;
use Carbon\Carbon;

class StaffMonthlyTargetsDashboardService
{
    public function __construct(
        private StaffDeliverableMonthlyTargetService $targets,
        private LegacyRbiServicesAssignedAchievementService $legacyServicesAchievement,
        private Phase2TargetsPhpAchievementService $phase2TargetsAchievement,
    ) {}

    /**
     * Map a datetime into FY month index M1..M12 from fiscal year bounds.
     */
    public function fiscalMonthIndex(Carbon $at, FiscalYear $fy): ?int
    {
        return $fy->fiscalMonthIndex($at);
    }

    /**
     * @return array<int, int> keys 1..12
     */
    public function cfaAchievementByFiscalMonth(int $userId, FiscalYear $fy): array
    {
        $counts = array_fill(1, 12, 0);
        CfaSubmission::query()
            ->where('referral_user_id', $userId)
            ->where('fiscal_year_id', $fy->id)
            ->select(['id', 'created_at'])
            ->orderBy('id')
            ->cursor()
            ->each(function (CfaSubmission $row) use (&$counts, $fy): void {
                $i = $this->fiscalMonthIndex($row->created_at, $fy);
                if ($i !== null) {
                    $counts[$i]++;
                }
            });

        return $counts;
    }

    /**
     * Short label for column header: M3 Jul
     *
     * @return array<int, string> 1..12 => label
     */
    public function fiscalMonthLabels(FiscalYear $fy): array
    {
        $start = Carbon::parse($fy->starts_on)->startOfMonth();
        $out = [];
        for ($m = 1; $m <= 12; $m++) {
            $d = $start->copy()->addMonths($m - 1);
            $out[$m] = 'M'.$m.' '.$d->format('M');
        }

        return $out;
    }

    /**
     * @return list<array{
     *   deliverable: Deliverable,
     *   districtTarget: int|null,
     *   othersAnnual: int,
     *   slot: int|null,
     *   monthlyTarget: array<int, int>,
     *   monthlyAchievement: array<int, int|null>,
     *   monthlySum: int,
     *   achievementAnnual: int,
     *   expandByDefault: bool,
     *   tracksAchievement: bool
     * }>
     */
    public function buildRows(User $user, FiscalYear $fy): array
    {
        $fyId = (int) $fy->id;
        $districtId = (int) $user->district_id;
        $userId = (int) $user->id;

        $cfaAchievement = $this->cfaAchievementByFiscalMonth($userId, $fy);

        $usePhase2 = $user->legacy_user_id
            && filled($user->district?->name)
            && filled((string) config('database.connections.legacy.database', ''));

        $legacyAchievement = $usePhase2
            ? $this->phase2TargetsAchievement->countsByDeliverableAndFiscalMonth($user, $fy)
            : $this->legacyServicesAchievement->countsByDeliverableAndFiscalMonth($user, $fy);

        $rows = [];
        $deliverables = Deliverable::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        foreach ($deliverables as $d) {
            $districtTarget = $this->targets->districtTargetTotal($fyId, $districtId, $d->id);
            $othersAnnual = $this->targets->otherStaffDistrictTotal($fyId, $districtId, $d->id, $userId);

            $monthlyTarget = StaffMonthlyTarget::query()
                ->where('fiscal_year_id', $fyId)
                ->where('user_id', $userId)
                ->where('deliverable_id', $d->id)
                ->pluck('target_count', 'month_number')
                ->all();

            $monthlySum = 0;
            $tMap = [];
            foreach (range(1, 12) as $m) {
                $tMap[$m] = (int) ($monthlyTarget[$m] ?? 0);
                $monthlySum += $tMap[$m];
            }

            $slot = $districtTarget !== null ? max(0, $districtTarget - $othersAnnual) : null;

            $legacyMonthly = $legacyAchievement[$d->id] ?? array_fill(1, 12, 0);
            $legacyAnnualTotal = array_sum($legacyMonthly);

            $hasDistrictTarget = $districtTarget !== null && (int) $districtTarget > 0;
            $tracksAchievement = $d->code === 'cfa'
                || $legacyAnnualTotal > 0
                || $monthlySum > 0
                || $hasDistrictTarget;

            $monthlyAchievement = array_fill(1, 12, null);
            $achievementAnnual = 0;

            if ($d->code === 'cfa') {
                foreach (range(1, 12) as $m) {
                    $v = max($cfaAchievement[$m], $legacyMonthly[$m]);
                    $monthlyAchievement[$m] = $v;
                    $achievementAnnual += $v;
                }
            } elseif ($tracksAchievement) {
                foreach (range(1, 12) as $m) {
                    $monthlyAchievement[$m] = (int) $legacyMonthly[$m];
                    $achievementAnnual += (int) $legacyMonthly[$m];
                }
            }

            $expandByDefault = $d->code === 'cfa'
                || $monthlySum > 0
                || $achievementAnnual > 0
                || $legacyAnnualTotal > 0
                || $hasDistrictTarget;

            $rows[] = [
                'deliverable' => $d,
                'districtTarget' => $districtTarget,
                'othersAnnual' => $othersAnnual,
                'slot' => $slot,
                'monthlyTarget' => $tMap,
                'monthlyAchievement' => $monthlyAchievement,
                'monthlySum' => $monthlySum,
                'achievementAnnual' => $achievementAnnual,
                'expandByDefault' => $expandByDefault,
                'tracksAchievement' => $tracksAchievement,
            ];
        }

        return $rows;
    }
}
