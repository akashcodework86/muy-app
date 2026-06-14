@extends('layouts.admin')

@section('title', \App\Models\StakeholderConsultationWorkshop::MODULE_LABEL)
@section('heading', \App\Models\StakeholderConsultationWorkshop::MODULE_LABEL)

@push('styles')
<style>
    .scw-show-card { background:#fff; border:1px solid #e2e8f0; border-radius:14px; padding:1.2rem 1.35rem; }
    .scw-show-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:0.75rem; }
    .scw-show-label { display:block; font-size:0.72rem; font-weight:700; color:#64748b; text-transform:uppercase; }
    .scw-show-value { font-size:0.9rem; font-weight:700; color:#0f172a; }
    .scw-show-full { grid-column:1 / -1; }
    .scw-show-actions { margin-top:1rem; display:flex; flex-wrap:wrap; gap:0.65rem; }
    .scw-show-btn { display:inline-flex; padding:0.55rem 0.95rem; border-radius:9px; font-weight:700; text-decoration:none; font-size:0.84rem; background:#fff; color:#334155; border:1px solid #cbd5e1; }
    .scw-show-btn--danger { color:#b91c1c; border-color:#fecaca; cursor:pointer; font-family:inherit; }
</style>
@endpush

@section('content')
@php
    $attachmentRoute = match ($currentRole) {
        'state_admin' => 'admin.stakeholder-consultation-workshops.attachment',
        default => 'spoc.stakeholder-consultation-workshops.attachment',
    };
    $dashboardRoute = match ($currentRole) {
        'state_admin' => 'admin.stakeholder-consultation-workshops.dashboard',
        default => 'spoc.stakeholder-consultation-workshops.dashboard',
    };
@endphp
<div class="scw-show-card">
    <h3 style="margin:0 0 0.9rem;font-weight:800;">{{ $row->workshop_title }}</h3>
    <div class="scw-show-grid">
        <div><span class="scw-show-label">Date</span><span class="scw-show-value">{{ $row->workshop_date?->format('d M Y') ?: '—' }}</span></div>
        <div><span class="scw-show-label">Entered by</span><span class="scw-show-value">{{ $row->submitted_by_name }}</span></div>
        <div><span class="scw-show-label">Mode</span><span class="scw-show-value">{{ $row->formattedWorkshopMode() }}</span></div>
        <div><span class="scw-show-label">Level</span><span class="scw-show-value">{{ $row->organizingLevelLabel() }}</span></div>
        <div><span class="scw-show-label">Venue</span><span class="scw-show-value">{{ $row->venue }}</span></div>
        @if ($row->hub_name)<div><span class="scw-show-label">Hub</span><span class="scw-show-value">{{ $row->hub_name }}</span></div>@endif
        @if ($row->district_name)<div><span class="scw-show-label">District</span><span class="scw-show-value">{{ $row->district_name }}</span></div>@endif
        <div><span class="scw-show-label">Departments</span><span class="scw-show-value">{{ $row->primaryDepartmentsLabel() }}</span></div>
        <div><span class="scw-show-label">Stakeholders</span><span class="scw-show-value">{{ $row->stakeholderTypesLabel() }}</span></div>
        <div><span class="scw-show-label">Participants</span><span class="scw-show-value">{{ number_format((int) $row->total_participants) }}</span></div>
        <div class="scw-show-full"><span class="scw-show-label">Consultation theme</span><span class="scw-show-value">{{ $row->consultation_theme }}</span></div>
        <div class="scw-show-full"><span class="scw-show-label">Key outcomes</span><span class="scw-show-value">{{ $row->key_outcomes }}</span></div>
    </div>

    @if ($row->hasAttendanceSheet())
        <h4 style="margin:1.25rem 0 0.5rem;font-size:0.85rem;font-weight:800;">Attendance</h4>
        @include('staff.technical-trainings.partials.attendance-media-preview', ['mediaItems' => (array) $row->attendance_media_json, 'attachmentRoute' => $attachmentRoute, 'record' => $row, 'showEmptyMessage' => false])
    @endif

    <div class="scw-show-actions">
        <a class="scw-show-btn" href="{{ route($dashboardRoute) }}">Back</a>
        @if (!empty($canEdit))<a class="scw-show-btn" href="{{ route('spoc.stakeholder-consultation-workshops.edit', $row) }}">Edit</a>@endif
        @if (!empty($canDelete))
            <form method="post" action="{{ route('spoc.stakeholder-consultation-workshops.destroy', $row) }}" style="display:inline;" onsubmit="return confirm('Delete this workshop?');">
                @csrf @method('DELETE')
                <button type="submit" class="scw-show-btn scw-show-btn--danger">Delete</button>
            </form>
        @endif
    </div>
</div>
@endsection
