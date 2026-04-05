<?php

namespace App\Services;

use App\Models\DistrictDeliverableTarget;
use App\Models\StaffMonthlyTarget;
use App\Models\User;

class StaffDeliverableMonthlyTargetService
{
    /**
     * Sum of 12 monthly rows for this user / FY / deliverable.
     */
    public function userAnnualTotal(int $fiscalYearId, int $userId, int $deliverableId): int
    {
        return (int) StaffMonthlyTarget::query()
            ->where('fiscal_year_id', $fiscalYearId)
            ->where('user_id', $userId)
            ->where('deliverable_id', $deliverableId)
            ->sum('target_count');
    }

    /**
     * Sum of annual totals (12-month sums) for other district_staff in the same district.
     */
    public function otherStaffDistrictTotal(int $fiscalYearId, int $districtId, int $deliverableId, int $excludeUserId): int
    {
        $ids = User::query()
            ->where('district_id', $districtId)
            ->where('role', 'district_staff')
            ->where('id', '!=', $excludeUserId)
            ->pluck('id');

        $sum = 0;
        foreach ($ids as $uid) {
            $sum += $this->userAnnualTotal($fiscalYearId, (int) $uid, $deliverableId);
        }

        return $sum;
    }

    public function districtTargetTotal(int $fiscalYearId, int $districtId, int $deliverableId): ?int
    {
        $row = DistrictDeliverableTarget::query()
            ->where('fiscal_year_id', $fiscalYearId)
            ->where('district_id', $districtId)
            ->where('deliverable_id', $deliverableId)
            ->first();

        return $row ? (int) $row->target_total : null;
    }

    /**
     * After saving months for $user, other staff + this user's annual total must equal district row.
     */
    public function districtTotalsMatch(int $fiscalYearId, User $user, int $deliverableId): bool
    {
        if (! $user->district_id) {
            return false;
        }

        $expected = $this->districtTargetTotal($fiscalYearId, (int) $user->district_id, $deliverableId);
        if ($expected === null) {
            return false;
        }

        $mine = $this->userAnnualTotal($fiscalYearId, $user->id, $deliverableId);
        $others = $this->otherStaffDistrictTotal($fiscalYearId, (int) $user->district_id, $deliverableId, $user->id);

        return ($mine + $others) === $expected;
    }
}
