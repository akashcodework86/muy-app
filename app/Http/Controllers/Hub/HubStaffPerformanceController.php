<?php

namespace App\Http\Controllers\Hub;

use App\Http\Controllers\Controller;
use App\Models\CfaSubmission;
use App\Models\District;
use App\Models\FiscalYear;
use App\Models\ServiceCase;
use App\Models\User;
use Illuminate\Support\Carbon;
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
            ->get(['id', 'name', 'starts_on', 'ends_on', 'is_active']);

        $selectedFyId = $request->integer('fy');
        if (! $selectedFyId) {
            $selectedFyId = (int) ($fiscalYears->firstWhere('is_active', true)?->id ?? 0);
        }
        $selectedFy = $fiscalYears->firstWhere('id', $selectedFyId);
        $fyStarts = $selectedFy?->starts_on ? Carbon::parse($selectedFy->starts_on)->startOfDay() : null;
        $fyEnds = $selectedFy?->ends_on ? Carbon::parse($selectedFy->ends_on)->endOfDay() : null;

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

        $staffUsers = User::query()
            ->where('role', 'district_staff')
            ->whereIn('district_id', $districtIds)
            ->orderBy('district_id')
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'phone', 'district_id', 'is_active']);
        $staffIds = $staffUsers->pluck('id')->all();

        $cfaByStaffFyQ = CfaSubmission::query()
            ->whereIn('referral_user_id', $staffIds);
        if ($selectedFyId > 0) {
            $cfaByStaffFyQ->where('fiscal_year_id', $selectedFyId);
        }
        $cfaByStaffFy = $cfaByStaffFyQ
            ->selectRaw('referral_user_id, COUNT(*) as total')
            ->groupBy('referral_user_id')
            ->pluck('total', 'referral_user_id');

        $cfaByStaffMonth = CfaSubmission::query()
            ->whereIn('referral_user_id', $staffIds)
            ->where('created_at', '>=', $monthStart)
            ->selectRaw('referral_user_id, COUNT(*) as total')
            ->groupBy('referral_user_id')
            ->pluck('total', 'referral_user_id');

        $serviceBase = ServiceCase::query()
            ->whereIn('created_by', $staffIds);
        if ($fyStarts && $fyEnds) {
            $serviceBase->whereBetween('created_at', [$fyStarts, $fyEnds]);
        }
        $serviceByStaff = $serviceBase
            ->selectRaw(
                "created_by, COUNT(*) as total_cases,
                SUM(CASE WHEN status='approved' THEN 1 ELSE 0 END) as approved_cases,
                SUM(CASE WHEN status='pending_approval' THEN 1 ELSE 0 END) as pending_cases,
                SUM(CASE WHEN status='sent_back' THEN 1 ELSE 0 END) as sent_back_cases,
                SUM(CASE WHEN status='rejected' THEN 1 ELSE 0 END) as rejected_cases"
            )
            ->groupBy('created_by')
            ->get()
            ->keyBy('created_by');

        $districtNameById = $districts->pluck('name', 'id');
        $staffRows = $staffUsers->map(function (User $staff) use ($districtNameById, $cfaByStaffFy, $cfaByStaffMonth, $serviceByStaff): array {
            $sid = (int) $staff->id;
            $cfaFy = (int) ($cfaByStaffFy[$sid] ?? 0);
            $cfaMonth = (int) ($cfaByStaffMonth[$sid] ?? 0);
            $svc = $serviceByStaff->get($sid);
            $serviceTotal = (int) ($svc->total_cases ?? 0);
            $serviceApproved = (int) ($svc->approved_cases ?? 0);
            $servicePending = (int) ($svc->pending_cases ?? 0);
            $serviceSentBack = (int) ($svc->sent_back_cases ?? 0);
            $serviceRejected = (int) ($svc->rejected_cases ?? 0);
            $approvalRate = $serviceTotal > 0 ? round(($serviceApproved / $serviceTotal) * 100, 1) : null;
            $performanceScore = ($cfaFy * 1.0) + ($serviceApproved * 1.5) + ($servicePending * 0.5);

            return [
                'staff_id' => $sid,
                'staff_name' => (string) $staff->name,
                'district_id' => (int) $staff->district_id,
                'district_name' => (string) ($districtNameById[$staff->district_id] ?? '—'),
                'email' => (string) ($staff->email ?? ''),
                'phone' => (string) ($staff->phone ?? ''),
                'is_active' => (bool) $staff->is_active,
                'cfa_fy' => $cfaFy,
                'cfa_month' => $cfaMonth,
                'service_total' => $serviceTotal,
                'service_approved' => $serviceApproved,
                'service_pending' => $servicePending,
                'service_sent_back' => $serviceSentBack,
                'service_rejected' => $serviceRejected,
                'service_approval_rate' => $approvalRate,
                'performance_score' => round($performanceScore, 1),
            ];
        })->sortByDesc('performance_score')->values();

        return view('hub.staff-performance.index', [
            'rows' => $rows,
            'staffRows' => $staffRows,
            'fiscalYears' => $fiscalYears,
            'selectedFyId' => $selectedFyId,
            'selectedFy' => $selectedFy,
            'hubName' => (string) DB::table('hubs')->where('id', $hubId)->value('name'),
        ]);
    }
}

