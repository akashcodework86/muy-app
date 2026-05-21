@extends('layouts.admin')

@section('title', 'District level workshop entry')
@section('heading', 'District level workshop')

@push('styles')
<style>
    .tp-show-shell { display:flex; flex-direction:column; gap:1.25rem; }
    .tp-show-alert { border-radius:12px; padding:0.85rem 1rem; font-size:0.88rem; background:#f0fdf4; border:1px solid #86efac; color:#166534; }
    .tp-show-card { background:#fff; border:1px solid #e2e8f0; border-radius:14px; padding:1.2rem 1.35rem; }
    .tp-show-card__title { margin:0 0 0.9rem; font-size:0.98rem; font-weight:800; color:#0f172a; }
    .tp-show-grid { display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:0.75rem 0.95rem; }
    .tp-show-field { min-width:0; }
    .tp-show-field--full { grid-column: 1 / -1; }
    .tp-show-field__label { display:block; font-size:0.72rem; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:0.05em; margin-bottom:0.22rem; }
    .tp-show-field__value { font-size:0.9rem; font-weight:700; color:#0f172a; line-height:1.45; }
    .tp-show-actions { margin-top:1rem; display:flex; flex-wrap:wrap; gap:0.65rem; align-items:center; }
    .tp-show-btn {
        display:inline-flex;
        align-items:center;
        justify-content:center;
        padding:0.55rem 0.95rem;
        border-radius:9px;
        font-size:0.84rem;
        font-weight:700;
        text-decoration:none;
        border:1px solid transparent;
    }
    .tp-show-btn--primary { background:#4f46e5; color:#fff; box-shadow:0 6px 16px rgba(79,70,229,0.18); }
    .tp-show-btn--primary:hover { background:#4338ca; color:#fff; }
    .tp-show-btn--secondary { background:#fff; color:#334155; border-color:#cbd5e1; }
    .tp-show-btn--secondary:hover { background:#f8fafc; }
    .ees-ws-pill {
        display:inline-flex;
        align-items:center;
        padding:0.22rem 0.6rem;
        border-radius:999px;
        font-size:0.78rem;
        font-weight:800;
        letter-spacing:0.02em;
    }
    .ees-ws-pill--virtual { background:#e0f2fe; color:#0369a1; border:1px solid #7dd3fc; }
    .ees-ws-pill--physical { background:#ecfdf5; color:#047857; border:1px solid #6ee7b7; }
</style>
@endpush

@section('content')
@php
    $attachmentRoute = match ($currentRole) {
        'state_admin' => 'admin.district-workshop-sessions.attachment',
        'state_staff' => 'spoc.district-workshop-sessions.attachment',
        default => 'staff.district-workshop-sessions.attachment',
    };
    $hasParticipantCounts = ((int) ($row->male_participants ?? 0) + (int) ($row->female_participants ?? 0)) > 0;
@endphp
<div class="tp-show-shell">
    @if (session('status'))
        <div class="tp-show-alert">
            {{ session('status') }}
        </div>
    @endif

    <div class="tp-show-card">
        <h3 class="tp-show-card__title">Event Details</h3>
        <div class="tp-show-grid">
            <div class="tp-show-field">
                <span class="tp-show-field__label">Date of Session</span>
                <span class="tp-show-field__value">{{ $row->event_date?->format('d M Y') ?: 'NA' }}</span>
            </div>
            <div class="tp-show-field">
                <span class="tp-show-field__label">Session Taken By</span>
                <span class="tp-show-field__value">{{ $row->submitted_by_name }}</span>
            </div>
            <div class="tp-show-field">
                <span class="tp-show-field__label">District</span>
                <span class="tp-show-field__value">{{ $row->district_name ?: ($row->district?->name ?? 'NA') }}</span>
            </div>
            <div class="tp-show-field">
                <span class="tp-show-field__label">Workshop format</span>
                <span class="tp-show-field__value">
                    <span class="ees-ws-pill {{ ($row->workshop_mode ?? '') === 'virtual' ? 'ees-ws-pill--virtual' : 'ees-ws-pill--physical' }}">
                        {{ $row->formatted_workshop_mode }}
                    </span>
                </span>
            </div>
            @if ($hasParticipantCounts)
                <div class="tp-show-field">
                    <span class="tp-show-field__label">Male participants</span>
                    <span class="tp-show-field__value">{{ number_format((int) $row->male_participants) }}</span>
                </div>
                <div class="tp-show-field">
                    <span class="tp-show-field__label">Female participants</span>
                    <span class="tp-show-field__value">{{ number_format((int) $row->female_participants) }}</span>
                </div>
                <div class="tp-show-field">
                    <span class="tp-show-field__label">Total participants</span>
                    <span class="tp-show-field__value">{{ number_format($row->totalParticipantCount()) }}</span>
                </div>
            @else
                <div class="tp-show-field">
                    <span class="tp-show-field__label">Total participants</span>
                    <span class="tp-show-field__value">{{ number_format($row->totalParticipantCount()) }} <span style="font-weight:500;color:#64748b;font-size:0.82rem;">(legacy entry)</span></span>
                </div>
            @endif
            <div class="tp-show-field tp-show-field--full">
                <span class="tp-show-field__label">Notes</span>
                <span class="tp-show-field__value">{{ $row->notes ?: '—' }}</span>
            </div>
            <div class="tp-show-field">
                <span class="tp-show-field__label">Submitted At</span>
                <span class="tp-show-field__value">{{ $row->created_at?->format('d M Y h:i A') ?: 'NA' }}</span>
            </div>
        </div>
        <div class="tp-show-actions">
            <a class="tp-show-btn tp-show-btn--primary" href="{{ match ($currentRole) {
                'state_admin' => route('admin.district-workshop-sessions.export-single', $row),
                'state_staff' => route('spoc.district-workshop-sessions.export-single', $row),
                default => route('staff.district-workshop-sessions.export-single', $row),
            } }}">Excel Export</a>
            @if ($canEdit)
                <a class="tp-show-btn tp-show-btn--secondary" href="{{ route('staff.district-workshop-sessions.edit', $row) }}">Edit Entry</a>
            @endif
            <a class="tp-show-btn tp-show-btn--secondary" href="{{ match ($currentRole) {
                'state_admin' => route('admin.district-workshop-sessions.dashboard'),
                'state_staff' => route('spoc.district-workshop-sessions.dashboard'),
                default => route('staff.district-workshop-sessions.dashboard'),
            } }}">Back to dashboard</a>
        </div>
    </div>

    <div class="tp-show-card">
        <h3 class="tp-show-card__title">Workshop Photos</h3>
        @include('staff.technical-trainings.partials.attendance-media-preview', [
            'mediaItems' => (array) $row->workshop_photos_json,
            'attachmentRoute' => $attachmentRoute,
            'attachmentQuery' => ['collection' => 'photos'],
            'record' => $row,
        ])
    </div>

    <div class="tp-show-card">
        <h3 class="tp-show-card__title">Uploaded Attendance Files</h3>
        @include('staff.technical-trainings.partials.attendance-media-preview', [
            'mediaItems' => (array) $row->attendance_media_json,
            'attachmentRoute' => $attachmentRoute,
            'record' => $row,
        ])
    </div>
</div>
@endsection
