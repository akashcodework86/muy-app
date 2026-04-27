@extends('layouts.admin')

@section('title', 'New service submission')
@section('heading', 'New service submission')

@section('content')
    <p style="margin:0 0 1rem;"><a href="{{ route('staff.services.index') }}">← Service cases</a></p>

    @if ($submissions->isEmpty())
        <p style="background:#fffbeb;border:1px solid #fde68a;color:#92400e;padding:0.75rem 1rem;border-radius:8px;font-size:0.88rem;">
            No eligible incubatees found for your district with the current eligibility rules.
            @if (app(\App\Services\AppSettingsService::class)->get('service_module.eligibility') === 'onboarded_only')
                Only incubatees who are in an onboarding batch are listed. State admin can widen this under <strong>Service module settings</strong>.
            @endif
        </p>
    @else
        <style>
            .svc-page { display:grid; gap:1rem; }
            .svc-hero {
                background: linear-gradient(130deg, #312e81 0%, #4338ca 35%, #0d9488 100%);
                color:#eef2ff; border-radius:16px; padding:1rem 1.1rem; box-shadow:0 14px 34px rgba(15,23,42,.18);
            }
            .svc-hero h2 { margin:0; font-size:1.2rem; font-weight:800; }
            .svc-hero p { margin:.35rem 0 0; font-size:.86rem; opacity:.92; }
            .svc-stepper { display:flex; gap:.45rem; flex-wrap:wrap; margin-top:.8rem; }
            .svc-step {
                font-size:.75rem; font-weight:700; letter-spacing:.02em; padding:.32rem .62rem; border-radius:999px;
                border:1px solid rgba(255,255,255,.34); background:rgba(255,255,255,.1); color:#e0e7ff;
            }
            .svc-step.is-active { background:#fff; color:#1e1b4b; border-color:#fff; }
            .svc-shell { display:grid; gap:1rem; grid-template-columns:320px 1fr; align-items:start; }
            @media (max-width: 1040px) { .svc-shell { grid-template-columns:1fr; } }
            .svc-card {
                background:#fff; border:1px solid #e2e8f0; border-radius:14px; box-shadow:0 8px 24px rgba(15,23,42,.06);
                padding:1rem;
            }
            .svc-card h3 { margin:0 0 .65rem; font-size:.86rem; text-transform:uppercase; letter-spacing:.05em; color:#475569; }
            .svc-input, .svc-select, .svc-textarea {
                width:100%; border:1px solid #cbd5e1; border-radius:10px; padding:.56rem .62rem; background:#fff; font-size:.9rem;
            }
            .svc-select:focus, .svc-input:focus, .svc-textarea:focus { outline:none; border-color:#6366f1; box-shadow:0 0 0 3px rgba(99,102,241,.12); }
            .svc-incubatee-meta { display:flex; flex-wrap:wrap; gap:.4rem; margin-top:.7rem; }
            .svc-chip { font-size:.72rem; font-weight:700; border-radius:999px; padding:.2rem .5rem; }
            .svc-chip--indigo { color:#3730a3; background:#e0e7ff; }
            .svc-chip--emerald { color:#065f46; background:#d1fae5; }
            .svc-chip--slate { color:#334155; background:#e2e8f0; }
            .svc-search-wrap { display:flex; gap:.55rem; align-items:center; margin-bottom:.7rem; }
            .svc-filter-pills { display:flex; gap:.45rem; flex-wrap:wrap; margin-bottom:.7rem; }
            .svc-pill {
                border:1px solid #cbd5e1; border-radius:999px; background:#f8fafc; color:#334155;
                padding:.26rem .6rem; font-size:.74rem; font-weight:700; cursor:pointer;
            }
            .svc-pill.is-active { background:#eef2ff; border-color:#818cf8; color:#3730a3; }
            .svc-service-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:.65rem; }
            @media (max-width: 960px) { .svc-service-grid { grid-template-columns:1fr; } }
            .svc-service {
                border:1px solid #e2e8f0; border-radius:12px; padding:.72rem; background:#fff;
                cursor:pointer; transition:all .15s ease; position:relative;
            }
            .svc-service:hover { border-color:#a5b4fc; box-shadow:0 6px 20px rgba(79,70,229,.12); transform:translateY(-1px); }
            .svc-service.is-selected { border-color:#6366f1; background:linear-gradient(180deg,#eef2ff,#fff); box-shadow:0 0 0 2px rgba(99,102,241,.16); }
            .svc-service.is-disabled { opacity:.55; cursor:not-allowed; background:#f8fafc; }
            .svc-service.is-disabled:hover { border-color:#e2e8f0; box-shadow:none; transform:none; }
            .svc-service-title { margin:0; font-size:.86rem; font-weight:800; color:#0f172a; }
            .svc-service-sub { margin:.18rem 0 .42rem; font-size:.73rem; font-weight:700; color:#6366f1; text-transform:uppercase; letter-spacing:.04em; }
            .svc-badges { display:flex; flex-wrap:wrap; gap:.3rem; }
            .svc-badge { font-size:.68rem; font-weight:700; border-radius:999px; padding:.14rem .45rem; }
            .svc-badge--approval { background:#fef3c7; color:#92400e; }
            .svc-badge--auto { background:#dcfce7; color:#166534; }
            .svc-badge--doc { background:#dbeafe; color:#1e40af; }
            .svc-badge--single { background:#fee2e2; color:#991b1b; }
            .svc-badge--multi { background:#e2e8f0; color:#334155; }
            .svc-badge--locked { background:#f1f5f9; color:#64748b; }
            .svc-meta { margin:.75rem 0 0; font-size:.84rem; color:#334155; }
            .svc-warning { margin-top:.6rem; font-size:.82rem; color:#b91c1c; font-weight:700; }
            .svc-section {
                border:1px solid #e2e8f0; border-radius:12px; background:#fff;
                padding:.85rem; margin-top:.75rem;
            }
            .svc-section h4 { margin:0 0 .58rem; font-size:.82rem; text-transform:uppercase; color:#475569; letter-spacing:.05em; }
            .svc-schema { display:flex; flex-direction:column; gap:.7rem; }
            .svc-right-note { border:1px dashed #c7d2fe; background:#f8faff; padding:.7rem .75rem; border-radius:10px; font-size:.8rem; color:#334155; line-height:1.45; }
            .svc-sticky-actions {
                position:sticky; bottom:.35rem; z-index:9; margin-top:1rem;
                display:flex; justify-content:flex-end; gap:.55rem; padding:.7rem;
                background:rgba(248,250,252,.9); border:1px solid #e2e8f0; border-radius:12px; backdrop-filter: blur(4px);
            }
            .svc-btn {
                border:none; border-radius:10px; padding:.55rem 1rem; font-weight:800; cursor:pointer;
            }
            .svc-btn--ghost { background:#e2e8f0; color:#334155; }
            .svc-btn--primary { background:linear-gradient(90deg,#4f46e5,#0ea5e9); color:#fff; box-shadow:0 8px 20px rgba(79,70,229,.3); }
            .svc-errors {
                margin:0; padding:.6rem .85rem .6rem 1.2rem; border:1px solid #fecaca; border-radius:10px; background:#fff1f2; color:#b91c1c;
                font-size:.86rem;
            }
        </style>

        @if ($errors->any())
            <ul class="svc-errors">
                @foreach ($errors->all() as $e)
                    <li>{{ $e }}</li>
                @endforeach
            </ul>
        @endif

        <div class="svc-page">
            <div class="svc-hero">
                <h2><i class="fa-solid fa-briefcase"></i> New service submission</h2>
                <p>Premium guided flow: pick incubatee, choose service card, complete details, submit smoothly.</p>
                <div class="svc-stepper" id="svc_stepper">
                    <span class="svc-step is-active" data-step="1">1. Incubatee</span>
                    <span class="svc-step" data-step="2">2. Service</span>
                    <span class="svc-step" data-step="3">3. Submit</span>
                </div>
            </div>

            <form id="serviceSubmitForm" method="post" action="{{ route('staff.services.store') }}" enctype="multipart/form-data">
                @csrf

                <div class="svc-shell">
                    <aside class="svc-card">
                        <h3><i class="fa-regular fa-user"></i> Incubatee</h3>
                        <label for="cfa_submission_id" style="display:block;font-size:.76rem;font-weight:700;color:#475569;margin-bottom:.3rem;">Select incubatee (CFA)</label>
                        <select id="cfa_submission_id" name="cfa_submission_id" required class="svc-select">
                            <option value="">Choose incubatee...</option>
                            @foreach ($submissions as $sub)
                                <option value="{{ $sub->id }}" @selected((int) old('cfa_submission_id', (int) ($defaultCfaSubmissionId ?? 0)) === (int) $sub->id)>
                                    {{ $sub->applicant_name }} @if ($sub->application_no) · {{ $sub->application_no }} @endif
                                </option>
                            @endforeach
                        </select>
                        <div class="svc-incubatee-meta">
                            <span class="svc-chip svc-chip--indigo"><i class="fa-solid fa-id-card"></i> CFA linked</span>
                            <span class="svc-chip svc-chip--emerald"><i class="fa-solid fa-circle-check"></i> Eligible</span>
                            <span class="svc-chip svc-chip--slate"><i class="fa-solid fa-shield"></i> District scope</span>
                        </div>
                        <div class="svc-right-note" style="margin-top:.8rem;">
                            <strong>What happens after submit?</strong><br>
                            If service needs approval, it moves to SPOC pending. Otherwise auto-approved.
                        </div>
                    </aside>

                    <section class="svc-card">
                        <h3><i class="fa-solid fa-layer-group"></i> Service Selection</h3>
                        <div class="svc-search-wrap">
                            <input type="search" id="svc_search" class="svc-input" placeholder="Search services by name or category...">
                        </div>
                        @php
                            $svcCategories = collect($services)->map(fn($s) => $s->category?->name ?? 'Other')->unique()->sort()->values();
                            $selectedServiceIdOld = (int) old('service_id');
                        @endphp
                        <div class="svc-filter-pills" id="svc_filters">
                            <button type="button" class="svc-pill is-active" data-cat="All">All</button>
                            @foreach ($svcCategories as $cat)
                                <button type="button" class="svc-pill" data-cat="{{ $cat }}">{{ $cat }}</button>
                            @endforeach
                        </div>
                        <div id="service_cards" class="svc-service-grid">
                            @foreach ($services as $svc)
                                @php
                                    $cat = $svc->category?->name ?? 'Other';
                                    $isSelected = $selectedServiceIdOld === (int) $svc->id;
                                @endphp
                                <label class="svc-service{{ $isSelected ? ' is-selected' : '' }}" data-id="{{ $svc->id }}" data-category="{{ $cat }}" data-name="{{ strtolower($svc->name) }}">
                                    <input type="radio" name="service_id" value="{{ $svc->id }}" style="display:none;" @checked($isSelected)>
                                    <p class="svc-service-sub">{{ $cat }}</p>
                                    <p class="svc-service-title">{{ $svc->name }}</p>
                                    <div class="svc-badges">
                                        @if ($svc->requires_approval)
                                            <span class="svc-badge svc-badge--approval"><i class="fa-regular fa-clock"></i> SPOC approval</span>
                                        @else
                                            <span class="svc-badge svc-badge--auto"><i class="fa-solid fa-check"></i> Auto approve</span>
                                        @endif
                                        @if ($svc->requires_document)
                                            <span class="svc-badge svc-badge--doc"><i class="fa-regular fa-file-lines"></i> Docs required</span>
                                        @endif
                                        @if ($svc->allows_multiple)
                                            <span class="svc-badge svc-badge--multi"><i class="fa-solid fa-repeat"></i> Multiple allowed</span>
                                        @else
                                            <span class="svc-badge svc-badge--single"><i class="fa-solid fa-lock"></i> One-time only</span>
                                        @endif
                                    </div>
                                </label>
                            @endforeach
                        </div>
                        <p id="svc_meta" class="svc-meta"></p>
                        <p id="svc_warning" class="svc-warning" style="display:none;"></p>

                        <div class="svc-section">
                            <h4><i class="fa-solid fa-certificate"></i> Reference details</h4>
                            <label for="reference_number" style="display:block;font-size:.78rem;font-weight:700;color:#475569;margin-bottom:.32rem;">Reference / certificate no. (optional)</label>
                            <input type="text" id="reference_number" name="reference_number" value="{{ old('reference_number') }}" maxlength="191" class="svc-input">
                        </div>

                        <div id="wrap_delivered" class="svc-section" style="display:none;">
                            <h4><i class="fa-regular fa-calendar-check"></i> Delivery date</h4>
                            <label for="delivered_on" style="display:block;font-size:.78rem;font-weight:700;color:#475569;margin-bottom:.32rem;">Delivered on</label>
                            <input type="date" id="delivered_on" name="delivered_on" value="{{ old('delivered_on') }}" class="svc-input">
                            <p style="margin:.35rem 0 0;font-size:.75rem;color:#64748b;">Shown for auto-approve services.</p>
                        </div>

                        <div class="svc-section">
                            <h4><i class="fa-regular fa-rectangle-list"></i> Service details</h4>
                            <div id="schema_fields" class="svc-schema">
                                <p style="margin:0;color:#64748b;font-size:.84rem;">Select a service card to load dynamic form fields.</p>
                            </div>
                        </div>

                        <div id="wrap_attach" class="svc-section" style="display:none;">
                            <h4><i class="fa-regular fa-file-lines"></i> Documents</h4>
                            <p style="margin:0 0 .4rem;font-size:.78rem;color:#64748b;">Max 3 files, PDF/Image only, 5 MB each.</p>
                            <input type="file" name="attachments[]" multiple accept=".pdf,.jpg,.jpeg,.png,.webp,image/*,application/pdf" class="svc-input">
                        </div>
                    </section>
                </div>

                <div class="svc-sticky-actions">
                    <a href="{{ route('staff.services.index') }}" class="svc-btn svc-btn--ghost" style="text-decoration:none;display:inline-flex;align-items:center;">Back</a>
                    <button type="submit" class="svc-btn svc-btn--primary"><i class="fa-solid fa-paper-plane"></i> Submit</button>
                </div>
            </form>
        </div>

        <div id="submitProgressOverlay" style="display:none;position:fixed;inset:0;background:rgba(9,12,22,0.62);z-index:9999;align-items:center;justify-content:center;padding:1.2rem;">
            <div style="width:min(760px,97vw);min-height:280px;background:#ffffff;border-radius:18px;padding:1.6rem 1.45rem 1.65rem;border:1px solid #e4e4e7;box-shadow:0 24px 60px rgba(0,0,0,0.28);position:relative;overflow:hidden;display:flex;flex-direction:column;justify-content:center;">
                <p style="margin:0 0 0.7rem;font-size:0.8rem;font-weight:800;color:#64748b;text-transform:uppercase;letter-spacing:0.08em;">Submitting intervention</p>
                <p id="submitProgressText" style="margin:0 0 1rem;font-size:1.4rem;line-height:1.25;font-weight:800;color:#0f172a;">Checking your data…</p>
                <div style="height:14px;background:#e2e8f0;border-radius:999px;overflow:hidden;">
                    <div id="submitProgressBar" style="width:8%;height:100%;background:linear-gradient(90deg,#4338ca,#0ea5e9,#10b981);border-radius:999px;transition:width .45s ease;"></div>
                </div>
                <div id="submitFireworksLayer" style="pointer-events:none;position:absolute;inset:0;overflow:hidden;"></div>
            </div>
        </div>

        <script>
            (function () {
                const SERVICES = @json($servicesJson);
                const EXISTING_NON_MULTIPLE = new Set(@json($existingNonMultiplePairs ?? []));

                const selSub = document.getElementById('cfa_submission_id');
                const search = document.getElementById('svc_search');
                const cardsWrap = document.getElementById('service_cards');
                const filtersWrap = document.getElementById('svc_filters');
                const meta = document.getElementById('svc_meta');
                const warning = document.getElementById('svc_warning');
                const wrapDel = document.getElementById('wrap_delivered');
                const wrapAtt = document.getElementById('wrap_attach');
                const box = document.getElementById('schema_fields');
                const stepEls = Array.from(document.querySelectorAll('.svc-step'));
                const categories = ['All'].concat(Array.from(new Set(SERVICES.map(s => String(s.category_name || 'Other')))).sort());
                let selectedCategory = 'All';

                function getSelectedServiceId() {
                    const picked = cardsWrap.querySelector('input[name="service_id"]:checked');
                    return parseInt(picked?.value || '0', 10);
                }

                function setSelectedServiceId(id) {
                    const picked = cardsWrap.querySelector('input[name="service_id"][value="' + id + '"]');
                    if (picked) picked.checked = true;
                }

                function esc(s) {
                    const d = document.createElement('div');
                    d.textContent = s;
                    return d.innerHTML;
                }

                function render() {
                    const id = getSelectedServiceId();
                    const subId = parseInt(selSub.value || '0', 10);
                    const svc = SERVICES.find(function (x) { return x.id === id; });
                    box.innerHTML = '';
                    meta.textContent = '';
                    warning.style.display = 'none';
                    wrapDel.style.display = 'none';
                    wrapAtt.style.display = 'none';
                    stepEls.forEach(el => el.classList.remove('is-active'));
                    if (subId > 0) stepEls.find(x => x.dataset.step === '1')?.classList.add('is-active');
                    if (id > 0) stepEls.find(x => x.dataset.step === '2')?.classList.add('is-active');
                    if (subId > 0 && id > 0) stepEls.find(x => x.dataset.step === '3')?.classList.add('is-active');

                    if (!svc) {
                        const p = document.createElement('p');
                        p.style.cssText = 'margin:0;font-size:0.84rem;color:#64748b;';
                        p.textContent = 'Select a service card to load dynamic fields.';
                        box.appendChild(p);
                        return;
                    }

                    if (svc.requires_approval) {
                        meta.textContent = 'This service requires SPOC approval - it will move to pending after submit.';
                    } else {
                        meta.textContent = 'This service auto-approves on submit.';
                        wrapDel.style.display = 'block';
                    }

                    const isDuplicateNotAllowed = subId > 0 && !svc.allows_multiple && EXISTING_NON_MULTIPLE.has(subId + ':' + id);
                    if (isDuplicateNotAllowed) {
                        meta.textContent = 'This service is already assigned for selected incubatee.';
                        warning.textContent = 'Pick a different service or incubatee. Multiple is disabled for this service.';
                        warning.style.display = 'block';
                        const p = document.createElement('p');
                        p.style.cssText = 'margin:0;font-size:0.84rem;color:#b91c1c;font-weight:700;';
                        p.textContent = 'Cannot submit this combination.';
                        box.appendChild(p);
                        return;
                    }

                    if (svc.requires_document) {
                        wrapAtt.style.display = 'block';
                    }

                    const schema = svc.schema || [];
                    if (schema.length === 0) {
                        const p = document.createElement('p');
                        p.style.cssText = 'margin:0;font-size:0.82rem;color:#71717a;';
                        p.textContent = 'No extra fields configured for this service.';
                        box.appendChild(p);
                        return;
                    }

                    const fieldRows = {};
                    function getPayloadValue(key) {
                        const els = box.querySelectorAll('[name="payload[' + key + ']"], [name="payload[' + key + '][]"]');
                        if (!els.length) return '';
                        const first = els[0];
                        if (first.type === 'checkbox') {
                            return first.checked ? '1' : '0';
                        }
                        if (first.tagName === 'SELECT' && first.multiple) {
                            return Array.from(first.selectedOptions).map(o => o.value);
                        }
                        if (first.type === 'radio') {
                            const checked = Array.from(els).find(el => el.checked);
                            return checked ? checked.value : '';
                        }
                        return first.value || '';
                    }
                    function syncVisibility() {
                        schema.forEach(function (field) {
                            const row = fieldRows[field.key];
                            if (!row) return;
                            const cond = field.visible_if || null;
                            let show = true;
                            if (cond && cond.field && cond.value !== undefined) {
                                const depVal = getPayloadValue(cond.field);
                                if (Array.isArray(depVal)) {
                                    show = depVal.indexOf(String(cond.value)) >= 0;
                                } else {
                                    show = String(depVal) === String(cond.value);
                                }
                            }
                            row.style.display = show ? '' : 'none';
                            row.querySelectorAll('input,select,textarea').forEach(function (el) {
                                if (!show) {
                                    el.dataset.wasRequired = el.required ? '1' : '0';
                                    el.required = false;
                                    el.disabled = true;
                                } else {
                                    el.disabled = false;
                                    if (el.dataset.wasRequired === '1') {
                                        el.required = true;
                                    }
                                }
                            });
                        });
                    }

                    schema.forEach(function (field) {
                        const key = field.key;
                        const label = field.label || key;
                        const type = field.type || 'text';
                        const wrap = document.createElement('div');
                        fieldRows[key] = wrap;
                        const lb = document.createElement('label');
                        lb.style.cssText = 'display:block;font-size:0.82rem;font-weight:600;margin-bottom:0.2rem;';
                        lb.innerHTML = esc(label) + (field.required ? ' <span style="color:#b91c1c">*</span>' : '');
                        wrap.appendChild(lb);

                        if (field.help) {
                            const h = document.createElement('p');
                            h.style.cssText = 'margin:0 0 0.2rem;font-size:0.75rem;color:#71717a;';
                            h.textContent = field.help;
                            wrap.appendChild(h);
                        }

                        let input;
                        if (type === 'textarea') {
                            input = document.createElement('textarea');
                            input.name = 'payload[' + key + ']';
                            input.rows = 3;
                            input.className = 'svc-textarea';
                        } else if (type === 'select') {
                            input = document.createElement('select');
                            input.name = 'payload[' + key + ']';
                            input.className = 'svc-select';
                            const o0 = document.createElement('option');
                            o0.value = '';
                            o0.textContent = '—';
                            input.appendChild(o0);
                            (field.options || []).forEach(function (o) {
                                const opt = document.createElement('option');
                                opt.value = o.value;
                                opt.textContent = o.label || o.value;
                                input.appendChild(opt);
                            });
                        } else if (type === 'multiselect') {
                            input = document.createElement('select');
                            input.name = 'payload[' + key + '][]';
                            input.multiple = true;
                            input.size = Math.min(6, Math.max(3, (field.options || []).length));
                            input.className = 'svc-select';
                            (field.options || []).forEach(function (o) {
                                const opt = document.createElement('option');
                                opt.value = o.value;
                                opt.textContent = o.label || o.value;
                                input.appendChild(opt);
                            });
                        } else if (type === 'checkbox') {
                            const cb = document.createElement('input');
                            cb.type = 'checkbox';
                            cb.name = 'payload[' + key + ']';
                            cb.value = '1';
                            if (field.required) {
                                cb.required = true;
                            }
                            cb.style.marginRight = '0.35rem';
                            const span = document.createElement('span');
                            span.style.display = 'flex';
                            span.style.alignItems = 'center';
                            span.appendChild(cb);
                            span.appendChild(document.createTextNode(' Yes'));
                            wrap.appendChild(span);
                            box.appendChild(wrap);
                            return;
                        } else if (type === 'file') {
                            input = document.createElement('input');
                            input.type = 'file';
                            input.name = 'payload_files[' + key + ']';
                            input.accept = '.pdf,.jpg,.jpeg,.png,.webp,image/*,application/pdf';
                            input.className = 'svc-input';
                        } else if (type === 'radio') {
                            const optionsWrap = document.createElement('div');
                            optionsWrap.style.cssText = 'display:flex;flex-wrap:wrap;gap:0.8rem;';
                            (field.options || []).forEach(function (o, idx) {
                                const optLabel = document.createElement('label');
                                optLabel.style.cssText = 'display:inline-flex;align-items:center;gap:0.3rem;font-size:0.84rem;';
                                const radio = document.createElement('input');
                                radio.type = 'radio';
                                radio.name = 'payload[' + key + ']';
                                radio.value = o.value;
                                if (idx === 0 && field.required) {
                                    radio.required = true;
                                }
                                optLabel.appendChild(radio);
                                optLabel.appendChild(document.createTextNode(o.label || o.value));
                                optionsWrap.appendChild(optLabel);
                            });
                            wrap.appendChild(optionsWrap);
                            box.appendChild(wrap);
                            return;
                        } else {
                            input = document.createElement('input');
                            input.type = type === 'amount' || type === 'number' ? 'number' : (type === 'date' ? 'date' : (type === 'email' ? 'email' : (type === 'url' ? 'url' : (type === 'phone' ? 'tel' : 'text'))));
                            input.name = 'payload[' + key + ']';
                            input.className = 'svc-input';
                        }

                        if (field.required && input.type !== 'hidden') {
                            input.required = true;
                        }
                        wrap.appendChild(input);
                        box.appendChild(wrap);
                    });

                    box.addEventListener('input', syncVisibility);
                    box.addEventListener('change', syncVisibility);
                    syncVisibility();
                }

                function renderFilters() {
                    filtersWrap.innerHTML = categories.map(function (cat) {
                        const active = cat === selectedCategory ? ' is-active' : '';
                        return '<button type="button" class="svc-pill'+active+'" data-cat="'+esc(cat)+'">'+esc(cat)+'</button>';
                    }).join('');
                    filtersWrap.querySelectorAll('.svc-pill').forEach(function (btn) {
                        btn.addEventListener('click', function () {
                            selectedCategory = btn.dataset.cat || 'All';
                            renderFilters();
                            renderServiceCards();
                        });
                    });
                }

                function renderServiceCards() {
                    const subId = parseInt(selSub.value || '0', 10);
                    const term = (search.value || '').trim().toLowerCase();
                    const selectedId = getSelectedServiceId();
                    const rows = SERVICES.filter(function (svc) {
                        const inCat = selectedCategory === 'All' || String(svc.category_name || 'Other') === selectedCategory;
                        const inSearch = term === ''
                            || String(svc.name || '').toLowerCase().includes(term)
                            || String(svc.category_name || '').toLowerCase().includes(term);
                        return inCat && inSearch;
                    });
                    if (!rows.length) {
                        cardsWrap.innerHTML = '<p style="margin:.3rem 0;color:#64748b;font-size:.84rem;">No services found for current filter.</p>';
                        return;
                    });
                    cardsWrap.innerHTML = rows.map(function (svc) {
                        const id = parseInt(svc.id, 10);
                        const blocked = subId > 0 && !svc.allows_multiple && EXISTING_NON_MULTIPLE.has(subId + ':' + id);
                        const selected = selectedId === id;
                        const cls = 'svc-service' + (selected ? ' is-selected' : '') + (blocked ? ' is-disabled' : '');
                        const badges = [];
                        badges.push(svc.requires_approval
                            ? '<span class="svc-badge svc-badge--approval"><i class="fa-regular fa-clock"></i> SPOC approval</span>'
                            : '<span class="svc-badge svc-badge--auto"><i class="fa-solid fa-check"></i> Auto approve</span>');
                        if (svc.requires_document) badges.push('<span class="svc-badge svc-badge--doc"><i class="fa-regular fa-file-lines"></i> Docs required</span>');
                        badges.push(svc.allows_multiple
                            ? '<span class="svc-badge svc-badge--multi"><i class="fa-solid fa-repeat"></i> Multiple allowed</span>'
                            : '<span class="svc-badge svc-badge--single"><i class="fa-solid fa-lock"></i> One-time only</span>');
                        if (blocked) badges.push('<span class="svc-badge svc-badge--locked">Already assigned</span>');
                        return '<label class="'+cls+'" data-id="'+id+'" data-category="'+esc(String(svc.category_name || 'Other'))+'" data-name="'+esc(String(svc.name || '').toLowerCase())+'">'
                            + '<input type="radio" name="service_id" value="'+id+'" style="display:none;" '+(selected ? 'checked' : '')+' '+(blocked ? 'disabled' : '')+'>'
                            + '<p class="svc-service-sub">'+esc(String(svc.category_name || 'Other'))+'</p>'
                            + '<p class="svc-service-title">'+esc(String(svc.name || 'Service'))+'</p>'
                            + '<div class="svc-badges">'+badges.join('')+'</div>'
                            + '</label>';
                    }).join('');
                    cardsWrap.querySelectorAll('.svc-service').forEach(function (btn) {
                        btn.addEventListener('click', function () {
                            const id = parseInt(btn.dataset.id || '0', 10);
                            if (!id || btn.classList.contains('is-disabled')) return;
                            setSelectedServiceId(id);
                            renderServiceCards();
                            render();
                        });
                    });
                    cardsWrap.querySelectorAll('input[name="service_id"]').forEach(function (radio) {
                        radio.addEventListener('change', function () {
                            renderServiceCards();
                            render();
                        });
                    });
                }

                selSub.addEventListener('change', function () {
                    renderServiceCards();
                    render();
                });
                search.addEventListener('input', renderServiceCards);
                renderFilters();
                renderServiceCards();
                render();
            })();

            (function () {
                const form = document.getElementById('serviceSubmitForm');
                if (!form) return;
                const SERVICES = @json($servicesJson);
                const EXISTING_NON_MULTIPLE = new Set(@json($existingNonMultiplePairs ?? []));

                const overlay = document.getElementById('submitProgressOverlay');
                const textEl = document.getElementById('submitProgressText');
                const barEl = document.getElementById('submitProgressBar');
                const fxLayer = document.getElementById('submitFireworksLayer');
                let inFlight = false;
                const STEP_PLAN = [
                    { text: 'Checking your data...', pct: 22, ms: 2200 },
                    { text: 'Reading your document...', pct: 52, ms: 2400 },
                    { text: 'Assigning to SPOC for approval...', pct: 82, ms: 2600 },
                ];

                function burstFireworks() {
                    if (!fxLayer) return;
                    const colors = ['#22c55e', '#0ea5e9', '#f97316', '#a855f7', '#ef4444', '#eab308'];
                    const pieces = 56;
                    for (let i = 0; i < pieces; i++) {
                        const dot = document.createElement('span');
                        const size = Math.floor(Math.random() * 7) + 5;
                        const angle = Math.random() * Math.PI * 2;
                        const distance = 90 + Math.random() * 190;
                        const dx = Math.cos(angle) * distance;
                        const dy = Math.sin(angle) * distance;
                        dot.style.position = 'absolute';
                        dot.style.left = (44 + Math.random() * 12) + '%';
                        dot.style.top = (32 + Math.random() * 24) + '%';
                        dot.style.width = size + 'px';
                        dot.style.height = size + 'px';
                        dot.style.borderRadius = '999px';
                        dot.style.background = colors[Math.floor(Math.random() * colors.length)];
                        dot.style.opacity = '1';
                        dot.style.transform = 'translate(-50%, -50%)';
                        dot.style.transition = 'transform 3000ms cubic-bezier(.2,.75,.2,1), opacity 3000ms ease';
                        fxLayer.appendChild(dot);
                        requestAnimationFrame(() => {
                            dot.style.transform = 'translate(calc(-50% + ' + dx + 'px), calc(-50% + ' + dy + 'px))';
                            dot.style.opacity = '0';
                        });
                        setTimeout(() => dot.remove(), 3100);
                    }
                }

                function setProgress(pct) {
                    const safePct = Math.max(3, Math.min(100, Math.round(pct)));
                    if (barEl) barEl.style.width = safePct + '%';
                    if (!textEl) return;
                    if (safePct < 20) {
                        textEl.textContent = 'Checking your data...';
                    } else if (safePct < 60) {
                        textEl.textContent = 'Reading your document...';
                    } else if (safePct < 95) {
                        textEl.textContent = 'Assigning to SPOC for approval...';
                    } else if (safePct < 100) {
                        textEl.textContent = 'Finalizing submission...';
                    }
                }

                function resetSubmitButton() {
                    const submitBtn = form.querySelector('button[type="submit"]');
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.style.opacity = '';
                        submitBtn.style.cursor = '';
                    }
                }

                function duplicateBlockedSelection() {
                    const serviceEl = form.querySelector('input[name="service_id"]:checked');
                    const submissionEl = document.getElementById('cfa_submission_id');
                    if (!serviceEl || !submissionEl) return false;
                    const serviceId = parseInt(serviceEl.value || '0', 10);
                    const submissionId = parseInt(submissionEl.value || '0', 10);
                    if (!serviceId || !submissionId) return false;
                    const svc = SERVICES.find(function (x) { return x.id === serviceId; });
                    if (!svc || svc.allows_multiple) return false;

                    return EXISTING_NON_MULTIPLE.has(submissionId + ':' + serviceId);
                }

                function runStepPlan() {
                    return new Promise(function (resolve) {
                        let idx = 0;
                        function nextStep() {
                            if (idx >= STEP_PLAN.length) {
                                resolve();
                                return;
                            }
                            const step = STEP_PLAN[idx++];
                            if (textEl) textEl.textContent = step.text;
                            if (barEl) barEl.style.width = step.pct + '%';
                            setTimeout(nextStep, step.ms);
                        }
                        nextStep();
                    });
                }

                function selectedServiceIdFromForm() {
                    const selected = form.querySelector('input[name="service_id"]:checked');
                    return parseInt(selected?.value || '0', 10);
                }

                form.addEventListener('submit', async function (e) {
                    if (inFlight) return;
                    e.preventDefault();
                    if (!form.reportValidity()) {
                        return;
                    }
                    const selectedServiceId = selectedServiceIdFromForm();
                    if (!selectedServiceId) {
                        alert('Please select a service card before submitting.');
                        return;
                    }
                    if (duplicateBlockedSelection()) {
                        alert('This incubatee already has this service. Multiple cases are disabled for this service.');
                        return;
                    }
                    inFlight = true;

                    const submitBtn = form.querySelector('button[type="submit"]');
                    if (submitBtn) {
                        submitBtn.disabled = true;
                        submitBtn.style.opacity = '0.72';
                        submitBtn.style.cursor = 'not-allowed';
                    }
                    if (overlay) overlay.style.display = 'flex';
                    setProgress(8);

                    const xhr = new XMLHttpRequest();
                    xhr.open('POST', form.action, true);
                    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

                    xhr.upload.onprogress = function (ev) {
                        if (ev.lengthComputable && ev.total > 0) {
                            const uploadPct = (ev.loaded / ev.total) * 92;
                            setProgress(uploadPct);
                        } else {
                            setProgress(55);
                        }
                    };

                    xhr.onerror = function () {
                        if (textEl) textEl.textContent = 'Upload failed. Please try again.';
                        if (barEl) barEl.style.width = '100%';
                        if (overlay) overlay.style.display = 'none';
                        resetSubmitButton();
                        inFlight = false;
                    };

                    xhr.onload = function () {
                        const finalizeSuccess = function () {
                            setProgress(100);
                            if (textEl) textEl.textContent = 'Submitted successfully!';
                            burstFireworks();
                            const nextUrl = xhr.responseURL || @json(route('staff.services.index'));
                            setTimeout(function () {
                                window.location.assign(nextUrl);
                            }, 3000);
                        };
                        const finalizeError = function () {
                            if (textEl) {
                                textEl.textContent = 'Could not submit. Please check the form and retry.';
                            }
                            if (overlay) overlay.style.display = 'none';
                            resetSubmitButton();
                            inFlight = false;
                        };
                        const ok = xhr.status >= 200 && xhr.status < 400;
                        stepPlanDone.then(function () {
                            if (ok) finalizeSuccess();
                            else finalizeError();
                        });
                    };

                    const stepPlanDone = runStepPlan();
                    xhr.send(new FormData(form));
                });
            })();
        </script>
    @endif
@endsection
