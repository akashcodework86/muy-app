<?php

namespace App\Http\Controllers;

use App\Models\StaffCheckIn;
use App\Services\StaffCheckInService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StaffCheckInController extends Controller
{
    public function __construct(
        private readonly StaffCheckInService $checkInService,
    ) {}

    public function index(Request $request): View
    {
        $user = auth()->user();
        $todayCheckIn = $this->checkInService->todayForUser($user);

        $monthInput = (string) $request->query('month', now()->format('Y-m'));
        try {
            $month = Carbon::createFromFormat('Y-m', $monthInput)->startOfMonth();
        } catch (\Throwable) {
            $month = now()->startOfMonth();
        }

        $monthlyGrid = $this->checkInService->adminMonthlyGrid($month, null, null, null, (int) $user->id);
        $today = now()->startOfDay();
        $canReportTodayAbsent = $this->checkInService->canSubmitAbsenceReason($user, $today);
        $todayAbsenceReason = \Illuminate\Support\Facades\Schema::hasTable('staff_absence_reasons')
            ? $user->staffAbsenceReasons()
                ->whereDate('absence_date', $today)
                ->value('reason')
            : null;

        $history = $this->checkInService->attendanceHistoryForUser($user, 60);

        $stats = [
            'total_days' => $user->staffCheckIns()->count(),
            'this_month' => (int) ($monthlyGrid['rows']->first()['present_count'] ?? 0),
            'absent_month' => (int) ($monthlyGrid['rows']->first()['absent_count'] ?? 0),
        ];

        return view('staff-daily-check-in.index', [
            'todayCheckIn' => $todayCheckIn,
            'showReminder' => $this->checkInService->shouldShowReminder($user),
            'history' => $history,
            'stats' => $stats,
            'month' => $month,
            'monthlyGrid' => $monthlyGrid,
            'canReportTodayAbsent' => $canReportTodayAbsent,
            'todayAbsenceReason' => $todayAbsenceReason,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($this->checkInService->todayForUser($user) !== null) {
            return redirect()
                ->route('staff-daily-check-in.index')
                ->with('status', 'You have already marked attendance for today.');
        }

        $validated = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'accuracy_m' => ['nullable', 'numeric', 'min:0', 'max:50000'],
        ]);

        StaffCheckIn::query()->create([
            'user_id' => $user->id,
            'check_in_date' => $this->checkInService->todayDate()->toDateString(),
            'marked_at' => now(),
            'latitude' => $validated['latitude'],
            'longitude' => $validated['longitude'],
            'accuracy_m' => $validated['accuracy_m'] ?? null,
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 512),
        ]);

        return redirect()
            ->route('staff-daily-check-in.index')
            ->with('status', 'Attendance marked successfully with your current location.');
    }

    public function storeAbsenceReason(Request $request): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'absence_date' => ['required', 'date', 'before_or_equal:today'],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
            'month' => ['nullable', 'date_format:Y-m'],
        ]);

        try {
            $date = Carbon::parse($validated['absence_date'])->startOfDay();
            $this->checkInService->saveAbsenceReason($user, $date, $validated['reason']);
        } catch (\InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['reason' => $e->getMessage()]);
        }

        $month = $validated['month'] ?? $date->format('Y-m');

        return redirect()
            ->route('staff-daily-check-in.index', ['month' => $month])
            ->with('status', 'Absence reason saved for '.$date->format('d M Y').'.');
    }
}
