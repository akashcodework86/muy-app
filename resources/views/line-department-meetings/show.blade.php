@extends('layouts.admin')

@section('title', \App\Models\LineDepartmentMeeting::MODULE_LABEL)
@section('heading', \App\Models\LineDepartmentMeeting::MODULE_LABEL)

@push('styles')
<style>
    .ldm-show-card { background:#fff; border:1px solid #e2e8f0; border-radius:14px; padding:1.2rem 1.35rem; }
    .ldm-show-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:0.75rem; }
    .ldm-show-label { display:block; font-size:0.72rem; font-weight:700; color:#64748b; text-transform:uppercase; }
    .ldm-show-value { font-size:0.9rem; font-weight:700; color:#0f172a; }
    .ldm-show-full { grid-column:1 / -1; }
    .ldm-show-actions { margin-top:1rem; display:flex; flex-wrap:wrap; gap:0.65rem; }
    .ldm-show-btn { display:inline-flex; padding:0.55rem 0.95rem; border-radius:9px; font-weight:700; text-decoration:none; font-size:0.84rem; background:#fff; color:#334155; border:1px solid #cbd5e1; }
    .ldm-show-btn--danger { color:#b91c1c; border-color:#fecaca; cursor:pointer; font-family:inherit; }
</style>
@endpush

@section('content')
@php
    $prefix = match ($currentRole) {
        'state_admin' => 'admin.',
        'hub_admin' => 'hub.',
        'district_staff' => 'staff.',
        default => 'spoc.',
    };
    $attachmentRoute = $prefix.'line-department-meetings.attachment';
    $dashboardRoute = $prefix.'line-department-meetings.dashboard';
@endphp
<div class="ldm-show-card">
    <h3 style="margin:0 0 0.9rem;font-weight:800;">{{ $row->department_name }} — {{ $row->official_name }}</h3>
    <div class="ldm-show-grid">
        <div><span class="ldm-show-label">Date</span><span class="ldm-show-value">{{ $row->meeting_date?->format('d M Y') }}</span></div>
        <div><span class="ldm-show-label">Entered by</span><span class="ldm-show-value">{{ $row->submitted_by_name }}</span></div>
        <div><span class="ldm-show-label">Level</span><span class="ldm-show-value">{{ $row->meetingLevelLabel() }}</span></div>
        <div><span class="ldm-show-label">Mode</span><span class="ldm-show-value">{{ $row->meetingModeLabel() }}</span></div>
        <div><span class="ldm-show-label">Purpose</span><span class="ldm-show-value">{{ $row->meetingPurposeLabel() }}</span></div>
        @if ($row->venue)<div><span class="ldm-show-label">Venue</span><span class="ldm-show-value">{{ $row->venue }}</span></div>@endif
        @if ($row->hub_name)<div><span class="ldm-show-label">Hub</span><span class="ldm-show-value">{{ $row->hub_name }}</span></div>@endif
        @if ($row->district_name)<div><span class="ldm-show-label">District</span><span class="ldm-show-value">{{ $row->district_name }}</span></div>@endif
        <div><span class="ldm-show-label">Official designation</span><span class="ldm-show-value">{{ $row->official_designation }}</span></div>
        <div class="ldm-show-full"><span class="ldm-show-label">MUY staff present</span><span class="ldm-show-value">{{ $row->muy_staff_present }}</span></div>
        <div class="ldm-show-full"><span class="ldm-show-label">Agenda</span><span class="ldm-show-value">{{ $row->agenda_summary }}</span></div>
        <div class="ldm-show-full"><span class="ldm-show-label">Outcome</span><span class="ldm-show-value">{{ $row->outcome_decision }}</span></div>
        @if (!empty($row->incubatees_discussed_json))
            <div class="ldm-show-full"><span class="ldm-show-label">Incubatees discussed</span><span class="ldm-show-value">{{ implode(', ', (array) $row->incubatees_discussed_json) }}</span></div>
        @endif
    </div>

    @if ($row->hasProofDocument())
        <h4 style="margin:1.25rem 0 0.5rem;font-size:0.85rem;font-weight:800;">Proof documents</h4>
        @include('staff.technical-trainings.partials.attendance-media-preview', ['mediaItems' => (array) $row->proof_media_json, 'attachmentRoute' => $attachmentRoute, 'record' => $row, 'showEmptyMessage' => false])
    @endif

    <div class="ldm-show-actions">
        <a class="ldm-show-btn" href="{{ route($dashboardRoute) }}">Back</a>
        @if (!empty($canEdit))<a class="ldm-show-btn" href="{{ route($prefix.'line-department-meetings.edit', $row) }}">Edit</a>@endif
        @if (!empty($canDelete))
            <form method="post" action="{{ route($prefix.'line-department-meetings.destroy', $row) }}" style="display:inline;" onsubmit="return confirm('Delete this meeting?');">
                @csrf @method('DELETE')
                <button type="submit" class="ldm-show-btn ldm-show-btn--danger">Delete</button>
            </form>
        @endif
    </div>
</div>
@endsection
