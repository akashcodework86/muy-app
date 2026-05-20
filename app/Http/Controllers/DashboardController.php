<?php

namespace App\Http\Controllers;

use App\Models\DistrictServiceSpoc;
use App\Models\ServiceCase;
use App\Services\HubAdminDashboardService;
use App\Services\StaffDashboardService;
use App\Services\StateAdminDashboardService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(
        StateAdminDashboardService $stateDashboard,
        HubAdminDashboardService $hubDashboard,
        StaffDashboardService $staffDashboard,
    ): View|RedirectResponse {
        $user = auth()->user();

        if ($user?->role === 'incubatee') {
            return redirect()->route('incubatee.dashboard');
        }

        if ($user?->role === 'state_admin') {
            return view('dashboards.state-admin', $stateDashboard->metrics());
        }

        if ($user?->role === 'state_staff') {
            $spocDistricts = DistrictServiceSpoc::query()
                ->where('state_staff_user_id', $user->id)
                ->with(['district:id,name,hub_id', 'district.hub:id,name'])
                ->get()
                ->map(fn ($a) => $a->district)
                ->filter()
                ->sortBy('name')
                ->values();

            $districtIds = $spocDistricts
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->filter(fn (int $id) => $id > 0)
                ->values()
                ->all();

            $queueBase = ServiceCase::query();
            if ($districtIds !== []) {
                $queueBase->whereHas('cfaSubmission', fn ($q) => $q->whereIn('district_id', $districtIds));
            } else {
                $queueBase->whereRaw('1 = 0');
            }

            $pendingApprovals = (clone $queueBase)
                ->where('status', ServiceCase::STATUS_PENDING_APPROVAL)
                ->count();

            $overduePending = (clone $queueBase)
                ->where('status', ServiceCase::STATUS_PENDING_APPROVAL)
                ->whereNotNull('sla_deadline_at')
                ->where('sla_deadline_at', '<', now())
                ->count();

            $approvedByYou = (clone $queueBase)
                ->where('status', ServiceCase::STATUS_APPROVED)
                ->where('approved_by', (int) $user->id)
                ->count();

            return view('dashboards.state-staff', [
                'user' => $user,
                'spocDistricts' => $spocDistricts,
                'pendingApprovals' => $pendingApprovals,
                'overduePending' => $overduePending,
                'approvedByYou' => $approvedByYou,
                'canSubmitSocialMediaPost' => \App\Support\SocialMediaPostAccess::canSubmit($user),
            ]);
        }

        if ($user?->role === 'hub_admin') {
            return view('dashboards.hub-admin', $hubDashboard->metrics($user));
        }

        if ($user?->role === 'district_staff') {
            return view('dashboards.staff', $staffDashboard->metrics($user));
        }

        abort(403, 'No dashboard for this account type.');
    }
}
