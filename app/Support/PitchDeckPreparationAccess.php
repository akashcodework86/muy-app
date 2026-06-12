<?php

namespace App\Support;

use App\Models\PitchDeckPreparation;
use App\Models\User;

final class PitchDeckPreparationAccess
{
    public static function canSubmit(?User $user): bool
    {
        if (! $user || $user->role !== 'state_staff') {
            return false;
        }

        $ids = config('pitch_deck_preparations.submitter_user_ids', []);
        if (is_array($ids) && $ids !== [] && in_array((int) $user->id, $ids, true)) {
            return true;
        }

        $names = config('pitch_deck_preparations.submitter_names', []);
        if (! is_array($names) || $names === []) {
            return false;
        }

        $normalized = self::normalizeName((string) $user->name);

        foreach ($names as $allowed) {
            if ($normalized === self::normalizeName((string) $allowed)) {
                return true;
            }
        }

        return false;
    }

    public static function canViewDashboard(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->role === 'state_admin') {
            return true;
        }

        return self::canSubmit($user);
    }

    public static function canEdit(?User $user, PitchDeckPreparation $row): bool
    {
        if (! $user || ! self::canSubmit($user)) {
            return false;
        }

        return (int) $row->entered_by_user_id === (int) $user->id;
    }

    public static function canDelete(?User $user, PitchDeckPreparation $row): bool
    {
        if ($user?->role === 'state_admin') {
            return true;
        }

        return self::canEdit($user, $row);
    }

    private static function normalizeName(string $name): string
    {
        $name = trim(preg_replace('/\s+/u', ' ', $name) ?? '');

        return function_exists('mb_strtolower') ? mb_strtolower($name) : strtolower($name);
    }
}
