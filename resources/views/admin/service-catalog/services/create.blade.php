@extends('layouts.admin')

@section('title', 'Add service')
@section('heading', 'Add catalog service')

@section('content')
    <p style="margin-bottom:1rem;"><a href="{{ route('admin.service-catalog.index') }}">← Service catalog</a></p>

    <form method="post" action="{{ route('admin.service-catalog.services.store') }}" style="max-width:40rem;">
        @csrf

        <div style="margin-bottom:0.85rem;">
            <label for="service_category_id" style="display:block; font-weight:500; margin-bottom:0.25rem;">Subcategory</label>
            <select id="service_category_id" name="service_category_id" required style="width:100%; padding:0.45rem 0.5rem; border:1px solid #d4d4d8; border-radius:6px;">
                <option value="">— Select —</option>
                @foreach ($subcategories as $sub)
                    <option value="{{ $sub->id }}" @selected((int) old('service_category_id', $selectedCategoryId) === (int) $sub->id)>
                        {{ $sub->parent?->name ?? '?' }} → {{ $sub->name }}
                    </option>
                @endforeach
            </select>
            @error('service_category_id')<div style="color:#b91c1c;font-size:0.85rem;margin-top:0.25rem;">{{ $message }}</div>@enderror
            @if ($subcategories->isEmpty())
                <p style="font-size:0.85rem; color:#b45309; margin-top:0.35rem;">No subcategories yet. Create a top category, then a subcategory, then return here.</p>
            @endif
        </div>

        <div style="margin-bottom:0.85rem;">
            <label for="name" style="display:block; font-weight:500; margin-bottom:0.25rem;">Display name</label>
            <input id="name" name="name" type="text" value="{{ old('name') }}" required maxlength="191" style="width:100%; padding:0.45rem 0.5rem; border:1px solid #d4d4d8; border-radius:6px;">
            @error('name')<div style="color:#b91c1c;font-size:0.85rem;margin-top:0.25rem;">{{ $message }}</div>@enderror
        </div>

        <div style="margin-bottom:0.85rem;">
            <label for="code" style="display:block; font-weight:500; margin-bottom:0.25rem;">Code <span style="font-weight:400;color:#71717a;">(optional, snake_case; auto if empty)</span></label>
            <input id="code" name="code" type="text" value="{{ old('code') }}" pattern="[a-z0-9_]+" maxlength="96" placeholder="e.g. utdb_registration" style="width:100%; padding:0.45rem 0.5rem; border:1px solid #d4d4d8; border-radius:6px;">
            @error('code')<div style="color:#b91c1c;font-size:0.85rem;margin-top:0.25rem;">{{ $message }}</div>@enderror
        </div>

        <div style="margin-bottom:0.85rem;">
            <label for="deliverable_id" style="display:block; font-weight:500; margin-bottom:0.25rem;">MIS deliverable roll-up <span style="font-weight:400;color:#71717a;">(optional)</span></label>
            <select id="deliverable_id" name="deliverable_id" style="width:100%; padding:0.45rem 0.5rem; border:1px solid #d4d4d8; border-radius:6px;">
                <option value="">— None —</option>
                @foreach ($deliverables as $d)
                    <option value="{{ $d->id }}" @selected((int) old('deliverable_id') === (int) $d->id)>{{ $d->name }} ({{ $d->code }})</option>
                @endforeach
            </select>
        </div>

        <div style="margin-bottom:0.85rem;">
            <label for="sort_order" style="display:block; font-weight:500; margin-bottom:0.25rem;">Sort order</label>
            <input id="sort_order" name="sort_order" type="number" min="0" value="{{ old('sort_order', 0) }}" style="width:8rem; padding:0.45rem 0.5rem; border:1px solid #d4d4d8; border-radius:6px;">
        </div>

        <div style="margin-bottom:0.85rem;">
            <label for="reporting_tier" style="display:block; font-weight:500; margin-bottom:0.25rem;">Reporting — Key / Non‑Key</label>
            <select id="reporting_tier" name="reporting_tier" required style="width:100%; max-width:24rem; padding:0.45rem 0.5rem; border:1px solid #d4d4d8; border-radius:6px;">
                @php $rt = old('reporting_tier', 'unset'); @endphp
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
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', true))> Active
            </label>
            <label style="display:flex; align-items:center; gap:0.35rem; cursor:pointer;">
                <input type="hidden" name="allows_multiple" value="0">
                <input type="checkbox" name="allows_multiple" value="1" @checked(old('allows_multiple'))> Allow multiple cases per incubatee
            </label>
        </div>

        <fieldset style="margin:0 0 1rem; padding:0.75rem 0.9rem; border:1px solid #e4e4e7; border-radius:8px;">
            <legend style="padding:0 0.4rem; font-size:0.85rem; font-weight:600; color:#3730a3;">Maker–checker &amp; documents</legend>
            <label style="display:flex; align-items:flex-start; gap:0.45rem; cursor:pointer; margin:0.35rem 0;">
                <input type="hidden" name="requires_approval" value="0">
                <input type="checkbox" id="requires_approval" name="requires_approval" value="1" @checked(old('requires_approval'))>
                <span>
                    <strong>Requires SPOC approval</strong> (maker–checker)
                    <div style="font-size:0.78rem; color:#71717a;">When on, staff-submitted cases sit in <em>pending approval</em> with the district's SPOC until they approve, send back, or reject. When off, cases auto-approve on submission.</div>
                </span>
            </label>
            <label style="display:flex; align-items:flex-start; gap:0.45rem; cursor:pointer; margin:0.35rem 0;">
                <input type="hidden" name="requires_document" value="0">
                <input type="checkbox" id="requires_document" name="requires_document" value="1" @checked(old('requires_document'))>
                <span>
                    <strong>Requires document upload</strong>
                    <div style="font-size:0.78rem; color:#71717a;">Staff must attach proof (GST certificate, acknowledgement PDF, screenshot, etc.) when submitting a case.</div>
                </span>
            </label>
            <div id="allowed_doc_types_wrap" style="margin-left:1.5rem; margin-top:0.35rem; display:flex; flex-wrap:wrap; gap:1rem; font-size:0.85rem;">
                <span style="color:#52525b; font-size:0.78rem;">Allowed file types:</span>
                @php $oldTypes = old('allowed_document_types', ['pdf', 'image']); @endphp
                <label style="display:flex; align-items:center; gap:0.3rem; cursor:pointer;">
                    <input type="checkbox" name="allowed_document_types[]" value="pdf" @checked(in_array('pdf', (array) $oldTypes, true))> PDF
                </label>
                <label style="display:flex; align-items:center; gap:0.3rem; cursor:pointer;">
                    <input type="checkbox" name="allowed_document_types[]" value="image" @checked(in_array('image', (array) $oldTypes, true))> Image (jpg/png)
                </label>
            </div>
        </fieldset>

        @php
            $schemaInitial = [];
            if (old('field_schema')) {
                $decoded = json_decode(old('field_schema'), true);
                $schemaInitial = is_array($decoded) ? $decoded : [];
            }
        @endphp
        @include('partials.admin-service-schema-builder')
        @error('field_schema')<div style="color:#b91c1c;font-size:0.85rem;margin-top:0.25rem;">{{ $message }}</div>@enderror

        <button type="submit" style="background:#18181b; color:#fff; border:none; padding:0.5rem 1rem; border-radius:6px; font-weight:500;">Save service</button>
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
@endsection
