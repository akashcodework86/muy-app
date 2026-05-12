<?php

/**
 * Simple app-wide feature flags.
 *
 * Re-enable a flag by setting the corresponding env variable to true in .env
 * (or by changing the default here) and then running `php artisan config:clear`.
 */
return [

    /*
    |--------------------------------------------------------------------------
    | Legacy service case assignment (Add case → Mark completed)
    |--------------------------------------------------------------------------
    | The original "Service delivery (cases)" section shown on each CFA
    | application detail page to district staff. It's currently hidden while
    | the maker-checker redesign (per-service custom submission form, SPOC
    | approval, doc upload, delivered_on dating, audit trail) is being built.
    |
    | Turning this OFF only hides the UI; existing service_cases rows in the
    | DB are untouched and direct POSTs to the controller are blocked with a
    | friendly message. Set FEATURE_SERVICE_CASE_ASSIGNMENT=true in .env to
    | re-enable the legacy UI.
    */
    'service_case_assignment' => env('FEATURE_SERVICE_CASE_ASSIGNMENT', false),

    /*
    |--------------------------------------------------------------------------
    | Training package extra sessions
    |--------------------------------------------------------------------------
    | District staff can record extra sessions outside the monthly target.
    | Hidden by default while the feature is being refined.
    */
    'training_package_extra_sessions' => env('FEATURE_TRAINING_PACKAGE_EXTRA_SESSIONS', false),

];
