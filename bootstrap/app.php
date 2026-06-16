<?php

use App\Http\Middleware\EnsureDistrictStaff;
use App\Http\Middleware\EnsureStaffDailyCheckInUser;
use App\Http\Middleware\EnsureDistrictStaffPhase3AttendanceNavVisible;
use App\Http\Middleware\EnsureHubAdmin;
use App\Http\Middleware\EnsureIncubatee;
use App\Http\Middleware\EnsureStateAdmin;
use App\Http\Middleware\EnsureStateStaff;
use App\Http\Middleware\EnsureTrainingPackageMonthPlanManager;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\TrackUserPresence;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;

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
            'staff_daily_check_in' => EnsureStaffDailyCheckInUser::class,
            'active' => EnsureUserIsActive::class,
            'training_package_month_plan_manager' => EnsureTrainingPackageMonthPlanManager::class,
        ]);

        $middleware->appendToGroup('web', TrackUserPresence::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (TokenMismatchException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Session expired. Refresh the page and try again.',
                ], 419);
            }

            if ($request->routeIs('login') && $request->isMethod('post')) {
                return redirect()
                    ->route('login')
                    ->withInput($request->only('email'))
                    ->withErrors([
                        'email' => 'Session expired. Refresh this page, then log in again.',
                    ]);
            }

            return redirect()
                ->route('login')
                ->withErrors([
                    'email' => 'Session expired. Refresh the page, then log in again.',
                ]);
        });

        $exceptions->render(function (PostTooLargeException $e, Request $request) {
            $limit = ini_get('post_max_size') ?: 'unknown';
            $message = 'Upload too large for this server (limit '.$limit.'). '
                .'Restart the dev server with: composer serve or ./serve.sh';

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'message' => $message,
                ], 413);
            }

            return redirect()
                ->back()
                ->withInput()
                ->withErrors([
                    'file' => $message,
                ]);
        });
    })->create();
