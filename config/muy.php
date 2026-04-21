<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Programme structure wipe (admin UI)
    |--------------------------------------------------------------------------
    |
    | When set (min. 8 characters), state admins can POST from the danger page
    | to run `programme:wipe-structure`. Never commit a real secret; use .env only.
    |
    */
    'programme_wipe_secret' => env('PROGRAMME_WIPE_SECRET'),

];
