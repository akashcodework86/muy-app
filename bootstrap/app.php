<?php

use App\Http\Middleware\EnsureDistrictStaff;
use App\Http\Middleware\EnsureDistrictStaffPhase3AttendanceNavVisible;
use App\Http\Middleware\EnsureHubAdmin;
use App\Http\Middleware\EnsureIncubatee;
use App\Http\Middleware\EnsureStateAdmin;
use App\Http\Middleware\EnsureStateStaff;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\TrackUserPresence;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Http\Request;

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
            'staff_phase3_attendance_nav' => EnsureDistrictStaffPhase3AttendanceNavVisible::class,
            'incubatee' => EnsureIncubatee::class,
            'active' => EnsureUserIsActive::class,
        ]);

        $middleware->appendToGroup('web', TrackUserPresence::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (PostTooLargeException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Upload too large. Add photos in smaller batches (up to 5 MB each) or increase PHP post_max_size.',
                ], 413);
            }

            return redirect()
                ->back()
                ->withInput()
                ->withErrors([
                    'visit_media' => 'Total upload is too large for the server limit. Photos are uploaded one at a time when you select them — submit again after they appear in the preview. If using php artisan serve, restart with: composer dev (higher limits) or set post_max_size to at least 64M in php.ini.',
                ]);
        });
    })->create();
