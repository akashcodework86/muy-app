<?php

/**
 * MUY Acceleration Services — MIS 7.2 initiation & co-incubation (Ankur Rawat).
 */
return [
    'submitter_emails' => array_values(array_filter(array_map(
        static fn (string $email): string => strtolower(trim($email)),
        explode(',', (string) env('ACCELERATION_SERVICES_SUBMITTER_EMAILS', 'ankur.rawat@pwc.com'))
    ))),
];
