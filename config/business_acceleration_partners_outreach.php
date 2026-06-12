<?php

/**
 * Business Acceleration Services — MIS 7.1 Partners outreach (Ankur Rawat).
 *
 * Prefer submitter_user_ids (stable). submitter_names is a fallback until IDs are set.
 * Example .env: BA_PARTNERS_OUTREACH_SUBMITTER_IDS=42
 */
return [
    'submitter_user_ids' => array_values(array_filter(array_map(
        static fn (string $id): int => (int) trim($id),
        explode(',', (string) env('BA_PARTNERS_OUTREACH_SUBMITTER_IDS', ''))
    ))),

    'submitter_names' => [
        'Ankur Rawat',
    ],
];
