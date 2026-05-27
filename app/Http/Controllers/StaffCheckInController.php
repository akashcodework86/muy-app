<?php

namespace App\Http\Controllers;

use App\Models\StaffCheckIn;
use App\Services\StaffCheckInService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StaffCheckInController extends Controller
{
    public function __construct(
        private readonly StaffCheckInService $checkInService,
    ) {}

    public function index(): View
    {
        $user = auth()->user();
        $todayCheckIn = $this->checkInService->todayForUser($user);

        $history = $user->staffCheckIns()
            ->orderByDesc('check_in_date')
            ->orderByDesc('marked_at')
            ->limit(60)
            ->get();

        $stats = [
            'total_days' => $history->count(),
            'this_month' => $history->filter(fn ($c) => $c->check_in_date->isSameMonth(now()))->count(),
        ];

        return view('staff-daily-check-in.index', [
            'todayCheckIn' => $todayCheckIn,
            'showReminder' => $this->checkInService->shouldShowReminder($user),
            'history' => $history,
            'stats' => $stats,
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
}
