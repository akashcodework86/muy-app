@extends('layouts.admin')

@section('title', \App\Models\StakeholderCapacityBuildingSession::MODULE_LABEL)
@section('heading', \App\Models\StakeholderCapacityBuildingSession::MODULE_LABEL)

@push('styles')
<style>
    .cbs-shell { display:flex; flex-direction:column; gap:1.25rem; }
    .cbs-alert { border-radius:12px; padding:0.85rem 1rem; font-size:0.88rem; }
    .cbs-alert--warning { background:#fffbeb; border:1px solid #fde68a; color:#92400e; }
    .cbs-alert--success { background:#f0fdf4; border:1px solid #86efac; color:#166534; }
    .cbs-alert--error { background:#fef2f2; border:1px solid #fca5a5; color:#991b1b; }
    .cbs-alert--error ul { margin:0.35rem 0 0 1rem; }
    .cbs-alert--info { background:#eff6ff; border:1px solid #bfdbfe; color:#1e40af; }
    .cbs-card { background:#fff; border:1px solid #e2e8f0; border-radius:14px; padding:1.25rem 1.35rem; }
    .cbs-card__title { margin:0 0 1.15rem; font-size:1rem; font-weight:700; color:#0f172a; }
    .cbs-section { margin-top:1.25rem; }
    .cbs-section__label { margin:0 0 0.65rem; font-size:0.72rem; font-weight:700; letter-spacing:0.08em; text-transform:uppercase; color:#64748b; }
    .cbs-grid { display:grid; grid-template-columns:repeat(2, minmax(0, 1fr)); gap:1rem 1.1rem; align-items:start; }
    .cbs-field { display:flex; flex-direction:column; gap:0.4rem; min-width:0; }
    .cbs-field--full { grid-column:1 / -1; }
    .cbs-field label { font-size:0.82rem; font-weight:700; color:#0f172a; line-height:1.35; }
    .cbs-field input[type="text"],
    .cbs-field input[type="date"],
    .cbs-field input[type="number"],
    .cbs-field input[type="file"],
    .cbs-field select,
    .cbs-field textarea { width:100%; box-sizing:border-box; border:1px solid #cbd5e1; border-radius:8px; padding:0.58rem 0.7rem; font-size:0.88rem; background:#fff; }
    .cbs-field textarea { min-height:5rem; resize:vertical; }
    .cbs-field input[type="file"] { padding:0.45rem 0.55rem; background:#f8fafc; }
    .cbs-readonly { background:#f8fafc; color:#64748b; }
    .cbs-field-hint { margin:0.2rem 0 0; color:#64748b; font-size:0.78rem; line-height:1.4; }
    .cbs-actions { margin-top:1.25rem; display:flex; flex-wrap:wrap; gap:0.65rem; align-items:center; }
    .cbs-submit { border:none; border-radius:8px; background:#4f46e5; color:#fff; padding:0.62rem 1rem; font-weight:700; cursor:pointer; font-size:0.88rem; }
    .cbs-link { color:#4f46e5; font-weight:700; text-decoration:none; font-size:0.88rem; }
    .cbs-req { color:#e11d48; margin-left:1px; }
    .cbs-conditional[hidden] { display:none !important; }
    @media (max-width: 720px) { .cbs-grid { grid-template-columns:1fr; } }
</style>
@endpush

@section('content')
@php
    $isEdit = isset($row);
    $stakeholderType = old('stakeholder_type', $isEdit ? $row->stakeholder_type : '');
    $hasExistingAttendance = $isEdit && $row->hasAttendanceSheet();
@endphp
<div class="cbs-shell">
    @if (!empty($migrationMissing))
        <div class="cbs-alert cbs-alert--warning">
            <strong>Table not found.</strong> Run <code>php artisan migrate</code> first.
        </div>
    @endif

    @if (session('status'))
        <div class="cbs-alert cbs-alert--success">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="cbs-alert cbs-alert--error">
            <strong>Please fix:</strong>
            <ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="cbs-alert cbs-alert--info">
        MIS <strong>3.4</strong> — Capacity building of stakeholders (REAP, USRLM, line department staff). One form = one session.
    </div>

    <div class="cbs-card">
        <h3 class="cbs-card__title">{{ $isEdit ? 'Edit session' : 'New session' }}</h3>
        <form id="cbsForm" method="post" action="{{ route($storeRoute, $isEdit ? $row : []) }}" enctype="multipart/form-data">
            @csrf
            @if ($isEdit)
                @method('PUT')
            @endif
            <p class="cbs-section__label">Session</p>
            <div class="cbs-grid">
                <div class="cbs-field">
                    <label>Entered by</label>
                    <input type="text" class="cbs-readonly" value="{{ $user->name }}" readonly>
                </div>
                <div class="cbs-field">
                    <label>Session date <span class="cbs-req">*</span></label>
                    <x-activity-date-input name="session_date" :value="$isEdit ? $row->session_date?->format('Y-m-d') : null" />
                </div>
                @include('staff.district-workshop-sessions.partials.workshop-mode-field', ['selected' => old('workshop_mode', $isEdit ? $row->workshop_mode : 'physical')])
                <div class="cbs-field cbs-field--full">
                    <label>Venue / location <span class="cbs-req">*</span></label>
                    <input type="text" name="venue" value="{{ old('venue', $isEdit ? $row->venue : '') }}" maxlength="191" required placeholder="Office, city, or venue name">
                </div>
            </div>

            <p class="cbs-section__label" style="margin-top:1.25rem;">Stakeholders</p>
            <div class="cbs-grid">
                <div class="cbs-field cbs-field--full">
                    <label for="stakeholder_type">Stakeholder type <span class="cbs-req">*</span></label>
                    <select id="stakeholder_type" name="stakeholder_type" required data-cbs-stakeholder-type>
                        <option value="" disabled @selected($stakeholderType === '' || $stakeholderType === null)>— Select —</option>
                        @foreach ($stakeholderTypes as $value => $label)
                            <option value="{{ $value }}" @selected($stakeholderType === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="cbs-field cbs-field--full cbs-conditional" id="cbsOtherTypeWrap" @if($stakeholderType !== 'other') hidden @endif>
                    <label for="stakeholder_type_other">Specify stakeholder type <span class="cbs-req">*</span></label>
                    <input type="text" id="stakeholder_type_other" name="stakeholder_type_other" value="{{ old('stakeholder_type_other', $isEdit ? $row->stakeholder_type_other : '') }}" maxlength="191" placeholder="e.g. NGO partner">
                </div>
                <div class="cbs-field cbs-field--full cbs-conditional" id="cbsDeptWrap" @if(!in_array($stakeholderType, ['line_department', 'other'], true)) hidden @endif>
                    <label for="department_name">Department / agency name <span class="cbs-req">*</span></label>
                    <input type="text" id="department_name" name="department_name" value="{{ old('department_name', $isEdit ? $row->department_name : '') }}" maxlength="191" placeholder="e.g. Agriculture, Tourism">
                </div>
            </div>

            <p class="cbs-section__label" style="margin-top:1.25rem;">Training</p>
            <div class="cbs-grid">
                <div class="cbs-field cbs-field--full">
                    <label>Training title <span class="cbs-req">*</span></label>
                    <input type="text" name="session_title" value="{{ old('session_title', $isEdit ? $row->session_title : '') }}" maxlength="191" required>
                </div>
                <div class="cbs-field cbs-field--full">
                    <label>Topics covered</label>
                    <textarea name="topics_covered" maxlength="5000" placeholder="Optional short summary">{{ old('topics_covered', $isEdit ? $row->topics_covered : '') }}</textarea>
                </div>
                <div class="cbs-field">
                    <label>Staff trained (total) <span class="cbs-req">*</span></label>
                    <input type="number" name="staff_trained_total" value="{{ old('staff_trained_total', $isEdit ? $row->staff_trained_total : '') }}" min="1" step="1" required>
                </div>
            </div>

            <p class="cbs-section__label" style="margin-top:1.25rem;">Proof</p>
            <div class="cbs-grid">
                <div class="cbs-field cbs-field--full">
                    <label>Attendance sheet @if (! $hasExistingAttendance)<span class="cbs-req">*</span>@endif</label>
                    <input type="file" name="attendance_media[]" accept=".pdf,.jpg,.jpeg,.png,.webp,.xls,.xlsx" @if (! $hasExistingAttendance) required @endif>
                    <p class="cbs-field-hint">
                        @if ($hasExistingAttendance)
                            Upload only to add more files (existing files are kept). PDF, image, or Excel.
                        @else
                            PDF, image, or Excel. At least one file required.
                        @endif
                    </p>
                    @if ($hasExistingAttendance)
                        @include('staff.technical-trainings.partials.attendance-media-preview', [
                            'mediaItems' => (array) $row->attendance_media_json,
                            'attachmentRoute' => 'spoc.capacity-building-stakeholders.attachment',
                            'record' => $row,
                            'showEmptyMessage' => false,
                        ])
                    @endif
                </div>
                <div class="cbs-field cbs-field--full">
                    <label>Workshop photos</label>
                    <input id="cbsPhotosInput" type="file" name="workshop_photos[]" accept=".jpg,.jpeg,.png,.webp,image/*" multiple>
                    <p class="cbs-field-hint">
                        @if ($isEdit && $row->hasWorkshopPhotos())
                            Upload only to add more (up to 3 total). New selections preview below; click × to remove before submit.
                        @else
                            Optional — up to 3 JPG/PNG photos. Preview appears below; click × to remove before submit.
                        @endif
                    </p>
                    @if ($isEdit && $row->hasWorkshopPhotos())
                        @include('staff.technical-trainings.partials.attendance-media-preview', [
                            'mediaItems' => $row->workshopPhotoItems(),
                            'attachmentRoute' => 'spoc.capacity-building-stakeholders.attachment',
                            'attachmentQuery' => ['collection' => 'photos'],
                            'record' => $row,
                            'showEmptyMessage' => false,
                        ])
                    @endif
                    <div id="cbsPhotosPreview" class="cbs-media-preview"></div>
                </div>
            </div>

            <div class="cbs-actions">
                <button class="cbs-submit" type="submit">{{ $isEdit ? 'Save changes' : 'Submit session' }}</button>
                <a class="cbs-link" href="{{ route($dashboardRoute) }}">View dashboard</a>
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

@include('capacity-building-stakeholders.partials.photo-upload-script')

@push('scripts')
<script>
(function () {
    var typeSel = document.querySelector('[data-cbs-stakeholder-type]');
    var otherWrap = document.getElementById('cbsOtherTypeWrap');
    var deptWrap = document.getElementById('cbsDeptWrap');
    if (!typeSel || !otherWrap || !deptWrap) return;

    function sync() {
        var v = typeSel.value || '';
        otherWrap.hidden = v !== 'other';
        deptWrap.hidden = v !== 'line_department' && v !== 'other';
    }

    typeSel.addEventListener('change', sync);
    sync();
}());
</script>
@endpush
