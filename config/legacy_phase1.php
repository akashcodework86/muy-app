<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Phase 1 legacy district (tblapplication.FatherName)
    |--------------------------------------------------------------------------
    |
    | Canonical muy.districts.name → normalized legacy FatherName values.
    | Legacy Phase 1 stores district in FatherName, not City.
    |
    */
    'district_aliases' => [
        'Almora' => ['almora'],
        'Bageshwar' => ['bageshwar'],
        'Chamoli' => ['chamoli'],
        'Champawat' => ['champawat'],
        'Dehradun' => ['dehradun', 'doon'],
        'Haridwar' => ['haridwar', 'hardwar'],
        'Nainital' => ['nainital'],
        'Pauri Garhwal' => ['pauri', 'pauri_garhwal'],
        'Pithoragarh' => ['pithoragarh'],
        'Rudraprayag' => ['rudraprayag'],
        'Tehri Garhwal' => ['tehri', 'tehri_garhwal'],
        'Udham Singh Nagar' => ['us_nagar'],
        'Uttarkashi' => ['uttarkashi'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Legacy hub column (Garhwal / kumaon) → MUY hub slug
    |--------------------------------------------------------------------------
    */
    'legacy_region_labels' => [
        'garhwal' => 'Garhwal (legacy region)',
        'kumaon' => 'Kumaon (legacy region)',
    ],

    /*
    |--------------------------------------------------------------------------
    | Phase 1 service columns (tblapplication — wide table, not child rows)
    |--------------------------------------------------------------------------
    */
    'service_fields' => [
        ['column' => 'training', 'label' => 'Training', 'type' => 'yes'],
        ['column' => 'technical_training', 'label' => 'Technical training', 'type' => 'yes'],
        ['column' => 'bmc_support', 'label' => 'BMC support', 'type' => 'yes'],
        ['column' => 'business_skillattained', 'label' => 'Business skills training', 'type' => 'yes'],
        ['column' => 'loan', 'label' => 'Loan', 'type' => 'yes', 'detail' => 'loan_scheme'],
        ['column' => 'registered', 'label' => 'Business registration', 'type' => 'yes', 'detail' => 'registration_type'],
        ['column' => 'unitsetup', 'label' => 'Unit setup', 'type' => 'yes'],
        ['column' => 'productlaunch_update', 'label' => 'Product launch', 'type' => 'yes'],
        ['column' => 'mentorships_attained', 'label' => 'Mentorship', 'type' => 'text'],
        ['column' => 'supportin_business', 'label' => 'Support in business', 'type' => 'text'],
        ['column' => 'prior_bisupport', 'label' => 'Prior business support', 'type' => 'text'],
        ['column' => 'othersupport_byrbi', 'label' => 'Other RBI support', 'type' => 'text'],
        ['column' => 'application_status', 'label' => 'Loan / scheme', 'type' => 'text'],
    ],

];
