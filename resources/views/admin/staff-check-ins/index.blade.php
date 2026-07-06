@extends('layouts.admin')

@section('title', 'Staff attendance')
@section('heading', 'Staff attendance')

@push('styles')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="">
<style>
    .satt {
        --satt-indigo: #4f46e5;
        --satt-violet: #7c3aed;
        --satt-teal: #0d9488;
        --satt-green: #16a34a;
        --satt-red: #dc2626;
        --satt-amber: #d97706;
        --satt-slate: #64748b;
        --satt-border: #e2e8f0;
        font-family: 'DM Sans', system-ui, sans-serif;
        display: flex;
        flex-direction: column;
        gap: 1.25rem;
        padding-bottom: 2.5rem;
    }

    /* Hero banner */
    .satt-hero {
        position: relative;
        overflow: hidden;
        border-radius: 20px;
        padding: 1.5rem 1.75rem;
        background: linear-gradient(135deg, #312e81 0%, #4f46e5 45%, #7c3aed 100%);
        color: #fff;
        box-shadow: 0 20px 50px rgba(79, 70, 229, 0.28);
    }
    .satt-hero::before {
        content: '';
        position: absolute;
        top: -40%;
        right: -8%;
        width: 280px;
        height: 280px;
        border-radius: 50%;
        background: rgba(255,255,255,0.08);
        pointer-events: none;
    }
    .satt-hero::after {
        content: '';
        position: absolute;
        bottom: -50%;
        left: 20%;
        width: 200px;
        height: 200px;
        border-radius: 50%;
        background: rgba(255,255,255,0.05);
        pointer-events: none;
    }
    .satt-hero__inner {
        position: relative;
        z-index: 1;
        display: flex;
        flex-wrap: wrap;
        align-items: flex-start;
        justify-content: space-between;
        gap: 1rem;
    }
    .satt-hero__title {
        margin: 0 0 0.3rem;
        font-size: 1.5rem;
        font-weight: 800;
        letter-spacing: -0.02em;
    }
    .satt-hero__sub {
        margin: 0;
        font-size: 0.9rem;
        opacity: 0.88;
    }
    .satt-hero__actions {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
    }

    /* Stat cards */
    .satt-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 0.75rem;
    }
    .satt-stat {
        background: #fff;
        border: 1px solid var(--satt-border);
        border-radius: 16px;
        padding: 1rem 1.1rem;
        display: flex;
        align-items: center;
        gap: 0.85rem;
        box-shadow: 0 4px 18px rgba(15,23,42,0.05);
        transition: transform 0.15s ease, box-shadow 0.15s ease;
    }
    .satt-stat:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 24px rgba(15,23,42,0.09);
    }
    .satt-stat__icon {
        width: 44px;
        height: 44px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.1rem;
        flex-shrink: 0;
    }
    .satt-stat__icon--staff { background: linear-gradient(135deg, #e0e7ff, #c7d2fe); color: #4338ca; }
    .satt-stat__icon--present { background: linear-gradient(135deg, #dcfce7, #bbf7d0); color: #15803d; }
    .satt-stat__icon--absent { background: linear-gradient(135deg, #fee2e2, #fecaca); color: #b91c1c; }
    .satt-stat__icon--rate { background: linear-gradient(135deg, #fef3c7, #fde68a); color: #b45309; }
    .satt-stat__icon--month { background: linear-gradient(135deg, #ccfbf1, #99f6e4); color: #0f766e; }
    .satt-stat__label {
        display: block;
        font-size: 0.68rem;
        text-transform: uppercase;
        letter-spacing: 0.07em;
        color: var(--satt-slate);
        font-weight: 700;
    }
    .satt-stat__value {
        display: block;
        font-size: 1.4rem;
        font-weight: 800;
        color: #0f172a;
        line-height: 1.1;
    }

    /* Buttons */
    .satt-btn {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        padding: 0.55rem 1.05rem;
        border: none;
        border-radius: 10px;
        font-weight: 700;
        font-size: 0.84rem;
        cursor: pointer;
        text-decoration: none;
        transition: transform 0.12s ease, box-shadow 0.12s ease, opacity 0.12s;
        white-space: nowrap;
    }
    .satt-btn:hover { transform: translateY(-1px); color: inherit; }
    .satt-btn--primary { background: #fff; color: var(--satt-indigo); box-shadow: 0 4px 14px rgba(0,0,0,0.12); }
    .satt-btn--primary:hover { color: var(--satt-indigo); }
    .satt-btn--excel { background: linear-gradient(135deg, #059669, #10b981); color: #fff; box-shadow: 0 4px 14px rgba(5,150,105,0.35); }
    .satt-btn--excel:hover { color: #fff; }
    .satt-btn--pdf { background: linear-gradient(135deg, #dc2626, #ef4444); color: #fff; box-shadow: 0 4px 14px rgba(220,38,38,0.3); }
    .satt-btn--pdf:hover { color: #fff; }
    .satt-btn--ghost {
        background: rgba(255,255,255,0.15);
        color: #fff;
        border: 1px solid rgba(255,255,255,0.35);
        backdrop-filter: blur(4px);
    }
    .satt-btn--ghost:hover { color: #fff; background: rgba(255,255,255,0.25); }
    .satt-btn--indigo { background: var(--satt-indigo); color: #fff; }
    .satt-btn--indigo:hover { color: #fff; }
    .satt-btn--outline {
        background: transparent;
        color: var(--satt-indigo);
        border: 1.5px solid var(--satt-indigo);
    }
    .satt-btn--outline:hover { color: var(--satt-indigo); background: #eef2ff; }
    .satt-btn--sm { padding: 0.35rem 0.7rem; font-size: 0.78rem; border-radius: 8px; }

    /* Tabs */
    .satt-tabs {
        display: flex;
        gap: 0.35rem;
        background: #f1f5f9;
        border-radius: 12px;
        padding: 0.3rem;
        width: fit-content;
    }
    .satt-tab {
        padding: 0.5rem 1.1rem;
        border-radius: 9px;
        font-size: 0.84rem;
        font-weight: 700;
        color: var(--satt-slate);
        text-decoration: none;
        transition: background 0.15s, color 0.15s, box-shadow 0.15s;
    }
    .satt-tab:hover { color: var(--satt-indigo); }
    .satt-tab.is-active {
        background: #fff;
        color: var(--satt-indigo);
        box-shadow: 0 2px 8px rgba(15,23,42,0.08);
    }

    /* Filters */
    .satt-filter {
        background: #fff;
        border: 1px solid var(--satt-border);
        border-radius: 16px;
        padding: 1.1rem 1.25rem;
        box-shadow: 0 4px 18px rgba(15,23,42,0.04);
    }
    .satt-filter-row {
        display: flex;
        flex-wrap: wrap;
        gap: 0.6rem;
        align-items: flex-end;
    }
    .satt-field {
        display: flex;
        flex-direction: column;
        gap: 0.28rem;
        min-width: 140px;
    }
    .satt-field label {
        font-size: 0.7rem;
        font-weight: 700;
        color: #475569;
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }
    .satt-field input,
    .satt-field select {
        padding: 0.52rem 0.65rem;
        border: 1.5px solid #cbd5e1;
        border-radius: 9px;
        font-size: 0.86rem;
        background: #fff;
        transition: border-color 0.15s, box-shadow 0.15s;
    }
    .satt-field input:focus,
    .satt-field select:focus {
        outline: none;
        border-color: var(--satt-indigo);
        box-shadow: 0 0 0 3px rgba(79,70,229,0.12);
    }

    /* Monthly grid */
    .satt-grid-card {
        background: #fff;
        border: 1px solid var(--satt-border);
        border-radius: 16px;
        overflow: hidden;
        box-shadow: 0 4px 18px rgba(15,23,42,0.05);
    }
    .satt-grid-card__head {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
        padding: 0.9rem 1.15rem;
        background: linear-gradient(90deg, #f8fafc, #f1f5f9);
        border-bottom: 1px solid var(--satt-border);
    }
    .satt-grid-card__title {
        margin: 0;
        font-size: 0.95rem;
        font-weight: 800;
        color: #0f172a;
    }
    .satt-legend {
        display: flex;
        flex-wrap: wrap;
        gap: 0.85rem;
        font-size: 0.78rem;
        color: var(--satt-slate);
        font-weight: 600;
    }
    .satt-legend__item {
        display: inline-flex;
        align-items: center;
        gap: 0.3rem;
    }
    .satt-grid-wrap {
        overflow: auto;
        max-height: calc(100vh - 320px);
        min-height: 200px;
    }
    .satt-grid {
        border-collapse: separate;
        border-spacing: 0;
        width: max-content;
        min-width: 100%;
        font-size: 0.82rem;
    }
    .satt-grid th,
    .satt-grid td {
        border-bottom: 1px solid #f1f5f9;
        border-right: 1px solid #f1f5f9;
        text-align: center;
        padding: 0;
    }
    .satt-grid thead th {
        position: sticky;
        top: 0;
        z-index: 3;
        background: linear-gradient(180deg, #0f766e, #0d9488);
        color: #fff;
        font-size: 0.72rem;
        font-weight: 700;
        padding: 0.55rem 0.2rem;
        min-width: 30px;
        letter-spacing: 0.02em;
    }
    .satt-grid thead th.satt-grid__emp {
        left: 0;
        z-index: 4;
        min-width: 200px;
        text-align: left;
        padding: 0.55rem 0.85rem;
        background: linear-gradient(180deg, #312e81, #4f46e5);
    }
    .satt-grid thead th.is-today {
        background: linear-gradient(180deg, #b45309, #d97706);
        box-shadow: inset 0 -3px 0 rgba(255,255,255,0.4);
    }
    .satt-grid thead th.is-weekend {
        background: linear-gradient(180deg, #475569, #64748b);
    }
    .satt-grid tbody td.satt-grid__emp {
        position: sticky;
        left: 0;
        z-index: 2;
        background: #fff;
        text-align: left;
        padding: 0.5rem 0.85rem;
        min-width: 200px;
        border-right: 2px solid #e2e8f0;
        font-weight: 600;
        color: #0f172a;
    }
    .satt-grid tbody tr:nth-child(even) td.satt-grid__emp { background: #fafbfc; }
    .satt-grid tbody tr:hover td { background: #f8fafc; }
    .satt-grid tbody tr:hover td.satt-grid__emp { background: #eef2ff; }
    .satt-grid__emp-sub {
        display: block;
        font-size: 0.7rem;
        font-weight: 500;
        color: var(--satt-slate);
        margin-top: 0.1rem;
    }
    .satt-grid__cell {
        width: 32px;
        height: 36px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto;
    }
    .satt-grid td.is-weekend { background: #f8fafc; }
    .satt-grid td.is-today { background: #fffbeb; }
    .satt-grid tbody td.satt-grid__sunday {
        background: #f1f5f9 !important;
        vertical-align: middle;
        padding: 0;
        min-width: 34px;
        border-left: 1px solid #e2e8f0;
        border-right: 1px solid #e2e8f0;
    }
    .satt-grid tbody tr:hover td.satt-grid__sunday { background: #f1f5f9 !important; }
    .satt-grid__sunday-label {
        writing-mode: vertical-rl;
        text-orientation: mixed;
        transform: rotate(180deg);
        font-size: 0.65rem;
        font-weight: 700;
        color: #94a3b8;
        letter-spacing: 0.12em;
        text-transform: uppercase;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 0.5rem 0;
        white-space: nowrap;
        line-height: 1.1;
    }
    .satt-mark {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 22px;
        height: 22px;
        border-radius: 6px;
        font-size: 0.75rem;
        font-weight: 800;
    }
    .satt-mark--present {
        background: linear-gradient(135deg, #dcfce7, #bbf7d0);
        color: #15803d;
        box-shadow: 0 2px 6px rgba(22,163,74,0.2);
    }
    .satt-mark--absent {
        background: linear-gradient(135deg, #fee2e2, #fecaca);
        color: #b91c1c;
        box-shadow: 0 2px 6px rgba(220,38,38,0.15);
        border: none;
        cursor: pointer;
    }
    .satt-mark--absent:hover { filter: brightness(0.95); }
    .satt-mark--future {
        color: #cbd5e1;
        font-weight: 400;
    }
    .satt-grid__summary {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        margin-left: 0.5rem;
        font-size: 0.68rem;
        font-weight: 700;
    }
    .satt-grid__summary span { padding: 0.1rem 0.35rem; border-radius: 4px; }
    .satt-grid__summary .p { background: #dcfce7; color: #15803d; }
    .satt-grid__summary .a { background: #fee2e2; color: #b91c1c; }

    .satt-grid thead th.satt-grid__col-total,
    .satt-grid tbody td.satt-grid__col-total {
        position: sticky;
        z-index: 2;
        min-width: 58px;
        padding: 0.45rem 0.5rem;
        text-align: center;
        vertical-align: middle;
        background: #f8fafc;
    }
    .satt-grid thead th.satt-grid__col-total {
        z-index: 5;
        font-size: 0.68rem;
        line-height: 1.2;
        padding: 0.5rem 0.35rem;
        color: #fff;
    }
    .satt-grid thead th.satt-grid__col-present {
        right: 58px;
        border-left: 2px solid #e2e8f0;
        background: linear-gradient(180deg, #15803d, #16a34a);
    }
    .satt-grid thead th.satt-grid__col-absent {
        right: 0;
        background: linear-gradient(180deg, #b91c1c, #dc2626);
    }
    .satt-grid tbody td.satt-grid__col-present {
        right: 58px;
        border-left: 2px solid #e2e8f0;
    }
    .satt-grid tbody td.satt-grid__col-absent {
        right: 0;
    }
    .satt-grid tbody tr:hover td.satt-grid__col-present { background: #f0fdf4; }
    .satt-grid tbody tr:hover td.satt-grid__col-absent { background: #fef2f2; }
    .satt-grid__total-val {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 2rem;
        padding: 0.2rem 0.5rem;
        border-radius: 8px;
        font-size: 0.85rem;
        font-weight: 800;
        line-height: 1.2;
    }
    .satt-grid__total-val--p { background: #dcfce7; color: #15803d; }
    .satt-grid__total-val--a { background: #fee2e2; color: #b91c1c; }

    .satt-grid-foot {
        padding: 0.85rem 1.15rem;
        border-top: 1px solid var(--satt-border);
        background: linear-gradient(180deg, #fafbfc, #f8fafc);
        font-size: 0.82rem;
        color: #475569;
        line-height: 1.5;
    }
    .satt-grid-foot strong { color: #334155; }
    .satt-grid-foot__icon { color: #64748b; margin-right: 0.35rem; }
    .satt-sun-chips {
        display: flex;
        flex-wrap: wrap;
        gap: 0.4rem;
        margin-top: 0.45rem;
    }
    .satt-sun-chip {
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
        padding: 0.22rem 0.55rem;
        border-radius: 999px;
        background: #f1f5f9;
        border: 1px solid #e2e8f0;
        font-size: 0.76rem;
        font-weight: 600;
        color: #64748b;
    }

    /* Daily table */
    .satt-table-wrap {
        overflow-x: auto;
        background: #fff;
        border: 1px solid var(--satt-border);
        border-radius: 16px;
        box-shadow: 0 4px 18px rgba(15,23,42,0.04);
    }
    .satt-table { width: 100%; border-collapse: collapse; font-size: 0.86rem; }
    .satt-table th, .satt-table td { padding: 0.7rem 0.9rem; text-align: left; border-bottom: 1px solid #f1f5f9; }
    .satt-table th {
        background: linear-gradient(180deg, #f8fafc, #f1f5f9);
        font-size: 0.7rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: var(--satt-slate);
        font-weight: 700;
    }
    .satt-table tr.row--absent { background: #fff5f5; }
    .satt-table tr.row--present { background: #f0fdf4; }
    .satt-badge {
        display: inline-block;
        padding: 0.22rem 0.55rem;
        border-radius: 999px;
        font-size: 0.72rem;
        font-weight: 700;
    }
    .satt-badge--present { background: #dcfce7; color: #166534; }
    .satt-badge--absent { background: #fee2e2; color: #991b1b; }
    .satt-role { font-size: 0.75rem; color: var(--satt-slate); }

    /* Modal */
    .satt-modal[hidden] { display: none; }
    .satt-modal {
        position: fixed; inset: 0; z-index: 9999;
        display: flex; align-items: center; justify-content: center; padding: 1rem;
    }
    .satt-modal__backdrop { position: absolute; inset: 0; background: rgba(15,23,42,0.55); backdrop-filter: blur(4px); }
    .satt-modal__panel {
        position: relative; width: min(100%, 520px);
        background: #fff; border-radius: 20px; padding: 1.35rem;
        box-shadow: 0 24px 60px rgba(15,23,42,0.25);
        max-height: 90vh; overflow-y: auto;
    }
    .satt-modal__close {
        position: absolute; top: 0.65rem; right: 0.8rem;
        width: 2rem; height: 2rem; border: none; border-radius: 999px;
        background: #f1f5f9; font-size: 1.25rem; cursor: pointer; line-height: 1;
    }
    .satt-modal__title { margin: 0 0 0.35rem; font-size: 1.15rem; font-weight: 800; }
    .satt-modal__meta { margin: 0 0 0.75rem; font-size: 0.85rem; color: var(--satt-slate); }
    #satt-detail-map { height: 280px; border-radius: 14px; border: 1px solid var(--satt-border); }
    .satt-modal__coords { margin-top: 0.65rem; font-family: ui-monospace, monospace; font-size: 0.82rem; color: #334155; }
    .satt-modal__link { display: inline-block; margin-top: 0.5rem; color: var(--satt-teal); font-weight: 700; font-size: 0.85rem; }

    .satt-empty {
        text-align: center;
        color: var(--satt-slate);
        padding: 3rem 1.5rem;
        font-size: 0.9rem;
    }
</style>
@endpush

@section('content')
@php
    $filterQuery = array_filter([
        'view' => $viewMode,
        'month' => $month->format('Y-m'),
        'date' => $date->toDateString(),
        'role' => $roleFilter ?: null,
        'hub_id' => $hubId > 0 ? $hubId : null,
        'district_id' => $districtId > 0 ? $districtId : null,
        'status' => $statusFilter ?: null,
    ]);
    $monthlyExportQuery = http_build_query(array_filter([
        'month' => $month->format('Y-m'),
        'role' => $roleFilter ?: null,
        'hub_id' => $hubId > 0 ? $hubId : null,
        'district_id' => $districtId > 0 ? $districtId : null,
    ]));
    $dailyExportQuery = http_build_query(array_filter([
        'date' => $date->toDateString(),
        'role' => $roleFilter ?: null,
        'hub_id' => $hubId > 0 ? $hubId : null,
        'district_id' => $districtId > 0 ? $districtId : null,
        'status' => $statusFilter ?: null,
    ]));
    $presentPct = $summary['total'] > 0 ? round(($summary['present'] / $summary['total']) * 100, 1) : 0;
    $todayDay = now()->day;
    $isCurrentMonth = $month->isSameMonth(now());
@endphp

<div class="satt">
    {{-- Hero --}}
    <div class="satt-hero">
        <div class="satt-hero__inner">
            <div>
                <h2 class="satt-hero__title">Staff Attendance Dashboard</h2>
                <p class="satt-hero__sub">
                    Monthly attendance sheet for all staff (CDO excluded) &mdash;
                    {{ $month->format('F Y') }}
                </p>
            </div>
            <div class="satt-hero__actions">
                <a href="{{ route('admin.staff-check-ins.monthly-export.excel') }}?{{ $monthlyExportQuery }}" class="satt-btn satt-btn--excel">
                    <i class="fa-solid fa-file-excel"></i> Export Excel
                </a>
                <a href="{{ route('admin.staff-check-ins.monthly-export.pdf') }}?{{ $monthlyExportQuery }}" class="satt-btn satt-btn--pdf">
                    <i class="fa-solid fa-file-pdf"></i> Export PDF
                </a>
            </div>
        </div>
    </div>

    {{-- Stats --}}
    <div class="satt-stats">
        <div class="satt-stat">
            <div class="satt-stat__icon satt-stat__icon--staff"><i class="fa-solid fa-users"></i></div>
            <div>
                <span class="satt-stat__label">Total staff</span>
                <span class="satt-stat__value">{{ number_format($monthlyGrid['total_staff']) }}</span>
            </div>
        </div>
        <div class="satt-stat">
            <div class="satt-stat__icon satt-stat__icon--present"><i class="fa-solid fa-circle-check"></i></div>
            <div>
                <span class="satt-stat__label">Present marks</span>
                <span class="satt-stat__value">{{ number_format($monthlyGrid['total_present']) }}</span>
            </div>
        </div>
        <div class="satt-stat">
            <div class="satt-stat__icon satt-stat__icon--absent"><i class="fa-solid fa-circle-xmark"></i></div>
            <div>
                <span class="satt-stat__label">Absent marks</span>
                <span class="satt-stat__value">{{ number_format($monthlyGrid['total_absent']) }}</span>
            </div>
        </div>
        <div class="satt-stat">
            <div class="satt-stat__icon satt-stat__icon--rate"><i class="fa-solid fa-chart-line"></i></div>
            <div>
                <span class="satt-stat__label">Attendance rate</span>
                <span class="satt-stat__value">{{ $monthlyGrid['rate_pct'] }}%</span>
            </div>
        </div>
        <div class="satt-stat">
            <div class="satt-stat__icon satt-stat__icon--month"><i class="fa-solid fa-calendar-days"></i></div>
            <div>
                <span class="satt-stat__label">Working days elapsed</span>
                <span class="satt-stat__value">{{ $monthlyGrid['elapsed_days'] }}/{{ $monthlyGrid['working_days_in_month'] }}</span>
            </div>
        </div>
    </div>

    {{-- View tabs --}}
    <div class="satt-tabs">
        <a href="{{ route('admin.staff-check-ins.index', array_merge($filterQuery, ['view' => 'monthly'])) }}"
           class="satt-tab @if ($viewMode === 'monthly') is-active @endif">
            <i class="fa-solid fa-table-cells"></i> Monthly sheet
        </a>
        <a href="{{ route('admin.staff-check-ins.index', array_merge($filterQuery, ['view' => 'daily'])) }}"
           class="satt-tab @if ($viewMode === 'daily') is-active @endif">
            <i class="fa-solid fa-list"></i> Daily detail
        </a>
    </div>

    {{-- Filters --}}
    <form method="get" class="satt-filter">
        <input type="hidden" name="view" value="{{ $viewMode }}">
        <div class="satt-filter-row">
            <div class="satt-field">
                <label for="month">Month</label>
                <input type="month" name="month" id="month" value="{{ $month->format('Y-m') }}">
            </div>
            @if ($viewMode === 'daily')
                <div class="satt-field">
                    <label for="date">Date</label>
                    <input type="date" name="date" id="date" value="{{ $date->toDateString() }}">
                </div>
                <div class="satt-field">
                    <label for="status">Status</label>
                    <select name="status" id="status">
                        <option value="">All</option>
                        <option value="present" @selected($statusFilter === 'present')>Present only</option>
                        <option value="absent" @selected($statusFilter === 'absent')>Absent only</option>
                    </select>
                </div>
            @endif
            <div class="satt-field">
                <label for="role">Role</label>
                <select name="role" id="role">
                    <option value="">All roles</option>
                    @foreach ($roleOptions as $value => $label)
                        <option value="{{ $value }}" @selected($roleFilter === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="satt-field">
                <label for="hub_id">Hub</label>
                <select name="hub_id" id="hub_id">
                    <option value="">All hubs</option>
                    @foreach ($hubs as $hub)
                        <option value="{{ $hub->id }}" @selected($hubId === (int) $hub->id)>{{ $hub->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="satt-field">
                <label for="district_id">District</label>
                <select name="district_id" id="district_id">
                    <option value="">All districts</option>
                    @foreach ($districts as $district)
                        <option value="{{ $district->id }}" @selected($districtId === (int) $district->id)>{{ $district->name }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="satt-btn satt-btn--indigo">Apply filters</button>
            <a href="{{ route('admin.staff-check-ins.index', ['view' => $viewMode]) }}" class="satt-btn satt-btn--outline">Reset</a>
        </div>
    </form>

    @if ($viewMode === 'monthly')
        {{-- Monthly grid --}}
            <div class="satt-grid-card">
            <div class="satt-grid-card__head">
                <h3 class="satt-grid-card__title">
                    <i class="fa-solid fa-calendar-check" style="color:var(--satt-teal);"></i>
                    Attendance sheet &mdash; {{ $month->format('F Y') }}
                </h3>
                <div class="satt-legend">
                    <span class="satt-legend__item">
                        <span class="satt-mark satt-mark--present">✓</span> Present
                    </span>
                    <span class="satt-legend__item">
                        <span class="satt-mark satt-mark--absent">✗</span> Absent (click for reason)
                    </span>
                    <span class="satt-legend__item">
                        <span class="satt-mark satt-mark--future">—</span> Future
                    </span>
                </div>
            </div>
            @include('partials.staff-monthly-attendance-grid', [
                'monthlyGrid' => $monthlyGrid,
                'month' => $month,
                'gridContext' => 'admin',
            ])
        </div>
    @else
        {{-- Daily detail view --}}
        <div style="display:flex;flex-wrap:wrap;gap:0.5rem;justify-content:flex-end;">
            <a href="{{ route('admin.staff-check-ins.export') }}?{{ $dailyExportQuery }}" class="satt-btn satt-btn--excel">
                <i class="fa-solid fa-file-excel"></i> Export daily Excel
            </a>
        </div>
        <div class="satt-table-wrap">
            <table class="satt-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Staff</th>
                        <th>Role / designation</th>
                        <th>Hub / district</th>
                        <th>Status</th>
                        <th>Check-in time</th>
                        <th>Absent reason</th>
                        <th>View location</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($summary['rows'] as $index => $row)
                        @php
                            $user = $row['user'];
                            $checkIn = $row['check_in'];
                        @endphp
                        <tr class="{{ $row['present'] ? 'row--present' : 'row--absent' }}">
                            <td style="color:var(--satt-slate);font-weight:700;">{{ $index + 1 }}</td>
                            <td>
                                <strong>{{ $user->name }}</strong><br>
                                <span class="satt-role">{{ $user->email }}</span>
                            </td>
                            <td>
                                {{ $roleOptions[$user->role] ?? $user->role }}<br>
                                <span class="satt-role">{{ $user->designationRecord?->name ?? '—' }}</span>
                            </td>
                            <td>
                                {{ $user->hub?->name ?? '—' }}<br>
                                <span class="satt-role">{{ $user->district?->name ?? '—' }}</span>
                            </td>
                            <td>
                                @if ($row['present'])
                                    <span class="satt-badge satt-badge--present">Present</span>
                                @else
                                    <span class="satt-badge satt-badge--absent">Absent</span>
                                @endif
                            </td>
                            <td>
                                @if ($checkIn)
                                    {{ $checkIn->marked_at->timezone(config('app.timezone'))->format('g:i A') }}
                                @else
                                    —
                                @endif
                            </td>
                            <td>
                                @if (! $row['present'] && ! $date->isSunday())
                                    @if ($row['absent_reason'] ?? null)
                                        <span title="{{ e($row['absent_reason']) }}">{{ \Illuminate\Support\Str::limit($row['absent_reason'], 60) }}</span>
                                    @else
                                        <span class="satt-role">No reason provided</span>
                                    @endif
                                @else
                                    —
                                @endif
                            </td>
                            <td>
                                @if ($checkIn)
                                    <button type="button" class="satt-btn satt-btn--sm satt-btn--indigo satt-view-detail"
                                        data-name="{{ e($user->name) }}"
                                        data-role="{{ e($roleOptions[$user->role] ?? $user->role) }}"
                                        data-designation="{{ e($user->designationRecord?->name ?? '—') }}"
                                        data-hub="{{ e($user->hub?->name ?? '—') }}"
                                        data-district="{{ e($user->district?->name ?? '—') }}"
                                        data-time="{{ $checkIn->marked_at->timezone(config('app.timezone'))->format('g:i A, d M Y') }}"
                                        data-lat="{{ $checkIn->latitude }}"
                                        data-lng="{{ $checkIn->longitude }}"
                                        data-accuracy="{{ $checkIn->accuracy_m ?? '' }}"
                                        data-maps="{{ $checkIn->googleMapsUrl() }}">
                                        <i class="fa-solid fa-location-dot"></i> View
                                    </button>
                                @else
                                    <span class="satt-role">No check-in</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="satt-empty">No staff match these filters.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif
</div>

<div class="satt-modal" id="satt-absent-modal" role="dialog" aria-modal="true" hidden>
    <div class="satt-modal__backdrop" data-satt-absent-close></div>
    <div class="satt-modal__panel">
        <button type="button" class="satt-modal__close" data-satt-absent-close aria-label="Close">&times;</button>
        <h2 class="satt-modal__title" id="satt-absent-name">—</h2>
        <p class="satt-modal__meta" id="satt-absent-date"></p>
        <div id="satt-absent-reason" style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:1rem;font-size:0.9rem;color:#334155;line-height:1.55;white-space:pre-wrap;"></div>
    </div>
</div>

<div class="satt-modal" id="satt-detail-modal" role="dialog" aria-modal="true" hidden>
    <div class="satt-modal__backdrop" data-satt-close></div>
    <div class="satt-modal__panel">
        <button type="button" class="satt-modal__close" data-satt-close aria-label="Close">&times;</button>
        <h2 class="satt-modal__title" id="satt-detail-name">—</h2>
        <p class="satt-modal__meta" id="satt-detail-meta"></p>
        <div id="satt-detail-map"></div>
        <p class="satt-modal__coords" id="satt-detail-coords"></p>
        <a href="#" id="satt-detail-maps-link" class="satt-modal__link" target="_blank" rel="noopener">Open in Google Maps →</a>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
<script>
(function () {
    const modal = document.getElementById('satt-detail-modal');
    if (!modal) return;

    let detailMap = null;

    function closeModal() {
        modal.hidden = true;
        document.body.style.overflow = '';
        if (detailMap) {
            detailMap.remove();
            detailMap = null;
        }
    }

    function openModal(btn) {
        document.getElementById('satt-detail-name').textContent = btn.dataset.name;
        document.getElementById('satt-detail-meta').textContent =
            (btn.dataset.role || '') + ' · ' + (btn.dataset.designation || '') +
            ' · ' + (btn.dataset.hub || '') + ' / ' + (btn.dataset.district || '') +
            ' · ' + (btn.dataset.time || '');
        const lat = parseFloat(btn.dataset.lat);
        const lng = parseFloat(btn.dataset.lng);
        const acc = btn.dataset.accuracy;
        document.getElementById('satt-detail-coords').textContent =
            'Lat ' + lat.toFixed(6) + ', Lng ' + lng.toFixed(6) +
            (acc ? ' · ±' + parseFloat(acc).toFixed(0) + ' m' : '');
        document.getElementById('satt-detail-maps-link').href = btn.dataset.maps || '#';

        modal.hidden = false;
        document.body.style.overflow = 'hidden';

        requestAnimationFrame(function () {
            if (detailMap) detailMap.remove();
            detailMap = L.map('satt-detail-map').setView([lat, lng], 15);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; OpenStreetMap'
            }).addTo(detailMap);
            L.marker([lat, lng]).addTo(detailMap).bindPopup(btn.dataset.name).openPopup();
            setTimeout(function () { detailMap.invalidateSize(); }, 150);
        });
    }

    document.querySelectorAll('.satt-view-detail').forEach(function (btn) {
        btn.addEventListener('click', function () { openModal(btn); });
    });
    modal.querySelectorAll('[data-satt-close]').forEach(function (el) {
        el.addEventListener('click', closeModal);
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && !modal.hidden) closeModal();
    });

    const absentModal = document.getElementById('satt-absent-modal');
    if (absentModal) {
        function closeAbsentModal() {
            absentModal.hidden = true;
            document.body.style.overflow = '';
        }
        document.querySelectorAll('.satt-absent-view').forEach(function (btn) {
            btn.addEventListener('click', function () {
                document.getElementById('satt-absent-name').textContent = btn.dataset.name || 'Staff';
                document.getElementById('satt-absent-date').textContent = 'Absent on ' + (btn.dataset.dateLabel || '');
                document.getElementById('satt-absent-reason').textContent =
                    (btn.dataset.reason || '').trim() || 'No reason provided.';
                absentModal.hidden = false;
                document.body.style.overflow = 'hidden';
            });
        });
        absentModal.querySelectorAll('[data-satt-absent-close]').forEach(function (el) {
            el.addEventListener('click', closeAbsentModal);
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && !absentModal.hidden) closeAbsentModal();
        });
    }
})();
</script>
@endpush
