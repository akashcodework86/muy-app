<?php

namespace App\Http\Controllers;

use App\Support\FundingSchematicConvergenceAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DemoDayLandingController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        abort_unless(FundingSchematicConvergenceAccess::canViewDashboard($request->user()), 403);

        if ($request->user()?->role === 'state_staff' && FundingSchematicConvergenceAccess::canSubmit($request->user())) {
            return redirect()->route('spoc.demo-days.create');
        }

        return redirect()->route(
            $request->user()?->role === 'state_admin'
                ? 'admin.demo-days.dashboard'
                : 'spoc.demo-days.dashboard'
        );
    }
}
