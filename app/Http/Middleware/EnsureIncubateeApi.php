<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureIncubateeApi
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user === null || $user->role !== 'incubatee') {
            return response()->json([
                'message' => 'Incubatee access only.',
            ], 403);
        }

        if (! $user->is_active) {
            $user->currentAccessToken()?->delete();

            return response()->json([
                'message' => 'This account has been disabled. Contact the administrator.',
            ], 403);
        }

        return $next($request);
    }
}
