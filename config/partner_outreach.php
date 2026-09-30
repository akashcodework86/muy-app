<?php

/**
 * Marketing partner outreach (MIS 6.1 / 6.2) — state-level entries by Sanjna Mishra only.
 *
 * Prefer submitter_user_ids (stable). submitter_names is a fallback until IDs are set.
 * Example .env: PARTNER_OUTREACH_SUBMITTER_IDS=12
 */
return [
    /*
    | MIS 6.2 — approved service cases for marketing partners onboarded (LoA/LoI/MoU).
    | Comma-separated in .env: MARKETING_PARTNER_ONBOARDED_SERVICE_IDS=83
    */
    'onboarded_service_ids' => array_values(array_unique(array_filter(array_map(
        static fn (string $id): int => (int) trim($id),
        explode(',', (string) env('MARKETING_PARTNER_ONBOARDED_SERVICE_IDS', '83')),
    )))),

    /*
    | Approved service cases that are not LoA/LoI/MoU onboardings.
    | MIS 6.2 keeps Basanti Devi (case 6701, 50405007) and
    | Reena Bhatt / REENA BHATT AS SHIVALIK SHG (case 3984, RBI1763624868).
    */
    'onboarded_excluded_service_case_ids' => [
        3067,
        3813,
        4142,
        4143,
        4144,
        6699,
        6700,
        6721,
    ],

    'submitter_user_ids' => array_values(array_filter(array_map(
        static fn (string $id): int => (int) trim($id),
        explode(',', (string) env('PARTNER_OUTREACH_SUBMITTER_IDS', ''))
    ))),

    'submitter_names' => [
        'Sanjna Mishra',
    ],
];
