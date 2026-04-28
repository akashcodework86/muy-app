@extends('layouts.admin')

@section('title', 'Edit service')
@section('heading', 'Edit service: '.$service->name)

@section('content')
    @php $schemaEditMode = false; @endphp
    @if (session('status'))
        <p style="color:#166534; margin:0 0 0.75rem;">{{ session('status') }}</p>
    @endif
    @if ($errors->any())
        <ul style="color:#b91c1c; margin:0 0 0.85rem; padding-left:1.15rem; font-size:0.85rem;">
            @foreach ($errors->all() as $e)
                <li>{{ $e }}</li>
            @endforeach
        </ul>
    @endif

    <p style="margin-bottom:1rem;"><a href="{{ route('admin.service-catalog.index') }}">← Service catalog</a></p>

    @if (($canonicalSchemaCount ?? 0) === 0 && !empty($recoveredSchema))
        <div style="margin:0 0 1rem; padding:0.75rem 0.9rem; border:1px solid #fde68a; background:#fffbeb; border-radius:8px;">
            <div style="font-weight:700; color:#92400e; margin-bottom:0.35rem;">Schema missing in service master</div>
            <p style="margin:0 0 0.55rem; font-size:0.83rem; color:#78350f;">
                Found <strong>{{ count($recoveredSchema) }}</strong> field(s) from recent submitted cases for this service.
                Import them to restore edit preview.
            </p>
            <form method="post" action="{{ route('admin.service-catalog.services.recover-schema', $service) }}">
                @csrf
                <button type="submit" style="background:#92400e; color:#fff; border:none; padding:0.45rem 0.75rem; border-radius:6px; font-weight:600; cursor:pointer;">
                    Import recovered fields
                </button>
            </form>
        </div>
    @endif

    <form method="post" action="{{ route('admin.service-catalog.services.update', $service) }}" style="max-width:40rem;">
        @csrf
        @method('PUT')

        <div style="margin-bottom:0.85rem;">
            <label for="service_category_id" style="display:block; font-weight:500; margin-bottom:0.25rem;">Category</label>
            <select id="service_category_id" name="service_category_id" required style="width:100%; padding:0.45rem 0.5rem; border:1px solid #d4d4d8; border-radius:6px;">
                @foreach ($categories as $cat)
                    <option value="{{ $cat->id }}" @selected((int) old('service_category_id', $service->service_category_id) === (int) $cat->id)>
                        {{ $cat->name }}
                    </option>
                @endforeach
            </select>
            @error('service_category_id')<div style="color:#b91c1c;font-size:0.85rem;margin-top:0.25rem;">{{ $message }}</div>@enderror
        </div>

        <div style="margin-bottom:0.85rem;">
            <label for="name" style="display:block; font-weight:500; margin-bottom:0.25rem;">Display name</label>
            <input id="name" name="name" type="text" value="{{ old('name', $service->name) }}" required maxlength="191" style="width:100%; padding:0.45rem 0.5rem; border:1px solid #d4d4d8; border-radius:6px;">
        </div>

        <div style="margin-bottom:0.85rem;">
            <label for="code" style="display:block; font-weight:500; margin-bottom:0.25rem;">Code</label>
            <input id="code" name="code" type="text" value="{{ old('code', $service->code) }}" required pattern="[a-z0-9_]+" maxlength="96" style="width:100%; padding:0.45rem 0.5rem; border:1px solid #d4d4d8; border-radius:6px;">
            @error('code')<div style="color:#b91c1c;font-size:0.85rem;margin-top:0.25rem;">{{ $message }}</div>@enderror
        </div>

        <div style="margin-bottom:0.85rem;">
            <label for="deliverable_id" style="display:block; font-weight:500; margin-bottom:0.25rem;">MIS deliverable roll-up <span style="font-weight:400;color:#71717a;">(optional)</span></label>
            <select id="deliverable_id" name="deliverable_id" style="width:100%; padding:0.45rem 0.5rem; border:1px solid #d4d4d8; border-radius:6px;">
                <option value="">— None —</option>
                @foreach ($deliverables as $d)
                    <option value="{{ $d->id }}" @selected((int) old('deliverable_id', $service->deliverable_id) === (int) $d->id)>{{ $d->name }} ({{ $d->code }})</option>
                @endforeach
            </select>
        </div>

        <div style="margin-bottom:0.85rem;">
            <label for="sort_order" style="display:block; font-weight:500; margin-bottom:0.25rem;">Sort order</label>
            <input id="sort_order" name="sort_order" type="number" min="0" value="{{ old('sort_order', $service->sort_order) }}" style="width:8rem; padding:0.45rem 0.5rem; border:1px solid #d4d4d8; border-radius:6px;">
        </div>

        <fieldset style="margin:0 0 1rem; padding:0.75rem 0.9rem; border:1px solid #e4e4e7; border-radius:8px;">
            <legend style="padding:0 0.4rem; font-size:0.85rem; font-weight:600; color:#14532d;">Estimated market price (impact)</legend>
            <p style="margin:0 0 0.6rem; font-size:0.78rem; color:#6b7280;">
                Savings is calculated as approved service cases × average market price.
            </p>
            <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(10rem,1fr)); gap:0.65rem; margin-bottom:0.65rem;">
                <div>
                    <label for="estimated_market_price_avg" style="display:block; font-weight:500; margin-bottom:0.25rem;">Average price (INR)</label>
                    <input id="estimated_market_price_avg" name="estimated_market_price_avg" type="number" min="0" step="0.01" value="{{ old('estimated_market_price_avg', $service->estimated_market_price_avg) }}" placeholder="e.g. 1200" style="width:100%; padding:0.45rem 0.5rem; border:1px solid #d4d4d8; border-radius:6px;">
                    @error('estimated_market_price_avg')<div style="color:#b91c1c;font-size:0.8rem;margin-top:0.2rem;">{{ $message }}</div>@enderror
                </div>
                <div>
                    <label for="estimated_market_price_min" style="display:block; font-weight:500; margin-bottom:0.25rem;">Minimum price (INR)</label>
                    <input id="estimated_market_price_min" name="estimated_market_price_min" type="number" min="0" step="0.01" value="{{ old('estimated_market_price_min', $service->estimated_market_price_min) }}" placeholder="optional" style="width:100%; padding:0.45rem 0.5rem; border:1px solid #d4d4d8; border-radius:6px;">
                    @error('estimated_market_price_min')<div style="color:#b91c1c;font-size:0.8rem;margin-top:0.2rem;">{{ $message }}</div>@enderror
                </div>
                <div>
                    <label for="estimated_market_price_max" style="display:block; font-weight:500; margin-bottom:0.25rem;">Maximum price (INR)</label>
                    <input id="estimated_market_price_max" name="estimated_market_price_max" type="number" min="0" step="0.01" value="{{ old('estimated_market_price_max', $service->estimated_market_price_max) }}" placeholder="optional" style="width:100%; padding:0.45rem 0.5rem; border:1px solid #d4d4d8; border-radius:6px;">
                    @error('estimated_market_price_max')<div style="color:#b91c1c;font-size:0.8rem;margin-top:0.2rem;">{{ $message }}</div>@enderror
                </div>
            </div>
            <div>
                <label for="market_price_basis_note" style="display:block; font-weight:500; margin-bottom:0.25rem;">Price basis / source note <span style="font-weight:400;color:#71717a;">(optional)</span></label>
                <textarea id="market_price_basis_note" name="market_price_basis_note" rows="2" maxlength="1000" placeholder="Example: CA consultant market range in district offices, Apr 2026." style="width:100%; padding:0.45rem 0.5rem; border:1px solid #d4d4d8; border-radius:6px;">{{ old('market_price_basis_note', $service->market_price_basis_note) }}</textarea>
                @error('market_price_basis_note')<div style="color:#b91c1c;font-size:0.8rem;margin-top:0.2rem;">{{ $message }}</div>@enderror
            </div>
        </fieldset>

        <div style="margin-bottom:0.85rem;">
            <label for="reporting_tier" style="display:block; font-weight:500; margin-bottom:0.25rem;">Reporting — Key / Non‑Key</label>
            <select id="reporting_tier" name="reporting_tier" required style="width:100%; max-width:24rem; padding:0.45rem 0.5rem; border:1px solid #d4d4d8; border-radius:6px;">
                @php $rt = old('reporting_tier', $service->reporting_tier ?? 'unset'); @endphp
                <option value="unset" @selected($rt === 'unset')>Unset (reports group with Non‑Key until you choose)</option>
                <option value="key" @selected($rt === 'key')>Key</option>
                <option value="non_key" @selected($rt === 'non_key')>Non‑Key</option>
            </select>
            <p style="font-size:0.75rem; color:#71717a; margin:0.25rem 0 0;">For MIS / exports only — does not change workflow.</p>
            @error('reporting_tier')<div style="color:#b91c1c;font-size:0.85rem;margin-top:0.25rem;">{{ $message }}</div>@enderror
        </div>

        <div style="margin-bottom:0.85rem; display:flex; flex-wrap:wrap; gap:1.25rem;">
            <label style="display:flex; align-items:center; gap:0.35rem; cursor:pointer;">
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $service->is_active))> Active
            </label>
            <label style="display:flex; align-items:center; gap:0.35rem; cursor:pointer;">
                <input type="hidden" name="allows_multiple" value="0">
                <input type="checkbox" name="allows_multiple" value="1" @checked(old('allows_multiple', $service->allows_multiple))> Allow multiple cases per incubatee
            </label>
        </div>

        <fieldset style="margin:0 0 1rem; padding:0.75rem 0.9rem; border:1px solid #e4e4e7; border-radius:8px;">
            <legend style="padding:0 0.4rem; font-size:0.85rem; font-weight:600; color:#3730a3;">Maker–checker &amp; documents</legend>
            <label style="display:flex; align-items:flex-start; gap:0.45rem; cursor:pointer; margin:0.35rem 0;">
                <input type="hidden" name="requires_approval" value="0">
                <input type="checkbox" id="requires_approval" name="requires_approval" value="1" @checked(old('requires_approval', $service->requires_approval))>
                <span>
                    <strong>Requires SPOC approval</strong> (maker–checker)
                    <div style="font-size:0.78rem; color:#71717a;">When on, staff-submitted cases sit in <em>pending approval</em> with the district's SPOC until they approve, send back, or reject. When off, cases auto-approve on submission.</div>
                    <div style="font-size:0.75rem; color:#b45309; margin-top:0.2rem;">Note: changes apply to <em>new</em> cases. Cases already in flight finish under the rule in force when they were created.</div>
                </span>
            </label>
            <label style="display:flex; align-items:flex-start; gap:0.45rem; cursor:pointer; margin:0.35rem 0;">
                <input type="hidden" name="requires_document" value="0">
                <input type="checkbox" id="requires_document" name="requires_document" value="1" @checked(old('requires_document', $service->requires_document))>
                <span>
                    <strong>Requires document upload</strong>
                    <div style="font-size:0.78rem; color:#71717a;">Staff must attach proof (certificate, acknowledgement, screenshot, etc.) when submitting a case.</div>
                </span>
            </label>
            @php
                $currentTypes = old('allowed_document_types', $service->allowed_document_types ?: ['pdf', 'image']);
                $currentTypes = is_array($currentTypes) ? $currentTypes : ['pdf', 'image'];
            @endphp
            <div id="allowed_doc_types_wrap" style="margin-left:1.5rem; margin-top:0.35rem; display:flex; flex-wrap:wrap; gap:1rem; font-size:0.85rem;">
                <span style="color:#52525b; font-size:0.78rem;">Allowed file types:</span>
                <label style="display:flex; align-items:center; gap:0.3rem; cursor:pointer;">
                    <input type="checkbox" name="allowed_document_types[]" value="pdf" @checked(in_array('pdf', $currentTypes, true))> PDF
                </label>
                <label style="display:flex; align-items:center; gap:0.3rem; cursor:pointer;">
                    <input type="checkbox" name="allowed_document_types[]" value="image" @checked(in_array('image', $currentTypes, true))> Image (jpg/png)
                </label>
            </div>
        </fieldset>

        @php
            $schemaResolved = is_array($schemaInitial ?? null) ? $schemaInitial : [];
            if (request()->session()->hasOldInput('field_schema')) {
                $decoded = json_decode((string) old('field_schema'), true);
                if (is_array($decoded) && $decoded !== []) {
                    $schemaResolved = \App\Support\ServiceFieldTypes::normalizeSchema($decoded);
                }
            }
            if ($schemaResolved === []) {
                $schemaResolved = \App\Support\ServiceFieldTypes::normalizeSchema($service->field_schema);
            }
            if ($schemaResolved === [] && (string) $service->code === 'udyam_registration') {
                $schemaResolved = [
                    [
                        'key' => 'registration_number',
                        'label' => 'Registration Number',
                        'type' => \App\Support\ServiceFieldTypes::TEXT,
                        'required' => true,
                    ],
                    [
                        'key' => 'remark',
                        'label' => 'Remark',
                        'type' => \App\Support\ServiceFieldTypes::TEXTAREA,
                        'required' => false,
                    ],
                ];
            }
            $schemaInitial = $schemaResolved;
        @endphp
        <div id="service-schema-editor-anchor"></div>
        @include('partials.admin-service-schema-builder', ['schemaEditMode' => $schemaEditMode])
        @error('field_schema')<div style="color:#b91c1c;font-size:0.85rem;margin-top:0.25rem;">{{ $message }}</div>@enderror

        <fieldset style="margin:0 0 1rem; padding:0.75rem 0.9rem; border:1px solid #e4e4e7; border-radius:8px; background:#fcfcff;">
            <legend style="padding:0 0.4rem; font-size:0.85rem; font-weight:600; color:#1f2937;">Live preview (staff submit form)</legend>
            <p style="font-size:0.78rem; color:#71717a; margin:0 0 0.6rem;">This is how the form will look to staff. A field appears here only after you enter both <strong>Field ID</strong> and <strong>Label</strong>.</p>
            <div id="svc_preview_fields" style="display:flex; flex-direction:column; gap:0.6rem;"></div>
            <div id="svc_preview_doc" style="margin-top:0.6rem; display:none;">
                <label style="display:block; font-size:0.85rem; font-weight:600; margin-bottom:0.25rem;">Documents <span style="font-weight:400;color:#71717a;">(required)</span></label>
                <input type="file" disabled style="padding:0.35rem 0.45rem;border:1px solid #d4d4d8;border-radius:6px;background:#fff;">
                <p style="font-size:0.75rem; color:#71717a; margin:0.25rem 0 0;">Allowed: <span id="svc_preview_doc_types">PDF / Image</span></p>
            </div>
        </fieldset>

        <button type="submit" style="background:#18181b; color:#fff; border:none; padding:0.5rem 1rem; border-radius:6px; font-weight:500;">Update service</button>
    </form>

    <script>
        (function () {
            const reqDoc = document.getElementById('requires_document');
            const wrap = document.getElementById('allowed_doc_types_wrap');
            if (!reqDoc || !wrap) return;
            const sync = () => {
                wrap.style.opacity = reqDoc.checked ? '1' : '0.45';
                wrap.querySelectorAll('input[type="checkbox"]').forEach(cb => cb.disabled = !reqDoc.checked);
            };
            sync();
            reqDoc.addEventListener('change', sync);
        })();
    </script>
    <script>
        (function () {
            const schemaEditMode = @json($schemaEditMode);
            const hidden = document.getElementById('svc_schema_hidden');
            const docReq = document.getElementById('requires_document');
            const docWrap = document.getElementById('svc_preview_doc');
            const docTypesWrap = document.getElementById('svc_preview_doc_types');
            const fieldsWrap = document.getElementById('svc_preview_fields');
            if (!hidden || !fieldsWrap) return;

            function esc(s) {
                return String(s || '')
                    .replaceAll('&', '&amp;')
                    .replaceAll('<', '&lt;')
                    .replaceAll('>', '&gt;');
            }

            function renderFields() {
                let rows = [];
                try {
                    const parsed = JSON.parse(hidden.value || '[]');
                    rows = Array.isArray(parsed) ? parsed : [];
                } catch (e) {
                    rows = [];
                }
                const valid = rows.filter(r => r && r.key && r.label);
                if (!valid.length) {
                    fieldsWrap.innerHTML = '<p style="margin:0; font-size:0.82rem; color:#6b7280;">No fields added yet. Click <strong>+ Add field</strong>, then fill <strong>Field ID</strong> and <strong>Label</strong>.</p>';
                } else {
                    fieldsWrap.innerHTML = valid.map(function (r) {
                        const label = esc(r.label);
                        const req = r.required ? ' <span style="color:#b91c1c;">*</span>' : '';
                        const type = String(r.type || 'text');
                        let control = '<input type="text" disabled style="width:100%;padding:0.4rem 0.5rem;border:1px solid #d4d4d8;border-radius:6px;background:#fff;">';
                        if (type === 'textarea') {
                            control = '<textarea disabled rows="2" style="width:100%;padding:0.4rem 0.5rem;border:1px solid #d4d4d8;border-radius:6px;background:#fff;"></textarea>';
                        } else if (type === 'number') {
                            control = '<input type="number" disabled style="width:12rem;padding:0.4rem 0.5rem;border:1px solid #d4d4d8;border-radius:6px;background:#fff;">';
                        } else if (type === 'date') {
                            control = '<input type="date" disabled style="padding:0.4rem 0.5rem;border:1px solid #d4d4d8;border-radius:6px;background:#fff;">';
                        } else if (type === 'select' || type === 'radio') {
                            const opts = Array.isArray(r.options) ? r.options : [];
                            const optionHtml = opts.map(function (o) {
                                const txt = esc(o.label || o.value || '');
                                return '<option>' + txt + '</option>';
                            }).join('');
                            control = '<select disabled style="width:100%;padding:0.4rem 0.5rem;border:1px solid #d4d4d8;border-radius:6px;background:#fff;"><option>— Select —</option>' + optionHtml + '</select>';
                        } else if (type === 'checkbox') {
                            control = '<label style="display:inline-flex;align-items:center;gap:0.35rem;"><input type="checkbox" disabled> <span style="color:#6b7280;">Option</span></label>';
                        } else if (type === 'file') {
                            control = '<input type="file" disabled style="width:100%;padding:0.35rem 0.45rem;border:1px solid #d4d4d8;border-radius:6px;background:#fff;font-size:0.82rem;">';
                        }
                        const help = r.help ? '<p style="margin:0.2rem 0 0;font-size:0.74rem;color:#71717a;">' + esc(r.help) + '</p>' : '';
                        return '<div><label style="display:block;font-size:0.84rem;font-weight:600;margin-bottom:0.2rem;">' + label + req + '</label>' + control + help + '</div>';
                    }).join('');
                }
            }

            function renderDoc() {
                if (!docReq || !docWrap || !docTypesWrap) return;
                docWrap.style.display = docReq.checked ? 'block' : 'none';
                const checked = Array.from(document.querySelectorAll('input[name="allowed_document_types[]"]:checked')).map(i => i.value);
                const labels = [];
                if (checked.includes('pdf')) labels.push('PDF');
                if (checked.includes('image')) labels.push('Image (jpg/png)');
                docTypesWrap.textContent = labels.length ? labels.join(' / ') : 'PDF / Image';
            }

            renderFields();
            renderDoc();
            hidden.addEventListener('input', renderFields);
            hidden.addEventListener('change', renderFields);
            document.addEventListener('input', function (e) {
                if (e.target && (e.target.id === 'requires_document' || e.target.name === 'allowed_document_types[]')) {
                    renderDoc();
                }
            });
            document.addEventListener('change', function (e) {
                if (e.target && (e.target.id === 'requires_document' || e.target.name === 'allowed_document_types[]')) {
                    renderDoc();
                }
            });
            setInterval(function () {
                renderFields();
                renderDoc();
            }, 800);

            // Keep edit page stable; no forced schema mode from query params.
        })();
    </script>
@endsection
