<?php

namespace App\Support;

use App\Models\District;

/**
 * Hub-only deliverables on the official monthly plan (Almora + Pauri Garhwal lines).
 */
final class HubTargetDeliverablesSupport
{
    public const LABEL = 'HUB';

    /**
     * Hub monthly targets apply only on these district lines (not every spoke in the hub).
     *
     * @return list<string>
     */
    public static function primaryDistrictSlugs(): array
    {
        $slugs = config('program_deliverables.hub_target_primary_district_slugs', ['almora', 'pauri-garhwal']);

        return is_array($slugs) ? array_values(array_map('strval', $slugs)) : ['almora', 'pauri-garhwal'];
    }

    public static function isPrimaryHubDistrictSlug(?string $slug): bool
    {
        if ($slug === null || $slug === '') {
            return false;
        }

        return in_array(strtolower(trim($slug)), self::primaryDistrictSlugs(), true);
    }

    public static function isPrimaryHubDistrictId(?int $districtId): bool
    {
        if ($districtId === null || $districtId <= 0) {
            return false;
        }

        $slug = District::query()->whereKey($districtId)->value('slug');

        return self::isPrimaryHubDistrictSlug(is_string($slug) ? $slug : null);
    }

    /**
     * @param  list<int>  $districtIds
     * @return list<int>
     */
    public static function filterDistrictIdsForHubTargets(array $districtIds): array
    {
        if ($districtIds === []) {
            return [];
        }

        return District::query()
            ->whereIn('id', $districtIds)
            ->whereIn('slug', self::primaryDistrictSlugs())
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    public static function serials(): array
    {
        $serials = config('program_deliverables.hub_target_serials', []);

        return is_array($serials) ? array_values(array_map('strval', $serials)) : [];
    }

    /**
     * @return list<string>
     */
    public static function deliverableCodes(): array
    {
        $codes = config('program_deliverables.hub_target_deliverable_codes', []);

        return is_array($codes) ? array_values(array_map('strval', $codes)) : [];
    }

    public static function isHubTargetRow(string $serial, array $source = []): bool
    {
        if (in_array($serial, self::serials(), true)) {
            return true;
        }

        foreach (self::lookupCodesFromSource($source) as $code) {
            if (in_array(strtolower($code), array_map('strtolower', self::deliverableCodes()), true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $source
     * @return list<string>
     */
    private static function lookupCodesFromSource(array $source): array
    {
        return match ($source['type'] ?? 'none') {
            'deliverable', 'service' => array_filter([(string) ($source['code'] ?? '')]),
            'cfa_count', 'onboarding_count', 'potential_lakhpati_onboarding_count',
            'district_workshop_sessions', 'edp_sessions', 'bst_sessions', 'bst_participants',
            'field_work_workshops', 'field_work_participants', 'technical_training_sessions',
            'technical_training_potential_lakhpati_sessions', 'community_org_outreach_count',
            'capacity_building_stakeholder_sessions', 'stakeholder_consultation_workshop_sessions',
            'line_department_meeting_sessions', 'pitch_deck_preparations', 'pitch_deck_combined',
            'demo_days_count', 'funding_schematic_partners_outreach_count',
            'marketing_partner_outreach_count', 'marketing_partner_onboarded_count',
            'business_acceleration_partners_outreach_count' => array_filter([(string) ($source['deliverable_code'] ?? '')]),
            'acceleration_services_initiation_count' => array_filter([(string) ($source['deliverable_code'] ?? '')]),
            'none' => array_filter([(string) ($source['deliverable_code'] ?? '')]),
            default => [],
        };
    }
}
