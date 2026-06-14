@extends('layouts.admin')

@section('title', \App\Models\StakeholderConsultationWorkshop::MODULE_LABEL)
@section('heading', \App\Models\StakeholderConsultationWorkshop::MODULE_LABEL)

@push('styles')
<style>
    .scw-shell { display:flex; flex-direction:column; gap:1.25rem; }
    .scw-alert { border-radius:12px; padding:0.85rem 1rem; font-size:0.88rem; }
    .scw-alert--info { background:#eff6ff; border:1px solid #bfdbfe; color:#1e40af; }
    .scw-alert--success { background:#f0fdf4; border:1px solid #86efac; color:#166534; }
    .scw-alert--error { background:#fef2f2; border:1px solid #fca5a5; color:#991b1b; }
    .scw-alert--error ul { margin:0.35rem 0 0 1rem; }
    .scw-card { background:#fff; border:1px solid #e2e8f0; border-radius:14px; padding:1.25rem 1.35rem; }
    .scw-card__title { margin:0 0 1.15rem; font-size:1rem; font-weight:700; color:#0f172a; }
    .scw-section { margin:0 0 0.65rem; font-size:0.72rem; font-weight:700; letter-spacing:0.08em; text-transform:uppercase; color:#64748b; }
    .scw-grid { display:grid; grid-template-columns:repeat(2, minmax(0, 1fr)); gap:1rem 1.1rem; align-items:start; }
    .scw-field { display:flex; flex-direction:column; gap:0.4rem; min-width:0; }
    .scw-field--full { grid-column:1 / -1; }
    .scw-field label { font-size:0.82rem; font-weight:700; color:#0f172a; }
    .scw-field input, .scw-field select, .scw-field textarea { width:100%; box-sizing:border-box; border:1px solid #cbd5e1; border-radius:8px; padding:0.58rem 0.7rem; font-size:0.88rem; background:#fff; }
    .scw-field textarea { min-height:5rem; resize:vertical; }
    .scw-readonly { background:#f8fafc; color:#64748b; }
    .scw-hint { margin:0.2rem 0 0; color:#64748b; font-size:0.78rem; }
    .scw-checks { display:grid; grid-template-columns:repeat(auto-fit, minmax(180px, 1fr)); gap:0.45rem; }
    .scw-checks label { display:flex; gap:0.45rem; align-items:flex-start; font-weight:500; font-size:0.84rem; }
    .scw-conditional[hidden] { display:none !important; }
    .scw-actions { margin-top:1.25rem; display:flex; flex-wrap:wrap; gap:0.65rem; }
    .scw-submit { border:none; border-radius:8px; background:#4f46e5; color:#fff; padding:0.62rem 1rem; font-weight:700; cursor:pointer; }
    .scw-link { color:#4f46e5; font-weight:700; text-decoration:none; }
    .scw-req { color:#e11d48; }
    @media (max-width:720px) { .scw-grid { grid-template-columns:1fr; } }
</style>
@endpush

@section('content')
@php
    $isEdit = isset($row);
    $selectedLevel = old('organizing_level', $isEdit ? $row->organizing_level : 'state');
    $selectedDepts = old('primary_departments', $isEdit ? (array) $row->primary_departments_json : []);
    $selectedTypes = old('stakeholder_types', $isEdit ? (array) $row->stakeholder_types_json : []);
    $hasExistingAttendance = $isEdit && $row->hasAttendanceSheet();
@endphp
<div class="scw-shell">
    <div class="scw-alert scw-alert--info">MIS <strong>12.1</strong> — Stakeholder Consultation Workshop. One form = one workshop.</div>

    @if (session('status'))<div class="scw-alert scw-alert--success">{{ session('status') }}</div>@endif
    @if ($errors->any())
        <div class="scw-alert scw-alert--error"><strong>Please fix:</strong><ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif

    <div class="scw-card">
        <h3 class="scw-card__title">{{ $isEdit ? 'Edit workshop' : 'New workshop' }}</h3>
        <form method="post" action="{{ route($storeRoute, $isEdit ? $row : []) }}" enctype="multipart/form-data">
            @csrf
            @if ($isEdit) @method('PUT') @endif

            <p class="scw-section">Session</p>
            <div class="scw-grid">
                <div class="scw-field"><label>Entered by</label><input type="text" class="scw-readonly" value="{{ $user->name }}" readonly></div>
                <div class="scw-field"><label>Workshop date <span class="scw-req">*</span></label><input type="date" name="workshop_date" value="{{ old('workshop_date', $isEdit ? $row->workshop_date?->format('Y-m-d') : '') }}" required></div>
                <div class="scw-field scw-field--full"><label>Workshop title <span class="scw-req">*</span></label><input type="text" name="workshop_title" maxlength="191" required value="{{ old('workshop_title', $isEdit ? $row->workshop_title : '') }}"></div>
                @include('staff.district-workshop-sessions.partials.workshop-mode-field', ['selected' => old('workshop_mode', $isEdit ? $row->workshop_mode : 'physical')])
                <div class="scw-field scw-field--full"><label>Venue / location <span class="scw-req">*</span></label><input type="text" name="venue" maxlength="191" required value="{{ old('venue', $isEdit ? $row->venue : '') }}"></div>
                <div class="scw-field">
                    <label>Organizing level <span class="scw-req">*</span></label>
                    <select id="scwOrganizingLevel" name="organizing_level" required>
                        @foreach ($organizingLevels as $value => $label)
                            <option value="{{ $value }}" @selected($selectedLevel === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="scw-field scw-conditional" id="scwHubWrap" @if(!in_array($selectedLevel, ['hub','spoke'], true)) hidden @endif>
                    <label for="hub_id">Hub <span class="scw-req">*</span></label>
                    <select id="hub_id" name="hub_id">
                        <option value="">— Select hub —</option>
                        @foreach ($hubs as $hub)
                            <option value="{{ $hub->id }}" @selected((string) old('hub_id', $isEdit ? $row->hub_id : '') === (string) $hub->id)>{{ $hub->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="scw-field scw-conditional" id="scwDistrictWrap" @if($selectedLevel !== 'spoke') hidden @endif>
                    <label for="district_id">District <span class="scw-req">*</span></label>
                    <select id="district_id" name="district_id">
                        <option value="">— Select district —</option>
                        @foreach ($districts as $district)
                            <option value="{{ $district->id }}" data-hub-id="{{ $district->hub_id }}" @selected((string) old('district_id', $isEdit ? $row->district_id : '') === (string) $district->id)>{{ $district->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <p class="scw-section" style="margin-top:1.25rem;">Stakeholders & departments</p>
            <div class="scw-grid">
                <div class="scw-field scw-field--full">
                    <label>Primary line department(s) <span class="scw-req">*</span></label>
                    <div class="scw-checks">
                        @foreach ($lineDepartments as $value => $label)
                            <label><input type="checkbox" name="primary_departments[]" value="{{ $value }}" @checked(in_array($value, (array) $selectedDepts, true))> {{ $label }}</label>
                        @endforeach
                    </div>
                </div>
                <div class="scw-field scw-field--full"><label>Other departments involved</label><textarea name="other_departments" maxlength="5000">{{ old('other_departments', $isEdit ? $row->other_departments : '') }}</textarea></div>
                <div class="scw-field scw-field--full">
                    <label>Stakeholder types present <span class="scw-req">*</span></label>
                    <div class="scw-checks">
                        @foreach ($stakeholderTypes as $value => $label)
                            <label><input type="checkbox" name="stakeholder_types[]" value="{{ $value }}" @checked(in_array($value, (array) $selectedTypes, true))> {{ $label }}</label>
                        @endforeach
                    </div>
                </div>
                <div class="scw-field"><label>Total participants <span class="scw-req">*</span></label><input type="number" name="total_participants" min="1" required value="{{ old('total_participants', $isEdit ? $row->total_participants : '') }}"></div>
                <div class="scw-field"><label>Officials / stakeholders count</label><input type="number" name="officials_count" min="0" value="{{ old('officials_count', $isEdit ? $row->officials_count : '') }}"></div>
            </div>

            <p class="scw-section" style="margin-top:1.25rem;">Consultation</p>
            <div class="scw-grid">
                <div class="scw-field scw-field--full"><label>Consultation theme / agenda <span class="scw-req">*</span></label><textarea name="consultation_theme" required maxlength="5000">{{ old('consultation_theme', $isEdit ? $row->consultation_theme : '') }}</textarea></div>
                <div class="scw-field scw-field--full"><label>Key outcomes / decisions <span class="scw-req">*</span></label><textarea name="key_outcomes" required maxlength="5000">{{ old('key_outcomes', $isEdit ? $row->key_outcomes : '') }}</textarea></div>
                <div class="scw-field">
                    <label>MoU / convergence planned?</label>
                    <select name="mou_convergence_planned">
                        <option value="">— Optional —</option>
                        @foreach ($mouOptions as $value => $label)
                            <option value="{{ $value }}" @selected(old('mou_convergence_planned', $isEdit ? $row->mou_convergence_planned : '') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <p class="scw-section" style="margin-top:1.25rem;">Proof</p>
            <div class="scw-grid">
                <div class="scw-field scw-field--full">
                    <label>Attendance sheet @if(!$hasExistingAttendance)<span class="scw-req">*</span>@endif</label>
                    <input type="file" name="attendance_media[]" accept=".pdf,.jpg,.jpeg,.png,.webp,.xls,.xlsx" multiple @if(!$hasExistingAttendance) required @endif>
                    <p class="scw-hint">PDF, image, or Excel. Up to 5 files.</p>
                </div>
                <div class="scw-field scw-field--full">
                    <label>Workshop photos</label>
                    <input type="file" name="workshop_photos[]" accept=".jpg,.jpeg,.png,.webp,image/*" multiple>
                    <p class="scw-hint">Optional — up to 3 photos.</p>
                </div>
                <div class="scw-field scw-field--full">
                    <label>Minutes / presentation</label>
                    <input type="file" name="minutes_media[]" accept=".pdf,.jpg,.jpeg,.png,.webp" multiple>
                    <p class="scw-hint">Optional — up to 3 files.</p>
                </div>
            </div>

            <div class="scw-actions">
                <button class="scw-submit" type="submit">{{ $isEdit ? 'Save changes' : 'Submit workshop' }}</button>
                <a class="scw-link" href="{{ route($dashboardRoute) }}">View dashboard</a>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    var levelSel = document.getElementById('scwOrganizingLevel');
    var hubWrap = document.getElementById('scwHubWrap');
    var districtWrap = document.getElementById('scwDistrictWrap');
    var hubSel = document.getElementById('hub_id');
    var districtSel = document.getElementById('district_id');
    if (!levelSel) return;

    function filterDistricts() {
        if (!districtSel || !hubSel) return;
        var hubId = hubSel.value || '';
        Array.from(districtSel.options).forEach(function (opt, idx) {
            if (idx === 0) return;
            var match = !hubId || opt.getAttribute('data-hub-id') === hubId;
            opt.hidden = !match;
            if (!match && opt.selected) opt.selected = false;
        });
    }

    function syncLevel() {
        var v = levelSel.value || '';
        if (hubWrap) hubWrap.hidden = v !== 'hub' && v !== 'spoke';
        if (districtWrap) districtWrap.hidden = v !== 'spoke';
        filterDistricts();
    }

    levelSel.addEventListener('change', syncLevel);
    if (hubSel) hubSel.addEventListener('change', filterDistricts);
    syncLevel();
})();
</script>
@endpush
