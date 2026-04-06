<?php

namespace App\Services\LegacyPhase2;

use App\Models\Deliverable;
use App\Models\FiscalYear;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fallback: rbi_services_assigned only, scoped to selected FY (event date in starts_on…ends_on).
 */
class LegacyRbiServicesAssignedAchievementService
{
    /**
     * @return array<int, array<int, int>> deliverable_id => [ 1..12 => count ]
     */
    public function countsByDeliverableAndFiscalMonth(User $user, FiscalYear $fy): array
    {
        if (! $user->legacy_user_id) {
            return [];
        }

        if ((string) config('database.connections.legacy.database', '') === '') {
            return [];
        }

        if (! Schema::connection('legacy')->hasTable('rbi_services_assigned')) {
            return [];
        }

        $codeToId = Deliverable::query()
            ->where('is_active', true)
            ->pluck('id', 'code')
            ->all();

        $rows = DB::connection('legacy')
            ->table('rbi_services_assigned')
            ->where('served_by', (int) $user->legacy_user_id)
            ->select(['category', 'service_name', 'assigned_date', 'doc_date'])
            ->cursor();

        $out = [];

        foreach ($rows as $row) {
            $at = $this->eventCarbon((string) ($row->doc_date ?? ''), $row->assigned_date ?? null);
            if ($at === null || ! $this->carbonInFiscalYear($at, $fy)) {
                continue;
            }

            $code = $this->resolveDeliverableCode((string) $row->category, (string) $row->service_name);
            if ($code === null || ! isset($codeToId[$code])) {
                continue;
            }

            $deliverableId = (int) $codeToId[$code];
            $idx = $fy->fiscalMonthIndex($at);
            if ($idx === null) {
                continue;
            }

            if (! isset($out[$deliverableId])) {
                $out[$deliverableId] = array_fill(1, 12, 0);
            }
            $out[$deliverableId][$idx]++;
        }

        return $out;
    }

    /**
     * Prefer doc_date (business / document date); legacy rows often have bulk-assigned assigned_date.
     */
    public function eventCarbon(string $docDate, mixed $assignedDate): ?Carbon
    {
        $d = trim($docDate);
        if ($d !== '') {
            try {
                return Carbon::parse($d)->startOfDay();
            } catch (\Throwable) {
                //
            }
        }
        if ($assignedDate === null || $assignedDate === '') {
            return null;
        }
        try {
            return Carbon::parse($assignedDate);
        } catch (\Throwable) {
            return null;
        }
    }

    /** FY window check (dashboard fallback + unmapped reports). */
    public function carbonInFiscalYear(Carbon $at, FiscalYear $fy): bool
    {
        $start = Carbon::parse($fy->starts_on)->startOfDay();
        $end = Carbon::parse($fy->ends_on)->endOfDay();

        return ! $at->lt($start) && ! $at->gt($end);
    }

    public function resolveDeliverableCode(string $category, string $serviceName): ?string
    {
        $explicit = config('legacy_phase2.rbi_services_assigned_to_deliverable', []);

        $catKey = $this->normKey($category);
        $svcKey = $this->normKey($serviceName);
        $compound = $catKey !== '' ? $catKey.'|'.$svcKey : $svcKey;

        if ($compound !== '' && isset($explicit[$compound])) {
            return $explicit[$compound];
        }
        if ($svcKey !== '' && isset($explicit[$svcKey])) {
            return $explicit[$svcKey];
        }

        $activityMap = config('legacy_phase2.activity_type_to_deliverable_code', []);
        $activityKey = str_replace([' ', '-'], ['_', '_'], strtolower(trim($serviceName)));

        if ($activityKey !== '' && isset($activityMap[$activityKey])) {
            return $activityMap[$activityKey];
        }

        $sn = strtolower(trim($serviceName));
        if ($sn !== '' && isset($activityMap[$sn])) {
            return $activityMap[$sn];
        }

        return null;
    }

    private function normKey(string $s): string
    {
        return strtolower(trim(preg_replace('/\s+/u', ' ', $s)));
    }
}
