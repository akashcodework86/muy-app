@extends('layouts.admin')

@section('title', 'Service catalog')
@section('heading', 'Service catalog')

@section('content')
    @php
        $hasQuickUpdateRoute = \Illuminate\Support\Facades\Route::has('admin.service-catalog.services.quick-update');
        $hasSetActive = \Illuminate\Support\Facades\Route::has('admin.service-catalog.services.set-active');
        $hasBulkActive = \Illuminate\Support\Facades\Route::has('admin.service-catalog.services.bulk-active');
        $catStats = $serviceCatalogStats ?? ['total' => 0, 'active' => 0];
        $catStatsInactive = max(0, (int) ($catStats['total'] ?? 0) - (int) ($catStats['active'] ?? 0));
        $catalogActiveRows = collect();
        $catalogInactiveRows = collect();
        foreach ($categories as $cat) {
            foreach ($cat->services as $s) {
                $row = ['name' => (string) $s->name, 'code' => (string) $s->code, 'category' => (string) $cat->name];
                if ($s->is_active) {
                    $catalogActiveRows->push($row);
                } else {
                    $catalogInactiveRows->push($row);
                }
            }
        }
        $catalogActiveRows = $catalogActiveRows->sortBy(fn ($r) => mb_strtolower($r['category'].' '.$r['name']))->values();
        $catalogInactiveRows = $catalogInactiveRows->sortBy(fn ($r) => mb_strtolower($r['category'].' '.$r['name']))->values();
        $bulkTotal = (int) ($catStats['total'] ?? 0);
        $bulkActiveCount = (int) ($catStats['active'] ?? 0);
        $bulkAllActive = $bulkTotal > 0 && $bulkActiveCount === $bulkTotal;
        $bulkMixed = $bulkTotal > 0 && $bulkActiveCount > 0 && $bulkActiveCount < $bulkTotal;
    @endphp
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

    @if (($catStats['total'] ?? 0) > 0)
        <div style="margin:0 0 1rem; padding:0.85rem 1rem; background:#fafafa; border:1px solid #e4e4e7; border-radius:10px;">
            <strong style="font-size:0.95rem; color:#18181b; display:block; margin-bottom:0.35rem;">Catalog availability</strong>
            <p style="margin:0 0 0.75rem; font-size:0.82rem; color:#52525b; line-height:1.45;">
                <strong>Active</strong> services can be used where the app checks this flag (e.g. selection lists).
                <strong>Inactive</strong> services stay in the catalog but are hidden or blocked for new use until turned back on.
            </p>
            <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(260px, 1fr)); gap:0.85rem;">
                <div style="background:#fff; border:1px solid #bbf7d0; border-radius:8px; padding:0.65rem 0.75rem; min-height:5rem;">
                    <div style="font-size:0.8rem; font-weight:700; color:#166534; margin-bottom:0.45rem;">
                        Active <span style="font-weight:600; color:#15803d;">({{ $catalogActiveRows->count() }})</span>
                    </div>
                    <ul style="margin:0; padding:0; list-style:none; max-height:14rem; overflow:auto; font-size:0.8rem; line-height:1.35;">
                        @foreach ($catalogActiveRows as $row)
                            <li style="padding:0.2rem 0; border-bottom:1px solid #f4f4f5;">
                                <span style="color:#18181b;">{{ $row['name'] }}</span>
                                <span style="color:#a1a1aa; font-size:0.75rem;"> · {{ $row['category'] }}</span>
                                <span style="color:#71717a; font-size:0.72rem;"> · <code style="font-size:0.72rem;">{{ $row['code'] }}</code></span>
                            </li>
                        @endforeach
                    </ul>
                </div>
                <div style="background:#fff; border:1px solid #fcd34d; border-radius:8px; padding:0.65rem 0.75rem; min-height:5rem;">
                    <div style="font-size:0.8rem; font-weight:700; color:#b45309; margin-bottom:0.45rem;">
                        Inactive <span style="font-weight:600; color:#c2410c;">({{ $catalogInactiveRows->count() }})</span>
                    </div>
                    @if ($catalogInactiveRows->isEmpty())
                        <p style="margin:0; font-size:0.8rem; color:#71717a;">None — every service is active.</p>
                    @else
                        <ul style="margin:0; padding:0; list-style:none; max-height:14rem; overflow:auto; font-size:0.8rem; line-height:1.35;">
                            @foreach ($catalogInactiveRows as $row)
                                <li style="padding:0.2rem 0; border-bottom:1px solid #fef3c7;">
                                    <span style="color:#18181b;">{{ $row['name'] }}</span>
                                    <span style="color:#a1a1aa; font-size:0.75rem;"> · {{ $row['category'] }}</span>
                                    <span style="color:#71717a; font-size:0.72rem;"> · <code style="font-size:0.72rem;">{{ $row['code'] }}</code></span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>
        </div>
    @endif

    @if ($hasBulkActive && ($catStats['total'] ?? 0) > 0)
        <div style="margin:0 0 1rem; padding:0.65rem 0.85rem; background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; display:flex; flex-wrap:wrap; align-items:center; gap:0.65rem 1rem;">
            <div style="display:flex; flex-wrap:wrap; align-items:center; gap:0.5rem;">
                <strong style="font-size:0.88rem; color:#0f172a;">All services</strong>
                <span style="font-size:0.8rem; color:#64748b;">{{ (int) $catStats['active'] }} active · {{ $catStatsInactive }} inactive</span>
            </div>
            <span style="flex:1; min-width:0.5rem;"></span>
            <form method="post" action="{{ route('admin.service-catalog.services.bulk-active') }}" id="svc-bulk-active-form" style="margin:0; display:flex; align-items:center; gap:0.5rem;">
                @csrf
                <input type="hidden" name="is_active" value="{{ $bulkAllActive ? '1' : '0' }}" id="svc-bulk-is-active">
                <label class="svc-toggle svc-toggle--bulk" title="{{ $bulkAllActive ? 'All active — turn all off' : ($bulkMixed ? 'Mixed — turn all on or all off' : 'All inactive — turn all on') }}">
                    <input
                        type="checkbox"
                        role="switch"
                        id="svc-bulk-toggle"
                        class="svc-toggle-input"
                        aria-label="All catalog services active"
                        @if ($bulkAllActive) checked @endif
                    >
                    <span class="svc-toggle-track" aria-hidden="true"><span class="svc-toggle-thumb"></span></span>
                </label>
                @if ($bulkMixed)
                    <span style="font-size:0.75rem; color:#64748b; white-space:nowrap;">Some on, some off</span>
                @endif
            </form>
        </div>
        @push('scripts')
        <script>
            (function () {
                var cb = document.getElementById('svc-bulk-toggle');
                var hidden = document.getElementById('svc-bulk-is-active');
                var form = document.getElementById('svc-bulk-active-form');
                if (!cb || !hidden || !form) return;
                var bulkMixed = @json($bulkMixed);
                var bulkAllActive = @json($bulkAllActive);
                function revert() {
                    if (bulkMixed) {
                        cb.checked = false;
                        cb.indeterminate = true;
                    } else {
                        cb.checked = bulkAllActive;
                        cb.indeterminate = false;
                    }
                }
                if (bulkMixed) {
                    cb.indeterminate = true;
                }
                cb.addEventListener('change', function () {
                    cb.indeterminate = false;
                    var wantOn = cb.checked;
                    hidden.value = wantOn ? '1' : '0';
                    var msg = wantOn
                        ? 'Turn every catalog service on?'
                        : 'Turn every catalog service off?';
                    if (!window.confirm(msg)) {
                        revert();
                        return;
                    }
                    form.submit();
                });
            })();
        </script>
        @endpush
    @endif

    @forelse ($categories as $category)
        <div style="background:#fff; border:1px solid #e4e4e7; border-radius:8px; margin-bottom:0.75rem; padding:0.8rem 0.95rem;">
            <div style="display:flex; flex-wrap:wrap; gap:0.35rem; align-items:center;">
                <strong style="font-size:1rem;">{{ $category->name }}</strong>
                <span style="font-size:0.78rem; color:#71717a;">({{ $category->slug }})</span>
                @if (($category->target_mode ?? 'service') === 'category')
                    <span style="font-size:0.72rem; color:#075985; background:#e0f2fe; border:1px solid #bae6fd; padding:0.1rem 0.45rem; border-radius:999px; font-weight:700;">Category target mode</span>
                @else
                    <span style="font-size:0.72rem; color:#475569; background:#f1f5f9; border:1px solid #e2e8f0; padding:0.1rem 0.45rem; border-radius:999px; font-weight:700;">Service target mode</span>
                @endif
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
                <ul style="margin:0.55rem 0 0; padding:0; list-style:none; font-size:0.85rem;">
                    @foreach ($category->services as $svc)
                        <li style="display:flex; align-items:flex-start; gap:0.6rem; margin-bottom:0.4rem; padding:0.5rem 0.6rem; border-radius:7px; border-left:3px solid {{ $svc->is_active ? '#22c55e' : '#f59e0b' }}; background:{{ $svc->is_active ? '#f0fdf4' : '#fffbeb' }};">
                            @if ($hasSetActive)
                                <form method="post" action="{{ route('admin.service-catalog.services.set-active', $svc) }}" class="svc-active-form" style="margin:0; flex-shrink:0; padding-top:0.12rem;">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="is_active" value="{{ $svc->is_active ? '1' : '0' }}" class="svc-active-hidden">
                                    <label class="svc-toggle" title="{{ $svc->is_active ? 'Active — click to turn off' : 'Inactive — click to turn on' }}">
                                        <input
                                            type="checkbox"
                                            role="switch"
                                            class="svc-toggle-input"
                                            aria-label="{{ $svc->is_active ? 'Active, switch to turn off' : 'Inactive, switch to turn on' }}"
                                            {{ $svc->is_active ? 'checked' : '' }}
                                            onchange="var f=this.closest('form'); f.querySelector('.svc-active-hidden').value=this.checked?'1':'0'; f.submit();"
                                        >
                                        <span class="svc-toggle-track" aria-hidden="true"><span class="svc-toggle-thumb"></span></span>
                                    </label>
                                </form>
                            @endif
                            <div style="flex:1; min-width:0;">
                                @if ($svc->is_active)
                                    <span style="font-size:0.7rem; font-weight:700; color:#15803d; background:#dcfce7; border:1px solid #86efac; padding:0.05rem 0.4rem; border-radius:999px; vertical-align:middle; margin-right:0.35rem;">Active</span>
                                @else
                                    <span style="font-size:0.7rem; font-weight:700; color:#b45309; background:#ffedd5; border:1px solid #fdba74; padding:0.05rem 0.4rem; border-radius:999px; vertical-align:middle; margin-right:0.35rem;">Inactive</span>
                                @endif
                                <code>{{ $svc->code }}</code> — {{ $svc->name }}
                                @include('admin.service-catalog.partials.reporting-tier-badge', ['svc' => $svc])
                                @if ($svc->requires_approval)
                                    <span style="background:#ede9fe; color:#5b21b6; border:1px solid #c4b5fd; padding:0 0.4rem; border-radius:999px; font-size:0.72rem; font-weight:600; margin-left:0.2rem;">Needs approval</span>
                                @endif
                                @if ($svc->requires_document)
                                    <span style="background:#e0f2fe; color:#075985; border:1px solid #bae6fd; padding:0 0.4rem; border-radius:999px; font-size:0.72rem; font-weight:600; margin-left:0.2rem;">Doc required</span>
                                @endif
                                @if ($svc->allows_multiple)<span style="color:#0369a1; margin-left:0.2rem;">· multiple</span>@endif
                                @if ($hasQuickUpdateRoute)
                                    — <a href="{{ route('admin.service-catalog.services.edit', $svc) }}">Edit</a>
                                    <span style="color:#d4d4d8;">|</span>
                                    <button
                                        type="button"
                                        class="js-quick-edit-open"
                                        data-id="{{ (int) $svc->id }}"
                                        data-name="{{ (string) $svc->name }}"
                                        data-code="{{ (string) $svc->code }}"
                                        data-category-id="{{ (int) $svc->service_category_id }}"
                                        data-sort-order="{{ (int) $svc->sort_order }}"
                                        data-is-active="{{ $svc->is_active ? '1' : '0' }}"
                                        data-allows-multiple="{{ $svc->allows_multiple ? '1' : '0' }}"
                                        data-requires-approval="{{ $svc->requires_approval ? '1' : '0' }}"
                                        data-requires-document="{{ $svc->requires_document ? '1' : '0' }}"
                                        data-has-pdf="{{ in_array('pdf', is_array($svc->allowed_document_types) ? $svc->allowed_document_types : [], true) ? '1' : '0' }}"
                                        data-has-image="{{ in_array('image', is_array($svc->allowed_document_types) ? $svc->allowed_document_types : [], true) ? '1' : '0' }}"
                                        data-reporting-tier="{{ (string) ($svc->reporting_tier ?? 'unset') }}"
                                        data-avg="{{ $svc->estimated_market_price_avg }}"
                                        data-min="{{ $svc->estimated_market_price_min }}"
                                        data-max="{{ $svc->estimated_market_price_max }}"
                                        data-note="{{ (string) ($svc->market_price_basis_note ?? '') }}"
                                        style="background:none;border:none;padding:0;color:#1d4ed8;cursor:pointer;font-size:inherit;text-decoration:underline;"
                                    >Quick edit</button>
                                @else
                                    — <a href="{{ route('admin.service-catalog.services.edit', $svc) }}">Edit</a>
                                @endif
                                <form method="post" action="{{ route('admin.service-catalog.services.destroy', $svc) }}" style="display:inline;" onsubmit="return confirm('Delete this service?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" style="background:none;border:none;padding:0;color:#b91c1c;cursor:pointer;font-size:inherit;text-decoration:underline;">Delete</button>
                                </form>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    @empty
        <p>No categories yet. Add category first, then services.</p>
    @endforelse

    @if ($hasQuickUpdateRoute)
    <div id="quickEditModal" style="display:none; position:fixed; inset:0; background:rgba(15,23,42,0.5); z-index:70; padding:1rem; overflow:auto;">
        <div style="max-width:46rem; margin:2rem auto; background:#fff; border-radius:10px; border:1px solid #e5e7eb; box-shadow:0 20px 50px rgba(0,0,0,0.2);">
            <form id="quickEditForm" method="post" action="">
                @csrf
                @method('PUT')
                <div style="padding:0.85rem 1rem; border-bottom:1px solid #e5e7eb; display:flex; justify-content:space-between; align-items:center; gap:0.75rem;">
                    <strong style="font-size:1rem;">Quick edit service</strong>
                    <button type="button" id="quickEditClose" style="background:none; border:none; font-size:1.2rem; line-height:1; cursor:pointer;">×</button>
                </div>
                <div style="padding:1rem; display:grid; grid-template-columns:repeat(auto-fit,minmax(14rem,1fr)); gap:0.7rem;">
                    <div>
                        <label style="display:block; font-size:0.8rem; margin-bottom:0.2rem;">Category</label>
                        <select name="service_category_id" id="qe_service_category_id" required style="width:100%; padding:0.45rem; border:1px solid #d1d5db; border-radius:6px;">
                            @foreach ($categories as $catOpt)
                                <option value="{{ (int) $catOpt->id }}">{{ $catOpt->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label style="display:block; font-size:0.8rem; margin-bottom:0.2rem;">Name</label>
                        <input name="name" id="qe_name" required maxlength="191" style="width:100%; padding:0.45rem; border:1px solid #d1d5db; border-radius:6px;">
                    </div>
                    <div>
                        <label style="display:block; font-size:0.8rem; margin-bottom:0.2rem;">Code</label>
                        <input name="code" id="qe_code" pattern="[a-z0-9_]+" required maxlength="96" style="width:100%; padding:0.45rem; border:1px solid #d1d5db; border-radius:6px;">
                    </div>
                    <div>
                        <label style="display:block; font-size:0.8rem; margin-bottom:0.2rem;">Sort order</label>
                        <input type="number" min="0" name="sort_order" id="qe_sort_order" style="width:100%; padding:0.45rem; border:1px solid #d1d5db; border-radius:6px;">
                    </div>
                    <div>
                        <label style="display:block; font-size:0.8rem; margin-bottom:0.2rem;">Reporting tier</label>
                        <select name="reporting_tier" id="qe_reporting_tier" required style="width:100%; padding:0.45rem; border:1px solid #d1d5db; border-radius:6px;">
                            <option value="unset">Unset</option>
                            <option value="key">Key</option>
                            <option value="non_key">Non-Key</option>
                        </select>
                    </div>
                    <div>
                        <label style="display:block; font-size:0.8rem; margin-bottom:0.2rem;">Avg market price (INR)</label>
                        <input type="number" min="0" step="0.01" name="estimated_market_price_avg" id="qe_avg" style="width:100%; padding:0.45rem; border:1px solid #d1d5db; border-radius:6px;">
                    </div>
                    <div>
                        <label style="display:block; font-size:0.8rem; margin-bottom:0.2rem;">Min market price (INR)</label>
                        <input type="number" min="0" step="0.01" name="estimated_market_price_min" id="qe_min" style="width:100%; padding:0.45rem; border:1px solid #d1d5db; border-radius:6px;">
                    </div>
                    <div>
                        <label style="display:block; font-size:0.8rem; margin-bottom:0.2rem;">Max market price (INR)</label>
                        <input type="number" min="0" step="0.01" name="estimated_market_price_max" id="qe_max" style="width:100%; padding:0.45rem; border:1px solid #d1d5db; border-radius:6px;">
                    </div>
                </div>
                <div style="padding:0 1rem 0.85rem;">
                    <label style="display:block; font-size:0.8rem; margin-bottom:0.2rem;">Price basis note</label>
                    <textarea name="market_price_basis_note" id="qe_note" rows="2" maxlength="1000" style="width:100%; padding:0.45rem; border:1px solid #d1d5db; border-radius:6px;"></textarea>
                </div>
                <div style="padding:0 1rem 1rem; display:flex; flex-wrap:wrap; gap:1rem;">
                    <label><input type="hidden" name="is_active" value="0"><input type="checkbox" id="qe_active" name="is_active" value="1"> Active</label>
                    <label><input type="hidden" name="allows_multiple" value="0"><input type="checkbox" id="qe_multiple" name="allows_multiple" value="1"> Multiple cases</label>
                    <label><input type="hidden" name="requires_approval" value="0"><input type="checkbox" id="qe_approval" name="requires_approval" value="1"> Needs approval</label>
                    <label><input type="hidden" name="requires_document" value="0"><input type="checkbox" id="qe_doc" name="requires_document" value="1"> Requires document</label>
                    <label><input type="checkbox" id="qe_doc_pdf" name="allowed_document_types[]" value="pdf"> PDF</label>
                    <label><input type="checkbox" id="qe_doc_image" name="allowed_document_types[]" value="image"> Image</label>
                </div>
                <div style="padding:0 1rem 1rem; display:flex; justify-content:flex-end; gap:0.6rem;">
                    <button type="button" id="quickEditCancel" style="border:1px solid #d1d5db; background:#fff; padding:0.45rem 0.8rem; border-radius:6px; cursor:pointer;">Cancel</button>
                    <button type="submit" style="border:none; background:#111827; color:#fff; padding:0.45rem 0.9rem; border-radius:6px; cursor:pointer;">Save changes</button>
                </div>
            </form>
        </div>
    </div>
    @endif

    @if ($hasQuickUpdateRoute)
    <script>
        (function () {
            const modal = document.getElementById('quickEditModal');
            const form = document.getElementById('quickEditForm');
            if (!modal || !form) return;

            const quickUpdateTemplate = @json(route('admin.service-catalog.services.quick-update', ['service' => '__SERVICE_ID__']));
            const catSelect = document.getElementById('qe_service_category_id');
            const closeBtn = document.getElementById('quickEditClose');
            const cancelBtn = document.getElementById('quickEditCancel');

            function setOpen(open) {
                modal.style.display = open ? 'block' : 'none';
                if (open) {
                    document.body.style.overflow = 'hidden';
                } else {
                    document.body.style.overflow = '';
                }
            }

            function checkbox(id, val) {
                const el = document.getElementById(id);
                if (el) el.checked = !!val;
            }

            document.querySelectorAll('.js-quick-edit-open').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    const id = btn.dataset.id || '';
                    if (!id) return;

                    form.action = quickUpdateTemplate.replace('__SERVICE_ID__', String(id));
                    catSelect.value = btn.dataset.categoryId || '';
                    document.getElementById('qe_name').value = btn.dataset.name || '';
                    document.getElementById('qe_code').value = btn.dataset.code || '';
                    document.getElementById('qe_sort_order').value = btn.dataset.sortOrder || 0;
                    document.getElementById('qe_reporting_tier').value = btn.dataset.reportingTier || 'unset';
                    document.getElementById('qe_avg').value = btn.dataset.avg || '';
                    document.getElementById('qe_min').value = btn.dataset.min || '';
                    document.getElementById('qe_max').value = btn.dataset.max || '';
                    document.getElementById('qe_note').value = btn.dataset.note || '';
                    checkbox('qe_active', btn.dataset.isActive === '1');
                    checkbox('qe_multiple', btn.dataset.allowsMultiple === '1');
                    checkbox('qe_approval', btn.dataset.requiresApproval === '1');
                    checkbox('qe_doc', btn.dataset.requiresDocument === '1');
                    checkbox('qe_doc_pdf', btn.dataset.hasPdf === '1');
                    checkbox('qe_doc_image', btn.dataset.hasImage === '1');

                    setOpen(true);
                });
            });

            closeBtn && closeBtn.addEventListener('click', function () { setOpen(false); });
            cancelBtn && cancelBtn.addEventListener('click', function () { setOpen(false); });
            modal.addEventListener('click', function (e) {
                if (e.target === modal) setOpen(false);
            });
        })();
    </script>
    @endif

