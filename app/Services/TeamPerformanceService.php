<?php

namespace App\Services;

use App\Models\CfaSubmission;
use App\Models\District;
use App\Models\FiscalYear;
use App\Models\Hub;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TeamPerformanceService
{
    /**
     * Build a nested tree: State -> Hubs -> Districts -> Staff
     * Each node carries its CFA count (scoped to the given or active FY).
     *
     * @return array<string, mixed>
     */
    public function buildTree(?int $fiscalYearId = null): array
    {
        $activeFy = $fiscalYearId
            ? FiscalYear::query()->find($fiscalYearId)
            : FiscalYear::query()->where('is_active', true)->orderByDesc('starts_on')->first();

        $fiscalYears = FiscalYear::query()->orderByDesc('starts_on')->get(['id', 'code', 'name']);

        $hubs = Hub::query()->orderBy('sort_order')->orderBy('name')->get();
        $districts = District::query()->orderBy('hub_id')->orderBy('sort_order')->orderBy('name')->get();
        $staffUsers = User::query()
            ->where('role', 'district_staff')
            ->orderBy('district_id')
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'phone', 'district_id', 'hub_id', 'is_active', 'avatar_path']);

        // Pre-count CFA counts by district & by referral_user_id (all scoped to FY if given).
        $cfaCountByDistrict = $this->cfaCountByDistrict($activeFy?->id);
        $cfaCountByStaff = $this->cfaCountByStaff($activeFy?->id);
        $cfaUnassignedByDistrict = $this->cfaCountUnassignedByDistrict($activeFy?->id);

        $stateTotal = $cfaCountByDistrict->sum();

        // Build nested structure
        $districtsByHub = $districts->groupBy('hub_id');
        $staffByDistrict = $staffUsers->groupBy('district_id');

        $hubNodes = [];
        foreach ($hubs as $hub) {
            $hubDistricts = $districtsByHub->get($hub->id, collect());
            $districtNodes = [];
            $hubTotal = 0;

            foreach ($hubDistricts as $district) {
                $districtCount = (int) ($cfaCountByDistrict[$district->id] ?? 0);
                $hubTotal += $districtCount;

                $districtStaff = $staffByDistrict->get($district->id, collect());
                $staffNodes = [];
                $staffAssignedSum = 0;

                foreach ($districtStaff as $staff) {
                    $count = (int) ($cfaCountByStaff[$staff->id] ?? 0);
                    $staffAssignedSum += $count;
                    $staffNodes[] = [
                        'id' => $staff->id,
                        'name' => $staff->name,
                        'email' => $staff->email,
                        'phone' => $staff->phone,
                        'is_active' => (bool) $staff->is_active,
                        'avatar_url' => $staff->avatarUrl(),
                        'cfa_count' => $count,
                        'pct_of_district' => $districtCount > 0 ? round($count / $districtCount * 100, 1) : 0.0,
                    ];
                }

                // Sort staff by cfa_count desc for relevance
                usort($staffNodes, fn ($a, $b) => $b['cfa_count'] <=> $a['cfa_count']);

                $unassigned = (int) ($cfaUnassignedByDistrict[$district->id] ?? 0);
                // Also account for staff referrals where referral_user_id points to a user
                // who is NOT in this district's staff list (defensive).
                $leftover = $districtCount - $staffAssignedSum - $unassigned;
                if ($leftover < 0) {
                    $leftover = 0;
                }
                // Merge leftover into unassigned bucket
                $unassigned += $leftover;

                $districtNodes[] = [
                    'id' => $district->id,
                    'name' => $district->name,
                    'cfa_count' => $districtCount,
                    'pct_of_hub' => 0.0, // filled after hubTotal known
                    'staff' => $staffNodes,
                    'unassigned_count' => $unassigned,
                ];
            }

            // Fill pct_of_hub
            foreach ($districtNodes as &$dn) {
                $dn['pct_of_hub'] = $hubTotal > 0 ? round($dn['cfa_count'] / $hubTotal * 100, 1) : 0.0;
            }
            unset($dn);

            // Sort districts by count desc
            usort($districtNodes, fn ($a, $b) => $b['cfa_count'] <=> $a['cfa_count']);

            $hubNodes[] = [
                'id' => $hub->id,
                'name' => $hub->name,
                'cfa_count' => $hubTotal,
                'pct_of_state' => $stateTotal > 0 ? round($hubTotal / $stateTotal * 100, 1) : 0.0,
                'districts' => $districtNodes,
                'district_count' => count($districtNodes),
                'staff_count' => array_sum(array_map(fn ($d) => count($d['staff']), $districtNodes)),
            ];
        }

        // Sort hubs by count desc (visual prominence)
        usort($hubNodes, fn ($a, $b) => $b['cfa_count'] <=> $a['cfa_count']);

        return [
            'activeFy' => $activeFy,
            'fiscalYears' => $fiscalYears,
            'stateTotal' => $stateTotal,
            'totalHubs' => count($hubNodes),
            'totalDistricts' => $districts->count(),
            'totalStaff' => $staffUsers->count(),
            'hubs' => $hubNodes,
        ];
    }

    /**
     * @return Collection<int,int>   keyed by district_id => count
     */
    private function cfaCountByDistrict(?int $fyId): Collection
    {
        $q = CfaSubmission::query();
        if ($fyId) {
            $q->where('fiscal_year_id', $fyId);
        }

        return $q->selectRaw('district_id, COUNT(*) as total')
            ->groupBy('district_id')
            ->pluck('total', 'district_id');
    }

    /**
     * @return Collection<int,int>   keyed by referral_user_id => count
     */
    private function cfaCountByStaff(?int $fyId): Collection
    {
        $q = CfaSubmission::query()->whereNotNull('referral_user_id');
        if ($fyId) {
            $q->where('fiscal_year_id', $fyId);
        }

        return $q->selectRaw('referral_user_id, COUNT(*) as total')
            ->groupBy('referral_user_id')
            ->pluck('total', 'referral_user_id');
    }

    /**
     * @return Collection<int,int>   keyed by district_id => count of rows with no referral_user_id
     */
    private function cfaCountUnassignedByDistrict(?int $fyId): Collection
    {
        $q = CfaSubmission::query()->whereNull('referral_user_id');
        if ($fyId) {
            $q->where('fiscal_year_id', $fyId);
        }

        return $q->selectRaw('district_id, COUNT(*) as total')
            ->groupBy('district_id')
            ->pluck('total', 'district_id');
    }
}
