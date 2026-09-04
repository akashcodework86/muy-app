<?php

namespace App\Support;

use Illuminate\Http\Request;

final class AuthLoginAudience
{
    public static function guestRoute(Request $request): string
    {
        return 'login';
    }

    public static function guestUrl(Request $request): string
    {
        return route(self::guestRoute($request));
    }

    public static function isIncubateeRequest(Request $request): bool
    {
        if ($request->routeIs('incubatee.*')) {
            return true;
        }

        return $request->is('portal', 'incubatee', 'incubatee/*');
    }
}
