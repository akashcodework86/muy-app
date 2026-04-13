<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAttendanceMarkRequest;
use App\Models\AttendanceMark;
use App\Services\WorkingCalendarService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    public function status(Request $request, WorkingCalendarService $calendar): JsonResponse
    {
        $user = $request->user();
        $cx = $calendar->todayContext();
        $todayStr = $cx['now']->toDateString();

        if (! $cx['is_working_day']) {
            return response()->json([
                'eligible' => true,
                'need_popup' => false,
                'already_marked' => false,
                'is_working_day' => false,
                'reason' => 'off_day',
            ]);
        }

        $already = AttendanceMark::query()
            ->where('user_id', $user->id)
            ->whereDate('attendance_date', $todayStr)
            ->exists();

        if ($already) {
            return response()->json([
                'eligible' => true,
                'need_popup' => false,
                'already_marked' => true,
                'is_working_day' => true,
            ]);
        }

        $needPopup = $cx['is_after_cutoff'];

        return response()->json([
            'eligible' => true,
            'need_popup' => $needPopup,
            'already_marked' => false,
            'is_working_day' => true,
            'seconds_until_cutoff' => $cx['seconds_until_cutoff'],
        ]);
    }

    public function store(StoreAttendanceMarkRequest $request, WorkingCalendarService $calendar): JsonResponse
    {
        $user = $request->user();
        $cx = $calendar->todayContext();
        $now = $cx['now'];
        $todayStr = $now->toDateString();

        if (! $cx['is_working_day']) {
            return response()->json(['message' => 'Attendance not required on off days.'], 422);
        }

        if (! $cx['is_after_cutoff']) {
            return response()->json(['message' => 'Attendance can be marked only on or after 9:00 AM.'], 422);
        }

        $exists = AttendanceMark::query()
            ->where('user_id', $user->id)
            ->whereDate('attendance_date', $todayStr)
            ->exists();

        if ($exists) {
            return response()->json(['message' => 'Already marked for today.'], 422);
        }

        $data = $request->validated();

        AttendanceMark::query()->create([
            'user_id' => $user->id,
            'attendance_date' => $todayStr,
            'marked_at' => $now,
            'latitude' => $data['latitude'],
            'longitude' => $data['longitude'],
            'accuracy_m' => $data['accuracy_m'] ?? null,
        ]);

        return response()->json(['ok' => true, 'message' => 'Attendance marked.']);
    }
}
