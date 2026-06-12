<?php

/**
 * MIS 8.3 — Incubatees Pitch Deck Preparation (state-level entry by designated staff).
 *
 * Prefer submitter_user_ids (stable). submitter_names is a fallback until IDs are set.
 * Example .env: PITCH_DECK_PREP_SUBMITTER_IDS=67
 */
return [
    /*
    | Explicit pitch deck service catalog ids (e.g. services.id = 69, code pitch_decks).
    | Comma-separated in .env: PITCH_DECK_SERVICE_IDS=69
    */
    'service_ids' => array_values(array_unique(array_filter(array_map(
        static fn (string $id): int => (int) trim($id),
        explode(',', (string) env('PITCH_DECK_SERVICE_IDS', '69')),
    )))),

    'submitter_user_ids' => array_values(array_filter(array_map(
        static fn (string $id): int => (int) trim($id),
        explode(',', (string) env('PITCH_DECK_PREP_SUBMITTER_IDS', ''))
    ))),

    'submitter_names' => [
        'Govind Singh Dhami',
        'Govind Dhami',
    ],

    'support_modes' => [
        'virtual' => 'Virtual',
        'physical' => 'Physical',
        'hybrid' => 'Hybrid',
    ],
];
