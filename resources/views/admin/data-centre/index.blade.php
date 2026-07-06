@extends('layouts.admin')

@section('title', 'Program Data Centre')
@section('heading', 'Program Data Centre')

@push('styles')
<style>
/* ── Page layout ───────────────────────────────────────────── */
.dc-page { max-width: 1280px; margin: 0 auto; padding: 1.25rem 1rem 3rem; }

/* ── Top bar ───────────────────────────────────────────────── */
.dc-topbar { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:.75rem; margin-bottom:1.5rem; }
.dc-topbar__title { font-size:1.2rem; font-weight:700; color:#1e293b; }
.dc-topbar__meta  { font-size:.78rem; color:#64748b; margin-top:.15rem; }
.dc-topbar__right { display:flex; align-items:center; gap:.5rem; flex-wrap:wrap; }

/* ── Availability badges ───────────────────────────────────── */
.dc-badge { display:inline-flex; align-items:center; gap:.3rem; font-size:.72rem; font-weight:600; padding:.2rem .6rem; border-radius:999px; }
.dc-badge--ok  { background:#dcfce7; color:#166534; }
.dc-badge--err { background:#fee2e2; color:#991b1b; }

/* ── Summary cards ─────────────────────────────────────────── */
.dc-cards { display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:1rem; margin-bottom:2rem; }
.dc-card  { background:#fff; border:1px solid #e2e8f0; border-radius:1rem; padding:1rem 1.25rem; box-shadow:0 1px 3px rgba(0,0,0,.06); }
.dc-card__label { font-size:.72rem; font-weight:600; color:#64748b; text-transform:uppercase; letter-spacing:.04em; margin-bottom:.25rem; }
.dc-card__value { font-size:1.65rem; font-weight:800; color:#1e293b; }
.dc-card__sub   { font-size:.72rem; color:#94a3b8; margin-top:.2rem; }
.dc-card--p3 .dc-card__value { color:#4f46e5; }
.dc-card--combined .dc-card__value { color:#0f766e; }

/* ── Section accordions ────────────────────────────────────── */
.dc-sections { display:flex; flex-direction:column; gap:1rem; }
.dc-section  { background:#fff; border:1px solid #e2e8f0; border-radius:1rem; box-shadow:0 1px 3px rgba(0,0,0,.05); overflow:hidden; }
.dc-section__head  {
    display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap;
    gap:.75rem; padding:1rem 1.25rem; cursor:pointer; user-select:none;
    border-bottom:1px solid transparent; transition: border-color .15s;
}
.dc-section__head:hover { background:#f8fafc; }
.dc-section[open] .dc-section__head { border-bottom-color:#e2e8f0; background:#f8fafc; }
.dc-section__head-left  { display:flex; align-items:center; gap:.75rem; }
.dc-section__icon  { width:2.1rem; height:2.1rem; border-radius:.6rem; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
.dc-section__icon svg  { width:1.1rem; height:1.1rem; }
.dc-section__name  { font-size:.95rem; font-weight:700; color:#1e293b; }
.dc-section__desc  { font-size:.78rem; color:#64748b; margin-top:.1rem; }
.dc-section__arrow { width:1.2rem; height:1.2rem; color:#94a3b8; transition:transform .2s; flex-shrink:0; }
.dc-section[open] .dc-section__arrow { transform:rotate(180deg); }
.dc-section__actions { display:flex; align-items:center; gap:.5rem; }

/* ── Export button ─────────────────────────────────────────── */
.dc-btn { display:inline-flex; align-items:center; gap:.35rem; font-size:.78rem; font-weight:600; padding:.4rem .85rem; border-radius:.5rem; border:1px solid; cursor:pointer; text-decoration:none; transition:background .15s,border-color .15s; }
.dc-btn--export { background:#f0fdf4; border-color:#bbf7d0; color:#166534; }
.dc-btn--export:hover { background:#dcfce7; border-color:#86efac; }
.dc-btn--export-all { background:#eef2ff; border-color:#c7d2fe; color:#3730a3; }
.dc-btn--export-all:hover { background:#e0e7ff; }

/* ── Table ─────────────────────────────────────────────────── */
.dc-table-wrap { padding:.75rem 1rem 1.25rem; overflow-x:auto; }
.dc-table { width:100%; border-collapse:collapse; font-size:.83rem; }
.dc-table th { background:#f8fafc; color:#475569; font-weight:700; font-size:.72rem; text-transform:uppercase; letter-spacing:.04em; padding:.55rem .75rem; text-align:left; border-bottom:2px solid #e2e8f0; white-space:nowrap; }
.dc-table td { padding:.55rem .75rem; border-bottom:1px solid #f1f5f9; color:#334155; }
.dc-table tr:last-child td { border-bottom:none; }
.dc-table tr._total td { font-weight:700; color:#1e293b; background:#f8fafc; border-top:2px solid #e2e8f0; }
.dc-table td._num { text-align:right; font-variant-numeric:tabular-nums; }
.dc-table th._num { text-align:right; }
.dc-note { font-size:.75rem; color:#94a3b8; padding:.4rem 1rem .75rem; }

/* ── Methodology ───────────────────────────────────────────── */
.dc-method { background:#fafafa; border:1px solid #e2e8f0; border-radius:.75rem; padding:.75rem 1rem; font-size:.78rem; color:#475569; margin-top:2rem; line-height:1.6; }
.dc-method summary { font-weight:700; cursor:pointer; color:#334155; }
.dc-method ul { margin:.5rem 0 0 1.1rem; }
.dc-method li { margin-bottom:.25rem; }

/* ── Cache notice ──────────────────────────────────────────── */
.dc-cache-notice { display:flex; align-items:center; gap:.5rem; font-size:.75rem; color:#92400e; background:#fffbeb; border:1px solid #fde68a; border-radius:.5rem; padding:.35rem .75rem; }
.dc-btn--refresh { background:#fff7ed; border-color:#fed7aa; color:#9a3412; }
.dc-btn--refresh:hover { background:#ffedd5; border-color:#fdba74; }

/* ── Flash message ─────────────────────────────────────────── */
.dc-flash { display:flex; align-items:center; gap:.5rem; font-size:.82rem; color:#166534; background:#f0fdf4; border:1px solid #bbf7d0; border-radius:.5rem; padding:.5rem 1rem; margin-bottom:1rem; }

/* ── View toggle ───────────────────────────────────────────── */
.dc-view-toggle { display:inline-flex; border:1px solid #e2e8f0; border-radius:.65rem; overflow:hidden; background:#f8fafc; margin-bottom:1.25rem; }
.dc-view-toggle__btn { display:inline-flex; align-items:center; gap:.35rem; padding:.45rem 1rem; font-size:.78rem; font-weight:700; color:#64748b; text-decoration:none; border-right:1px solid #e2e8f0; transition:background .15s,color .15s; }
.dc-view-toggle__btn:last-child { border-right:none; }
.dc-view-toggle__btn:hover { background:#fff; color:#334155; }
.dc-view-toggle__btn.is-active { background:#4f46e5; color:#fff; }
.dc-view-toggle__btn.is-active--onboarded { background:#0f766e; color:#fff; }

/* ── Application Analysis (slide-style) ────────────────────── */
.dc-analysis { background:#fff; border:1px solid #e2e8f0; border-radius:1rem; padding:1.25rem 1.5rem 1.5rem; margin-bottom:1.5rem; box-shadow:0 1px 3px rgba(0,0,0,.06); }
.dc-analysis__head { display:flex; align-items:flex-end; justify-content:space-between; flex-wrap:wrap; gap:1rem; margin-bottom:1.25rem; padding-bottom:1rem; border-bottom:2px solid #f1f5f9; }
.dc-analysis__title { font-size:1.05rem; font-weight:800; color:#ea580c; letter-spacing:.01em; }
.dc-analysis__subtitle { display:inline-block; margin-top:.35rem; font-size:.78rem; font-weight:700; color:#fff; background:#ea580c; padding:.25rem .65rem; border-radius:.35rem; }
.dc-analysis__total { text-align:right; }
.dc-analysis__total-label { font-size:.72rem; font-weight:600; color:#64748b; text-transform:uppercase; letter-spacing:.04em; }
.dc-analysis__total-value { font-size:2.4rem; font-weight:900; color:#ea580c; line-height:1.1; font-variant-numeric:tabular-nums; }
.dc-analysis__filter-scope { margin-top:.35rem; font-size:.72rem; color:#b45309; font-weight:700; }
.dc-analysis__filter-chips { margin-top:.35rem; display:flex; flex-wrap:wrap; gap:.3rem; justify-content:flex-end; }
.dc-analysis__filter-chip { font-size:.65rem; font-weight:700; color:#7c2d12; background:#ffedd5; border:1px solid #fdba74; border-radius:999px; padding:.15rem .5rem; }
.dc-analysis__grid { display:grid; grid-template-columns:1fr 1fr 1fr; gap:1.25rem; align-items:start; }
@media (max-width:960px) { .dc-analysis__grid { grid-template-columns:1fr; } }
.dc-analysis__col { border-left:2px dotted #cbd5e1; padding-left:1rem; min-width:0; }
.dc-analysis__col:first-child { border-left:none; padding-left:0; }
.dc-analysis__col--business { display:flex; flex-direction:column; gap:0; }
.dc-analysis__col-title { font-size:.72rem; font-weight:700; color:#475569; text-transform:uppercase; letter-spacing:.05em; margin-bottom:.75rem; }
.dc-analysis__row { display:grid; grid-template-columns:1fr auto auto; gap:.5rem 1rem; align-items:center; padding:.45rem 0; border-bottom:1px solid #f8fafc; font-size:.84rem; color:#334155; }
.dc-analysis__row:last-child { border-bottom:none; }
.dc-analysis__row strong { font-weight:800; color:#991b1b; font-variant-numeric:tabular-nums; text-align:right; }
.dc-analysis__row span.dc-analysis__pct { font-weight:700; color:#991b1b; font-variant-numeric:tabular-nums; text-align:right; min-width:3rem; }
.dc-analysis__stages { margin:.35rem 0 .65rem; padding:.5rem .65rem; background:#fff7ed; border:1px solid #fed7aa; border-radius:.5rem; }
.dc-analysis__stages .dc-analysis__row { border-bottom-color:#ffedd5; }
.dc-analysis__stages .dc-analysis__row:last-child { border-bottom:none; }
.dc-analysis__stats { display:flex; flex-direction:column; gap:1.25rem; justify-content:flex-start; flex-shrink:0; }
.dc-analysis__stat { text-align:center; }
.dc-analysis__stat-pct { font-size:2rem; font-weight:900; color:#991b1b; line-height:1; font-variant-numeric:tabular-nums; }
.dc-analysis__stat-label { font-size:.78rem; font-weight:600; color:#334155; margin-top:.35rem; line-height:1.35; }
.dc-analysis__checks { margin-top:1.25rem; padding-top:1rem; border-top:1px solid #e2e8f0; }
.dc-analysis__checks-title { font-size:.72rem; font-weight:700; color:#475569; text-transform:uppercase; letter-spacing:.04em; margin-bottom:.5rem; }
.dc-analysis__checks-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:.45rem .75rem; }
.dc-analysis__check { display:flex; align-items:center; gap:.4rem; font-size:.75rem; color:#475569; }
.dc-analysis__check-dot { width:.55rem; height:.55rem; border-radius:50%; flex-shrink:0; }
.dc-analysis__check-dot--pass { background:#22c55e; }
.dc-analysis__check-dot--fail { background:#ef4444; }

/* ── Phase 3 filters ─────────────────────────────────────────── */
.dc-filters { background:#fff; border:1px solid #e2e8f0; border-radius:.75rem; padding:1rem 1.1rem; margin-bottom:1.25rem; box-shadow:0 1px 3px rgba(0,0,0,.04); }
.dc-filters__title { font-size:.78rem; font-weight:700; color:#4f46e5; text-transform:uppercase; letter-spacing:.04em; margin-bottom:.75rem; }
.dc-filters__grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(150px,1fr)); gap:.65rem .75rem; align-items:end; }
.dc-filters__field label { display:block; font-size:.72rem; font-weight:600; color:#475569; margin-bottom:.25rem; }
.dc-filters__field select,
.dc-filters__field input[type="date"] { width:100%; padding:.42rem .55rem; border:1px solid #d4d4d8; border-radius:.5rem; font-size:.82rem; color:#334155; background:#fff; }
.dc-filters__actions { display:flex; gap:.45rem; flex-wrap:wrap; }
.dc-analysis__income-block { margin-top:1rem; padding-top:1rem; border-top:1px solid #e2e8f0; flex-shrink:0; }
.dc-analysis__income-title { font-size:.68rem; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:.04em; margin:0 0 .5rem; }
.dc-analysis__income-rows { display:flex; flex-direction:column; gap:0; }
.dc-analysis__income-row { display:grid; grid-template-columns:minmax(0,1fr) auto auto; gap:.35rem .65rem; align-items:center; padding:.35rem 0; border-bottom:1px solid #f1f5f9; font-size:.78rem; color:#475569; line-height:1.35; }
.dc-analysis__income-row:last-child { border-bottom:none; }
.dc-analysis__income-row span:first-child { min-width:0; word-break:break-word; }
.dc-analysis__income-row strong { font-weight:700; color:#991b1b; text-align:right; font-variant-numeric:tabular-nums; white-space:nowrap; }
.dc-analysis__income-row span.dc-analysis__pct { font-weight:600; color:#991b1b; text-align:right; min-width:2.75rem; font-variant-numeric:tabular-nums; white-space:nowrap; }
</style>
@endpush

@section('content')
@php
    $viewMode = $view_mode ?? 'all';
    $dataScope = $data_scope ?? 'all';
    $isPhase3View = $viewMode === 'rbiphase3';
    $isOnboardedOnly = $dataScope === 'onboarded';
    $filterQuery = ($filter ?? \App\Services\DataCentre\DataCentreFilter::empty())->queryParams();
    $dcQuery = [];
    if ($isPhase3View) {
        $dcQuery['view'] = 'rbiphase3';
        $dcQuery = array_merge($dcQuery, $filterQuery);
    }
    $phase3ToggleQuery = array_merge(['view' => 'rbiphase3'], $filterQuery);
    $phase3OnboardedQuery = array_merge($phase3ToggleQuery, ['scope' => 'onboarded']);
    if ($isOnboardedOnly) {
        $dcQuery['scope'] = 'onboarded';
    }
    $recordLabel = $isOnboardedOnly ? 'onboarded' : 'applications';
    $recordLabelTitle = $isOnboardedOnly ? 'Onboarded incubatees' : 'CFA applications';
@endphp
<div class="dc-page">

    {{-- ── Flash message ── --}}
    @if (session('flash_success'))
        <div class="dc-flash">
            <svg viewBox="0 0 20 20" fill="currentColor" width="16" height="16"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
            {{ session('flash_success') }}
        </div>
    @endif

    {{-- ── View toggle ── --}}
    <div class="dc-view-toggle" role="tablist" aria-label="Data centre phase view">
        <a href="{{ route('admin.data-centre.index', $isOnboardedOnly ? ['scope' => 'onboarded'] : []) }}"
           class="dc-view-toggle__btn {{ ! $isPhase3View ? 'is-active' : '' }}"
           role="tab"
           aria-selected="{{ ! $isPhase3View ? 'true' : 'false' }}">
            All Phases
        </a>
        <a href="{{ route('admin.data-centre.index', $isOnboardedOnly ? $phase3OnboardedQuery : $phase3ToggleQuery) }}"
           class="dc-view-toggle__btn {{ $isPhase3View ? 'is-active' : '' }}"
           role="tab"
           aria-selected="{{ $isPhase3View ? 'true' : 'false' }}">
            rbiphase3
        </a>
    </div>

    {{-- ── Data scope toggle ── --}}
    <div class="dc-view-toggle" role="tablist" aria-label="Data centre scope" style="margin-top:-.65rem;">
        <a href="{{ route('admin.data-centre.index', $isPhase3View ? $phase3ToggleQuery : []) }}"
           class="dc-view-toggle__btn {{ ! $isOnboardedOnly ? 'is-active' : '' }}"
           role="tab"
           aria-selected="{{ ! $isOnboardedOnly ? 'true' : 'false' }}">
            All applications
        </a>
        <a href="{{ route('admin.data-centre.index', $isPhase3View ? $phase3OnboardedQuery : ['scope' => 'onboarded']) }}"
           class="dc-view-toggle__btn {{ $isOnboardedOnly ? 'is-active is-active--onboarded' : '' }}"
           role="tab"
           aria-selected="{{ $isOnboardedOnly ? 'true' : 'false' }}">
            Onboarded only
        </a>
    </div>

    {{-- ── Phase 3 filters (FY 2026-27 only) ── --}}
    @if ($isPhase3View)
        @php
            $dcFilter = $filter ?? \App\Services\DataCentre\DataCentreFilter::empty();
            $dcFilterDates = $filter_form_dates ?? ['dateFrom' => null, 'dateTo' => null];
            $dcFilterResetQuery = array_filter(['view' => 'rbiphase3', 'scope' => $isOnboardedOnly ? 'onboarded' : null]);
        @endphp
        <form method="get" action="{{ route('admin.data-centre.index') }}" class="dc-filters" id="dc-filters-form">
            <input type="hidden" name="view" value="rbiphase3">
            @if ($isOnboardedOnly)
                <input type="hidden" name="scope" value="onboarded">
            @endif
            <div class="dc-filters__title">Filters — Phase 3 ({{ $phase3_fy->name ?? 'FY 2026-27' }})</div>
            <div class="dc-filters__grid">
                <div class="dc-filters__field">
                    <label for="dc-district">District</label>
                    <select name="district_id" id="dc-district">
                        <option value="">All districts</option>
                        @foreach ($districts ?? [] as $dist)
                            <option value="{{ $dist->id }}" @selected((int) ($dcFilter->districtId ?? 0) === (int) $dist->id)>{{ $dist->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="dc-filters__field">
                    <label for="dc-quarter">Quarter</label>
                    <select name="quarter" id="dc-quarter">
                        <option value="">All quarters</option>
                        @foreach (range(1, 4) as $q)
                            <option value="{{ $q }}" @selected((int) ($dcFilter->quarter ?? 0) === $q)>
                                Q{{ $q }}@if (!empty($phase3_fy)) ({{ $phase3_fy->fiscalQuarterLabel($q) }})@endif
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="dc-filters__field">
                    <label for="dc-fiscal-month">Month</label>
                    <select name="fiscal_month" id="dc-fiscal-month">
                        <option value="">All months</option>
                        @foreach ($fiscal_month_options ?? [] as $opt)
                            <option value="{{ $opt['value'] }}" @selected((int) ($dcFilter->fiscalMonth ?? 0) === (int) $opt['value'])>{{ $opt['label'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="dc-filters__field">
                    <label for="dc-date-from">From date</label>
                    <input type="date" name="date_from" id="dc-date-from" value="{{ $dcFilterDates['dateFrom'] ?? '' }}">
                </div>
                <div class="dc-filters__field">
                    <label for="dc-date-to">To date</label>
                    <input type="date" name="date_to" id="dc-date-to" value="{{ $dcFilterDates['dateTo'] ?? '' }}">
                </div>
                <div class="dc-filters__actions">
                    <button type="submit" class="dc-btn dc-btn--export-all">Apply</button>
                    <a href="{{ route('admin.data-centre.index', $dcFilterResetQuery) }}" class="dc-btn" style="background:#fff;border-color:#d4d4d8;color:#475569;">Reset</a>
                </div>
            </div>
            @if (!empty($meta['filter_active']))
                @php
                    $dcFilterBarDistrict = $dcFilter->districtId
                        ? (($districts ?? collect())->firstWhere('id', $dcFilter->districtId)?->name)
                        : null;
                    $dcFilterBarLabels = $dcFilter->activeLabels($phase3_fy ?? null, $dcFilterBarDistrict);
                @endphp
                <p class="dc-note" style="margin:.65rem 0 0;padding:0;">
                    Filtered view — counts reflect
                    @if (! empty($dcFilterBarLabels))
                        {{ implode(' · ', $dcFilterBarLabels) }}
                    @else
                        selected filters
                    @endif
                    within {{ $phase3_fy->name ?? 'FY 2026-27' }}.
                    @if ($isOnboardedOnly)
                        Onboarded date uses batch <code>locked_at</code>.
                    @endif
                </p>
            @endif
        </form>
    @endif

    {{-- ── Top bar ── --}}
    <div class="dc-topbar">
        <div>
            <div class="dc-topbar__title">
                Program Data Centre
                @if ($isPhase3View)
                    <span style="font-size:.85rem;font-weight:600;color:#4f46e5;margin-left:.35rem;">· rbiphase3 only</span>
                @endif
                @if ($isOnboardedOnly)
                    <span style="font-size:.85rem;font-weight:600;color:#0f766e;margin-left:.35rem;">· onboarded only</span>
                @endif
            </div>
            <div class="dc-topbar__meta">
                Generated: {{ $meta['generated_at'] }}
                &nbsp;·&nbsp;
                <span class="dc-badge {{ $meta['phase1_available'] ? 'dc-badge--ok' : 'dc-badge--err' }}">
                    Phase 1 {{ $meta['phase1_available'] ? '✓' : '✗' }}
                </span>
                <span class="dc-badge {{ $meta['phase2_available'] ? 'dc-badge--ok' : 'dc-badge--err' }}">
                    Phase 2 {{ $meta['phase2_available'] ? '✓' : '✗' }}
                </span>
                <span class="dc-badge dc-badge--ok">Phase 3 Live ✓</span>
                &nbsp;·&nbsp;
                <span class="dc-cache-notice">
                    <svg viewBox="0 0 20 20" fill="currentColor" width="12" height="12"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"/></svg>
                    Cached for {{ $meta['cache_ttl'] / 60 }} min
                </span>
            </div>
        </div>
        <div class="dc-topbar__right">
            <form method="POST" action="{{ route('admin.data-centre.refresh') }}" style="display:inline;">
                @csrf
                @if ($isPhase3View)
                    <input type="hidden" name="view" value="rbiphase3">
                    @foreach (($filter ?? null)?->queryParams() ?? [] as $fk => $fv)
                        <input type="hidden" name="{{ $fk }}" value="{{ $fv }}">
                    @endforeach
                @endif
                @if ($isOnboardedOnly)
                    <input type="hidden" name="scope" value="onboarded">
                @endif
                <button type="submit" class="dc-btn dc-btn--refresh">
                    <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" width="14" height="14"><path d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    Refresh Data
                </button>
            </form>
            <a href="{{ route('admin.data-centre.export-all', $dcQuery) }}" class="dc-btn dc-btn--export-all">
                <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" width="14" height="14"><path d="M10 3v10M6 9l4 4 4-4"/><path d="M3 15h14" stroke-linecap="round"/></svg>
                Export All (CSV)
            </a>
        </div>
    </div>

    {{-- ── Application Analysis (rbiphase3 only) ── --}}
    @if ($isPhase3View && ! empty($application_analysis))
        @php
            $analysis = $application_analysis;
            $allChecksPass = collect($analysis['accuracy_checks'] ?? [])->every(fn ($c) => ! empty($c['pass']));
            $dcFilter = $filter ?? \App\Services\DataCentre\DataCentreFilter::empty();
            $dcFilterDistrictName = $dcFilter->districtId
                ? (($districts ?? collect())->firstWhere('id', $dcFilter->districtId)?->name)
                : null;
            $dcActiveFilterLabels = $dcFilter->activeLabels($phase3_fy ?? null, $dcFilterDistrictName);
            $stageRowLabels = ['Seed-Stage Entrepreneurs', 'Early-Stage Entrepreneurs', 'Growth-Stage Entrepreneurs', 'Stage not specified'];
            $entrepreneurWomen = collect($analysis['entrepreneur'] ?? [])->filter(fn ($row) => ($row['label'] ?? '') === 'Women Entrepreneurs')->values();
            $entrepreneurStages = collect($analysis['entrepreneur'] ?? [])->filter(fn ($row) => in_array($row['label'] ?? '', $stageRowLabels, true))->values();
            $entrepreneurOther = collect($analysis['entrepreneur'] ?? [])->reject(fn ($row) => ($row['label'] ?? '') === 'Women Entrepreneurs' || in_array($row['label'] ?? '', $stageRowLabels, true))->values();
        @endphp
        <section class="dc-analysis" aria-labelledby="dc-analysis-title">
            <div class="dc-analysis__head">
                <div>
                    <div class="dc-analysis__title" id="dc-analysis-title">{{ $isOnboardedOnly ? 'Onboarded Incubatees' : 'Call for Applications' }}</div>
                    <div class="dc-analysis__subtitle">{{ $isOnboardedOnly ? 'Onboarded Analysis' : 'Application Analysis' }} — rbiphase3 ({{ $meta['phase3_fy'] ?? 'FY 2026-27' }})</div>
                </div>
                <div class="dc-analysis__total">
                    <div class="dc-analysis__total-label">Total Number of {{ $isOnboardedOnly ? 'Onboarded' : 'Applications' }}</div>
                    <div class="dc-analysis__total-value">{{ number_format($analysis['total']) }}</div>
                    @if (! empty($analysis['full_total']) && (int) $analysis['full_total'] !== (int) $analysis['total'])
                        <div class="dc-analysis__filter-scope">
                            {{ number_format($analysis['total']) }} of {{ number_format($analysis['full_total']) }} total onboarded (filter applied)
                        </div>
                    @endif
                    @if (! empty($dcActiveFilterLabels))
                        <div class="dc-analysis__filter-chips">
                            @foreach ($dcActiveFilterLabels as $chip)
                                <span class="dc-analysis__filter-chip">{{ $chip }}</span>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

            <div class="dc-analysis__grid">
                {{-- Entrepreneur categories --}}
                <div class="dc-analysis__col">
                    <div class="dc-analysis__col-title">Entrepreneur Categories</div>
                    @if ($entrepreneurStages->isNotEmpty())
                        <div class="dc-analysis__stages">
                            @foreach ($entrepreneurStages as $row)
                                <div class="dc-analysis__row">
                                    <span>{{ $row['label'] }}</span>
                                    <strong>{{ number_format($row['count']) }}</strong>
                                    @if (array_key_exists('pct', $row) && $row['pct'] !== null)
                                        <span class="dc-analysis__pct">{{ number_format($row['pct'], 1) }}%</span>
                                    @else
                                        <span class="dc-analysis__pct">—</span>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endif
                    @foreach ($entrepreneurWomen as $row)
                        <div class="dc-analysis__row">
                            <span>{{ $row['label'] }}</span>
                            <strong>{{ number_format($row['count']) }}</strong>
                            @if (array_key_exists('pct', $row) && $row['pct'] !== null)
                                <span class="dc-analysis__pct">{{ number_format($row['pct'], 1) }}%</span>
                            @else
                                <span class="dc-analysis__pct">—</span>
                            @endif
                        </div>
                    @endforeach
                    @foreach ($entrepreneurOther as $row)
                        <div class="dc-analysis__row">
                            <span>{{ $row['label'] }}</span>
                            <strong>{{ number_format($row['count']) }}</strong>
                            @if (array_key_exists('pct', $row) && $row['pct'] !== null)
                                <span class="dc-analysis__pct">{{ number_format($row['pct'], 1) }}%</span>
                            @else
                                <span class="dc-analysis__pct">—</span>
                            @endif
                        </div>
                    @endforeach
                </div>

                {{-- Sector-wise breakup --}}
                <div class="dc-analysis__col">
                    <div class="dc-analysis__col-title">Sector-wise Breakup</div>
                    @foreach ($analysis['sectors'] as $row)
                        <div class="dc-analysis__row">
                            <span>{{ $row['sector'] }}</span>
                            <strong>{{ number_format($row['count']) }}</strong>
                            <span class="dc-analysis__pct">{{ number_format($row['pct'], 1) }}%</span>
                        </div>
                    @endforeach
                    <p class="dc-note" style="margin:.5rem 0 0;padding:0;font-size:.68rem;">Sector counts sum to {{ number_format($analysis['total']) }}.</p>
                </div>

                {{-- Business statistics --}}
                <div class="dc-analysis__col dc-analysis__col--business">
                    <div class="dc-analysis__col-title">Business Statistics</div>
                    <div class="dc-analysis__stats">
                        @foreach ($analysis['business_stats'] as $row)
                            <div class="dc-analysis__stat">
                                <div class="dc-analysis__stat-pct">{{ number_format($row['pct'], 1) }}%</div>
                                <div class="dc-analysis__stat-label">{{ $row['label'] }}</div>
                                <div style="font-size:.72rem;color:#94a3b8;margin-top:.2rem;">{{ number_format($row['count']) }} {{ $recordLabel }}</div>
                            </div>
                        @endforeach
                    </div>
                    @if (! empty($analysis['income_slabs']))
                        <div class="dc-analysis__income-block">
                            <div class="dc-analysis__income-title">Income / Turnover (last FY)</div>
                            <div class="dc-analysis__income-rows">
                                @foreach ($analysis['income_slabs'] as $row)
                                    <div class="dc-analysis__income-row">
                                        <span>{{ $row['label'] }}</span>
                                        <strong>{{ number_format($row['count']) }}</strong>
                                        <span class="dc-analysis__pct">{{ number_format($row['pct'], 1) }}%</span>
                                    </div>
                                @endforeach
                            </div>
                            <p class="dc-note" style="margin:.45rem 0 0;padding:0;font-size:.68rem;">Income slabs sum to {{ number_format($analysis['total']) }}.</p>
                        </div>
                    @endif
                </div>
            </div>

            @if (! empty($analysis['accuracy_checks']))
                <div class="dc-analysis__checks">
                    <div class="dc-analysis__checks-title">
                        Accuracy checks
                        @if ($allChecksPass)
                            <span style="color:#166534;font-weight:600;"> — all pass</span>
                        @else
                            <span style="color:#991b1b;font-weight:600;"> — review needed</span>
                        @endif
                    </div>
                    <div class="dc-analysis__checks-grid">
                        @foreach ($analysis['accuracy_checks'] as $check)
                            <div class="dc-analysis__check">
                                <span class="dc-analysis__check-dot {{ ! empty($check['pass']) ? 'dc-analysis__check-dot--pass' : 'dc-analysis__check-dot--fail' }}"></span>
                                <span>{{ $check['label'] }}: {{ number_format($check['actual']) }} / {{ number_format($check['expected']) }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </section>
    @endif

    {{-- ── Summary cards ── --}}
    <div class="dc-cards">
        @foreach ($summary as $row)
            @php
                $isP3  = str_contains($row['phase'], 'Phase 3');
                $isCom = str_contains($row['phase'], 'Combined');
            @endphp
            <div class="dc-card {{ $isP3 ? 'dc-card--p3' : ($isCom ? 'dc-card--combined' : '') }}">
                <div class="dc-card__label">{{ $row['phase'] }}</div>
                <div class="dc-card__value">{{ number_format($row['count']) }}</div>
                <div class="dc-card__sub">{{ $row['source'] }}</div>
            </div>
        @endforeach
    </div>

    {{-- ── Sections ── --}}
    <div class="dc-sections">

        {{-- 1. CFA by District --}}
        <details class="dc-section" id="sec-cfa-district">
            <summary class="dc-section__head">
                <div class="dc-section__head-left">
                    <div class="dc-section__icon" style="background:#ede9fe;">
                        <svg viewBox="0 0 24 24" fill="none" stroke="#7c3aed" stroke-width="1.8"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><path d="M9 22V12h6v10"/></svg>
                    </div>
                    <div>
                        <div class="dc-section__name">{{ $recordLabelTitle }} — District-wise</div>
                        <div class="dc-section__desc">
                            @if ($isPhase3View)
                                Phase 3 (rbiphase3) {{ $isOnboardedOnly ? 'onboarded' : 'counts' }} per district — FY 2026–27 only
                            @else
                                Phase 1 + Phase 2 + Phase 3 {{ $isOnboardedOnly ? 'onboarded totals' : 'counts' }} per district and combined total
                            @endif
                        </div>
                    </div>
                </div>
                <div class="dc-section__actions" onclick="event.stopPropagation()">
                    <a href="{{ route('admin.data-centre.export', array_merge(['section' => 'cfa-by-district'], $dcQuery)) }}" class="dc-btn dc-btn--export">
                        <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" width="13" height="13"><path d="M10 3v10M6 9l4 4 4-4"/><path d="M3 15h14" stroke-linecap="round"/></svg>
                        Export CSV
                    </a>
                    <svg class="dc-section__arrow" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 8l5 5 5-5"/></svg>
                </div>
            </summary>
            <div class="dc-table-wrap">
                <table class="dc-table">
                    <thead>
                        <tr>
                            <th>District</th>
                            @if ($isPhase3View)
                                <th class="_num">Phase 3 (FY 26–27)</th>
                                <th class="_num">% of State</th>
                            @else
                                <th class="_num">Phase 1 (FY 24–25)</th>
                                <th class="_num">Phase 2 (FY 25–26)</th>
                                <th class="_num">Phase 3 (FY 26–27)</th>
                                <th class="_num">Combined</th>
                                <th class="_num">% of State</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            if ($isPhase3View) {
                                $stateTotal = collect($cfa_by_district)->where('_is_total', true)->first()['p3'] ?? 1;
                            } else {
                                $stateTotal = collect($cfa_by_district)->where('_is_total', true)->first()['c'] ?? 1;
                            }
                        @endphp
                        @foreach ($cfa_by_district as $row)
                            <tr class="{{ !empty($row['_is_total']) ? '_total' : '' }}">
                                <td>{{ $row['name'] }}</td>
                                @if ($isPhase3View)
                                    <td class="_num">{{ number_format($row['p3']) }}</td>
                                    <td class="_num">
                                        @if (empty($row['_is_total']))
                                            {{ number_format($stateTotal > 0 ? ($row['p3'] / $stateTotal) * 100 : 0, 1) }}%
                                        @else
                                            100%
                                        @endif
                                    </td>
                                @else
                                    <td class="_num">{{ number_format($row['p1']) }}</td>
                                    <td class="_num">{{ number_format($row['p2']) }}</td>
                                    <td class="_num">{{ number_format($row['p3']) }}</td>
                                    <td class="_num">{{ number_format($row['c']) }}</td>
                                    <td class="_num">
                                        @if (empty($row['_is_total']))
                                            {{ number_format($stateTotal > 0 ? ($row['c'] / $stateTotal) * 100 : 0, 1) }}%
                                        @else
                                            100%
                                        @endif
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <p class="dc-note">
                    @if ($isPhase3View)
                        @if ($isOnboardedOnly)
                            Phase 3 locked onboarding batches only (all locked members — matches Admin → Onboarded). Includes legacy Phase 2 imports onboarded via MIS.
                        @else
                            Phase 3 live MIS only. Excludes <code>source = legacy_phase2</code> imports. Fiscal year: {{ $meta['phase3_fy'] ?? 'FY 2026-27' }}.
                        @endif
                    @else
                        @if ($isOnboardedOnly)
                            Phase 1: <code>onboard=yes</code>. Phase 2: <code>rbi_onboarded_applicants</code> (FY 2025–26 window). Phase 3: locked MIS onboarding batches.
                        @else
                            Phase 3 is live from MIS. Phase 1 district = <code>FatherName</code> field. Phase 2 FY 2025–26 only.
                        @endif
                    @endif
                </p>
            </div>
        </details>

        {{-- 2. Employment Generation --}}
        <details class="dc-section" id="sec-employment">
            <summary class="dc-section__head">
                <div class="dc-section__head-left">
                    <div class="dc-section__icon" style="background:#fef3c7;">
                        <svg viewBox="0 0 24 24" fill="none" stroke="#b45309" stroke-width="1.8"><rect x="3" y="7" width="18" height="13" rx="2"/><path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><path d="M3 13h18"/></svg>
                    </div>
                    <div>
                        <div class="dc-section__name">Employment Generation</div>
                        <div class="dc-section__desc">
                            @if ($isPhase3View)
                                Enterprises generating employment — Phase 3 (rbiphase3)
                            @else
                                Enterprises generating employment, employment generated and % — per phase and combined
                            @endif
                        </div>
                    </div>
                </div>
                <div class="dc-section__actions" onclick="event.stopPropagation()">
                    <a href="{{ route('admin.data-centre.export', array_merge(['section' => 'employment-state'], $dcQuery)) }}" class="dc-btn dc-btn--export">
                        <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" width="13" height="13"><path d="M10 3v10M6 9l4 4 4-4"/><path d="M3 15h14" stroke-linecap="round"/></svg>
                        Export CSV
                    </a>
                    <svg class="dc-section__arrow" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 8l5 5 5-5"/></svg>
                </div>
            </summary>
            <div class="dc-table-wrap">
                <table class="dc-table">
                    <thead>
                        <tr>
                            <th>Phase</th>
                            <th class="_num">Generating Employment</th>
                            <th class="_num">Employment Generated</th>
                            <th class="_num">Total {{ $isOnboardedOnly ? 'Onboarded' : 'Applications' }}</th>
                            <th class="_num">% Generating</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($employment_state as $row)
                            <tr class="{{ !empty($row['_is_total']) ? '_total' : '' }}">
                                <td>{{ $row['phase'] }}</td>
                                <td class="_num">{{ number_format($row['employers']) }}</td>
                                <td class="_num">{{ number_format($row['jobs']) }}</td>
                                <td class="_num">{{ number_format($row['total']) }}</td>
                                <td class="_num">{{ number_format($row['pct'], 1) }}%</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <p class="dc-note">
                    <strong>Generating employment</strong> = applicants who answered "Yes". <strong>Employment generated</strong> = sum of people employed — legacy Phase 1/2 are free-text fields, so non-numeric entries and outliers above 500 are excluded (Phase 3 is form-validated). <strong>% Generating</strong> = employers ÷ total {{ $isOnboardedOnly ? 'onboarded' : 'applications' }}.
                </p>
            </div>
        </details>

        {{-- 3. Gender — State --}}
        <details class="dc-section" id="sec-gender-state">
            <summary class="dc-section__head">
                <div class="dc-section__head-left">
                    <div class="dc-section__icon" style="background:#fce7f3;">
                        <svg viewBox="0 0 24 24" fill="none" stroke="#be185d" stroke-width="1.8"><circle cx="9" cy="8" r="3.5"/><path d="M3 20a6 6 0 0 1 12 0"/><path d="M19 8v5M16.5 10.5h5"/></svg>
                    </div>
                    <div>
                        <div class="dc-section__name">Gender Breakdown — State Totals</div>
                        <div class="dc-section__desc">
                            @if ($isPhase3View)
                                Male / Female / NA breakdown — Phase 3 (rbiphase3) state totals
                            @else
                                Male / Female / NA / NA-Blank counts per phase and combined
                            @endif
                        </div>
                    </div>
                </div>
                <div class="dc-section__actions" onclick="event.stopPropagation()">
                    <a href="{{ route('admin.data-centre.export', array_merge(['section' => 'gender-state'], $dcQuery)) }}" class="dc-btn dc-btn--export">
                        <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" width="13" height="13"><path d="M10 3v10M6 9l4 4 4-4"/><path d="M3 15h14" stroke-linecap="round"/></svg>
                        Export CSV
                    </a>
                    <svg class="dc-section__arrow" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 8l5 5 5-5"/></svg>
                </div>
            </summary>
            <div class="dc-table-wrap">
                <table class="dc-table">
                    <thead>
                        <tr>
                            <th>Phase</th>
                            <th class="_num">Male</th>
                            <th class="_num">Female</th>
                            <th class="_num">NA</th>
                            <th class="_num">NA / Blank</th>
                            <th class="_num">Other</th>
                            <th class="_num">Total</th>
                            <th class="_num">% Female</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($gender_state as $row)
                            <tr class="{{ !empty($row['_is_total']) ? '_total' : '' }}">
                                <td>{{ $row['phase'] }}</td>
                                <td class="_num">{{ number_format($row['Male']) }}</td>
                                <td class="_num">{{ number_format($row['Female']) }}</td>
                                <td class="_num">{{ number_format($row['NA']) }}</td>
                                <td class="_num">{{ number_format($row['NA/Blank']) }}</td>
                                <td class="_num">{{ number_format($row['Other']) }}</td>
                                <td class="_num">{{ number_format($row['total']) }}</td>
                                <td class="_num">{{ $row['total'] > 0 ? number_format(($row['Female'] / $row['total']) * 100, 1).'%' : '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <p class="dc-note">
                    @if ($isPhase3View)
                        Phase 3 {{ $isOnboardedOnly ? 'onboarded incubatees' : 'CFA submissions' }} only ({{ number_format($meta['phase3_total'] ?? 0) }} total).
                    @else
                        Phase 2 has {{ number_format(collect($gender_state)->firstWhere('phase', 'Phase 2 (FY 2025–26)')['NA/Blank'] ?? 0) }} rows with no gender in legacy data (shown as NA/Blank).
                    @endif
                </p>
            </div>
        </details>

        {{-- 3. Gender — By District --}}
        <details class="dc-section" id="sec-gender-district">
            <summary class="dc-section__head">
                <div class="dc-section__head-left">
                    <div class="dc-section__icon" style="background:#fce7f3;">
                        <svg viewBox="0 0 24 24" fill="none" stroke="#be185d" stroke-width="1.8"><circle cx="9" cy="8" r="3.5"/><path d="M3 20a6 6 0 0 1 12 0"/><path d="M19 8v5M16.5 10.5h5"/></svg>
                    </div>
                    <div>
                        <div class="dc-section__name">Gender Breakdown — By District (Combined)</div>
                        <div class="dc-section__desc">
                            @if ($isPhase3View)
                                Male / Female / NA breakdown per district — Phase 3 only
                            @else
                                Male / Female / NA breakdown per district across all three phases
                            @endif
                        </div>
                    </div>
                </div>
                <div class="dc-section__actions" onclick="event.stopPropagation()">
                    <a href="{{ route('admin.data-centre.export', array_merge(['section' => 'gender-district'], $dcQuery)) }}" class="dc-btn dc-btn--export">
                        <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" width="13" height="13"><path d="M10 3v10M6 9l4 4 4-4"/><path d="M3 15h14" stroke-linecap="round"/></svg>
                        Export CSV
                    </a>
                    <svg class="dc-section__arrow" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 8l5 5 5-5"/></svg>
                </div>
            </summary>
            <div class="dc-table-wrap">
                <table class="dc-table">
                    <thead>
                        <tr>
                            <th>District</th>
                            <th class="_num">Male</th>
                            <th class="_num">Female</th>
                            <th class="_num">NA</th>
                            <th class="_num">NA / Blank</th>
                            <th class="_num">Other</th>
                            <th class="_num">Total {{ $isOnboardedOnly ? 'onboarded' : 'CFA' }}</th>
                            <th class="_num">% Female</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($gender_district as $row)
                            <tr class="{{ !empty($row['_is_total']) ? '_total' : '' }}">
                                <td>{{ $row['name'] }}</td>
                                <td class="_num">{{ number_format($row['Male']) }}</td>
                                <td class="_num">{{ number_format($row['Female']) }}</td>
                                <td class="_num">{{ number_format($row['NA']) }}</td>
                                <td class="_num">{{ number_format($row['NA/Blank']) }}</td>
                                <td class="_num">{{ number_format($row['Other']) }}</td>
                                <td class="_num">{{ number_format($row['total']) }}</td>
                                <td class="_num">{{ $row['total'] > 0 ? number_format(($row['Female'] / $row['total']) * 100, 1).'%' : '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </details>

        {{-- 4. Education — State --}}
        <details class="dc-section" id="sec-edu-state">
            <summary class="dc-section__head">
                <div class="dc-section__head-left">
                    <div class="dc-section__icon" style="background:#ecfdf5;">
                        <svg viewBox="0 0 24 24" fill="none" stroke="#047857" stroke-width="1.8"><path d="M4 5a2 2 0 0 1 2-2h13v16H6a2 2 0 0 0-2 2V5Z"/><path d="M6 3v16"/><path d="M11 8h5M11 12h5M11 16h3"/></svg>
                    </div>
                    <div>
                        <div class="dc-section__name">Education Level — State Totals</div>
                        <div class="dc-section__desc">
                            @if ($isPhase3View)
                                10th pass / Below 10th / Above 10th — Phase 3 state totals
                            @else
                                10th pass / Below 10th / Above 10th / NA per phase and combined
                            @endif
                        </div>
                    </div>
                </div>
                <div class="dc-section__actions" onclick="event.stopPropagation()">
                    <a href="{{ route('admin.data-centre.export', array_merge(['section' => 'education-state'], $dcQuery)) }}" class="dc-btn dc-btn--export">
                        <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" width="13" height="13"><path d="M10 3v10M6 9l4 4 4-4"/><path d="M3 15h14" stroke-linecap="round"/></svg>
                        Export CSV
                    </a>
                    <svg class="dc-section__arrow" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 8l5 5 5-5"/></svg>
                </div>
            </summary>
            <div class="dc-table-wrap">
                <table class="dc-table">
                    <thead>
                        <tr>
                            <th>Phase</th>
                            <th class="_num">10th Pass</th>
                            <th class="_num">Below 10th</th>
                            <th class="_num">Above 10th / Other</th>
                            <th class="_num">NA</th>
                            <th class="_num">NA / Blank</th>
                            <th class="_num">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($education_state as $row)
                            <tr class="{{ !empty($row['_is_total']) ? '_total' : '' }}">
                                <td>{{ $row['phase'] }}</td>
                                <td class="_num">{{ number_format($row['10th pass']) }}</td>
                                <td class="_num">{{ number_format($row['Below 10th']) }}</td>
                                <td class="_num">{{ number_format($row['Above 10th / Other']) }}</td>
                                <td class="_num">{{ number_format($row['NA']) }}</td>
                                <td class="_num">{{ number_format($row['NA/Blank']) }}</td>
                                <td class="_num">{{ number_format($row['total']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <p class="dc-note">
                    @if ($isPhase3View)
                        Education from Phase 3 CFA form payload.
                    @else
                        Phase 1 uses <code>10th</code> label (1,973 rows) counted as 10th pass. Below 10th includes: Below 8th, 8th pass, 8th, 5th, 9th, Non Matric.
                    @endif
                </p>
            </div>
        </details>

        {{-- 5. Education — By District --}}
        <details class="dc-section" id="sec-edu-district">
            <summary class="dc-section__head">
                <div class="dc-section__head-left">
                    <div class="dc-section__icon" style="background:#ecfdf5;">
                        <svg viewBox="0 0 24 24" fill="none" stroke="#047857" stroke-width="1.8"><path d="M4 5a2 2 0 0 1 2-2h13v16H6a2 2 0 0 0-2 2V5Z"/><path d="M6 3v16"/><path d="M11 8h5M11 12h5M11 16h3"/></svg>
                    </div>
                    <div>
                        <div class="dc-section__name">Education Level — By District (Combined)</div>
                        <div class="dc-section__desc">
                            @if ($isPhase3View)
                                10th pass / Below 10th breakdown per district — Phase 3 only
                            @else
                                10th pass / Below 10th breakdown per district across all three phases
                            @endif
                        </div>
                    </div>
                </div>
                <div class="dc-section__actions" onclick="event.stopPropagation()">
                    <a href="{{ route('admin.data-centre.export', array_merge(['section' => 'education-district'], $dcQuery)) }}" class="dc-btn dc-btn--export">
                        <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" width="13" height="13"><path d="M10 3v10M6 9l4 4 4-4"/><path d="M3 15h14" stroke-linecap="round"/></svg>
                        Export CSV
                    </a>
                    <svg class="dc-section__arrow" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 8l5 5 5-5"/></svg>
                </div>
            </summary>
            <div class="dc-table-wrap">
                <table class="dc-table">
                    <thead>
                        <tr>
                            <th>District</th>
                            <th class="_num">10th Pass</th>
                            <th class="_num">Below 10th</th>
                            <th class="_num">Above 10th / Other</th>
                            <th class="_num">NA</th>
                            <th class="_num">NA / Blank</th>
                            <th class="_num">Total {{ $isOnboardedOnly ? 'onboarded' : 'CFA' }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($education_district as $row)
                            <tr class="{{ !empty($row['_is_total']) ? '_total' : '' }}">
                                <td>{{ $row['name'] }}</td>
                                <td class="_num">{{ number_format($row['10th pass']) }}</td>
                                <td class="_num">{{ number_format($row['Below 10th']) }}</td>
                                <td class="_num">{{ number_format($row['Above 10th / Other']) }}</td>
                                <td class="_num">{{ number_format($row['NA']) }}</td>
                                <td class="_num">{{ number_format($row['NA/Blank']) }}</td>
                                <td class="_num">{{ number_format($row['total']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </details>

    </div>{{-- /.dc-sections --}}

    {{-- ── Methodology note ── --}}
    <details class="dc-method" style="margin-top:1.5rem;">
        <summary>How counts are calculated (methodology)</summary>
        <ul>
            @if ($isOnboardedOnly)
                <li><strong>Onboarded only mode:</strong> Counts incubatees who completed onboarding — not all CFA applicants.</li>
                <li><strong>Phase 1 (FY 2024–25):</strong> <code>tblapplication</code> where <code>onboard = yes</code>. District = <code>FatherName</code> (same aliases as CFA view).</li>
                <li><strong>Phase 2 (FY 2025–26):</strong> <code>rbi_onboarded_applicants</code> with non-empty <code>status</code>, within Phase 2 FY <code>submission_date</code> window (2025-04-02 to 2026-04-01).</li>
                <li><strong>Phase 3 (FY 2026–27 — live):</strong> Locked <code>onboarding_batches</code> via <code>onboarding_batch_cfa</code> — all locked members (same as Admin → Onboarded; not filtered by <code>fiscal_year_id</code>). Includes <code>legacy_phase2</code> imports onboarded through MIS.</li>
                <li><strong>Combined onboarded:</strong> P1 legacy + P2 legacy onboarded + P3 MIS locked batches. Phase 3 includes Phase 2 imports onboarded only via the new MIS.</li>
            @else
                <li><strong>Phase 1 (FY 2024–25):</strong> Reads <code>ukrbiin_rbi.tblapplication</code>. District = <code>FatherName</code> column (aliases: <code>US_Nagar</code> → Udham Singh Nagar, <code>Tehri_Garhwal</code> → Tehri Garhwal, <code>Pauri</code> → Pauri Garhwal).</li>
                <li><strong>Phase 2 (FY 2025–26):</strong> Reads <code>rbiphase2.rbi_applications</code> joined with <code>rbi_applicant_details</code>. Filtered by <code>submission_date</code> between 2025-04-02 and 2026-04-01. District from <code>d.district</code>.</li>
                <li><strong>Phase 3 (FY 2026–27 — live):</strong> Reads <code>cfa_submissions</code> for fiscal year {{ $meta['phase3_fy'] ?? 'FY 2026-27' }}. Excludes rows with <code>source = 'legacy_phase2'</code> to avoid double-counting Phase 2 applicants imported into this MIS.</li>
            @endif
            @if ($isPhase3View)
                <li><strong>rbiphase3 view:</strong> Shows Phase 3 data only. {{ $isOnboardedOnly ? 'Onboarded analysis uses locked batch members (MIS + rbiphase2 legacy). Filters (district / quarter / month / date) apply within FY 2026-27; onboarded date uses batch locked_at.' : 'Application Analysis uses live payload fields with Phase 2 legacy fallback (rbiphase2 DB) for sector, turnover, loan and registration when MIS payload is sparse. Filters (district / quarter / month / date) apply within FY 2026-27 on this view.' }}</li>
            @endif
            @unless ($isOnboardedOnly)
                <li><strong>No double-counting:</strong> Phase 3 new-only count + Phase 2 = no overlap. 4,414 Phase 2 rows were imported into cfa_submissions and are excluded from Phase 3 counts.</li>
            @endunless
            <li><strong>10th pass (Phase 1):</strong> Legacy DB uses label <code>10th</code> (not <code>10th pass</code>). Both are counted as "10th pass".</li>
            <li><strong>Gender NA/Blank:</strong> Phase 2 legacy data has many blank gender fields — these appear as NA/Blank, not 0.</li>
        </ul>
    </details>

</div>

@if ($isPhase3View ?? false)
@push('scripts')
<script>
(function () {
    const quarterEl = document.getElementById('dc-quarter');
    const monthEl = document.getElementById('dc-fiscal-month');
    const dateFromEl = document.getElementById('dc-date-from');
    const dateToEl = document.getElementById('dc-date-to');
    if (!quarterEl || !monthEl || !dateFromEl || !dateToEl) return;

    const fyQuarterMonths = @json($fy_quarter_periods ?? []);
    const fiscalMonthOptions = @json($fiscal_month_options ?? []);
    let syncingPresetDates = false;

    function setDateRange(fromIso, toIso) {
        syncingPresetDates = true;
        dateFromEl.value = fromIso || '';
        dateToEl.value = toIso || '';
        syncingPresetDates = false;
    }

    function clearPeriodFields() {
        setDateRange('', '');
    }

    function syncDatesFromMonth() {
        const fiscalMonth = parseInt(monthEl.value, 10);
        if (!fiscalMonth || fiscalMonth < 1 || fiscalMonth > 12) {
            if (!quarterEl.value) clearPeriodFields();
            return;
        }
        const opt = fiscalMonthOptions.find(function (o) { return parseInt(o.value, 10) === fiscalMonth; });
        if (!opt) return;
        const parts = String(opt.label).split(' ');
        const monthNames = {Jan:1,Feb:2,Mar:3,Apr:4,May:5,Jun:6,Jul:7,Aug:8,Sep:9,Oct:10,Nov:11,Dec:12};
        const m = monthNames[parts[0]] || 1;
        const y = parseInt(parts[1], 10);
        const lastDay = new Date(y, m, 0).getDate();
        const pad = function (n) { return String(n).padStart(2, '0'); };
        setDateRange(y + '-' + pad(m) + '-01', y + '-' + pad(m) + '-' + pad(lastDay));
    }

    function syncDatesFromQuarter() {
        const quarter = parseInt(quarterEl.value, 10);
        if (!quarter || quarter < 1 || quarter > 4) {
            if (!monthEl.value) clearPeriodFields();
            return;
        }
        const range = fyQuarterMonths[quarter];
        if (!range) { clearPeriodFields(); return; }
        setDateRange(range.from, range.to);
    }

    function onManualDateChange() {
        if (syncingPresetDates) return;
        quarterEl.value = '';
        monthEl.value = '';
    }

    quarterEl.addEventListener('change', function () {
        if (quarterEl.value) monthEl.value = '';
        syncDatesFromQuarter();
    });

    monthEl.addEventListener('change', function () {
        if (monthEl.value) quarterEl.value = '';
        syncDatesFromMonth();
    });

    dateFromEl.addEventListener('change', onManualDateChange);
    dateToEl.addEventListener('change', onManualDateChange);
})();
</script>
@endpush
@endif
@endsection
