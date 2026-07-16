<?php

namespace App\Support;

use App\Models\AccelerationServiceSession;
use App\Models\User;

final class AccelerationServicesAccess
{
    /**
     * Full-form submitters (Ankur) — allowlisted state staff emails.
     */
    public static function canSubmitFullForm(?User $user): bool
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

    /**
     * District staff may submit In-house service details only.
     */
    public static function canSubmitInHouseOnly(?User $user): bool
    {
        return $user?->role === 'district_staff'
            && (int) ($user->district_id ?? 0) > 0;
    }

    public static function canSubmit(?User $user): bool
    {
        return self::canSubmitFullForm($user) || self::canSubmitInHouseOnly($user);
    }

    public static function canViewDashboard(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->role === 'state_admin') {
            return true;
        }

        return self::canSubmit($user) || AccelerationServicesApproval::isApprover($user);
    }

    public static function canDelete(?User $user, AccelerationServiceSession $session): bool
    {
        if (! self::canSubmit($user)) {
            return false;
        }

        // Approved entries are locked — nobody deletes them from the UI.
        if ($session->isLocked()) {
            return false;
        }

        return (int) $session->submitted_by_user_id === (int) $user->id;
    }

    /**
     * @return list<string>
     */
    public static function allowedSections(?User $user): array
    {
        if (self::canSubmitFullForm($user)) {
            return [
                AccelerationServicesOptions::SECTION_SERVICE_DETAIL,
                AccelerationServicesOptions::SECTION_CROSS_CUTTING,
                AccelerationServicesOptions::SECTION_PARTNERSHIP,
            ];
        }

        if (self::canSubmitInHouseOnly($user)) {
            return [AccelerationServicesOptions::SECTION_SERVICE_DETAIL];
        }

        return [];
    }

    public static function isInHouseOnlySubmitter(?User $user): bool
    {
        return self::canSubmitInHouseOnly($user) && ! self::canSubmitFullForm($user);
    }

    /**
     * Named-route prefix including trailing dot (staff. / spoc. / admin.).
     */
    public static function routePrefixForUser(?User $user): string
    {
        return match ($user?->role) {
            'district_staff' => 'staff.',
            'state_admin' => 'admin.',
            default => 'spoc.',
        };
    }
}
