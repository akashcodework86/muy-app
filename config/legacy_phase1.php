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

];
