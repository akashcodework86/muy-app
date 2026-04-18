<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default password for newly provisioned incubatee portal accounts
    |--------------------------------------------------------------------------
    |
    | Set INCUBATEE_DEFAULT_PASSWORD in .env. Used by `incubatees:provision-users`.
    |
    */
    'default_password' => env('INCUBATEE_DEFAULT_PASSWORD', 'Muy@2026'),

    /*
    |--------------------------------------------------------------------------
    | Placeholder domain when payload has no email (legacy Phase 2, etc.)
    |--------------------------------------------------------------------------
    |
    | Login ID becomes: incubatee-{cfa_submission_id}@{domain}
    | Leave empty to use APP_URL host (e.g. ukrbi.in). Set explicitly if you prefer
    | a dedicated noreply subdomain.
    |
    */
    'synthetic_email_domain' => env('INCUBATEE_SYNTHETIC_EMAIL_DOMAIN'),

];
