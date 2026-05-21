<?php

namespace App\Http\Controllers;

use App\Support\SocialMediaPostAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SocialMediaPostLandingController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        abort_unless(SocialMediaPostAccess::canViewDashboard($request->user()), 403);

        if ($request->user()?->role === 'state_staff' && SocialMediaPostAccess::canSubmit($request->user())) {
            return redirect()->to(url('/spoc/social-media-posts/create'));
        }

        if ($request->user()?->role === 'state_admin') {
            return redirect()->to(url('/admin/social-media-posts/dashboard'));
        }

        return redirect()->to(url('/spoc/social-media-posts/dashboard'));
    }
}
