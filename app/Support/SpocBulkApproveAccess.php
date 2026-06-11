<?php

namespace App\Support;

use App\Models\User;

final class SpocBulkApproveAccess
{
    public static function canBulkApprove(?User $user): bool
    {
        if (! $user || $user->role !== 'state_staff') {
            return false;
        }

        $allowed = config('spoc_bulk_approve.allowed_emails', []);
        if ($allowed === []) {
            return false;
        }

        $email = strtolower(trim((string) $user->email));

        return in_array($email, $allowed, true);
    }
}
