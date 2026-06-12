@extends('layouts.admin')

@section('title', \App\Models\BusinessAccelerationPartnerOutreachEntry::MODULE_LABEL)
@section('heading', \App\Models\BusinessAccelerationPartnerOutreachEntry::MODULE_LABEL)

@push('styles')
<style>
    .bapo-shell { display:flex; flex-direction:column; gap:1.25rem; }
    .bapo-alert { border-radius:12px; padding:0.85rem 1rem; font-size:0.88rem; }
    .bapo-alert--warning { background:#fffbeb; border:1px solid #fde68a; color:#92400e; }
    .bapo-alert--success { background:#f0fdf4; border:1px solid #86efac; color:#166534; }
    .bapo-stats { display:grid; grid-template-columns:repeat(auto-fit, minmax(140px, 1fr)); gap:0.65rem; }
    .bapo-stat { background:#fff; border:1px solid #e2e8f0; border-radius:14px; padding:0.85rem 1rem; }
    .bapo-stat__label { font-size:0.72rem; font-weight:700; text-transform:uppercase; letter-spacing:0.05em; color:#64748b; }
    .bapo-stat__value { margin-top:0.3rem; font-size:1.45rem; font-weight:800; color:#0f172a; }
    .bapo-stat__hint { margin-top:0.2rem; font-size:0.72rem; color:#64748b; }
    .bapo-card { background:#fff; border:1px solid #e2e8f0; border-radius:14px; padding:1.25rem 1.35rem; }
    .bapo-head { display:flex; flex-wrap:wrap; justify-content:space-between; align-items:flex-start; gap:0.75rem; margin-bottom:1rem; }
    .bapo-head__title { margin:0; font-size:1rem; font-weight:700; color:#0f172a; }
    .bapo-filters { display:grid; grid-template-columns:repeat(auto-fit, minmax(150px, 1fr)); gap:0.85rem; align-items:end; margin-bottom:1rem; }
    .bapo-filter-field label { font-size:0.78rem; font-weight:700; color:#0f172a; display:block; margin-bottom:0.35rem; }
    .bapo-filter-field input, .bapo-filter-field select { width:100%; border:1px solid #cbd5e1; border-radius:8px; padding:0.55rem 0.65rem; font-size:0.88rem; box-sizing:border-box; }
    .bapo-btn { border:none; border-radius:8px; background:#0f766e; color:#fff; padding:0.58rem 0.9rem; font-weight:700; cursor:pointer; font-size:0.88rem; text-decoration:none; display:inline-flex; align-items:center; }
    .bapo-btn--secondary { background:#fff; color:#334155; border:1px solid #cbd5e1; }
    .bapo-btn--export { background:#065f46; }
    .bapo-table-wrap { background:#fff; border:1px solid #e2e8f0; border-radius:14px; overflow:auto; }
    .bapo-table { width:100%; border-collapse:collapse; font-size:0.84rem; }
    .bapo-table th, .bapo-table td { text-align:left; padding:0.7rem 0.75rem; border-bottom:1px solid #e2e8f0; vertical-align:top; }
    .bapo-table thead tr { background:#f8fafc; }
    .bapo-link { color:#0f766e; font-weight:700; text-decoration:none; }
    .bapo-empty { padding:1rem; color:#64748b; }
</style>
@endpush

@section('content')
@php
    $filterQuery = array_filter([
        'q' => $filters['q'] ?? '',
        'from' => $filters['from'] ?? '',
        'to' => $filters['to'] ?? '',
        'partner_type' => $filters['partner_type'] ?? '',
    ], fn ($v) => $v !== null && $v !== '');
@endphp
<div class="bapo-shell">
    @if (!empty($migrationMissing))
        <div class="bapo-alert bapo-alert--warning">
            <strong>Database update required.</strong> Run <code>php artisan migrate</code>.
        </div>
    @endif

    @if (session('status'))
        <div class="bapo-alert bapo-alert--success">{{ session('status') }}</div>
    @endif

    <div class="bapo-stats">
        <div class="bapo-stat">
            <div class="bapo-stat__label">7.1 Unique partners (FY)</div>
            <div class="bapo-stat__value">{{ number_format((int) ($deliverableStats['unique_partners_fy'] ?? 0)) }}</div>
            <div class="bapo-stat__hint">Deliverable achievement</div>
        </div>
        <div class="bapo-stat">
            <div class="bapo-stat__label">Filtered entries</div>
            <div class="bapo-stat__value">{{ number_format((int) ($totals['entries'] ?? 0)) }}</div>
        </div>
        <div class="bapo-stat">
            <div class="bapo-stat__label">Submissions</div>
            <div class="bapo-stat__value">{{ number_format((int) ($totals['batches'] ?? 0)) }}</div>
        </div>
    </div>

    <div class="bapo-card">
        <div class="bapo-head">
            <h3 class="bapo-head__title">
                @if (!empty($isAdminView))
                    All BA partner outreach (state)
                @else
                    Your partner outreach entries
                @endif
            </h3>
            <div style="display:flex; flex-wrap:wrap; gap:0.5rem;">
                @if ($createRoute)
                    <a href="{{ route($createRoute) }}" class="bapo-btn">+ New outreach</a>
                @endif
                <a href="{{ route($exportRoute, $filterQuery) }}" class="bapo-btn bapo-btn--export">Export CSV</a>
            </div>
        </div>

        <form method="get" action="{{ route($dashboardRoute) }}">
            <div class="bapo-filters">
                <div class="bapo-filter-field">
                    <label>Search</label>
                    <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Partner, POC…">
                </div>
                <div class="bapo-filter-field">
                    <label>From</label>
                    <input type="date" name="from" value="{{ $filters['from'] ?? '' }}">
                </div>
                <div class="bapo-filter-field">
                    <label>To</label>
                    <input type="date" name="to" value="{{ $filters['to'] ?? '' }}">
                </div>
                <div class="bapo-filter-field">
                    <label>Partner type</label>
                    <select name="partner_type">
                        <option value="">All</option>
                        @foreach ($partnerTypes as $value => $label)
                            <option value="{{ $value }}" @selected(($filters['partner_type'] ?? '') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="bapo-filter-field">
                    <label>&nbsp;</label>
                    <button type="submit" class="bapo-btn">Filter</button>
                </div>
                <div class="bapo-filter-field">
                    <label>&nbsp;</label>
                    <a href="{{ route($dashboardRoute) }}" class="bapo-btn bapo-btn--secondary">Reset</a>
                </div>
            </div>
        </form>

        <div class="bapo-table-wrap">
            @if ($rows instanceof \Illuminate\Support\Collection ? $rows->isEmpty() : (empty($rows) || (is_object($rows) && method_exists($rows, 'isEmpty') && $rows->isEmpty())))
                <p class="bapo-empty">No partner outreach entries yet.</p>
            @else
                <table class="bapo-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Mode</th>
                            <th>Partner</th>
                            <th>Type</th>
                            <th>POC</th>
                            <th>Submitted by</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            <tr>
                                <td>{{ $row->outreach_date?->format('d M Y') ?? '—' }}</td>
                                <td>{{ \App\Support\BusinessAccelerationPartnersOutreachOptions::outreachModeLabel((string) $row->outreach_mode) }}</td>
                                <td>{{ $row->partner_name }}</td>
                                <td>{{ \App\Support\BusinessAccelerationPartnersOutreachOptions::partnerTypeLabel((string) $row->partner_type, $row->partner_type_other) }}</td>
                                <td>{{ $row->poc_name }}@if($row->poc_phone)<br><span style="color:#64748b;font-size:0.78rem;">{{ $row->poc_phone }}</span>@endif</td>
                                <td>{{ $row->submitted_by_name }}</td>
                                <td><a href="{{ route($showRoute, $row) }}" class="bapo-link">View</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

        @if (!empty($isPaginated) && is_object($rows) && method_exists($rows, 'links'))
            <div style="margin-top:0.85rem;">{{ $rows->links() }}</div>
        @endif
    </div>
</div>
@endsection
