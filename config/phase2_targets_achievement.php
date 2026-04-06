<?php

use App\Services\LegacyPhase2\Phase2TargetsPhpAchievementService;

/**
 * Mirror admin/targets.php (Phase 2 staff dashboard) achievement → Laravel `deliverables.code`.
 * Norm types use space-separated keys (targets.php `norm_type` output style).
 *
 * Achievement **dates** for a selected FY come from `fiscal_years.starts_on` / `ends_on` in
 * {@see Phase2TargetsPhpAchievementService}. FY 2025-26 uses
 * 2025-04-02 … 2026-04-01 (Phase 2 / 24.php quarter alignment), not calendar Apr 1 – Mar 31 only.
 */
return [

    'norm_type_to_deliverable_code' => [
        'call for application' => 'cfa',
        'district workshop' => 'awareness_district',
        'block workshop' => 'lakhpati_block',
        'edp workshop' => 'edp_workshop',
        'onboarding' => 'onboarding',
        'business model canvas' => 'bmc',
        'business skills training' => 'bst_sessions',
        'market link' => 'market_link',
        'marketing support' => 'market_link',
        'access to finance' => 'access_to_finance',

        'udyam registration' => 'business_registration',
        'company registration' => 'business_registration',
        'shop establishment' => 'business_registration',
        'cooperative' => 'business_registration',
        'uk firm registration' => 'business_registration',
        'other registration' => 'business_registration',
        'other service' => 'business_registration',
        'other licensing support' => 'business_registration',

        'fssai' => 'fssai',
        'gst' => 'gst',
        'gi seller registration' => 'gi_seller',
        'artisan card' => 'artisan_card',
        'trademark filing' => 'trademark',
        'trademark' => 'trademark',
        'utdb registration' => 'utdb_registration',
        'utdb hub wise' => 'utdb_registration',
        'pan card' => 'business_registration',
        'ipr support' => 'trademark',
        'business plan' => 'bmc',
        'business acceleration' => 'acceleration_services',
        'legal vetting of documents' => 'acceleration_services',
        'product testing' => 'acceleration_services',
        'fire noc' => 'business_registration',
        'ayush licence' => 'business_registration',
        'training package 1' => 'bst_sessions',
        'training package 2' => 'bst_sessions',
        'training package 3' => 'bst_sessions',
        'training package 4' => 'bst_sessions',
        'pmfme' => 'access_to_finance',
        'msy' => 'access_to_finance',
        'msy nano' => 'access_to_finance',
        'pmegp' => 'access_to_finance',
        'msme' => 'access_to_finance',
        'mudra' => 'access_to_finance',
        'veer chandra singh garhwali self empl.' => 'access_to_finance',
        'ddu grah awas yojana (homestay)' => 'access_to_finance',
        'support in process' => 'access_to_finance',
        'support in application process' => 'access_to_finance',
        'other support service' => 'access_to_finance',
    ],

];
