@extends('layouts.admin')

@section('title', \App\Models\PitchDeckPreparation::MODULE_LABEL)
@section('heading', \App\Models\PitchDeckPreparation::MODULE_LABEL)

@push('styles')
<style>
    .pdp-shell { display:flex; flex-direction:column; gap:1.25rem; }
    .pdp-alert { border-radius:12px; padding:0.85rem 1rem; font-size:0.88rem; }
    .pdp-alert--warning { background:#fffbeb; border:1px solid #fde68a; color:#92400e; }
    .pdp-alert--success { background:#f0fdf4; border:1px solid #86efac; color:#166534; }
    .pdp-alert--error { background:#fef2f2; border:1px solid #fca5a5; color:#991b1b; }
    .pdp-alert--error ul { margin:0.35rem 0 0 1rem; }
    .pdp-card { background:#fff; border:1px solid #e2e8f0; border-radius:14px; padding:1.25rem 1.35rem; max-width:56rem; }
    .pdp-card__title { margin:0 0 1.15rem; font-size:1rem; font-weight:700; color:#0f172a; }
    .pdp-grid { display:grid; grid-template-columns:repeat(2, minmax(0, 1fr)); gap:1rem 1.1rem; align-items:start; }
    .pdp-field { display:flex; flex-direction:column; gap:0.4rem; min-width:0; }
    .pdp-field--full { grid-column:1 / -1; }
    .pdp-field label { font-size:0.82rem; font-weight:700; color:#0f172a; }
    .pdp-req { color:#b91c1c; }
    .pdp-field input[type="text"], .pdp-field input[type="date"], .pdp-field input[type="file"], .pdp-field select, .pdp-field textarea {
        width:100%; box-sizing:border-box; border:1px solid #cbd5e1; border-radius:8px; padding:0.58rem 0.7rem; font-size:0.88rem; background:#fff;
    }
    .pdp-field textarea { min-height:5rem; resize:vertical; }
    .pdp-readonly { background:#f8fafc; color:#64748b; }
    .pdp-hint { margin:0.2rem 0 0; color:#64748b; font-size:0.78rem; line-height:1.4; }
    .pdp-picker { display:grid; grid-template-columns:minmax(0,1fr) minmax(0,1fr); gap:0.85rem; margin-top:0.45rem; }
    .pdp-picker__col { min-width:0; border:1px solid #e2e8f0; border-radius:12px; background:#f8fafc; display:flex; flex-direction:column; }
    .pdp-picker__head {
        display:flex; align-items:center; justify-content:space-between; gap:0.5rem;
        padding:0.55rem 0.75rem; border-bottom:1px solid #e2e8f0; font-size:0.76rem; font-weight:800; color:#475569;
        text-transform:uppercase; letter-spacing:0.04em;
    }
    .pdp-picker__count {
        font-size:0.72rem; font-weight:800; color:#4f46e5; background:#eef2ff; border-radius:999px;
        padding:0.12rem 0.5rem; letter-spacing:0; text-transform:none;
    }
    .pdp-picker__body { flex:1; padding:0.35rem; min-height:0; }
    .pdp-picker__body--results {
        max-height:22.5rem;
        overflow-y:auto;
        overflow-x:hidden;
        overscroll-behavior:contain;
    }
    .pdp-picker__body--detail {
        max-height:22.5rem;
        overflow-y:auto;
        overflow-x:hidden;
    }
    .pdp-picker__empty { padding:1rem 0.75rem; font-size:0.84rem; color:#64748b; line-height:1.45; }
    .pdp-result {
        display:block; width:100%; text-align:left; border:1px solid transparent; background:#fff; cursor:pointer;
        padding:0.62rem 0.68rem; border-radius:10px; margin-bottom:0.35rem;
    }
    .pdp-result:hover, .pdp-result.is-hover { border-color:#c7d2fe; background:#eef2ff; }
    .pdp-result.is-selected { border-color:#6366f1; background:#e0e7ff; box-shadow:0 0 0 1px #6366f1; }
    .pdp-result.is-disabled { opacity:0.62; cursor:not-allowed; }
    .pdp-result__top { display:flex; align-items:flex-start; justify-content:space-between; gap:0.5rem; }
    .pdp-result__name { font-size:0.86rem; font-weight:700; color:#0f172a; }
    .pdp-result__meta { margin-top:0.28rem; font-size:0.76rem; color:#64748b; line-height:1.4; }
    .pdp-pill { display:inline-flex; padding:0.12rem 0.45rem; border-radius:999px; font-size:0.68rem; font-weight:800; background:#eef2ff; color:#3730a3; margin-right:0.25rem; }
    .pdp-pill--ok { background:#dcfce7; color:#166534; }
    .pdp-pill--muted { background:#f1f5f9; color:#475569; }
    .pdp-pill--warn { background:#fff7ed; color:#c2410c; }
    .pdp-detail { padding:0.65rem 0.75rem; }
    .pdp-detail__title { margin:0 0 0.65rem; font-size:0.95rem; font-weight:800; color:#0f172a; }
    .pdp-detail__grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:0.55rem 0.75rem; }
    .pdp-detail__item { min-width:0; }
    .pdp-detail__label { font-size:0.72rem; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:0.03em; }
    .pdp-detail__value { margin-top:0.15rem; font-size:0.84rem; color:#0f172a; word-break:break-word; }
    .pdp-detail__badge { margin-bottom:0.65rem; }
    .pdp-search { width:100%; box-sizing:border-box; border:1px solid #cbd5e1; border-radius:8px; padding:0.58rem 0.7rem; font-size:0.88rem; background:#fff; }
    .pdp-actions { margin-top:1.1rem; display:flex; flex-wrap:wrap; gap:0.65rem; align-items:center; }
    .pdp-submit { border:none; border-radius:8px; background:#4f46e5; color:#fff; padding:0.62rem 1rem; font-weight:700; cursor:pointer; font-size:0.88rem; }
    .pdp-link { color:#4f46e5; font-weight:700; text-decoration:none; font-size:0.88rem; }
    @media (max-width:720px) {
        .pdp-grid { grid-template-columns:1fr; }
        .pdp-picker { grid-template-columns:1fr; }
        .pdp-detail__grid { grid-template-columns:1fr; }
    }
</style>
@endpush

@section('content')
@php
    $isEdit = !empty($row);
    $oldCfa = (int) old('cfa_submission_id', $isEdit ? (int) ($row->cfa_submission_id ?? 0) : 0);
    $oldLegacy = (int) old('legacy_application_id', $isEdit ? (int) ($row->legacy_application_id ?? 0) : 0);
@endphp
<div class="pdp-shell">
    @if (!empty($migrationMissing))
        <div class="pdp-alert pdp-alert--warning">
            <strong>Database update required.</strong> Run <code>php artisan migrate</code> for <code>pitch_deck_preparations</code>.
        </div>
    @endif

    @if (session('status'))
        <div class="pdp-alert pdp-alert--success">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="pdp-alert pdp-alert--error">
            <strong>Please fix:</strong>
            <ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="pdp-card">
        <h3 class="pdp-card__title">{{ $isEdit ? 'Edit entry' : 'New entry' }}</h3>
        <form method="post"
              action="{{ $isEdit ? route($storeRoute, $row) : route($storeRoute) }}"
              enctype="multipart/form-data"
              id="pdpForm">
            @csrf
            @if ($isEdit)
                @method('PUT')
            @endif

            <div class="pdp-grid">
                <div class="pdp-field">
                    <label>Entered by</label>
                    <input type="text" class="pdp-readonly" value="{{ $user->name }}" readonly>
                </div>
                <div class="pdp-field">
                    <label for="prepared_on">Prepared on <span class="pdp-req">*</span></label>
                    <x-activity-date-input name="prepared_on" id="prepared_on" :value="$isEdit ? $row->prepared_on?->format('Y-m-d') : null" />
                </div>

                <div class="pdp-field pdp-field--full">
                    <label for="pdpIncubateeSearch">Incubatee name <span class="pdp-req">*</span></label>
                    @if ($isEdit)
                        <input type="text" class="pdp-readonly" readonly
                            value="{{ $row->incubatee_name }}@if($row->application_no) · {{ $row->application_no }}@endif · {{ $row->incubateeSourceLabel() }}">
                        <input type="hidden" name="cfa_submission_id" value="{{ $oldCfa }}">
                        <input type="hidden" name="legacy_application_id" value="{{ $oldLegacy }}">
                    @else
                        <input type="text" id="pdpIncubateeSearch" class="pdp-search" autocomplete="off"
                            placeholder="Search by name, phone, or application number…"
                            value="{{ old('incubatee_label') }}">
                        <input type="hidden" name="cfa_submission_id" id="pdpCfaId" value="{{ $oldCfa ?: '' }}">
                        <input type="hidden" name="legacy_application_id" id="pdpLegacyId" value="{{ $oldLegacy ?: '' }}">
                        <input type="hidden" name="incubatee_key" id="pdpIncubateeKey" value="{{ old('incubatee_key') }}">
                        <div class="pdp-picker">
                            <div class="pdp-picker__col">
                                <div class="pdp-picker__head">
                                    <span>Search results</span>
                                    <span id="pdpResultsCount" class="pdp-picker__count" hidden>0</span>
                                </div>
                                <div id="pdpResults" class="pdp-picker__body pdp-picker__body--results" role="listbox" aria-label="Applicant search results">
                                    <p class="pdp-picker__empty">Type at least 2 characters to search all Phase 3 CFA and Phase 2 applicants.</p>
                                </div>
                            </div>
                            <div class="pdp-picker__col">
                                <div class="pdp-picker__head" id="pdpDetailHead">Applicant details</div>
                                <div id="pdpDetail" class="pdp-picker__body pdp-picker__body--detail">
                                    <p class="pdp-picker__empty">Hover a result to preview. Click to select the incubatee for this pitch deck.</p>
                                </div>
                            </div>
                        </div>
                        <p class="pdp-hint">Shows up to 5 matches. Hover to preview; click to select. Refine your search for more specific results.</p>
                        @error('incubatee_key')<p class="pdp-hint" style="color:#b91c1c;">{{ $message }}</p>@enderror
                    @endif
                </div>

                <div class="pdp-field pdp-field--full">
                    <label for="deck_file">Pitch deck file @if(!$isEdit)<span class="pdp-req">*</span>@endif</label>
                    <input type="file" id="deck_file" name="deck_file" accept=".pdf,.ppt,.pptx,application/pdf,application/vnd.ms-powerpoint,application/vnd.openxmlformats-officedocument.presentationml.presentation" @if(!$isEdit) required @endif>
                    <p class="pdp-hint">PDF or PowerPoint, max 20 MB.@if($isEdit) Leave empty to keep the current file.@endif</p>
                    @if ($isEdit && $row->deck_file_name)
                        <p class="pdp-hint">Current: {{ $row->deck_file_name }}</p>
                    @endif
                </div>

                <div class="pdp-field pdp-field--full">
                    <label for="prepared_for">Prepared for</label>
                    <input type="text" id="prepared_for" name="prepared_for" maxlength="191"
                        value="{{ old('prepared_for', $isEdit ? $row->prepared_for : '') }}"
                        placeholder="e.g. Demo Day, YES Summit">
                </div>

                <div class="pdp-field">
                    <label for="support_mode">Support mode</label>
                    <select id="support_mode" name="support_mode">
                        <option value="">— Optional —</option>
                        @foreach ($supportModes as $value => $label)
                            <option value="{{ $value }}" @selected(old('support_mode', $isEdit ? $row->support_mode : '') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="pdp-field pdp-field--full">
                    <label for="remarks">Remarks</label>
                    <textarea id="remarks" name="remarks" maxlength="5000" placeholder="Optional notes">{{ old('remarks', $isEdit ? $row->remarks : '') }}</textarea>
                </div>
            </div>

            <div class="pdp-actions">
                <button type="submit" class="pdp-submit">{{ $isEdit ? 'Update entry' : 'Save entry' }}</button>
                <a href="{{ route($dashboardRoute) }}" class="pdp-link">View dashboard</a>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
@if (!$isEdit)
<script>
(function () {
    const searchInput = document.getElementById('pdpIncubateeSearch');
    const resultsPanel = document.getElementById('pdpResults');
    const resultsCount = document.getElementById('pdpResultsCount');
    const detailPanel = document.getElementById('pdpDetail');
    const detailHead = document.getElementById('pdpDetailHead');
    const cfaInput = document.getElementById('pdpCfaId');
    const legacyInput = document.getElementById('pdpLegacyId');
    const keyInput = document.getElementById('pdpIncubateeKey');
    const searchUrl = @json(route($searchRoute));
    let timer = null;
    let lastResults = [];
    let hoverIndex = -1;
    let selectedKey = keyInput.value || '';
    let selectedItem = null;

    function esc(text) {
        return String(text ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function dash(value) {
        const v = String(value ?? '').trim();
        return v !== '' ? v : '—';
    }

    function statusPill(item) {
        if (item.is_onboarded) {
            return '<span class="pdp-pill pdp-pill--ok">Onboarded</span>';
        }
        return '<span class="pdp-pill pdp-pill--muted">Not onboarded</span>';
    }

    function renderDetail(item, mode) {
        if (!item) {
            detailHead.textContent = 'Applicant details';
            detailPanel.innerHTML = '<p class="pdp-picker__empty">Hover a result to preview. Click to select the incubatee for this pitch deck.</p>';
            return;
        }

        detailHead.textContent = mode === 'selected' ? 'Selected incubatee' : 'Applicant preview';
        const recorded = item.already_recorded
            ? '<span class="pdp-pill pdp-pill--warn">Pitch deck already recorded</span>'
            : '';
        detailPanel.innerHTML =
            '<div class="pdp-detail">' +
                '<div class="pdp-detail__badge">' + statusPill(item) +
                    '<span class="pdp-pill">' + esc(item.source || '') + '</span>' + recorded + '</div>' +
                '<h4 class="pdp-detail__title">' + esc(item.name || 'Unnamed') + '</h4>' +
                '<div class="pdp-detail__grid">' +
                    detailField('Application no.', item.application_no) +
                    detailField('Phone', item.phone) +
                    detailField('District', item.district) +
                    detailField('Hub', item.hub) +
                    detailField('Block', item.block) +
                    detailField('Village', item.village) +
                    detailField('Gender', item.gender) +
                    detailField('Business category', item.business_category) +
                    detailField('Onboarding status', item.onboarding_status) +
                    detailField('Onboarding batch', item.onboarding_batch_name) +
                '</div>' +
            '</div>';
    }

    function detailField(label, value) {
        return '<div class="pdp-detail__item"><div class="pdp-detail__label">' + esc(label) +
            '</div><div class="pdp-detail__value">' + esc(dash(value)) + '</div></div>';
    }

    function clearSelection() {
        selectedKey = '';
        selectedItem = null;
        cfaInput.value = '';
        legacyInput.value = '';
        keyInput.value = '';
    }

    function updateResultsCount(total) {
        if (!resultsCount) {
            return;
        }
        if (!total) {
            resultsCount.hidden = true;
            resultsCount.textContent = '0';
            return;
        }
        resultsCount.hidden = false;
        resultsCount.textContent = total === 1 ? '1 found' : total + ' found';
    }

    function scrollToHoveredRow() {
        if (hoverIndex < 0) {
            return;
        }
        const row = resultsPanel.querySelector('.pdp-result[data-index="' + hoverIndex + '"]');
        if (row) {
            row.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
        }
    }

    function highlightRows() {
        resultsPanel.querySelectorAll('.pdp-result').forEach(function (btn, idx) {
            btn.classList.toggle('is-hover', idx === hoverIndex);
            btn.classList.toggle('is-selected', lastResults[idx] && lastResults[idx].key === selectedKey);
        });
    }

    function renderResults(items) {
        lastResults = items;
        hoverIndex = -1;
        resultsPanel.innerHTML = '';
        updateResultsCount(items.length);

        if (!items.length) {
            resultsPanel.innerHTML = '<p class="pdp-picker__empty">No matches. Try name, phone, or application number.</p>';
            if (!selectedItem) {
                renderDetail(null);
            }
            return;
        }

        items.forEach(function (item, idx) {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'pdp-result' + (item.already_recorded ? ' is-disabled' : '');
            btn.dataset.index = String(idx);
            const recorded = item.already_recorded ? '<span class="pdp-pill pdp-pill--warn">Recorded</span>' : '';
            btn.innerHTML =
                '<div class="pdp-result__top">' +
                    '<div class="pdp-result__name">' + esc(item.name || 'Unnamed') + '</div>' +
                    '<div>' + statusPill(item) + '</div>' +
                '</div>' +
                '<div class="pdp-result__meta">' +
                    '<span class="pdp-pill">' + esc(item.source || '') + '</span>' + recorded +
                    '<div>App: ' + esc(dash(item.application_no)) + ' · Phone: ' + esc(dash(item.phone)) + '</div>' +
                    '<div>' + esc(dash(item.district)) + (item.block ? ' · ' + esc(item.block) : '') + '</div>' +
                '</div>';

            btn.addEventListener('mouseenter', function () {
                hoverIndex = idx;
                highlightRows();
                renderDetail(item, 'hover');
            });
            btn.addEventListener('click', function () { selectItem(item, idx); });
            resultsPanel.appendChild(btn);
        });

        highlightRows();
    }

    function selectItem(item, idx) {
        if (item.already_recorded) {
            alert('A pitch deck is already recorded for this incubatee.');
            return;
        }
        selectedKey = item.key || '';
        selectedItem = item;
        hoverIndex = typeof idx === 'number' ? idx : hoverIndex;
        cfaInput.value = item.cfa_submission_id ? String(item.cfa_submission_id) : '';
        legacyInput.value = item.legacy_application_id ? String(item.legacy_application_id) : '';
        keyInput.value = item.key || '';
        searchInput.value = (item.name || '') + (item.application_no ? ' · ' + item.application_no : '');
        highlightRows();
        renderDetail(item, 'selected');
    }

    function fetchResults(q) {
        fetch(searchUrl + '?q=' + encodeURIComponent(q), {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                renderResults(data.results || []);
                if (selectedItem) {
                    const stillThere = (data.results || []).find(function (row) { return row.key === selectedItem.key; });
                    if (stillThere) {
                        selectItem(stillThere);
                    }
                }
            })
            .catch(function () { renderResults([]); });
    }

    searchInput.addEventListener('input', function () {
        const q = searchInput.value.trim();
        clearSelection();
        clearTimeout(timer);
        if (q.length < 2) {
            lastResults = [];
            updateResultsCount(0);
            resultsPanel.innerHTML = '<p class="pdp-picker__empty">Type at least 2 characters to search all Phase 3 CFA and Phase 2 applicants.</p>';
            renderDetail(null);
            return;
        }
        timer = setTimeout(function () { fetchResults(q); }, 220);
    });

    searchInput.addEventListener('keydown', function (e) {
        if (!lastResults.length) {
            return;
        }
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            hoverIndex = Math.min(lastResults.length - 1, hoverIndex + 1);
            highlightRows();
            renderDetail(lastResults[hoverIndex], 'hover');
            scrollToHoveredRow();
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            hoverIndex = Math.max(0, hoverIndex - 1);
            highlightRows();
            renderDetail(lastResults[hoverIndex], 'hover');
            scrollToHoveredRow();
        } else if (e.key === 'Enter' && hoverIndex >= 0) {
            e.preventDefault();
            selectItem(lastResults[hoverIndex], hoverIndex);
        }
    });
})();
</script>
@endif
@endpush
