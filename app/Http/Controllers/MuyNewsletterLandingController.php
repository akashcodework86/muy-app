<?php

namespace App\Http\Controllers;

use App\Support\BrandingCommunicationAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class MuyNewsletterLandingController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        abort_unless(BrandingCommunicationAccess::canViewDashboard($request->user()), 403);

        if ($request->user()?->role === 'state_staff' && BrandingCommunicationAccess::canSubmit($request->user())) {
            return redirect()->route('spoc.muy-newsletters.create');
        }

        if ($request->user()?->role === 'state_admin') {
            return redirect()->route('admin.muy-newsletters.dashboard');
        }

        return redirect()->route('spoc.muy-newsletters.dashboard');
    }
}
