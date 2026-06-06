<?php

namespace App\Support;

use Illuminate\Http\Request;

final class StateAdminDashboardTheme
{
    public const REVAMP = 'revamp';

    public const LEGACY = 'legacy';

    /**
     * @return self::REVAMP|self::LEGACY
     */
    public static function resolve(Request $request): string
    {
        if ($request->has('theme')) {
            $candidate = strtolower(trim((string) $request->query('theme', '')));
            if (in_array($candidate, [self::REVAMP, self::LEGACY], true)) {
                session(['state_admin_dashboard_theme' => $candidate]);

                return $candidate;
            }
        }

        $session = session('state_admin_dashboard_theme');
        if (is_string($session) && in_array($session, [self::REVAMP, self::LEGACY], true)) {
            return $session;
        }

        $configured = strtolower(trim((string) config('dashboard.state_admin_theme', self::REVAMP)));

        return in_array($configured, [self::REVAMP, self::LEGACY], true) ? $configured : self::REVAMP;
    }

    public static function toggleUrl(string $current): string
    {
        $next = $current === self::LEGACY ? self::REVAMP : self::LEGACY;

        return route('dashboard', ['theme' => $next]);
    }
}
