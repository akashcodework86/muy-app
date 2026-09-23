<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureReviewPptAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $role = $user?->role;
        if ($role === 'state_admin') {
            return $next($request);
        }
        if ($role === 'hub_admin' && $user->hub_id) {
            return $next($request);
        }

        abort(403);
    }
}
