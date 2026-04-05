<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureDistrictStaff
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->role !== 'district_staff') {
            abort(403);
        }

        return $next($request);
    }
}
