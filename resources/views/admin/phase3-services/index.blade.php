@extends('layouts.admin')

@section('title', 'Phase 3 Service Cases')
@section('heading', 'Phase 3 Service Cases')

@section('content')
    @php
        $statusLabel = [
            'draft' => 'Draft',
            'pending_approval' => 'Pending approval',
            'approved' => 'Approved',
            'sent_back' => 'Sent back',
            'rejected' => 'Rejected',
            'cancelled' => 'Cancelled',
        ];
        $statusStyle = [
            'draft' => 'background:#f3f4f6;color:#374151;border:1px solid #d1d5db;',
            'pending_approval' => 'background:#fff7ed;color:#9a3412;border:1px solid #fed7aa;',
            'approved' => 'background:#ecfdf5;color:#166534;border:1px solid #bbf7d0;',
            'sent_back' => 'background:#fff7ed;color:#b45309;border:1px solid #fed7aa;',
            'rejected' => 'background:#fef2f2;color:#b91c1c;border:1px solid #fecaca;',
            'cancelled' => 'background:#f8fafc;color:#475569;border:1px solid #e2e8f0;',
        ];
        $activeFilterCount = collect($filters)->filter(fn ($v) => (string) $v !== '')->count();
        $legacyPreviews = $legacyPreviews ?? [];
        $unifiedMarketLinkage = (bool) ($unifiedMarketLinkage ?? false);
        $uniqueIncubateesView = (bool) ($uniqueIncubateesView ?? false);
        $listQuery = request()->query();
        $uniqueViewQuery = array_merge($listQuery, ['unique_incubatees' => '1', 'page' => null]);
        $allRowsQuery = collect($listQuery)->except(['unique_incubatees', 'page'])->all();
    @endphp

    <style>
        .p3-table-card { border: 1px solid #e5e7eb; border-radius: 12px; background: #fff; overflow: hidden; box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04); }
        .p3-table-wrap { overflow-x: auto; }
        .p3-table { width: 100%; border-collapse: collapse; min-width: 1180px; font-size: 0.84rem; }
        .p3-table thead th {
            position: sticky; top: 0; z-index: 2;
            text-align: left; font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.05em;
            color: #64748b; background: #f8fafc; padding: 0.62rem 0.7rem; border-bottom: 1px solid #e2e8f0;
            white-space: nowrap;
        }
        .p3-table td { padding: 0.62rem 0.7rem; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
        .p3-table tr.p3-row--pending_approval td { background: #fffbeb; }
        .p3-table tr.p3-row--sent_back td { background: #fff7ed; }
        .p3-table tr.p3-row--approved td { background: #ecfdf5; }
        .p3-table tr.p3-row--rejected td { background: #fef2f2; }
        .p3-table tr.p3-row--draft td { background: #f9fafb; }
        .p3-table tr.p3-row--cancelled td { background: #f8fafc; }
        .p3-table tbody tr:hover td { filter: brightness(0.98); }
        .p3-sr { width: 2.8rem; text-align: center; color: #64748b; font-weight: 700; }
        .p3-name { font-weight: 700; color: #0f172a; }
        .p3-sub { font-size: 0.76rem; color: #64748b; margin-top: 0.12rem; }
        .p3-pill { display: inline-flex; align-items: center; padding: 0.14rem 0.48rem; border-radius: 999px; font-size: 0.72rem; font-weight: 700; }
        .p3-pill--legacy { background: #f1f5f9; border: 1px solid #cbd5e1; color: #475569; margin-left: 0.25rem; }
        .p3-pill--batch { background: #eff6ff; border: 1px solid #bfdbfe; color: #1d4ed8; }
        .p3-remark { max-width: 14rem; color: #475569; font-size: 0.8rem; word-break: break-word; white-space: normal; }
        .p3-link { color: #4338ca; font-weight: 600; text-decoration: none; }
        .p3-link:hover { text-decoration: underline; }
        .p3-btn { background: #fff; border: 1px solid #cbd5e1; color: #1e293b; padding: 0.24rem 0.5rem; border-radius: 6px; cursor: pointer; font-size: 0.76rem; font-weight: 600; }
        .p3-pill--mode-offline { background:#fff7ed; border:1px solid #fed7aa; color:#c2410c; }
        .p3-pill--mode-online { background:#eff6ff; border:1px solid #bfdbfe; color:#1d4ed8; }
        .p3-count-hint { margin-bottom: 0.65rem; color: #475569; font-size: 0.86rem; }
        .p3-deliverable-highlight {
            margin-bottom: 1rem;
            padding: 0.9rem 1rem 1rem;
            border-radius: 14px;
            border: 2px solid #fdba74;
            background: linear-gradient(135deg, #fff7ed 0%, #fffbeb 45%, #eff6ff 100%);
            box-shadow: 0 8px 24px rgba(194, 65, 12, 0.08);
        }
        .p3-deliverable-highlight__head {
            display: flex;
            flex-wrap: wrap;
            align-items: flex-start;
            justify-content: space-between;
            gap: 0.75rem;
            margin-bottom: 0.85rem;
        }
        .p3-deliverable-highlight__title {
            margin: 0;
            font-size: 0.98rem;
            font-weight: 800;
            color: #9a3412;
        }
        .p3-deliverable-highlight__sub {
            margin: 0.2rem 0 0;
            font-size: 0.8rem;
            color: #78716c;
            max-width: 42rem;
        }
        .p3-deliverable-highlight__grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 0.75rem;
        }
        .p3-deliverable-stat {
            border-radius: 12px;
            padding: 0.85rem 0.95rem;
            border: 2px solid transparent;
            box-shadow: 0 2px 8px rgba(15, 23, 42, 0.06);
        }
        .p3-deliverable-stat--total {
            background: #fff;
            border-color: #fb923c;
        }
        .p3-deliverable-stat--offline {
            background: #fff7ed;
            border-color: #f97316;
        }
        .p3-deliverable-stat--online {
            background: #eff6ff;
            border-color: #3b82f6;
        }
        .p3-deliverable-stat__label {
            font-size: 0.76rem;
            font-weight: 700;
            line-height: 1.35;
        }
        .p3-deliverable-stat__value {
            margin-top: 0.35rem;
            font-size: 1.65rem;
            font-weight: 900;
            line-height: 1;
        }
        .p3-deliverable-stat--total .p3-deliverable-stat__label,
        .p3-deliverable-stat--total .p3-deliverable-stat__value { color: #c2410c; }
        .p3-deliverable-stat--offline .p3-deliverable-stat__label,
        .p3-deliverable-stat--offline .p3-deliverable-stat__value { color: #c2410c; }
        .p3-deliverable-stat--online .p3-deliverable-stat__label,
        .p3-deliverable-stat--online .p3-deliverable-stat__value { color: #1d4ed8; }
        .p3-view-toggle {
            display: inline-flex;
            border: 1px solid #d4d4d8;
            border-radius: 10px;
            overflow: hidden;
            background: #fff;
        }
        .p3-view-toggle__btn {
            display: inline-flex;
            align-items: center;
            padding: 0.45rem 0.8rem;
            font-size: 0.82rem;
            font-weight: 700;
            text-decoration: none;
            color: #475569;
            border-right: 1px solid #e5e7eb;
        }
        .p3-view-toggle__btn:last-child { border-right: none; }
        .p3-view-toggle__btn.is-active {
            background: #18181b;
            color: #fff;
        }
        .p3-view-toggle__btn:hover:not(.is-active) {
            background: #f8fafc;
        }
        @media (max-width: 900px) {
            .p3-deliverable-highlight__grid { grid-template-columns: 1fr; }
        }
        .p3-staff-breakdown {
            margin-bottom: 1rem;
            background: #fff;
            border: 1px solid #c7d2fe;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 16px rgba(79, 70, 229, 0.08);
        }
        .p3-staff-breakdown__head {
            padding: 0.85rem 1rem;
            background: linear-gradient(135deg, #eef2ff 0%, #f8fafc 100%);
            border-bottom: 1px solid #e0e7ff;
        }
        .p3-staff-breakdown__title {
            margin: 0;
            font-size: 0.95rem;
            font-weight: 800;
            color: #312e81;
        }
        .p3-staff-breakdown__sub {
            margin: 0.2rem 0 0;
            font-size: 0.8rem;
            color: #64748b;
        }
        .p3-staff-breakdown__totals {
            display: flex;
            flex-wrap: wrap;
            gap: 0.55rem;
            margin-top: 0.65rem;
        }
        .p3-staff-breakdown__total-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.28rem 0.6rem;
            border-radius: 999px;
            font-size: 0.76rem;
            font-weight: 700;
        }
        .p3-staff-breakdown__total-pill--approved {
            background: #ecfdf5;
            border: 1px solid #bbf7d0;
            color: #166534;
        }
        .p3-staff-breakdown__total-pill--pending {
            background: #fff7ed;
            border: 1px solid #fed7aa;
            color: #9a3412;
        }
        .p3-staff-breakdown__total-pill--all {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            color: #334155;
        }
        .p3-staff-breakdown__table-wrap { overflow-x: auto; }
        .p3-staff-breakdown__table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.84rem;
            min-width: 520px;
        }
        .p3-staff-breakdown__table th {
            text-align: left;
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #64748b;
            background: #f8fafc;
            padding: 0.55rem 0.85rem;
            border-bottom: 1px solid #e2e8f0;
        }
        .p3-staff-breakdown__table td {
            padding: 0.55rem 0.85rem;
            border-bottom: 1px solid #f1f5f9;
        }
        .p3-staff-breakdown__table tr:last-child td { border-bottom: none; }
        .p3-staff-breakdown__num { font-weight: 800; text-align: right; white-space: nowrap; }
        .p3-staff-breakdown__num--approved { color: #166534; }
        .p3-staff-breakdown__num--pending { color: #9a3412; }
        .p3-doc-modal {
            position: fixed;
            inset: 0;
            display: none;
            align-items: center;
            justify-content: center;
            background: rgba(15, 23, 42, 0.55);
            z-index: 80;
            padding: 1rem;
        }
        .p3-doc-modal.is-open { display: flex; }
        .p3-doc-modal__card {
            width: min(980px, 96vw);
            max-height: 92vh;
            background: #fff;
            border-radius: 12px;
            border: 1px solid #e5e7eb;
            overflow: hidden;
            box-shadow: 0 20px 45px rgba(15, 23, 42, 0.28);
            display: flex;
            flex-direction: column;
        }
        .p3-doc-modal__head {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 0.8rem;
            padding: 0.7rem 0.9rem;
            border-bottom: 1px solid #e5e7eb;
        }
        .p3-doc-modal__title {
            font-size: 0.9rem;
            font-weight: 700;
            color: #0f172a;
        }
        .p3-doc-modal__ref { font-size: 0.8rem; color: #64748b; margin-top: 0.15rem; }
        .p3-doc-modal__close {
            border: 1px solid #cbd5e1;
            background: #f8fafc;
            color: #0f172a;
            border-radius: 8px;
            padding: 0.25rem 0.55rem;
            cursor: pointer;
            font-size: 0.8rem;
            font-weight: 700;
            flex-shrink: 0;
        }
        .p3-doc-modal__tabs {
            display: none;
            flex-wrap: wrap;
            gap: 0.4rem;
            padding: 0.65rem 0.9rem 0;
            background: #f8fafc;
            border-bottom: 1px solid #e5e7eb;
        }
        .p3-doc-modal__tabs.is-visible { display: flex; padding-bottom: 0.65rem; }
        .p3-doc-modal__tab {
            border: 1px solid #cbd5e1;
            background: #fff;
            color: #334155;
            border-radius: 999px;
            padding: 0.28rem 0.7rem;
            font-size: 0.76rem;
            font-weight: 700;
            cursor: pointer;
            max-width: 16rem;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .p3-doc-modal__tab.is-active {
            background: #18181b;
            border-color: #18181b;
            color: #fff;
        }
        .p3-doc-modal__body {
            padding: 0.8rem;
            overflow: auto;
            background: #f8fafc;
            min-height: 320px;
        }
        .p3-doc-modal__frame {
            width: 100%;
            min-height: 72vh;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            background: #fff;
        }
        .p3-doc-modal__img {
            max-width: 100%;
            max-height: 72vh;
            display: block;
            margin: 0 auto;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            background: #fff;
        }
        .p3-doc-modal__empty {
            margin: 0;
            color: #6b7280;
            font-size: 0.9rem;
        }
        .p3-doc-modal__fallback {
            font-size: 0.86rem;
            color: #334155;
        }
    </style>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:0.7rem;margin-bottom:1rem;">
        <div style="background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:0.75rem 0.85rem;">
            <div style="font-size:0.78rem;color:#6b7280;">Total cases</div>
            <div style="font-size:1.3rem;font-weight:800;color:#111827;">{{ number_format($summary['total']) }}</div>
        </div>
        <div style="background:#fff;border:1px solid #bbf7d0;border-radius:10px;padding:0.75rem 0.85rem;">
            <div style="font-size:0.78rem;color:#15803d;">Approved</div>
            <div style="font-size:1.3rem;font-weight:800;color:#166534;">{{ number_format($summary['approved']) }}</div>
        </div>
        <div style="background:#fff;border:1px solid #fde68a;border-radius:10px;padding:0.75rem 0.85rem;">
            <div style="font-size:0.78rem;color:#92400e;">Pending approval</div>
            <div style="font-size:1.3rem;font-weight:800;color:#9a3412;">{{ number_format($summary['pending_approval']) }}</div>
        </div>
        <div style="background:#fff;border:1px solid #fed7aa;border-radius:10px;padding:0.75rem 0.85rem;">
            <div style="font-size:0.78rem;color:#b45309;">Sent back</div>
            <div style="font-size:1.3rem;font-weight:800;color:#c2410c;">{{ number_format($summary['sent_back']) }}</div>
        </div>
        <div style="background:#fff;border:1px solid #fecaca;border-radius:10px;padding:0.75rem 0.85rem;">
            <div style="font-size:0.78rem;color:#b91c1c;">Rejected</div>
            <div style="font-size:1.3rem;font-weight:800;color:#b91c1c;">{{ number_format($summary['rejected']) }}</div>
        </div>
    </div>

    @if (! empty($givenByBreakdown) && $givenByStaff)
        <section class="p3-staff-breakdown" aria-label="Services given by staff breakdown">
            <div class="p3-staff-breakdown__head">
                <h2 class="p3-staff-breakdown__title">Services given by {{ $givenByStaff->name }}</h2>
                <p class="p3-staff-breakdown__sub">Breakdown by service type — approved and pending approval counts (other filters still apply).</p>
                <div class="p3-staff-breakdown__totals">
                    <span class="p3-staff-breakdown__total-pill p3-staff-breakdown__total-pill--all">
                        Total cases: {{ number_format((int) ($givenByBreakdown['totals']['total'] ?? 0)) }}
                    </span>
                    <span class="p3-staff-breakdown__total-pill p3-staff-breakdown__total-pill--approved">
                        Approved: {{ number_format((int) ($givenByBreakdown['totals']['approved'] ?? 0)) }}
                    </span>
                    <span class="p3-staff-breakdown__total-pill p3-staff-breakdown__total-pill--pending">
                        Pending: {{ number_format((int) ($givenByBreakdown['totals']['pending'] ?? 0)) }}
                    </span>
                </div>
            </div>
            <div class="p3-staff-breakdown__table-wrap">
                <table class="p3-staff-breakdown__table">
                    <thead>
                        <tr>
                            <th>Service</th>
                            <th style="text-align:right;">Approved</th>
                            <th style="text-align:right;">Pending</th>
                            <th style="text-align:right;">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($givenByBreakdown['rows'] ?? [] as $serviceRow)
                            <tr>
                                <td style="font-weight:600;color:#0f172a;">{{ $serviceRow['service_name'] }}</td>
                                <td class="p3-staff-breakdown__num p3-staff-breakdown__num--approved">{{ number_format((int) $serviceRow['approved']) }}</td>
                                <td class="p3-staff-breakdown__num p3-staff-breakdown__num--pending">{{ number_format((int) $serviceRow['pending']) }}</td>
                                <td class="p3-staff-breakdown__num">{{ number_format((int) $serviceRow['total']) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" style="padding:0.85rem;color:#64748b;text-align:center;">No service cases found for this staff member with current filters.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    @endif

    @if ($unifiedMarketLinkage)
        <section class="p3-deliverable-highlight" aria-label="Deliverables 6.3 incubatee counts">
            <div class="p3-deliverable-highlight__head">
                <div>
                    <h2 class="p3-deliverable-highlight__title">Deliverables 6.3 — unique incubatees (approved)</h2>
                    <p class="p3-deliverable-highlight__sub">
                        These three numbers match <strong>Admin → Deliverables</strong> row <em>Incubatees linked to online/offline Market</em>.
                        Unified count from market linkage and approved service cases — each incubatee once (market linkage takes precedence when both exist).
                        @if (($filters['date_from'] ?? '') !== '' && ($filters['date_to'] ?? '') !== '')
                            <br>Period filter active: {{ \Carbon\Carbon::parse($filters['date_from'])->format('d M Y') }} – {{ \Carbon\Carbon::parse($filters['date_to'])->format('d M Y') }} (same window as deliverables month filter).
                        @endif
                    </p>
                </div>
                <div class="p3-view-toggle" role="group" aria-label="List view">
                    <a
                        href="{{ route('admin.phase3-services.index', $uniqueViewQuery) }}"
                        class="p3-view-toggle__btn @if ($uniqueIncubateesView) is-active @endif"
                    >Unique incubatees only</a>
                    <a
                        href="{{ route('admin.phase3-services.index', $allRowsQuery) }}"
                        class="p3-view-toggle__btn @if (! $uniqueIncubateesView) is-active @endif"
                    >All partner rows</a>
                </div>
            </div>
            <div class="p3-deliverable-highlight__grid">
                <div class="p3-deliverable-stat p3-deliverable-stat--total">
                    <div class="p3-deliverable-stat__label">Deliverable incubatees (approved)</div>
                    <div class="p3-deliverable-stat__value">{{ number_format((int) ($summary['deliverable_incubatees'] ?? 0)) }}</div>
                </div>
                <div class="p3-deliverable-stat p3-deliverable-stat--offline">
                    <div class="p3-deliverable-stat__label">Offline incubatees</div>
                    <div class="p3-deliverable-stat__value">{{ number_format((int) ($summary['offline_incubatees'] ?? 0)) }}</div>
                </div>
                <div class="p3-deliverable-stat p3-deliverable-stat--online">
                    <div class="p3-deliverable-stat__label">Online incubatees</div>
                    <div class="p3-deliverable-stat__value">{{ number_format((int) ($summary['online_incubatees'] ?? 0)) }}</div>
                </div>
            </div>
        </section>
    @endif

    <div style="background:#fff;border:1px solid #e4e4e7;border-radius:10px;padding:0.75rem 0.85rem;margin-bottom:1rem;">
        <div style="font-weight:700;margin-bottom:0.5rem;">District-wise count</div>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:0.45rem;">
            @foreach ($districtCounts as $dc)
                @php
                    $isSelectedDistrict = (int) ($filters['district_id'] ?? 0) === (int) $dc['id'];
                    $pillStyle = $isSelectedDistrict
                        ? 'border:1px solid #93c5fd;background:#dbeafe;color:#1d4ed8;'
                        : ($dc['total'] > 0
                            ? 'border:1px solid #d1fae5;background:#ecfdf5;color:#065f46;'
                            : 'border:1px solid #e5e7eb;background:#f9fafb;color:#6b7280;');
                @endphp
                <a
                    href="{{ route('admin.phase3-services.index', array_merge(request()->query(), ['district_id' => $dc['id']])) }}"
                    style="display:flex;justify-content:space-between;gap:0.35rem;padding:0.38rem 0.45rem;border-radius:8px;text-decoration:none;{{ $pillStyle }}"
                >
                    <span style="font-size:0.82rem;font-weight:600;">{{ $dc['name'] }}</span>
                    <span style="font-size:0.82rem;font-weight:800;">{{ number_format($dc['total']) }}</span>
                </a>
            @endforeach
        </div>
    </div>

    <form id="phase3FilterForm" method="get" action="{{ route('admin.phase3-services.index') }}" style="background:#fff;border:1px solid #e4e4e7;border-radius:10px;padding:0.75rem 0.85rem;margin-bottom:1rem;">
        @if ($unifiedMarketLinkage && $uniqueIncubateesView)
            <input type="hidden" name="unique_incubatees" value="1">
        @endif
        <div style="display:flex;justify-content:space-between;align-items:center;gap:0.5rem;margin-bottom:0.6rem;flex-wrap:wrap;">
            <strong>Filters</strong>
            <div style="display:flex;gap:0.5rem;align-items:center;">
                <span style="font-size:0.8rem;color:#475569;">{{ $activeFilterCount }} active</span>
                <a
                    id="phase3ExportBtn"
                    href="{{ route('admin.phase3-services.export', request()->query()) }}"
                    data-export-base="{{ route('admin.phase3-services.export') }}"
                    style="text-decoration:none;background:#065f46;color:#fff;padding:0.38rem 0.7rem;border-radius:8px;font-size:0.82rem;font-weight:600;"
                >⬇ Export Excel</a>
            </div>
        </div>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:0.55rem;">
            <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="Applicant / app no / ref no" style="padding:0.45rem 0.55rem;border:1px solid #d4d4d8;border-radius:8px;">

            <select name="district_id" style="padding:0.45rem 0.55rem;border:1px solid #d4d4d8;border-radius:8px;">
                <option value="0">All districts</option>
                @foreach ($districts as $district)
                    <option value="{{ $district->id }}" @selected((int) $filters['district_id'] === (int) $district->id)>{{ $district->name }}</option>
                @endforeach
            </select>

            <select name="service_id" id="serviceFilter" style="padding:0.45rem 0.55rem;border:1px solid #d4d4d8;border-radius:8px;">
                <option value="0">All services</option>
                <option value="{{ \App\Support\ConvergenceReapSupport::MIS_8_2_LIST_FILTER }}" @selected(($filters['service_id'] ?? '') === \App\Support\ConvergenceReapSupport::MIS_8_2_LIST_FILTER)>{{ \App\Support\ConvergenceReapSupport::MIS_8_2_LIST_LABEL }}</option>
                @foreach ($services as $service)
                    <option value="{{ $service->id }}" data-category-id="{{ $service->service_category_id }}" @selected(is_numeric($filters['service_id'] ?? '') && (int) $filters['service_id'] === (int) $service->id)>{{ $service->name }}</option>
                @endforeach
            </select>

            <select name="spoc_id" style="padding:0.45rem 0.55rem;border:1px solid #d4d4d8;border-radius:8px;">
                <option value="">All SPOCs</option>
                <option value="unassigned" @selected($filters['spoc_id'] === 'unassigned')>Unassigned</option>
                @foreach ($spocs as $spoc)
                    <option value="{{ $spoc->id }}" @selected((string) $filters['spoc_id'] === (string) $spoc->id)>{{ $spoc->name }}</option>
                @endforeach
            </select>

            <select name="given_by_id" style="padding:0.45rem 0.55rem;border:1px solid #d4d4d8;border-radius:8px;">
                <option value="0">All staff (service given by)</option>
                @foreach ($districtStaff as $staff)
                    <option value="{{ $staff->id }}" @selected((int) ($filters['given_by_id'] ?? 0) === (int) $staff->id)>{{ $staff->name }}</option>
                @endforeach
            </select>

            <select name="status" style="padding:0.45rem 0.55rem;border:1px solid #d4d4d8;border-radius:8px;">
                <option value="">All statuses</option>
                @foreach ($statusLabel as $statusKey => $statusText)
                    <option value="{{ $statusKey }}" @selected($filters['status'] === $statusKey)>{{ $statusText }}</option>
                @endforeach
            </select>

            <select name="reporting_tier" style="padding:0.45rem 0.55rem;border:1px solid #d4d4d8;border-radius:8px;">
                <option value="">All reporting tiers</option>
                <option value="key" @selected($filters['reporting_tier'] === 'key')>Key</option>
                <option value="non_key" @selected($filters['reporting_tier'] === 'non_key')>Non-Key</option>
                <option value="unset" @selected($filters['reporting_tier'] === 'unset')>Unset</option>
            </select>

            <select name="has_docs" style="padding:0.45rem 0.55rem;border:1px solid #d4d4d8;border-radius:8px;">
                <option value="">All document states</option>
                <option value="1" @selected($filters['has_docs'] === '1')>With documents only</option>
                <option value="0" @selected($filters['has_docs'] === '0')>Without document entry</option>
            </select>

            <select name="month" id="monthFilter" style="padding:0.45rem 0.55rem;border:1px solid #d4d4d8;border-radius:8px;">
                <option value="">All months</option>
                @foreach (range(1, 12) as $m)
                    <option value="{{ $m }}" @selected((int) ($filters['month'] ?? 0) === $m)>{{ \Carbon\Carbon::create(null, $m, 1)->format('F') }}</option>
                @endforeach
            </select>

            <input type="date" name="date_from" id="dateFromFilter" value="{{ $filters['date_from'] }}" style="padding:0.45rem 0.55rem;border:1px solid #d4d4d8;border-radius:8px;" title="From date">
            <input type="date" name="date_to" id="dateToFilter" value="{{ $filters['date_to'] }}" style="padding:0.45rem 0.55rem;border:1px solid #d4d4d8;border-radius:8px;" title="To date">
        </div>
        <div style="display:flex;gap:0.5rem;align-items:center;margin-top:0.65rem;">
            <button type="submit" style="background:#18181b;color:#fff;border:none;padding:0.45rem 0.85rem;border-radius:8px;font-weight:600;cursor:pointer;">Filter</button>
            <a href="{{ route('admin.phase3-services.index') }}" style="text-decoration:none;border:1px solid #d4d4d8;padding:0.45rem 0.85rem;border-radius:8px;color:#111827;">Clear</a>
        </div>
    </form>

    <div class="p3-count-hint">
        Showing {{ number_format($cases->count()) }} of {{ number_format($cases->total()) }}
        @if ($unifiedMarketLinkage && $uniqueIncubateesView)
            unique incubatees
        @elseif ($unifiedMarketLinkage)
            market linkage rows
        @else
            cases
        @endif
        @if ($unifiedMarketLinkage)
            <span style="color:#64748b;">
                @if ($uniqueIncubateesView)
                    — one row per incubatee (matches deliverables 6.3 counts above).
                @else
                    — one row per partner; use <strong>Unique incubatees only</strong> to match deliverables counts.
                @endif
            </span>
        @endif
    </div>

    <div class="p3-table-card">
        <div class="p3-table-wrap">
        <table class="p3-table">
            <thead>
                <tr>
                    <th class="p3-sr">Sr.</th>
                    <th>Reference</th>
                    <th>Applicant</th>
                    <th>Applicant Category</th>
                    <th>SHG/CBO Member</th>
                    <th>District</th>
                    <th>Batch</th>
                    <th>Service</th>
                    <th>Linkage</th>
                    <th>Tier</th>
                    <th>Status</th>
                    <th>SPOC remark</th>
                    <th>Service Date</th>
                    <th>SLA</th>
                    <th>Submitted</th>
                    <th>Service given by</th>
                    <th>SPOC</th>
                    <th>Docs</th>
                    <th>CFA</th>
                    <th>Details</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($cases as $row)
                    @php
                        $isUnifiedRow = is_array($row);
                        $rowType = $isUnifiedRow ? (string) ($row['type'] ?? 'service_case') : 'service_case';
                        $case = $isUnifiedRow ? ($row['service_case'] ?? null) : $row;
                        $ml = $isUnifiedRow ? ($row['market_linkage'] ?? null) : null;
                        $linkageMode = '—';
                        if ($isUnifiedRow) {
                            $linkageMode = (string) ($row['linkage_mode'] ?? '—');
                        } elseif ($case instanceof \App\Models\ServiceCase) {
                            $payload = is_array($case->payload) ? $case->payload : [];
                            $modeLabels = \App\Support\MarketLinkageUnifiedListingSupport::linkageModeLabelsFromServiceCasePayload($payload);
                            $linkageMode = $modeLabels !== [] ? implode(', ', $modeLabels) : '—';
                        }
                        $legacyId = (int) ($case?->legacy_application_id ?? $ml?->legacy_application_id ?? 0);
                        $lp = $legacyId > 0 ? ($legacyPreviews[$legacyId] ?? null) : null;
                        $applicantPayload = is_array($case?->cfaSubmission?->payload)
                            ? $case->cfaSubmission->payload
                            : (is_array($ml?->cfaSubmission?->payload) ? $ml->cfaSubmission->payload : []);
                        $applicantCategory = \App\Support\ApplicantCategoryShgSupport::categoryLabel(
                            $applicantPayload,
                            is_array($lp) ? ($lp['applicant_category'] ?? null) : null,
                        );
                        $shgMember = \App\Support\ApplicantCategoryShgSupport::shgMemberLabel(
                            $applicantPayload,
                            is_array($lp) ? ($lp['is_shg_member'] ?? null) : null,
                        );
                        $batchName = $case?->cfaSubmission?->onboardingBatchMembership?->batch?->name
                            ?? (is_array($lp) ? ($lp['onboarding_batch_name'] ?? '') : '');
                        $isLegacyBatch = $batchName !== '' && $case && ! $case->cfaSubmission;
                        $attachments = $case
                            ? $case->attachments->map(fn ($a) => [
                                'id' => (int) $a->id,
                                'name' => (string) ($a->original_name ?: 'Attachment'),
                                'size' => (int) ($a->size_bytes ?? 0),
                                'mime' => (string) ($a->mime_type ?? ''),
                                'url' => route('admin.phase3-services.attachments.view', ['service_case' => $case->id, 'attachment' => $a->id]),
                            ])->values()->all()
                            : [];
                        $rowStatus = in_array($rowType, ['market_linkage_partner', 'market_linkage_incubatee'], true)
                            ? (string) ($ml?->status ?? '')
                            : (string) ($case?->status ?? '');
                        $isSlaBreached = $case
                            && $case->sla_deadline_at
                            && \Illuminate\Support\Carbon::parse($case->sla_deadline_at)->isPast()
                            && ! in_array($case->status, ['approved', 'rejected', 'cancelled'], true);
                        $serviceCodeLower = strtolower((string) ($case?->service?->code ?? ''));
                        $serviceNameLower = strtolower((string) ($case?->service?->name ?? ''));
                        $isUdyamService = $case && (
                            $serviceCodeLower === 'udyam_registration'
                            || str_contains($serviceCodeLower, 'udyam')
                            || str_contains($serviceNameLower, 'udyam')
                        );
                        $udyamTypeLabel = match ((string) ($case?->udyam_registration_type ?? '')) {
                            'existing' => 'Existing',
                            'new' => 'New',
                            default => '',
                        };
                        $udyamTypeDisplay = $udyamTypeLabel !== ''
                            ? $udyamTypeLabel
                            : (($isUdyamService && $case?->status === 'pending_approval')
                                ? 'Awaiting SPOC selection'
                                : '');
                        $srNo = $loop->iteration + (($cases->currentPage() - 1) * $cases->perPage());
                        $applicantName = $case?->cfaSubmission?->applicant_name
                            ?? ($ml?->incubatee_name ?? null)
                            ?? ($lp['applicant_name'] ?? null)
                            ?: '—';
                        $applicationNo = $case?->cfaSubmission?->application_no
                            ?? ($ml?->application_no ?? null)
                            ?? ($lp['application_no'] ?? null)
                            ?: '—';
                        $districtName = $case?->cfaSubmission?->district?->name
                            ?? ($ml?->district_name ?? null)
                            ?? ($lp['district'] ?? null)
                            ?: '—';
                        $referenceNumber = $case?->reference_number
                            ?: ($rowType === 'market_linkage_incubatee'
                                ? ($ml?->application_no ?: '—')
                                : ($isUnifiedRow ? (string) ($row['partner_name'] ?? '—') : '—'));
                        $serviceLabel = in_array($rowType, ['market_linkage_partner', 'market_linkage_incubatee'], true)
                            ? \App\Models\MarketLinkageSubmission::SERVICE_LIST_LABEL
                            : ($case?->service?->name ?? '—');
                        $serviceSubLabel = $rowType === 'market_linkage_incubatee'
                            ? (string) ($row['partner_name'] ?? '—')
                            : ($rowType === 'market_linkage_partner'
                                ? (string) ($row['partner_name'] ?? '—')
                                : ($case?->service?->category?->name ?? '—'));
                        $submittedAt = $case?->submitted_at ?? $ml?->submitted_at;
                        $assignedBy = $case?->submitter?->name ?? $case?->creator?->name ?? $ml?->submitted_by_name ?? $ml?->submitter?->name ?? '—';
                        $spocName = $case?->spoc?->name ?? $ml?->spoc?->name ?? 'Unassigned';
                        $spocRemark = match ($rowStatus) {
                            'sent_back' => $case?->sent_back_note ?? $ml?->sent_back_note,
                            'rejected' => $case?->rejected_note ?? $ml?->rejected_note,
                            default => null,
                        };
                        $serviceDate = $case?->serviceDateForReporting();
                        $slaDeadline = $case?->sla_deadline_at ?? $ml?->sla_deadline_at;
                        $detailsUrl = $case
                            ? route('admin.phase3-services.show', $case)
                            : ($ml ? route('admin.market-linkages.show', $ml) : '#');
                    @endphp
                    <tr class="p3-row--{{ $rowStatus ?: 'draft' }}">
                        <td class="p3-sr">{{ $srNo }}</td>
                        <td><span style="font-weight:600;">{{ $referenceNumber ?: '—' }}</span></td>
                        <td>
                            <div class="p3-name">{{ $applicantName }}</div>
                            <div class="p3-sub">{{ $applicationNo }}</div>
                        </td>
                        <td>{{ $applicantCategory !== '' ? $applicantCategory : '—' }}</td>
                        <td>{{ $shgMember !== '' ? $shgMember : '—' }}</td>
                        <td>{{ $districtName }}</td>
                        <td>
                            @if ($batchName !== '')
                                <span class="p3-pill p3-pill--batch">{{ $batchName }}</span>
                                @if ($isLegacyBatch)
                                    <span class="p3-pill p3-pill--legacy">legacy</span>
                                @endif
                            @else
                                <span style="color:#94a3b8;">—</span>
                            @endif
                        </td>
                        <td>
                            @if ($case && ! $unifiedMarketLinkage)
                                @include('staff.services.partials.reap-listing-service-cell', ['case' => $case])
                            @else
                                <strong>{{ $serviceLabel }}</strong>
                            @endif
                            <div class="p3-sub">{{ $serviceSubLabel }}</div>
                            @if ($isUdyamService && $udyamTypeDisplay !== '')
                                <div style="margin-top:0.26rem;">
                                    <span class="p3-pill" style="background:#fffbeb;border:1px solid #fcd34d;color:#92400e;">
                                        Udyam: {{ $udyamTypeDisplay }}
                                    </span>
                                </div>
                            @endif
                        </td>
                        <td>
                            @if ($linkageMode === '—')
                                <span style="color:#94a3b8;">—</span>
                            @else
                                @foreach (array_filter(array_map('trim', explode(',', $linkageMode))) as $modePart)
                                    <span class="p3-pill {{ str_contains(strtolower($modePart), 'offline') ? 'p3-pill--mode-offline' : 'p3-pill--mode-online' }}" style="margin-right:0.2rem;">
                                        {{ $modePart }}
                                    </span>
                                @endforeach
                            @endif
                        </td>
                        <td>
                            @if ($case?->service?->reporting_tier)
                                <span class="p3-pill" style="background:#eef2ff;border:1px solid #c7d2fe;color:#3730a3;">
                                    {{ strtoupper((string) $case->service->reporting_tier) }}
                                </span>
                            @else
                                <span style="color:#94a3b8;">—</span>
                            @endif
                        </td>
                        <td>
                            <span class="p3-pill" style="{{ $statusStyle[$rowStatus] ?? $statusStyle['draft'] }}">
                                {{ $statusLabel[$rowStatus] ?? ucfirst(str_replace('_', ' ', $rowStatus)) }}
                            </span>
                        </td>
                        <td class="p3-remark">{{ $spocRemark ?: '—' }}</td>
                        <td style="color:#475569;white-space:nowrap;">
                            {{ $serviceDate?->format('d M Y') ?? '—' }}
                        </td>
                        <td style="{{ $isSlaBreached ? 'color:#b91c1c;font-weight:700;' : 'color:#475569;' }}">
                            {{ $slaDeadline ? \Illuminate\Support\Carbon::parse($slaDeadline)->format('d M Y') : '—' }}
                            @if ($isSlaBreached)
                                <div class="p3-sub" style="color:#b91c1c;">Breached</div>
                            @endif
                        </td>
                        <td style="color:#475569;white-space:nowrap;">
                            {{ $submittedAt ? \Illuminate\Support\Carbon::parse($submittedAt)->format('d M Y, h:i A') : '—' }}
                        </td>
                        <td>{{ $assignedBy }}</td>
                        <td>{{ $spocName }}</td>
                        <td style="white-space:nowrap;">
                            @if ($case)
                                <strong>{{ count($attachments) }}</strong>
                                <button
                                    type="button"
                                    class="js-documents-open p3-btn"
                                    data-case-label="{{ $case->service?->name ?? 'Service case' }}"
                                    data-case-ref="{{ $case->reference_number ?: '—' }}"
                                    data-documents='@json($attachments)'
                                >View</button>
                            @else
                                <span style="color:#94a3b8;">—</span>
                            @endif
                        </td>
                        <td>
                            @if ($case?->cfaSubmission)
                                <a href="{{ route('admin.cfa.show', $case->cfaSubmission) }}" class="p3-link">View CFA</a>
                            @elseif ($ml?->cfaSubmission)
                                <a href="{{ route('admin.cfa.show', $ml->cfaSubmission) }}" class="p3-link">View CFA</a>
                            @else
                                —
                            @endif
                        </td>
                        <td>
                            @if ($detailsUrl !== '#')
                                <a href="{{ $detailsUrl }}" class="p3-link" target="_blank" rel="noopener">View details</a>
                            @else
                                —
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="20" style="padding:1.2rem;color:#64748b;text-align:center;">No Phase 3 service cases found for selected filters.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        </div>
    </div>

    @if ($cases->hasPages())
        <div style="margin-top:1rem;">{{ $cases->links() }}</div>
    @endif

    <div id="docModal" class="p3-doc-modal" aria-hidden="true">
        <div class="p3-doc-modal__card" role="dialog" aria-modal="true" aria-label="Document preview">
            <div class="p3-doc-modal__head">
                <div>
                    <div class="p3-doc-modal__title" id="docModalTitle">Documents</div>
                    <div class="p3-doc-modal__ref" id="docModalRef"></div>
                </div>
                <button type="button" id="docModalClose" class="p3-doc-modal__close">Close</button>
            </div>
            <div id="docModalTabs" class="p3-doc-modal__tabs" aria-label="Choose document"></div>
            <div id="docModalBody" class="p3-doc-modal__body"></div>
        </div>
    </div>

    <script>
        (function () {
            const filterForm = document.getElementById('phase3FilterForm');
            const serviceFilter = document.getElementById('serviceFilter');
            const monthEl = document.getElementById('monthFilter');
            const dateFromEl = document.getElementById('dateFromFilter');
            const dateToEl = document.getElementById('dateToFilter');
            let autoSubmitTimer = null;
            let syncingPresetDates = false;

            const fiscalStartYear = @json((int) ($fiscalYear?->starts_on?->year ?? now()->year));
            const fiscalStartMonth = @json((int) ($fiscalYear?->starts_on?->month ?? 4));

            function pad2(n) {
                return String(n).padStart(2, '0');
            }

            function calendarYearForMonth(month) {
                return month >= fiscalStartMonth ? fiscalStartYear : fiscalStartYear + 1;
            }

            function setDateRange(fromIso, toIso) {
                if (!dateFromEl || !dateToEl) return;
                syncingPresetDates = true;
                dateFromEl.value = fromIso || '';
                dateToEl.value = toIso || '';
                syncingPresetDates = false;
            }

            function syncDatesFromMonth() {
                if (!monthEl) return;
                const month = parseInt(monthEl.value, 10);
                if (!month || month < 1 || month > 12) {
                    setDateRange('', '');
                    return;
                }
                const year = calendarYearForMonth(month);
                const lastDay = new Date(year, month, 0).getDate();
                setDateRange(
                    year + '-' + pad2(month) + '-01',
                    year + '-' + pad2(month) + '-' + pad2(lastDay)
                );
            }

            function onManualDateChange() {
                if (syncingPresetDates || !monthEl) return;
                monthEl.value = '';
            }

            function queueSubmit(delayMs) {
                if (!filterForm) return;
                if (autoSubmitTimer) {
                    clearTimeout(autoSubmitTimer);
                }
                autoSubmitTimer = setTimeout(function () {
                    const pageInput = filterForm.querySelector('input[name="page"]');
                    if (pageInput) {
                        pageInput.remove();
                    }
                    filterForm.submit();
                }, delayMs);
            }

            if (monthEl) {
                monthEl.addEventListener('change', syncDatesFromMonth);
            }
            if (dateFromEl) {
                dateFromEl.addEventListener('change', onManualDateChange);
            }
            if (dateToEl) {
                dateToEl.addEventListener('change', onManualDateChange);
            }

            if (filterForm) {
                filterForm.querySelectorAll('select,input[type="date"]').forEach(function (el) {
                    el.addEventListener('change', function () {
                        queueSubmit(120);
                    });
                });

                const searchInput = filterForm.querySelector('input[name="q"]');
                if (searchInput) {
                    searchInput.addEventListener('input', function () {
                        queueSubmit(450);
                    });
                }

                const exportBtn = document.getElementById('phase3ExportBtn');
                if (exportBtn) {
                    exportBtn.addEventListener('click', function (event) {
                        event.preventDefault();
                        const base = exportBtn.getAttribute('data-export-base') || exportBtn.href;
                        const params = new URLSearchParams(new FormData(filterForm));
                        params.delete('page');
                        const query = params.toString();
                        window.location.href = query ? (base + '?' + query) : base;
                    });
                }
            }

            const modal = document.getElementById('docModal');
            const modalTitle = document.getElementById('docModalTitle');
            const modalRef = document.getElementById('docModalRef');
            const modalTabs = document.getElementById('docModalTabs');
            const modalBody = document.getElementById('docModalBody');
            const closeBtn = document.getElementById('docModalClose');

            function escapeHtml(value) {
                return String(value || '')
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#39;');
            }

            function closeModal() {
                if (!modal) return;
                modal.classList.remove('is-open');
                modal.setAttribute('aria-hidden', 'true');
                modalBody.innerHTML = '';
                modalTabs.innerHTML = '';
                modalTabs.classList.remove('is-visible');
                document.body.style.overflow = '';
            }

            function openModalShell() {
                modal.classList.add('is-open');
                modal.setAttribute('aria-hidden', 'false');
                document.body.style.overflow = 'hidden';
            }

            function isPdf(doc) {
                const mime = String(doc.mime || '').toLowerCase();
                const name = String(doc.name || doc.url || '').toLowerCase();
                return mime === 'application/pdf' || name.endsWith('.pdf');
            }

            function isImage(doc) {
                const mime = String(doc.mime || '').toLowerCase();
                const name = String(doc.name || doc.url || '').toLowerCase();
                return mime.indexOf('image/') === 0 || /\.(png|jpg|jpeg|webp|gif)$/i.test(name);
            }

            function renderPreview(doc) {
                modalBody.innerHTML = '';
                if (!doc || !doc.url) {
                    modalBody.innerHTML = '<p class="p3-doc-modal__empty">No document to preview.</p>';
                    return;
                }

                if (isPdf(doc)) {
                    const frame = document.createElement('iframe');
                    frame.className = 'p3-doc-modal__frame';
                    frame.src = doc.url;
                    frame.title = doc.name || 'Document';
                    modalBody.appendChild(frame);
                    return;
                }

                if (isImage(doc)) {
                    const img = document.createElement('img');
                    img.className = 'p3-doc-modal__img';
                    img.alt = doc.name || 'Document image';
                    img.src = doc.url;
                    modalBody.appendChild(img);
                    return;
                }

                const fallback = document.createElement('div');
                fallback.className = 'p3-doc-modal__fallback';
                fallback.innerHTML = 'Preview not supported for this file type. '
                    + '<a href="' + escapeHtml(doc.url) + '" target="_blank" rel="noopener">Open document</a>.';
                modalBody.appendChild(fallback);
            }

            function setActiveTab(tabs, activeIndex) {
                Array.prototype.forEach.call(tabs, function (tab, idx) {
                    tab.classList.toggle('is-active', idx === activeIndex);
                });
            }

            function showDocuments(docs, caseLabel, caseRef) {
                modalTitle.textContent = 'Documents — ' + (caseLabel || 'Service case');
                modalRef.textContent = 'Case Ref: ' + (caseRef || '—');
                modalTabs.innerHTML = '';
                modalTabs.classList.remove('is-visible');

                if (!docs.length) {
                    modalBody.innerHTML = '<p class="p3-doc-modal__empty">No documents uploaded for this case.</p>';
                    openModalShell();
                    return;
                }

                if (docs.length > 1) {
                    modalTabs.classList.add('is-visible');
                    docs.forEach(function (doc, idx) {
                        const tab = document.createElement('button');
                        tab.type = 'button';
                        tab.className = 'p3-doc-modal__tab' + (idx === 0 ? ' is-active' : '');
                        tab.textContent = (idx + 1) + '. ' + (doc.name || ('Document ' + (idx + 1)));
                        tab.title = doc.name || ('Document ' + (idx + 1));
                        tab.addEventListener('click', function () {
                            setActiveTab(modalTabs.querySelectorAll('.p3-doc-modal__tab'), idx);
                            renderPreview(doc);
                        });
                        modalTabs.appendChild(tab);
                    });
                }

                renderPreview(docs[0]);
                openModalShell();
            }

            document.querySelectorAll('.js-documents-open').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    let docs = [];
                    try {
                        docs = JSON.parse(btn.dataset.documents || '[]');
                    } catch (e) {
                        docs = [];
                    }
                    if (!Array.isArray(docs)) {
                        docs = [];
                    }
                    showDocuments(docs, btn.dataset.caseLabel || 'Service case', btn.dataset.caseRef || '—');
                });
            });

            closeBtn && closeBtn.addEventListener('click', closeModal);
            modal && modal.addEventListener('click', function (event) {
                if (event.target === modal) closeModal();
            });
            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape' && modal && modal.classList.contains('is-open')) {
                    closeModal();
                }
            });
        })();
    </script>
@endsection
