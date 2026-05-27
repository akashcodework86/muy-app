<?php

namespace App\Providers;

use App\Services\NotificationReminderService;
use App\Services\StaffCheckInService;
use App\Support\StaffDailyCheckInAccess;
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
        View::composer('partials.staff-daily-check-in-reminder', function ($view): void {
            $user = auth()->user();
            $show = $user && app(StaffCheckInService::class)->shouldShowReminder($user);

            $view->with('showStaffDailyCheckInReminder', $show);
        });

        View::composer('partials.admin-topbar', function ($view): void {
            $user = auth()->user();
            if (! $user || ! in_array($user->role, ['state_admin', 'hub_admin', 'district_staff', 'state_staff'], true)) {
                $view->with([
                    'notificationsPreview' => collect(),
                    'unreadNotificationCount' => 0,
                    'dbUnreadNotificationCount' => 0,
                    'showNotificationBell' => false,
                ]);

                return;
            }

            $dbNotifications = $user->notifications()->latest()->limit(8)->get();
            $reminders = app(NotificationReminderService::class)->remindersFor($user);
            $now = now();

            $preview = collect($reminders)->map(function (array $r) use ($now) {
                return [
                    'title' => $r['title'],
                    'body' => $r['body'],
                    'link' => $r['link'],
                    'is_unread' => (bool) ($r['unread'] ?? true),
                    'time_human' => 'now',
                    'is_reminder' => true,
                    'id' => null,
                ];
            })->values();

            $preview = $preview->concat(
                $dbNotifications->map(function ($n) {
                    $d = $n->data ?? [];

                    return [
                        'title' => $d['title'] ?? 'Notification',
                        'body' => $d['body'] ?? '',
                        'link' => route('notifications.open', $n->id),
                        'is_unread' => $n->read_at === null,
                        'time_human' => $n->created_at?->timezone(config('app.timezone'))->diffForHumans(),
                        'is_reminder' => false,
                        'id' => $n->id,
                    ];
                })->values()
            )->take(8)->values();

            $dbUnread = $user->unreadNotifications()->count();

            $view->with([
                'notificationsPreview' => $preview,
                'unreadNotificationCount' => $dbUnread + count($reminders),
                'dbUnreadNotificationCount' => $dbUnread,
                'showNotificationBell' => true,
            ]);
        });
    }
}
