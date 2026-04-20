<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Updates users.last_seen_at on authenticated requests.
 * Uses a short-lived cache flag to avoid a DB write on every request.
 */
class TrackUserPresence
{
    private const TTL_SECONDS = 30;

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $user = $request->user();
        if ($user === null) {
            return $response;
        }

        $userId = (int) $user->getAuthIdentifier();
        if ($userId <= 0) {
            return $response;
        }

        $key = 'presence:seen:'.$userId;
        if (Cache::has($key)) {
            return $response;
        }

        try {
            DB::table('users')
                ->where('id', $userId)
                ->update(['last_seen_at' => now()]);
            Cache::put($key, 1, self::TTL_SECONDS);
        } catch (\Throwable) {
            // Never block request cycle on presence failure.
        }

        return $response;
    }
}
