@extends('layouts.admin')

@section('title', 'Upload document')
@section('heading', 'Upload document')

@section('content')
    <p style="margin:0 0 1rem;"><a href="{{ route('admin.documents.index') }}">← Document repository</a></p>

    <form method="post" action="{{ route('admin.documents.store') }}" enctype="multipart/form-data" style="max-width:46rem;">
        @csrf
        @include('admin.documents.partials.form-fields', ['doc' => null, 'categories' => $categories, 'roles' => $roles])
        <button type="submit" style="background:#18181b;color:#fff;border:none;padding:0.55rem 1.1rem;border-radius:8px;font-weight:600;">Upload document</button>
    </form>
@endsection
