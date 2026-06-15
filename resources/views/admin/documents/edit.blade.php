@extends('layouts.admin')

@section('title', 'Edit document')
@section('heading', 'Edit document')

@section('content')
    <p style="margin:0 0 1rem;"><a href="{{ route('admin.documents.index') }}">← Document repository</a></p>

    <form method="post" action="{{ route('admin.documents.update', $doc) }}" style="max-width:46rem;margin-bottom:1rem;">
        @csrf
        @method('PUT')
        @include('admin.documents.partials.form-fields', ['doc' => $doc, 'rootCategories' => $rootCategories, 'subcategoriesByRoot' => $subcategoriesByRoot, 'roles' => $roles, 'fileOptional' => true])
        <button type="submit" style="background:#18181b;color:#fff;border:none;padding:0.55rem 1.1rem;border-radius:8px;font-weight:600;">Save details</button>
    </form>

    <form method="post" action="{{ route('admin.documents.upload-version', $doc) }}" enctype="multipart/form-data" data-doc-upload-progress
        style="max-width:46rem;background:#fff;border:1px solid #e4e4e7;border-radius:8px;padding:0.85rem 1rem;margin-bottom:1rem;">
        @csrf
        <h3 style="margin:0 0 0.55rem;font-size:0.95rem;">Upload new version</h3>
        <input type="file" name="file" required accept=".pdf,.docx,.xlsx,.pptx,.jpg,.jpeg,.png"
            style="padding:0.35rem 0.45rem;border:1px solid #d4d4d8;border-radius:8px;background:#fff;">
        <p style="margin:0.35rem 0 0;font-size:0.78rem;color:#71717a;">Max 50 MB.</p>
        @include('admin.documents.partials.upload-progress')
        <button type="submit" style="margin-top:0.65rem;background:#4f46e5;color:#fff;border:none;padding:0.45rem 0.8rem;border-radius:8px;">Upload</button>
    </form>

    <div style="max-width:46rem;background:#fff;border:1px solid #e4e4e7;border-radius:8px;padding:0.85rem 1rem;">
        <h3 style="margin:0 0 0.55rem;font-size:0.95rem;">Version history</h3>
        <ul style="margin:0;padding-left:1.1rem;">
            @forelse ($doc->versions as $v)
                <li style="margin-bottom:0.25rem;">
                    v{{ $v->version_no }} — {{ $v->original_name }} ({{ number_format((int) $v->size_bytes / 1024, 1) }} KB)
                    · {{ $v->created_at?->format('Y-m-d H:i') }}
                    · by {{ $v->uploader?->name ?? 'System' }}
                    @if ((int) $doc->latest_version_id === (int) $v->id)
                        <span style="color:#0f766e;font-weight:600;">(latest)</span>
                    @endif
                </li>
            @empty
                <li>No versions found.</li>
            @endforelse
        </ul>
    </div>

    <form method="post" action="{{ route('admin.documents.destroy', $doc) }}" onsubmit="return confirm('Delete this document and all versions?');"
        style="max-width:46rem;margin-top:1rem;">
        @csrf
        @method('DELETE')
        <button type="submit" style="background:#b91c1c;color:#fff;border:none;padding:0.45rem 0.75rem;border-radius:8px;">Delete document</button>
    </form>
@endsection
