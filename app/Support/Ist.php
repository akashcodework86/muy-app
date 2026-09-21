<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

final class Ist
{
    public const TZ = 'Asia/Kolkata';

    public static function carbon(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            $dt = $value instanceof CarbonInterface
                ? Carbon::instance($value)
                : Carbon::parse((string) $value);

            return $dt->timezone(self::TZ);
        } catch (\Throwable) {
            return null;
        }
    }

    public static function datetime(mixed $value, string $empty = '—'): string
    {
        $dt = self::carbon($value);
        if ($dt === null) {
            return $empty;
        }

        return $dt->locale(app()->getLocale())->translatedFormat('d M Y, h:i A').' IST';
    }

    public static function date(mixed $value, string $empty = '—'): string
    {
        $dt = self::carbon($value);
        if ($dt === null) {
            return $empty;
        }

        return $dt->locale(app()->getLocale())->translatedFormat('d M Y');
    }
}
