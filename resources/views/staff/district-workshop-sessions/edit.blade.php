@extends('layouts.admin')

@section('title', 'Edit district level workshop')
@section('heading', 'Edit district level workshop')

@push('styles')
<style>
    .tp-shell { display:flex; flex-direction:column; gap:1.25rem; }
    .tp-alert { border-radius:12px; padding:0.85rem 1rem; font-size:0.88rem; }
    .tp-alert--error { background:#fef2f2; border:1px solid #fca5a5; color:#991b1b; }
    .tp-alert--error ul { margin:0.35rem 0 0 1rem; }
    .tp-card { background:#fff; border:1px solid #e2e8f0; border-radius:14px; padding:1.25rem 1.35rem; }
    .tp-card__title { margin:0 0 1.15rem; font-size:1rem; font-weight:700; color:#0f172a; }
    .tp-section { margin-top:1.35rem; }
    .tp-grid { display:grid; grid-template-columns:repeat(2, minmax(0, 1fr)); gap:1rem 1.1rem; align-items:start; }
    .tp-field { display:flex; flex-direction:column; gap:0.4rem; min-width:0; }
    .tp-field--full { grid-column:1 / -1; }
    .tp-field label { font-size:0.82rem; font-weight:700; color:#0f172a; }
    .tp-field input[type="text"],
    .tp-field input[type="date"],
    .tp-field input[type="number"],
    .tp-field input[type="file"],
    .tp-field textarea { width:100%; box-sizing:border-box; border:1px solid #cbd5e1; border-radius:8px; padding:0.58rem 0.7rem; font-size:0.88rem; background:#fff; }
    .tp-field textarea { min-height:5.5rem; resize:vertical; }
    .tp-field input[type="file"] { padding:0.45rem 0.55rem; background:#f8fafc; }
    .tp-readonly { background:#f8fafc; color:#64748b; }
    .tp-field-hint { margin:0.2rem 0 0; color:#64748b; font-size:0.78rem; line-height:1.4; }
    .tp-media-preview { margin-top:0.55rem; }
    .tp-actions { margin-top:1.25rem; display:flex; flex-wrap:wrap; gap:0.65rem; align-items:center; }
    .tp-submit { border:none; border-radius:8px; background:#4f46e5; color:#fff; padding:0.62rem 1rem; font-weight:700; cursor:pointer; font-size:0.88rem; }
    .tp-link { color:#4f46e5; font-weight:700; text-decoration:none; font-size:0.88rem; }
    .tp-attendance-pill {
        display:inline-flex;
        align-items:center;
        padding:0.2rem 0.55rem;
        border-radius:999px;
        font-size:0.74rem;
        font-weight:800;
    }
    .tp-attendance-pill--pending { background:#fef3c7; color:#92400e; border:1px solid #fcd34d; }
    .tp-attendance-pill--uploaded { background:#ecfdf5; color:#047857; border:1px solid #6ee7b7; }
    .tp-alert--pending {
        margin-bottom:1rem;
        background:#fffbeb;
        border:1px solid #fde68a;
        color:#92400e;
    }
</style>
@endpush

@section('content')
<div class="tp-shell">
    @if ($errors->any())
        <div class="tp-alert tp-alert--error">
            <strong>Please fix:</strong>
            <ul>
                @foreach ($errors->all() as $e)
                    <li>{{ $e }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="tp-card">
        <h3 class="tp-card__title">Update Submission</h3>
        <form method="post" action="{{ route('staff.district-workshop-sessions.update', $row) }}" enctype="multipart/form-data">
            @csrf
            @method('put')
            <div class="tp-grid">
                <div class="tp-field">
                    <label>Session Taken By</label>
                    <input type="text" class="tp-readonly" value="{{ $row->submitted_by_name }}" readonly>
                </div>
                <div class="tp-field">
                    <label>Date of Session *</label>
                    <input type="date" name="session_date" value="{{ old('session_date', $row->event_date?->format('Y-m-d')) }}" required>
                </div>
                <div class="tp-field">
                    <label>District</label>
                    <input type="text" class="tp-readonly" value="{{ $row->district_name ?: ($row->district?->name ?? 'NA') }}" readonly>
                </div>
                @include('staff.district-workshop-sessions.partials.workshop-mode-field', ['selected' => $row->workshop_mode ?? 'physical'])
                <div class="tp-field">
                    <label>Male participants *</label>
                    <input id="tpMaleParticipants" type="number" name="male_participants" value="{{ old('male_participants', $row->male_participants ?? 0) }}" min="0" step="1" required>
                </div>
                <div class="tp-field">
                    <label>Female participants *</label>
                    <input id="tpFemaleParticipants" type="number" name="female_participants" value="{{ old('female_participants', $row->female_participants ?? 0) }}" min="0" step="1" required>
                </div>
                <div class="tp-field">
                    <label>Total participants</label>
                    <input id="tpTotalParticipants" type="text" class="tp-readonly" value="{{ (int) old('male_participants', $row->male_participants ?? 0) + (int) old('female_participants', $row->female_participants ?? 0) }}" readonly>
                </div>
                <div class="tp-field tp-field--full">
                    <label>Notes (optional)</label>
                    <textarea name="notes" maxlength="5000">{{ old('notes', $row->notes) }}</textarea>
                </div>
            </div>

            <div class="tp-section">
                <div class="tp-field">
                    <label>Upload workshop photos @if (!is_array($row->workshop_photos_json) || count($row->workshop_photos_json) === 0)*@endif</label>
                    <input id="tpPhotosInput" type="file" name="workshop_photos[]" accept=".jpg,.jpeg,.png,.webp,image/*" multiple>
                    <p class="tp-field-hint">Workshop photos (JPG/PNG). Minimum 1, maximum 5 total (50 MB each).</p>
                    @if (is_array($row->workshop_photos_json) && count($row->workshop_photos_json))
                        <p class="tp-field-hint">Current photos:</p>
                        @include('staff.technical-trainings.partials.attendance-media-preview', [
                            'mediaItems' => (array) $row->workshop_photos_json,
                            'attachmentRoute' => 'staff.district-workshop-sessions.attachment',
                            'attachmentQuery' => ['collection' => 'photos'],
                            'record' => $row,
                        ])
                    @endif
                    <div id="tpPhotosPreview" class="tp-media-preview"></div>
                </div>
            </div>

            <div class="tp-section">
                <div class="tp-field">
                    <label>Upload attendance sheet (optional)</label>
                    @if ($row->isAttendancePending())
                        <p class="tp-field-hint"><span class="tp-attendance-pill tp-attendance-pill--pending">Attendance pending</span> — upload PDF, image, or Excel when ready (up to 25 files).</p>
                    @else
                        <p class="tp-field-hint">PDF, image (JPG/PNG), or Excel. Choose files again to add more uploads (up to 25 total).</p>
                    @endif
                    <input id="tpMediaInput" type="file" name="attendance_media[]" accept=".pdf,.jpg,.jpeg,.png,.webp,.xls,.xlsx" multiple>
                    @if (is_array($row->attendance_media_json) && count($row->attendance_media_json))
                        <p class="tp-field-hint">Current uploads:</p>
                        @include('staff.technical-trainings.partials.attendance-media-preview', [
                            'mediaItems' => (array) $row->attendance_media_json,
                            'attachmentRoute' => 'staff.district-workshop-sessions.attachment',
                            'record' => $row,
                        ])
                    @endif
                    <div id="tpMediaPreview" class="tp-media-preview"></div>
                </div>
            </div>

            <div class="tp-actions">
                <button class="tp-submit" type="submit">Update attendance</button>
                <a class="tp-link" href="{{ route('staff.district-workshop-sessions.export-single', $row) }}">Excel Export</a>
                <a class="tp-link" href="{{ route('staff.district-workshop-sessions.dashboard') }}">Back to dashboard</a>
            </div>
        </form>
    </div>
</div>

@include('staff.technical-trainings.partials.attendance-media-preview', [
    'mediaItems' => [],
    'showEmptyMessage' => false,
    'record' => null,
])
@endsection

@include('staff.district-workshop-sessions.partials.attendance-upload-script')
@include('staff.district-workshop-sessions.partials.workshop-photos-upload-script')
@include('staff.district-workshop-sessions.partials.participant-total-script')
