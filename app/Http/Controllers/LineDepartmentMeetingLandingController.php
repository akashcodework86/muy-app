<?php

namespace App\Http\Controllers;

use App\Support\LineDepartmentMeetingAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LineDepartmentMeetingLandingController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        abort_unless(LineDepartmentMeetingAccess::canViewDashboard($request->user()), 403);

        $user = $request->user();
        if ($user?->role === 'state_admin') {
            return redirect()->route('admin.line-department-meetings.dashboard');
        }

        if (LineDepartmentMeetingAccess::canSubmit($user)) {
            $prefix = LineDepartmentMeetingAccess::routePrefixForUser($user);

            return redirect()->route($prefix.'line-department-meetings.create');
        }

        $prefix = LineDepartmentMeetingAccess::routePrefixForUser($user);

        return redirect()->route($prefix.'line-department-meetings.dashboard');
    }
}
