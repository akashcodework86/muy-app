@extends('layouts.admin')

@section('title', $parent ? 'Add subcategory' : 'Add category')
@section('heading', $parent ? 'Add subcategory under: '.$parent->name : 'Add top-level category')

@section('content')
    <p style="margin-bottom:1rem;"><a href="{{ route('admin.service-catalog.index') }}">← Service catalog</a></p>

    <form method="post" action="{{ route('admin.service-catalog.categories.store') }}" style="max-width:32rem;">
        @csrf
        @if ($parent)
            <input type="hidden" name="parent_id" value="{{ $parent->id }}">
            <p style="font-size:0.9rem; color:#52525b;">Parent: <strong>{{ $parent->name }}</strong></p>
        @else
            <p style="font-size:0.9rem; color:#52525b;">This will be a <strong>top-level</strong> category (e.g. Registration).</p>
        @endif

        <div style="margin-bottom:0.85rem;">
            <label for="name" style="display:block; font-weight:500; margin-bottom:0.25rem;">Name</label>
            <input id="name" name="name" type="text" value="{{ old('name') }}" required maxlength="160" style="width:100%; padding:0.45rem 0.5rem; border:1px solid #d4d4d8; border-radius:6px;">
            @error('name')<div style="color:#b91c1c;font-size:0.85rem;margin-top:0.25rem;">{{ $message }}</div>@enderror
        </div>
        <div style="margin-bottom:0.85rem;">
            <label for="sort_order" style="display:block; font-weight:500; margin-bottom:0.25rem;">Sort order</label>
            <input id="sort_order" name="sort_order" type="number" min="0" value="{{ old('sort_order', 0) }}" style="width:8rem; padding:0.45rem 0.5rem; border:1px solid #d4d4d8; border-radius:6px;">
        </div>
        @error('parent_id')<div style="color:#b91c1c;font-size:0.85rem;margin-bottom:0.5rem;">{{ $message }}</div>@enderror

        <button type="submit" style="background:#18181b; color:#fff; border:none; padding:0.5rem 1rem; border-radius:6px; font-weight:500;">Save</button>
    </form>
@endsection
