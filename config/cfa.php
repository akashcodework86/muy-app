<?php

return [
    /** Uttarakhand state LGD code (MoPR); used on CFA submissions for government alignment. */
    'lgd_state_code' => env('CFA_LGD_STATE_CODE', '5'),

    /** Legacy fallback if district_blocks table has no rows for a district (run DistrictBlockSeeder). */
    'blocks_by_district' => require __DIR__.'/cfa_blocks.php',
    'products_by_category' => require __DIR__.'/cfa_products.php',

    'categories' => ['Individual', 'SHG', 'CBO'],

    'castes' => ['GEN', 'EWS', 'OBC', 'SC', 'ST', 'OTH'],

    'genders' => ['Female', 'Male', 'Other', 'NA'],

    'education_individual' => [
        'Below 8th', '8th pass', '10th pass', '12th pass', 'ITI', 'Diploma', 'Certificate',
        'Under Graduate', 'Post Graduate', 'NA',
    ],

    'business_categories' => [
        'Agri Allied', 'Food Processing', 'Handloom & Handicraft', 'Herbal and Aromatic', 'Homestay', 'Others',
    ],

    'business_age' => ['0', '1-6 months', '7-12 months', '12-24 months', '>24 months'],

    'registration_types' => [
        'Udyam (MSME)', 'Company', 'Shop & Establishment', 'Cooperative', 'FSSAI', 'GST', 'UTDB',
        'Artisan Card', 'Partnership Firm', 'Proprietorship (other)', 'Other',
    ],

    'id_proof_types' => ['', 'Aadhaar', 'Voter ID', 'PAN'],

    'info_sources' => ['Social Media', 'Print Media', 'Department', 'RBI/MUY Staff'],

    'department_names' => [
        'Agriculture', 'Rural Development', 'Women and Child Development', 'Commerce and Industry', 'Other',
    ],

    'training_modes' => ['Online', 'Physical'],

    'techuse_options' => ['WhatsApp', 'Social media', 'E-Commerce', 'Website'],

    'location_types' => ['Rural', 'Urban'],

    /*
     * Checkbox values must match legacy index.php exactly for reporting parity.
     */
    'challenge_values' => [
        'Unavailability of Packaging Material',
        'Sales & Marketing',
        'Branding',
        'Loan or Financial Issue',
        'License or Legal support',
        'Lack of Government Scheme Information',
        'Lack of Technical Knowledge',
        'Lack of Training',
        'Unavailability of Raw material',
        'Wild Animals Destroy our Crops',
        'Lack of Mentor',
        'Lack of Digital Marketing Knowledge',
        'Networking issue to sell our Products',
        'Lack of teamwork',
        'Unavailability of the Machine',
        'Connectivity Challenge for Homestay',
        'Human Resource Problem Due to Migration',
        'Lack of Skills',
        'Capacity Building issue',
        'Seasonal work',
        'Lack of Pricing and Costing',
        'Exploitation by intermediaries',
        'Machine Servicing Challenge',
        'Animal attack while collecting raw Material Like Pine Leaf, Ringal, Bamboo',
        'Not getting enough money after selling our Products',
        'Lack of Logistics Connectivity',
        'Insufficient Water for Farming',
        'Payment issue',
        'Diseases in Animals',
        'Not getting a Trainer for Product Development',
        'No Update on District Level Industrial Policies',
        'No Idea of a Business Plan/ Road map/ vision for a business cycle.',
    ],

    'expectation_values' => [
        'Advise on ideation of the business idea',
        'Support in prototyping',
        'Market testing',
        'Support for IPR and other licenses',
        'Access to market',
        'Access to mentors',
        'Access to finance',
        'Access to infrastructure',
        'Networking',
        'Access to funders and investors',
        'Other',
    ],
];
