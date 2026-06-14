@extends('layouts.admin')

@section('title', \App\Models\FundingSchematicPartnerOutreachEntry::MODULE_LABEL)
@section('heading', \App\Models\FundingSchematicPartnerOutreachEntry::MODULE_LABEL)

@push('styles')
<style>
    .fspoe-shell { display:flex; flex-direction:column; gap:1.25rem; }
    .fspoe-alert { border-radius:12px; padding:0.85rem 1rem; font-size:0.88rem; }
    .fspoe-alert--info { background:#eff6ff; border:1px solid #bfdbfe; color:#1e40af; }
    .fspoe-alert--warning { background:#fffbeb; border:1px solid #fde68a; color:#92400e; }
    .fspoe-alert--success { background:#f0fdf4; border:1px solid #86efac; color:#166534; }
    .fspoe-alert--error { background:#fef2f2; border:1px solid #fca5a5; color:#991b1b; }
    .fspoe-alert--error ul { margin:0.35rem 0 0 1rem; }
    .fspoe-card { background:#fff; border:1px solid #e2e8f0; border-radius:14px; padding:1.25rem; }
    .fspoe-grid { display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:0.85rem; }
    .fspoe-field { display:flex; flex-direction:column; gap:0.35rem; margin-bottom:0.85rem; }
    .fspoe-field label { font-size:0.82rem; font-weight:700; }
    .fspoe-field input, .fspoe-field select, .fspoe-field textarea { width:100%; box-sizing:border-box; border:1px solid #cbd5e1; border-radius:8px; padding:0.58rem 0.7rem; font-size:0.88rem; }
    .fspoe-readonly { background:#f8fafc; color:#64748b; }
    .fspoe-section { margin-top:1rem; padding-top:1rem; border-top:1px solid #e2e8f0; }
    .fspoe-partner-row { border:1px solid #e2e8f0; border-radius:10px; padding:0.85rem; background:#f8fafc; margin-bottom:0.65rem; }
    .fspoe-add { background:#f5f3ff; color:#6d28d9; border:1px solid #ddd6fe; padding:0.45rem 0.85rem; border-radius:8px; font-weight:700; cursor:pointer; }
    .fspoe-submit { border:none; border-radius:8px; background:#7c3aed; color:#fff; padding:0.62rem 1rem; font-weight:700; cursor:pointer; }
    .fspoe-link { color:#7c3aed; font-weight:700; text-decoration:none; }
    .fspoe-other-wrap { display:none; }
    .fspoe-other-wrap.is-visible { display:flex; }
    .fspoe-req { color:#e11d48; }
    .fspoe-remove { background:#fff; border:1px solid #fecaca; color:#b91c1c; padding:0.25rem 0.55rem; border-radius:6px; cursor:pointer; font-size:0.78rem; }
</style>
@endpush

@section('content')
@php
    $oldPartners = old('partners');
    if (! is_array($oldPartners) || $oldPartners === []) {
        $oldPartners = [['partner_name' => '', 'partner_type' => '', 'partner_type_other' => '', 'contact_name' => '', 'designation' => '', 'poc_phone' => '', 'partner_link' => '', 'remarks' => '']];
    }
@endphp
<div class="fspoe-shell">
    <div class="fspoe-alert fspoe-alert--info">
        MIS <strong>8.5</strong> — Partners outreach (Funding &amp; Schematic Convergence). Unique partner names count toward deliverable achievement.
    </div>
    @if (!empty($migrationMissing))<div class="fspoe-alert fspoe-alert--warning">Run <code>php artisan migrate</code>.</div>@endif
    @if (session('status'))<div class="fspoe-alert fspoe-alert--success">{{ session('status') }}</div>@endif
    @if ($errors->any())<div class="fspoe-alert fspoe-alert--error"><ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

    <div class="fspoe-card">
        <h3 style="margin:0 0 1rem;">New partner outreach</h3>
        <form method="post" action="{{ route($storeRoute) }}">
            @csrf
            <div class="fspoe-grid">
                <div class="fspoe-field"><label>Entered by</label><input type="text" class="fspoe-readonly" value="{{ $user->name }}" readonly></div>
                <div class="fspoe-field"><label>Outreach date <span class="fspoe-req">*</span></label><input type="date" name="outreach_date" value="{{ old('outreach_date', now()->toDateString()) }}" required></div>
                <div class="fspoe-field"><label>Outreach mode <span class="fspoe-req">*</span></label>
                    <select name="outreach_mode" required><option value="">— Select —</option>
                        @foreach ($outreachModes as $v => $l)<option value="{{ $v }}" @selected(old('outreach_mode') === $v)>{{ $l }}</option>@endforeach
                    </select>
                </div>
            </div>
            <div class="fspoe-section">
                <p style="margin:0 0 0.65rem;font-size:0.72rem;font-weight:700;text-transform:uppercase;color:#64748b;">Partners</p>
                <div id="partners_container"></div>
                <button type="button" class="fspoe-add" id="add_partner_btn">+ Add partner</button>
            </div>
            <div style="margin-top:0.75rem;display:flex;gap:0.65rem;">
                <button type="submit" class="fspoe-submit">Save outreach</button>
                <a href="{{ route($dashboardRoute) }}" class="fspoe-link">View dashboard</a>
            </div>
        </form>
    </div>
</div>

<template id="partner_row_template">
    <div class="fspoe-partner-row">
        <div style="display:flex;justify-content:space-between;margin-bottom:0.55rem;"><span class="partner-title" style="font-weight:700;color:#6d28d9;">Partner</span><button type="button" class="fspoe-remove" style="display:none;">Remove</button></div>
        <div class="fspoe-grid">
            <div class="fspoe-field"><label>Partner organization <span class="fspoe-req">*</span></label><input type="text" data-field="partner_name" maxlength="255" required></div>
            <div class="fspoe-field"><label>Partner type <span class="fspoe-req">*</span></label>
                <select data-field="partner_type" required><option value="">— Select —</option>@foreach ($partnerTypes as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach</select>
            </div>
            <div class="fspoe-field fspoe-other-wrap" data-other-wrap><label>Specify other <span class="fspoe-req">*</span></label><input type="text" data-field="partner_type_other" maxlength="191"></div>
            <div class="fspoe-field"><label>Contact person</label><input type="text" data-field="contact_name" maxlength="191"></div>
            <div class="fspoe-field"><label>Designation</label><input type="text" data-field="designation" maxlength="191"></div>
            <div class="fspoe-field"><label>Phone <span class="fspoe-req">*</span></label><input type="tel" data-field="poc_phone" maxlength="10" pattern="[6-9][0-9]{9}" required></div>
            <div class="fspoe-field"><label>Email / link</label><input type="text" data-field="partner_link" maxlength="2048"></div>
            <div class="fspoe-field" style="grid-column:1/-1;"><label>Remarks</label><textarea data-field="remarks" maxlength="5000" rows="2"></textarea></div>
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
    const fields = ['partner_name','partner_type','partner_type_other','contact_name','designation','poc_phone','partner_link','remarks'];

    function bindOther(row) {
        const sel = row.querySelector('[data-field="partner_type"]');
        const wrap = row.querySelector('[data-other-wrap]');
        if (!sel || !wrap) return;
        function sync() { const show = sel.value === 'other'; wrap.classList.toggle('is-visible', show); wrap.style.display = show ? 'flex' : 'none'; }
        sel.addEventListener('change', sync); sync();
    }

    function addRow(data, index) {
        const clone = template.content.cloneNode(true);
        const row = clone.querySelector('.fspoe-partner-row');
        row.querySelector('.partner-title').textContent = 'Partner ' + (index + 1);
        fields.forEach(function (f) {
            const el = row.querySelector('[data-field="'+f+'"]');
            if (el) { el.name = 'partners['+index+']['+f+']'; if (data && data[f] != null) el.value = data[f]; }
        });
        row.querySelector('.fspoe-remove').addEventListener('click', function () { row.remove(); renumber(); updateRemove(); });
        bindOther(row);
        container.appendChild(row);
    }

    function renumber() {
        container.querySelectorAll('.fspoe-partner-row').forEach(function (row, i) {
            row.querySelector('.partner-title').textContent = 'Partner ' + (i + 1);
            fields.forEach(function (f) {
                const el = row.querySelector('[data-field="'+f+'"]');
                if (el) el.name = 'partners['+i+']['+f+']';
            });
        });
    }

    function updateRemove() {
        const rows = container.querySelectorAll('.fspoe-partner-row');
        rows.forEach(function (row) {
            const btn = row.querySelector('.fspoe-remove');
            if (btn) btn.style.display = rows.length > 1 ? 'inline-block' : 'none';
        });
    }

    oldPartners.forEach(function (p, i) { addRow(p, i); });
    if (!oldPartners.length) addRow({}, 0);
    updateRemove();
    addBtn.addEventListener('click', function () { addRow({}, container.querySelectorAll('.fspoe-partner-row').length); updateRemove(); });
})();
</script>
@endpush
