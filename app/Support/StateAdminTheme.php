<?php

namespace App\Support;

use Illuminate\Http\Request;

class StateAdminTheme
{
    public const SESSION_KEY = 'state_admin_dashboard_theme';

    /** @var list<string> */
    public const ALLOWED = ['revamp', 'legacy'];

    public static function appliesToRole(?string $role): bool
    {
        return in_array($role, ['state_admin', 'hub_admin'], true);
    }

    public static function resolve(?Request $request = null): string
    {
        $request ??= request();

        if ($request !== null && $request->has('theme')) {
            $candidate = strtolower(trim((string) $request->query('theme', '')));
            if (in_array($candidate, self::ALLOWED, true)) {
                session([self::SESSION_KEY => $candidate]);

                return $candidate;
            }
        }

        $session = session(self::SESSION_KEY);
        if (is_string($session) && in_array($session, self::ALLOWED, true)) {
            return $session;
        }

        $configured = strtolower(trim((string) config('dashboard.state_admin_theme', 'revamp')));

        return in_array($configured, self::ALLOWED, true) ? $configured : 'revamp';
    }

    public static function toggleTheme(string $current): string
    {
        return $current === 'legacy' ? 'revamp' : 'legacy';
    }

    public static function toggleUrl(?Request $request = null, ?string $current = null): string
    {
        $request ??= request();
        $current ??= self::resolve($request);

        return $request->fullUrlWithQuery([
            'theme' => self::toggleTheme($current),
        ]);
    }
}
