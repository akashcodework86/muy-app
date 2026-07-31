<?php

/**
 * Branding, Communication & Knowledge Management entries (MIS 10.2–10.4) — Sanjna Mishra only.
 */
return [
    'submitter_user_ids' => array_values(array_filter(array_map(
        static fn (string $id): int => (int) trim($id),
        explode(',', (string) env('BRANDING_COMMUNICATION_SUBMITTER_IDS', ''))
    ))),

    'submitter_names' => [
        'Sanjna Mishra',
    ],

    'story_types' => [
        'case_study' => 'Case study',
        'testimonial' => 'Testimonial',
    ],

    'distribution_modes' => [
        'email' => 'Email',
        'whatsapp' => 'WhatsApp',
        'pdf' => 'PDF',
        'other' => 'Other',
    ],

    'media_types' => [
        'newspaper' => 'Newspaper',
        'radio' => 'Radio',
        'both' => 'Both',
    ],

    'media_campaign_max_attachments' => 5,

    'case_study_max_attachments' => 10,
];
