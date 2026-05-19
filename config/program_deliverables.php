<?php

return [

    /** Phase 3 CFA floor — must match StateAdminDashboardService. */
    'phase3_floor_date' => '2026-04-01',

    /*
    |--------------------------------------------------------------------------
    | Official MIS indicator sequence (S.N. 1 … 12)
    |--------------------------------------------------------------------------
    |
    | Tree: pillar/subcategory headings (blank metrics) + leaf indicators (1.1, 1.2, …).
    | source types: deliverable, service, services, cfa_count, onboarding_count,
    |   field_work_workshops (1.3), field_work_participants (1.3.1) — staff Field work visits,
    |   district_workshop_sessions,
    |   edp_sessions, bst_sessions, bst_participants, technical_training_sessions,
    |   none (placeholder — target/achievement 0 unless deliverable mapped)
    |
    */
    'matrix' => [
        [
            'name' => 'Outreach and Mobilisation',
            'row_type' => 'pillar',
            'children' => [
                [
                    'name' => 'Call for Application',
                    'row_type' => 'leaf',
                    'indicator_type' => 'Key Indicator',
                    'level' => 'Spoke & Hub',
                    'source' => ['type' => 'cfa_count', 'deliverable_code' => 'cfa'],
                ],
                [
                    'name' => 'District Level Workshops',
                    'row_type' => 'leaf',
                    'indicator_type' => 'Key Indicator',
                    'level' => 'State',
                    'source' => ['type' => 'district_workshop_sessions', 'deliverable_code' => 'awareness_district'],
                ],
                [
                    'name' => 'No. of Awareness cum Outreach activities for SHG members/Potential Lakhpati Didis/ SHGs/CBOs etc.',
                    'row_type' => 'leaf',
                    'indicator_type' => 'Key Indicator',
                    'level' => 'Spoke & Hub',
                    'source' => ['type' => 'field_work_workshops'],
                    'children' => [
                        [
                            'name' => 'Participants in Awareness cum Outreach activities',
                            'row_type' => 'leaf',
                            'indicator_type' => 'Non-Key',
                            'level' => 'Spoke & Hub',
                            'source' => ['type' => 'field_work_participants'],
                        ],
                    ],
                ],
                [
                    'name' => 'EAP/EDP Sessions',
                    'row_type' => 'leaf',
                    'indicator_type' => 'Key Indicator',
                    'level' => 'Spoke & Hub',
                    'source' => ['type' => 'edp_sessions', 'deliverable_code' => 'edp_workshop'],
                ],
                [
                    'name' => 'Outreach through Community Organizations',
                    'row_type' => 'leaf',
                    'indicator_type' => 'Non-Key',
                    'level' => 'Spoke & Hub',
                    'source' => ['type' => 'none'],
                ],
            ],
        ],
        [
            'name' => 'Identification and Onboarding of Enterprises',
            'row_type' => 'pillar',
            'children' => [
                [
                    'name' => 'Incubatees Onboarded',
                    'row_type' => 'leaf',
                    'indicator_type' => 'Key Indicator',
                    'level' => 'Spoke & Hub',
                    'source' => ['type' => 'onboarding_count', 'deliverable_code' => 'onboarding'],
                    'children' => [
                        [
                            'name' => 'Onboarding of Potential Lakhpati Didi/ SHG Members/ CBOs*',
                            'row_type' => 'leaf',
                            'indicator_type' => 'Non-Key',
                            'level' => 'Spoke & Hub',
                            'source' => ['type' => 'deliverable', 'code' => 'lakhpati_block'],
                        ],
                    ],
                ],
            ],
        ],
        [
            'name' => 'Training & Capacity Building',
            'row_type' => 'pillar',
            'children' => [
                [
                    'name' => 'Business Skills Training Sessions',
                    'row_type' => 'leaf',
                    'indicator_type' => 'Key Indicator',
                    'level' => 'Spoke & Hub',
                    'source' => ['type' => 'bst_sessions', 'deliverable_code' => 'bst_sessions'],
                ],
                [
                    'name' => 'Incubatees taken Part in Business Modules Training',
                    'row_type' => 'leaf',
                    'indicator_type' => 'Key Indicator',
                    'level' => 'Spoke & Hub',
                    'source' => ['type' => 'bst_participants', 'deliverable_code' => 'bst_participations'],
                ],
                [
                    'name' => 'Technical Trainings to Incubatees',
                    'row_type' => 'leaf',
                    'indicator_type' => 'Non-Key',
                    'level' => 'Spoke & Hub',
                    'source' => ['type' => 'technical_training_sessions'],
                    'children' => [
                        [
                            'name' => 'Technical Trainings to Potential Lakhpati Didis/ SHG Members/ CBOs',
                            'row_type' => 'leaf',
                            'indicator_type' => 'Non-Key',
                            'level' => 'Spoke & Hub',
                            'source' => ['type' => 'none'],
                        ],
                    ],
                ],
                [
                    'name' => 'Capacity Building of stakeholders (REAP, USRLM, Other Line department staff)',
                    'row_type' => 'leaf',
                    'indicator_type' => 'Key Indicator',
                    'level' => 'State',
                    'source' => ['type' => 'none'],
                ],
            ],
        ],
        [
            'name' => 'Business Formalization',
            'row_type' => 'pillar',
            'children' => [
                [
                    'name' => 'Business Registration',
                    'row_type' => 'subcategory',
                    'children' => [
                        [
                            'name' => 'Business Registration',
                            'row_type' => 'leaf',
                            'indicator_type' => 'Key Indicator',
                            'level' => 'Spoke & Hub',
                            'source' => ['type' => 'deliverable', 'code' => 'business_registration'],
                        ],
                    ],
                ],
                [
                    'name' => 'Legal & Licensing Support',
                    'row_type' => 'subcategory',
                    'children' => [
                        [
                            'name' => 'Artisan Card',
                            'row_type' => 'leaf',
                            'indicator_type' => 'Key Indicator',
                            'level' => 'Spoke & Hub',
                            'source' => ['type' => 'deliverable', 'code' => 'artisan_card'],
                        ],
                        [
                            'name' => 'FSSAI',
                            'row_type' => 'leaf',
                            'indicator_type' => 'Key Indicator',
                            'level' => 'Spoke & Hub',
                            'source' => ['type' => 'deliverable', 'code' => 'fssai'],
                        ],
                        [
                            'name' => 'UTDB',
                            'row_type' => 'leaf',
                            'indicator_type' => 'Key Indicator',
                            'level' => 'Spoke & Hub',
                            'source' => ['type' => 'deliverable', 'code' => 'utdb_registration'],
                        ],
                        [
                            'name' => 'GST Registration',
                            'row_type' => 'leaf',
                            'indicator_type' => 'Key Indicator',
                            'level' => 'Spoke & Hub',
                            'source' => ['type' => 'deliverable', 'code' => 'gst'],
                        ],
                        [
                            'name' => 'Trademark application filling',
                            'row_type' => 'leaf',
                            'indicator_type' => 'Non-Key',
                            'level' => 'Spoke & Hub',
                            'source' => ['type' => 'deliverable', 'code' => 'trademark'],
                        ],
                        [
                            'name' => 'GI Seller Registration',
                            'row_type' => 'leaf',
                            'indicator_type' => 'Non-Key',
                            'level' => 'Spoke & Hub',
                            'source' => ['type' => 'deliverable', 'code' => 'gi_seller'],
                        ],
                        [
                            'name' => 'Advance Licensing Support (Mandi Licensing, Lab Test etc.)',
                            'row_type' => 'leaf',
                            'indicator_type' => 'Non-Key',
                            'level' => 'Spoke & Hub',
                            'source' => [
                                'type' => 'services',
                                'codes' => ['other_licensing', 'legal_vetting', 'fire_noc', 'ayush_licence', 'ipr_support'],
                            ],
                        ],
                    ],
                ],
            ],
        ],
        [
            'name' => 'Mentorship',
            'row_type' => 'pillar',
            'children' => [
                [
                    'name' => 'Specialized Mentorship Support',
                    'row_type' => 'leaf',
                    'indicator_type' => 'Non-Key',
                    'level' => 'Spoke & Hub',
                    'source' => ['type' => 'target_name', 'match' => 'specialized mentorship'],
                ],
                [
                    'name' => 'Mentorship Support through online portal',
                    'row_type' => 'leaf',
                    'indicator_type' => 'Non-Key',
                    'level' => 'State',
                    'source' => ['type' => 'target_name', 'match' => 'mentorship support through online'],
                ],
            ],
        ],
        [
            'name' => 'Partnership & Forward Linkages',
            'row_type' => 'pillar',
            'children' => [
                [
                    'name' => 'No of Partners outreach',
                    'row_type' => 'leaf',
                    'indicator_type' => 'Non-Key',
                    'level' => 'State',
                    'source' => ['type' => 'none'],
                ],
                [
                    'name' => 'Marketing Partners Onboarded through (LoA/LoI/MoU)',
                    'row_type' => 'leaf',
                    'indicator_type' => 'Non-Key',
                    'level' => 'State',
                    'source' => ['type' => 'none'],
                ],
                [
                    'name' => 'Incubatees linked to online/offline Market',
                    'row_type' => 'leaf',
                    'indicator_type' => 'Key Indicator',
                    'level' => 'Spoke & Hub',
                    'source' => ['type' => 'deliverable', 'code' => 'market_link'],
                ],
            ],
        ],
        [
            'name' => 'Business Acceleration Services',
            'row_type' => 'pillar',
            'children' => [
                [
                    'name' => 'No of Partners outreach',
                    'row_type' => 'leaf',
                    'indicator_type' => 'Non-Key',
                    'level' => 'State',
                    'source' => ['type' => 'none'],
                ],
                [
                    'name' => 'Initiation of acceleration and co-incubation services',
                    'row_type' => 'leaf',
                    'indicator_type' => 'Key Indicator',
                    'level' => 'Spoke & Hub',
                    'source' => ['type' => 'deliverable', 'code' => 'acceleration_services'],
                ],
            ],
        ],
        [
            'name' => 'Funding & Schematic Convergence',
            'row_type' => 'pillar',
            'children' => [
                [
                    'name' => 'Schematic Convergence',
                    'row_type' => 'leaf',
                    'indicator_type' => 'Key Indicator',
                    'level' => 'Spoke & Hub',
                    'source' => ['type' => 'deliverable', 'code' => 'access_to_finance'],
                ],
                [
                    'name' => 'Support to MUY Incubatee through Reap',
                    'row_type' => 'leaf',
                    'indicator_type' => 'Non-Key',
                    'level' => 'Spoke & Hub',
                    'source' => ['type' => 'none'],
                ],
                [
                    'name' => 'Incubatees Pitch Deck Preparation',
                    'row_type' => 'leaf',
                    'indicator_type' => 'Key Indicator',
                    'level' => 'Spoke & Hub',
                    'source' => ['type' => 'deliverable', 'code' => 'pitch_deck_prep'],
                ],
                [
                    'name' => 'Demo Days',
                    'row_type' => 'leaf',
                    'indicator_type' => 'Key Indicator',
                    'level' => 'State',
                    'source' => ['type' => 'deliverable', 'code' => 'pitchathon_demo'],
                ],
                [
                    'name' => 'No of Partners outreach',
                    'row_type' => 'leaf',
                    'indicator_type' => 'Non-Key',
                    'level' => 'State',
                    'source' => ['type' => 'none'],
                ],
            ],
        ],
        [
            'name' => 'Other Support Services',
            'row_type' => 'pillar',
            'children' => [
                [
                    'name' => 'Business Model Canvas',
                    'row_type' => 'leaf',
                    'indicator_type' => 'Key Indicator',
                    'level' => 'Spoke & Hub',
                    'source' => ['type' => 'deliverable', 'code' => 'bmc'],
                ],
                [
                    'name' => 'Other Support Services - Labelling, Packaging, Logo Designing etc.',
                    'row_type' => 'leaf',
                    'indicator_type' => 'Non-Key',
                    'level' => 'Spoke & Hub',
                    'source' => [
                        'type' => 'services',
                        'codes' => [
                            'packaging_support', 'packaging_designing', 'labelling_support', 'logo_designing',
                            'product_testing', 'catalogue_development', 'photoshoot', 'product_diversification',
                            'content_writing', 'business_plan',
                        ],
                    ],
                ],
            ],
        ],
        [
            'name' => 'Branding, Communication & Knowledge Management',
            'row_type' => 'pillar',
            'children' => [
                [
                    'name' => 'Social Media Post',
                    'row_type' => 'leaf',
                    'indicator_type' => 'Non-Key',
                    'level' => 'State',
                    'source' => ['type' => 'deliverable', 'code' => 'social_media'],
                ],
                [
                    'name' => 'Preparation of Case Studies and Testimonials',
                    'row_type' => 'leaf',
                    'indicator_type' => 'Non-Key',
                    'level' => 'State',
                    'source' => ['type' => 'deliverable', 'code' => 'case_studies'],
                ],
                [
                    'name' => 'MUY Newsletter',
                    'row_type' => 'leaf',
                    'indicator_type' => 'Non-Key',
                    'level' => 'State',
                    'source' => ['type' => 'none'],
                ],
                [
                    'name' => 'Newspaper Ads and Radio promotion campaigns',
                    'row_type' => 'leaf',
                    'indicator_type' => 'Non-Key',
                    'level' => 'State',
                    'source' => ['type' => 'none'],
                ],
                [
                    'name' => 'Buyer-Seller Meet',
                    'row_type' => 'leaf',
                    'indicator_type' => 'Key Indicator',
                    'level' => 'State',
                    'source' => ['type' => 'deliverable', 'code' => 'buyer_seller_meets'],
                ],
                [
                    'name' => 'Events/ Seminars/ Workshops',
                    'row_type' => 'leaf',
                    'indicator_type' => 'Key Indicator',
                    'level' => 'State',
                    'source' => ['type' => 'deliverable', 'code' => 'events_seminars'],
                ],
            ],
        ],
        [
            'name' => 'Product Development',
            'row_type' => 'pillar',
            'children' => [
                [
                    'name' => 'Identification and Submission of Proposal for New Product Development',
                    'row_type' => 'leaf',
                    'indicator_type' => 'Non-Key',
                    'level' => 'State',
                    'source' => ['type' => 'none'],
                ],
            ],
        ],
        [
            'name' => 'Synergies Across Line Departments',
            'row_type' => 'pillar',
            'children' => [
                [
                    'name' => 'Stakeholder Consultation Workshop',
                    'row_type' => 'leaf',
                    'indicator_type' => 'Non-Key',
                    'level' => 'State',
                    'source' => ['type' => 'none'],
                ],
                [
                    'name' => 'Meeting of staff with Line Department at Spoke/Hub/State Level',
                    'row_type' => 'leaf',
                    'indicator_type' => 'Non-Key',
                    'level' => 'State',
                    'source' => ['type' => 'none'],
                ],
            ],
        ],
    ],

    'level_by_deliverable_code' => [
        'awareness_district' => 'State',
        'cfa' => 'Spoke & Hub',
        'onboarding' => 'Spoke & Hub',
        'lakhpati_block' => 'Spoke & Hub',
        'edp_workshop' => 'Spoke & Hub',
        'bst_sessions' => 'Spoke & Hub',
        'bst_participations' => 'Spoke & Hub',
        'business_registration' => 'Spoke & Hub',
        'fssai' => 'Spoke & Hub',
        'gst' => 'Spoke & Hub',
        'utdb_registration' => 'Spoke & Hub',
        'artisan_card' => 'Spoke & Hub',
        'trademark' => 'Spoke & Hub',
        'gi_seller' => 'Spoke & Hub',
        'market_link' => 'Spoke & Hub',
        'access_to_finance' => 'Spoke & Hub',
        'pitch_deck_prep' => 'Spoke & Hub',
        'pitchathon_demo' => 'State',
        'bmc' => 'Spoke & Hub',
        'acceleration_services' => 'Spoke & Hub',
        'social_media' => 'State',
        'case_studies' => 'State',
        'buyer_seller_meets' => 'State',
        'events_seminars' => 'State',
    ],

    'default_level' => 'Spoke & Hub',

    /*
    | MIS deliverable code => service catalog code(s) used on State targets (svc_* rows).
    */
    'target_code_aliases' => [
        'pitch_deck_prep' => ['pitch_deck'],
        'pitchathon_demo' => ['demo_days'],
        'market_link' => ['market_link', 'offline_connect'],
        'bmc' => ['bmc_canvas'],
        'business_registration' => [
            'udyam_registration', 'shop_establishment', 'company_registration',
            'uk_firm_registration', 'cooperative', 'already_registered',
        ],
    ],

];
