<?php

namespace App\Support;

/**
 * Deliverables without a fixed annual target — show "(Need Based)" when target is unset.
 */
final class NeedBasedDeliverablesSupport
{
    public const LABEL = '(Need Based)';

    /**
     * @return list<string>
     */
    public static function serials(): array
    {
        $serials = config('program_deliverables.need_based_serials', []);

        return is_array($serials) ? array_values(array_map('strval', $serials)) : [];
    }

    /**
     * @return list<string>
     */
    public static function deliverableCodes(): array
    {
        $codes = config('program_deliverables.need_based_deliverable_codes', []);

        return is_array($codes) ? array_values(array_map('strval', $codes)) : [];
    }

    /**
     * @return list<string>
     */
    public static function serviceCodes(): array
    {
        $codes = config('program_deliverables.need_based_service_codes', []);

        return is_array($codes) ? array_values(array_map('strval', $codes)) : [];
    }

    public static function isNeedBasedRow(string $serial, array $source = []): bool
    {
        if (in_array($serial, self::serials(), true)) {
            return true;
        }

        foreach (self::lookupCodesFromSource($source) as $code) {
            $lower = strtolower($code);
            if (in_array($lower, array_map('strtolower', self::deliverableCodes()), true)) {
                return true;
            }
            if (in_array($lower, array_map('strtolower', self::serviceCodes()), true)) {
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
            'deliverable' => array_filter([(string) ($source['code'] ?? '')]),
            'service' => array_filter([(string) ($source['code'] ?? '')]),
            'cfa_count', 'onboarding_count', 'potential_lakhpati_onboarding_count',
            'district_workshop_sessions', 'edp_sessions', 'bst_sessions', 'bst_participants',
            'field_work_workshops', 'field_work_participants', 'technical_training_sessions',
            'technical_training_potential_lakhpati_sessions', 'community_org_outreach_count',
            'capacity_building_stakeholder_sessions', 'stakeholder_consultation_workshop_sessions',
            'line_department_meeting_sessions', 'pitch_deck_preparations', 'pitch_deck_combined',
            'demo_days_count', 'funding_schematic_partners_outreach_count',
            'marketing_partner_outreach_count', 'marketing_partner_onboarded_count',
            'business_acceleration_partners_outreach_count',
            'mentorship_online_portal_unique' => array_filter([(string) ($source['deliverable_code'] ?? '')]),
            'acceleration_services_initiation_count' => array_filter([(string) ($source['deliverable_code'] ?? '')]),
            'none' => array_filter([(string) ($source['deliverable_code'] ?? '')]),
            default => [],
        };
    }
}
