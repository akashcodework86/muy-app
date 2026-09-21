@extends('layouts.admin')

@php
    $isEdit = isset($row) && $row;
    $pageTitle = $isEdit ? 'Edit incubatee meeting' : 'Schedule incubatee meeting';
    $dashRoute = $prefix.'incubatee-meetings.dashboard';
    $storeRoute = $isEdit ? $prefix.'incubatee-meetings.update' : $prefix.'incubatee-meetings.store';
    $selectedMode = old('mode', $isEdit ? $row->mode : 'online');
@endphp

@section('title', $pageTitle)
@section('heading', $pageTitle)

@push('styles')
<style>
    .ldm-shell { display:flex; flex-direction:column; gap:1.25rem; }
    .ldm-alert { border-radius:12px; padding:0.85rem 1rem; font-size:0.88rem; }
    .ldm-alert--info { background:#eff6ff; border:1px solid #bfdbfe; color:#1e40af; }
    .ldm-alert--error { background:#fef2f2; border:1px solid #fca5a5; color:#991b1b; }
    .ldm-alert--error ul { margin:0.35rem 0 0 1rem; }
    .ldm-card { background:#fff; border:1px solid #e2e8f0; border-radius:14px; padding:1.25rem 1.35rem; max-width:56rem; }
    .ldm-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:1rem 1.1rem; }
    .ldm-field { display:flex; flex-direction:column; gap:0.4rem; }
    .ldm-field--full { grid-column:1 / -1; }
    .ldm-field label { font-size:0.82rem; font-weight:700; }
    .ldm-field input, .ldm-field select, .ldm-field textarea { width:100%; box-sizing:border-box; border:1px solid #cbd5e1; border-radius:8px; padding:0.58rem 0.7rem; font-size:0.88rem; }
    .ldm-field textarea { min-height:5rem; resize:vertical; }
    .ldm-readonly { background:#f8fafc; color:#64748b; }
    .ldm-conditional[hidden] { display:none !important; }
    .ldm-actions { margin-top:1.25rem; display:flex; gap:0.65rem; flex-wrap:wrap; }
    .ldm-submit { border:none; border-radius:8px; background:#4f46e5; color:#fff; padding:0.62rem 1rem; font-weight:700; cursor:pointer; }
    .ldm-link { color:#4f46e5; font-weight:700; text-decoration:none; }
    .ldm-req { color:#b91c1c; }
    @media (max-width:720px) { .ldm-grid { grid-template-columns:1fr; } }
</style>
@endpush

@section('content')
<div class="ldm-shell">
    <div class="ldm-alert ldm-alert--info">This meeting is for <strong>all incubatees</strong>. Title and agenda are saved as typed. Date and time are IST.</div>
    @if ($errors->any())<div class="ldm-alert ldm-alert--error"><strong>Please fix:</strong><ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

    <div class="ldm-card">
        <h3 style="margin:0 0 1rem;font-weight:700;">{{ $isEdit ? 'Edit meeting' : 'New meeting' }}</h3>
        <form method="post" action="{{ route($storeRoute, $isEdit ? $row : []) }}">
            @csrf
            @if ($isEdit) @method('PUT') @endif
            <div class="ldm-grid">
                <div class="ldm-field"><label>Organizer</label><input class="ldm-readonly" type="text" value="{{ $user->name }}" readonly></div>
                <div class="ldm-field">
                    <label for="title">Title <span class="ldm-req">*</span></label>
                    <input id="title" type="text" name="title" maxlength="191" required value="{{ old('title', $isEdit ? $row->title : '') }}">
                </div>
                <div class="ldm-field">
                    <label for="scheduled_at">Date &amp; time (IST) <span class="ldm-req">*</span></label>
                    <input id="scheduled_at" type="datetime-local" name="scheduled_at" required value="{{ old('scheduled_at', $isEdit ? $row->datetimeLocalValue() : '') }}">
                </div>
                <div class="ldm-field">
                    <label for="duration_minutes">Duration (minutes)</label>
                    <input id="duration_minutes" type="number" name="duration_minutes" min="1" max="1440" value="{{ old('duration_minutes', $isEdit ? $row->duration_minutes : '') }}">
                </div>
                <div class="ldm-field">
                    <label for="imMode">Mode <span class="ldm-req">*</span></label>
                    <select id="imMode" name="mode" required>
                        <option value="online" @selected($selectedMode === 'online')>Online</option>
                        <option value="in_person" @selected($selectedMode === 'in_person')>In-person</option>
                    </select>
                </div>
                <div class="ldm-field ldm-conditional" id="imLinkWrap" @if($selectedMode !== 'online') hidden @endif>
                    <label for="meeting_link">Meeting link <span class="ldm-req">*</span></label>
                    <input id="meeting_link" type="url" name="meeting_link" maxlength="500" placeholder="https://" value="{{ old('meeting_link', $isEdit ? $row->meeting_link : '') }}">
                </div>
                <div class="ldm-field ldm-conditional" id="imVenueWrap" @if($selectedMode !== 'in_person') hidden @endif>
                    <label for="venue">Venue <span class="ldm-req">*</span></label>
                    <input id="venue" type="text" name="venue" maxlength="255" value="{{ old('venue', $isEdit ? $row->venue : '') }}">
                </div>
                <div class="ldm-field ldm-field--full">
                    <label for="agenda">Agenda / notes</label>
                    <textarea id="agenda" name="agenda" maxlength="5000">{{ old('agenda', $isEdit ? $row->agenda : '') }}</textarea>
                </div>
            </div>
            <div class="ldm-actions">
                <button class="ldm-submit" type="submit">{{ $isEdit ? 'Save changes' : 'Schedule meeting' }}</button>
                <a class="ldm-link" href="{{ route($dashRoute) }}">Back to meetings</a>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    var modeSel = document.getElementById('imMode');
    var linkWrap = document.getElementById('imLinkWrap');
    var venueWrap = document.getElementById('imVenueWrap');
    var linkInput = document.getElementById('meeting_link');
    var venueInput = document.getElementById('venue');
    function sync() {
        var online = modeSel.value === 'online';
        linkWrap.hidden = !online;
        venueWrap.hidden = online;
        if (linkInput) linkInput.required = online;
        if (venueInput) venueInput.required = !online;
    }
    modeSel.addEventListener('change', sync);
    sync();
})();
</script>
@endpush
