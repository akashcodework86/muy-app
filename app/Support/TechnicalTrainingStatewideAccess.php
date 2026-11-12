<?php

namespace App\Support;

use App\Models\User;

final class TechnicalTrainingStatewideAccess
{
    public static function allows(?User $user): bool
    {
        if (! $user || ! $user->is_active || $user->role !== 'state_staff') {
            return false;
        }

        $email = strtolower(trim((string) $user->email));
        if ($email === '') {
            return false;
        }

        $allowed = config('technical_training.statewide_submitter_emails', []);
        if (! is_array($allowed)) {
            return false;
        }

        foreach ($allowed as $candidate) {
            if ($email === strtolower(trim((string) $candidate))) {
                return true;
            }
        }

        return false;
    }

    public static function submittedByLabel(User $user): string
    {
        return 'stateadmin('.trim((string) $user->name).')';
    }
}
