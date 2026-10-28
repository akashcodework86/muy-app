<?php

namespace App\Support;

use App\Models\FiscalYear;
use Carbon\Carbon;
use Closure;

/** Date must fall within the currently active fiscal year row. */
final class ActiveFiscalYearDate
{
    public static function activeFiscalYear(): ?FiscalYear
    {
        return FiscalYear::query()->where('is_active', true)->orderByDesc('starts_on')->first();
    }

    public static function isWithinActiveFiscalYear(?string $date): bool
    {
        $fy = self::activeFiscalYear();
        if ($fy === null || $date === null || $date === '') {
            return false;
        }

        try {
            $parsed = Carbon::parse(substr($date, 0, 10))->startOfDay();
        } catch (\Throwable) {
            return false;
        }

        $start = $fy->starts_on?->copy()->startOfDay();
        $end = $fy->ends_on?->copy()->startOfDay();
        if ($start === null || $end === null) {
            return false;
        }

        return $parsed->betweenIncluded($start, $end);
    }

    /**
     * @return list<string|Closure>
     */
    public static function rules(bool $required = true): array
    {
        return array_values(array_filter([
            $required ? 'required' : 'nullable',
            'date',
            function (string $attribute, mixed $value, Closure $fail): void {
                if ($value === null || $value === '') {
                    return;
                }

                if (self::isWithinActiveFiscalYear((string) $value)) {
                    return;
                }

                $fy = self::activeFiscalYear();
                $label = $fy?->code ?? 'active financial year';
                $fail('The :attribute must fall within the '.$label.' period.');
            },
        ]));
    }

    /**
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

                if (self::isWithinActiveFiscalYear($date)) {
                    return;
                }

                if ($existing !== null && $date === $existing) {
                    return;
                }

                $fy = self::activeFiscalYear();
                $label = $fy?->code ?? 'active financial year';
                $fail('The :attribute must fall within the '.$label.' period.');
            },
        ]));
    }
}
