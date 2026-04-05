<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureHubAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $u = $request->user();
        if ($u?->role !== 'hub_admin' || ! $u->hub_id) {
            abort(403);
        }

        return $next($request);
    }
}
