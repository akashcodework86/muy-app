<?php

namespace App\Services;

use App\Models\StaffCheckIn;
use App\Models\User;
use App\Support\StaffDailyCheckInAccess;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class StaffCheckInService
{
    public function todayDate(): Carbon
    {
        return now()->startOfDay();
    }

    public function todayForUser(User $user): ?StaffCheckIn
    {
        return StaffCheckIn::query()
            ->where('user_id', $user->id)
            ->whereDate('check_in_date', $this->todayDate())
            ->first();
    }

    public function shouldShowReminder(User $user): bool
    {
        if (! StaffDailyCheckInAccess::isRequired($user)) {
            return false;
        }

        if ($this->todayForUser($user) !== null) {
            return false;
        }

        return now()->hour >= StaffDailyCheckInAccess::reminderHour();
    }

    /**
     * @return Collection<int, User>
     */
    public function staffUsersQuery()
    {
        return User::query()
            ->where('is_active', true)
            ->whereNotIn('role', ['state_admin', 'incubatee'])
            ->with([
                'designationRecord:id,name',
                'hub:id,name',
                'district:id,name',
            ]);
    }

    /**
     * @return array{total: int, present: int, absent: int, rows: Collection<int, array<string, mixed>>}
     */
    public function adminSummaryForDate(Carbon $date, ?string $roleFilter = null, ?int $hubId = null, ?int $districtId = null, ?string $statusFilter = null): array
    {
        $query = $this->staffUsersQuery()->orderBy('name');

        if ($roleFilter !== null && $roleFilter !== '') {
            $query->where('role', $roleFilter);
        }
        if ($hubId !== null && $hubId > 0) {
            $query->where('hub_id', $hubId);
        }
        if ($districtId !== null && $districtId > 0) {
            $query->where('district_id', $districtId);
        }

        $staff = $query->get();
        $checkIns = StaffCheckIn::query()
            ->whereDate('check_in_date', $date)
            ->whereIn('user_id', $staff->pluck('id'))
            ->get()
            ->keyBy('user_id');

        $rows = $staff->map(function (User $user) use ($checkIns) {
            $checkIn = $checkIns->get($user->id);

            return [
                'user' => $user,
                'check_in' => $checkIn,
                'present' => $checkIn !== null,
            ];
        });

        $total = $staff->count();
        $presentCount = $staff->filter(fn (User $user) => $checkIns->has($user->id))->count();

        if ($statusFilter === 'present') {
            $rows = $rows->filter(fn (array $row) => $row['present'])->values();
        } elseif ($statusFilter === 'absent') {
            $rows = $rows->filter(fn (array $row) => ! $row['present'])->values();
        }

        return [
            'total' => $total,
            'present' => $presentCount,
            'absent' => max(0, $total - $presentCount),
            'rows' => $rows,
        ];
    }

    /**
     * Hub dashboard: district staff daily attendance (today, trends, analysis).
     *
     * @return array<string, mixed>
     */
    public function hubAttendanceMetrics(int $hubId): array
    {
        $dateLabel = now()->format('d M Y');
        $empty = [
            'enabled' => false,
            'date_label' => $dateLabel,
            'today' => ['total' => 0, 'present' => 0, 'absent' => 0, 'rate_pct' => null],
            'today_rows' => [],
            'district_today' => [],
            'trend_14d' => ['labels' => [], 'rates' => [], 'present' => [], 'total' => []],
            'rate_7d' => null,
            'rate_30d' => null,
            'rate_mtd' => null,
            'staff_30d' => [],
            'weekday' => ['labels' => [], 'rates' => []],
            'insights' => [],
        ];

        if ($hubId <= 0 || ! Schema::hasTable('staff_check_ins')) {
            return $empty;
        }

        $staffBase = User::query()
            ->where('role', 'district_staff')
            ->where('hub_id', $hubId)
            ->where('is_active', true);

        $staffCount = (int) (clone $staffBase)->count();
        if ($staffCount === 0) {
            return array_merge($empty, ['enabled' => true]);
        }

        $todaySummary = $this->adminSummaryForDate(now(), 'district_staff', $hubId);
        $todayTotal = (int) $todaySummary['total'];
        $todayPresent = (int) $todaySummary['present'];
        $todayRate = $todayTotal > 0 ? (int) round(($todayPresent / $todayTotal) * 100) : null;

        $todayRows = $todaySummary['rows']->map(function (array $row): array {
            /** @var User $user */
            $user = $row['user'];
            $checkIn = $row['check_in'];

            return [
                'id' => (int) $user->id,
                'name' => (string) $user->name,
                'district' => (string) ($user->district?->name ?? 'Unassigned'),
                'present' => (bool) $row['present'],
                'marked_at' => $checkIn?->marked_at?->format('H:i'),
            ];
        })->values()->all();

        $districtToday = [];
        foreach ($todayRows as $row) {
            $district = $row['district'];
            if (! isset($districtToday[$district])) {
                $districtToday[$district] = ['district' => $district, 'total' => 0, 'present' => 0];
            }
            $districtToday[$district]['total']++;
            if ($row['present']) {
                $districtToday[$district]['present']++;
            }
        }
        $districtToday = collect($districtToday)
            ->map(function (array $row): array {
                $total = (int) $row['total'];
                $present = (int) $row['present'];

                return [
                    'district' => (string) $row['district'],
                    'total' => $total,
                    'present' => $present,
                    'absent' => max(0, $total - $present),
                    'rate_pct' => $total > 0 ? (int) round(($present / $total) * 100) : null,
                ];
            })
            ->sort(function (array $a, array $b): int {
                $ra = (int) ($a['rate_pct'] ?? 0);
                $rb = (int) ($b['rate_pct'] ?? 0);
                if ($ra !== $rb) {
                    return $rb <=> $ra;
                }

                return ((int) $b['present']) <=> ((int) $a['present']);
            })
            ->values()
            ->all();

        $trendLabels = [];
        $trendRates = [];
        $trendPresent = [];
        $trendTotal = [];
        $rateSum7 = 0;
        $rateCount7 = 0;

        for ($i = 13; $i >= 0; $i--) {
            $day = now()->subDays($i)->startOfDay();
            $summary = $this->adminSummaryForDate($day, 'district_staff', $hubId);
            $total = (int) $summary['total'];
            $present = (int) $summary['present'];
            $rate = $total > 0 ? (int) round(($present / $total) * 100) : 0;

            $trendLabels[] = $day->format('d M');
            $trendRates[] = $rate;
            $trendPresent[] = $present;
            $trendTotal[] = $total;

            if ($i <= 6) {
                $rateSum7 += $rate;
                $rateCount7++;
            }
        }

        $rate7d = $rateCount7 > 0 ? (int) round($rateSum7 / $rateCount7) : null;

        $start30 = now()->subDays(29)->startOfDay();
        $present30 = (int) StaffCheckIn::query()
            ->where('check_in_date', '>=', $start30->toDateString())
            ->whereIn('user_id', (clone $staffBase)->pluck('id'))
            ->count();
        $rate30d = (int) round(($present30 / max(1, $staffCount * 30)) * 100);

        $mtdStart = now()->startOfMonth();
        $mtdDays = max(1, $mtdStart->diffInDays(now()->startOfDay()) + 1);
        $presentMtd = (int) StaffCheckIn::query()
            ->where('check_in_date', '>=', $mtdStart->toDateString())
            ->where('check_in_date', '<=', now()->toDateString())
            ->whereIn('user_id', (clone $staffBase)->pluck('id'))
            ->count();
        $rateMtd = (int) round(($presentMtd / max(1, $staffCount * $mtdDays)) * 100);

        $daysPresentByUser = DB::table('staff_check_ins as sci')
            ->join('users as u', 'u.id', '=', 'sci.user_id')
            ->where('u.role', 'district_staff')
            ->where('u.hub_id', $hubId)
            ->where('u.is_active', true)
            ->where('sci.check_in_date', '>=', $start30->toDateString())
            ->groupBy('sci.user_id')
            ->selectRaw('sci.user_id as user_id, COUNT(sci.id) as day_count')
            ->pluck('day_count', 'user_id')
            ->map(fn ($count) => (int) $count)
            ->all();

        $staff30d = (clone $staffBase)
            ->with('district:id,name')
            ->orderBy('name')
            ->get(['id', 'name', 'district_id'])
            ->map(function (User $user) use ($daysPresentByUser): array {
                $days = (int) ($daysPresentByUser[$user->id] ?? 0);

                return [
                    'id' => (int) $user->id,
                    'name' => (string) $user->name,
                    'district' => (string) ($user->district?->name ?? 'Unassigned'),
                    'days_present' => $days,
                    'rate_pct' => (int) round(($days / 30) * 100),
                ];
            })
            ->sort(function (array $a, array $b): int {
                if ($a['days_present'] !== $b['days_present']) {
                    return $b['days_present'] <=> $a['days_present'];
                }

                return strcasecmp((string) $a['name'], (string) $b['name']);
            })
            ->values()
            ->all();

        $weekdayLabels = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
        $weekdayBuckets = array_fill(0, 7, ['present' => 0, 'capacity' => 0]);
        for ($i = 27; $i >= 0; $i--) {
            $day = now()->subDays($i)->startOfDay();
            $summary = $this->adminSummaryForDate($day, 'district_staff', $hubId);
            $iso = (int) $day->isoWeekday();
            $idx = $iso - 1;
            $weekdayBuckets[$idx]['present'] += (int) $summary['present'];
            $weekdayBuckets[$idx]['capacity'] += (int) $summary['total'];
        }
        $weekdayRates = [];
        foreach ($weekdayBuckets as $bucket) {
            $cap = (int) $bucket['capacity'];
            $weekdayRates[] = $cap > 0
                ? (int) round(((int) $bucket['present'] / $cap) * 100)
                : 0;
        }

        $insights = $this->hubAttendanceInsights(
            $todayRate,
            $rate7d,
            $rate30d,
            $districtToday,
            $weekdayLabels,
            $weekdayRates,
            $todayRows
        );

        return [
            'enabled' => true,
            'date_label' => $dateLabel,
            'today' => [
                'total' => $todayTotal,
                'present' => $todayPresent,
                'absent' => (int) $todaySummary['absent'],
                'rate_pct' => $todayRate,
            ],
            'today_rows' => $todayRows,
            'district_today' => $districtToday,
            'trend_14d' => [
                'labels' => $trendLabels,
                'rates' => $trendRates,
                'present' => $trendPresent,
                'total' => $trendTotal,
            ],
            'rate_7d' => $rate7d,
            'rate_30d' => $rate30d,
            'rate_mtd' => $rateMtd,
            'staff_30d' => $staff30d,
            'weekday' => [
                'labels' => $weekdayLabels,
                'rates' => $weekdayRates,
            ],
            'insights' => $insights,
        ];
    }

    /**
     * @param  list<array{district: string, total: int, present: int, absent: int, rate_pct: int|null}>  $districtToday
     * @param  list<string>  $weekdayLabels
     * @param  list<int>  $weekdayRates
     * @param  list<array<string, mixed>>  $todayRows
     * @return list<string>
     */
    private function hubAttendanceInsights(
        ?int $todayRate,
        ?int $rate7d,
        ?int $rate30d,
        array $districtToday,
        array $weekdayLabels,
        array $weekdayRates,
        array $todayRows,
    ): array {
        $insights = [];

        if ($todayRate !== null && $rate7d !== null) {
            $delta = $todayRate - $rate7d;
            if ($delta >= 8) {
                $insights[] = "Today's attendance ({$todayRate}%) is above the 7-day hub average ({$rate7d}%).";
            } elseif ($delta <= -8) {
                $insights[] = "Today's attendance ({$todayRate}%) is below the 7-day hub average ({$rate7d}%). Follow up with absent district staff.";
            } else {
                $insights[] = "Today's attendance ({$todayRate}%) is in line with the recent 7-day hub average ({$rate7d}%).";
            }
        }

        if ($rate30d !== null) {
            $insights[] = "Rolling 30-day mark rate across active district staff is {$rate30d}% (one mark per staff per day).";
        }

        if ($districtToday !== []) {
            $best = $districtToday[0];
            $worst = $districtToday[count($districtToday) - 1];
            if ($best['district'] !== $worst['district'] && $best['rate_pct'] !== null && $worst['rate_pct'] !== null) {
                $insights[] = "Strongest district today: {$best['district']} ({$best['rate_pct']}% present). Lowest: {$worst['district']} ({$worst['rate_pct']}% present).";
            }
        }

        $maxRate = -1;
        $maxLabel = null;
        foreach ($weekdayRates as $i => $rate) {
            if ($rate > $maxRate) {
                $maxRate = $rate;
                $maxLabel = $weekdayLabels[$i] ?? null;
            }
        }
        if ($maxLabel !== null && $maxRate >= 0) {
            $insights[] = "Highest typical attendance day in the last 4 weeks: {$maxLabel} ({$maxRate}% average).";
        }

        $absentToday = array_filter($todayRows, fn (array $row) => ! ($row['present'] ?? false));
        $absentCount = count($absentToday);
        if ($absentCount > 0) {
            $insights[] = "{$absentCount} district staff have not marked attendance yet today.";
        }

        return $insights;
    }
}
