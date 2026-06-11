<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * BST deliverable targets from Aadil's training package monthly session plans.
 */
final class BstTrainingMonthPlanTargetSupport
{
    /**
     * Count required (non-extra) planned sessions in scope.
     *
     * @param  list<int>|null  $districtIds  null = all districts
     */
    public static function plannedRequiredSessionCount(
        ?array $districtIds,
        ?Carbon $periodFrom,
        ?Carbon $periodTo,
    ): int {
        if (! Schema::hasTable('training_package_month_sessions')) {
            return 0;
        }

        $query = DB::table('training_package_month_sessions')
            ->where('is_extra', false);

        if ($districtIds !== null) {
            if ($districtIds === []) {
                return 0;
            }

            $query->whereIn('district_id', $districtIds);
        }

        if ($periodFrom && $periodTo) {
            $query->where(function ($periodQuery) use ($periodFrom, $periodTo): void {
                $cursor = $periodFrom->copy()->startOfMonth();
                $end = $periodTo->copy()->startOfMonth();

                while ($cursor->lte($end)) {
                    $year = (int) $cursor->year;
                    $month = (int) $cursor->month;

                    $periodQuery->orWhere(function ($monthQuery) use ($year, $month): void {
                        $monthQuery->where('calendar_year', $year)
                            ->where('calendar_month', $month);
                    });

                    $cursor->addMonth();
                }
            });
        }

        return (int) $query->count();
    }
}
