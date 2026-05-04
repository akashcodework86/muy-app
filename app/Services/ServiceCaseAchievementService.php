<?php

namespace App\Services;

use App\Models\FiscalYear;
use App\Models\ServiceCase;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Approved {@see ServiceCase} rows credited to {@see ServiceCase::$submitted_by},
 * fiscal month from {@see ServiceCase::$created_at}.
 */
class ServiceCaseAchievementService
{
    /**
     * @return array<int, array<int, int>> deliverable_id => [ 1..12 => count ]
     */
    public function countsByDeliverableAndFiscalMonth(int $userId, FiscalYear $fy): array
    {
        if ($userId < 1) {
            return [];
        }

        $start = Carbon::parse($fy->starts_on)->startOfDay();
        $end = Carbon::parse($fy->ends_on)->endOfDay();

        $out = [];

        $query = DB::table('service_cases as sc')
            ->join('services as s', 's.id', '=', 'sc.service_id')
            ->where('sc.status', ServiceCase::STATUS_APPROVED)
            ->where('sc.submitted_by', $userId)
            ->whereNotNull('s.deliverable_id')
            ->whereBetween('sc.created_at', [$start, $end])
            ->select(['s.deliverable_id', 'sc.created_at']);

        foreach ($query->cursor() as $row) {
            $deliverableId = (int) $row->deliverable_id;
            $idx = $fy->fiscalMonthIndex(Carbon::parse($row->created_at));
            if ($idx === null || $idx < 1 || $idx > 12) {
                continue;
            }
            if (! isset($out[$deliverableId])) {
                $out[$deliverableId] = array_fill(1, 12, 0);
            }
            $out[$deliverableId][$idx]++;
        }

        return $out;
    }
}
