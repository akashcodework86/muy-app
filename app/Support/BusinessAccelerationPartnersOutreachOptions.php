<?php

namespace App\Support;

final class BusinessAccelerationPartnersOutreachOptions
{
    /** @return array<string, string> */
    public static function outreachModes(): array
    {
        return [
            'physical' => 'Physical',
            'virtual' => 'Virtual',
            'phone_email' => 'Phone / Email',
        ];
    }

    /** @return array<string, string> */
    public static function partnerTypes(): array
    {
        return [
            'corporate' => 'Corporate',
            'ecommerce' => 'E-commerce',
            'investor_vc' => 'Investor / VC',
            'industry_association' => 'Industry association',
            'govt_department' => 'Govt department',
            'ngo' => 'NGO',
            'bank_fi' => 'Bank / FI',
            'other' => 'Other',
        ];
    }

    public static function outreachModeLabel(string $value): string
    {
        return self::outreachModes()[$value] ?? $value;
    }

    public static function partnerTypeLabel(string $value, ?string $other = null): string
    {
        if ($value === 'other') {
            $other = trim((string) $other);

            return $other !== '' ? $other : 'Other';
        }

        return self::partnerTypes()[$value] ?? $value;
    }
}
