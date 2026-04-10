<?php

namespace App\Services;

use App\Models\CfaSubmission;
use App\Models\Deliverable;
use App\Models\FiscalYear;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class StaffDashboardService
{
    public function __construct(
        private StaffDeliverableMonthlyTargetService $monthlyTargets,
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

        $activeFy = FiscalYear::query()->where('is_active', true)->orderByDesc('starts_on')->first();
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

        $districtCfaThisFy = null;
        if ($activeFy && $user->district_id) {
            $districtCfaThisFy = CfaSubmission::query()
                ->where('district_id', (int) $user->district_id)
                ->where('fiscal_year_id', (int) $activeFy->id)
                ->count();
        }

        $base = CfaSubmission::query()->where('referral_user_id', $user->id);

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

        $heatmap30 = $this->referralHeatmap30((int) $user->id);
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

        return [
            'staff' => $user,
            'activeFy' => $activeFy,
            'cfaDeliverable' => $cfaDeliverable,
            'referralUrl' => $user->referralApplyUrl(),
            'staffAnnualTarget' => $staffAnnualTarget,
            'districtCfaTarget' => $districtCfaTarget,
            'districtCfaThisFy' => $districtCfaThisFy,
            'cfaTotal' => $cfaTotal,
            'cfaThisMonth' => $cfaThisMonth,
            'cfaLast30' => $cfaLast30,
            'cfaThisFy' => $cfaThisFy,
            'cfaTrend' => [
                'labels' => $trendLabels,
                'values' => $trendValues,
            ],
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
        ];
    }

    /**
     * Last 30 calendar days (oldest → newest) with submission counts per day.
     *
     * @return list<array{date: string, count: int}>
     */
    private function referralHeatmap30(int $userId): array
    {
        $start = now()->copy()->subDays(29)->startOfDay();
        /** @var array<string, int|string> $counts */
        $counts = CfaSubmission::query()
            ->where('referral_user_id', $userId)
            ->where('created_at', '>=', $start)
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

        return [
            'businessMix' => $this->sortAndCapChart($business, 8),
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
}
