@extends('layouts.admin')

@section('title', 'Edit service — '.$service->name)
@section('heading', 'Edit service')

@push('styles')
<style>
/* ── Page layout ──────────────────────────────────────────────────────────── */
.se-grid {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 21rem;
    gap: 1.75rem;
    align-items: start;
}

/* ── Shared form base ─────────────────────────────────────────────────────── */
.se-field { margin-bottom: 1rem; }
.se-field:last-child { margin-bottom: 0; }

.se-label {
    display: block;
    font-size: 0.83rem;
    font-weight: 600;
    color: #374151;
    margin-bottom: 0.3rem;
}
.se-label-note {
    font-weight: 400;
    color: #9ca3af;
    font-size: 0.77rem;
}
.se-hint {
    font-size: 0.74rem;
    color: #9ca3af;
    margin: 0.2rem 0 0;
}
.se-err {
    font-size: 0.78rem;
    color: #dc2626;
    margin: 0.2rem 0 0;
}
.se-req { color: #dc2626; }

/* ── Input elements ───────────────────────────────────────────────────────── */
.se-input, .se-select, .se-textarea {
    display: block;
    width: 100%;
    max-width: 100%;
    padding: 0.46rem 0.6rem;
    font-size: 0.875rem;
    line-height: 1.4;
    color: #111827;
    background: #fff;
    border: 1px solid #d1d5db;
    border-radius: 7px;
    box-sizing: border-box;
    transition: border-color 0.15s, box-shadow 0.15s;
    outline: none;
}
.se-input:focus, .se-select:focus, .se-textarea:focus {
    border-color: #6366f1;
    box-shadow: 0 0 0 3px rgba(99,102,241,.12);
}
.se-input.is-err, .se-select.is-err, .se-textarea.is-err {
    border-color: #f87171;
}
.se-input.is-err:focus, .se-select.is-err:focus, .se-textarea.is-err:focus {
    box-shadow: 0 0 0 3px rgba(239,68,68,.12);
}
.se-input--code {
    font-family: ui-monospace, 'Cascadia Code', 'Fira Code', monospace;
    font-size: 0.84rem;
    background: #f9fafb;
    letter-spacing: 0.01em;
    max-width: 26rem;
}
.se-input--short { max-width: 9rem; }
.se-input--mid   { max-width: 14rem; }
.se-select--mid  { max-width: 22rem; }
.se-textarea { resize: vertical; }

/* ── Grid rows for side-by-side fields ───────────────────────────────────── */
.se-row-2 {
    display: grid;
    grid-template-columns: 1fr 7rem;
    gap: 0.75rem;
}
.se-row-3 {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 0.65rem;
}

/* ── Sections (cards) ─────────────────────────────────────────────────────── */
.se-card {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 10px;
    padding: 1rem 1.15rem;
    margin-bottom: 1.25rem;
}
.se-card:last-of-type { margin-bottom: 0; }
.se-card-title {
    font-size: 0.75rem;
    font-weight: 700;
    letter-spacing: 0.07em;
    text-transform: uppercase;
    padding-bottom: 0.55rem;
    margin: 0 0 1rem;
    border-bottom: 1px solid #f3f4f6;
}
.se-card-title--blue   { color: #4f46e5; border-color: #e0e7ff; }
.se-card-title--green  { color: #16a34a; border-color: #dcfce7; }
.se-card-title--amber  { color: #b45309; border-color: #fef3c7; }
.se-card-title--slate  { color: #475569; border-color: #f1f5f9; }

.se-card-desc {
    font-size: 0.78rem;
    color: #6b7280;
    margin: -0.5rem 0 0.9rem;
}

/* ── Checkboxes (toggle rows) ─────────────────────────────────────────────── */
.se-toggle-row {
    display: flex;
    flex-wrap: wrap;
    gap: 1.25rem;
    margin-bottom: 0.9rem;
}
.se-toggle {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    cursor: pointer;
    font-size: 0.875rem;
    font-weight: 500;
    color: #374151;
    user-select: none;
}
.se-toggle input[type="checkbox"] {
    width: 1rem;
    height: 1rem;
    cursor: pointer;
    accent-color: #4f46e5;
    flex-shrink: 0;
}

/* ── Inset sub-panel (maker-checker) ─────────────────────────────────────── */
.se-subpanel {
    background: #f8f9ff;
    border: 1px solid #e0e7ff;
    border-radius: 8px;
    padding: 0.85rem 1rem;
}
.se-subpanel-title {
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: #4f46e5;
    margin: 0 0 0.7rem;
}
.se-check-row {
    display: flex;
    align-items: flex-start;
    gap: 0.55rem;
    margin-bottom: 0.75rem;
    cursor: pointer;
}
.se-check-row:last-of-type { margin-bottom: 0; }
.se-check-row input[type="checkbox"] {
    margin-top: 0.18rem;
    width: 1rem;
    height: 1rem;
    flex-shrink: 0;
    cursor: pointer;
    accent-color: #4f46e5;
}
.se-check-row strong { font-size: 0.875rem; color: #1f2937; }
.se-check-desc { display: block; font-size: 0.77rem; color: #6b7280; margin-top: 0.12rem; }
.se-check-note { display: block; font-size: 0.73rem; color: #b45309; margin-top: 0.1rem; }

/* ── Doc-type sub-row ─────────────────────────────────────────────────────── */
.se-doctype-row {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.85rem;
    margin-top: 0.5rem;
    margin-left: 1.55rem;
    font-size: 0.83rem;
}
.se-doctype-label {
    font-size: 0.75rem;
    font-weight: 600;
    color: #6b7280;
}

/* ── Submit bar ───────────────────────────────────────────────────────────── */
.se-actions {
    display: flex;
    align-items: center;
    gap: 0.85rem;
    padding-top: 0.25rem;
}
.se-btn-primary {
    background: #18181b;
    color: #fff;
    border: none;
    padding: 0.58rem 1.35rem;
    border-radius: 7px;
    font-weight: 600;
    font-size: 0.88rem;
    cursor: pointer;
    letter-spacing: 0.01em;
    transition: background 0.15s;
}
.se-btn-primary:hover { background: #3f3f46; }
.se-btn-cancel {
    font-size: 0.84rem;
    color: #71717a;
    text-decoration: none;
}
.se-btn-cancel:hover { color: #18181b; }

/* ── Breadcrumb ───────────────────────────────────────────────────────────── */
.se-crumb {
    font-size: 0.84rem;
    color: #9ca3af;
    margin: 0 0 1.25rem;
}
.se-crumb a { color: #4f46e5; text-decoration: none; }
.se-crumb a:hover { text-decoration: underline; }
.se-crumb-sep { margin: 0 0.35rem; color: #e5e7eb; }
.se-crumb-cur { color: #374151; font-weight: 500; }

/* ── Recovery banner ──────────────────────────────────────────────────────── */
.se-recovery {
    display: flex;
    gap: 0.85rem;
    align-items: flex-start;
    padding: 0.85rem 1rem;
    background: #fffbeb;
    border: 1px solid #fde68a;
    border-radius: 8px;
    margin-bottom: 1.25rem;
}
.se-recovery-title {
    font-weight: 700;
    color: #92400e;
    font-size: 0.87rem;
    margin: 0 0 0.25rem;
}
.se-recovery-body {
    font-size: 0.81rem;
    color: #78350f;
    margin: 0 0 0.55rem;
}
.se-recovery-btn {
    background: #92400e;
    color: #fff;
    border: none;
    padding: 0.38rem 0.8rem;
    border-radius: 6px;
    font-weight: 600;
    font-size: 0.81rem;
    cursor: pointer;
}

/* ── Right: preview panel ─────────────────────────────────────────────────── */
.se-preview-wrap {
    position: sticky;
    top: 1.5rem;
    align-self: start;
}
.se-preview-chip {
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: #f4f4f5;
    border-radius: 8px;
    padding: 0.5rem 0.75rem;
    font-size: 0.77rem;
    color: #52525b;
    margin-bottom: 0.65rem;
    gap: 0.5rem;
    min-width: 0;
}
.se-preview-chip-name {
    font-weight: 600;
    color: #18181b;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.se-preview-chip-code {
    font-family: ui-monospace, monospace;
    color: #9ca3af;
    font-size: 0.73rem;
    margin-left: 0.35rem;
}
.se-preview-badge {
    background: #e4e4e7;
    color: #52525b;
    padding: 0.12rem 0.5rem;
    border-radius: 99px;
    font-size: 0.72rem;
    font-weight: 600;
    white-space: nowrap;
    flex-shrink: 0;
}
.se-preview-card {
    border: 1px solid #e4e4e7;
    border-radius: 10px;
    background: #fcfcff;
    overflow: hidden;
}
.se-preview-header {
    padding: 0.55rem 0.85rem;
    background: #f0f4ff;
    border-bottom: 1px solid #e0e7ff;
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.se-preview-header-title {
    font-size: 0.75rem;
    font-weight: 700;
    color: #4f46e5;
    text-transform: uppercase;
    letter-spacing: 0.06em;
}
.se-preview-header-sub {
    font-size: 0.71rem;
    color: #9ca3af;
}
.se-preview-body {
    padding: 0.85rem;
    display: flex;
    flex-direction: column;
    gap: 0.7rem;
}
.se-preview-hint {
    margin: 0.55rem 0 0;
    font-size: 0.71rem;
    color: #c4c4c4;
    text-align: center;
}

/* ── Error multi-list banner ──────────────────────────────────────────────── */
.se-errbanner {
    margin: 0 0 1rem;
    padding: 0.75rem 1rem;
    background: #fef2f2;
    border: 1px solid #fca5a5;
    border-radius: 8px;
}
.se-errbanner-title {
    font-weight: 600;
    color: #991b1b;
    font-size: 0.86rem;
    margin: 0 0 0.3rem;
}
.se-errbanner ul {
    margin: 0;
    padding-left: 1.2rem;
    color: #b91c1c;
    font-size: 0.83rem;
}
</style>
@endpush

@section('content')
@php $schemaEditMode = false; @endphp

{{-- Multi-error list (layout shows only first; show all here if >1) --}}
@if ($errors->count() > 1)
    <div class="se-errbanner">
        <div class="se-errbanner-title">Please fix the following errors:</div>
        <ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
@endif

{{-- Breadcrumb --}}
<p class="se-crumb">
    <a href="{{ route('admin.service-catalog.index') }}">← Service catalog</a>
    <span class="se-crumb-sep">/</span>
    <span class="se-crumb-cur">{{ $service->name }}</span>
</p>

{{-- Schema recovery banner --}}
@if (($canonicalSchemaCount ?? 0) === 0 && !empty($recoveredSchema))
    <div class="se-recovery">
        <div>
            <p class="se-recovery-title">⚠ Submission form schema is missing</p>
            <p class="se-recovery-body">
                Found <strong>{{ count($recoveredSchema) }} field(s)</strong> from recent submitted cases.
                Import them to restore the form builder so you can review and edit.
            </p>
            <form method="post" action="{{ route('admin.service-catalog.services.recover-schema', $service) }}">
                @csrf
                <button type="submit" class="se-recovery-btn">Import recovered fields</button>
            </form>
        </div>
    </div>
@endif

{{-- Resolve schema --}}
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
    $serviceCodeLower = strtolower((string) $service->code);
    $serviceNameLower = strtolower((string) $service->name);
    if ($schemaResolved === [] && (
        $serviceCodeLower === 'udyam_registration'
        || str_contains($serviceCodeLower, 'udyam')
        || str_contains($serviceNameLower, 'udyam')
    )) {
        $schemaResolved = [
            ['key' => 'registration_number', 'label' => 'Registration Number', 'type' => \App\Support\ServiceFieldTypes::TEXT,     'required' => true],
            ['key' => 'remark',              'label' => 'Remark',              'type' => \App\Support\ServiceFieldTypes::TEXTAREA, 'required' => false],
        ];
    }
    $schemaInitial   = $schemaResolved;
    $currentDocTypes = old('allowed_document_types', $service->allowed_document_types ?: ['pdf', 'image']);
    $currentDocTypes = is_array($currentDocTypes) ? $currentDocTypes : ['pdf', 'image'];
@endphp

{{-- ─── Two-column grid ──────────────────────────────────────────────────── --}}
<div class="se-grid">

    {{-- ════════════ LEFT COLUMN : form ════════════ --}}
    <form method="post" action="{{ route('admin.service-catalog.services.update', $service) }}" id="svc-edit-form">
        @csrf @method('PUT')

        {{-- 1 · Basic details --}}
        <div class="se-card">
            <h3 class="se-card-title se-card-title--blue">Basic details</h3>

            <div class="se-field">
                <label class="se-label" for="service_category_id">
                    Category <span class="se-req">*</span>
                </label>
                <select id="service_category_id" name="service_category_id" required
                    class="se-select se-select--mid {{ $errors->has('service_category_id') ? 'is-err' : '' }}">
                    @foreach ($categories as $cat)
                        <option value="{{ $cat->id }}" @selected((int) old('service_category_id', $service->service_category_id) === (int) $cat->id)>
                            {{ $cat->name }}
                        </option>
                    @endforeach
                </select>
                @error('service_category_id')<p class="se-err">{{ $message }}</p>@enderror
            </div>

            <div class="se-field">
                <label class="se-label" for="name">
                    Display name <span class="se-req">*</span>
                </label>
                <input id="name" name="name" type="text"
                    value="{{ old('name', $service->name) }}"
                    required maxlength="191"
                    class="se-input {{ $errors->has('name') ? 'is-err' : '' }}">
                @error('name')<p class="se-err">{{ $message }}</p>@enderror
            </div>

            <div class="se-field">
                <label class="se-label" for="code">
                    Code <span class="se-req">*</span>
                    <span class="se-label-note">snake_case · unique</span>
                </label>
                <input id="code" name="code" type="text"
                    value="{{ old('code', $service->code) }}"
                    required pattern="[a-z0-9_]+" maxlength="96"
                    class="se-input se-input--code {{ $errors->has('code') ? 'is-err' : '' }}">
                <p class="se-hint">Lowercase, numbers and underscores only. Changing this updates deliverable linkage.</p>
                @error('code')<p class="se-err">{{ $message }}</p>@enderror
            </div>

            <div class="se-row-2">
                <div class="se-field">
                    <label class="se-label" for="deliverable_id">
                        MIS deliverable roll-up <span class="se-label-note">(optional)</span>
                    </label>
                    <select id="deliverable_id" name="deliverable_id" class="se-select">
                        <option value="">— None —</option>
                        @foreach ($deliverables as $d)
                            <option value="{{ $d->id }}" @selected((int) old('deliverable_id', $service->deliverable_id) === (int) $d->id)>
                                {{ $d->name }} ({{ $d->code }})
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="se-field">
                    <label class="se-label" for="sort_order">Sort order</label>
                    <input id="sort_order" name="sort_order" type="number" min="0"
                        value="{{ old('sort_order', $service->sort_order) }}"
                        class="se-input se-input--short">
                </div>
            </div>
        </div>

        {{-- 2 · Market price --}}
        <div class="se-card">
            <h3 class="se-card-title se-card-title--green">Estimated market price (impact)</h3>
            <p class="se-card-desc">Savings = approved cases × average price. Used in dashboards.</p>

            <div class="se-row-3">
                <div class="se-field">
                    <label class="se-label" for="estimated_market_price_avg">Average (₹)</label>
                    <input id="estimated_market_price_avg" name="estimated_market_price_avg"
                        type="number" min="0" step="0.01"
                        value="{{ old('estimated_market_price_avg', $service->estimated_market_price_avg) }}"
                        placeholder="e.g. 1200"
                        class="se-input {{ $errors->has('estimated_market_price_avg') ? 'is-err' : '' }}">
                    @error('estimated_market_price_avg')<p class="se-err">{{ $message }}</p>@enderror
                </div>
                <div class="se-field">
                    <label class="se-label" for="estimated_market_price_min">Min (₹) <span class="se-label-note">opt.</span></label>
                    <input id="estimated_market_price_min" name="estimated_market_price_min"
                        type="number" min="0" step="0.01"
                        value="{{ old('estimated_market_price_min', $service->estimated_market_price_min) }}"
                        placeholder="—"
                        class="se-input {{ $errors->has('estimated_market_price_min') ? 'is-err' : '' }}">
                    @error('estimated_market_price_min')<p class="se-err">{{ $message }}</p>@enderror
                </div>
                <div class="se-field">
                    <label class="se-label" for="estimated_market_price_max">Max (₹) <span class="se-label-note">opt.</span></label>
                    <input id="estimated_market_price_max" name="estimated_market_price_max"
                        type="number" min="0" step="0.01"
                        value="{{ old('estimated_market_price_max', $service->estimated_market_price_max) }}"
                        placeholder="—"
                        class="se-input {{ $errors->has('estimated_market_price_max') ? 'is-err' : '' }}">
                    @error('estimated_market_price_max')<p class="se-err">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="se-field">
                <label class="se-label" for="market_price_basis_note">
                    Price basis / source note <span class="se-label-note">(optional)</span>
                </label>
                <textarea id="market_price_basis_note" name="market_price_basis_note"
                    rows="2" maxlength="1000"
                    placeholder="e.g. CA consultant market range in district offices, Apr 2026."
                    class="se-textarea {{ $errors->has('market_price_basis_note') ? 'is-err' : '' }}">{{ old('market_price_basis_note', $service->market_price_basis_note) }}</textarea>
                @error('market_price_basis_note')<p class="se-err">{{ $message }}</p>@enderror
            </div>
        </div>
        {{-- 3 · Reporting & behaviour --}}
        <div class="se-card">
            <h3 class="se-card-title se-card-title--amber">Reporting &amp; behaviour</h3>

            <div class="se-field">
                <label class="se-label" for="reporting_tier">Reporting tier</label>
                @php $rt = old('reporting_tier', $service->reporting_tier ?? 'unset'); @endphp
                <select id="reporting_tier" name="reporting_tier" required
                    class="se-select se-select--mid {{ $errors->has('reporting_tier') ? 'is-err' : '' }}">
                    <option value="unset"   @selected($rt === 'unset')>Unset — groups with Non‑Key until set</option>
                    <option value="key"     @selected($rt === 'key')>Key</option>
                    <option value="non_key" @selected($rt === 'non_key')>Non‑Key</option>
                </select>
                <p class="se-hint">For MIS exports only — does not affect workflow.</p>
                @error('reporting_tier')<p class="se-err">{{ $message }}</p>@enderror
            </div>

            <div class="se-toggle-row">
                <label class="se-toggle">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $service->is_active))>
                    Active
                </label>
                <label class="se-toggle">
                    <input type="hidden" name="allows_multiple" value="0">
                    <input type="checkbox" name="allows_multiple" value="1" @checked(old('allows_multiple', $service->allows_multiple))>
                    Allow multiple cases per incubatee
                </label>
            </div>

            <div class="se-subpanel">
                <p class="se-subpanel-title">Maker–checker &amp; documents</p>

                <label class="se-check-row">
                    <input type="hidden" name="requires_approval" value="0">
                    <input type="checkbox" id="requires_approval" name="requires_approval" value="1"
                        @checked(old('requires_approval', $service->requires_approval))>
                    <span>
                        <strong>Requires SPOC approval</strong>
                        <span class="se-check-desc">When on, staff-submitted cases sit in <em>pending approval</em> until the district SPOC acts. When off, cases auto-approve.</span>
                        <span class="se-check-note">Only affects new cases — cases in flight keep the rule that was active when they were created.</span>
                    </span>
                </label>

                <label class="se-check-row">
                    <input type="hidden" name="requires_document" value="0">
                    <input type="checkbox" id="requires_document" name="requires_document" value="1"
                        @checked(old('requires_document', $service->requires_document))>
                    <span>
                        <strong>Requires document upload</strong>
                        <span class="se-check-desc">Staff must attach proof (certificate, acknowledgement, screenshot, etc.) when submitting.</span>
                    </span>
                </label>

                <div id="allowed_doc_types_wrap" class="se-doctype-row">
                    <span class="se-doctype-label">Allowed types:</span>
                    <label class="se-toggle">
                        <input type="checkbox" name="allowed_document_types[]" value="pdf" @checked(in_array('pdf', $currentDocTypes, true))> PDF
                    </label>
                    <label class="se-toggle">
                        <input type="checkbox" name="allowed_document_types[]" value="image" @checked(in_array('image', $currentDocTypes, true))> Image (jpg/png)
                    </label>
                </div>
            </div>
        </div>

        {{-- 4 · Submission form builder --}}
        <div class="se-card">
            <h3 class="se-card-title se-card-title--slate">Submission form builder</h3>
            <p class="se-card-desc">
                Define what staff fill in when opening a case for this service.
                The <strong>live preview</strong> on the right updates as you type.
                Leave empty if no extra details are needed.
            </p>
            <div id="service-schema-editor-anchor"></div>
            @include('partials.admin-service-schema-builder', ['schemaEditMode' => $schemaEditMode])
            @error('field_schema')<p class="se-err" style="margin-top:0.4rem;">{{ $message }}</p>@enderror
        </div>

        {{-- Submit --}}
        <div class="se-actions">
            <button type="submit" class="se-btn-primary">Update service</button>
            <a href="{{ route('admin.service-catalog.index') }}" class="se-btn-cancel">Cancel</a>
        </div>
    </form>

    {{-- ════════════ RIGHT COLUMN : sticky preview ════════════ --}}
    <aside class="se-preview-wrap">
        <div class="se-preview-chip">
            <span style="min-width:0; overflow:hidden;">
                <span class="se-preview-chip-name">{{ $service->name }}</span>
                <span class="se-preview-chip-code">{{ $service->code }}</span>
            </span>
            <span id="preview_field_count" class="se-preview-badge">0 fields</span>
        </div>

        <div class="se-preview-card">
            <div class="se-preview-header">
                <span class="se-preview-header-title">Live preview</span>
                <span class="se-preview-header-sub">Staff submit form</span>
            </div>
            <div class="se-preview-body">
                <div id="svc_preview_fields"></div>
                <div id="svc_preview_doc" style="display:none;">
                    <label style="display:block; font-size:0.83rem; font-weight:600; margin-bottom:0.25rem; color:#374151;">
                        Documents <span style="color:#dc2626;">*</span>
                    </label>
                    <input type="file" disabled
                        style="width:100%; padding:0.35rem 0.45rem; border:1px solid #d1d5db; border-radius:6px; background:#f9fafb; font-size:0.81rem; box-sizing:border-box;">
                    <p style="font-size:0.73rem; color:#9ca3af; margin:0.2rem 0 0;">
                        Allowed: <span id="svc_preview_doc_types">PDF / Image</span>
                    </p>
                </div>
                <div id="svc_preview_error" style="display:none; padding:0.45rem 0.6rem; background:#fef2f2; border:1px solid #fca5a5; border-radius:6px; font-size:0.77rem; color:#991b1b;"></div>
            </div>
        </div>

        <p class="se-preview-hint">Updates instantly as you edit the form builder.</p>
    </aside>

</div>{{-- end .se-grid --}}

<script>
(function () {
    'use strict';

    // ── Doc-type checkbox visibility ───────────────────────────────────────
    const reqDoc  = document.getElementById('requires_document');
    const docWrap = document.getElementById('allowed_doc_types_wrap');

    function syncDocWrap() {
        if (!reqDoc || !docWrap) return;
        const on = reqDoc.checked;
        docWrap.style.opacity = on ? '1' : '0.35';
        docWrap.querySelectorAll('input[type="checkbox"]').forEach(function (cb) { cb.disabled = !on; });
    }
    if (reqDoc) { syncDocWrap(); reqDoc.addEventListener('change', syncDocWrap); }

    // ── Live preview ───────────────────────────────────────────────────────
    const hidden       = document.getElementById('svc_schema_hidden');
    const fieldsWrap   = document.getElementById('svc_preview_fields');
    const previewDocEl = document.getElementById('svc_preview_doc');
    const previewDocTy = document.getElementById('svc_preview_doc_types');
    const previewErrEl = document.getElementById('svc_preview_error');
    const fieldCountEl = document.getElementById('preview_field_count');

    if (!hidden || !fieldsWrap) {
        console.warn('[svc-preview] DOM elements missing; preview disabled.');
        return;
    }

    function esc(s) {
        return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    function buildControl(r) {
        const type = String(r.type || 'text');
        const s = 'width:100%;padding:0.4rem 0.5rem;border:1px solid #d1d5db;border-radius:6px;background:#f9fafb;font-size:0.83rem;box-sizing:border-box;';
        function opts(list) {
            return (Array.isArray(list) ? list : []).map(function(o){ return '<option>'+esc(o.label||o.value||'')+'</option>'; }).join('');
        }
        switch (type) {
            case 'textarea':    return '<textarea disabled rows="2" style="'+s+'resize:none;"></textarea>';
            case 'number':      return '<input type="number" disabled style="'+s+'width:9rem;">';
            case 'amount':      return '<input type="number" disabled placeholder="₹" style="'+s+'width:9rem;">';
            case 'date':        return '<input type="date" disabled style="'+s+'width:auto;">';
            case 'url':         return '<input type="url" disabled placeholder="https://…" style="'+s+'">';
            case 'email':       return '<input type="email" disabled placeholder="name@example.com" style="'+s+'">';
            case 'phone':       return '<input type="tel" disabled placeholder="10-digit mobile" style="'+s+'width:11rem;">';
            case 'select':
            case 'radio':       return '<select disabled style="'+s+'"><option>— Select —</option>'+opts(r.options)+'</select>';
            case 'multiselect': return '<select multiple disabled size="3" style="'+s+'">'+( opts(r.options)||'<option>— No options —</option>')+'</select>';
            case 'checkbox':    return '<label style="display:inline-flex;align-items:center;gap:0.35rem;font-size:0.83rem;"><input type="checkbox" disabled> Confirm</label>';
            case 'file':        return '<input type="file" disabled style="'+s+'">';
            default:            return '<input type="text" disabled style="'+s+'">';
        }
    }

    function renderFields() {
        if (previewErrEl) { previewErrEl.style.display = 'none'; previewErrEl.textContent = ''; }
        let rows = [];
        try {
            const raw = (hidden.value || '').trim();
            const parsed = raw ? JSON.parse(raw) : [];
            rows = Array.isArray(parsed) ? parsed : [];
        } catch (e) {
            console.warn('[svc-preview] JSON parse error:', e.message);
            if (previewErrEl) { previewErrEl.textContent = '⚠ Invalid schema JSON: ' + e.message; previewErrEl.style.display = 'block'; }
            return;
        }
        const valid = rows.filter(function(r){ return r && r.key && r.label; });
        if (fieldCountEl) fieldCountEl.textContent = valid.length + (valid.length === 1 ? ' field' : ' fields');
        if (!valid.length) {
            fieldsWrap.innerHTML = '<p style="margin:0;font-size:0.8rem;color:#c4c4c4;font-style:italic;">No fields yet — add fields in the builder.</p>';
            return;
        }
        fieldsWrap.innerHTML = valid.map(function(r) {
            const req  = r.required ? ' <span style="color:#dc2626;">*</span>' : '';
            const help = r.help ? '<p style="margin:0.18rem 0 0;font-size:0.72rem;color:#9ca3af;">'+esc(r.help)+'</p>' : '';
            return '<div><label style="display:block;font-size:0.83rem;font-weight:600;margin-bottom:0.2rem;color:#374151;">'
                + esc(r.label)+req+'</label>'+buildControl(r)+help+'</div>';
        }).join('');
    }

    function renderDoc() {
        if (!previewDocEl || !previewDocTy) return;
        const show = reqDoc ? reqDoc.checked : false;
        previewDocEl.style.display = show ? 'block' : 'none';
        if (!show) return;
        const checked = Array.from(document.querySelectorAll('input[name="allowed_document_types[]"]:checked')).map(function(i){ return i.value; });
        const labels = [];
        if (checked.includes('pdf'))   labels.push('PDF');
        if (checked.includes('image')) labels.push('Image (jpg/png)');
        previewDocTy.textContent = labels.length ? labels.join(' / ') : '—';
    }

    renderFields();
    renderDoc();

    hidden.addEventListener('change', function() {
        try { renderFields(); } catch(e) { console.error('[svc-preview]', e); }
    });
    document.addEventListener('change', function(e) {
        if (!e.target) return;
        if (e.target.id === 'requires_document' || e.target.name === 'allowed_document_types[]') {
            try { renderDoc(); } catch(e) { console.error('[svc-preview]', e); }
        }
    });
    setInterval(function() {
        try { renderFields(); renderDoc(); } catch(e) { /* silent */ }
    }, 2000);

})();
</script>
@endsection
