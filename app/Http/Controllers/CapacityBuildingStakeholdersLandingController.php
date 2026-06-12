<?php

namespace App\Http\Controllers;

use App\Support\CapacityBuildingStakeholdersAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CapacityBuildingStakeholdersLandingController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        abort_unless(CapacityBuildingStakeholdersAccess::canViewDashboard($request->user()), 403);

        if ($request->user()?->role === 'state_staff' && CapacityBuildingStakeholdersAccess::canSubmit($request->user())) {
            return redirect()->route('spoc.capacity-building-stakeholders.create');
        }

        if ($request->user()?->role === 'state_admin') {
            return redirect()->route('admin.capacity-building-stakeholders.dashboard');
        }

        return redirect()->route('spoc.capacity-building-stakeholders.dashboard');
    }
}
