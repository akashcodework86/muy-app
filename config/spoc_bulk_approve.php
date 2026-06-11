<?php

/**
 * SPOC service-case bulk approval — restricted to designated state staff emails.
 *
 * Comma-separated list in .env, e.g. SPOC_BULK_APPROVE_EMAILS=akash.b.shrivastava@pwc.com
 */
return [
    'allowed_emails' => array_values(array_filter(array_map(
        static fn (string $email): string => strtolower(trim($email)),
        explode(',', (string) env('SPOC_BULK_APPROVE_EMAILS', 'akash.b.shrivastava@pwc.com'))
    ))),
];
