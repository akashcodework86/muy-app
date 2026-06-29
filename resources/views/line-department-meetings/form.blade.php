@extends('layouts.admin')

@section('title', \App\Models\LineDepartmentMeeting::MODULE_LABEL)
@section('heading', \App\Models\LineDepartmentMeeting::MODULE_LABEL)

@push('styles')
<style>
    .ldm-shell { display:flex; flex-direction:column; gap:1.25rem; }
    .ldm-alert { border-radius:12px; padding:0.85rem 1rem; font-size:0.88rem; }
    .ldm-alert--info { background:#eff6ff; border:1px solid #bfdbfe; color:#1e40af; }
    .ldm-alert--success { background:#f0fdf4; border:1px solid #86efac; color:#166534; }
    .ldm-alert--error { background:#fef2f2; border:1px solid #fca5a5; color:#991b1b; }
    .ldm-alert--error ul { margin:0.35rem 0 0 1rem; }
    .ldm-card { background:#fff; border:1px solid #e2e8f0; border-radius:14px; padding:1.25rem 1.35rem; max-width:56rem; }
    .ldm-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:1rem 1.1rem; }
    .ldm-field { display:flex; flex-direction:column; gap:0.4rem; }
    .ldm-field--full { grid-column:1 / -1; }
    .ldm-field label { font-size:0.82rem; font-weight:700; }
    .ldm-field input, .ldm-field select, .ldm-field textarea { width:100%; box-sizing:border-box; border:1px solid #cbd5e1; border-radius:8px; padding:0.58rem 0.7rem; font-size:0.88rem; }
    .ldm-field textarea { min-height:5rem; resize:vertical; }
    .ldm-readonly { background:#f8fafc; color:#64748b; }
    .ldm-conditional[hidden] { display:none !important; }
    .ldm-other[hidden] { display:none !important; }
    .ldm-actions { margin-top:1.25rem; display:flex; gap:0.65rem; flex-wrap:wrap; }
    .ldm-submit { border:none; border-radius:8px; background:#0d9488; color:#fff; padding:0.62rem 1rem; font-weight:700; cursor:pointer; }
    .ldm-link { color:#0d9488; font-weight:700; text-decoration:none; }
    .ldm-req { color:#b91c1c; }
    @media (max-width:720px) { .ldm-grid { grid-template-columns:1fr; } }
</style>
@endpush

@section('content')
@php
    $isEdit = isset($row);
    $selectedLevel = old('meeting_level', $isEdit ? $row->meeting_level : ($user->role === 'district_staff' ? 'spoke' : ($user->role === 'hub_admin' ? 'hub' : 'state')));
    $selectedPurpose = old('meeting_purpose', $isEdit ? $row->meeting_purpose : '');
    $selectedDepartment = $selectedDepartment ?? old('department_name', '');
    $departmentNameOther = $departmentNameOther ?? old('department_name_other', '');
    $agendaRemarkOutcome = $agendaRemarkOutcome ?? old('agenda_remark_outcome', '');
    $defaultHub = old('hub_id', $isEdit ? $row->hub_id : ($defaultHubId ?: ''));
    $defaultDistrict = old('district_id', $isEdit ? $row->district_id : ($defaultDistrictId ?: ''));
@endphp
<div class="ldm-shell">
    <div class="ldm-alert ldm-alert--info">MIS <strong>12.2</strong> — Meeting of staff with Line Department. One form = one meeting.</div>
    @if (session('status'))<div class="ldm-alert ldm-alert--success">{{ session('status') }}</div>@endif
    @if ($errors->any())<div class="ldm-alert ldm-alert--error"><strong>Please fix:</strong><ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

    <div class="ldm-card">
        <h3 style="margin:0 0 1rem;font-weight:700;">{{ $isEdit ? 'Edit meeting' : 'New meeting' }}</h3>
        <form method="post" action="{{ route($storeRoute, $isEdit ? $row : []) }}" enctype="multipart/form-data">
            @csrf @if ($isEdit) @method('PUT') @endif
            <div class="ldm-grid">
                <div class="ldm-field"><label>Entered by</label><input class="ldm-readonly" type="text" value="{{ $user->name }}" readonly></div>
                <div class="ldm-field"><label>Meeting date <span class="ldm-req">*</span></label><input type="date" name="meeting_date" required value="{{ old('meeting_date', $isEdit ? $row->meeting_date?->format('Y-m-d') : now()->toDateString()) }}"></div>
                <div class="ldm-field">
                    <label>Meeting level <span class="ldm-req">*</span></label>
                    <select id="ldmMeetingLevel" name="meeting_level" required>
                        @foreach ($meetingLevels as $value => $label)
                            <option value="{{ $value }}" @selected($selectedLevel === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="ldm-field ldm-conditional" id="ldmHubWrap" @if(!in_array($selectedLevel, ['hub','spoke'], true)) hidden @endif>
                    <label for="hub_id">Hub <span class="ldm-req">*</span></label>
                    <select id="hub_id" name="hub_id" @if($user->role === 'hub_admin') disabled @endif>
                        <option value="">— Select —</option>
                        @foreach ($hubs as $hub)
                            <option value="{{ $hub->id }}" @selected((string) $defaultHub === (string) $hub->id)>{{ $hub->name }}</option>
                        @endforeach
                    </select>
                    @if ($user->role === 'hub_admin')<input type="hidden" name="hub_id" value="{{ $defaultHubId }}">@endif
                </div>
                <div class="ldm-field ldm-conditional" id="ldmDistrictWrap" @if($selectedLevel !== 'spoke') hidden @endif>
                    <label for="district_id">District <span class="ldm-req">*</span></label>
                    <select id="district_id" name="district_id" @if($user->role === 'district_staff') disabled @endif>
                        <option value="">— Select —</option>
                        @foreach ($districts as $district)
                            <option value="{{ $district->id }}" data-hub-id="{{ $district->hub_id }}" @selected((string) $defaultDistrict === (string) $district->id)>{{ $district->name }}</option>
                        @endforeach
                    </select>
                    @if ($user->role === 'district_staff')<input type="hidden" name="district_id" value="{{ $defaultDistrictId }}">@endif
                </div>
                <div class="ldm-field">
                    <label>Meeting mode <span class="ldm-req">*</span></label>
                    <select name="meeting_mode" required>
                        @foreach ($meetingModes as $value => $label)
                            <option value="{{ $value }}" @selected(old('meeting_mode', $isEdit ? $row->meeting_mode : 'physical') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="ldm-field"><label>Venue / location</label><input type="text" name="venue" maxlength="191" value="{{ old('venue', $isEdit ? $row->venue : '') }}"></div>
                <div class="ldm-field">
                    <label>Line department name <span class="ldm-req">*</span></label>
                    <select id="ldmDepartment" name="department_name" required>
                        <option value="">— Select —</option>
                        @foreach ($departmentNames as $value => $label)
                            <option value="{{ $value }}" @selected($selectedDepartment === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="ldm-field ldm-other" id="ldmDepartmentOtherWrap" @if($selectedDepartment !== 'Other') hidden @endif>
                    <label>Specify other department <span class="ldm-req">*</span></label>
                    <input type="text" name="department_name_other" maxlength="191" value="{{ $departmentNameOther }}">
                </div>
                <div class="ldm-field"><label>Department unit</label><input type="text" name="department_unit" maxlength="191" value="{{ old('department_unit', $isEdit ? $row->department_unit : '') }}"></div>
                <div class="ldm-field"><label>Official met — name <span class="ldm-req">*</span></label><input type="text" name="official_name" maxlength="191" required value="{{ old('official_name', $isEdit ? $row->official_name : '') }}"></div>
                <div class="ldm-field"><label>Official met — designation <span class="ldm-req">*</span></label><input type="text" name="official_designation" maxlength="191" required value="{{ old('official_designation', $isEdit ? $row->official_designation : '') }}"></div>
                <div class="ldm-field"><label>Official contact phone</label><input type="tel" name="official_phone" maxlength="10" pattern="[6-9][0-9]{9}" value="{{ old('official_phone', $isEdit ? $row->official_phone : '') }}"></div>
                <div class="ldm-field">
                    <label>Purpose of meeting <span class="ldm-req">*</span></label>
                    <select id="ldmPurpose" name="meeting_purpose" required>
                        <option value="">— Select —</option>
                        @foreach ($meetingPurposes as $value => $label)
                            <option value="{{ $value }}" @selected($selectedPurpose === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="ldm-field ldm-other" id="ldmPurposeOtherWrap" @if($selectedPurpose !== 'other') hidden @endif>
                    <label>Specify other purpose <span class="ldm-req">*</span></label>
                    <input type="text" name="meeting_purpose_other" maxlength="191" value="{{ old('meeting_purpose_other', $isEdit ? $row->meeting_purpose_other : '') }}">
                </div>
                <div class="ldm-field ldm-field--full"><label>Agenda/Remark/outcome <span class="ldm-req">*</span></label><textarea name="agenda_remark_outcome" required maxlength="5000">{{ $agendaRemarkOutcome }}</textarea></div>
                <div class="ldm-field ldm-field--full">
                    <label>Meeting proof or photo</label>
                    <input type="file" name="meeting_media[]" accept=".pdf,.jpg,.jpeg,.png,.webp,image/*" multiple>
                    <p style="margin:0.2rem 0 0;color:#64748b;font-size:0.78rem;">Optional — minutes, letter, email screenshot, or photos. Up to 5 files.</p>
                    @if ($isEdit && $row->hasMeetingMedia())
                        <p style="margin:0.35rem 0 0;color:#64748b;font-size:0.78rem;">{{ count($row->meetingMediaItems()) }} file(s) already uploaded. Add more below (max 5 total).</p>
                    @endif
                </div>
            </div>
            <div class="ldm-actions">
                <button class="ldm-submit" type="submit">{{ $isEdit ? 'Save changes' : 'Submit meeting' }}</button>
                <a class="ldm-link" href="{{ route($dashboardRoute) }}">View dashboard</a>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    var levelSel = document.getElementById('ldmMeetingLevel');
    var hubWrap = document.getElementById('ldmHubWrap');
    var districtWrap = document.getElementById('ldmDistrictWrap');
    var hubSel = document.getElementById('hub_id');
    var districtSel = document.getElementById('district_id');
    var purposeSel = document.getElementById('ldmPurpose');
    var purposeOther = document.getElementById('ldmPurposeOtherWrap');
    var departmentSel = document.getElementById('ldmDepartment');
    var departmentOther = document.getElementById('ldmDepartmentOtherWrap');

    function filterDistricts() {
        if (!districtSel || !hubSel || hubSel.disabled) return;
        var hubId = hubSel.value || '';
        Array.from(districtSel.options).forEach(function (opt, idx) {
            if (idx === 0) return;
            var match = !hubId || opt.getAttribute('data-hub-id') === hubId;
            opt.hidden = !match;
        });
    }

    function syncLevel() {
        if (!levelSel) return;
        var v = levelSel.value || '';
        if (hubWrap) hubWrap.hidden = v !== 'hub' && v !== 'spoke';
        if (districtWrap) districtWrap.hidden = v !== 'spoke';
        filterDistricts();
    }

    if (levelSel) levelSel.addEventListener('change', syncLevel);
    if (hubSel && !hubSel.disabled) hubSel.addEventListener('change', filterDistricts);
    if (purposeSel && purposeOther) {
        purposeSel.addEventListener('change', function () {
            purposeOther.hidden = purposeSel.value !== 'other';
        });
    }
    if (departmentSel && departmentOther) {
        departmentSel.addEventListener('change', function () {
            departmentOther.hidden = departmentSel.value !== 'Other';
        });
    }
    syncLevel();
})();
</script>
@endpush
