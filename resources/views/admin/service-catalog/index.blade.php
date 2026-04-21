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

    <p style="font-size:0.9rem; color:#52525b; margin-top:0;">Top-level <strong>categories</strong>, then <strong>subcategories</strong>. <strong>Services</strong> must sit under a subcategory (for staff case picker). Each service can be tagged <strong>Key</strong> / <strong>Non‑Key</strong> / <strong>Unset</strong> for reporting only (Unset counts with Non‑Key in roll-ups until you set it).</p>

    <p style="margin:0.75rem 0 1rem; display:flex; flex-wrap:wrap; gap:0.5rem;">
        <a href="{{ route('admin.service-catalog.categories.create') }}" style="display:inline-block; background:#18181b; color:#fff; padding:0.45rem 0.85rem; border-radius:6px; text-decoration:none; font-size:0.9rem;">Add top category</a>
        <a href="{{ route('admin.service-catalog.services.create') }}" style="display:inline-block; background:#fff; color:#18181b; border:1px solid #d4d4d8; padding:0.45rem 0.85rem; border-radius:6px; text-decoration:none; font-size:0.9rem;">Add service</a>
    </p>

    @forelse ($roots as $root)
        <div style="background:#fff; border:1px solid #e4e4e7; border-radius:8px; padding:1rem; margin-bottom:1rem;">
            <div style="display:flex; flex-wrap:wrap; justify-content:space-between; gap:0.5rem; align-items:center;">
                <strong style="font-size:1rem;">{{ $root->name }}</strong>
                <span style="font-size:0.8rem; color:#71717a;">{{ $root->slug }}</span>
                <span style="flex:1"></span>
                <a href="{{ route('admin.service-catalog.categories.create', ['parent_id' => $root->id]) }}" style="font-size:0.85rem;">Add subcategory</a>
                <span style="color:#d4d4d8;">|</span>
                <a href="{{ route('admin.service-catalog.categories.edit', $root) }}" style="font-size:0.85rem;">Edit</a>
                <span style="color:#d4d4d8;">|</span>
                <form method="post" action="{{ route('admin.service-catalog.categories.destroy', $root) }}" style="display:inline;" onsubmit="return confirm('Delete this category? Only if empty.');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" style="background:none;border:none;padding:0;color:#b91c1c;cursor:pointer;font-size:0.85rem;text-decoration:underline;">Delete</button>
                </form>
            </div>
            @if ($root->services->isNotEmpty())
                <p style="font-size:0.8rem; color:#b45309; margin:0.5rem 0 0;">Services should be under a subcategory, not directly on a top category. Move these:</p>
                <ul style="margin:0.25rem 0 0; font-size:0.85rem;">
                    @foreach ($root->services as $svc)
                        <li>
                            <code>{{ $svc->code }}</code> — {{ $svc->name }}
                            @include('admin.service-catalog.partials.reporting-tier-badge', ['svc' => $svc])
                            — <a href="{{ route('admin.service-catalog.services.edit', $svc) }}">Edit</a>
                        </li>
                    @endforeach
                </ul>
            @endif
            @foreach ($root->children as $child)
                <div style="margin-top:0.85rem; padding:0.75rem; background:#fafafa; border-radius:6px; border:1px solid #f4f4f5;">
                    <div style="display:flex; flex-wrap:wrap; justify-content:space-between; gap:0.35rem; align-items:center;">
                        <span><strong>{{ $child->name }}</strong> <span style="color:#71717a;font-size:0.8rem;">({{ $child->slug }})</span></span>
                        <a href="{{ route('admin.service-catalog.services.create', ['service_category_id' => $child->id]) }}" style="font-size:0.82rem;">Add service here</a>
                        <span style="color:#d4d4d8;">|</span>
                        <a href="{{ route('admin.service-catalog.categories.edit', $child) }}" style="font-size:0.82rem;">Edit</a>
                        <span style="color:#d4d4d8;">|</span>
                        <form method="post" action="{{ route('admin.service-catalog.categories.destroy', $child) }}" style="display:inline;" onsubmit="return confirm('Delete this subcategory?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" style="background:none;border:none;padding:0;color:#b91c1c;cursor:pointer;font-size:0.82rem;text-decoration:underline;">Delete</button>
                        </form>
                    </div>
                    @if ($child->services->isEmpty())
                        <p style="margin:0.35rem 0 0; font-size:0.8rem; color:#71717a;">No services yet.</p>
                    @else
                        <ul style="margin:0.5rem 0 0; padding-left:1.1rem; font-size:0.85rem;">
                            @foreach ($child->services as $svc)
                                <li style="margin-bottom:0.25rem;">
                                    <code>{{ $svc->code }}</code> — {{ $svc->name }}
                                    @include('admin.service-catalog.partials.reporting-tier-badge', ['svc' => $svc])
                                    @if ($svc->requires_approval)
                                        <span title="Cases need SPOC approval before becoming active" style="background:#ede9fe; color:#5b21b6; border:1px solid #c4b5fd; padding:0 0.4rem; border-radius:999px; font-size:0.72rem; font-weight:600; margin-left:0.2rem;">Needs approval</span>
                                    @endif
                                    @if ($svc->requires_document)
                                        <span title="Staff must attach document on submission" style="background:#e0f2fe; color:#075985; border:1px solid #bae6fd; padding:0 0.4rem; border-radius:999px; font-size:0.72rem; font-weight:600; margin-left:0.2rem;">Doc required</span>
                                    @endif
                                    @if ($svc->allows_multiple)<span style="color:#0369a1; margin-left:0.2rem;">· multiple</span>@endif
                                    @if (! $svc->is_active)<span style="color:#b45309; margin-left:0.2rem;">· inactive</span>@endif
                                    — <a href="{{ route('admin.service-catalog.services.edit', $svc) }}">Edit</a>
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
            @endforeach
            @if ($root->children->isEmpty())
                <p style="margin:0.5rem 0 0; font-size:0.85rem; color:#71717a;">No subcategories. Add one to attach services.</p>
            @endif
        </div>
    @empty
        <p>No categories yet. Add a top category, then subcategories, then services.</p>
    @endforelse
@endsection
