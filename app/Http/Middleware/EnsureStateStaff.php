<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureStateStaff
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->role !== 'state_staff') {
            abort(403);
        }

        return $next($request);
    }
}
