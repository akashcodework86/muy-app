@extends('layouts.admin')

@section('title', 'Edit — '.\App\Models\LakhpatiTechnicalTraining::MODULE_LABEL)
@section('heading', 'Edit — '.\App\Models\LakhpatiTechnicalTraining::MODULE_LABEL)

@push('styles')
<style>
    .tp-shell { display:flex; flex-direction:column; gap:1.25rem; }
    .tp-alert--error { border-radius:12px; padding:0.85rem 1rem; background:#fef2f2; border:1px solid #fca5a5; color:#991b1b; }
    .tp-card { background:#fff; border:1px solid #e2e8f0; border-radius:14px; padding:1.25rem 1.35rem; }
    .tp-card__title { margin:0 0 1.15rem; font-size:1rem; font-weight:700; }
    .tp-section__label { margin:0 0 0.65rem; font-size:0.72rem; font-weight:700; text-transform:uppercase; color:#64748b; }
    .tp-grid { display:grid; grid-template-columns:repeat(2, minmax(0,1fr)); gap:1rem; }
    .tp-field { display:flex; flex-direction:column; gap:0.4rem; }
    .tp-field--full { grid-column:1 / -1; }
    .tp-field input, .tp-field select, .tp-field textarea { border:1px solid #cbd5e1; border-radius:8px; padding:0.58rem 0.7rem; }
    .tp-readonly { background:#f8fafc; color:#64748b; }
    .tp-submit { border:none; border-radius:8px; background:#4f46e5; color:#fff; padding:0.62rem 1rem; font-weight:700; cursor:pointer; }
    .tp-link { color:#4f46e5; font-weight:700; text-decoration:none; }
    .tp-req { color:#e11d48; }
    .tp-field-hint { font-size:0.78rem; color:#64748b; }
    .tp-actions { margin-top:1.25rem; display:flex; gap:0.65rem; flex-wrap:wrap; }
</style>
@endpush

@section('content')
<div class="tp-shell">
    @if ($errors->any())
        <div class="tp-alert tp-alert--error">
            <strong>Please fix:</strong>
            <ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="tp-card">
        <h3 class="tp-card__title">Update session</h3>
        <form id="lttWorkshopForm" method="post" action="{{ route('staff.lakhpati-technical-trainings.update', $row) }}" enctype="multipart/form-data">
            @csrf
            @method('put')
            <p class="tp-section__label">Section A — Session details</p>
            <div class="tp-grid">
                <div class="tp-field">
                    <label>Session entered by</label>
                    <input type="text" class="tp-readonly" value="{{ $row->submitted_by_name }}" readonly>
                </div>
                <div class="tp-field">
                    <label>Date of session <span class="tp-req">*</span></label>
                    <x-activity-date-input name="session_date" :value="$row->session_date?->format('Y-m-d')" />
                </div>
                <div class="tp-field">
                    <label>District</label>
                    <input type="text" class="tp-readonly" value="{{ $row->district_name ?: ($row->district?->name ?? 'NA') }}" readonly>
                </div>
                @include('staff.partials.workshop-participants.location-fields', [
                    'user' => $user,
                    'blockRows' => $blockRows ?? collect(),
                    'gramPanchayatsEnabled' => $gramPanchayatsEnabled ?? false,
                    'defaultBlockId' => $defaultBlockId ?? 0,
                    'defaultGpId' => $defaultGpId ?? 0,
                ])
                <div class="tp-field tp-field--full">
                    <label>Venue / area <span class="tp-req">*</span></label>
                    <input type="text" name="area" value="{{ old('area', $row->area) }}" maxlength="191" required>
                </div>
                @include('staff.district-workshop-sessions.partials.workshop-mode-field', ['selected' => old('workshop_mode', $row->workshop_mode ?? 'physical')])
            </div>

            <p class="tp-section__label" style="margin-top:1.25rem;">Section B — Requesting agency</p>
            <div class="tp-grid">
                @include('staff.lakhpati-technical-trainings.partials.agency-type-field', [
                    'agencyTypes' => $agencyTypes ?? [],
                    'selected' => old('requesting_agency_type', $row->requesting_agency_type),
                ])
            </div>

            <p class="tp-section__label" style="margin-top:1.25rem;">Section C — Training details</p>
            <div class="tp-grid">
                <div class="tp-field tp-field--full">
                    <label>Training title <span class="tp-req">*</span></label>
                    <input type="text" name="session_title" value="{{ old('session_title', $row->session_title) }}" maxlength="191" required>
                </div>
                <div class="tp-field tp-field--full">
                    <label>Session brief (optional)</label>
                    <textarea name="session_brief" maxlength="5000">{{ old('session_brief', $row->session_brief) }}</textarea>
                </div>
            </div>

            <p class="tp-section__label" style="margin-top:1.25rem;">Section D — Attendance (optional)</p>
            <div class="tp-grid">
                <div class="tp-field">
                    <label>Male participants</label>
                    <input id="tpMaleParticipants" type="number" name="male_participants" value="{{ old('male_participants', $row->male_participants ?? 0) }}" min="0" step="1">
                </div>
                <div class="tp-field">
                    <label>Female participants</label>
                    <input id="tpFemaleParticipants" type="number" name="female_participants" value="{{ old('female_participants', $row->female_participants ?? 0) }}" min="0" step="1">
                </div>
                <div class="tp-field">
                    <label>Total participants</label>
                    <input id="tpTotalParticipants" type="text" class="tp-readonly" value="{{ (int) old('male_participants', $row->male_participants ?? 0) + (int) old('female_participants', $row->female_participants ?? 0) }}" readonly>
                </div>
            </div>

            @include('staff.partials.workshop-participants.registry')

            <div class="tp-section">
                <div class="tp-field">
                    <label>Upload workshop photos (optional)</label>
                    @if ($row->hasWorkshopPhotos())
                        <p class="tp-field-hint">Existing photos are kept. Upload more below (max 5 total).</p>
                        @include('staff.technical-trainings.partials.attendance-media-preview', [
                            'mediaItems' => $row->workshopPhotoItems(),
                            'attachmentRoute' => 'staff.lakhpati-technical-trainings.attachment',
                            'attachmentQuery' => ['collection' => 'photos'],
                            'record' => $row,
                            'showEmptyMessage' => false,
                        ])
                    @endif
                    <input id="tpPhotosInput" type="file" name="workshop_photos[]" accept=".jpg,.jpeg,.png,.webp,image/*" multiple>
                    <div id="tpPhotosPreview" class="tp-media-preview"></div>
                </div>
            </div>

            <div class="tp-section">
                <div class="tp-field">
                    <label>Upload attendance sheet (optional)</label>
                    @if ($row->hasAttendanceSheet())
                        <p class="tp-field-hint">Existing files are kept. Upload more below to add.</p>
                        @include('staff.technical-trainings.partials.attendance-media-preview', [
                            'mediaItems' => (array) $row->attendance_media_json,
                            'attachmentRoute' => 'staff.lakhpati-technical-trainings.attachment',
                            'record' => $row,
                            'showEmptyMessage' => false,
                        ])
                    @endif
                    <input id="tpMediaInput" type="file" name="attendance_media[]" accept=".pdf,.jpg,.jpeg,.png,.webp,.xls,.xlsx" multiple>
                    <div id="tpMediaPreview" class="tp-media-preview"></div>
                </div>
            </div>

            <div class="tp-actions">
                <button class="tp-submit" type="submit">Save changes</button>
                <a class="tp-link" href="{{ route('staff.lakhpati-technical-trainings.export-single', $row) }}">Excel export</a>
                <a class="tp-link" href="{{ route('staff.lakhpati-technical-trainings.dashboard') }}">Back to dashboard</a>
            </div>
        </form>
    </div>

    @include('staff.technical-trainings.partials.attendance-media-preview', [
        'mediaItems' => [],
        'showEmptyMessage' => false,
        'record' => null,
    ])
</div>
@endsection

@include('staff.partials.workshop-participants.script', [
    'maleInputId' => 'tpMaleParticipants',
    'femaleInputId' => 'tpFemaleParticipants',
    'formId' => 'lttWorkshopForm',
    'gramPanchayatsUrl' => route('staff.lakhpati-technical-trainings.gram-panchayats'),
    'districtLabel' => $districtLabel ?? ($user->district?->name ?? '—'),
    'initialRows' => $initialRows ?? [],
    'defaultBlockId' => $defaultBlockId ?? 0,
    'defaultGpId' => $defaultGpId ?? 0,
])
@include('staff.district-workshop-sessions.partials.attendance-upload-script')
@include('staff.district-workshop-sessions.partials.workshop-photos-upload-script')
@include('staff.district-workshop-sessions.partials.participant-total-script')
