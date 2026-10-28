<?php

namespace App\Support;

class HubBatchImportNormalizer
{
    public static function phone(mixed $value): string
    {
        $digits = preg_replace('/\D+/', '', (string) $value) ?? '';

        return strlen($digits) > 10 ? substr($digits, -10) : $digits;
    }

    public static function applicationNo(mixed $value): string
    {
        return strtoupper(preg_replace('/\s+/', '', trim((string) $value)) ?? '');
    }

    /** @return list<string> */
    public static function applicationVariants(mixed $value): array
    {
        $raw = self::applicationNo($value);
        $variants = [];
        if (preg_match('/^[94]0C/', $raw)) {
            $variants[] = 'A0C'.substr($raw, 3);
        }
        if ($raw === '4080000C') {
            $variants[] = 'A080000C';
        }

        return array_values(array_unique(array_filter($variants, fn ($v) => $v !== $raw)));
    }

    public static function name(mixed $value): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/', ' ', (string) $value) ?? ''));
    }

    public static function header(mixed $value): string
    {
        return strtolower(preg_replace('/[^a-z0-9]+/i', '', trim((string) $value)) ?? '');
    }
}
