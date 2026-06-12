<?php

/**
 * Marketing partner outreach (MIS 6.1 / 6.2) — state-level entries by Sanjna Mishra only.
 *
 * Prefer submitter_user_ids (stable). submitter_names is a fallback until IDs are set.
 * Example .env: PARTNER_OUTREACH_SUBMITTER_IDS=12
 */
return [
    'submitter_user_ids' => array_values(array_filter(array_map(
        static fn (string $id): int => (int) trim($id),
        explode(',', (string) env('PARTNER_OUTREACH_SUBMITTER_IDS', ''))
    ))),

    'submitter_names' => [
        'Sanjna Mishra',
    ],
];
