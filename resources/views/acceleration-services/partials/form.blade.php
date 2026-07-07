@php
    $prefill = $prefillApplicant ?? null;
    $sections = [
        'service_detail' => ['title' => 'In-house — Service details', 'input' => 'service_detail', 'custom' => 'custom_service_detail'],
        'cross_cutting' => ['title' => 'Cross-cutting initiative', 'input' => 'cross_cutting', 'custom' => 'custom_cross_cutting'],
        'partnership' => ['title' => 'External — Co-incubation partners', 'input' => 'partnership', 'custom' => 'custom_partnership'],
    ];
@endphp
@include('acceleration-services.partials.styles')

<div class="accel-card accel-card--form" id="accel-form">
    <div class="accel-card__head">
        <h3 class="accel-card__title">New acceleration entry</h3>
        <p class="accel-card__sub">Phase 1 applicants only. Select services ticked today; remarks and photos are optional.</p>
    </div>

    @if (empty($legacyPhase1Available))
        <div class="accel-alert accel-alert--warning">Legacy Phase 1 database is not configured. Applicant search will be empty.</div>
    @endif

    <form method="post" action="{{ route($storeRoute) }}" enctype="multipart/form-data" id="accelSubmitForm">
        @csrf
        <div class="accel-grid">
            <div class="accel-form-top">
                <div class="accel-field">
                    <label for="service_date">Service date <span class="accel-required">*</span></label>
                    <input type="date" id="service_date" name="service_date" value="{{ old('service_date', now()->toDateString()) }}" required>
                </div>
                <div class="accel-field">
                    <label for="accel_search">Search Phase 1 applicant <span class="accel-required">*</span></label>
                    <input type="text" id="accel_search" class="accel-search-input" placeholder="Name, application no, or phone (min 2 characters)" autocomplete="off" @if($prefill) disabled @endif>
                    <p class="accel-field-hint">Search statewide Phase 1 applicants. Hover a row to preview; click <strong>Add</strong> to select.</p>
                </div>
            </div>

            <div class="accel-field" style="margin-bottom:0;">
                <div class="accel-picker" id="accel_picker">
                    <div class="accel-picker__grid">
                        <div>
                            <p class="accel-picker__col-head">Applicants</p>
                            <div id="accel_search_results" class="accel-picker__list">
                                <p class="accel-picker__detail-empty" style="padding:0.85rem;">Type in the search box to load applicants.</p>
                            </div>
                        </div>
                        <div class="accel-picker__detail" id="accel_detail_card">
                            <h4 class="accel-picker__detail-title">Applicant details</h4>
                            <p class="accel-picker__detail-empty" id="accel_detail_empty">Hover or click <strong>View</strong> on an applicant to see details here.</p>
                            <div id="accel_detail_body" hidden></div>
                        </div>
                    </div>
                </div>

                <div id="accel_selected_applicant" class="accel-selected" style="{{ $prefill ? '' : 'display:none;' }}">
                    @if ($prefill)
                        <strong>Selected:</strong> {{ $prefill['applicant_name'] }}
                        @if (!empty($prefill['application_no'])) · {{ $prefill['application_no'] }} @endif
                        <br><span>CFA: {{ $prefill['application_date'] ?? '—' }} · {{ $prefill['district_name'] ?: '—' }} · {{ $prefill['phone'] ?: '—' }} · {{ $prefill['onboard_label'] ?: '—' }}</span>
                    @endif
                </div>
                <input type="hidden" name="legacy_phase1_application_id" id="legacy_phase1_application_id" value="{{ old('legacy_phase1_application_id', $prefill['legacy_phase1_application_id'] ?? '') }}" required>
                <input type="hidden" id="incubatee_key" value="{{ $prefill['incubatee_key'] ?? '' }}">
            </div>

            @foreach ($sections as $sectionKey => $meta)
                <section class="accel-section">
                    <div class="accel-section__head">
                        <h4 class="accel-section__title">{{ $loop->iteration }}. {{ $meta['title'] }}</h4>
                    </div>
                    <div class="accel-section__items">
                        @foreach ($catalog[$sectionKey] ?? [] as $item)
                            @php $key = $item['key']; @endphp
                            <div class="accel-item" data-item-key="{{ $key }}">
                                <div class="accel-item__head">
                                    <input type="checkbox" name="{{ $meta['input'] }}[]" value="{{ $key }}" id="item_{{ $key }}" class="accel-item-check">
                                    <label for="item_{{ $key }}">{{ $item['label'] }}</label>
                                </div>
                                <div class="accel-item__extra">
                                    <div class="accel-field">
                                        <label>Remarks (optional)</label>
                                        <textarea name="remarks[{{ $key }}]" rows="2" placeholder="Optional notes for this service"></textarea>
                                    </div>
                                    <div class="accel-field">
                                        <label>Documents / photos (optional)</label>
                                        <input type="file" name="media[{{ $key }}][]" accept=".pdf,.jpg,.jpeg,.png,.webp,image/*,application/pdf" multiple class="accel-media-input" data-preview="preview_{{ $key }}">
                                        <div id="preview_{{ $key }}" class="accel-media-preview"></div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <div class="accel-custom-row">
                        <input type="text" name="{{ $meta['custom'] }}[]" placeholder="+ Add new service (saved for next time)">
                    </div>
                </section>
            @endforeach

            <div class="accel-form-actions">
                <button type="submit" class="accel-btn">Save entry</button>
            </div>
        </div>
    </form>
