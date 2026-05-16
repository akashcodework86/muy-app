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

];
