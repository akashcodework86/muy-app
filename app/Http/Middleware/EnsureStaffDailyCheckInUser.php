<?php

namespace App\Http\Middleware;

use App\Support\StaffDailyCheckInAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureStaffDailyCheckInUser
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! StaffDailyCheckInAccess::isRequired($request->user())) {
            abort(403, 'Daily attendance is not required for this account.');
        }

        return $next($request);
    }
}
