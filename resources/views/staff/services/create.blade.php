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
        @if ($errors->any())
            <ul style="color:#b91c1c;margin:0 0 0.75rem;padding-left:1.2rem;font-size:0.88rem;">
                @foreach ($errors->all() as $e)
                    <li>{{ $e }}</li>
                @endforeach
            </ul>
        @endif

        <form id="serviceSubmitForm" method="post" action="{{ route('staff.services.store') }}" enctype="multipart/form-data" style="max-width:42rem;">
            @csrf

            <div style="margin-bottom:0.85rem;">
                <label for="cfa_submission_id" style="display:block;font-weight:600;margin-bottom:0.25rem;font-size:0.9rem;">Incubatee (CFA)</label>
                <select id="cfa_submission_id" name="cfa_submission_id" required style="width:100%;padding:0.45rem 0.5rem;border:1px solid #d4d4d8;border-radius:6px;">
                    <option value="">— Select —</option>
                    @foreach ($submissions as $sub)
                        <option value="{{ $sub->id }}" @selected((int) old('cfa_submission_id', (int) ($defaultCfaSubmissionId ?? 0)) === (int) $sub->id)>
                            {{ $sub->applicant_name }} @if ($sub->application_no) · {{ $sub->application_no }} @endif
                        </option>
                    @endforeach
                </select>
            </div>

            <div style="margin-bottom:0.85rem;">
                <label for="service_id" style="display:block;font-weight:600;margin-bottom:0.25rem;font-size:0.9rem;">Service</label>
                <select id="service_id" name="service_id" required style="width:100%;padding:0.45rem 0.5rem;border:1px solid #d4d4d8;border-radius:6px;">
                    <option value="">— Select —</option>
                    @foreach ($services as $svc)
                        <option value="{{ $svc->id }}" @selected((int) old('service_id') === (int) $svc->id)>
                            {{ $svc->category?->name ?? '?' }} — {{ $svc->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <p id="svc_meta" style="font-size:0.8rem;color:#52525b;margin:0 0 0.75rem;"></p>

            <div id="wrap_delivered" style="margin-bottom:0.85rem;display:none;">
                <label for="delivered_on" style="display:block;font-weight:600;margin-bottom:0.25rem;font-size:0.9rem;">Delivered on</label>
                <input type="date" id="delivered_on" name="delivered_on" value="{{ old('delivered_on') }}" style="padding:0.45rem 0.5rem;border:1px solid #d4d4d8;border-radius:6px;">
                <p style="font-size:0.75rem;color:#71717a;margin:0.25rem 0 0;">Shown for services that <strong>auto-approve</strong> (no SPOC). Defaults to today if left blank.</p>
            </div>

            <div style="margin-bottom:0.85rem;">
                <label for="reference_number" style="display:block;font-weight:600;margin-bottom:0.25rem;font-size:0.9rem;">Reference / certificate no. <span style="font-weight:400;color:#71717a;">(optional)</span></label>
                <input type="text" id="reference_number" name="reference_number" value="{{ old('reference_number') }}" maxlength="191" style="width:100%;padding:0.45rem 0.5rem;border:1px solid #d4d4d8;border-radius:6px;">
            </div>

            <fieldset style="margin:0 0 1rem;padding:0.75rem 0.9rem;border:1px solid #e4e4e7;border-radius:8px;">
                <legend style="font-size:0.85rem;font-weight:600;">Service details</legend>
                <div id="schema_fields" style="display:flex;flex-direction:column;gap:0.65rem;">
                    <p style="margin:0;font-size:0.82rem;color:#71717a;">Select a service to load its form fields.</p>
                </div>
            </fieldset>

            <div id="wrap_attach" style="margin-bottom:0.85rem;display:none;">
                <label style="display:block;font-weight:600;margin-bottom:0.25rem;font-size:0.9rem;">Documents <span style="font-weight:400;color:#71717a;">(max 3, PDF or image, 5 MB each)</span></label>
                <input type="file" name="attachments[]" multiple accept=".pdf,.jpg,.jpeg,.png,.webp,image/*,application/pdf" style="font-size:0.85rem;">
            </div>

            <button type="submit" style="background:#18181b;color:#fff;border:none;padding:0.55rem 1.1rem;border-radius:8px;font-weight:600;cursor:pointer;">Submit</button>
        </form>

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

                const selSvc = document.getElementById('service_id');
                const selSub = document.getElementById('cfa_submission_id');
                const meta = document.getElementById('svc_meta');
                const wrapDel = document.getElementById('wrap_delivered');
                const wrapAtt = document.getElementById('wrap_attach');
                const box = document.getElementById('schema_fields');
                const serviceById = new Map(SERVICES.map(function (s) { return [parseInt(s.id, 10), s]; }));

                function esc(s) {
                    const d = document.createElement('div');
                    d.textContent = s;
                    return d.innerHTML;
                }

                function render() {
                    const id = parseInt(selSvc.value || '0', 10);
                    const subId = parseInt(selSub.value || '0', 10);
                    const svc = SERVICES.find(function (x) { return x.id === id; });
                    box.innerHTML = '';
                    meta.textContent = '';
                    wrapDel.style.display = 'none';
                    wrapAtt.style.display = 'none';

                    if (!svc) {
                        const p = document.createElement('p');
                        p.style.cssText = 'margin:0;font-size:0.82rem;color:#71717a;';
                        p.textContent = 'Select a service to load its form fields.';
                        box.appendChild(p);
                        return;
                    }

                    if (svc.requires_approval) {
                        meta.textContent = 'This service requires SPOC approval — it will go to pending after submit.';
                    } else {
                        meta.textContent = 'This service auto-approves on submit.';
                        wrapDel.style.display = 'block';
                    }

                    const isDuplicateNotAllowed = subId > 0 && !svc.allows_multiple && EXISTING_NON_MULTIPLE.has(subId + ':' + id);
                    if (isDuplicateNotAllowed) {
                        meta.textContent = 'This incubatee already has this service. Multiple cases are disabled for this service.';
                        const p = document.createElement('p');
                        p.style.cssText = 'margin:0;font-size:0.84rem;color:#b91c1c;font-weight:600;';
                        p.textContent = 'Pick a different service or incubatee.';
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
                            input.style.cssText = 'width:100%;padding:0.4rem 0.5rem;border:1px solid #d4d4d8;border-radius:6px;font-size:0.85rem;';
                        } else if (type === 'select') {
                            input = document.createElement('select');
                            input.name = 'payload[' + key + ']';
                            input.style.cssText = 'width:100%;padding:0.4rem 0.5rem;border:1px solid #d4d4d8;border-radius:6px;';
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
                            input.style.cssText = 'width:100%;padding:0.4rem 0.5rem;border:1px solid #d4d4d8;border-radius:6px;';
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
                            input.style.cssText = 'width:100%;padding:0.35rem 0.45rem;border:1px solid #d4d4d8;border-radius:6px;background:#fff;font-size:0.82rem;';
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
                            input.style.cssText = 'width:100%;padding:0.4rem 0.5rem;border:1px solid #d4d4d8;border-radius:6px;font-size:0.85rem;';
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

                function refreshServiceOptionLocks() {
                    const subId = parseInt(selSub.value || '0', 10);
                    const opts = Array.from(selSvc.options || []);
                    opts.forEach(function (opt) {
                        const serviceId = parseInt(opt.value || '0', 10);
                        if (!serviceId) return;
                        const svc = serviceById.get(serviceId);
                        if (!svc) return;
                        if (!opt.dataset.baseLabel) {
                            opt.dataset.baseLabel = opt.textContent;
                        }
                        const blocked = subId > 0 && !svc.allows_multiple && EXISTING_NON_MULTIPLE.has(subId + ':' + serviceId);
                        opt.disabled = blocked;
                        opt.textContent = blocked
                            ? (opt.dataset.baseLabel + ' (Already assigned)')
                            : opt.dataset.baseLabel;
                    });

                    const currentServiceId = parseInt(selSvc.value || '0', 10);
                    if (currentServiceId > 0) {
                        const currentOpt = selSvc.querySelector('option[value="' + currentServiceId + '"]');
                        if (currentOpt && currentOpt.disabled) {
                            selSvc.value = '';
                        }
                    }
                }

                selSvc.addEventListener('change', render);
                selSub.addEventListener('change', function () {
                    refreshServiceOptionLocks();
                    render();
                });
                refreshServiceOptionLocks();
                render();
            })();

            (function () {
                const form = document.getElementById('serviceSubmitForm');
                if (!form) return;

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
                    const serviceEl = document.getElementById('service_id');
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

                form.addEventListener('submit', async function (e) {
                    if (inFlight) return;
                    e.preventDefault();
                    if (!form.reportValidity()) {
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
