<?php

namespace App\Http\Controllers;

use App\Models\DistrictServiceSpoc;
use App\Services\HubAdminDashboardService;
use App\Services\SpocApprovalQueueStatsService;
use App\Services\StaffDashboardService;
use App\Services\StateAdminDashboardService;
use App\Support\StateAdminTheme;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(
        Request $request,
        StateAdminDashboardService $stateDashboard,
        HubAdminDashboardService $hubDashboard,
        StaffDashboardService $staffDashboard,
        SpocApprovalQueueStatsService $spocQueueStats,
    ): View|RedirectResponse {
        $user = auth()->user();

        if ($user?->role === 'incubatee') {
            return redirect()->route('incubatee.dashboard');
        }

        if ($user?->role === 'state_admin') {
            return view('dashboards.state-admin', array_merge($stateDashboard->metrics(), [
                'dashboardTheme' => StateAdminTheme::resolve($request),
            ]));
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

            $stats = $spocQueueStats->forSpoc($user);

            return view('dashboards.state-staff', [
                'user' => $user,
                'spocDistricts' => $spocDistricts,
                'pendingApprovals' => $stats['pending'],
                'overduePending' => $stats['overdue'],
                'approvedByYou' => $stats['approved'],
                'canSubmitSocialMediaPost' => \App\Support\SocialMediaPostAccess::canSubmit($user),
            ]);
        }

        if ($user?->role === 'hub_admin') {
            return view('dashboards.hub-admin', array_merge($hubDashboard->metrics($user), [
                'dashboardTheme' => StateAdminTheme::resolve($request),
            ]));
        }

        if ($user?->role === 'district_staff') {
            return view('dashboards.staff', array_merge($staffDashboard->metrics($user), [
                'dashboardTheme' => StateAdminTheme::resolve($request),
            ]));
        }

        abort(403, 'No dashboard for this account type.');
    }

}
