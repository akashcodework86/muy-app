<?php

namespace App\Support;

use Illuminate\Http\Request;

final class IncubateeLocale
{
    public const COOKIE = 'incubatee_locale';

    public const DEFAULT = 'hi';

    /** @var list<string> */
    public const ALLOWED = ['hi', 'en'];

    public static function fromRequest(?Request $request = null): string
    {
        $request ??= request();

        $fromSession = (string) $request->session()->get(self::COOKIE, '');
        if (in_array($fromSession, self::ALLOWED, true)) {
            return $fromSession;
        }

        $locale = (string) $request->cookie(self::COOKIE, '');
        if (in_array($locale, self::ALLOWED, true)) {
            return $locale;
        }

        return self::DEFAULT;
    }

    public static function apply(?Request $request = null): string
    {
        $locale = self::fromRequest($request);
        app()->setLocale($locale);

        return $locale;
    }

    public static function isHindi(?Request $request = null): bool
    {
        return self::fromRequest($request) === 'hi';
    }
}
