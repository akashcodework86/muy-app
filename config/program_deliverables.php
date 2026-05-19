<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Spoke / Hub / State column (MIS-style)
    |--------------------------------------------------------------------------
    |
    | Mapped by linked deliverable code. Services without a deliverable use default.
    |
    */
    'level_by_deliverable_code' => [
        'awareness_district' => 'State',
        'cfa' => 'Spoke & Hub',
        'onboarding' => 'Spoke & Hub',
        'lakhpati_block' => 'Spoke & Hub',
        'edp_workshop' => 'Spoke & Hub',
        'bst_sessions' => 'Spoke & Hub',
        'bst_participations' => 'Spoke & Hub',
        'events_seminars' => 'State',
        'buyer_seller_meets' => 'State',
    ],

    'default_level' => 'Spoke & Hub',

];
