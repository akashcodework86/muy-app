<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Programme structure wipe (one-shot URL)
    |--------------------------------------------------------------------------
    |
    | GET /programme-wipe-run?key=<this value> runs `programme:wipe-structure`.
    | Optional: &app_settings=1. Remove the route after use. Never commit a real secret.
    |
    */
    'programme_wipe_secret' => env('PROGRAMME_WIPE_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | Pending migrations (one-shot URL)
    |--------------------------------------------------------------------------
    |
    | GET /migration-run?key=<this value> runs `php artisan migrate --force`.
    | Remove the route after use. Never commit a real secret.
    |
    */
    'migration_run_secret' => env('MIGRATION_RUN_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | Training package monthly session plan managers
    |--------------------------------------------------------------------------
    |
    | Only these state admin / state staff emails can open and edit Training
    | package session targets (admin/training-package-month-plans). Their planned required
    | sessions feed the BST target on the Deliverables page.
    |
    */
    'training_package_month_plan_managers' => [
        'aadil.ishrat@pwc.com',
    ],

];
