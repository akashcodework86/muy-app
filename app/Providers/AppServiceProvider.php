<?php

namespace App\Providers;

use App\Models\BusinessAccelerationPartnerOutreachEntry;
use App\Models\CaseStudyEntry;
use App\Models\DemoDay;
use App\Models\FundingSchematicPartnerOutreachEntry;
use App\Models\MediaCampaignEntry;
use App\Models\MuyNewsletterEntry;
use App\Models\LineDepartmentMeeting;
use App\Models\StakeholderCapacityBuildingSession;
use App\Models\StakeholderConsultationWorkshop;
use App\Services\NotificationReminderService;
use App\Services\StaffCheckInService;
use App\Support\StateAdminTheme;
use App\Support\StaffDailyCheckInAccess;
use Illuminate\Support\Facades\Route;
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
        Route::bind('cbsSession', fn (string $value) => StakeholderCapacityBuildingSession::query()->findOrFail($value));
        Route::bind('scwWorkshop', fn (string $value) => StakeholderConsultationWorkshop::query()->findOrFail($value));
        Route::bind('ldmMeeting', fn (string $value) => LineDepartmentMeeting::query()->findOrFail($value));
        Route::bind('baPartnerOutreach', fn (string $value) => BusinessAccelerationPartnerOutreachEntry::query()->findOrFail($value));
        Route::bind('demoDay', fn (string $value) => DemoDay::query()->findOrFail($value));
        Route::bind('fundingPartnerOutreach', fn (string $value) => FundingSchematicPartnerOutreachEntry::query()->findOrFail($value));
        Route::bind('caseStudyEntry', fn (string $value) => CaseStudyEntry::query()->findOrFail($value));
        Route::bind('muyNewsletter', fn (string $value) => MuyNewsletterEntry::query()->findOrFail($value));
        Route::bind('mediaCampaign', fn (string $value) => MediaCampaignEntry::query()->findOrFail($value));

        View::composer(['layouts.admin', 'dashboards.state-admin', 'dashboards.hub-admin'], function ($view): void {
            $user = auth()->user();
            if (! StateAdminTheme::appliesToRole($user?->role)) {
                return;
            }

            $theme = StateAdminTheme::resolve(request());
            $view->with('stateAdminTheme', $theme);
            $view->with('dashboardTheme', $theme);
        });

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

            if ($user->role === 'state_admin') {
                $view->with('stateAdminTheme', StateAdminTheme::resolve(request()));
            }
            if ($user->role === 'hub_admin') {
                $view->with('stateAdminTheme', StateAdminTheme::resolve(request()));
            }
        });
    }
}
