<?php

/**
 * MIS 8.4 Demo Days & 8.5 Partners outreach (Funding & Schematic Convergence) — Govind Singh Dhami.
 *
 * Prefer submitter_user_ids (stable). submitter_names is a fallback until IDs are set.
 * Example .env: FUNDING_SCHEMATIC_SUBMITTER_IDS=67
 */
return [
    'submitter_user_ids' => array_values(array_filter(array_map(
        static fn (string $id): int => (int) trim($id),
        explode(',', (string) env('FUNDING_SCHEMATIC_SUBMITTER_IDS', ''))
    ))),

    'submitter_names' => [
        'Govind Singh Dhami',
        'Govind Dhami',
    ],

    'event_modes' => [
        'physical' => 'Physical',
        'virtual' => 'Virtual',
        'hybrid' => 'Hybrid',
    ],

    'event_outcomes' => [
        'participated' => 'Participated',
        'shortlisted' => 'Shortlisted',
        'funding_interest' => 'Funding interest',
        'no_outcome' => 'No outcome yet',
    ],
];
