@extends('layouts.admin')

@section('title', 'Edit designation')
@section('heading', 'Edit designation')

@section('content')
    <form method="post" action="{{ route('admin.designations.update', $designation) }}" style="max-width:28rem;">
        @csrf
        @method('PUT')
        <div style="margin-bottom:0.85rem;">
            <label for="name" style="display:block; font-size:0.85rem; font-weight:500; margin-bottom:0.25rem;">Name</label>
            <input id="name" name="name" type="text" value="{{ old('name', $designation->name) }}" required maxlength="120" style="width:100%; padding:0.45rem; border:1px solid #d4d4d8; border-radius:6px;">
        </div>
        <div style="margin-bottom:1rem;">
            <label for="sort_order" style="display:block; font-size:0.85rem; font-weight:500; margin-bottom:0.25rem;">Sort order</label>
            <input id="sort_order" name="sort_order" type="number" min="0" max="65535" value="{{ old('sort_order', $designation->sort_order) }}" style="width:100%; max-width:10rem; padding:0.45rem; border:1px solid #d4d4d8; border-radius:6px;">
        </div>
        <button type="submit" style="background:#18181b; color:#fff; border:none; padding:0.55rem 1rem; border-radius:6px; font-weight:500;">Save</button>
        <a href="{{ route('admin.designations.index') }}" style="margin-left:0.75rem; font-size:0.9rem;">Cancel</a>
    </form>
@endsection