@push('styles')
<style>
    .svc-toggle {
        position: relative;
        display: inline-block;
        cursor: pointer;
        vertical-align: middle;
        -webkit-tap-highlight-color: transparent;
    }
    .svc-toggle-input {
        position: absolute;
        opacity: 0;
        width: 0;
        height: 0;
        margin: 0;
    }
    .svc-toggle-input:focus-visible + .svc-toggle-track {
        outline: 2px solid #2563eb;
        outline-offset: 3px;
    }
    .svc-toggle-track {
        display: block;
        width: 2.85rem;
        height: 1.5rem;
        border-radius: 999px;
        background: #d4d4d8;
        transition: background 0.18s ease;
        position: relative;
        box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.08);
    }
    .svc-toggle-input:checked + .svc-toggle-track {
        background: #22c55e;
    }
    .svc-toggle-input:indeterminate + .svc-toggle-track {
        background: #94a3b8;
    }
    .svc-toggle-input:indeterminate + .svc-toggle-track .svc-toggle-thumb {
        transform: translateX(0.675rem);
    }
    .svc-toggle-thumb {
        position: absolute;
        width: 1.2rem;
        height: 1.2rem;
        border-radius: 50%;
        background: #fff;
        top: 0.15rem;
        left: 0.15rem;
        transition: transform 0.2s ease;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.2);
    }
    .svc-toggle-input:checked + .svc-toggle-track .svc-toggle-thumb {
        transform: translateX(1.35rem);
    }
</style>
@endpush
@endsection
