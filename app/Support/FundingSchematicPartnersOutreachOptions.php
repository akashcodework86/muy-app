<?php

namespace App\Support;

final class FundingSchematicPartnersOutreachOptions
{
    /** @return array<string, string> */
    public static function outreachModes(): array
    {
        return [
            'call' => 'Call',
            'email' => 'Email',
            'virtual_meeting' => 'Virtual meeting',
            'in_person' => 'In-person meeting',
        ];
    }

    /** @return array<string, string> */
    public static function partnerTypes(): array
    {
        return [
            'investor' => 'Investor',
            'bank_nbfc' => 'Bank & NBFC',
            'govt_scheme' => 'Government scheme',
            'corporate_csr' => 'Corporate CSR',
            'angel_network' => 'Angel network',
            'accelerator' => 'Accelerator',
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
