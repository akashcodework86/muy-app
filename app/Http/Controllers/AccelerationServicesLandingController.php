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

        $prefix = AccelerationServicesAccess::routePrefixForUser($request->user());

        if (AccelerationServicesAccess::canSubmit($request->user())) {
            return redirect()->route($prefix.'acceleration-services.create');
        }

        return redirect()->route($prefix.'acceleration-services.dashboard');
    }
}
