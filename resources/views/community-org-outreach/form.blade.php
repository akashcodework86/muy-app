@extends('layouts.admin')

@section('title', 'Log community organization outreach')
@section('heading', 'Community organization outreach (MIS 1.5)')

@push('styles')
<style>
    .coo-shell { display:flex; flex-direction:column; gap:1.25rem; }
    .coo-alert { border-radius:12px; padding:0.85rem 1rem; font-size:0.88rem; }
    .coo-alert--warning { background:#fffbeb; border:1px solid #fde68a; color:#92400e; }
    .coo-alert--success { background:#f0fdf4; border:1px solid #86efac; color:#166534; }
    .coo-alert--error { background:#fef2f2; border:1px solid #fca5a5; color:#991b1b; }
    .coo-alert--error ul { margin:0.35rem 0 0 1rem; }
    .coo-card { background:#fff; border:1px solid #e2e8f0; border-radius:14px; padding:1.25rem 1.35rem; }
    .coo-card__title { margin:0 0 0.35rem; font-size:1rem; font-weight:700; color:#0f172a; }
    .coo-card__sub { margin:0 0 1rem; font-size:0.82rem; color:#64748b; line-height:1.45; }
    .coo-grid { display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:0.85rem 1rem; }
    .coo-field { display:flex; flex-direction:column; gap:0.35rem; margin-bottom:0.85rem; }
    .coo-field--full { grid-column:1 / -1; }
    .coo-field label { font-size:0.82rem; font-weight:700; color:#0f172a; }
    .coo-field input, .coo-field select, .coo-field textarea {
        width:100%; box-sizing:border-box; border:1px solid #cbd5e1; border-radius:8px;
        padding:0.58rem 0.7rem; font-size:0.88rem;
    }
    .coo-field textarea { min-height:4.5rem; resize:vertical; }
    .coo-hint { margin:0; color:#64748b; font-size:0.76rem; line-height:1.4; }
    .coo-actions { display:flex; flex-wrap:wrap; gap:0.65rem; align-items:center; margin-top:0.5rem; }
    .coo-submit { border:none; border-radius:8px; background:#0d9488; color:#fff; padding:0.62rem 1rem; font-weight:700; cursor:pointer; font-size:0.88rem; }
    .coo-link { color:#0d9488; font-weight:700; text-decoration:none; font-size:0.88rem; }
    .coo-other-wrap { display:none; }
    .coo-other-wrap.is-visible { display:flex; }
    .coo-photo-preview { display:grid; grid-template-columns:repeat(auto-fill, minmax(88px, 1fr)); gap:0.55rem; margin-top:0.45rem; }
    .coo-photo-preview:empty { display:none; }
    .coo-photo-preview__item { position:relative; border-radius:10px; overflow:hidden; border:1px solid #e2e8f0; background:#f8fafc; aspect-ratio:1; }
    .coo-photo-preview__item img { display:block; width:100%; height:100%; object-fit:cover; }
    .coo-photo-preview__label { position:absolute; left:0; right:0; bottom:0; padding:0.2rem 0.35rem; background:rgba(15,23,42,0.72); color:#fff; font-size:0.65rem; font-weight:700; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
</style>
@endpush

@section('content')
<div class="coo-shell">
    @if (!empty($migrationMissing))
        <div class="coo-alert coo-alert--warning">
            <strong>Database update required.</strong> Run <code>php artisan migrate</code> for the community organization outreach table.
        </div>
    @endif

    @if (session('status'))
        <div class="coo-alert coo-alert--success">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="coo-alert coo-alert--error">
            <strong>Please fix:</strong>
            <ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="coo-card">
        <h3 class="coo-card__title">{{ !empty($isEdit) ? 'Edit outreach visit' : 'New outreach visit' }}</h3>
        <p class="coo-card__sub">
            Log one visit per meeting with a community organization, institute, or partner body under <strong>{{ $hub->name }}</strong>.
            @if (empty($isEdit))
            Each approved entry counts as one achievement toward MIS indicator 1.5.
            @endif
        </p>

        <form method="post" action="{{ route($storeRoute, $storeRouteParams ?? []) }}" enctype="multipart/form-data">
            @csrf
            @if (!empty($isEdit))
                @method('PUT')
            @endif
            <div class="coo-grid">
                <div class="coo-field">
                    <label for="visit_date">Date of visit <span style="color:#b91c1c;">*</span></label>
                    <x-activity-date-input name="visit_date" id="visit_date" :value="optional($row)->visit_date?->toDateString()" />
                </div>
                <div class="coo-field">
                    <label for="district_id">District <span style="color:#b91c1c;">*</span></label>
                    @if (!empty($districtLocked))
                        <input type="hidden" name="district_id" value="{{ $districts->first()->id }}">
                        <input type="text" id="district_id" value="{{ $districts->first()->name }}" readonly style="background:#f8fafc;color:#475569;">
                    @else
                    <select id="district_id" name="district_id" required>
                        <option value="">Select district</option>
                        @foreach ($districts as $d)
                            <option value="{{ $d->id }}" @selected((string) old('district_id', optional($row)->district_id ?? '') === (string) $d->id)>{{ $d->name }}</option>
                        @endforeach
                    </select>
                    @endif
                </div>
                <div class="coo-field coo-field--full">
                    <label for="organization_name">Name of organisation / institute <span style="color:#b91c1c;">*</span></label>
                    <input type="text" id="organization_name" name="organization_name" value="{{ old('organization_name', optional($row)->organization_name ?? '') }}" maxlength="255" required>
                </div>
                <div class="coo-field">
                    <label for="organization_type">Organisation type <span style="color:#b91c1c;">*</span></label>
                    <select id="organization_type" name="organization_type" required>
                        <option value="">Select</option>
                        @foreach ($organizationTypes as $value => $label)
                            <option value="{{ $value }}" @selected(old('organization_type') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="coo-field coo-other-wrap @if(old('organization_type') === 'other') is-visible @endif" id="organizationTypeOtherWrap">
                    <label for="organization_type_other">Specify other organisation type <span style="color:#b91c1c;">*</span></label>
                    <input type="text" id="organization_type_other" name="organization_type_other" value="{{ old('organization_type_other') }}" maxlength="191">
                </div>
                <div class="coo-field">
                    <label for="purpose">Purpose of visit <span style="color:#b91c1c;">*</span></label>
                    <select id="purpose" name="purpose" required>
                        <option value="">Select</option>
                        @foreach ($purposes as $value => $label)
                            <option value="{{ $value }}" @selected(old('purpose') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="coo-field">
                    <label for="meeting_mode">Meeting mode</label>
                    <select id="meeting_mode" name="meeting_mode">
                        @foreach ($meetingModes as $value => $label)
                            <option value="{{ $value }}" @selected(old('meeting_mode', 'physical') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="coo-field">
                    <label for="person_met_name">Person met <span style="color:#b91c1c;">*</span></label>
                    <input type="text" id="person_met_name" name="person_met_name" value="{{ old('person_met_name') }}" maxlength="191" required>
                </div>
                <div class="coo-field">
                    <label for="person_met_designation">Designation</label>
                    <input type="text" id="person_met_designation" name="person_met_designation" value="{{ old('person_met_designation') }}" maxlength="191">
                </div>
                <div class="coo-field">
                    <label for="poc_name">POC (point of contact) <span style="color:#b91c1c;">*</span></label>
                    <input type="text" id="poc_name" name="poc_name" value="{{ old('poc_name') }}" maxlength="191" required>
                </div>
                <div class="coo-field">
                    <label for="poc_phone">POC contact no. <span style="color:#b91c1c;">*</span></label>
                    <input type="text" id="poc_phone" name="poc_phone" value="{{ old('poc_phone') }}" maxlength="10" inputmode="numeric" pattern="[6-9][0-9]{9}" required>
                    <p class="coo-hint">10-digit Indian mobile number.</p>
                </div>
                <div class="coo-field">
                    <label for="poc_email">POC email</label>
                    <input type="email" id="poc_email" name="poc_email" value="{{ old('poc_email') }}" maxlength="191">
                </div>
                <div class="coo-field coo-field--full">
                    <label for="remarks">Remark</label>
                    <textarea id="remarks" name="remarks" maxlength="5000">{{ old('remarks') }}</textarea>
                </div>
                <div class="coo-field coo-field--full">
                    <label for="documents">Documents (optional)</label>
                    <input type="file" id="documents" name="documents[]" multiple accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.webp,application/pdf">
                    <p class="coo-hint">PDF, Word, Excel, or image files. Up to 10 files, 10 MB each.</p>
                </div>
                <div class="coo-field coo-field--full">
                    <label for="photos">Photos (optional)</label>
                    <input type="file" id="photos" name="photos[]" multiple accept="image/*,.heic,.heif">
                    <p class="coo-hint">Upload one or more visit photos. Up to 25 images, 10 MB each.</p>
                    <div class="coo-photo-preview" id="photoPreviewGrid" aria-live="polite"></div>
                </div>
            </div>

            <div class="coo-actions">
                <button type="submit" class="coo-submit">Save visit</button>
                <a href="{{ route($dashboardRoute) }}" class="coo-link">View dashboard</a>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
    (function () {
        const typeSelect = document.getElementById('organization_type');
        const otherWrap = document.getElementById('organizationTypeOtherWrap');
        const otherInput = document.getElementById('organization_type_other');
        if (!typeSelect || !otherWrap || !otherInput) return;

        function syncOtherField() {
            const isOther = typeSelect.value === 'other';
            otherWrap.classList.toggle('is-visible', isOther);
            otherInput.required = isOther;
            if (!isOther) {
                otherInput.value = '';
            }
        }

        typeSelect.addEventListener('change', syncOtherField);
        syncOtherField();
    })();

    (function () {
        const input = document.getElementById('photos');
        const grid = document.getElementById('photoPreviewGrid');
        if (!input || !grid) return;

        let objectUrls = [];

        function clearPreviews() {
            objectUrls.forEach(function (url) { URL.revokeObjectURL(url); });
            objectUrls = [];
            grid.innerHTML = '';
        }

        input.addEventListener('change', function () {
            clearPreviews();
            const files = Array.from(input.files || []);
            files.forEach(function (file, index) {
                if (!file.type.startsWith('image/')) {
                    return;
                }
                const url = URL.createObjectURL(file);
                objectUrls.push(url);

                const item = document.createElement('div');
                item.className = 'coo-photo-preview__item';

                const img = document.createElement('img');
                img.src = url;
                img.alt = file.name || ('Photo ' + (index + 1));

                const label = document.createElement('span');
                label.className = 'coo-photo-preview__label';
                label.textContent = file.name || ('Photo ' + (index + 1));

                item.appendChild(img);
                item.appendChild(label);
                grid.appendChild(item);
            });
        });
    })();
</script>
@endpush
