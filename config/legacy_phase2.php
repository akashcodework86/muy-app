<?php

/**
 * Map rbiphase2.monthly_activity_targets.activity_type → deliverables.code
 * Add keys as you discover more values in legacy data.
 */
return [
    'activity_type_to_deliverable_code' => [
        'district_workshop' => 'awareness_district',
        'block_workshop' => 'lakhpati_block',
        'call_for_application' => 'cfa',
        'call_for_applications' => 'cfa',
        'onboarding' => 'onboarding',
        'edp_session' => 'edp_workshop',
        'business_skills_training' => 'bst_sessions',
        'business_plan' => 'bmc',
        'business_model_canvas' => 'bmc',
        'bmc' => 'bmc',
        'access_to_finance' => 'access_to_finance',
        'access to finance' => 'access_to_finance',
        'fssai_registration' => 'fssai',
        'fssai' => 'fssai',
        'gst' => 'gst',
        'gi_seller' => 'gi_seller',
        'gi seller' => 'gi_seller',
        'artisan_card' => 'artisan_card',
        'trademark' => 'trademark',
        'utdb_registration' => 'utdb_registration',
        'utdb' => 'utdb_registration',
        'business_registration' => 'business_registration',
        'udyam_registration' => 'business_registration',
        'bst_participations' => 'bst_participations',
        'market_link' => 'market_link',
        'pitch_deck_prep' => 'pitch_deck_prep',
        'pitchathon_demo' => 'pitchathon_demo',
        'acceleration_services' => 'acceleration_services',
        'business_acceleration' => 'acceleration_services',
        'case_studies' => 'case_studies',
        'social_media' => 'social_media',
        'events_seminars' => 'events_seminars',
        'buyer_seller_meets' => 'buyer_seller_meets',

        /* Legacy rbiphase2 variants (previously unmapped) */
        'support_to_lakhpati_didis' => 'lakhpati_block', // MIS: Support to Lakhpati Didis / block workshop family
        'marketing_support' => 'market_link', // closest MIS: Market Link
        'trademark_filing' => 'trademark',
        'utdb_hub_wise' => 'utdb_registration',
        'business_registration_(composite)' => 'business_registration',
        'call_for_application_(cfa)' => 'cfa',
    ],

    /*
    | rbiphase2.rbi_services_assigned: optional explicit map (normalized keys).
    | Tries: "category|service_name", then service_name, then activity_type_to_deliverable_code(service_name).
    */
    'rbi_services_assigned_to_deliverable' => [
        'convergence|mudra' => 'access_to_finance',
        'convergence|support in application process' => 'access_to_finance',
        'convergence|msy' => 'access_to_finance',
        'convergence|pmfme' => 'access_to_finance',
        'convergence|ddu grah awas yojana (homestay)' => 'access_to_finance',
        'convergence|other support service' => 'access_to_finance',
        'business formalisation|already registered' => 'business_registration',
        'business formalisation|cooperative' => 'business_registration',
        'business formalisation|company registration' => 'business_registration',
        'business formalisation|shop & establishment' => 'business_registration',
        'business formalisation|uk firm registration' => 'business_registration',
        'legal support & licencing|pan card' => 'business_registration',
        'legal support & licencing|gi seller registration' => 'gi_seller',
        'legal support & licencing|legal vetting of documents' => 'acceleration_services',
        'legal support & licencing|ipr support' => 'trademark',
        'training & capacity building|training package 1' => 'bst_sessions',
        'training & capacity building|training package 2' => 'bst_sessions',
        'forward linkages|offline connect' => 'market_link',
        'other support service|trade fair participation' => 'events_seminars',
    ],

    /*
    |--------------------------------------------------------------------------
    | Legacy staff import (rbiphase2.users → Laravel district_staff)
    |--------------------------------------------------------------------------
    */
    'staff_import' => [
        'roles' => [
            'incubation_manager',
            'bpd',
            'outreach_coordinator',
            'data_support',
            'market_expert',
            'flmi_expert',
        ],
        /** Default first-login password when creating users (re-import does not reset password). */
        'default_password' => 'password@123',
        /**
         * Canonical district `name` as in muy.districts → normalized alias strings from legacy.
         * Districts not listed here must match `districts.name` after normalize().
         */
        'district_aliases' => [
            'Udham Singh Nagar' => [
                'udham singh nagar',
                'udham singh nagr',
                'us nagar',
                'u s nagar',
                'u.s. nagar',
                'u s n',
            ],
            'Pauri Garhwal' => [
                'pauri garhwal',
                'pauri',
            ],
            'Tehri Garhwal' => [
                'tehri garhwal',
                'tehri',
            ],
            'Haridwar' => [
                'haridwar',
                'hardwar',
            ],
            'Dehradun' => [
                'dehradun',
                'doon',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Legacy incubatee profile (Phase 2 MIS)
    |--------------------------------------------------------------------------
    |
    | Optional absolute base URL (with trailing slash) where legacy PHP served
    | files such as product images or incubatee photos, e.g. https://host/rbiphase2/
    | When empty, product images fall back to placeholders unless stored locally.
    |
    */
    'legacy_public_assets_base_url' => rtrim((string) env('LEGACY_PUBLIC_ASSETS_BASE_URL', ''), '/'),
];
