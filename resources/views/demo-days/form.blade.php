@extends('layouts.admin')

@section('title', \App\Models\DemoDay::MODULE_LABEL)
@section('heading', \App\Models\DemoDay::MODULE_LABEL)

@push('styles')
<style>
    .ddy-shell { display:flex; flex-direction:column; gap:1.25rem; }
    .ddy-alert { border-radius:12px; padding:0.85rem 1rem; font-size:0.88rem; }
    .ddy-alert--info { background:#eff6ff; border:1px solid #bfdbfe; color:#1e40af; }
    .ddy-alert--warning { background:#fffbeb; border:1px solid #fde68a; color:#92400e; }
    .ddy-alert--success { background:#f0fdf4; border:1px solid #86efac; color:#166534; }
    .ddy-alert--error { background:#fef2f2; border:1px solid #fca5a5; color:#991b1b; }
    .ddy-alert--error ul { margin:0.35rem 0 0 1rem; }
    .ddy-card { background:#fff; border:1px solid #e2e8f0; border-radius:14px; padding:1.25rem 1.35rem; max-width:56rem; }
    .ddy-card__title { margin:0 0 1rem; font-size:1rem; font-weight:700; color:#0f172a; }
    .ddy-grid { display:grid; grid-template-columns:repeat(2, minmax(0, 1fr)); gap:1rem 1.1rem; }
    .ddy-field { display:flex; flex-direction:column; gap:0.4rem; min-width:0; }
    .ddy-field--full { grid-column:1 / -1; }
    .ddy-field label { font-size:0.82rem; font-weight:700; color:#0f172a; }
    .ddy-req { color:#b91c1c; }
    .ddy-field input, .ddy-field select, .ddy-field textarea {
        width:100%; box-sizing:border-box; border:1px solid #cbd5e1; border-radius:8px; padding:0.58rem 0.7rem; font-size:0.88rem;
    }
    .ddy-readonly { background:#f8fafc; color:#64748b; }
    .ddy-hint { margin:0.2rem 0 0; color:#64748b; font-size:0.78rem; }
    .ddy-section { grid-column:1 / -1; margin-top:0.25rem; padding-top:0.85rem; border-top:1px solid #e2e8f0; font-size:0.72rem; font-weight:700; letter-spacing:0.06em; text-transform:uppercase; color:#64748b; }
    .ddy-other { display:none; }
    .ddy-other.is-visible { display:flex; }
    .ddy-actions { margin-top:1.1rem; display:flex; flex-wrap:wrap; gap:0.65rem; }
    .ddy-submit { border:none; border-radius:8px; background:#7c3aed; color:#fff; padding:0.62rem 1rem; font-weight:700; cursor:pointer; }
    .ddy-link { color:#7c3aed; font-weight:700; text-decoration:none; }
    @media (max-width:720px) { .ddy-grid { grid-template-columns:1fr; } }
</style>
@endpush

@section('content')
@php
    $isEdit = !empty($row);
@endphp
<div class="ddy-shell">
    <div class="ddy-alert ddy-alert--info">
        MIS <strong>8.4</strong> — Demo Days (Funding &amp; Schematic Convergence). Search and select one or more participating incubatees.
    </div>

    @if (!empty($migrationMissing))
        <div class="ddy-alert ddy-alert--warning">Run <code>php artisan migrate</code> for <code>demo_days</code>.</div>
    @endif
    @if (session('status'))<div class="ddy-alert ddy-alert--success">{{ session('status') }}</div>@endif
    @if ($errors->any())
        <div class="ddy-alert ddy-alert--error"><strong>Please fix:</strong><ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif

    <div class="ddy-card">
        <h3 class="ddy-card__title">{{ $isEdit ? 'Edit demo day' : 'New demo day' }}</h3>
        <form method="post" action="{{ $isEdit ? route($storeRoute, $row) : route($storeRoute) }}" enctype="multipart/form-data">
            @csrf
            @if ($isEdit) @method('PUT') @endif

            <div class="ddy-grid">
                <div class="ddy-field">
                    <label>Entered by</label>
                    <input type="text" class="ddy-readonly" value="{{ $user->name }}" readonly>
                </div>
                <div class="ddy-field">
                    <label for="event_date">Demo day date <span class="ddy-req">*</span></label>
                    <input type="date" id="event_date" name="event_date" required
                        value="{{ old('event_date', $isEdit ? $row->event_date?->format('Y-m-d') : now()->toDateString()) }}">
                </div>

                <div class="ddy-field ddy-field--full">
                    <label for="event_name">Event name <span class="ddy-req">*</span></label>
                    <input type="text" id="event_name" name="event_name" maxlength="255" required
                        value="{{ old('event_name', $isEdit ? $row->event_name : '') }}" placeholder="e.g. Dehradun Demo Day Q1">
                </div>

                <div class="ddy-field">
                    <label for="event_type">Event type <span class="ddy-req">*</span></label>
                    <select id="event_type" name="event_type" required>
                        <option value="">— Select —</option>
                        @foreach ($eventTypes as $value => $label)
                            <option value="{{ $value }}" @selected(old('event_type', $isEdit ? $row->event_type : '') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="ddy-field ddy-other" id="investor_wrap">
                    <label for="investor_name">Investor / organization name <span class="ddy-req">*</span></label>
                    <input type="text" id="investor_name" name="investor_name" maxlength="255"
                        value="{{ old('investor_name', $isEdit ? $row->investor_name : '') }}">
                </div>
                <div class="ddy-field ddy-other" id="other_wrap">
                    <label for="event_type_other">Specify other <span class="ddy-req">*</span></label>
                    <input type="text" id="event_type_other" name="event_type_other" maxlength="191"
                        value="{{ old('event_type_other', $isEdit ? $row->event_type_other : '') }}">
                </div>

                <div class="ddy-field">
                    <label for="venue">Venue / location</label>
                    <input type="text" id="venue" name="venue" maxlength="255" value="{{ old('venue', $isEdit ? $row->venue : '') }}">
                </div>
                <div class="ddy-field">
                    <label for="mode">Mode</label>
                    <select id="mode" name="mode">
                        <option value="">— Optional —</option>
                        @foreach ($modes as $value => $label)
                            <option value="{{ $value }}" @selected(old('mode', $isEdit ? $row->mode : '') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="ddy-section">Participating incubatees</div>
                <div class="ddy-field ddy-field--full">
                    @if ($isEdit)
                        @php $editCounts = $row->participantCounts(); @endphp
                        <div style="display:flex; flex-direction:column; gap:0.45rem;">
                            @foreach ($row->participatingIncubatees() as $index => $inc)
                                <input type="text" class="ddy-readonly" readonly
                                    value="{{ $inc['name'] ?? '' }}@if(!empty($inc['application_no'])) · {{ $inc['application_no'] }}@endif">
                                <input type="hidden" name="participating_incubatees[{{ $index }}][key]" value="{{ $inc['key'] ?? '' }}">
                                <input type="hidden" name="participating_incubatees[{{ $index }}][cfa_submission_id]" value="{{ (int) ($inc['cfa_submission_id'] ?? 0) }}">
                                <input type="hidden" name="participating_incubatees[{{ $index }}][legacy_application_id]" value="{{ (int) ($inc['legacy_application_id'] ?? 0) }}">
                                <input type="hidden" name="participating_incubatees[{{ $index }}][name]" value="{{ $inc['name'] ?? '' }}">
                                <input type="hidden" name="participating_incubatees[{{ $index }}][application_no]" value="{{ $inc['application_no'] ?? '' }}">
                            @endforeach
                        </div>
                        <p class="ddy-hint">Incubatee list is locked on edit. Participants: {{ $editCounts['total'] }} selected.</p>
                    @else
                        @include('demo-days.partials.incubatee-picker', ['searchRoute' => $searchRoute])
                    @endif
                </div>

                <div class="ddy-field ddy-field--full">
                    @include('demo-days.partials.event-photos-upload', [
                        'isEdit' => $isEdit,
                        'row' => $row ?? null,
                        'attachmentRoute' => $attachmentRoute ?? 'spoc.demo-days.attachment',
                    ])
                </div>
                <div class="ddy-field ddy-field--full">
                    <label for="summary">Brief summary</label>
                    <textarea id="summary" name="summary" maxlength="5000" rows="3">{{ old('summary', $isEdit ? $row->summary : '') }}</textarea>
                </div>
                <div class="ddy-field ddy-field--full">
                    <label for="remarks">Remarks</label>
                    <textarea id="remarks" name="remarks" maxlength="5000" rows="2">{{ old('remarks', $isEdit ? $row->remarks : '') }}</textarea>
                </div>
            </div>

            <div class="ddy-actions">
                <button type="submit" class="ddy-submit">{{ $isEdit ? 'Update' : 'Save demo day' }}</button>
                <a href="{{ route($dashboardRoute) }}" class="ddy-link">View dashboard</a>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const typeSelect = document.getElementById('event_type');
    const investorWrap = document.getElementById('investor_wrap');
    const otherWrap = document.getElementById('other_wrap');
    function syncType() {
        const v = typeSelect ? typeSelect.value : '';
        if (investorWrap) investorWrap.classList.toggle('is-visible', v === 'investor_meet');
        if (otherWrap) otherWrap.classList.toggle('is-visible', v === 'other');
    }
    typeSelect?.addEventListener('change', syncType);
    syncType();
})();
</script>
@if (!$isEdit)
    @include('demo-days.partials.incubatee-picker-script', ['searchRoute' => $searchRoute])
@endif
@include('staff.technical-trainings.partials.attendance-media-preview', [
    'mediaItems' => [],
    'showEmptyMessage' => false,
    'record' => null,
])
@endpush
