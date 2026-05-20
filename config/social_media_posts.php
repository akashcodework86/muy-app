<?php

/**
 * Social Media Post log — state-level entries by designated state staff only.
 *
 * Prefer submitter_user_ids (stable). submitter_names is a fallback until IDs are set.
 * Example .env: SOCIAL_MEDIA_POST_SUBMITTER_IDS=12
 */
return [
    'submitter_user_ids' => array_values(array_filter(array_map(
        static fn (string $id): int => (int) trim($id),
        explode(',', (string) env('SOCIAL_MEDIA_POST_SUBMITTER_IDS', ''))
    ))),

    'submitter_names' => [
        'Sanjna Mishra',
    ],
];
