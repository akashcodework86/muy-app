<?php

namespace App\Http\Controllers;

use App\Support\FundingSchematicConvergenceAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class FundingSchematicPartnerOutreachLandingController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        abort_unless(FundingSchematicConvergenceAccess::canViewDashboard($request->user()), 403);

        if ($request->user()?->role === 'state_staff' && FundingSchematicConvergenceAccess::canSubmit($request->user())) {
            return redirect()->route('spoc.funding-partners-outreach.create');
        }

        return redirect()->route(
            $request->user()?->role === 'state_admin'
                ? 'admin.funding-partners-outreach.dashboard'
                : 'spoc.funding-partners-outreach.dashboard'
        );
    }
}
