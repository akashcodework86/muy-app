@if ($row->isAttendancePending())
    <span class="tp-attendance-pill tp-attendance-pill--pending">Attendance pending</span>
@else
    <span class="tp-attendance-pill tp-attendance-pill--uploaded">Uploaded</span>
@endif
