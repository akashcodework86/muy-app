<?php

namespace App\Http\Controllers;

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

        if ($user?->role === 'hub_admin') {
            return view('dashboards.hub-admin', $hubDashboard->metrics($user));
        }

        if ($user?->role === 'district_staff') {
            return view('dashboards.staff', $staffDashboard->metrics($user));
        }

        abort(403, 'No dashboard for this account type.');
    }
}
