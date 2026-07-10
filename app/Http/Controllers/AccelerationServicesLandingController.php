<?php

namespace App\Http\Controllers;

use App\Support\AccelerationServicesAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AccelerationServicesLandingController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        abort_unless(AccelerationServicesAccess::canViewDashboard($request->user()), 403);

        if ($request->user()?->role === 'state_admin') {
            return redirect()->route('admin.acceleration-services.dashboard');
        }

        if (AccelerationServicesAccess::canSubmit($request->user())) {
            return redirect()->route('spoc.acceleration-services.create');
        }

        return redirect()->route('spoc.acceleration-services.dashboard');
    }
}
