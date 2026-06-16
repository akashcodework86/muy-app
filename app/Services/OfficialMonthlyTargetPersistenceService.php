<?php

namespace App\Services;

use App\Models\OfficialDistrictMonthlyTarget;
use App\Models\OfficialHubMonthlyTarget;
use App\Models\OfficialStateMonthlyTarget;
use Illuminate\Support\Facades\DB;

class OfficialMonthlyTargetPersistenceService
{
    /**
     * @param  array<int, array<int, int|string|null>>  $monthsByKey  district_id|hub_id => [month => count]
     */
    public function saveStateGrid(int $fiscalYearId, int $deliverableId, array $months): void
    {
        for ($m = 1; $m <= 12; $m++) {
            $count = max(0, (int) ($months[$m] ?? $months[(string) $m] ?? 0));

            OfficialStateMonthlyTarget::query()->updateOrCreate(
                [
                    'fiscal_year_id' => $fiscalYearId,
                    'deliverable_id' => $deliverableId,
                    'month_number' => $m,
                ],
                ['target_count' => $count],
            );
        }
    }

    /**
     * @param  array<int, array<int, int|string|null>>  $districtMonths
     */
    public function saveDistrictGrid(int $fiscalYearId, int $deliverableId, array $districtMonths): void
    {
        DB::transaction(function () use ($fiscalYearId, $deliverableId, $districtMonths): void {
            foreach ($districtMonths as $districtId => $months) {
                $districtId = (int) $districtId;
                if ($districtId <= 0 || ! is_array($months)) {
                    continue;
                }

                for ($m = 1; $m <= 12; $m++) {
                    $count = max(0, (int) ($months[$m] ?? $months[(string) $m] ?? 0));

                    OfficialDistrictMonthlyTarget::query()->updateOrCreate(
                        [
                            'fiscal_year_id' => $fiscalYearId,
                            'district_id' => $districtId,
                            'deliverable_id' => $deliverableId,
                            'month_number' => $m,
                        ],
                        ['target_count' => $count],
                    );
                }
            }
        });
    }

    /**
     * @param  array<int, array<int, int|string|null>>  $hubMonths
     */
    public function saveHubGrid(int $fiscalYearId, int $deliverableId, array $hubMonths): void
    {
        DB::transaction(function () use ($fiscalYearId, $deliverableId, $hubMonths): void {
            foreach ($hubMonths as $hubId => $months) {
                $hubId = (int) $hubId;
                if ($hubId <= 0 || ! is_array($months)) {
                    continue;
                }

                for ($m = 1; $m <= 12; $m++) {
                    $count = max(0, (int) ($months[$m] ?? $months[(string) $m] ?? 0));

                    OfficialHubMonthlyTarget::query()->updateOrCreate(
                        [
                            'fiscal_year_id' => $fiscalYearId,
                            'hub_id' => $hubId,
                            'deliverable_id' => $deliverableId,
                            'month_number' => $m,
                        ],
                        ['target_count' => $count],
                    );
                }
            }
        });
    }
}
