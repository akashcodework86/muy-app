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

        <div style="margin-bottom:0.85rem;">
            <label for="field_schema" style="display:block; font-weight:500; margin-bottom:0.25rem;">Field schema (JSON)</label>
            <textarea id="field_schema" name="field_schema" rows="10" style="width:100%; font-family:ui-monospace,monospace; font-size:0.8rem; padding:0.5rem; border:1px solid #d4d4d8; border-radius:6px;" placeholder='[{"key":"platform","label":"Platform","type":"select","options":["Amazon","Flipkart","Blinkit"]}]'>{{ old('field_schema') }}</textarea>
            <p style="font-size:0.78rem; color:#71717a; margin:0.35rem 0 0;">Each item: <code>key</code> (snake_case), <code>label</code>, <code>type</code> <code>text</code> or <code>select</code>, and for select an <code>options</code> array. Leave empty if not needed.</p>
            @error('field_schema')<div style="color:#b91c1c;font-size:0.85rem;margin-top:0.25rem;">{{ $message }}</div>@enderror
        </div>

        <button type="submit" style="background:#18181b; color:#fff; border:none; padding:0.5rem 1rem; border-radius:6px; font-weight:500;">Save service</button>
    </form>
@endsection
