<?php

namespace App\Support;

final class LineDepartmentMeetingOptions
{
    /** @return array<string, string> */
    public static function meetingModes(): array
    {
        return [
            'physical' => 'Physical',
            'virtual' => 'Virtual',
            'hybrid' => 'Hybrid',
        ];
    }

    public static function meetingModeLabel(string $key): string
    {
        return self::meetingModes()[$key] ?? ucfirst($key);
    }

    /** @return array<string, string> */
    public static function meetingPurposes(): array
    {
        return [
            'convergence' => 'Scheme convergence',
            'onboarding_support' => 'Onboarding support',
            'product_approval' => 'Product approval',
            'data_sharing' => 'Data sharing',
            'event_coordination' => 'Event coordination',
            'other' => 'Other',
        ];
    }
}
