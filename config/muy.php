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

];
