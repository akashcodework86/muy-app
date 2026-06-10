<?php

namespace App\Support;

use App\Models\User;

final class TrainingPackageMonthPlanAccess
{
    /**
     * @return list<string>
     */
    public static function managerEmails(): array
    {
        $emails = config('muy.training_package_month_plan_managers', []);

        return array_values(array_filter(array_map(
            fn (mixed $email): string => strtolower(trim((string) $email)),
            is_array($emails) ? $emails : [],
        )));
    }

    public static function canManage(?User $user): bool
    {
        if (! $user || ! in_array($user->role, ['state_admin', 'state_staff'], true)) {
            return false;
        }

        $allowed = self::managerEmails();
        if ($allowed === []) {
            return false;
        }

        return in_array(strtolower(trim((string) $user->email)), $allowed, true);
    }
}
