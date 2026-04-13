<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAttendanceParticipant
{
    /**
     * District staff and hub admin must mark attendance; state admin is excluded.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user || ! in_array($user->role, ['district_staff', 'hub_admin'], true)) {
            abort(403);
        }

        return $next($request);
    }
}
