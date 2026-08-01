<?php

namespace App\Support;

use Carbon\Carbon;
use Closure;

/**
 * Activity dates on MIS entry forms: any day within the current calendar month.
 */
final class TodayOnlyDate
{
    public static function today(): string
    {
        return now()->toDateString();
    }

    public static function monthStart(): string
    {
        return now()->copy()->startOfMonth()->toDateString();
    }

    public static function monthEnd(): string
    {
        return now()->copy()->endOfMonth()->toDateString();
    }

    public static function isInCurrentMonth(?string $date): bool
    {
        if ($date === null || $date === '') {
            return false;
        }

        try {
            $parsed = Carbon::parse(substr($date, 0, 10))->startOfDay();
        } catch (\Throwable) {
            return false;
        }

        return $parsed->betweenIncluded(
            now()->copy()->startOfMonth()->startOfDay(),
            now()->copy()->endOfMonth()->startOfDay(),
        );
    }

    /**
     * Validation for a new entry (create / submit).
     *
     * @return list<string>
     */
    public static function rules(bool $required = true): array
    {
        return array_values(array_filter([
            $required ? 'required' : 'nullable',
            'date',
            'after_or_equal:'.self::monthStart(),
            'before_or_equal:'.self::monthEnd(),
        ]));
    }

    /**
     * Validation for updates: any date in the current month, or keep the existing stored date.
     *
     * @return list<string|Closure>
     */
    public static function rulesAllowingExisting(?string $existingDate, bool $required = true): array
    {
        $existing = $existingDate !== null && $existingDate !== ''
            ? substr($existingDate, 0, 10)
            : null;

        return array_values(array_filter([
            $required ? 'required' : 'nullable',
            'date',
            function (string $attribute, mixed $value, Closure $fail) use ($existing): void {
                if ($value === null || $value === '') {
                    return;
                }

                $date = substr((string) $value, 0, 10);

                if (TodayOnlyDate::isInCurrentMonth($date)) {
                    return;
                }

                if ($existing !== null && $date === $existing) {
                    return;
                }

                $fail('The :attribute must be a date in the current month.');
            },
        ]));
    }
}
