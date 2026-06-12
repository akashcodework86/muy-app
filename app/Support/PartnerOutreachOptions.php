<?php

namespace App\Support;

use App\Models\MarketingPartnerOutreachEntry;

final class PartnerOutreachOptions
{
    /**
     * @return array<string, string>
     */
    public static function cohortOrSectors(): array
    {
        return [
            'food_processing' => 'Food processing',
            'handloom_handicraft' => 'Handloom & handicraft',
            'agriculture_horticulture' => 'Agriculture & horticulture',
            'tourism_hospitality' => 'Tourism & hospitality',
            'health_wellness' => 'Health & wellness',
            'technology' => 'Technology / IT',
            'retail_trade' => 'Retail & trade',
            'services' => 'Services',
            'cohort_1' => 'Cohort 1',
            'cohort_2' => 'Cohort 2',
            'cohort_3' => 'Cohort 3',
            'other' => 'Other',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function statuses(): array
    {
        return [
            MarketingPartnerOutreachEntry::STATUS_OUTREACH => 'Outreach logged',
            MarketingPartnerOutreachEntry::STATUS_IN_DISCUSSION => 'In discussion',
            MarketingPartnerOutreachEntry::STATUS_ONBOARDED_LOA => 'Onboarded — LoA',
            MarketingPartnerOutreachEntry::STATUS_ONBOARDED_LOI => 'Onboarded — LoI',
            MarketingPartnerOutreachEntry::STATUS_ONBOARDED_MOU => 'Onboarded — MoU',
            MarketingPartnerOutreachEntry::STATUS_DECLINED => 'Declined',
        ];
    }

    public static function statusLabel(string $status): string
    {
        return self::statuses()[$status] ?? ucfirst(str_replace('_', ' ', $status));
    }

    public static function cohortOrSectorDisplay(string $value, ?string $other = null): string
    {
        if ($value === 'other') {
            $other = trim((string) $other);

            return $other !== '' ? $other : 'Other';
        }

        return self::cohortOrSectors()[$value] ?? $value;
    }

    public static function statusBadgeClass(string $status): string
    {
        return match ($status) {
            MarketingPartnerOutreachEntry::STATUS_ONBOARDED_LOA,
            MarketingPartnerOutreachEntry::STATUS_ONBOARDED_LOI,
            MarketingPartnerOutreachEntry::STATUS_ONBOARDED_MOU => 'mpo-badge--onboarded',
            MarketingPartnerOutreachEntry::STATUS_IN_DISCUSSION => 'mpo-badge--discussion',
            MarketingPartnerOutreachEntry::STATUS_DECLINED => 'mpo-badge--declined',
            default => 'mpo-badge--outreach',
        };
    }
}
