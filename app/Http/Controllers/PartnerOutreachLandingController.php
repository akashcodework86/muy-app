<?php

namespace App\Http\Controllers;

use App\Support\PartnerOutreachAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PartnerOutreachLandingController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        abort_unless(PartnerOutreachAccess::canViewDashboard($request->user()), 403);

        if ($request->user()?->role === 'state_staff' && PartnerOutreachAccess::canSubmit($request->user())) {
            return redirect()->route('spoc.partner-outreach.create');
        }

        if ($request->user()?->role === 'state_admin') {
            return redirect()->route('admin.partner-outreach.dashboard');
        }

        return redirect()->route('spoc.partner-outreach.dashboard');
    }
}
