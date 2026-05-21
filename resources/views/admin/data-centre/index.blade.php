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
</style>
@endpush

@section('content')
<div class="dc-page">

    {{-- ── Flash message ── --}}
    @if (session('flash_success'))
        <div class="dc-flash">
            <svg viewBox="0 0 20 20" fill="currentColor" width="16" height="16"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
            {{ session('flash_success') }}
        </div>
    @endif

    {{-- ── Top bar ── --}}
    <div class="dc-topbar">
        <div>
            <div class="dc-topbar__title">Program Data Centre</div>
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
                <button type="submit" class="dc-btn dc-btn--refresh">
                    <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" width="14" height="14"><path d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    Refresh Data
                </button>
            </form>
            <a href="{{ route('admin.data-centre.export-all') }}" class="dc-btn dc-btn--export-all">
                <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" width="14" height="14"><path d="M10 3v10M6 9l4 4 4-4"/><path d="M3 15h14" stroke-linecap="round"/></svg>
                Export All (CSV)
            </a>
        </div>
    </div>

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
                        <div class="dc-section__name">CFA Applications — District-wise</div>
                        <div class="dc-section__desc">Phase 1 + Phase 2 + Phase 3 counts per district and combined total</div>
                    </div>
                </div>
                <div class="dc-section__actions" onclick="event.stopPropagation()">
                    <a href="{{ route('admin.data-centre.export', 'cfa-by-district') }}" class="dc-btn dc-btn--export">
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
                            <th class="_num">Phase 1 (FY 24–25)</th>
                            <th class="_num">Phase 2 (FY 25–26)</th>
                            <th class="_num">Phase 3 (FY 26–27)</th>
                            <th class="_num">Combined</th>
                            <th class="_num">% of State</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php $stateTotal = collect($cfa_by_district)->where('_is_total', true)->first()['c'] ?? 1; @endphp
                        @foreach ($cfa_by_district as $row)
                            <tr class="{{ !empty($row['_is_total']) ? '_total' : '' }}">
                                <td>{{ $row['name'] }}</td>
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
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <p class="dc-note">Phase 3 is live from MIS. Phase 1 district = <code>FatherName</code> field. Phase 2 FY 2025–26 only.</p>
            </div>
        </details>

        {{-- 2. Gender — State --}}
        <details class="dc-section" id="sec-gender-state">
            <summary class="dc-section__head">
                <div class="dc-section__head-left">
                    <div class="dc-section__icon" style="background:#fce7f3;">
                        <svg viewBox="0 0 24 24" fill="none" stroke="#be185d" stroke-width="1.8"><circle cx="9" cy="8" r="3.5"/><path d="M3 20a6 6 0 0 1 12 0"/><path d="M19 8v5M16.5 10.5h5"/></svg>
                    </div>
                    <div>
                        <div class="dc-section__name">Gender Breakdown — State Totals</div>
                        <div class="dc-section__desc">Male / Female / NA / NA-Blank counts per phase and combined</div>
                    </div>
                </div>
                <div class="dc-section__actions" onclick="event.stopPropagation()">
                    <a href="{{ route('admin.data-centre.export', 'gender-state') }}" class="dc-btn dc-btn--export">
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
                <p class="dc-note">Phase 2 has {{ number_format(collect($gender_state)->firstWhere('phase', 'Phase 2 (FY 2025–26)')['NA/Blank'] ?? 0) }} rows with no gender in legacy data (shown as NA/Blank).</p>
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
                        <div class="dc-section__desc">Male / Female / NA breakdown per district across all three phases</div>
                    </div>
                </div>
                <div class="dc-section__actions" onclick="event.stopPropagation()">
                    <a href="{{ route('admin.data-centre.export', 'gender-district') }}" class="dc-btn dc-btn--export">
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
                            <th class="_num">Total CFA</th>
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
                        <div class="dc-section__desc">10th pass / Below 10th / Above 10th / NA per phase and combined</div>
                    </div>
                </div>
                <div class="dc-section__actions" onclick="event.stopPropagation()">
                    <a href="{{ route('admin.data-centre.export', 'education-state') }}" class="dc-btn dc-btn--export">
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
                <p class="dc-note">Phase 1 uses <code>10th</code> label (1,973 rows) counted as 10th pass. Below 10th includes: Below 8th, 8th pass, 8th, 5th, 9th, Non Matric.</p>
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
                        <div class="dc-section__desc">10th pass / Below 10th breakdown per district across all three phases</div>
                    </div>
                </div>
                <div class="dc-section__actions" onclick="event.stopPropagation()">
                    <a href="{{ route('admin.data-centre.export', 'education-district') }}" class="dc-btn dc-btn--export">
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
                            <th class="_num">Total CFA</th>
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
            <li><strong>Phase 1 (FY 2024–25):</strong> Reads <code>ukrbiin_rbi.tblapplication</code>. District = <code>FatherName</code> column (aliases: <code>US_Nagar</code> → Udham Singh Nagar, <code>Tehri_Garhwal</code> → Tehri Garhwal, <code>Pauri</code> → Pauri Garhwal).</li>
            <li><strong>Phase 2 (FY 2025–26):</strong> Reads <code>rbiphase2.rbi_applications</code> joined with <code>rbi_applicant_details</code>. Filtered by <code>submission_date</code> between 2025-04-02 and 2026-04-01. District from <code>d.district</code>.</li>
            <li><strong>Phase 3 (FY 2026–27 — live):</strong> Reads <code>muy.cfa_submissions</code>. Excludes rows with <code>source = 'legacy_phase2'</code> to avoid double-counting Phase 2 applicants imported into this MIS.</li>
            <li><strong>No double-counting:</strong> Phase 3 new-only count + Phase 2 = no overlap. 4,414 Phase 2 rows were imported into cfa_submissions and are excluded from Phase 3 counts.</li>
            <li><strong>10th pass (Phase 1):</strong> Legacy DB uses label <code>10th</code> (not <code>10th pass</code>). Both are counted as "10th pass".</li>
            <li><strong>Gender NA/Blank:</strong> Phase 2 legacy data has many blank gender fields — these appear as NA/Blank, not 0.</li>
        </ul>
    </details>

</div>
@endsection
