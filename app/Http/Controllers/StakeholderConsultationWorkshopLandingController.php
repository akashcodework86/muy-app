<?php

namespace App\Http\Controllers;

use App\Support\StakeholderConsultationWorkshopAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class StakeholderConsultationWorkshopLandingController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        abort_unless(StakeholderConsultationWorkshopAccess::canViewDashboard($request->user()), 403);

        if ($request->user()?->role === 'state_staff' && StakeholderConsultationWorkshopAccess::canSubmit($request->user())) {
            return redirect()->route('spoc.stakeholder-consultation-workshops.create');
        }

        if ($request->user()?->role === 'state_admin') {
            return redirect()->route('admin.stakeholder-consultation-workshops.dashboard');
        }

        return redirect()->route('spoc.stakeholder-consultation-workshops.dashboard');
    }
}