</div>

@push('scripts')
<script>
(function () {
    const searchUrl = @json(route($searchRoute));
    const historyUrl = @json(route($historyRoute));
    const prefillApplicant = @json($prefill ?? null);
    const searchInput = document.getElementById('accel_search');
    const searchResults = document.getElementById('accel_search_results');
    const selectedPanel = document.getElementById('accel_selected_applicant');
    const legacyInput = document.getElementById('legacy_phase1_application_id');
    const incubateeKeyInput = document.getElementById('incubatee_key');
    const detailEmpty = document.getElementById('accel_detail_empty');
    const detailBody = document.getElementById('accel_detail_body');
    let searchTimer = null;
    let applicants = [];
    let focusedId = null;
    let lockedId = null;
    let selectedId = null;
    const servicesCache = {};

    function bindItemChecks() {
        document.querySelectorAll('.accel-item-check').forEach((el) => {
            const wrap = el.closest('.accel-item');
            const sync = () => wrap.classList.toggle('is-checked', el.checked);
            el.addEventListener('change', sync);
            sync();
        });
    }

    function bindMediaPreviews() {
        document.querySelectorAll('.accel-media-input').forEach((input) => {
            input.addEventListener('change', function () {
                const preview = document.getElementById(this.dataset.preview || '');
                if (!preview) return;
                preview.innerHTML = '';
                Array.from(this.files || []).forEach((file) => {
                    if ((file.type || '').startsWith('image/')) {
                        const img = document.createElement('img');
                        img.alt = file.name;
                        img.src = URL.createObjectURL(file);
                        preview.appendChild(img);
                    } else {
                        const chip = document.createElement('span');
                        chip.className = 'accel-media-chip';
                        chip.textContent = file.name;
                        preview.appendChild(chip);
                    }
                });
            });
        });
    }

    function applicantId(app) {
        return String(app.legacy_phase1_application_id || '');
    }

    function applicantBriefMeta(app) {
        return [
            'CFA: ' + (app.application_date || '—'),
            app.district_name || '—',
            app.phone || '—',
            app.onboard_label || '—',
        ].join(' · ');
    }

    function renderServicesHtml(items) {
        if (!items.length) {
            return '<p class="accel-picker__detail-empty">No services recorded yet.</p>';
        }
        return items.map((row) => {
            let badge = '';
            if (row.badge === 'initiation') {
                badge = '<span class="accel-badge accel-badge--init">Initiation</span>';
            } else if (row.badge === 'follow-up') {
                badge = '<span class="accel-badge accel-badge--follow">Follow-up</span>';
            }
            const status = row.status ? ' · ' + row.status.replace(/_/g, ' ') : '';
            const detail = row.detail
                ? '<div class="accel-side-entry__meta">' + row.detail + '</div>'
                : '';
            return '<div class="accel-side-entry">'
                + '<div class="accel-side-entry__date">' + (row.date || '—') + (badge ? ' ' + badge : '') + '</div>'
                + '<div>' + (row.label || '—') + '</div>'
                + '<div class="accel-side-entry__meta">By ' + (row.assigned_by || '—')
                + (row.media_count > 0 ? ' · ' + row.media_count + ' file(s)' : '')
                + status
                + (row.source_label ? ' · ' + row.source_label : '')
                + '</div>'
                + detail
                + '</div>';
        }).join('');
    }

    function loadServices(app) {
        if (!app) {
            return Promise.resolve([]);
        }
        const legacyId = applicantId(app);
        const key = String(app.incubatee_key || '');
        const cacheKey = legacyId + ':' + key;
        if (servicesCache[cacheKey]) {
            return Promise.resolve(servicesCache[cacheKey]);
        }
        const params = new URLSearchParams({
            legacy_phase1_application_id: legacyId,
            incubatee_key: key,
        });
        return fetch(historyUrl + '?' + params.toString(), { headers: { 'Accept': 'application/json' } })
            .then((r) => r.json())
            .then((data) => {
                const items = data.services || [];
                servicesCache[cacheKey] = items;
                return items;
            })
            .catch(() => []);
    }

    function renderDetail(app, serviceItems) {
        if (!app) {
            detailEmpty.hidden = false;
            detailBody.hidden = true;
            detailBody.innerHTML = '';
            return;
        }
        detailEmpty.hidden = true;
        detailBody.hidden = false;
        detailBody.innerHTML = ''
            + '<dl class="accel-picker__meta">'
            + '<div><dt>Name</dt><dd>' + (app.applicant_name || 'Unnamed') + '</dd></div>'
            + '<div><dt>Application no</dt><dd>' + (app.application_no || '—') + '</dd></div>'
            + '<div><dt>CFA date</dt><dd>' + (app.application_date || '—') + '</dd></div>'
            + '<div><dt>District</dt><dd>' + (app.district_name || '—') + '</dd></div>'
            + '<div><dt>Block</dt><dd>' + (app.block_name || '—') + '</dd></div>'
            + '<div><dt>Phone</dt><dd>' + (app.phone || '—') + '</dd></div>'
            + '<div><dt>Onboarding</dt><dd>' + (app.onboard_label || '—') + '</dd></div>'
            + '</dl>'
            + '<p class="accel-picker__history-title">All services given</p>'
            + '<div class="accel-picker__history">' + renderServicesHtml(serviceItems || []) + '</div>';
    }

    function showApplicantDetail(app, lock) {
        if (!app) return;
        const id = applicantId(app);
        focusedId = id;
        if (lock) {
            lockedId = id;
        }
        highlightListRows();
        loadServices(app).then((items) => renderDetail(app, items));
    }

    function highlightListRows() {
        searchResults.querySelectorAll('.accel-search-item').forEach((row) => {
            const id = row.dataset.applicantId || '';
            row.classList.toggle('is-active', id === focusedId || id === lockedId);
            row.classList.toggle('is-selected', id === selectedId);
        });
    }

    function renderSelectedApplicant(app) {
        selectedPanel.style.display = '';
        selectedPanel.innerHTML = '<strong>Selected:</strong> ' + (app.applicant_name || 'Unnamed')
            + (app.application_no ? ' · ' + app.application_no : '')
            + '<br><span>' + applicantBriefMeta(app) + '</span>';
    }

    function selectApplicant(app) {
        const id = applicantId(app);
        selectedId = id;
        legacyInput.value = id;
        incubateeKeyInput.value = String(app.incubatee_key || '');
        renderSelectedApplicant(app);
        showApplicantDetail(app, true);
        highlightListRows();
    }

    function renderApplicantList(list) {
        applicants = list;
        searchResults.innerHTML = '';
        if (!list.length) {
            searchResults.innerHTML = '<p class="accel-picker__detail-empty" style="padding:0.85rem;">No applicants found.</p>';
            renderDetail(null);
            return;
        }

        list.forEach((app) => {
            const id = applicantId(app);
            const div = document.createElement('div');
            div.className = 'accel-search-item';
            div.dataset.applicantId = id;

            const nameLine = document.createElement('div');
            nameLine.className = 'accel-search-item__name';
            nameLine.textContent = (app.applicant_name || 'Unnamed')
                + (app.application_no ? ' · ' + app.application_no : '');

            const metaLine = document.createElement('div');
            metaLine.className = 'accel-search-item__meta';
            metaLine.textContent = applicantBriefMeta(app);

            const actions = document.createElement('div');
            actions.className = 'accel-search-item__actions';

            const viewBtn = document.createElement('button');
            viewBtn.type = 'button';
            viewBtn.className = 'accel-btn accel-btn--xs accel-btn--ghost';
            viewBtn.textContent = 'View';
            viewBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                showApplicantDetail(app, true);
            });

            const addBtn = document.createElement('button');
            addBtn.type = 'button';
            addBtn.className = 'accel-btn accel-btn--xs accel-btn--add';
            addBtn.textContent = 'Add';
            addBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                selectApplicant(app);
            });

            actions.appendChild(viewBtn);
            actions.appendChild(addBtn);

            div.appendChild(nameLine);
            div.appendChild(metaLine);
            div.appendChild(actions);

            div.addEventListener('mouseenter', () => {
                showApplicantDetail(app, false);
            });

            div.addEventListener('mouseleave', () => {
                if (lockedId) {
                    const lockedApp = applicants.find((a) => applicantId(a) === lockedId);
                    if (lockedApp) {
                        showApplicantDetail(lockedApp, true);
                    }
                    return;
                }
                focusedId = null;
                highlightListRows();
                renderDetail(null);
            });

            searchResults.appendChild(div);
        });

        highlightListRows();
    }

    if (searchInput) {
        searchInput.addEventListener('input', function () {
            clearTimeout(searchTimer);
            const q = (this.value || '').trim();
            lockedId = null;
            focusedId = null;
            if (q.length < 2) {
                searchResults.innerHTML = '<p class="accel-picker__detail-empty" style="padding:0.85rem;">Type at least 2 characters to search.</p>';
                renderDetail(null);
                return;
            }
            searchTimer = setTimeout(() => {
                searchResults.innerHTML = '<p class="accel-picker__detail-empty" style="padding:0.85rem;">Searching…</p>';
                fetch(searchUrl + '?q=' + encodeURIComponent(q), { headers: { 'Accept': 'application/json' } })
                    .then((r) => r.json())
                    .then((data) => renderApplicantList(data.applicants || []));
            }, 250);
        });
    }

    bindItemChecks();
    bindMediaPreviews();

    if (prefillApplicant && prefillApplicant.legacy_phase1_application_id) {
        selectApplicant(prefillApplicant);
    } else if (incubateeKeyInput && incubateeKeyInput.value && legacyInput.value) {
        const stub = {
            legacy_phase1_application_id: legacyInput.value,
            incubatee_key: incubateeKeyInput.value,
            applicant_name: selectedPanel.textContent || 'Selected applicant',
            application_no: '',
            application_date: '—',
            district_name: '—',
            phone: '—',
            onboard_label: '—',
            block_name: '—',
        };
        showApplicantDetail(stub, true);
        selectedId = String(legacyInput.value);
        highlightListRows();
    }
}());
</script>
@endpush
