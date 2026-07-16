<?php

/**
 * MUY Acceleration Services — MIS 7.2 initiation & co-incubation (Ankur Rawat).
 */
return [
    'submitter_emails' => array_values(array_filter(array_map(
        static fn (string $email): string => strtolower(trim($email)),
        explode(',', (string) env('ACCELERATION_SERVICES_SUBMITTER_EMAILS', 'ankur.rawat@pwc.com'))
    ))),

    /*
     * Final approver (maker–checker): district entries need first review by the
     * state SPOC (submitter_emails), then final approval here. State SPOC
     * entries go straight to final approval.
     */
    'final_approver_email' => strtolower(trim((string) env(
        'ACCELERATION_SERVICES_FINAL_APPROVER_EMAIL',
        env('MIS_FIELD_APPROVER_EMAIL', 'aadil.ishrat@pwc.com')
    ))),
];
