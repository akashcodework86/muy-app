{{--
  Activity date locked to the current calendar month for new entry.
  Dates outside the current month stay readonly so edits of other fields do not force a date change.
  Month arrows in the custom picker show a backdate reminder toast.
--}}
@props([
    'name',
    'id' => null,
    'value' => null,
    'required' => true,
])

@php
    $today = \App\Support\TodayOnlyDate::today();
    $monthStart = \App\Support\TodayOnlyDate::monthStart();
    $monthEnd = \App\Support\TodayOnlyDate::monthEnd();
    $submitted = old($name);
    $stored = null;
    if ($value !== null && $value !== '') {
        try {
            $stored = \Illuminate\Support\Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            $stored = is_string($value) ? substr($value, 0, 10) : null;
        }
    }
    $isOutsideCurrentMonth = $submitted === null
        && $stored !== null
        && ! \App\Support\TodayOnlyDate::isInCurrentMonth($stored);
    $display = $isOutsideCurrentMonth
        ? $stored
        : ($submitted ?? $stored ?? $today);
@endphp

@once
    <script src="{{ asset('js/muy-current-month-date.js') }}"></script>
@endonce

@if ($isOutsideCurrentMonth)
    <input
        type="date"
        name="{{ $name }}"
        @if ($id) id="{{ $id }}" @endif
        value="{{ $display }}"
        @if ($required) required @endif
        readonly
        title="Past activity date is locked. New entries can only use dates in the current month."
        {{ $attributes }}
    >
@else
    <input
        type="date"
        name="{{ $name }}"
        @if ($id) id="{{ $id }}" @endif
        value="{{ $display }}"
        @if ($required) required @endif
        min="{{ $monthStart }}"
        max="{{ $monthEnd }}"
        data-month-start="{{ $monthStart }}"
        data-month-end="{{ $monthEnd }}"
        data-today="{{ $today }}"
        {{ $attributes }}
    >
@endif
