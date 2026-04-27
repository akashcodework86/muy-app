@extends('layouts.admin')

@section('title', 'Service catalog')
@section('heading', 'Service catalog')

@section('content')
    @if (session('status'))
        <p style="color:#166534; margin:0 0 1rem;">{{ session('status') }}</p>
    @endif
    @if ($errors->any())
        <ul style="color:#b91c1c; margin:0 0 1rem; padding-left:1.2rem;">
            @foreach ($errors->all() as $e)
                <li>{{ $e }}</li>
            @endforeach
        </ul>
    @endif

    <p style="font-size:0.9rem; color:#52525b; margin-top:0;">Simple structure: <strong>Category → Service</strong>. Subcategories are removed.</p>

    <div style="margin:0.75rem 0 1rem; display:flex; flex-wrap:wrap; gap:0.5rem; align-items:center;">
        <a href="{{ route('admin.service-catalog.categories.create') }}" style="display:inline-block; background:#18181b; color:#fff; padding:0.45rem 0.85rem; border-radius:6px; text-decoration:none; font-size:0.9rem;">Add category</a>
        <a href="{{ route('admin.service-catalog.services.create') }}" style="display:inline-block; background:#fff; color:#18181b; border:1px solid #d4d4d8; padding:0.45rem 0.85rem; border-radius:6px; text-decoration:none; font-size:0.9rem;">Add service</a>
    </div>

    @forelse ($categories as $category)
        <div style="background:#fff; border:1px solid #e4e4e7; border-radius:8px; margin-bottom:0.75rem; padding:0.8rem 0.95rem;">
            <div style="display:flex; flex-wrap:wrap; gap:0.35rem; align-items:center;">
                <strong style="font-size:1rem;">{{ $category->name }}</strong>
                <span style="font-size:0.78rem; color:#71717a;">({{ $category->slug }})</span>
                <span style="font-size:0.75rem; color:#64748b; background:#f1f5f9; padding:0.15rem 0.5rem; border-radius:999px; font-weight:600;">{{ $category->services->count() }} service{{ $category->services->count() === 1 ? '' : 's' }}</span>
                <span style="flex:1"></span>
                <a href="{{ route('admin.service-catalog.services.create', ['service_category_id' => $category->id]) }}" style="font-size:0.82rem;">Add service</a>
                <span style="color:#d4d4d8;">|</span>
                <a href="{{ route('admin.service-catalog.categories.edit', $category) }}" style="font-size:0.82rem;">Edit</a>
                <span style="color:#d4d4d8;">|</span>
                <form method="post" action="{{ route('admin.service-catalog.categories.destroy', $category) }}" style="display:inline;" onsubmit="return confirm('Delete this category? Only if it has no services.');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" style="background:none;border:none;padding:0;color:#b91c1c;cursor:pointer;font-size:0.82rem;text-decoration:underline;">Delete</button>
                </form>
            </div>

            @if ($category->services->isEmpty())
                <p style="margin:0.5rem 0 0; font-size:0.84rem; color:#71717a;">No services yet.</p>
            @else
                <ul style="margin:0.55rem 0 0; padding-left:1.1rem; font-size:0.85rem;">
                    @foreach ($category->services as $svc)
                        <li style="margin-bottom:0.25rem;">
                            <code>{{ $svc->code }}</code> — {{ $svc->name }}
                            @include('admin.service-catalog.partials.reporting-tier-badge', ['svc' => $svc])
                            @if ($svc->requires_approval)
                                <span style="background:#ede9fe; color:#5b21b6; border:1px solid #c4b5fd; padding:0 0.4rem; border-radius:999px; font-size:0.72rem; font-weight:600; margin-left:0.2rem;">Needs approval</span>
                            @endif
                            @if ($svc->requires_document)
                                <span style="background:#e0f2fe; color:#075985; border:1px solid #bae6fd; padding:0 0.4rem; border-radius:999px; font-size:0.72rem; font-weight:600; margin-left:0.2rem;">Doc required</span>
                            @endif
                            @if ($svc->allows_multiple)<span style="color:#0369a1; margin-left:0.2rem;">· multiple</span>@endif
                            @if (! $svc->is_active)<span style="color:#b45309; margin-left:0.2rem;">· inactive</span>@endif
                            — <a href="{{ route('admin.service-catalog.services.edit', ['service' => $svc, 'form' => 1]) }}">Edit</a>
                            <form method="post" action="{{ route('admin.service-catalog.services.destroy', $svc) }}" style="display:inline;" onsubmit="return confirm('Delete this service?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" style="background:none;border:none;padding:0;color:#b91c1c;cursor:pointer;font-size:inherit;text-decoration:underline;">Delete</button>
                            </form>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    @empty
        <p>No categories yet. Add category first, then services.</p>
    @endforelse
@endsection
