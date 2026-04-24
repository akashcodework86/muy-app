<?php

namespace App\Http\Controllers;

use App\Services\NotificationReminderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function __construct(
        private NotificationReminderService $reminders,
    ) {}

    public function index(Request $request): View
    {
        $notifications = $request->user()
            ->notifications()
            ->paginate(25);

        return view('notifications.index', [
            'notifications' => $notifications,
            'systemReminders' => $this->reminders->remindersFor($request->user()),
        ]);
    }

    /**
     * Mark notification read and redirect to the related CFA (mentorship) or back to the list.
     */
    public function open(Request $request, string $id): RedirectResponse
    {
        $notification = $request->user()->notifications()->where('id', $id)->firstOrFail();
        $notification->markAsRead();

        $data = $notification->data;
        $cfaId = $data['cfa_submission_id'] ?? null;
        if ($cfaId) {
            $url = match ($request->user()->role) {
                'state_admin' => route('admin.cfa.show', $cfaId),
                'district_staff' => route('staff.applications.show', $cfaId),
                'hub_admin' => route('hub.batches.cfa.show', $cfaId),
                default => null,
            };
            if ($url) {
                return redirect()->to($url);
            }
        }

        return redirect()->route('notifications.index');
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return back();
    }
}
