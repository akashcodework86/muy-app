@extends('layouts.admin')

@section('title', \App\Models\LakhpatiTechnicalTraining::MODULE_LABEL)
@section('heading', \App\Models\LakhpatiTechnicalTraining::MODULE_LABEL)

@push('styles')
<style>
    .tp-show-shell { display:flex; flex-direction:column; gap:1.25rem; }
    .tp-show-card { background:#fff; border:1px solid #e2e8f0; border-radius:14px; padding:1.2rem 1.35rem; }
    .tp-show-card__title { margin:0 0 0.9rem; font-size:0.98rem; font-weight:800; }
    .tp-show-grid { display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:0.75rem; }
    .tp-show-field__label { display:block; font-size:0.72rem; font-weight:700; color:#64748b; text-transform:uppercase; margin-bottom:0.2rem; }
    .tp-show-field__value { font-size:0.9rem; font-weight:700; color:#0f172a; }
    .tp-show-field--full { grid-column:1 / -1; }
    .tp-show-actions { margin-top:1rem; display:flex; flex-wrap:wrap; gap:0.65rem; }
    .tp-show-btn { display:inline-flex; padding:0.55rem 0.95rem; border-radius:9px; font-weight:700; text-decoration:none; font-size:0.84rem; }
    .tp-show-btn--primary { background:#4f46e5; color:#fff; }
    .tp-show-btn--secondary { background:#fff; color:#334155; border:1px solid #cbd5e1; }
</style>
@endpush

@section('content')
@php
    $attachmentRoute = match ($currentRole) {
        'state_admin' => 'admin.lakhpati-technical-trainings.attachment',
        'state_staff' => 'spoc.lakhpati-technical-trainings.attachment',
        default => 'staff.lakhpati-technical-trainings.attachment',
    };
    $dashboardRoute = match ($currentRole) {
        'state_admin' => 'admin.lakhpati-technical-trainings.dashboard',
        'state_staff' => 'spoc.lakhpati-technical-trainings.dashboard',
        default => 'staff.lakhpati-technical-trainings.dashboard',
    };
@endphp
<div class="tp-show-shell">
    <div class="tp-show-card">
        <h3 class="tp-show-card__title">{{ $row->session_title }}</h3>
        <div class="tp-show-grid">
            <div class="tp-show-field"><span class="tp-show-field__label">Date</span><span class="tp-show-field__value">{{ $row->session_date?->format('d M Y') ?: '—' }}</span></div>
            <div class="tp-show-field"><span class="tp-show-field__label">Entered by</span><span class="tp-show-field__value">{{ $row->submitted_by_name }}</span></div>
            <div class="tp-show-field"><span class="tp-show-field__label">District</span><span class="tp-show-field__value">{{ $row->district_name }}</span></div>
            <div class="tp-show-field"><span class="tp-show-field__label">Block</span><span class="tp-show-field__value">{{ $row->block ?: '—' }}</span></div>
            <div class="tp-show-field"><span class="tp-show-field__label">Gram panchayat</span><span class="tp-show-field__value">{{ $row->gramPanchayat?->name ?? '—' }}</span></div>
            <div class="tp-show-field"><span class="tp-show-field__label">Venue</span><span class="tp-show-field__value">{{ $row->area ?: '—' }}</span></div>
            <div class="tp-show-field"><span class="tp-show-field__label">Workshop mode</span><span class="tp-show-field__value">{{ $row->formattedWorkshopMode() }}</span></div>
            <div class="tp-show-field"><span class="tp-show-field__label">Requesting agency</span><span class="tp-show-field__value">{{ $row->agencyTypeLabel() }}</span></div>
            <div class="tp-show-field"><span class="tp-show-field__label">Participants (M / F / Total)</span><span class="tp-show-field__value">{{ (int) $row->male_participants }} / {{ (int) $row->female_participants }} / {{ $row->totalParticipantCount() }}</span></div>
            @if ($row->session_brief)
                <div class="tp-show-field tp-show-field--full"><span class="tp-show-field__label">Brief</span><span class="tp-show-field__value">{{ $row->session_brief }}</span></div>
            @endif
        </div>

        @if (count($row->participantRows()) > 0)
            <h4 style="margin:1.25rem 0 0.5rem;font-size:0.85rem;">Participant rows</h4>
            <div style="overflow-x:auto;">
                <table style="width:100%;border-collapse:collapse;font-size:0.82rem;">
                    <thead><tr style="background:#f8fafc;"><th style="padding:0.5rem;">#</th><th>Name</th><th>Mobile</th><th>Gender</th><th>Block</th><th>GP</th></tr></thead>
                    <tbody>
                    @foreach ($row->participantRows() as $p)
                        <tr><td style="padding:0.45rem;">{{ $p['sr'] ?? '' }}</td><td>{{ $p['name'] ?? '—' }}</td><td>{{ $p['mobile'] ?? '—' }}</td><td>{{ $p['gender'] ?? '—' }}</td><td>{{ $p['block_name'] ?? '—' }}</td><td>{{ $p['gram_panchayat_name'] ?? '—' }}</td></tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        @if ($row->hasWorkshopPhotos())
            <h4 style="margin:1.25rem 0 0.5rem;font-size:0.85rem;font-weight:800;color:#0f172a;">Workshop photos</h4>
            @include('staff.technical-trainings.partials.attendance-media-preview', [
                'mediaItems' => $row->workshopPhotoItems(),
                'attachmentRoute' => $attachmentRoute,
                'attachmentQuery' => ['collection' => 'photos'],
                'record' => $row,
                'showEmptyMessage' => false,
            ])
        @endif

        @if ($row->hasAttendanceSheet())
            <h4 style="margin:1.25rem 0 0.5rem;font-size:0.85rem;font-weight:800;color:#0f172a;">Attendance files</h4>
            @include('staff.technical-trainings.partials.attendance-media-preview', [
                'mediaItems' => (array) $row->attendance_media_json,
                'attachmentRoute' => $attachmentRoute,
                'record' => $row,
                'showEmptyMessage' => false,
            ])
        @endif

        <div class="tp-show-actions">
            @if (!empty($canEdit))
                <a class="tp-show-btn tp-show-btn--secondary" href="{{ route('staff.lakhpati-technical-trainings.edit', $row) }}">Edit entry</a>
            @endif
            <a class="tp-show-btn tp-show-btn--secondary" href="{{ route($dashboardRoute) }}">Back to dashboard</a>
        </div>
    </div>
</div>
@endsection
