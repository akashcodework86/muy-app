<?php

namespace App\Services;

use App\Models\DistrictDeliverableTarget;
use App\Models\StaffMonthlyTarget;
use App\Models\StateDeliverableTarget;
use App\Models\User;

class TargetValidationService
{
    /**
     * Sum of district targets for (fy, deliverable) must equal state target.
     */
    public function stateMatchesDistricts(int $fiscalYearId, int $deliverableId): bool
    {
        $state = StateDeliverableTarget::query()
            ->where('fiscal_year_id', $fiscalYearId)
            ->where('deliverable_id', $deliverableId)
            ->value('target_total');

        if ($state === null) {
            return false;
        }

        $districtSum = (int) DistrictDeliverableTarget::query()
            ->where('fiscal_year_id', $fiscalYearId)
            ->where('deliverable_id', $deliverableId)
            ->sum('target_total');

        return (int) $state === $districtSum;
    }

    /**
     * Sum of staff monthly targets for users in district must equal district target.
     */
    public function districtMatchesStaffMonthly(int $fiscalYearId, int $districtId, int $deliverableId): bool
    {
        $district = DistrictDeliverableTarget::query()
            ->where('fiscal_year_id', $fiscalYearId)
            ->where('district_id', $districtId)
            ->where('deliverable_id', $deliverableId)
            ->value('target_total');

        if ($district === null) {
            return false;
        }

        $userIds = User::query()->where('district_id', $districtId)->pluck('id');

        $staffSum = (int) StaffMonthlyTarget::query()
            ->where('fiscal_year_id', $fiscalYearId)
            ->where('deliverable_id', $deliverableId)
            ->whereIn('user_id', $userIds)
            ->sum('target_count');

        return (int) $district === $staffSum;
    }

    /**
     * Sum of months 1–12 for one user/deliverable must equal expected FY staff total (if stored separately later).
     * For now: optional check that monthly sum equals sum of district allocation for that user... not stored.
     * Use: verify staff monthly total equals a staff FY cap if you add staff_fy_targets.
     */
    public function staffMonthlyFyTotal(int $fiscalYearId, int $userId, int $deliverableId): int
    {
        return (int) StaffMonthlyTarget::query()
            ->where('fiscal_year_id', $fiscalYearId)
            ->where('user_id', $userId)
            ->where('deliverable_id', $deliverableId)
            ->sum('target_count');
    }
}
