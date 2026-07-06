@extends('layouts.admin')

@section('title', \App\Models\AccelerationServiceSession::MODULE_LABEL)
@section('heading', \App\Models\AccelerationServiceSession::MODULE_LABEL)

@push('styles')
<style>
    .accel-shell { display:flex; flex-direction:column; gap:1.25rem; }
    .accel-alert { border-radius:12px; padding:0.85rem 1rem; font-size:0.88rem; }
    .accel-alert--warning { background:#fffbeb; border:1px solid #fde68a; color:#92400e; }
    .accel-alert--success { background:#f0fdf4; border:1px solid #86efac; color:#166534; }
    .accel-alert--error { background:#fef2f2; border:1px solid #fca5a5; color:#991b1b; }
    .accel-alert--info { background:#eff6ff; border:1px solid #bfdbfe; color:#1e40af; }
    .accel-stats { display:grid; grid-template-columns:repeat(auto-fit, minmax(150px, 1fr)); gap:0.65rem; }
    .accel-stat { background:#fff; border:1px solid #e2e8f0; border-radius:14px; padding:0.85rem 1rem; }
    .accel-stat__label { font-size:0.72rem; font-weight:700; text-transform:uppercase; letter-spacing:0.05em; color:#64748b; }
    .accel-stat__value { margin-top:0.3rem; font-size:1.45rem; font-weight:800; color:#0f172a; }
    .accel-card { background:#fff; border:1px solid #e2e8f0; border-radius:14px; padding:1.25rem 1.35rem; }
    .accel-grid { display:grid; grid-template-columns:minmax(0,1fr); gap:1rem; align-items:start; }
    .accel-badge { display:inline-block; font-size:0.68rem; font-weight:700; padding:0.12rem 0.4rem; border-radius:999px; }
    .accel-badge--init { background:#dcfce7; color:#166534; }
    .accel-badge--follow { background:#f1f5f9; color:#475569; }
    .accel-field label { display:block; font-size:0.82rem; font-weight:700; margin-bottom:0.35rem; color:#0f172a; }
    .accel-field input, .accel-field select, .accel-field textarea { width:100%; box-sizing:border-box; border:1px solid #cbd5e1; border-radius:8px; padding:0.55rem 0.65rem; font-size:0.88rem; }
    .accel-section-title { margin:1rem 0 0.5rem; font-size:0.72rem; font-weight:700; letter-spacing:0.08em; text-transform:uppercase; color:#64748b; }
    .accel-item { border:1px solid #e2e8f0; border-radius:10px; padding:0.65rem 0.75rem; margin-bottom:0.5rem; background:#f8fafc; }
    .accel-item__head { display:flex; gap:0.5rem; align-items:flex-start; }
    .accel-item__extra { margin-top:0.55rem; display:none; }
    .accel-item.is-checked .accel-item__extra { display:block; }
    .accel-media-preview { display:flex; flex-wrap:wrap; gap:0.45rem; margin-top:0.45rem; }
    .accel-media-preview img { width:72px; height:72px; object-fit:cover; border-radius:8px; border:1px solid #e2e8f0; }
    .accel-media-chip { font-size:0.75rem; background:#fff; border:1px solid #e2e8f0; border-radius:6px; padding:0.25rem 0.45rem; }
    .accel-search-results { max-height:220px; overflow:auto; border:1px solid #e2e8f0; border-radius:8px; background:#fff; }
    .accel-search-item { padding:0.55rem 0.65rem; border-bottom:1px solid #f1f5f9; cursor:pointer; font-size:0.82rem; }
    .accel-search-item:hover { background:#eef2ff; }
    .accel-search-item.is-active { background:#e0e7ff; border-left:3px solid #4f46e5; }
    .accel-search-item.is-selected { background:#ecfdf5; border-left:3px solid #059669; }
    .accel-search-item__name { font-weight:700; color:#0f172a; }
    .accel-search-item__meta { margin-top:0.15rem; font-size:0.76rem; color:#64748b; }
    .accel-search-item__actions { display:flex; gap:0.35rem; margin-top:0.4rem; }
    .accel-picker { border:1px solid #e2e8f0; border-radius:12px; background:#f8fafc; padding:0.75rem; margin-top:0.45rem; }
    .accel-picker__grid { display:grid; grid-template-columns:minmax(0,1.1fr) minmax(0,0.9fr); gap:0.75rem; min-height:280px; }
    .accel-picker__list { max-height:320px; overflow:auto; background:#fff; border:1px solid #e2e8f0; border-radius:10px; }
    .accel-picker__detail { background:#fff; border:1px solid #c7d2fe; border-radius:10px; padding:0.75rem 0.85rem; min-height:280px; }
    .accel-picker__detail-title { margin:0 0 0.5rem; font-size:0.88rem; font-weight:700; color:#312e81; }
    .accel-picker__detail-empty { margin:0; font-size:0.8rem; color:#64748b; }
    .accel-picker__meta { display:grid; gap:0.35rem; font-size:0.8rem; margin-bottom:0.75rem; }
    .accel-picker__meta dt { font-weight:700; color:#64748b; font-size:0.7rem; text-transform:uppercase; }
    .accel-picker__meta dd { margin:0; color:#0f172a; }
    .accel-picker__history-title { margin:0.75rem 0 0.45rem; font-size:0.72rem; font-weight:700; letter-spacing:0.06em; text-transform:uppercase; color:#64748b; }
    .accel-picker__history { max-height:260px; overflow:auto; }
    .accel-side-entry { background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:0.5rem 0.55rem; margin-bottom:0.45rem; font-size:0.78rem; }
    .accel-side-entry__date { font-weight:700; color:#0f172a; }
    .accel-side-entry__meta { color:#64748b; margin-top:0.2rem; }
    .accel-btn--xs { padding:0.28rem 0.55rem; font-size:0.74rem; border-radius:6px; }
    .accel-btn--ghost { background:#fff; color:#334155; border:1px solid #cbd5e1; }
    .accel-btn--add { background:#059669; }
    .accel-selected { margin-top:0.55rem; padding:0.55rem 0.65rem; background:#ecfdf5; border:1px solid #86efac; border-radius:8px; font-size:0.82rem; }
    .accel-btn { border:none; border-radius:8px; background:#0f766e; color:#fff; padding:0.62rem 1rem; font-weight:700; cursor:pointer; font-size:0.88rem; text-decoration:none; display:inline-flex; align-items:center; }
    .accel-btn--secondary { background:#fff; color:#334155; border:1px solid #cbd5e1; }
    .accel-table-wrap { overflow:auto; }
    .accel-table { width:100%; border-collapse:collapse; font-size:0.84rem; }
    .accel-table th, .accel-table td { text-align:left; padding:0.7rem 0.75rem; border-bottom:1px solid #e2e8f0; vertical-align:top; }
    .accel-table thead tr { background:#f8fafc; }
    .accel-link { color:#0f766e; font-weight:700; text-decoration:none; }
    .accel-custom-row { display:flex; gap:0.45rem; margin-top:0.35rem; }
    .accel-custom-row input { flex:1; }
    @media (max-width: 960px) { .accel-picker__grid { grid-template-columns:1fr; } }
</style>
@endpush

@section('content')
@php
    $filterQuery = array_filter($filters ?? [], fn ($v) => $v !== null && $v !== '');
@endphp
<div class="accel-shell">
    @if (!empty($migrationMissing))
        <div class="accel-alert accel-alert--warning"><strong>Database update required.</strong> Run <code>php artisan migrate</code>.</div>
    @endif

    @if (session('status'))
        <div class="accel-alert accel-alert--success">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="accel-alert accel-alert--error">
            <strong>Please fix:</strong>
            <ul style="margin:0.35rem 0 0 1rem;">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="accel-alert accel-alert--info">
        MIS <strong>7.2</strong> — Initiation of acceleration &amp; co-incubation services.
        Counts <strong>unique Phase 1 incubatees per FY</strong> on first initiation; follow-up visits add services without re-counting 7.2.
    </div>

    <div class="accel-stats">
        <div class="accel-stat">
            <div class="accel-stat__label">7.2 Unique initiations (FY)</div>
            <div class="accel-stat__value">{{ number_format((int) ($totals['initiations_fy'] ?? 0)) }}</div>
        </div>
        <div class="accel-stat">
            <div class="accel-stat__label">Sessions logged</div>
            <div class="accel-stat__value">{{ number_format((int) ($totals['sessions'] ?? 0)) }}</div>
        </div>
        <div class="accel-stat">
            <div class="accel-stat__label">Buyer Seller ticks (FY)</div>
            <div class="accel-stat__value">{{ number_format((int) ($totals['buyer_seller_ticks'] ?? 0)) }}</div>
        </div>
    </div>

    @if (!empty($canSubmit) && empty($migrationMissing))
        @include('acceleration-services.partials.form')
    @endif

    <div class="accel-card">
        <div style="display:flex; flex-wrap:wrap; justify-content:space-between; gap:0.65rem; margin-bottom:1rem;">
            <h3 style="margin:0; font-size:1rem; font-weight:700;">
                @if (!empty($isAdminView)) All acceleration sessions (state) @else Your acceleration sessions @endif
            </h3>
            <a href="{{ route($exportRoute, $filterQuery) }}" class="accel-btn accel-btn--secondary">Export CSV</a>
        </div>

        <form method="get" action="{{ route($dashboardRoute) }}" style="display:grid; grid-template-columns:repeat(auto-fit,minmax(150px,1fr)); gap:0.65rem; margin-bottom:1rem;">
            <div class="accel-field" style="margin:0;">
                <label for="filter_q">Search</label>
                <input type="text" id="filter_q" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Name, app no, phone">
            </div>
            <div class="accel-field" style="margin:0;">
                <label for="filter_from">From</label>
                <input type="date" id="filter_from" name="from" value="{{ $filters['from'] ?? '' }}">
            </div>
            <div class="accel-field" style="margin:0;">
                <label for="filter_to">To</label>
                <input type="date" id="filter_to" name="to" value="{{ $filters['to'] ?? '' }}">
            </div>
            <div style="display:flex; align-items:flex-end;">
                <button type="submit" class="accel-btn">Filter</button>
            </div>
        </form>

        <div class="accel-table-wrap">
            <table class="accel-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Applicant</th>
                        <th>App no</th>
                        <th>District</th>
                        <th>Services</th>
                        <th>7.2</th>
                        <th>By</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td>{{ $row->service_date?->format('d M Y') }}</td>
                            <td>{{ $row->applicant_name }}</td>
                            <td>{{ $row->application_no ?: '—' }}</td>
                            <td>{{ $row->district_name ?: '—' }}</td>
                            <td>{{ (int) ($row->items_count ?? 0) }} item(s)</td>
                            <td>
                                @if ($row->counts_for_7_2)
                                    <span class="accel-badge accel-badge--init">Initiation</span>
                                @else
                                    <span class="accel-badge accel-badge--follow">Follow-up</span>
                                @endif
                            </td>
                            <td>{{ $row->submitted_by_name }}</td>
                            <td style="white-space:nowrap;">
                                <a class="accel-link" href="{{ route($showRoute, $row) }}">View</a>
                                @if (!empty($canSubmit))
                                    · <a class="accel-link" href="{{ route('spoc.acceleration-services.dashboard', ['from_session' => $row->id]) }}#accel-form">Add services</a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" style="color:#64748b;">No sessions yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if (method_exists($rows, 'links'))
            <div style="margin-top:0.85rem;">{{ $rows->links() }}</div>
        @endif
    </div>
</div>
@endsection
