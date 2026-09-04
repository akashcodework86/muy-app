<?php

namespace App\Support;

final class IncubateeLoginPhone
{
    /**
     * Normalize to a 10-digit Indian mobile, or empty string if not valid.
     */
    public static function normalize(mixed $raw): string
    {
        $digits = preg_replace('/\D+/', '', (string) $raw) ?? '';
        if ($digits === '') {
            return '';
        }

        if (strlen($digits) === 12 && str_starts_with($digits, '91')) {
            $digits = substr($digits, 2);
        } elseif (strlen($digits) === 11 && str_starts_with($digits, '0')) {
            $digits = substr($digits, 1);
        }

        if (! self::isValid($digits)) {
            return '';
        }

        return $digits;
    }

    public static function isValid(string $digits): bool
    {
        return (bool) preg_match('/^[6-9]\d{9}$/', $digits);
    }

    public static function fromInput(mixed $raw): ?string
    {
        $normalized = self::normalize($raw);

        return $normalized !== '' ? $normalized : null;
    }
}
