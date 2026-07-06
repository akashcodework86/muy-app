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
    |   potential_lakhpati_onboarding_count (2.1.1 — Phase 3 SHG/CBO/member; Legacy Lakhpati+member Yes),
    |   field_work_workshops (1.3), field_work_participants (1.3.1) — staff Field work visits,
    |   community_org_outreach_count (1.5) — hub Community organization outreach visits,
    |   marketing_partner_outreach_count (6.1) — state Marketing partner outreach log,
    |   marketing_partner_onboarded_count (6.2) — onboarded partners (LoA/LoI/MoU),
    |   business_acceleration_partners_outreach_count (7.1) — BA partner outreach (Ankur Rawat),
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
                    'source' => ['type' => 'field_work_workshops', 'deliverable_code' => 'field_work_workshops'],
                    'children' => [
                        [
                            'name' => 'Participants in Awareness cum Outreach activities',
                            'row_type' => 'leaf',
                            'indicator_type' => 'Non-Key',
                            'level' => 'Spoke & Hub',
                            'source' => ['type' => 'field_work_participants', 'deliverable_code' => 'field_work_participants'],
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
                    'source' => ['type' => 'community_org_outreach_count', 'deliverable_code' => 'community_org_outreach'],
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
                            'source' => [
                                'type' => 'potential_lakhpati_onboarding_count',
                                'deliverable_code' => 'potential_lakhpati_onboarding',
                            ],
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
                    'source' => ['type' => 'technical_training_sessions', 'deliverable_code' => 'technical_training_sessions'],
                    'children' => [
                        [
                            'name' => 'Technical Trainings to Potential Lakhpati Didis/ SHG Members/ CBOs',
                            'row_type' => 'leaf',
                            'indicator_type' => 'Non-Key',
                            'level' => 'Spoke & Hub',
                            'source' => [
                                'type' => 'technical_training_potential_lakhpati_sessions',
                                'deliverable_code' => 'technical_training_potential_lakhpati',
                            ],
                        ],
                    ],
                ],
                [
                    'name' => 'Capacity Building of stakeholders (REAP, USRLM, Other Line department staff)',
                    'row_type' => 'leaf',
                    'indicator_type' => 'Key Indicator',
                    'level' => 'State',
                    'source' => ['type' => 'capacity_building_stakeholder_sessions', 'deliverable_code' => 'capacity_building_stakeholders'],
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
                            'indicator_type' => 'Non-Key',
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
                            'source' => ['type' => 'deliverable', 'code' => 'advance_licensing_support'],
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
                    'source' => ['type' => 'service', 'code' => 'specialized_mentorship_support'],
                ],
                [
                    'name' => 'Mentorship Support through online portal',
                    'row_type' => 'leaf',
                    'indicator_type' => 'Non-Key',
                    'level' => 'State',
                    'source' => ['type' => 'deliverable', 'code' => 'mentorship_online_portal'],
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
                    'source' => ['type' => 'marketing_partner_outreach_count', 'deliverable_code' => 'partners_outreach'],
                ],
                [
                    'name' => 'Marketing Partners Onboarded through (LoA/LoI/MoU)',
                    'row_type' => 'leaf',
                    'indicator_type' => 'Non-Key',
                    'level' => 'State',
                    'source' => ['type' => 'marketing_partner_onboarded_count', 'deliverable_code' => 'marketing_partners_onboarded'],
                ],
                [
                    'name' => 'Incubatees linked to online/offline Market',
                    'row_type' => 'leaf',
                    'indicator_type' => 'Key Indicator',
                    'level' => 'Spoke & Hub',
                    'source' => ['type' => 'market_linkage_incubatees'],
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
                    'source' => ['type' => 'business_acceleration_partners_outreach_count', 'deliverable_code' => 'business_acceleration_partners_outreach'],
                ],
                [
                    'name' => 'Initiation of acceleration and co-incubation services',
                    'row_type' => 'leaf',
                    'indicator_type' => 'Key Indicator',
                    'level' => 'State',
                    'source' => ['type' => 'acceleration_services_initiation_count', 'deliverable_code' => 'acceleration_services'],
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
                    'source' => [
                        'type' => 'schematic_convergence_services',
                        'deliverable_code' => 'schematic_convergence',
                        'bifurcation' => [
                            ['name' => 'PMEGP', 'codes' => ['pmegp', 'p_m_e_g_p']],
                            ['name' => 'MSY 2.0', 'codes' => ['msy_nano', 'm_s_y_2_0']],
                            ['name' => 'PMFME', 'codes' => ['pmfme', 'p_m_f_m_e']],
                            ['name' => 'DDUGAYVY - Homestay', 'codes' => ['ddu_homestay', 'deen_dayal_upadhyay_grah_awas_vikas_yojana_d_d_u_g_a_v_y_homestay']],
                            ['name' => 'Veer Chandra Singh Garhwali Self Employment Scheme', 'codes' => ['vcsg', 'veer_chandra_singh_garhwali_self_employment_scheme']],
                            ['name' => 'Other Convergence Support', 'codes' => ['support_application', 'other_convergence_support']],
                            ['name' => 'Support through REAP', 'codes' => ['support_muy_incubatee_reap']],
                        ],
                    ],
                ],
                [
                    'name' => 'Support to MUY Incubatee through Reap',
                    'row_type' => 'leaf',
                    'indicator_type' => 'Non-Key',
                    'level' => 'Spoke & Hub',
                    'source' => ['type' => 'reap_support_services'],
                ],
                [
                    'name' => 'Incubatees Pitch Deck Preparation',
                    'row_type' => 'leaf',
                    'indicator_type' => 'Key Indicator',
                    'level' => 'State',
                    'source' => ['type' => 'pitch_deck_combined', 'deliverable_code' => 'pitch_deck_prep'],
                ],
                [
                    'name' => 'Demo Days',
                    'row_type' => 'leaf',
                    'indicator_type' => 'Key Indicator',
                    'level' => 'State',
                    'source' => ['type' => 'demo_days_count', 'deliverable_code' => 'pitchathon_demo'],
                ],
                [
                    'name' => 'No of Partners outreach',
                    'row_type' => 'leaf',
                    'indicator_type' => 'Non-Key',
                    'level' => 'State',
                    'source' => ['type' => 'funding_schematic_partners_outreach_count', 'deliverable_code' => 'funding_schematic_partners_outreach'],
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
                        'type' => 'service',
                        'code' => 'other_support_services_labelling_packaging_logo_designing_etc',
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
                    'source' => ['type' => 'muy_newsletter_count', 'deliverable_code' => 'muy_newsletter'],
                ],
                [
                    'name' => 'Newspaper Ads and Radio promotion campaigns',
                    'row_type' => 'leaf',
                    'indicator_type' => 'Non-Key',
                    'level' => 'State',
                    'source' => ['type' => 'media_campaigns_count', 'deliverable_code' => 'media_campaigns'],
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
                    'source' => ['type' => 'deliverable', 'code' => 'product_development_proposal'],
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
                    'source' => ['type' => 'stakeholder_consultation_workshop_sessions', 'deliverable_code' => 'stakeholder_consultation_workshop'],
                ],
                [
                    'name' => 'Meeting of staff with Line Department at Spoke/Hub/State Level',
                    'row_type' => 'leaf',
                    'indicator_type' => 'Non-Key',
                    'level' => 'State',
                    'source' => ['type' => 'line_department_meeting_sessions', 'deliverable_code' => 'line_department_meeting'],
                ],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Hub-only targets (official plan: Almora + Pauri Garhwal hub lines)
    |--------------------------------------------------------------------------
    |
    | District staff see "HUB" instead of a spoke number on Deliverables.
    |
    */
    'hub_target_serials' => [
        '1.5',
        '3.3',
        '4.2.3',
        '4.2.5',
        '4.2.6',
    ],

    'hub_target_deliverable_codes' => [
        'community_org_outreach',
        'technical_training_sessions',
        'utdb_registration',
        'trademark',
        'gi_seller',
    ],

    /*
    | Hub monthly targets are owned by the hub seat district only (not every spoke).
    */
    'hub_target_primary_district_slugs' => [
        'almora',
        'pauri-garhwal',
    ],

    /*
    |--------------------------------------------------------------------------
    | Need-based indicators (no fixed annual target on Deliverables page)
    |--------------------------------------------------------------------------
    |
    | When no target is set, the Target column shows "(Need Based)".
    |
    */
    'need_based_serials' => [
        '3.3.1',
        '4.2.7',
        '5.2',
        '9.2',
    ],

    'need_based_deliverable_codes' => [
        'technical_training_potential_lakhpati',
        'advance_licensing_support',
        'mentorship_online_portal',
    ],

    'need_based_service_codes' => [
        'other_support_services_labelling_packaging_logo_designing_etc',
    ],

    /*
    |--------------------------------------------------------------------------
    | State-owned targets on Deliverables (district staff see "State" label)
    |--------------------------------------------------------------------------
    |
    | District staff see the State label instead of spoke/hub monthly numbers.
    |
    */
    'state_target_label_serials' => [
        '12.2',
    ],

    'state_target_label_deliverable_codes' => [
        'line_department_meeting',
    ],

    'level_by_deliverable_code' => [
        'awareness_district' => 'State',
        'field_work_workshops' => 'Spoke & Hub',
        'field_work_participants' => 'Spoke & Hub',
        'technical_training_sessions' => 'Spoke & Hub',
        'technical_training_potential_lakhpati' => 'Spoke & Hub',
        'capacity_building_stakeholders' => 'State',
        'cfa' => 'Spoke & Hub',
        'onboarding' => 'Spoke & Hub',
        'potential_lakhpati_onboarding' => 'Spoke & Hub',
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
        'pitch_deck_prep' => 'State',
        'mentorship_online_portal' => 'State',
        'advance_licensing_support' => 'Spoke & Hub',
        'pitchathon_demo' => 'State',
        'funding_schematic_partners_outreach' => 'State',
        'bmc' => 'Spoke & Hub',
        'acceleration_services' => 'State',
        'business_acceleration_partners_outreach' => 'State',
        'social_media' => 'State',
        'case_studies' => 'State',
        'muy_newsletter' => 'State',
        'media_campaigns' => 'State',
        'buyer_seller_meets' => 'State',
        'events_seminars' => 'State',
        'partners_outreach' => 'State',
        'marketing_partners_onboarded' => 'State',
        'product_development_proposal' => 'State',
        'stakeholder_consultation_workshop' => 'State',
        'line_department_meeting' => 'State',
    ],

    'default_level' => 'Spoke & Hub',

    /*
    |--------------------------------------------------------------------------
    | District / hub monthly target page — allowed MIS indicators only
    |--------------------------------------------------------------------------
    |
    | scope: district = Hub & Spoke tab (13 districts × M1–M12)
    |         hub      = Hub only tab (Kumaon / Garhwal × M1–M12)
    |
    */
    'district_hub_monthly_indicators' => [
        [
            'serial' => '1.3',
            'code' => 'field_work_workshops',
            'name' => 'No. of Awareness cum Outreach activities for SHG members/Potential Lakhpati Didis/ SHGs/CBOs etc.',
            'mis_entry_label' => 'Awareness cum Outreach activities (count)',
            'scope' => 'district',
            'sort_order' => 91,
        ],
        [
            'serial' => '1.3.1',
            'code' => 'field_work_participants',
            'name' => 'Participants in Awareness cum Outreach activities',
            'mis_entry_label' => 'Outreach participants (female)',
            'scope' => 'district',
            'sort_order' => 92,
        ],
        [
            'serial' => '3.1',
            'code' => 'bst_sessions',
            'name' => 'Business Skills Training Sessions',
            'mis_entry_label' => 'BST sessions conducted',
            'scope' => 'district',
            'sort_order' => 93,
        ],
        [
            'serial' => '3.2',
            'code' => 'bst_participations',
            'name' => 'Incubatees taken Part in Business Modules Training',
            'mis_entry_label' => 'BST module participations',
            'scope' => 'district',
            'sort_order' => 94,
        ],
        [
            'serial' => '3.3',
            'code' => 'technical_training_sessions',
            'name' => 'Technical Trainings to Incubatees',
            'mis_entry_label' => 'Technical training sessions',
            'scope' => 'district',
            'sort_order' => 95,
        ],
        [
            'serial' => '3.3.1',
            'code' => 'technical_training_potential_lakhpati',
            'name' => 'Technical Trainings to Potential Lakhpati Didis/ SHG Members/ CBOs',
            'mis_entry_label' => 'Technical training sessions (Lakhpati / SHG / CBO)',
            'scope' => 'district',
            'sort_order' => 96,
        ],
        [
            'serial' => '3.4',
            'code' => 'capacity_building_stakeholders',
            'name' => 'Capacity Building of stakeholders (REAP, USRLM, Other Line department staff)',
            'mis_entry_label' => 'Capacity building of stakeholders',
            'scope' => 'hub',
            'sort_order' => 97,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | State monthly target page — allowed MIS indicators only (M1–M12 at state)
    |--------------------------------------------------------------------------
    */
    'state_monthly_indicators' => [
        [
            'serial' => '3.3',
            'category_serial' => '3',
            'category_name' => 'Training & Capacity Building',
            'code' => 'technical_training_sessions',
            'name' => 'Technical Trainings to Incubatees',
            'mis_entry_label' => 'Technical training sessions',
            'sort_order' => 95,
        ],
        [
            'serial' => '3.3.1',
            'category_serial' => '3',
            'category_name' => 'Training & Capacity Building',
            'code' => 'technical_training_potential_lakhpati',
            'name' => 'Technical Trainings to Potential Lakhpati Didis/ SHG Members/ CBOs',
            'mis_entry_label' => 'Technical training sessions (Lakhpati / SHG / CBO)',
            'sort_order' => 96,
        ],
        [
            'serial' => '3.4',
            'category_serial' => '3',
            'category_name' => 'Training & Capacity Building',
            'code' => 'capacity_building_stakeholders',
            'name' => 'Capacity Building of stakeholders (REAP, USRLM, Other Line department staff)',
            'mis_entry_label' => 'Capacity building of stakeholders',
            'sort_order' => 97,
        ],
        [
            'serial' => '4.2.7',
            'category_serial' => '4',
            'category_name' => 'Business Formalization',
            'code' => 'advance_licensing_support',
            'name' => 'Advance Licensing Support (Mandi Licensing, Lab Test etc.)',
            'mis_entry_label' => 'Advance licensing support',
            'sort_order' => 98,
        ],
        [
            'serial' => '5.2',
            'category_serial' => '5',
            'category_name' => 'Mentorship',
            'code' => 'mentorship_online_portal',
            'name' => 'Mentorship Support through online portal',
            'mis_entry_label' => 'Mentorship support (online portal)',
            'sort_order' => 99,
        ],
        [
            'serial' => '6.1',
            'category_serial' => '6',
            'category_name' => 'Partnership & Forward Linkages',
            'code' => 'partners_outreach',
            'name' => 'No of Partners outreach',
            'mis_entry_label' => 'Marketing partner outreach',
            'sort_order' => 100,
        ],
        [
            'serial' => '6.2',
            'category_serial' => '6',
            'category_name' => 'Partnership & Forward Linkages',
            'code' => 'marketing_partners_onboarded',
            'name' => 'Marketing Partners Onboarded through (LoA/LoI/MoU)',
            'mis_entry_label' => 'Marketing partners onboarded (LoA/LoI/MoU)',
            'sort_order' => 101,
        ],
        [
            'serial' => '7.1',
            'category_serial' => '7',
            'category_name' => 'Business Acceleration Services',
            'code' => 'business_acceleration_partners_outreach',
            'name' => 'No of Partners outreach (Business Acceleration Services)',
            'mis_entry_label' => 'BA partners outreach',
            'sort_order' => 102,
        ],
        [
            'serial' => '7.2',
            'category_serial' => '7',
            'category_name' => 'Business Acceleration Services',
            'code' => 'acceleration_services',
            'name' => 'Initiation of acceleration and co-incubation services',
            'mis_entry_label' => 'Acceleration / co-incubation initiation',
            'sort_order' => 103,
        ],
        [
            'serial' => '8.3',
            'category_serial' => '8',
            'category_name' => 'Funding & Schematic Convergence',
            'code' => 'pitch_deck_prep',
            'name' => 'Incubatees Pitch Deck Preparation',
            'mis_entry_label' => 'Pitch deck preparations',
            'sort_order' => 104,
        ],
        [
            'serial' => '8.4',
            'category_serial' => '8',
            'category_name' => 'Funding & Schematic Convergence',
            'code' => 'pitchathon_demo',
            'name' => 'Demo Days',
            'mis_entry_label' => 'Demo days',
            'sort_order' => 105,
        ],
        [
            'serial' => '8.5',
            'category_serial' => '8',
            'category_name' => 'Funding & Schematic Convergence',
            'code' => 'funding_schematic_partners_outreach',
            'name' => 'No of Partners outreach (Funding & Schematic Convergence)',
            'mis_entry_label' => 'Funding partners outreach',
            'sort_order' => 106,
        ],
        [
            'serial' => '9.2',
            'category_serial' => '9',
            'category_name' => 'Other Support Services',
            'service_code' => 'other_support_services_labelling_packaging_logo_designing_etc',
            'name' => 'Other Support Services - Labelling, Packaging, Logo Designing etc.',
            'mis_entry_label' => 'Labelling, packaging, logo designing',
            'sort_order' => 107,
        ],
        [
            'serial' => '10.1',
            'category_serial' => '10',
            'category_name' => 'Branding, Communication & Knowledge Management',
            'code' => 'social_media',
            'name' => 'Social Media Post',
            'mis_entry_label' => 'Social media posts',
            'sort_order' => 108,
        ],
        [
            'serial' => '10.2',
            'category_serial' => '10',
            'category_name' => 'Branding, Communication & Knowledge Management',
            'code' => 'case_studies',
            'name' => 'Preparation of Case Studies and Testimonials',
            'mis_entry_label' => 'Case studies and testimonials',
            'sort_order' => 109,
        ],
        [
            'serial' => '10.3',
            'category_serial' => '10',
            'category_name' => 'Branding, Communication & Knowledge Management',
            'code' => 'muy_newsletter',
            'name' => 'MUY Newsletter',
            'mis_entry_label' => 'MUY newsletter',
            'sort_order' => 110,
        ],
        [
            'serial' => '10.4',
            'category_serial' => '10',
            'category_name' => 'Branding, Communication & Knowledge Management',
            'code' => 'media_campaigns',
            'name' => 'Newspaper Ads and Radio promotion campaigns',
            'mis_entry_label' => 'Newspaper / radio campaigns',
            'sort_order' => 111,
        ],
        [
            'serial' => '10.5',
            'category_serial' => '10',
            'category_name' => 'Branding, Communication & Knowledge Management',
            'code' => 'buyer_seller_meets',
            'name' => 'Buyer-Seller Meet',
            'mis_entry_label' => 'Buyer-seller meets',
            'sort_order' => 112,
        ],
        [
            'serial' => '10.6',
            'category_serial' => '10',
            'category_name' => 'Branding, Communication & Knowledge Management',
            'code' => 'events_seminars',
            'name' => 'Events/ Seminars/ Workshops',
            'mis_entry_label' => 'Events / seminars / workshops',
            'sort_order' => 113,
        ],
        [
            'serial' => '11.1',
            'category_serial' => '11',
            'category_name' => 'Product Development',
            'code' => 'product_development_proposal',
            'name' => 'Identification and Submission of Proposal for New Product Development',
            'mis_entry_label' => 'Product development proposals',
            'sort_order' => 114,
        ],
        [
            'serial' => '12.1',
            'category_serial' => '12',
            'category_name' => 'Synergies Across Line Departments',
            'code' => 'stakeholder_consultation_workshop',
            'name' => 'Stakeholder Consultation Workshop',
            'mis_entry_label' => 'Stakeholder consultation workshops',
            'sort_order' => 115,
        ],
        [
            'serial' => '12.2',
            'category_serial' => '12',
            'category_name' => 'Synergies Across Line Departments',
            'code' => 'line_department_meeting',
            'name' => 'Meeting of staff with Line Department at Spoke/Hub/State Level',
            'mis_entry_label' => 'Line department meetings',
            'sort_order' => 116,
        ],
    ],

    /*
    | MIS deliverable code => service catalog code(s) used on State targets (svc_* rows).
    */
    'target_code_aliases' => [
        'pitch_deck_prep' => ['pitch_deck', 'pitch_decks'],
        'advance_licensing_support' => ['mandi_license', 'seed_license', 'pan_card', 'lab_testing'],
        'pitchathon_demo' => ['demo_days'],
        'funding_schematic_partners_outreach' => ['funding_partners_outreach'],
        'market_link' => ['market_link', 'offline_connect'],
        'bmc' => ['bmc_canvas', 'business_model_canvas', 'bmc'],
        'business_registration' => [
            'udyam_registration', 'shop_establishment', 'company_registration',
            'uk_firm_registration', 'cooperative', 'already_registered',
        ],
        'fssai' => ['fssai_registration', 'fssai_renewal', 'fssai_registration_renewal'],
        'gst' => ['gst_registration'],
        'artisan_card' => ['artisan_card_registration'],
        'utdb_registration' => ['utdb_registration_bf', 'utdb_registration', 'utdb'],
        'trademark' => ['trademark_registration'],
        'gi_seller' => ['gi_seller_registration'],
    ],

    /*
    | Match approved service cases to MIS rows when catalog code differs (name / label fallback).
    */
    'achievement_deliverable_keywords' => [
        'pitch_deck_prep' => ['pitch deck'],
        'fssai' => ['fssai'],
        'gst' => ['gst'],
        'bmc' => ['business model canvas', 'bmc'],
        'artisan_card' => ['artisan card', 'artisan_card'],
        'utdb_registration' => ['utdb'],
        'trademark' => ['trademark'],
        'gi_seller' => ['gi seller'],
        'business_registration' => ['udyam', 'shop & establishment', 'company registration', 'cooperative'],
    ],

];
