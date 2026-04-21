<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Support\Str;

/**
 * Turns a raw stored value from service_cases.payload into something
 * human-friendly for display: ₹1,23,456 for amounts, 21 Apr 2026 for
 * dates, a clickable link for URLs, comma-separated option labels for
 * multiselects, etc.
 *
 * Intended for Blade usage:
 *   {!! SchemaValueFormatter::renderHtml($field, $value) !!}
 *
 * `formatPlain()` returns text-only output (for exports / audit log).
 * `renderHtml()` returns raw HTML safe to output with {!! !!}.
 */
class SchemaValueFormatter
{
    /**
     * @param  array<string, mixed>  $field
     */
    public static function formatPlain(array $field, mixed $value): string
    {
        if ($value === null || $value === '' || $value === []) {
            return '—';
        }

        $type = $field['type'] ?? ServiceFieldTypes::TEXT;

        switch ($type) {
            case ServiceFieldTypes::AMOUNT:
                return '₹'.self::indianGrouping((float) $value);

            case ServiceFieldTypes::NUMBER:
                return is_numeric($value) ? (string) (0 + $value) : (string) $value;

            case ServiceFieldTypes::DATE:
                try {
                    return Carbon::parse($value)->format('d M Y');
                } catch (\Throwable) {
                    return (string) $value;
                }

            case ServiceFieldTypes::CHECKBOX:
                return $value ? 'Yes' : 'No';

            case ServiceFieldTypes::SELECT:
                return self::labelForOption($field, (string) $value);

            case ServiceFieldTypes::MULTISELECT:
                if (! is_array($value)) {
                    return (string) $value;
                }
                $labels = array_map(fn ($v) => self::labelForOption($field, (string) $v), $value);

                return implode(', ', array_filter($labels, fn ($l) => $l !== ''));

            case ServiceFieldTypes::URL:
            case ServiceFieldTypes::EMAIL:
            case ServiceFieldTypes::PHONE:
            case ServiceFieldTypes::TEXT:
            case ServiceFieldTypes::TEXTAREA:
            default:
                return is_scalar($value) ? (string) $value : json_encode($value);
        }
    }

    /**
     * @param  array<string, mixed>  $field
     */
    public static function renderHtml(array $field, mixed $value): string
    {
        if ($value === null || $value === '' || $value === []) {
            return '<span style="color:#94a3b8;">—</span>';
        }

        $type = $field['type'] ?? ServiceFieldTypes::TEXT;

        switch ($type) {
            case ServiceFieldTypes::URL:
                $url = (string) $value;
                $safeUrl = e($url);
                $display = Str::limit($url, 60);

                return '<a href="'.$safeUrl.'" target="_blank" rel="noopener noreferrer" style="color:#4338ca; text-decoration:underline; word-break:break-all;">'.e($display).'</a>';

            case ServiceFieldTypes::EMAIL:
                $email = (string) $value;

                return '<a href="mailto:'.e($email).'" style="color:#4338ca;">'.e($email).'</a>';

            case ServiceFieldTypes::PHONE:
                $phone = (string) $value;

                return '<a href="tel:+91'.e($phone).'" style="color:#4338ca;">+91 '.e($phone).'</a>';

            case ServiceFieldTypes::AMOUNT:
                return '<span style="font-variant-numeric:tabular-nums; font-weight:600;">₹'.e(self::indianGrouping((float) $value)).'</span>';

            case ServiceFieldTypes::TEXTAREA:
                return '<span style="white-space:pre-wrap;">'.e(self::formatPlain($field, $value)).'</span>';

            default:
                return e(self::formatPlain($field, $value));
        }
    }

    /**
     * @param  array<string, mixed>  $field
     */
    private static function labelForOption(array $field, string $value): string
    {
        foreach ((array) ($field['options'] ?? []) as $opt) {
            if (is_array($opt) && (string) ($opt['value'] ?? '') === $value) {
                return (string) ($opt['label'] ?? $value);
            }
        }

        return $value;
    }

    /**
     * Indian place-value comma grouping: 1234567.89 -> "12,34,567.89".
     * Handles negatives and decimals; no external i18n dependency.
     */
    public static function indianGrouping(float $amount): string
    {
        $negative = $amount < 0;
        $amount = abs($amount);
        $parts = explode('.', rtrim(rtrim(number_format($amount, 2, '.', ''), '0'), '.'));
        $intPart = $parts[0] !== '' ? $parts[0] : '0';
        $decPart = $parts[1] ?? '';

        $last3 = substr($intPart, -3);
        $rest = substr($intPart, 0, -3);
        if ($rest !== '') {
            $rest = preg_replace('/(\d)(?=(\d\d)+$)/', '$1,', $rest);
            $grouped = $rest.','.$last3;
        } else {
            $grouped = $last3;
        }

        $out = $grouped;
        if ($decPart !== '') {
            $out .= '.'.$decPart;
        }

        return ($negative ? '-' : '').$out;
    }
}
