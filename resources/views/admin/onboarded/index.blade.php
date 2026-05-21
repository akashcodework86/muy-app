@extends('layouts.admin')

@section('title', 'Onboarded Applicants')
@section('heading', 'Onboarded Applicants')

@push('styles')
<style>
    .onb-shell { display: flex; flex-direction: column; gap: 1.1rem; }

    /* ── KPI strip ── */
    .onb-kpi-grid {
        display: grid;
        grid-template-columns: repeat(5, minmax(0, 1fr));
        gap: 0.75rem;
    }
    @media (max-width: 1200px) { .onb-kpi-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
    @media (max-width: 960px) { .onb-kpi-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    @media (max-width: 520px) { .onb-kpi-grid { grid-template-columns: 1fr; } }

    .onb-kpi {
        border-radius: 14px;
        padding: 1rem 1.05rem;
        border: 1px solid transparent;
        position: relative;
        overflow: hidden;
    }
    .onb-kpi::after {
        content: '';
        position: absolute;
        inset: auto -20% -40% auto;
        width: 90px;
        height: 90px;
        border-radius: 50%;
        opacity: 0.12;
        background: currentColor;
    }
    .onb-kpi--total { background: linear-gradient(135deg, #eef2ff, #e0e7ff); border-color: #c7d2fe; color: #4338ca; }
    .onb-kpi--districts { background: linear-gradient(135deg, #ecfeff, #cffafe); border-color: #a5f3fc; color: #0e7490; }
    .onb-kpi--female { background: linear-gradient(135deg, #fdf2f8, #fce7f3); border-color: #f9a8d4; color: #be185d; }
    .onb-kpi--recent { background: linear-gradient(135deg, #fff7ed, #ffedd5); border-color: #fdba74; color: #c2410c; }
    .onb-kpi--lakhpati { background: linear-gradient(135deg, #f0fdf4, #dcfce7); border-color: #86efac; color: #15803d; }

    .onb-kpi__label {
        font-size: 0.68rem;
        text-transform: uppercase;
        letter-spacing: 0.07em;
        font-weight: 800;
        opacity: 0.85;
    }
    .onb-kpi__value {
        margin-top: 0.25rem;
        font-size: 1.65rem;
        font-weight: 800;
        line-height: 1.1;
        color: #0f172a;
    }
    .onb-kpi__sub {
        margin-top: 0.35rem;
        font-size: 0.78rem;
        color: #475569;
        line-height: 1.35;
    }

    /* ── District cards ── */
    .onb-section-head {
        display: flex;
        align-items: flex-end;
        justify-content: space-between;
        gap: 0.75rem;
        flex-wrap: wrap;
    }
    .onb-section-head h3 {
        margin: 0;
        font-size: 0.95rem;
        font-weight: 800;
        color: #0f172a;
    }
    .onb-section-head p {
        margin: 0.2rem 0 0;
        font-size: 0.8rem;
        color: #64748b;
    }

    .onb-district-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
        gap: 0.7rem;
    }

    .onb-district-card {
        display: block;
        text-decoration: none;
        color: inherit;
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        padding: 0.85rem 0.95rem;
        transition: border-color 0.2s, box-shadow 0.2s, transform 0.2s;
        box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);
    }
    .onb-district-card:hover {
        border-color: #818cf8;
        box-shadow: 0 6px 20px rgba(79, 70, 229, 0.1);
        transform: translateY(-1px);
    }
    .onb-district-card.is-active {
        border-color: #4f46e5;
        background: linear-gradient(180deg, #fafafe 0%, #fff 100%);
        box-shadow: 0 0 0 2px rgba(79, 70, 229, 0.15);
    }
    .onb-district-card__top {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 0.5rem;
    }
    .onb-district-card__name {
        font-size: 0.92rem;
        font-weight: 800;
        color: #0f172a;
        line-height: 1.3;
    }
    .onb-district-card__hub {
        font-size: 0.72rem;
        color: #64748b;
        margin-top: 0.15rem;
    }
    .onb-district-card__count {
        font-size: 1.35rem;
        font-weight: 800;
        color: #4f46e5;
        line-height: 1;
        white-space: nowrap;
    }
    .onb-district-card__bar {
        margin-top: 0.65rem;
        height: 6px;
        border-radius: 999px;
        background: #e2e8f0;
        overflow: hidden;
    }
    .onb-district-card__bar span {
        display: block;
        height: 100%;
        border-radius: inherit;
        background: linear-gradient(90deg, #6366f1, #14b8a6);
    }
    .onb-district-card__meta {
        margin-top: 0.55rem;
        display: flex;
        flex-wrap: wrap;
        gap: 0.35rem 0.55rem;
        font-size: 0.72rem;
        color: #64748b;
    }
    .onb-district-card__meta strong { color: #334155; }

    .onb-district-all {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        padding: 0.35rem 0.65rem;
        border-radius: 999px;
        border: 1px solid #cbd5e1;
        background: #fff;
        color: #334155;
        font-size: 0.78rem;
        font-weight: 700;
        text-decoration: none;
    }
    .onb-district-all.is-active {
        background: #4f46e5;
        border-color: #4f46e5;
        color: #fff;
    }

    /* ── Filters ── */
    .onb-filters {
        display: flex;
        flex-wrap: wrap;
        gap: 0.6rem;
        align-items: flex-end;
        padding: 0.9rem 1rem;
        border-radius: 14px;
        background: #fff;
        border: 1px solid #e2e8f0;
    }
    .onb-fld { display: flex; flex-direction: column; gap: 0.25rem; min-width: 180px; }
    .onb-fld--grow { flex: 1 1 260px; min-width: 220px; max-width: 480px; }
    .onb-fld label { font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.08em; color: #64748b; font-weight: 700; }
    .onb-fld select,
    .onb-fld input {
        padding: 0.45rem 0.6rem;
        border-radius: 10px;
        border: 1px solid #cbd5e1;
        font: inherit;
        background: #fff;
    }
    .onb-actions { display: flex; gap: 0.4rem; margin-left: auto; }
    .btn-sm {
        padding: 0.45rem 0.8rem;
        border-radius: 10px;
        font-size: 0.82rem;
        font-weight: 600;
        border: 1px solid #cbd5e1;
        background: #fff;
        color: #334155;
        text-decoration: none;
        cursor: pointer;
    }
    .btn-primary {
        background: linear-gradient(135deg, #4f46e5, #0d9488);
        color: #fff;
        border-color: transparent;
    }

    /* ── Applicant cards ── */
    .onb-list-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
        flex-wrap: wrap;
    }
    .onb-list-head h3 { margin: 0; font-size: 0.95rem; font-weight: 800; color: #0f172a; }
    .onb-list-meta { font-size: 0.82rem; color: #64748b; }

    .onb-applicant-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 0.75rem;
    }

    .onb-applicant-card {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        padding: 0.95rem 1rem;
        display: flex;
        flex-direction: column;
        gap: 0.65rem;
        box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);
    }
    .onb-applicant-card__head {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 0.65rem;
    }
    .onb-applicant-card__name {
        font-size: 0.98rem;
        font-weight: 800;
        color: #0f172a;
        line-height: 1.25;
    }
    .onb-applicant-card__appno {
        font-size: 0.76rem;
        color: #64748b;
        margin-top: 0.15rem;
        font-family: ui-monospace, monospace;
    }
    .onb-badge {
        display: inline-flex;
        align-items: center;
        padding: 0.2rem 0.55rem;
        border-radius: 999px;
        font-size: 0.68rem;
        font-weight: 700;
        white-space: nowrap;
    }
    .onb-badge--phase3 { background: #eff6ff; color: #1d4ed8; }
    .onb-badge--legacy { background: #fef3c7; color: #92400e; }

    .onb-applicant-card__grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 0.45rem 0.75rem;
    }
    .onb-applicant-card__field label {
        display: block;
        font-size: 0.65rem;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        color: #94a3b8;
        font-weight: 700;
    }
    .onb-applicant-card__field span {
        display: block;
        font-size: 0.82rem;
        color: #334155;
        font-weight: 600;
        margin-top: 0.1rem;
        word-break: break-word;
    }
    .onb-applicant-card__field--full { grid-column: 1 / -1; }

    .onb-applicant-card__batch {
        padding-top: 0.55rem;
        border-top: 1px dashed #e2e8f0;
        font-size: 0.78rem;
        color: #64748b;
        line-height: 1.4;
    }
    .onb-applicant-card__batch strong { color: #0f172a; }

    .onb-empty {
        padding: 2.5rem 1rem;
        text-align: center;
        color: #64748b;
        background: #fff;
        border: 1px dashed #cbd5e1;
        border-radius: 14px;
    }
    .onb-pager {
        padding: 0.85rem 1rem;
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 14px;
    }

    details.onb-full-table { background: #fff; border: 1px solid #e2e8f0; border-radius: 14px; overflow: hidden; }
    details.onb-full-table summary {
        cursor: pointer;
        padding: 0.75rem 1rem;
        font-size: 0.82rem;
        font-weight: 700;
        color: #475569;
        background: #f8fafc;
        list-style: none;
    }
    details.onb-full-table summary::-webkit-details-marker { display: none; }
    .onb-table-wrap { overflow: auto; }
    .onb-table { width: 100%; border-collapse: collapse; min-width: 900px; }
    .onb-table thead th {
        text-align: left;
        padding: 0.7rem 0.9rem;
        background: #f8fafc;
        font-size: 0.72rem;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        color: #475569;
        font-weight: 700;
        border-bottom: 1px solid #e2e8f0;
        white-space: nowrap;
    }
    .onb-table tbody td {
        padding: 0.75rem 0.9rem;
        font-size: 0.86rem;
        border-bottom: 1px solid #f1f5f9;
        color: #0f172a;
        vertical-align: top;
    }
    .muted { color: #64748b; font-size: 0.79rem; }

    /* ── Target progress ── */
    .onb-target {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        padding: 1rem 1.1rem;
        box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);
    }
    .onb-target__head {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 0.75rem;
        flex-wrap: wrap;
        margin-bottom: 0.75rem;
    }
    .onb-target__title {
        margin: 0;
        font-size: 0.92rem;
        font-weight: 800;
        color: #0f172a;
    }
    .onb-target__sub {
        margin: 0.2rem 0 0;
        font-size: 0.78rem;
        color: #64748b;
    }
    .onb-target__stats {
        text-align: right;
        font-size: 0.78rem;
        color: #475569;
        line-height: 1.45;
    }
    .onb-target__stats strong {
        display: block;
        font-size: 1.25rem;
        color: #4338ca;
        line-height: 1.1;
    }
    .onb-target__bar {
        height: 10px;
        border-radius: 999px;
        background: #e2e8f0;
        overflow: hidden;
    }
    .onb-target__bar span {
        display: block;
        height: 100%;
        border-radius: inherit;
        background: linear-gradient(90deg, #4f46e5, #14b8a6);
    }
    .onb-target__meta {
        margin-top: 0.55rem;
        display: flex;
        flex-wrap: wrap;
        gap: 0.45rem 0.85rem;
        font-size: 0.75rem;
        color: #64748b;
    }
    .onb-target__meta strong { color: #334155; }

    /* ── Insights + sector ── */
    .onb-analysis-grid {
        display: grid;
        grid-template-columns: minmax(0, 1fr) minmax(0, 1.1fr);
        gap: 0.75rem;
    }
    @media (max-width: 900px) { .onb-analysis-grid { grid-template-columns: 1fr; } }

    .onb-panel {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        padding: 0.95rem 1.05rem;
        box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);
    }
    .onb-panel__title {
        margin: 0 0 0.65rem;
        font-size: 0.88rem;
        font-weight: 800;
        color: #0f172a;
    }
    .onb-insights {
        margin: 0;
        padding-left: 1.1rem;
        display: flex;
        flex-direction: column;
        gap: 0.45rem;
        font-size: 0.82rem;
        color: #334155;
        line-height: 1.45;
    }
    .onb-sector-list { display: flex; flex-direction: column; gap: 0.55rem; }
    .onb-sector-row__top {
        display: flex;
        justify-content: space-between;
        gap: 0.5rem;
        font-size: 0.78rem;
        color: #475569;
        margin-bottom: 0.25rem;
    }
    .onb-sector-row__top strong { color: #0f172a; font-size: 0.8rem; }
    .onb-sector-row__bar {
        height: 7px;
        border-radius: 999px;
        background: #f1f5f9;
        overflow: hidden;
    }
    .onb-sector-row__bar span {
        display: block;
        height: 100%;
        border-radius: inherit;
        background: linear-gradient(90deg, #6366f1, #8b5cf6);
    }
</style>
@endpush

@section('content')
@php
    $overview = $overview ?? [];
    $districtSummaries = $districtSummaries ?? [];
    $targetProgress = $targetProgress ?? [];
    $sectorBreakdown = $sectorBreakdown ?? [];
    $insights = $insights ?? [];
    $activeDistrict = (int) ($filters['district'] ?? 0);
    $routeIndex = $routeIndex ?? 'admin.onboarded.index';
    $filterQuery = array_filter([
        'hub' => $filters['hub'] ?? null,
        'district' => $filters['district'] ?? null,
        'q' => $filters['q'] ?? null,
    ]);
@endphp

<div class="onb-shell">
    {{-- KPI overview --}}
    <div class="onb-kpi-grid">
        <div class="onb-kpi onb-kpi--total">
            <div class="onb-kpi__label">Total onboarded</div>
            <div class="onb-kpi__value">{{ number_format((int) ($overview['total'] ?? 0)) }}</div>
            <div class="onb-kpi__sub">
                @if ($activeDistrict)
                    Filtered district view
                @else
                    {{ number_format((int) ($overview['hubs_covered'] ?? 0)) }} hubs · {{ number_format((int) ($overview['this_month'] ?? 0)) }} this month
                @endif
            </div>
        </div>
        <div class="onb-kpi onb-kpi--districts">
            <div class="onb-kpi__label">Districts covered</div>
            <div class="onb-kpi__value">{{ number_format((int) ($overview['districts_covered'] ?? 0)) }}</div>
            <div class="onb-kpi__sub">Active districts with locked onboarding batches</div>
        </div>
        <div class="onb-kpi onb-kpi--female">
            <div class="onb-kpi__label">Women onboarded</div>
            <div class="onb-kpi__value">{{ number_format((int) ($overview['female_count'] ?? 0)) }}</div>
            <div class="onb-kpi__sub">
                @if (! is_null($overview['female_pct'] ?? null))
                    {{ $overview['female_pct'] }}% of recorded gender · {{ number_format((int) ($overview['male_count'] ?? 0)) }} male
                @else
                    Gender not recorded for all applicants
                @endif
            </div>
        </div>
        <div class="onb-kpi onb-kpi--lakhpati">
            <div class="onb-kpi__label">Potential Lakhpati Didi</div>
            <div class="onb-kpi__value">{{ number_format((int) ($overview['potential_lakhpati_count'] ?? 0)) }}</div>
            <div class="onb-kpi__sub">
                @if (! is_null($overview['potential_lakhpati_pct'] ?? null))
                    {{ $overview['potential_lakhpati_pct'] }}% of onboarded · SHG/CBO category, or Individual with member Yes
                @else
                    No onboarded applicants in current filter scope
                @endif
            </div>
        </div>
        <div class="onb-kpi onb-kpi--recent">
            <div class="onb-kpi__label">Last 7 days</div>
            <div class="onb-kpi__value">{{ number_format((int) ($overview['recent_7_days'] ?? 0)) }}</div>
            <div class="onb-kpi__sub">Recent onboarding activity in current filter scope</div>
        </div>
    </div>

    @if (($targetProgress['configured'] ?? false) && (int) ($targetProgress['target'] ?? 0) > 0)
        @php
            $targetPct = min(100, max(0, (int) ($targetProgress['progress_pct'] ?? 0)));
        @endphp
        <div class="onb-target">
            <div class="onb-target__head">
                <div>
                    <h3 class="onb-target__title">{{ $targetProgress['label'] ?? 'Onboarding target' }}</h3>
                    <p class="onb-target__sub">
                        FY {{ $targetProgress['fiscal_year'] ?? '—' }} · locked batch onboarding (search filter excluded from progress count)
                    </p>
                </div>
                <div class="onb-target__stats">
                    <strong>{{ number_format((int) ($targetProgress['achieved'] ?? 0)) }} / {{ number_format((int) ($targetProgress['target'] ?? 0)) }}</strong>
                    {{ $targetPct }}% achieved
                </div>
            </div>
            <div class="onb-target__bar" aria-hidden="true">
                <span style="width: {{ max(3, $targetPct) }}%;"></span>
            </div>
            <div class="onb-target__meta">
                @if ((int) ($targetProgress['gap'] ?? 0) > 0)
                    <span><strong>{{ number_format((int) $targetProgress['gap']) }}</strong> remaining</span>
                @else
                    <span><strong>Target met</strong></span>
                @endif
                @if (! is_null($targetProgress['expected_pct_by_now'] ?? null))
                    <span>Expected by now: <strong>{{ (int) $targetProgress['expected_pct_by_now'] }}%</strong> of FY timeline</span>
                @endif
                @if (! is_null($targetProgress['pace_delta'] ?? null))
                    @php $paceDelta = (int) $targetProgress['pace_delta']; @endphp
                    <span>
                        FY pace:
                        <strong>@if ($paceDelta >= 0)+@endif{{ number_format($paceDelta) }}</strong>
                        vs expected
                    </span>
                @endif
            </div>
        </div>
    @endif

    @if ($insights !== [] || (($sectorBreakdown['rows'] ?? []) !== []))
        <div class="onb-analysis-grid">
            @if ($insights !== [])
                <div class="onb-panel">
                    <h3 class="onb-panel__title">Key insights</h3>
                    <ul class="onb-insights">
                        @foreach ($insights as $insight)
                            <li>{{ $insight }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
            @if (($sectorBreakdown['rows'] ?? []) !== [])
                <div class="onb-panel">
                    <h3 class="onb-panel__title">Sector mix (Phase 3)</h3>
                    <div class="onb-sector-list">
                        @foreach ($sectorBreakdown['rows'] as $sectorRow)
                            <div class="onb-sector-row">
                                <div class="onb-sector-row__top">
                                    <strong>{{ $sectorRow['sector'] }}</strong>
                                    <span>{{ number_format((int) $sectorRow['count']) }} · {{ (int) $sectorRow['pct'] }}%</span>
                                </div>
                                <div class="onb-sector-row__bar" aria-hidden="true">
                                    <span style="width: {{ max(4, (int) $sectorRow['pct']) }}%;"></span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    @endif

    {{-- District-wise cards --}}
    @if ($districtSummaries !== [])
        <div>
            <div class="onb-section-head" style="margin-bottom:0.65rem;">
                <div>
                    <h3>District-wise onboarding</h3>
                    <p>Click a district to filter applicants below. Share shows contribution within current hub/search scope.</p>
                </div>
                @php
                    $allDistrictQuery = array_filter(['hub' => $filters['hub'] ?? null, 'q' => $filters['q'] ?? null]);
                @endphp
                <a
                    href="{{ route($routeIndex, $allDistrictQuery) }}"
                    class="onb-district-all @if (! $activeDistrict) is-active @endif"
                >All districts</a>
            </div>
            <div class="onb-district-grid">
                @foreach ($districtSummaries as $districtRow)
                    @php
                        $cardQuery = array_filter([
                            'hub' => $filters['hub'] ?? null,
                            'district' => $districtRow['district_id'] > 0 ? $districtRow['district_id'] : null,
                            'q' => $filters['q'] ?? null,
                        ]);
                        $isActive = $activeDistrict > 0 && $activeDistrict === (int) $districtRow['district_id'];
                    @endphp
                    <a
                        href="{{ route($routeIndex, $cardQuery) }}"
                        class="onb-district-card @if ($isActive) is-active @endif"
                    >
                        <div class="onb-district-card__top">
                            <div>
                                <div class="onb-district-card__name">{{ $districtRow['district_name'] }}</div>
                                @if ($districtRow['hub_name'] !== '')
                                    <div class="onb-district-card__hub">{{ $districtRow['hub_name'] }}</div>
                                @endif
                            </div>
                            <div class="onb-district-card__count">{{ number_format((int) $districtRow['total']) }}</div>
                        </div>
                        <div class="onb-district-card__bar" aria-hidden="true">
                            <span style="width: {{ max(4, (int) $districtRow['share_pct']) }}%;"></span>
                        </div>
                        <div class="onb-district-card__meta">
                            <span><strong>{{ $districtRow['share_pct'] }}%</strong> share</span>
                            <span><strong>{{ $districtRow['female_pct'] }}%</strong> women</span>
                            <span><strong>{{ number_format((int) ($districtRow['potential_lakhpati_count'] ?? 0)) }}</strong> Potential Lakhpati ({{ $districtRow['potential_lakhpati_pct'] ?? 0 }}%)</span>
                            @if ((int) ($districtRow['target'] ?? 0) > 0)
                                <span><strong>{{ (int) ($districtRow['target_progress_pct'] ?? 0) }}%</strong> of target</span>
                            @endif
                            @if ((int) $districtRow['recent_7_days'] > 0)
                                <span><strong>+{{ number_format((int) $districtRow['recent_7_days']) }}</strong> this week</span>
                            @endif
                        </div>
                    </a>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Filters --}}
    <form method="get" action="{{ route($routeIndex) }}" class="onb-filters">
        <div class="onb-fld">
            <label for="fld-hub">Hub</label>
            <select id="fld-hub" name="hub">
                <option value="">All hubs</option>
                @foreach ($hubs as $hub)
                    <option value="{{ $hub->id }}" @selected((int) ($filters['hub'] ?? 0) === (int) $hub->id)>{{ $hub->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="onb-fld">
            <label for="fld-district">District</label>
            <select id="fld-district" name="district">
                <option value="">All districts</option>
                @foreach ($districts as $district)
                    <option value="{{ $district->id }}" @selected((int) ($filters['district'] ?? 0) === (int) $district->id)>{{ $district->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="onb-fld onb-fld--grow">
            <label for="fld-q">Search</label>
            <input
                id="fld-q"
                type="search"
                name="q"
                value="{{ $filters['q'] ?? '' }}"
                placeholder="Application no, name, phone, batch"
                autocomplete="off"
            >
        </div>
        <div class="onb-actions">
            @if (($filters['hub'] ?? null) || ($filters['district'] ?? null) || ($filters['q'] ?? null))
                <a href="{{ route($routeIndex) }}" class="btn-sm">Clear</a>
            @endif
            <a href="{{ route($routeExport ?? 'admin.onboarded.export', $filterQuery) }}" class="btn-sm">Export Excel</a>
            <button type="submit" class="btn-sm btn-primary">Apply</button>
        </div>
    </form>

    {{-- Applicant cards --}}
    <div>
        <div class="onb-list-head" style="margin-bottom:0.65rem;">
            <h3>Applicant records</h3>
            <div class="onb-list-meta">
                <strong>{{ number_format($rows->total()) }}</strong> total
                @if ($rows->total() > 0)
                    · showing {{ number_format($rows->firstItem() ?? 0) }}–{{ number_format($rows->lastItem() ?? 0) }}
                @endif
            </div>
        </div>

        @if ($rows->count() > 0)
            <div class="onb-applicant-grid">
                @foreach ($rows as $row)
                    @php
                        $isLegacy = ($row['data_source'] ?? '') === 'legacy_phase2';
                        $onboardedLabel = ! empty($row['onboarded_at'])
                            ? \Illuminate\Support\Carbon::parse($row['onboarded_at'])->timezone(config('app.timezone'))->format('d M Y, H:i')
                            : '—';
                    @endphp
                    <article class="onb-applicant-card">
                        <div class="onb-applicant-card__head">
                            <div>
                                <div class="onb-applicant-card__name">{{ $row['common_values']['applicant_name'] ?? $row['applicant_name'] ?? '—' }}</div>
                                <div class="onb-applicant-card__appno">{{ $row['common_values']['application_no'] ?? $row['application_no'] ?? '—' }}</div>
                            </div>
                            <span class="onb-badge {{ $isLegacy ? 'onb-badge--legacy' : 'onb-badge--phase3' }}">
                                {{ $isLegacy ? 'Legacy' : 'Phase 3' }}
                            </span>
                        </div>
                        <div class="onb-applicant-card__grid">
                            <div class="onb-applicant-card__field">
                                <label>District</label>
                                <span>{{ $row['district'] ?: '—' }}</span>
                            </div>
                            <div class="onb-applicant-card__field">
                                <label>Block</label>
                                <span>{{ $row['block_name'] ?: '—' }}</span>
                            </div>
                            <div class="onb-applicant-card__field">
                                <label>Phone</label>
                                <span>{{ $row['common_values']['phone'] ?? $row['phone'] ?? '—' }}</span>
                            </div>
                            <div class="onb-applicant-card__field">
                                <label>Gender</label>
                                <span>{{ $row['common_values']['gender'] ?? $row['gender'] ?? '—' }}</span>
                            </div>
                            <div class="onb-applicant-card__field onb-applicant-card__field--full">
                                <label>Onboarded at</label>
                                <span>{{ $onboardedLabel }}</span>
                            </div>
                        </div>
                        <div class="onb-applicant-card__batch">
                            <strong>{{ $row['onboarding_batch_name'] ?: '—' }}</strong>
                            @if ($row['hub_name'] ?? null)
                                · {{ $row['hub_name'] }}
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>
        @else
            <div class="onb-empty">No onboarded applicants found for the selected filters.</div>
        @endif

        @if ($rows->hasPages())
            <div class="onb-pager" style="margin-top:0.75rem;">
                {{ $rows->links() }}
            </div>
        @endif
    </div>

    {{-- Full export-oriented table (collapsed) --}}
    <details class="onb-full-table">
        <summary>View full data table (all columns for export reference)</summary>
        <div class="onb-table-wrap">
            <table class="onb-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Application No</th>
                        <th>Source</th>
                        <th>District</th>
                        <th>Hub</th>
                        <th>Batch</th>
                        <th>Onboarded at</th>
                        @foreach (($commonColumns ?? []) as $columnKey => $column)
                            @if ($columnKey === 'application_no')
                                @continue
                            @endif
                            <th>{{ $column['label'] }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr>
                            <td><strong>{{ (int) (($rows->firstItem() ?? 1) + $loop->index) }}</strong></td>
                            <td>{{ $row['common_values']['application_no'] ?? $row['application_no'] ?? '—' }}</td>
                            <td><span class="muted">{{ ($row['data_source'] ?? '') === 'legacy_phase2' ? 'Legacy Phase 2' : 'Phase 3' }}</span></td>
                            <td>
                                <div>{{ $row['district'] ?: '—' }}</div>
                                <div class="muted">Block: {{ $row['block_name'] ?: '—' }}</div>
                            </td>
                            <td>{{ $row['hub_name'] ?: '—' }}</td>
                            <td>
                                <strong>{{ $row['onboarding_batch_name'] ?: '—' }}</strong>
                                <div class="muted">Batch #{{ $row['onboarding_batch_id'] ?: '—' }}</div>
                            </td>
                            <td>{{ ! empty($row['onboarded_at']) ? \Illuminate\Support\Carbon::parse($row['onboarded_at'])->timezone(config('app.timezone'))->format('d M Y, H:i') : '—' }}</td>
                            @foreach (array_keys($commonColumns ?? []) as $columnKey)
                                @if ($columnKey === 'application_no')
                                    @continue
                                @endif
                                <td>{{ $row['common_values'][$columnKey] ?? '—' }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </details>
</div>
@endsection
