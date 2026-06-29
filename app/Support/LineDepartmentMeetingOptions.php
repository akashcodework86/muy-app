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

    /** @return array<string, string> */
    public static function departmentNames(): array
    {
        return [
            'Agriculture' => 'Agriculture',
            'Tourism' => 'Tourism',
            'Rural Development' => 'Rural Development',
            'USRLM' => 'USRLM',
            'MSME / Industry' => 'MSME / Industry',
            'Horticulture' => 'Horticulture',
            'Animal Husbandry' => 'Animal Husbandry',
            'Forest' => 'Forest',
            'Education' => 'Education',
            'Women and Child Development' => 'Women and Child Development',
            'Commerce and Industry' => 'Commerce and Industry',
            'REAP' => 'REAP',
            'District Administration' => 'District Administration',
            'Other' => 'Other',
        ];
    }

    public static function departmentNameLabel(string $key): string
    {
        return self::departmentNames()[$key] ?? $key;
    }
}
