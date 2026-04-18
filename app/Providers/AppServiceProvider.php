<?php

namespace App\Providers;

use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        View::composer('partials.admin-topbar', function ($view): void {
            $user = auth()->user();
            if (! $user || ! in_array($user->role, ['state_admin', 'hub_admin', 'district_staff'], true)) {
                $view->with([
                    'notificationsPreview' => collect(),
                    'unreadNotificationCount' => 0,
                    'showNotificationBell' => false,
                ]);

                return;
            }

            $view->with([
                'notificationsPreview' => $user->notifications()->latest()->limit(8)->get(),
                'unreadNotificationCount' => $user->unreadNotifications()->count(),
                'showNotificationBell' => true,
            ]);
        });
    }
}
