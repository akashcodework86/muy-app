<?php

namespace App\Http\Controllers;

use App\Models\DistrictServiceSpoc;
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

            return view('dashboards.state-staff', [
                'user' => $user,
                'spocDistricts' => $spocDistricts,
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
