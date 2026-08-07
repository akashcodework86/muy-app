<?php

namespace App\Support;

final class ApplicantCategoryShgSupport
{
    /** @var list<string> */
    private const CATEGORY_KEYS = [
        'category',
        'app_category',
        'applicant_category',
        'applicant_type',
        'applicant_category_type',
    ];

    /** @var list<string> */
    private const SHG_MEMBER_KEYS = [
        'is_member',
        'is_shg_member',
        'member_of_shg',
        'member_of_shg_cbo',
    ];

    public static function categoryLabel(array $payload, mixed $fallback = null): string
    {
        $value = self::firstMeaningfulValue($payload, self::CATEGORY_KEYS, $fallback);
        $label = trim((string) ($value ?? ''));
        if ($label === '') {
            return '';
        }

        $normalized = strtolower(str_replace([' ', '-'], '_', $label));

        return match ($normalized) {
            'individual', 'vyaktigat' => 'Individual',
            'shg', 'self_help_group' => 'SHG',
            'cbo', 'community_based_organization' => 'CBO',
            default => $label,
        };
    }

    public static function shgMemberLabel(array $payload, mixed $fallback = null): string
    {
        $value = self::firstMeaningfulValue($payload, self::SHG_MEMBER_KEYS, $fallback);
        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        if (is_int($value) || is_float($value)) {
            return (float) $value === 1.0 ? 'Yes' : ((float) $value === 0.0 ? 'No' : '');
        }

        $normalized = strtolower(trim((string) ($value ?? '')));

        return match ($normalized) {
            '1', 'yes', 'y', 'true' => 'Yes',
            '0', 'no', 'n', 'false' => 'No',
            default => '',
        };
    }

    /**
     * @param  list<string>  $keys
     */
    private static function firstMeaningfulValue(array $payload, array $keys, mixed $fallback): mixed
    {
        foreach ($keys as $key) {
            if (! array_key_exists($key, $payload)) {
                continue;
            }

            $value = $payload[$key];
            if ($value !== null && (! is_string($value) || trim($value) !== '')) {
                return $value;
            }
        }

        return $fallback;
    }
}
