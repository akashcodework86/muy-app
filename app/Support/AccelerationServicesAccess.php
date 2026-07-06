<?php

namespace App\Support;

use App\Models\AccelerationServiceSession;
use App\Models\User;

final class AccelerationServicesAccess
{
    public static function canSubmit(?User $user): bool
    {
        if (! $user || $user->role !== 'state_staff') {
            return false;
        }

        $email = strtolower(trim((string) $user->email));
        if ($email === '') {
            return false;
        }

        $allowed = config('acceleration_services.submitter_emails', []);

        return in_array($email, $allowed, true);
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

    public static function canDelete(?User $user, AccelerationServiceSession $session): bool
    {
        if (! self::canSubmit($user)) {
            return false;
        }

        return (int) $session->submitted_by_user_id === (int) $user->id;
    }
}
