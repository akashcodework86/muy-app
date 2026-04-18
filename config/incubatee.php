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

];
