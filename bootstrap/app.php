<?php

use App\Http\Middleware\EnsureDistrictStaff;
use App\Http\Middleware\EnsureHubAdmin;
use App\Http\Middleware\EnsureIncubatee;
use App\Http\Middleware\EnsureStateAdmin;
use App\Http\Middleware\EnsureStateStaff;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\TrackUserPresence;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands([
        __DIR__.'/../app/Console/Commands',
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'state_admin' => EnsureStateAdmin::class,
            'state_staff' => EnsureStateStaff::class,
            'hub_admin' => EnsureHubAdmin::class,
            'district_staff' => EnsureDistrictStaff::class,
            'incubatee' => EnsureIncubatee::class,
            'active' => EnsureUserIsActive::class,
        ]);

        $middleware->appendToGroup('web', TrackUserPresence::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
