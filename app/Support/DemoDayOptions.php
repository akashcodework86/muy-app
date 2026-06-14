<?php

namespace App\Support;

final class DemoDayOptions
{
    /** @return array<string, string> */
    public static function eventTypes(): array
    {
        return [
            'workshop' => 'Workshop',
            'investor_meet' => 'Investor meet',
            'other' => 'Other',
        ];
    }

    /** @return array<string, string> */
    public static function modes(): array
    {
        return config('funding_schematic_convergence.event_modes', []);
    }

    /** @return array<string, string> */
    public static function outcomes(): array
    {
        return config('funding_schematic_convergence.event_outcomes', []);
    }

    public static function eventTypeLabel(string $value, ?string $other = null): string
    {
        if ($value === 'other') {
            $other = trim((string) $other);

            return $other !== '' ? $other : 'Other';
        }

        return self::eventTypes()[$value] ?? $value;
    }

    public static function modeLabel(?string $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        return self::modes()[$value] ?? $value;
    }

    public static function outcomeLabel(?string $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        return self::outcomes()[$value] ?? $value;
    }
}
