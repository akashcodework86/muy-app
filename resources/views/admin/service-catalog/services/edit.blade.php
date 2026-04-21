@extends('layouts.admin')

@section('title', 'Edit service')
@section('heading', 'Edit service: '.$service->name)

@section('content')
    <p style="margin-bottom:1rem;"><a href="{{ route('admin.service-catalog.index') }}">← Service catalog</a></p>

    <form method="post" action="{{ route('admin.service-catalog.services.update', $service) }}" style="max-width:40rem;">
        @csrf
        @method('PUT')

        <div style="margin-bottom:0.85rem;">
            <label for="service_category_id" style="display:block; font-weight:500; margin-bottom:0.25rem;">Subcategory</label>
            <select id="service_category_id" name="service_category_id" required style="width:100%; padding:0.45rem 0.5rem; border:1px solid #d4d4d8; border-radius:6px;">
                @foreach ($subcategories as $sub)
                    <option value="{{ $sub->id }}" @selected((int) old('service_category_id', $service->service_category_id) === (int) $sub->id)>
                        {{ $sub->parent?->name ?? '?' }} → {{ $sub->name }}
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

        <div style="margin-bottom:0.85rem;">
            <label for="field_schema" style="display:block; font-weight:500; margin-bottom:0.25rem;">Field schema (JSON)</label>
            <textarea id="field_schema" name="field_schema" rows="10" style="width:100%; font-family:ui-monospace,monospace; font-size:0.8rem; padding:0.5rem; border:1px solid #d4d4d8; border-radius:6px;">{{ old('field_schema', $fieldSchemaJson) }}</textarea>
            @error('field_schema')<div style="color:#b91c1c;font-size:0.85rem;margin-top:0.25rem;">{{ $message }}</div>@enderror
        </div>

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
@endsection
