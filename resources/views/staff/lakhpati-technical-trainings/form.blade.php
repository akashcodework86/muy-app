@extends('layouts.admin')

@section('title', \App\Models\LakhpatiTechnicalTraining::MODULE_LABEL)
@section('heading', \App\Models\LakhpatiTechnicalTraining::MODULE_LABEL)

@push('styles')
<style>
    .tp-shell { display:flex; flex-direction:column; gap:1.25rem; }
    .tp-alert { border-radius:12px; padding:0.85rem 1rem; font-size:0.88rem; }
    .tp-alert--warning { background:#fffbeb; border:1px solid #fde68a; color:#92400e; }
    .tp-alert--success { background:#f0fdf4; border:1px solid #86efac; color:#166534; }
    .tp-alert--error { background:#fef2f2; border:1px solid #fca5a5; color:#991b1b; }
    .tp-alert--error ul { margin:0.35rem 0 0 1rem; }
    .tp-alert--info { background:#eff6ff; border:1px solid #bfdbfe; color:#1e40af; }
    .tp-card { background:#fff; border:1px solid #e2e8f0; border-radius:14px; padding:1.25rem 1.35rem; }
    .tp-card__title { margin:0 0 1.15rem; font-size:1rem; font-weight:700; color:#0f172a; }
    .tp-section { margin-top:1.35rem; }
    .tp-section__label { margin:0 0 0.65rem; font-size:0.72rem; font-weight:700; letter-spacing:0.08em; text-transform:uppercase; color:#64748b; }
    .tp-grid { display:grid; grid-template-columns:repeat(2, minmax(0, 1fr)); gap:1rem 1.1rem; align-items:start; }
    .tp-field { display:flex; flex-direction:column; gap:0.4rem; min-width:0; }
    .tp-field--full { grid-column:1 / -1; }
    .tp-field label { font-size:0.82rem; font-weight:700; color:#0f172a; line-height:1.35; }
    .tp-field input[type="text"],
    .tp-field input[type="date"],
    .tp-field input[type="number"],
    .tp-field input[type="file"],
    .tp-field select,
    .tp-field textarea { width:100%; box-sizing:border-box; border:1px solid #cbd5e1; border-radius:8px; padding:0.58rem 0.7rem; font-size:0.88rem; background:#fff; }
    .tp-field textarea { min-height:5.5rem; resize:vertical; }
    .tp-field input[type="file"] { padding:0.45rem 0.55rem; background:#f8fafc; }
    .tp-readonly { background:#f8fafc; color:#64748b; }
    .tp-field-hint { margin:0.2rem 0 0; color:#64748b; font-size:0.78rem; line-height:1.4; }
    .tp-media-preview { margin-top:0.55rem; }
    .tp-actions { margin-top:1.25rem; display:flex; flex-wrap:wrap; gap:0.65rem; align-items:center; }
    .tp-submit { border:none; border-radius:8px; background:#4f46e5; color:#fff; padding:0.62rem 1rem; font-weight:700; cursor:pointer; font-size:0.88rem; }
    .tp-link { color:#4f46e5; font-weight:700; text-decoration:none; font-size:0.88rem; }
    .tp-req { color:#e11d48; margin-left:1px; }
</style>
@endpush

@section('content')
<div class="tp-shell">
    @if (!empty($migrationMissing))
        <div class="tp-alert tp-alert--warning">
            <strong>Table not found.</strong> Run <code>php artisan migrate</code> first.
        </div>
    @endif

    @if (session('status'))
        <div class="tp-alert tp-alert--success">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="tp-alert tp-alert--error">
            <strong>Please fix:</strong>
            <ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="tp-alert tp-alert--info">
        For partner-requested technical trainings to Potential Lakhpati Didis / SHG members / CBOs (MIS 3.3.1).
        Attendance is optional — submit the session now and add participant details later via Edit.
    </div>

    <div class="tp-card">
        <h3 class="tp-card__title">New session entry</h3>
        <form id="lttWorkshopForm" method="post" action="{{ route('staff.lakhpati-technical-trainings.store') }}" enctype="multipart/form-data">
            @csrf
            <p class="tp-section__label">Section A — Session details</p>
            <div class="tp-grid">
                <div class="tp-field">
                    <label>Session entered by</label>
                    <input type="text" class="tp-readonly" value="{{ $user->name }}" readonly>
                </div>
                <div class="tp-field">
                    <label>Date of session <span class="tp-req">*</span></label>
                    <input type="date" name="session_date" value="{{ old('session_date') }}" required>
                </div>
                <div class="tp-field">
                    <label>District</label>
                    <input type="text" class="tp-readonly" value="{{ $user->district?->name ?? 'Not assigned' }}" readonly>
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
                    <input type="text" name="area" value="{{ old('area') }}" maxlength="191" required placeholder="Village or venue name">
                </div>
                @include('staff.district-workshop-sessions.partials.workshop-mode-field', ['selected' => old('workshop_mode', 'physical')])
            </div>

            <p class="tp-section__label" style="margin-top:1.25rem;">Section B — Requesting agency</p>
            <div class="tp-grid">
                @include('staff.lakhpati-technical-trainings.partials.agency-type-field', [
                    'agencyTypes' => $agencyTypes ?? [],
                    'selected' => old('requesting_agency_type'),
                ])
            </div>

            <p class="tp-section__label" style="margin-top:1.25rem;">Section C — Training details</p>
            <div class="tp-grid">
                <div class="tp-field tp-field--full">
                    <label>Training title <span class="tp-req">*</span></label>
                    <input type="text" name="session_title" value="{{ old('session_title') }}" maxlength="191" required>
                </div>
                <div class="tp-field tp-field--full">
                    <label>Session brief (optional)</label>
                    <textarea name="session_brief" maxlength="5000" placeholder="Topics covered or short summary">{{ old('session_brief') }}</textarea>
                </div>
            </div>

            <p class="tp-section__label" style="margin-top:1.25rem;">Section D — Attendance (optional)</p>
            <div class="tp-grid">
                <div class="tp-field">
                    <label>Male participants</label>
                    <input id="tpMaleParticipants" type="number" name="male_participants" value="{{ old('male_participants', 0) }}" min="0" step="1">
                </div>
                <div class="tp-field">
                    <label>Female participants</label>
                    <input id="tpFemaleParticipants" type="number" name="female_participants" value="{{ old('female_participants', 0) }}" min="0" step="1">
                </div>
                <div class="tp-field">
                    <label>Total participants</label>
                    <input id="tpTotalParticipants" type="text" class="tp-readonly" value="{{ (int) old('male_participants', 0) + (int) old('female_participants', 0) }}" readonly>
                </div>
            </div>

            @include('staff.partials.workshop-participants.registry')

            <div class="tp-section">
                <div class="tp-field">
                    <label>Upload workshop photos (optional)</label>
                    <input id="tpPhotosInput" type="file" name="workshop_photos[]" accept=".jpg,.jpeg,.png,.webp,image/*" multiple>
                    <p class="tp-field-hint">JPG or PNG, up to 5 photos (50 MB each). Shown on the dashboard after upload.</p>
                    <div id="tpPhotosPreview" class="tp-media-preview"></div>
                </div>
            </div>

            <div class="tp-section">
                <div class="tp-field">
                    <label>Upload attendance sheet (optional)</label>
                    <input id="tpMediaInput" type="file" name="attendance_media[]" accept=".pdf,.jpg,.jpeg,.png,.webp,.xls,.xlsx" multiple>
                    <p class="tp-field-hint">PDF, image, or Excel. You can add this later from Edit.</p>
                    <div id="tpMediaPreview" class="tp-media-preview"></div>
                </div>
            </div>

            <div class="tp-actions">
                <button class="tp-submit" type="submit">Submit session</button>
                <a class="tp-link" href="{{ route('staff.lakhpati-technical-trainings.dashboard') }}">View dashboard</a>
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
