<?php

namespace App\Support;

use App\Models\LineDepartmentMeeting;
use App\Models\User;

final class LineDepartmentMeetingAccess
{
    /**
     * @return list<string>
     */
    public static function aadilEmails(): array
    {
        $emails = config('muy.line_department_meeting_aadil_submitters', []);

        return array_values(array_filter(array_map(
            fn (mixed $email): string => strtolower(trim((string) $email)),
            is_array($emails) ? $emails : [],
        )));
    }

    public static function isAadil(?User $user): bool
    {
        if (! $user || $user->role !== 'state_staff') {
            return false;
        }

        $allowed = self::aadilEmails();

        return $allowed !== []
            && in_array(strtolower(trim((string) $user->email)), $allowed, true);
    }

    public static function isIncubationManager(?User $user): bool
    {
        if (! $user || $user->role !== 'district_staff') {
            return false;
        }

        $user->loadMissing('designationRecord');
        $designation = strtolower(trim((string) ($user->designationRecord?->name ?? '')));

        return str_contains($designation, 'incubation manager');
    }

    public static function canSubmit(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        if (self::isAadil($user)) {
            return true;
        }

        if ($user->role === 'hub_admin' && (int) ($user->hub_id ?? 0) > 0) {
            return true;
        }

        return self::isIncubationManager($user) && (int) ($user->district_id ?? 0) > 0;
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

    public static function canEdit(?User $user, LineDepartmentMeeting $row): bool
    {
        if (! $user || ! self::canSubmit($user)) {
            return false;
        }

        return (int) $row->submitted_by_user_id === (int) $user->id;
    }

    public static function canDelete(?User $user, LineDepartmentMeeting $row): bool
    {
        if ($user?->role === 'state_admin') {
            return true;
        }

        return self::canEdit($user, $row);
    }

    public static function routePrefixForUser(?User $user): string
    {
        if (! $user) {
            return 'spoc.';
        }

        return match ($user->role) {
            'hub_admin' => 'hub.',
            'district_staff' => 'staff.',
            default => 'spoc.',
        };
    }
}
