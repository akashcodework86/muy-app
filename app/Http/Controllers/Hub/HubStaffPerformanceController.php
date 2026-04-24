<?php

namespace App\Http\Controllers\Hub;

use App\Http\Controllers\Controller;
use App\Models\CfaSubmission;
use App\Models\District;
use App\Models\FiscalYear;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class HubStaffPerformanceController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $hubId = (int) $user->hub_id;

        $fiscalYears = FiscalYear::query()
            ->orderByDesc('starts_on')
            ->get(['id', 'name', 'is_active']);

        $selectedFyId = $request->integer('fy');
        if (! $selectedFyId) {
            $selectedFyId = (int) ($fiscalYears->firstWhere('is_active', true)?->id ?? 0);
        }

        $districts = District::query()
            ->where('hub_id', $hubId)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name']);

        $districtIds = $districts->pluck('id')->all();

        $staffAgg = User::query()
            ->where('role', 'district_staff')
            ->whereIn('district_id', $districtIds)
            ->selectRaw('district_id, COUNT(*) as total_staff, SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_staff')
            ->groupBy('district_id')
            ->get()
            ->keyBy('district_id');

        $fyCfaQuery = CfaSubmission::query()
            ->whereIn('district_id', $districtIds);
        if ($selectedFyId > 0) {
            $fyCfaQuery->where('fiscal_year_id', $selectedFyId);
        }
        $fyCfaByDistrict = $fyCfaQuery
            ->selectRaw('district_id, COUNT(*) as total')
            ->groupBy('district_id')
            ->pluck('total', 'district_id');

        $monthStart = now()->startOfMonth();
        $monthCfaByDistrict = CfaSubmission::query()
            ->whereIn('district_id', $districtIds)
            ->where('created_at', '>=', $monthStart)
            ->selectRaw('district_id, COUNT(*) as total')
            ->groupBy('district_id')
            ->pluck('total', 'district_id');

        $staffPerfQuery = CfaSubmission::query()
            ->join('users', 'users.id', '=', 'cfa_submissions.referral_user_id')
            ->where('users.role', 'district_staff')
            ->whereIn('users.district_id', $districtIds);
        if ($selectedFyId > 0) {
            $staffPerfQuery->where('cfa_submissions.fiscal_year_id', $selectedFyId);
        }
        $topStaffRows = $staffPerfQuery
            ->selectRaw('users.id as user_id, users.name as user_name, users.district_id, COUNT(cfa_submissions.id) as cfa_count')
            ->groupBy('users.id', 'users.name', 'users.district_id')
            ->orderByDesc('cfa_count')
            ->get();

        $topStaffByDistrict = [];
        foreach ($topStaffRows as $row) {
            $dId = (int) $row->district_id;
            if (! isset($topStaffByDistrict[$dId])) {
                $topStaffByDistrict[$dId] = [
                    'name' => (string) $row->user_name,
                    'count' => (int) $row->cfa_count,
                ];
            }
        }

        $rows = $districts->map(function (District $district) use ($staffAgg, $fyCfaByDistrict, $monthCfaByDistrict, $topStaffByDistrict): array {
            $dId = (int) $district->id;
            $staffRow = $staffAgg->get($dId);
            $totalStaff = (int) ($staffRow->total_staff ?? 0);
            $activeStaff = (int) ($staffRow->active_staff ?? 0);
            $fyCfa = (int) ($fyCfaByDistrict[$dId] ?? 0);
            $monthCfa = (int) ($monthCfaByDistrict[$dId] ?? 0);
            $perStaffAvg = $activeStaff > 0 ? round($fyCfa / $activeStaff, 2) : 0.0;

            return [
                'district_id' => $dId,
                'district_name' => $district->name,
                'total_staff' => $totalStaff,
                'active_staff' => $activeStaff,
                'fy_cfa' => $fyCfa,
                'month_cfa' => $monthCfa,
                'per_staff_avg' => $perStaffAvg,
                'top_staff' => $topStaffByDistrict[$dId] ?? null,
            ];
        });

        return view('hub.staff-performance.index', [
            'rows' => $rows,
            'fiscalYears' => $fiscalYears,
            'selectedFyId' => $selectedFyId,
            'hubName' => (string) DB::table('hubs')->where('id', $hubId)->value('name'),
        ]);
    }
}

