@extends('layouts.admin')

@section('title', 'Submit Field Visit Report')
@section('heading', 'Submit Field Visit Report')

@push('styles')
<style>
    :root {
        --fr-indigo: #4f46e5;
        --fr-teal:   #0d9488;
        --fr-text:   #0f172a;
        --fr-muted:  #64748b;
        --fr-border: #cbd5e1;
        --fr-bg:     #f8fafc;
    }
    .fr-shell {
        max-width: 680px;
        margin: 0 auto;
        padding-bottom: 3rem;
        font-family: 'DM Sans', sans-serif;
    }
    .fr-card {
        background: #fff;
        border: 1px solid rgba(226,232,240,.9);
        border-radius: 20px;
        box-shadow: 0 6px 24px rgba(15,23,42,.06);
        padding: 2rem;
    }
    .fr-card-title {
        font-size: 1.05rem;
        font-weight: 800;
        color: var(--fr-text);
        margin: 0 0 1.5rem;
        padding-bottom: .85rem;
        border-bottom: 1px solid #f1f5f9;
        display: flex;
        align-items: center;
        gap: .5rem;
    }
    .fr-card-title svg { color: var(--fr-indigo); flex-shrink: 0; }
    .fr-section-label {
        font-size: .7rem;
        text-transform: uppercase;
        letter-spacing: .09em;
        font-weight: 700;
        color: var(--fr-muted);
        margin: 1.25rem 0 .75rem;
    }
    .fr-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: .85rem; }
    .fr-grid-1 { display: grid; grid-template-columns: 1fr; gap: .85rem; }
    @media (max-width: 520px) { .fr-grid-2 { grid-template-columns: 1fr; } }
    .fr-field { display: flex; flex-direction: column; gap: .3rem; }
    .fr-label { font-size: .78rem; font-weight: 600; color: var(--fr-text); }
    .fr-sublabel { font-size: .7rem; color: var(--fr-muted); margin-top: .1rem; }
    .fr-input, .fr-select, .fr-textarea {
        border: 1px solid var(--fr-border);
        border-radius: 9px;
        padding: .5rem .8rem;
        font-size: .88rem;
        font-family: inherit;
        color: var(--fr-text);
        background: #fff;
        outline: none;
        transition: border-color .15s, box-shadow .15s;
        width: 100%;
        box-sizing: border-box;
    }
    .fr-input:focus, .fr-select:focus, .fr-textarea:focus {
        border-color: var(--fr-indigo);
        box-shadow: 0 0 0 3px rgba(79,70,229,.1);
    }
    .fr-input[readonly] { background: var(--fr-bg); color: var(--fr-muted); cursor: default; }
    .fr-select {
        appearance: none;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='none' viewBox='0 0 24 24'%3E%3Cpath stroke='%2364748b' stroke-width='1.8' stroke-linecap='round' stroke-linejoin='round' d='M6 9l6 6 6-6'/%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right .6rem center;
        padding-right: 2.2rem;
        cursor: pointer;
    }
    .fr-textarea { resize: vertical; min-height: 80px; }
    .fr-optional { font-size: .68rem; font-weight: 500; color: var(--fr-muted); background: #f1f5f9; border-radius: 4px; padding: .1rem .35rem; margin-left: .3rem; }
    .fr-error { font-size: .75rem; color: #dc2626; margin-top: .2rem; }

    /* file upload */
    .fr-file-area {
        border: 2px dashed var(--fr-border);
        border-radius: 10px;
        padding: 1.25rem;
        text-align: center;
        cursor: pointer;
        transition: border-color .15s, background .15s;
    }
    .fr-file-area:hover { border-color: var(--fr-indigo); background: rgba(79,70,229,.03); }
    .fr-file-area input[type=file] { display: none; }
    .fr-file-area__icon { color: var(--fr-muted); margin-bottom: .4rem; }
    .fr-file-area__text { font-size: .82rem; color: var(--fr-muted); }
    .fr-file-area__text strong { color: var(--fr-indigo); }
    .fr-file-area__hint { font-size: .7rem; color: #94a3b8; margin-top: .25rem; }
    .fr-file-name { margin-top: .5rem; font-size: .78rem; font-weight: 600; color: var(--fr-teal); }

    /* divider */
    .fr-divider { border: none; border-top: 1px solid #f1f5f9; margin: 1.25rem 0; }

    /* submit */
    .fr-actions { display: flex; align-items: center; gap: .75rem; margin-top: 1.5rem; }
    .fr-submit {
        flex: 1;
        background: var(--fr-indigo);
        color: #fff;
        border: none;
        border-radius: 10px;
        padding: .7rem 1.5rem;
        font-size: .95rem;
        font-weight: 700;
        font-family: inherit;
        cursor: pointer;
        transition: background .15s;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: .5rem;
    }
    .fr-submit:hover { background: #4338ca; }
    .fr-back-link { font-size: .82rem; color: var(--fr-muted); text-decoration: none; }
    .fr-back-link:hover { color: var(--fr-text); }
</style>
@endpush

@section('content')
<div class="fr-shell">
    <div class="fr-card">

        <h2 class="fr-card-title">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 21s-7-7.5-7-12a7 7 0 0 1 14 0c0 4.5-7 12-7 12Z"/><circle cx="12" cy="9" r="2.5"/>
            </svg>
            Field Coordinator Visit Report
        </h2>

        <form method="POST" action="{{ route('staff.field-reports.store') }}" enctype="multipart/form-data" id="frForm">
            @csrf

            {{-- ── Coordinator & Date ── --}}
            <div class="fr-section-label">Coordinator &amp; Visit Date</div>
            <div class="fr-grid-2">
                <div class="fr-field">
                    <label class="fr-label">Name of Field Coordinator</label>
                    <input type="text" class="fr-input" value="{{ $user->name }}" readonly>
                </div>
                <div class="fr-field">
                    <label class="fr-label" for="visit_date">Date of Visit</label>
                    <input
                        id="visit_date"
                        type="date"
                        name="visit_date"
                        class="fr-input @error('visit_date') border-red-400 @enderror"
                        value="{{ old('visit_date', now()->toDateString()) }}"
                        max="{{ now()->toDateString() }}"
                        required
                    >
                    @error('visit_date')<p class="fr-error">{{ $message }}</p>@enderror
                </div>
            </div>

            <hr class="fr-divider">

            {{-- ── Location ── --}}
            <div class="fr-section-label">Location</div>
            <div class="fr-grid-2">
                <div class="fr-field">
                    <label class="fr-label" for="district_id">District</label>
                    <select
                        id="district_id"
                        name="district_id"
                        class="fr-select @error('district_id') border-red-400 @enderror"
                        required
                        onchange="loadBlocks(this.value)"
                    >
                        <option value="">Select district…</option>
                        @foreach ($districts as $d)
                            <option value="{{ $d->id }}" @selected(old('district_id', $user->district_id) == $d->id)>{{ $d->name }}</option>
                        @endforeach
                    </select>
                    @error('district_id')<p class="fr-error">{{ $message }}</p>@enderror
                </div>

                <div class="fr-field">
                    <label class="fr-label" for="block_id">Block <span class="fr-optional">optional</span></label>
                    <select id="block_id" name="block_id" class="fr-select">
                        <option value="">Select block…</option>
                        @foreach ($blocks as $b)
                            <option value="{{ $b->id }}" @selected(old('block_id') == $b->id)>{{ $b->name }}</option>
                        @endforeach
                    </select>
                    @error('block_id')<p class="fr-error">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="fr-grid-1">
                <div class="fr-field">
                    <label class="fr-label" for="area">Area / Panchayat <span class="fr-optional">optional</span></label>
                    <input
                        id="area"
                        type="text"
                        name="area"
                        class="fr-input"
                        value="{{ old('area') }}"
                        placeholder="e.g. Sadar Panchayat, Ward 4…"
                        maxlength="191"
                    >
                </div>
            </div>

            <hr class="fr-divider">

            {{-- ── Villages ── --}}
            <div class="fr-section-label">Villages</div>
            <div class="fr-grid-2">
                <div class="fr-field">
                    <label class="fr-label" for="total_villages">Total Number of Villages Visited</label>
                    <input
                        id="total_villages"
                        type="number"
                        name="total_villages"
                        class="fr-input @error('total_villages') border-red-400 @enderror"
                        value="{{ old('total_villages', 0) }}"
                        min="0" max="500"
                        required
                    >
                    @error('total_villages')<p class="fr-error">{{ $message }}</p>@enderror
                </div>
                <div class="fr-field" style="grid-column:1/-1;">
                    <label class="fr-label" for="village_names">Names of Villages Covered <span class="fr-optional">optional</span></label>
                    <textarea
                        id="village_names"
                        name="village_names"
                        class="fr-textarea"
                        placeholder="e.g. Rampur, Sherpur, Baluahi…"
                        maxlength="2000"
                    >{{ old('village_names') }}</textarea>
                    <div class="fr-sublabel">Enter village names separated by commas or one per line.</div>
                </div>
            </div>

            <hr class="fr-divider">

            {{-- ── Activity Counts ── --}}
            <div class="fr-section-label">Activity Counts</div>
            <div class="fr-grid-2">
                <div class="fr-field">
                    <label class="fr-label" for="total_participants">Total Number of Participants</label>
                    <input
                        id="total_participants"
                        type="number"
                        name="total_participants"
                        class="fr-input @error('total_participants') border-red-400 @enderror"
                        value="{{ old('total_participants', 0) }}"
                        min="0" max="10000"
                        required
                    >
                    @error('total_participants')<p class="fr-error">{{ $message }}</p>@enderror
                </div>

                <div class="fr-field">
                    <label class="fr-label" for="outreach_programmes">Number of Outreach Programmes Conducted</label>
                    <input
                        id="outreach_programmes"
                        type="number"
                        name="outreach_programmes"
                        class="fr-input @error('outreach_programmes') border-red-400 @enderror"
                        value="{{ old('outreach_programmes', 0) }}"
                        min="0" max="500"
                        required
                    >
                    @error('outreach_programmes')<p class="fr-error">{{ $message }}</p>@enderror
                </div>

                <div class="fr-field">
                    <label class="fr-label" for="cfas_reported">Total Number of CFAs Filled</label>
                    <input
                        id="cfas_reported"
                        type="number"
                        name="cfas_reported"
                        class="fr-input @error('cfas_reported') border-red-400 @enderror"
                        value="{{ old('cfas_reported', 0) }}"
                        min="0" max="500"
                        required
                    >
                    <div class="fr-sublabel">System will auto-verify this against your referral records.</div>
                    @error('cfas_reported')<p class="fr-error">{{ $message }}</p>@enderror
                </div>
            </div>

            <hr class="fr-divider">

            {{-- ── Attachment ── --}}
            <div class="fr-section-label">Attachment <span class="fr-optional" style="text-transform:none; letter-spacing:0;">Optional</span></div>
            <label class="fr-file-area" for="attachment">
                <input type="file" id="attachment" name="attachment" accept=".jpg,.jpeg,.png,.pdf" onchange="showFileName(this)">
                <div class="fr-file-area__icon">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8l-5-5Z"/><path d="M14 3v5h5"/><path d="M12 11v6M9 14l3 3 3-3"/>
                    </svg>
                </div>
                <div class="fr-file-area__text"><strong>Click to upload</strong> or drag and drop</div>
                <div class="fr-file-area__hint">Workshop photo, attendance sheet — JPG, PNG or PDF · max 5 MB</div>
                <div class="fr-file-name" id="frFileName"></div>
            </label>
            @error('attachment')<p class="fr-error" style="margin-top:.35rem;">{{ $message }}</p>@enderror

            {{-- ── Actions ── --}}
            <div class="fr-actions">
                <button type="submit" class="fr-submit">
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                    Submit Report
                </button>
                <a href="{{ route('staff.field-reports.index') }}" class="fr-back-link">← Back to my reports</a>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
function showFileName(input) {
    const label = document.getElementById('frFileName');
    if (input.files && input.files[0]) {
        label.textContent = '📎 ' + input.files[0].name;
    } else {
        label.textContent = '';
    }
}

function loadBlocks(districtId, selectId) {
    const sel = document.getElementById('block_id');
    sel.innerHTML = '<option value="">Loading…</option>';
    if (!districtId) { sel.innerHTML = '<option value="">Select block…</option>'; return; }
    fetch('/api/field-reports/blocks?district_id=' + encodeURIComponent(districtId))
        .then(r => r.json())
        .then(blocks => {
            sel.innerHTML = '<option value="">Select block…</option>';
            blocks.forEach(function(b) {
                const opt = document.createElement('option');
                opt.value = b.id;
                opt.textContent = b.name;
                if (selectId && String(b.id) === String(selectId)) opt.selected = true;
                sel.appendChild(opt);
            });
        })
        .catch(function() {
            sel.innerHTML = '<option value="">Could not load blocks</option>';
        });
}

// If district is pre-selected on page load (old input or user's district), load blocks
(function() {
    const distSel = document.getElementById('district_id');
    if (distSel && distSel.value) {
        loadBlocks(distSel.value, "{{ old('block_id') }}");
    }
}());
</script>
@endpush
