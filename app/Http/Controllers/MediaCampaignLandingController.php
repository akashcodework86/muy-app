<?php

namespace App\Http\Controllers;

use App\Support\BrandingCommunicationAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class MediaCampaignLandingController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        abort_unless(BrandingCommunicationAccess::canViewDashboard($request->user()), 403);

        if ($request->user()?->role === 'state_staff' && BrandingCommunicationAccess::canSubmit($request->user())) {
            return redirect()->route('spoc.media-campaigns.create');
        }

        if ($request->user()?->role === 'state_admin') {
            return redirect()->route('admin.media-campaigns.dashboard');
        }

        return redirect()->route('spoc.media-campaigns.dashboard');
    }
}
