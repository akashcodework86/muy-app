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

        <form method="post" action="{{ route('staff.services.store') }}" enctype="multipart/form-data" style="max-width:42rem;">
            @csrf

            <div style="margin-bottom:0.85rem;">
                <label for="cfa_submission_id" style="display:block;font-weight:600;margin-bottom:0.25rem;font-size:0.9rem;">Incubatee (CFA)</label>
                <select id="cfa_submission_id" name="cfa_submission_id" required style="width:100%;padding:0.45rem 0.5rem;border:1px solid #d4d4d8;border-radius:6px;">
                    <option value="">— Select —</option>
                    @foreach ($submissions as $sub)
                        <option value="{{ $sub->id }}" @selected((int) old('cfa_submission_id') === (int) $sub->id)>
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
                            {{ $svc->category?->parent?->name ?? '?' }} → {{ $svc->category?->name ?? '?' }} — {{ $svc->name }}
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

        <script>
            (function () {
                const SERVICES = @json($servicesJson);

                const selSvc = document.getElementById('service_id');
                const meta = document.getElementById('svc_meta');
                const wrapDel = document.getElementById('wrap_delivered');
                const wrapAtt = document.getElementById('wrap_attach');
                const box = document.getElementById('schema_fields');

                function esc(s) {
                    const d = document.createElement('div');
                    d.textContent = s;
                    return d.innerHTML;
                }

                function render() {
                    const id = parseInt(selSvc.value || '0', 10);
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

                    schema.forEach(function (field) {
                        const key = field.key;
                        const label = field.label || key;
                        const type = field.type || 'text';
                        const wrap = document.createElement('div');
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
                }

                selSvc.addEventListener('change', render);
                render();
            })();
        </script>
    @endif
@endsection
