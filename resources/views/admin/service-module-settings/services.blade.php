@extends('layouts.admin')

@section('title', 'Service module — per-service')
@section('heading', 'Service module settings')

@section('content')
    @include('admin.service-module-settings._nav', ['active' => $serviceModuleNavActive ?? 'services'])

    @php
        $svcAllowStats = ['allow' => 0, 'pause' => 0];
        foreach ($serviceGroups ?? [] as $g) {
            foreach ($g['services'] as $svc) {
                $oldMap = old('accepts_new_service_cases');
                $on = (bool) $svc->accepts_new_service_cases;
                if (is_array($oldMap)) {
                    if (array_key_exists($svc->id, $oldMap)) {
                        $on = (bool) $oldMap[$svc->id];
                    } elseif (array_key_exists((string) $svc->id, $oldMap)) {
                        $on = (bool) $oldMap[(string) $svc->id];
                    }
                }
                $on ? $svcAllowStats['allow']++ : $svcAllowStats['pause']++;
            }
        }
        $svcTotal = $svcAllowStats['allow'] + $svcAllowStats['pause'];
    @endphp

    <p style="font-size:0.9rem; color:#52525b; margin:0 0 1rem; max-width:55rem;">
        Choose which catalog services district staff can <strong>start new</strong> maker&ndash;checker cases for.
        This does not change catalog <em>Active</em> or targets; draft and in-flight cases can still be submitted.
    </p>

    @if (session('status'))
        <p style="background:#dcfce7; color:#166534; padding:0.5rem 0.75rem; border-radius:6px; font-size:0.88rem; margin:0.5rem 0 1rem; max-width:55rem;">{{ session('status') }}</p>
    @endif
    @if ($errors->any())
        <ul style="color:#b91c1c; margin:0 0 1rem; padding-left:1.2rem; font-size:0.88rem;">
            @foreach ($errors->all() as $e)
                <li>{{ $e }}</li>
            @endforeach
        </ul>
    @endif

    <form id="svc-allow-form" method="POST" action="{{ route('admin.service-module-settings.services.update') }}" style="max-width:55rem;">
        @csrf
        @method('PUT')

        @if (!empty($serviceGroups))
            <div style="display:flex; flex-wrap:wrap; gap:0.65rem; margin-bottom:1rem; max-width:55rem;">
                <div style="flex:1; min-width:7.5rem; border:1px solid #bbf7d0; background:#f0fdf4; border-radius:10px; padding:0.55rem 0.75rem;">
                    <div style="font-size:0.68rem; font-weight:600; color:#166534; text-transform:uppercase; letter-spacing:0.04em;">Allowing new cases</div>
                    <div id="svc-stat-allow" style="font-size:1.25rem; font-weight:700; color:#15803d; line-height:1.2;">{{ $svcAllowStats['allow'] }}</div>
                </div>
                <div style="flex:1; min-width:7.5rem; border:1px solid #fde68a; background:#fffbeb; border-radius:10px; padding:0.55rem 0.75rem;">
                    <div style="font-size:0.68rem; font-weight:600; color:#b45309; text-transform:uppercase; letter-spacing:0.04em;">Paused (new cases)</div>
                    <div id="svc-stat-pause" style="font-size:1.25rem; font-weight:700; color:#c2410c; line-height:1.2;">{{ $svcAllowStats['pause'] }}</div>
                </div>
                <div style="flex:1; min-width:7.5rem; border:1px solid #e4e4e7; background:#fafafa; border-radius:10px; padding:0.55rem 0.75rem;">
                    <div style="font-size:0.68rem; font-weight:600; color:#52525b; text-transform:uppercase; letter-spacing:0.04em;">Total services</div>
                    <div id="svc-stat-total" style="font-size:1.25rem; font-weight:700; color:#18181b; line-height:1.2;">{{ $svcTotal }}</div>
                </div>
            </div>

            <div style="display:flex; flex-wrap:wrap; gap:0.65rem; align-items:center; margin-bottom:0.85rem; max-width:55rem;">
                <label style="display:inline-flex; align-items:center; gap:0.45rem; cursor:pointer; font-size:0.86rem; color:#18181b; font-weight:600;">
                    <input type="checkbox" id="svc-select-all" style="width:1.05rem; height:1.05rem; accent-color:#4f46e5; cursor:pointer;">
                    Select all
                </label>
                <div style="display:flex; flex-wrap:wrap; gap:0.4rem; align-items:center; flex:1; min-width:12rem;">
                    <input type="search" id="svc-search" autocomplete="off" placeholder="Search services or category…" style="flex:1; min-width:11rem; border:1px solid #d4d4d8; border-radius:8px; padding:0.45rem 0.65rem; font-size:0.86rem;">
                    <button type="button" id="svc-search-clear" style="border:1px solid #d4d4d8; background:#fff; color:#3f3f46; padding:0.45rem 0.75rem; border-radius:8px; font-size:0.82rem; font-weight:600; cursor:pointer;">
                        Clear
                    </button>
                </div>
            </div>
        @endif

        <section style="background:#fff; border:1px solid #e4e4e7; border-radius:12px; padding:1rem 1.1rem; margin-bottom:1rem;">
            @if (empty($serviceGroups))
                <p style="font-size:0.86rem; color:#71717a;">No services in the catalog.</p>
            @else
                @foreach ($serviceGroups as $group)
                    @php
                        $category = $group['category'];
                        $groupServices = $group['services'];
                        $categorySearch = strtolower($category?->name ?? 'uncategorized');
                    @endphp
                    <details class="js-svc-group" data-category-name="{{ $categorySearch }}" {!! $loop->first ? 'open' : '' !!} style="border:1px solid #e4e4e7; border-radius:10px; margin-bottom:0.55rem; padding:0.35rem 0.65rem; background:#fafafa;">
                        <summary class="js-svc-group-summary" style="cursor:pointer; font-weight:600; font-size:0.9rem; color:#18181b; user-select:none;">
                            {{ $category?->name ?? 'Uncategorized' }}
                            <span style="font-weight:500; color:#71717a; font-size:0.78rem;">({{ $groupServices->count() }})</span>
                        </summary>
                        <ul class="js-svc-list" style="list-style:none; margin:0.5rem 0 0; padding:0;">
                            @foreach ($groupServices as $svc)
                                @php
                                    $oldMap = old('accepts_new_service_cases');
                                    $acceptsVal = $svc->accepts_new_service_cases;
                                    if (is_array($oldMap)) {
                                        if (array_key_exists($svc->id, $oldMap)) {
                                            $acceptsVal = (bool) $oldMap[$svc->id];
                                        } elseif (array_key_exists((string) $svc->id, $oldMap)) {
                                            $acceptsVal = (bool) $oldMap[(string) $svc->id];
                                        }
                                    }
                                    $searchBlob = strtolower($categorySearch.' '.$svc->name);
                                @endphp
                                <li class="js-svc-row" data-search-text="{{ $searchBlob }}" style="display:flex; align-items:center; justify-content:space-between; gap:0.75rem; padding:0.35rem 0; border-top:1px solid #f4f4f5; font-size:0.86rem;">
                                    <span style="color:#3f3f46;">{{ $svc->name }}</span>
                                    <label style="display:inline-flex; align-items:center; gap:0.4rem; cursor:pointer; flex-shrink:0;">
                                        <input type="hidden" name="accepts_new_service_cases[{{ $svc->id }}]" value="0">
                                        <input type="checkbox" class="js-svc-allow-cb" name="accepts_new_service_cases[{{ $svc->id }}]" value="1" @checked($acceptsVal) style="width:1rem; height:1rem; accent-color:#4f46e5; cursor:pointer;">
                                        <span style="font-size:0.78rem; color:#52525b;">Allow new cases</span>
                                    </label>
                                </li>
                            @endforeach
                        </ul>
                    </details>
                @endforeach
            @endif
        </section>

        <div style="display:flex; justify-content:flex-end; gap:0.5rem;">
            <button type="submit" style="background:#4f46e5; color:#fff; border:none; padding:0.6rem 1.25rem; border-radius:8px; font-weight:600; font-size:0.9rem; cursor:pointer;">
                Save per-service settings
            </button>
        </div>
    </form>

    @if (!empty($serviceGroups))
        @push('scripts')
            <script>
                (function () {
                    const form = document.getElementById('svc-allow-form');
                    if (!form) return;

                    const cbs = function () {
                        return Array.from(form.querySelectorAll('.js-svc-allow-cb'));
                    };

                    const statAllow = document.getElementById('svc-stat-allow');
                    const statPause = document.getElementById('svc-stat-pause');
                    const statTotal = document.getElementById('svc-stat-total');
                    const selectAll = document.getElementById('svc-select-all');
                    const searchInput = document.getElementById('svc-search');
                    const searchClear = document.getElementById('svc-search-clear');

                    function updateStats() {
                        const list = cbs();
                        let allow = 0;
                        list.forEach(function (cb) {
                            if (cb.checked) allow++;
                        });
                        const total = list.length;
                        const pause = total - allow;
                        if (statAllow) statAllow.textContent = String(allow);
                        if (statPause) statPause.textContent = String(pause);
                        if (statTotal) statTotal.textContent = String(total);
                    }

                    function syncSelectAll() {
                        if (!selectAll) return;
                        const list = cbs();
                        const n = list.length;
                        if (n === 0) {
                            selectAll.checked = false;
                            selectAll.indeterminate = false;
                            return;
                        }
                        const checked = list.filter(function (cb) {
                            return cb.checked;
                        }).length;
                        selectAll.checked = checked === n;
                        selectAll.indeterminate = checked > 0 && checked < n;
                    }

                    function applySearch() {
                        const q = (searchInput && searchInput.value ? searchInput.value : '').trim().toLowerCase();
                        form.querySelectorAll('.js-svc-group').forEach(function (details) {
                            let visibleRows = 0;
                            details.querySelectorAll('.js-svc-row').forEach(function (li) {
                                const hay = (li.getAttribute('data-search-text') || '');
                                const show = !q || hay.includes(q);
                                li.style.display = show ? '' : 'none';
                                if (show) visibleRows++;
                            });
                            details.style.display = visibleRows > 0 ? '' : 'none';
                        });
                    }

                    form.addEventListener('change', function (e) {
                        if (e.target && e.target.classList && e.target.classList.contains('js-svc-allow-cb')) {
                            updateStats();
                            syncSelectAll();
                        }
                    });

                    if (selectAll) {
                        selectAll.addEventListener('change', function () {
                            const on = selectAll.checked;
                            cbs().forEach(function (cb) {
                                cb.checked = on;
                            });
                            updateStats();
                            selectAll.indeterminate = false;
                        });
                    }

                    if (searchInput) {
                        searchInput.addEventListener('input', applySearch);
                        searchInput.addEventListener('search', applySearch);
                    }
                    if (searchClear) {
                        searchClear.addEventListener('click', function () {
                            if (searchInput) searchInput.value = '';
                            applySearch();
                            if (searchInput) searchInput.focus();
                        });
                    }

                    updateStats();
                    syncSelectAll();
                })();
            </script>
        @endpush
    @endif
@endsection
