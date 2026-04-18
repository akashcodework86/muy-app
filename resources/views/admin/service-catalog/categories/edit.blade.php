@extends('layouts.admin')

@section('title', 'Edit category')
@section('heading', 'Edit category: '.$category->name)

@section('content')
    <p style="margin-bottom:1rem;"><a href="{{ route('admin.service-catalog.index') }}">← Service catalog</a></p>

    <form method="post" action="{{ route('admin.service-catalog.categories.update', $category) }}" style="max-width:32rem;">
        @csrf
        @method('PUT')

        <div style="margin-bottom:0.85rem;">
            <label for="name" style="display:block; font-weight:500; margin-bottom:0.25rem;">Name</label>
            <input id="name" name="name" type="text" value="{{ old('name', $category->name) }}" required maxlength="160" style="width:100%; padding:0.45rem 0.5rem; border:1px solid #d4d4d8; border-radius:6px;">
            @error('name')<div style="color:#b91c1c;font-size:0.85rem;margin-top:0.25rem;">{{ $message }}</div>@enderror
        </div>
        <div style="margin-bottom:0.85rem;">
            <label for="sort_order" style="display:block; font-weight:500; margin-bottom:0.25rem;">Sort order</label>
            <input id="sort_order" name="sort_order" type="number" min="0" value="{{ old('sort_order', $category->sort_order) }}" style="width:8rem; padding:0.45rem 0.5rem; border:1px solid #d4d4d8; border-radius:6px;">
        </div>

        <div style="margin-bottom:0.85rem;">
            <label for="parent_id" style="display:block; font-weight:500; margin-bottom:0.25rem;">Parent (blank = top-level)</label>
            <select id="parent_id" name="parent_id" style="width:100%; padding:0.45rem 0.5rem; border:1px solid #d4d4d8; border-radius:6px;">
                <option value="">— Top-level category —</option>
                @foreach (\App\Models\ServiceCategory::query()->whereNull('parent_id')->whereKeyNot($category->id)->orderBy('sort_order')->orderBy('name')->get() as $root)
                    <option value="{{ $root->id }}" @selected(old('parent_id', $category->parent_id) == $root->id)>{{ $root->name }}</option>
                @endforeach
            </select>
            <p style="font-size:0.78rem; color:#71717a; margin:0.35rem 0 0;">Slug stays stable: <code>{{ $category->slug }}</code></p>
            @error('parent_id')<div style="color:#b91c1c;font-size:0.85rem;margin-top:0.25rem;">{{ $message }}</div>@enderror
        </div>

        <button type="submit" style="background:#18181b; color:#fff; border:none; padding:0.5rem 1rem; border-radius:6px; font-weight:500;">Update</button>
    </form>
@endsection
