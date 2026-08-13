<?php

/**
 * MUY Homestay Incubate Progress Survey — option lists & prefill field keys.
 */
return [
    /** Prefill keys that the State Admin Editable/Lock toggle applies to. */
    'prefill_keys' => [
        'respondent_name',
        'gender',
        'age_group',
        'caste',
        'enterprise_name',
        'district',
        'block',
        'village',
        'pincode',
        'location_type',
        'phone',
        'email',
        'enrolment_year',
        'info_source',
        'incubation_center',
        'venture_type',
        'stage_at_enrolment',
        'utdb_registered',
        'utdb_reg_number',
        'muy_financial_assistance',
        'muy_financial_amount',
        'bank_loan_muy',
        'employed_count_during',
        'empwomen_during',
        'support_services',
        'challenges_prefill',
    ],

    'genders' => ['Male', 'Female', 'Other'],

    'age_groups' => ['18–25', '26–35', '36–45', '46–60', 'Above 60'],

    'castes' => ['General', 'OBC', 'SC', 'ST', 'Minority'],

    'location_types' => ['Rural', 'Semi-urban', 'Urban', 'Remote/border area'],

    'roles' => ['Owner', 'Co-owner/family', 'Manager', 'Other'],

    'enrolment_years' => ['2021', '2022', '2023', '2024', '2025', '2026'],

    'info_sources' => [
        'Government official/line dept',
        'MUY Incubation center',
        'RSETI',
        'Word of mouth',
        'Social media/website',
        'Camp/awareness drive',
        'Other',
    ],

    'venture_types' => [
        'New venture (started after joining MUY)',
        'Existing (expanded/formalized under MUY)',
    ],

    'stages' => ['Seed', 'Early', 'Growth'],

    'yes_no_process' => ['Yes', 'No', 'In process'],

    'room_counts' => ['1', '2', '3', '4', '5+'],

    'homestay_types' => [
        'Traditional/heritage',
        'Eco/nature',
        'Farm/agri',
        'Adventure',
        'Pilgrimage/spiritual',
        'Standard',
    ],

    'facilities' => [
        'Local cuisine',
        'Guided treks/tours',
        'Cultural experiences',
        'Farm activities',
        'Wi-Fi',
        'Attached washrooms',
        'Parking',
        'Bonfire/outdoor',
    ],

    'peak_seasons' => [
        'Summer',
        'Monsoon',
        'Winter',
        'Year-round',
        'Pilgrimage (Char Dham yatra) season',
    ],

    'tariffs' => ['<1,000', '1,000–2,000', '2,001–3,500', '3,501–5,000', '>5,000'],

    'funding_sources' => [
        'MUY grant/seed support',
        'Bank loan',
        'Own savings',
        'Family/friends',
        'SHG/cooperative',
        'Other subsidy',
    ],

    'revenue_status' => [
        'Increased significantly',
        'Increased moderately',
        'Same',
        'Decreased',
        'Not comparable (new)',
    ],

    'other_income_sources' => [
        'Food & Beverages',
        'Sale of local products (pickles, honey, handicrafts, etc.)',
        'Village/nature/cultural tours',
        'Trekking or adventure activities',
        'Transport/travel arrangements',
        'Workshops or experiential activities',
        'Events/retreats/workations',
    ],

    'occupancy_bands' => [
        'Rarely occupied (less than 20%)',
        'Occasionally occupied (20% to 40%)',
        'Moderately occupied (41% to 60%)',
        'Frequently occupied (61% to 80%)',
        'Almost always occupied (more than 80%)',
    ],

    'booking_sources' => [
        'Walk-ins',
        'Online travel platforms (OTA)',
        'Own social media',
        'Travel agents/tour operators',
        'Repeat guests/referrals',
        'Govt/tourism dept listing',
    ],

    'ota_platforms' => [
        'MakeMyTrip',
        'Booking.com',
        'Airbnb',
        'Goibibo',
        'State tourism portal',
        'Other',
    ],

    'employment_bands' => ['1', '2–3', '4–6', '7–10', '>10'],

    'local_sourcing' => [
        'Local farm produce',
        'Local artisans/handicrafts',
        'Local guides',
        'Local transport',
        'Local labor',
    ],

    'yes_no_unsure' => ['Yes', 'No', 'Not sure'],

    'support_services' => [
        'Business/entrepreneurship training',
        'Hospitality/guest management',
        'Digital marketing',
        'Financial literacy/ assistance',
        'Registration/licensing help',
        'Branding',
        'Mentoring/handholding',
        'Market linkage',
    ],

    'usefulness' => [
        'Very useful',
        'Useful',
        'Neutral',
        'Not useful',
        'Did not receive',
    ],

    'followup_frequency' => [
        'Regular (monthly)',
        'Occasional',
        'Rare',
        'None after enrolment',
    ],

    'challenge_rank_options' => [
        'Access to finance/working capital',
        'Low/seasonal footfall',
        'Marketing & online visibility',
        'Connectivity/road/infrastructure',
        'Skilled staff availability',
        'Regulatory/licensing hurdles',
        'Competition',
        'Maintenance/upgradation costs',
        'Other',
    ],

    'digital_support' => [
        'Google Business profile',
        'Instagram/Facebook page',
        'WhatsApp Business',
        'Own website',
        'Digital payment (UPI/POS)',
    ],

    'digital_comfort' => [
        'Fully independent',
        'Need occasional help',
        'Fully dependent on others',
        'Not managing online at all',
    ],

    'progress_rating' => [
        '5 Excellent',
        '4 Good',
        '3 Moderate',
        '2 Slow',
        '1 No progress',
    ],

    'agree_rating' => [
        '5 Strongly agree',
        '4 Agree',
        '3 Neutral',
        '2 Disagree',
        '1 Strongly disagree',
    ],

    'recommend_rating' => ['5', '4', '3', '2', '1'],

    'expansion_plans' => [
        'Add more rooms',
        'Add experiences/activities',
        'Improve amenities',
        'Hire more staff',
        'Better marketing',
        'No expansion planned',
    ],

    'future_support' => [
        'Additional finance/credit',
        'Marketing & branding',
        'Skill training',
        'Infrastructure grants',
        'Aggregator/platform linkage',
        'Certification',
        'Mentoring',
    ],
];
