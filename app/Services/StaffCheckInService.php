<?php

namespace App\Services;

use App\Models\StaffCheckIn;
use App\Models\User;
use App\Support\StaffDailyCheckInAccess;
use Carbon\Carbon;
use Illuminate\Support\Collection;

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
}
