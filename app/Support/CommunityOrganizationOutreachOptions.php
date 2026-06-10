<?php

namespace App\Support;

final class CommunityOrganizationOutreachOptions
{
    /** @return array<string, string> */
    public static function organizationTypes(): array
    {
        return [
            'shg_federation' => 'SHG federation / federation of CBOs',
            'cbo' => 'CBO / community-based organization',
            'ngo' => 'NGO',
            'govt_department' => 'Government department',
            'bank_fi' => 'Bank / financial institution',
            'educational_institute' => 'Educational institute',
            'industry_association' => 'Industry / trade association',
            'other' => 'Other',
        ];
    }

    /** @return array<string, string> */
    public static function purposes(): array
    {
        return [
            'awareness' => 'Awareness / orientation',
            'partnership' => 'Partnership / MoU discussion',
            'referral' => 'Referral / linkage',
            'training_coordination' => 'Training coordination',
            'convergence' => 'Convergence / scheme coordination',
            'other' => 'Other',
        ];
    }

    /** @return array<string, string> */
    public static function meetingModes(): array
    {
        return [
            'physical' => 'In-person',
            'virtual' => 'Virtual',
            'phone' => 'Phone call',
        ];
    }

    /** @return array<string, string> */
    public static function outcomes(): array
    {
        return [
            'info_shared' => 'Information shared',
            'follow_up_planned' => 'Follow-up planned',
            'referral_received' => 'Referral received',
            'session_planned' => 'Session / activity planned',
            'partnership_discussed' => 'Partnership discussed',
            'no_outcome' => 'No outcome yet',
        ];
    }

    public static function labelFor(string $map, string $key): string
    {
        $options = match ($map) {
            'organization_type' => self::organizationTypes(),
            'purpose' => self::purposes(),
            'meeting_mode' => self::meetingModes(),
            'outcome' => self::outcomes(),
            default => [],
        };

        return $options[$key] ?? $key;
    }

    public static function organizationTypeDisplay(string $type, ?string $other = null): string
    {
        if ($type === 'other') {
            $custom = trim((string) $other);

            return $custom !== '' ? 'Other — '.$custom : 'Other';
        }

        return self::labelFor('organization_type', $type);
    }
}
