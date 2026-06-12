<?php

namespace App\Http\Controllers;

use App\Support\BusinessAccelerationPartnersOutreachAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BusinessAccelerationPartnersOutreachLandingController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        abort_unless(BusinessAccelerationPartnersOutreachAccess::canViewDashboard($request->user()), 403);

        if ($request->user()?->role === 'state_staff' && BusinessAccelerationPartnersOutreachAccess::canSubmit($request->user())) {
            return redirect()->route('spoc.business-acceleration-partners-outreach.create');
        }

        if ($request->user()?->role === 'state_admin') {
            return redirect()->route('admin.business-acceleration-partners-outreach.dashboard');
        }

        return redirect()->route('spoc.business-acceleration-partners-outreach.dashboard');
    }
}
