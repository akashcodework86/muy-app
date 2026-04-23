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

    <div style="margin:0.75rem 0 1rem; display:flex; flex-wrap:wrap; gap:0.5rem; align-items:center;">
        <a href="{{ route('admin.service-catalog.categories.create') }}" style="display:inline-block; background:#18181b; color:#fff; padding:0.45rem 0.85rem; border-radius:6px; text-decoration:none; font-size:0.9rem;">Add top category</a>
        <a href="{{ route('admin.service-catalog.services.create') }}" style="display:inline-block; background:#fff; color:#18181b; border:1px solid #d4d4d8; padding:0.45rem 0.85rem; border-radius:6px; text-decoration:none; font-size:0.9rem;">Add service</a>
        <span style="flex:1; min-width:0.5rem;"></span>
        <label for="sc-search" class="sc-sr">Search catalog</label>
        <input type="search" id="sc-search" name="q" placeholder="Filter by name, code, slug…" autocomplete="off"
            style="min-width:12rem; max-width:22rem; flex:1; padding:0.45rem 0.75rem; border:1px solid #d4d4d8; border-radius:8px; font-size:0.9rem;" />
        <button type="button" id="sc-expand-all" style="background:#f4f4f5; border:1px solid #d4d4d8; color:#18181b; padding:0.4rem 0.7rem; border-radius:6px; font-size:0.82rem; cursor:pointer;">Expand all</button>
        <button type="button" id="sc-collapse-all" style="background:#f4f4f5; border:1px solid #d4d4d8; color:#18181b; padding:0.4rem 0.7rem; border-radius:6px; font-size:0.82rem; cursor:pointer;">Collapse all</button>
    </div>

    @forelse ($roots as $root)
        @php
            $countChildSvcs = $root->children->sum(fn ($c) => $c->services->count());
            $countRootSvcs = $root->services->count();
            $rootSvcTotal = $countChildSvcs + $countRootSvcs;
            $searchBlob = mb_strtolower(
                $root->name.' '.$root->slug.' '.
                $root->children->map(fn ($c) => $c->name.' '.$c->slug)->implode(' ').' '.
                $root->children->flatMap(fn ($c) => $c->services->map(fn ($s) => $s->name.' '.$s->code))->implode(' ').' '.
                $root->services->map(fn ($s) => $s->name.' '.$s->code)->implode(' ')
            );
        @endphp
        <details class="sc-root" data-sc-root="1" data-sc-blob="{{ e($searchBlob) }}"
            style="background:#fff; border:1px solid #e4e4e7; border-radius:8px; margin-bottom:0.75rem;">
            <summary style="list-style:none; cursor:pointer; padding:0.85rem 1rem; display:flex; flex-wrap:wrap; align-items:center; gap:0.4rem; font-weight:600; user-select:none;">
                <span class="sc-chev" style="color:#64748b; font-size:0.85rem; margin-right:0.15rem; transition:transform 0.15s;">▸</span>
                <span style="font-size:1rem; color:#0f172a;">{{ $root->name }}</span>
                <span style="font-size:0.8rem; color:#71717a;">({{ $root->slug }})</span>
                <span style="font-size:0.75rem; color:#64748b; background:#f1f5f9; padding:0.15rem 0.5rem; border-radius:999px; font-weight:600;">{{ $rootSvcTotal }} service{{ $rootSvcTotal === 1 ? '' : 's' }}</span>
                <span style="flex:1"></span>
                <span class="sc-summary-actions" style="font-size:0.85rem; font-weight:400; color:#0d9488;" onclick="event.stopPropagation()">
                    <a href="{{ route('admin.service-catalog.categories.create', ['parent_id' => $root->id]) }}">Add subcategory</a>
                    <span style="color:#d4d4d8;">|</span>
                    <a href="{{ route('admin.service-catalog.categories.edit', $root) }}">Edit</a>
                    <span style="color:#d4d4d8;">|</span>
                    <form method="post" action="{{ route('admin.service-catalog.categories.destroy', $root) }}" style="display:inline;" onsubmit="return confirm('Delete this category? Only if empty.');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" style="background:none;border:none;padding:0;color:#b91c1c;cursor:pointer;font-size:0.85rem;text-decoration:underline;">Delete</button>
                    </form>
                </span>
            </summary>
            <div style="padding:0 1rem 1rem 1rem; border-top:1px solid #f4f4f5;">
            @if ($root->services->isNotEmpty())
                <p class="sc-orphan-warning" style="font-size:0.8rem; color:#b45309; margin:0.65rem 0 0.35rem;">Services should be under a subcategory, not directly on a top category. Move these:</p>
                <ul class="sc-orphan-ul" style="margin:0.25rem 0 0; font-size:0.85rem; padding-left:1.1rem;">
                    @foreach ($root->services as $svc)
                        <li class="sc-svc" data-sc-text="{{ mb_strtolower($svc->name.' '.$svc->code) }}">
                            <code>{{ $svc->code }}</code> — {{ $svc->name }}
                            @include('admin.service-catalog.partials.reporting-tier-badge', ['svc' => $svc])
                            — <a href="{{ route('admin.service-catalog.services.edit', $svc) }}">Edit</a>
                        </li>
                    @endforeach
                </ul>
            @endif
            @foreach ($root->children as $child)
                @php
                    $subBlob = mb_strtolower(
                        $child->name.' '.$child->slug.' '.
                        $child->services->map(fn ($s) => $s->name.' '.$s->code)->implode(' ')
                    );
                @endphp
                <div class="sc-sub" data-sc-blob="{{ e($subBlob) }}" style="margin-top:0.85rem; padding:0.75rem; background:#fafafa; border-radius:6px; border:1px solid #f4f4f5;">
                    <div style="display:flex; flex-wrap:wrap; justify-content:space-between; gap:0.35rem; align-items:center;">
                        <span><strong>{{ $child->name }}</strong> <span style="color:#71717a;font-size:0.8rem;">({{ $child->slug }})</span></span>
                        <span style="font-size:0.78rem; color:#64748b;">{{ $child->services->count() }} svc</span>
                        <span style="flex:1"></span>
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
                        <p class="sc-sub-empty" style="margin:0.35rem 0 0; font-size:0.8rem; color:#71717a;">No services yet.</p>
                    @else
                        <ul style="margin:0.5rem 0 0; padding-left:1.1rem; font-size:0.85rem;">
                            @foreach ($child->services as $svc)
                                <li class="sc-svc" data-sc-text="{{ mb_strtolower($svc->name.' '.$svc->code) }}" style="margin-bottom:0.25rem;">
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
        </details>
    @empty
        <p>No categories yet. Add a top category, then subcategories, then services.</p>
    @endforelse

    @if ($roots->isNotEmpty())
    @push('scripts')
    <script>
    (function () {
        const input = document.getElementById('sc-search');
        const roots = function () { return document.querySelectorAll('details.sc-root'); };
        if (!input) return;

        function apply() {
            const q = (input.value || '').trim().toLowerCase();
            const showAll = q === '';
            roots().forEach(function (d) {
                const blob = (d.getAttribute('data-sc-blob') || '').toLowerCase();
                const rootMatch = showAll || blob.indexOf(q) !== -1;
                d.querySelectorAll('.sc-svc').forEach(function (li) {
                    const t = (li.getAttribute('data-sc-text') || '').toLowerCase();
                    li.style.display = (showAll || t.indexOf(q) !== -1) ? '' : 'none';
                });
                d.querySelectorAll('.sc-sub').forEach(function (sub) {
                    const sb = (sub.getAttribute('data-sc-blob') || '').toLowerCase();
                    const subNameMatch = !showAll && sb.indexOf(q) !== -1;
                    const anySvc = Array.prototype.some.call(sub.querySelectorAll('.sc-svc'), function (li) { return li.style.display !== 'none'; });
                    sub.style.display = (showAll || subNameMatch || anySvc) ? '' : 'none';
                });
                d.querySelectorAll('.sc-orphan-ul').forEach(function (ul) {
                    const anyOrphan = Array.prototype.some.call(ul.querySelectorAll('.sc-svc'), function (li) { return li.style.display !== 'none'; });
                    const show = showAll || anyOrphan;
                    ul.style.display = show ? '' : 'none';
                    const warn = d.querySelector('.sc-orphan-warning');
                    if (warn) warn.style.display = show ? '' : 'none';
                });
                const any = rootMatch || Array.prototype.some.call(d.querySelectorAll('.sc-svc'), function (li) { return li.style.display !== 'none'; });
                d.style.display = any ? '' : 'none';
                if (!showAll && any) d.open = true;
            });
        }

        input.addEventListener('input', apply);
        input.addEventListener('search', apply);

        const ex = document.getElementById('sc-expand-all');
        const cl = document.getElementById('sc-collapse-all');
        if (ex) ex.addEventListener('click', function () { roots().forEach(function (d) { if (d.style.display !== 'none') d.open = true; }); });
        if (cl) cl.addEventListener('click', function () { roots().forEach(function (d) { d.open = false; }); });
    })();
    </script>
    @endpush
    @endif

    @if ($roots->isNotEmpty())
    @push('styles')
    <style>
        .sc-sr { position:absolute; width:1px; height:1px; padding:0; margin:-1px; overflow:hidden; clip:rect(0,0,0,0); border:0; }
        details.sc-root[open] > summary .sc-chev { display:inline-block; transform: rotate(90deg); }
    </style>
    @endpush
    @endif
@endsection
