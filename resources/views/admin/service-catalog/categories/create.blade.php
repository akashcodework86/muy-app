@extends('layouts.admin')

@section('title', 'Add category')
@section('heading', 'Add category')

@section('content')
    <p style="margin-bottom:1rem;"><a href="{{ route('admin.service-catalog.index') }}">← Service catalog</a></p>

    <form method="post" action="{{ route('admin.service-catalog.categories.store') }}" style="max-width:32rem;">
        @csrf
        <p style="font-size:0.9rem; color:#52525b;">Each service belongs directly to one category (no subcategory level).</p>

        <div style="margin-bottom:0.85rem;">
            <label for="name" style="display:block; font-weight:500; margin-bottom:0.25rem;">Name</label>
            <input id="name" name="name" type="text" value="{{ old('name') }}" required maxlength="160" style="width:100%; padding:0.45rem 0.5rem; border:1px solid #d4d4d8; border-radius:6px;">
            @error('name')<div style="color:#b91c1c;font-size:0.85rem;margin-top:0.25rem;">{{ $message }}</div>@enderror
        </div>
        <div style="margin-bottom:0.85rem;">
            <label for="sort_order" style="display:block; font-weight:500; margin-bottom:0.25rem;">Sort order</label>
            <input id="sort_order" name="sort_order" type="number" min="0" value="{{ old('sort_order', 0) }}" style="width:8rem; padding:0.45rem 0.5rem; border:1px solid #d4d4d8; border-radius:6px;">
        </div>
        <div style="margin-bottom:0.85rem;">
            <label for="target_mode" style="display:block; font-weight:500; margin-bottom:0.25rem;">Target mode</label>
            <select id="target_mode" name="target_mode" style="width:100%; padding:0.45rem 0.5rem; border:1px solid #d4d4d8; border-radius:6px;">
                <option value="service" @selected(old('target_mode', 'service') === 'service')>Service-wise targets (existing behavior)</option>
                <option value="category" @selected(old('target_mode') === 'category')>Category-wise collective target</option>
            </select>
            <p style="font-size:0.78rem; color:#71717a; margin:0.3rem 0 0;">
                Service-wise: each service has separate target. Category-wise: one target for this category, achievement is combined from all services.
            </p>
            @error('target_mode')<div style="color:#b91c1c;font-size:0.85rem;margin-top:0.25rem;">{{ $message }}</div>@enderror
        </div>
        <button type="submit" style="background:#18181b; color:#fff; border:none; padding:0.5rem 1rem; border-radius:6px; font-weight:500;">Save</button>
    </form>
@endsection
