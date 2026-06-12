@extends('layouts.admin')

@section('title', \App\Models\StakeholderCapacityBuildingSession::MODULE_LABEL)
@section('heading', \App\Models\StakeholderCapacityBuildingSession::MODULE_LABEL)

@push('styles')
<style>
    .cbs-show-shell { display:flex; flex-direction:column; gap:1.25rem; }
    .cbs-show-card { background:#fff; border:1px solid #e2e8f0; border-radius:14px; padding:1.2rem 1.35rem; }
    .cbs-show-card__title { margin:0 0 0.9rem; font-size:0.98rem; font-weight:800; }
    .cbs-show-grid { display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:0.75rem; }
    .cbs-show-field__label { display:block; font-size:0.72rem; font-weight:700; color:#64748b; text-transform:uppercase; margin-bottom:0.2rem; }
    .cbs-show-field__value { font-size:0.9rem; font-weight:700; color:#0f172a; }
    .cbs-show-field--full { grid-column:1 / -1; }
    .cbs-show-actions { margin-top:1rem; display:flex; flex-wrap:wrap; gap:0.65rem; }
    .cbs-show-btn { display:inline-flex; padding:0.55rem 0.95rem; border-radius:9px; font-weight:700; text-decoration:none; font-size:0.84rem; background:#fff; color:#334155; border:1px solid #cbd5e1; }
    .cbs-show-btn--danger { color:#b91c1c; border-color:#fecaca; cursor:pointer; font-family:inherit; }
</style>
@endpush

@section('content')
@php
    $attachmentRoute = match ($currentRole) {
        'state_admin' => 'admin.capacity-building-stakeholders.attachment',
        default => 'spoc.capacity-building-stakeholders.attachment',
    };
    $dashboardRoute = match ($currentRole) {
        'state_admin' => 'admin.capacity-building-stakeholders.dashboard',
        default => 'spoc.capacity-building-stakeholders.dashboard',
    };
@endphp
<div class="cbs-show-shell">
    <div class="cbs-show-card">
        <h3 class="cbs-show-card__title">{{ $row->session_title }}</h3>
        <div class="cbs-show-grid">
            <div><span class="cbs-show-field__label">Date</span><span class="cbs-show-field__value">{{ $row->session_date?->format('d M Y') ?: '—' }}</span></div>
            <div><span class="cbs-show-field__label">Entered by</span><span class="cbs-show-field__value">{{ $row->submitted_by_name }}</span></div>
            <div><span class="cbs-show-field__label">Mode</span><span class="cbs-show-field__value">{{ $row->formattedWorkshopMode() }}</span></div>
            <div><span class="cbs-show-field__label">Venue</span><span class="cbs-show-field__value">{{ $row->venue }}</span></div>
            <div><span class="cbs-show-field__label">Stakeholder type</span><span class="cbs-show-field__value">{{ $row->stakeholderTypeLabel() }}</span></div>
            @if ($row->department_name)
                <div><span class="cbs-show-field__label">Department</span><span class="cbs-show-field__value">{{ $row->department_name }}</span></div>
            @endif
            <div><span class="cbs-show-field__label">Staff trained</span><span class="cbs-show-field__value">{{ number_format((int) $row->staff_trained_total) }}</span></div>
            @if ($row->topics_covered)
                <div class="cbs-show-field--full"><span class="cbs-show-field__label">Topics covered</span><span class="cbs-show-field__value">{{ $row->topics_covered }}</span></div>
            @endif
        </div>

        @if ($row->hasWorkshopPhotos())
            <h4 style="margin:1.25rem 0 0.5rem;font-size:0.85rem;font-weight:800;">Workshop photos</h4>
            @include('staff.technical-trainings.partials.attendance-media-preview', [
                'mediaItems' => $row->workshopPhotoItems(),
                'attachmentRoute' => $attachmentRoute,
                'attachmentQuery' => ['collection' => 'photos'],
                'record' => $row,
                'showEmptyMessage' => false,
            ])
        @endif

        @if ($row->hasAttendanceSheet())
            <h4 style="margin:1.25rem 0 0.5rem;font-size:0.85rem;font-weight:800;">Attendance files</h4>
            @include('staff.technical-trainings.partials.attendance-media-preview', [
                'mediaItems' => (array) $row->attendance_media_json,
                'attachmentRoute' => $attachmentRoute,
                'record' => $row,
                'showEmptyMessage' => false,
            ])
        @endif

        <div class="cbs-show-actions">
            <a class="cbs-show-btn" href="{{ route($dashboardRoute) }}">Back to dashboard</a>
            @if (!empty($canEdit))
                <a class="cbs-show-btn" href="{{ route('spoc.capacity-building-stakeholders.edit', $row) }}">Edit</a>
            @endif
            @if (!empty($canDelete))
                <form
                    method="post"
                    action="{{ route('spoc.capacity-building-stakeholders.destroy', $row) }}"
                    style="display:inline;"
                    onsubmit="return confirm('Delete this session permanently?');"
                >
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="cbs-show-btn cbs-show-btn--danger">Delete</button>
                </form>
            @endif
        </div>
    </div>
</div>
@endsection
