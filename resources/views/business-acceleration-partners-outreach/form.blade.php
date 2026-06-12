@extends('layouts.admin')

@section('title', \App\Models\BusinessAccelerationPartnerOutreachEntry::MODULE_LABEL)
@section('heading', \App\Models\BusinessAccelerationPartnerOutreachEntry::MODULE_LABEL)

@push('styles')
<style>
    .bapo-shell { display:flex; flex-direction:column; gap:1.25rem; }
    .bapo-alert { border-radius:12px; padding:0.85rem 1rem; font-size:0.88rem; }
    .bapo-alert--warning { background:#fffbeb; border:1px solid #fde68a; color:#92400e; }
    .bapo-alert--success { background:#f0fdf4; border:1px solid #86efac; color:#166534; }
    .bapo-alert--error { background:#fef2f2; border:1px solid #fca5a5; color:#991b1b; }
    .bapo-alert--error ul { margin:0.35rem 0 0 1rem; }
    .bapo-alert--info { background:#eff6ff; border:1px solid #bfdbfe; color:#1e40af; }
    .bapo-card { background:#fff; border:1px solid #e2e8f0; border-radius:14px; padding:1.25rem 1.35rem; }
    .bapo-card__title { margin:0 0 0.35rem; font-size:1rem; font-weight:700; color:#0f172a; }
    .bapo-card__sub { margin:0 0 1rem; font-size:0.82rem; color:#64748b; line-height:1.45; }
    .bapo-grid { display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:0.85rem 1rem; }
    .bapo-field { display:flex; flex-direction:column; gap:0.35rem; margin-bottom:0.85rem; }
    .bapo-field label { font-size:0.82rem; font-weight:700; color:#0f172a; }
    .bapo-field input, .bapo-field select, .bapo-field textarea {
        width:100%; box-sizing:border-box; border:1px solid #cbd5e1; border-radius:8px;
        padding:0.58rem 0.7rem; font-size:0.88rem;
    }
    .bapo-field textarea { min-height:3.5rem; resize:vertical; }
    .bapo-readonly { background:#f8fafc; color:#64748b; }
    .bapo-section { margin-top:1.15rem; padding-top:1rem; border-top:1px solid #e2e8f0; }
    .bapo-section__label { margin:0 0 0.65rem; font-size:0.72rem; font-weight:700; letter-spacing:0.08em; text-transform:uppercase; color:#64748b; }
    .bapo-partner-row { border:1px solid #e2e8f0; border-radius:10px; padding:0.85rem; background:#f8fafc; margin-bottom:0.65rem; }
    .bapo-partner-row__head { display:flex; justify-content:space-between; align-items:center; margin-bottom:0.55rem; }
    .bapo-partner-row__title { font-size:0.85rem; font-weight:700; color:#0f766e; }
    .bapo-remove { background:#fff; border:1px solid #fecaca; color:#b91c1c; padding:0.25rem 0.55rem; border-radius:6px; font-size:0.78rem; font-weight:600; cursor:pointer; }
    .bapo-add { margin-top:0.35rem; background:#ecfdf5; color:#047857; border:1px solid #6ee7b7; padding:0.45rem 0.85rem; border-radius:8px; font-weight:700; cursor:pointer; font-size:0.85rem; }
    .bapo-actions { display:flex; flex-wrap:wrap; gap:0.65rem; align-items:center; margin-top:0.75rem; }
    .bapo-submit { border:none; border-radius:8px; background:#0f766e; color:#fff; padding:0.62rem 1rem; font-weight:700; cursor:pointer; font-size:0.88rem; }
    .bapo-link { color:#0f766e; font-weight:700; text-decoration:none; font-size:0.88rem; }
    .bapo-other-wrap { display:none; }
    .bapo-other-wrap.is-visible { display:flex; }
    .bapo-req { color:#e11d48; }
</style>
@endpush

@section('content')
@php
    $oldPartners = old('partners');
    if (! is_array($oldPartners) || $oldPartners === []) {
        $oldPartners = [['partner_name' => '', 'partner_type' => '', 'partner_type_other' => '', 'poc_name' => '', 'poc_phone' => '', 'remarks' => '']];
    }
@endphp
<div class="bapo-shell">
    @if (!empty($migrationMissing))
        <div class="bapo-alert bapo-alert--warning">
            <strong>Database update required.</strong> Run <code>php artisan migrate</code> first.
        </div>
    @endif

    @if (session('status'))
        <div class="bapo-alert bapo-alert--success">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="bapo-alert bapo-alert--error">
            <strong>Please fix:</strong>
            <ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="bapo-alert bapo-alert--info">
        MIS <strong>7.1</strong> — No of Partners outreach (Business Acceleration Services). Add one or more partners per save.
        Deliverable counts <strong>unique partner names</strong> in the fiscal year.
    </div>

    <div class="bapo-card">
        <h3 class="bapo-card__title">New partner outreach</h3>
        <p class="bapo-card__sub">State-level outreach log for acceleration / co-incubation partners.</p>

        <form method="post" action="{{ route($storeRoute) }}" id="bapoForm">
            @csrf
            <p class="bapo-section__label" style="margin-top:0; padding-top:0; border:none;">Session</p>
            <div class="bapo-grid">
                <div class="bapo-field">
                    <label>1. Entered by</label>
                    <input type="text" class="bapo-readonly" value="{{ $user->name }}" readonly>
                </div>
                <div class="bapo-field">
                    <label for="outreach_date">2. Outreach date <span class="bapo-req">*</span></label>
                    <input type="date" id="outreach_date" name="outreach_date" value="{{ old('outreach_date', now()->toDateString()) }}" required>
                </div>
                <div class="bapo-field">
                    <label for="outreach_mode">3. Outreach mode <span class="bapo-req">*</span></label>
                    <select id="outreach_mode" name="outreach_mode" required>
                        <option value="">— Select —</option>
                        @foreach ($outreachModes as $value => $label)
                            <option value="{{ $value }}" @selected(old('outreach_mode') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="bapo-section">
                <p class="bapo-section__label">Partner list</p>
                <div id="partners_container"></div>
                <button type="button" class="bapo-add" id="add_partner_btn">+ Add partner</button>
            </div>

            <div class="bapo-actions">
                <button type="submit" class="bapo-submit">Save outreach</button>
                <a href="{{ route($dashboardRoute) }}" class="bapo-link">View dashboard</a>
            </div>
        </form>
    </div>
</div>

<template id="partner_row_template">
    <div class="bapo-partner-row">
        <div class="bapo-partner-row__head">
            <span class="bapo-partner-row__title">Partner</span>
            <button type="button" class="bapo-remove" style="display:none;">Remove</button>
        </div>
        <div class="bapo-grid">
            <div class="bapo-field">
                <label>4. Partner name <span class="bapo-req">*</span></label>
                <input type="text" data-field="partner_name" maxlength="255" required placeholder="Organisation / company">
            </div>
            <div class="bapo-field">
                <label>5. Partner type <span class="bapo-req">*</span></label>
                <select data-field="partner_type" required>
                    <option value="">— Select —</option>
                    @foreach ($partnerTypes as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="bapo-field bapo-other-wrap" data-other-wrap>
                <label>Specify type <span class="bapo-req">*</span></label>
                <input type="text" data-field="partner_type_other" maxlength="191" placeholder="When Other is selected">
            </div>
            <div class="bapo-field">
                <label>6. POC name <span class="bapo-req">*</span></label>
                <input type="text" data-field="poc_name" maxlength="191" required>
            </div>
            <div class="bapo-field">
                <label>7. POC phone</label>
                <input type="tel" data-field="poc_phone" maxlength="10" pattern="[6-9][0-9]{9}" placeholder="10-digit mobile">
            </div>
            <div class="bapo-field" style="grid-column:1 / -1;">
                <label>8. Remarks</label>
                <textarea data-field="remarks" maxlength="5000" rows="2"></textarea>
            </div>
        </div>
    </div>
</template>
@endsection

@push('scripts')
<script>
(function () {
    const container = document.getElementById('partners_container');
    const template = document.getElementById('partner_row_template');
    const addBtn = document.getElementById('add_partner_btn');
    const oldPartners = @json($oldPartners);

    function esc(s) {
        const d = document.createElement('div');
        d.textContent = s == null ? '' : String(s);
        return d.innerHTML;
    }

    function bindOtherToggle(row) {
        const select = row.querySelector('[data-field="partner_type"]');
        const wrap = row.querySelector('[data-other-wrap]');
        if (!select || !wrap) return;
        function sync() {
            const show = select.value === 'other';
            wrap.classList.toggle('is-visible', show);
            wrap.style.display = show ? 'flex' : 'none';
        }
        select.addEventListener('change', sync);
        sync();
    }

    function addRow(data, index) {
        const clone = template.content.cloneNode(true);
        const row = clone.querySelector('.bapo-partner-row');
        row.querySelector('.bapo-partner-row__title').textContent = 'Partner ' + (index + 1);

        const fields = ['partner_name', 'partner_type', 'partner_type_other', 'poc_name', 'poc_phone', 'remarks'];
        fields.forEach(function (field) {
            const el = row.querySelector('[data-field="' + field + '"]');
            if (!el) return;
            el.name = 'partners[' + index + '][' + field + ']';
            if (data && data[field] != null) el.value = data[field];
        });

        const removeBtn = row.querySelector('.bapo-remove');
        removeBtn.addEventListener('click', function () {
            row.remove();
            renumber();
            updateRemoveButtons();
        });

        bindOtherToggle(row);
        container.appendChild(row);
    }

    function renumber() {
        container.querySelectorAll('.bapo-partner-row').forEach(function (row, i) {
            row.querySelector('.bapo-partner-row__title').textContent = 'Partner ' + (i + 1);
            row.querySelectorAll('[data-field]').forEach(function (el) {
                const field = el.getAttribute('data-field');
                el.name = 'partners[' + i + '][' + field + ']';
            });
        });
    }

    function updateRemoveButtons() {
        const rows = container.querySelectorAll('.bapo-partner-row');
        rows.forEach(function (row) {
            const btn = row.querySelector('.bapo-remove');
            if (btn) btn.style.display = rows.length > 1 ? 'inline-block' : 'none';
        });
    }

    oldPartners.forEach(function (p, i) { addRow(p, i); });
    if (oldPartners.length === 0) addRow({}, 0);
    updateRemoveButtons();

    addBtn.addEventListener('click', function () {
        addRow({}, container.querySelectorAll('.bapo-partner-row').length);
        updateRemoveButtons();
    });
})();
</script>
@endpush
