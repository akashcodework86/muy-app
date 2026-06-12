@extends('layouts.admin')

@section('title', 'Partner outreach dashboard')
@section('heading', 'Partner outreach (MIS 6.1 / 6.2)')

@push('styles')
<style>
    .mpo-shell { display:flex; flex-direction:column; gap:1.25rem; }
    .mpo-alert { border-radius:12px; padding:0.85rem 1rem; font-size:0.88rem; }
    .mpo-alert--warning { background:#fffbeb; border:1px solid #fde68a; color:#92400e; }
    .mpo-alert--success { background:#f0fdf4; border:1px solid #86efac; color:#166534; }
    .mpo-stats { display:grid; grid-template-columns:repeat(auto-fit, minmax(140px, 1fr)); gap:0.65rem; }
    .mpo-stat { background:#fff; border:1px solid #e2e8f0; border-radius:14px; padding:0.85rem 1rem; }
    .mpo-stat__label { font-size:0.72rem; font-weight:700; text-transform:uppercase; letter-spacing:0.05em; color:#64748b; }
    .mpo-stat__value { margin-top:0.3rem; font-size:1.45rem; font-weight:800; color:#0f172a; }
    .mpo-stat__hint { margin-top:0.2rem; font-size:0.72rem; color:#64748b; }
    .mpo-card { background:#fff; border:1px solid #e2e8f0; border-radius:14px; padding:1.25rem 1.35rem; }
    .mpo-head { display:flex; flex-wrap:wrap; justify-content:space-between; align-items:flex-start; gap:0.75rem; margin-bottom:1rem; }
    .mpo-head__title { margin:0; font-size:1rem; font-weight:700; color:#0f172a; }
    .mpo-head__meta { margin:0.2rem 0 0; font-size:0.82rem; color:#64748b; font-weight:600; }
    .mpo-head__actions { display:flex; flex-wrap:wrap; gap:0.5rem; }
    .mpo-filters { display:grid; grid-template-columns:repeat(auto-fit, minmax(150px, 1fr)); gap:0.85rem; align-items:end; margin-bottom:1rem; }
    .mpo-filter-field { display:flex; flex-direction:column; gap:0.35rem; }
    .mpo-filter-field label { font-size:0.78rem; font-weight:700; color:#0f172a; }
    .mpo-filter-field input, .mpo-filter-field select { border:1px solid #cbd5e1; border-radius:8px; padding:0.55rem 0.65rem; font-size:0.88rem; }
    .mpo-btn { border:none; border-radius:8px; background:#7c3aed; color:#fff; padding:0.58rem 0.9rem; font-weight:700; cursor:pointer; font-size:0.88rem; text-decoration:none; display:inline-flex; align-items:center; }
    .mpo-btn--secondary { background:#fff; color:#334155; border:1px solid #cbd5e1; }
    .mpo-btn--export { background:#065f46; }
    .mpo-table-wrap { background:#fff; border:1px solid #e2e8f0; border-radius:14px; overflow:auto; }
    .mpo-table { width:100%; border-collapse:collapse; font-size:0.84rem; }
    .mpo-table th, .mpo-table td { text-align:left; padding:0.7rem 0.75rem; border-bottom:1px solid #e2e8f0; vertical-align:top; }
    .mpo-table thead tr { background:#f8fafc; }
    .mpo-link { color:#7c3aed; font-weight:700; text-decoration:none; }
    .mpo-badge { display:inline-flex; align-items:center; padding:0.18rem 0.5rem; border-radius:999px; font-size:0.72rem; font-weight:700; }
    .mpo-badge--outreach { background:#ede9fe; color:#5b21b6; }
    .mpo-badge--discussion { background:#fef3c7; color:#92400e; }
    .mpo-badge--onboarded { background:#dcfce7; color:#166534; }
    .mpo-badge--declined { background:#f1f5f9; color:#475569; }
    .mpo-empty { padding:1rem; color:#64748b; }
</style>
@endpush

@section('content')
@php
    $filterQuery = array_filter([
        'q' => $filters['q'] ?? '',
        'from' => $filters['from'] ?? '',
        'to' => $filters['to'] ?? '',
        'status' => $filters['status'] ?? '',
        'cohort_or_sector' => $filters['cohort_or_sector'] ?? '',
    ], fn ($v) => $v !== null && $v !== '');
    $totalCount = (int) ($totals['total'] ?? 0);
@endphp
<div class="mpo-shell">
    @if (!empty($migrationMissing))
        <div class="mpo-alert mpo-alert--warning">
            <strong>Database update required.</strong> Run <code>php artisan migrate</code> for the partner outreach table.
        </div>
    @endif

    @if (session('status'))
        <div class="mpo-alert mpo-alert--success">{{ session('status') }}</div>
    @endif

    <div class="mpo-stats">
        <div class="mpo-stat">
            <div class="mpo-stat__label">6.1 Outreach</div>
            <div class="mpo-stat__value">{{ number_format((int) ($totals['total'] ?? 0)) }}</div>
            <div class="mpo-stat__hint">Filtered list total</div>
        </div>
        <div class="mpo-stat">
            <div class="mpo-stat__label">6.2 Onboarded</div>
            <div class="mpo-stat__value">{{ number_format((int) ($totals['onboarded'] ?? 0)) }}</div>
            <div class="mpo-stat__hint">LoA / LoI / MoU</div>
        </div>
        <div class="mpo-stat">
            <div class="mpo-stat__label">In discussion</div>
            <div class="mpo-stat__value">{{ number_format((int) ($totals['in_discussion'] ?? 0)) }}</div>
        </div>
        <div class="mpo-stat">
            <div class="mpo-stat__label">Declined</div>
            <div class="mpo-stat__value">{{ number_format((int) ($totals['declined'] ?? 0)) }}</div>
        </div>
    </div>

    <div class="mpo-card">
        <div class="mpo-head">
            <div>
                <h3 class="mpo-head__title">
                    @if (!empty($isAdminView))
                        All partner outreach entries (state)
                    @else
                        Your partner outreach entries
                    @endif
                </h3>
                @if (!empty($isPaginated) && is_object($rows) && method_exists($rows, 'firstItem') && $totalCount > 0)
                    <p class="mpo-head__meta">
                        Showing {{ number_format((int) $rows->firstItem()) }}–{{ number_format((int) $rows->lastItem()) }} of {{ number_format($totalCount) }}
                    </p>
                @elseif ($totalCount === 0)
                    <p class="mpo-head__meta">No entries match the current filters.</p>
                @endif
            </div>
            <div class="mpo-head__actions">
                @if ($createRoute)
                    <a href="{{ route($createRoute) }}" class="mpo-btn">+ Log new outreach</a>
                @endif
                <a href="{{ route($exportRoute, $filterQuery) }}" class="mpo-btn mpo-btn--export">Export CSV</a>
            </div>
        </div>

        <form method="get" action="{{ route($dashboardRoute) }}" class="mpo-filters">
            <div class="mpo-filter-field">
                <label for="q">Search</label>
                <input type="text" id="q" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Partner, POC, phone…">
            </div>
            <div class="mpo-filter-field">
                <label for="from">From</label>
                <input type="date" id="from" name="from" value="{{ $filters['from'] ?? '' }}">
            </div>
            <div class="mpo-filter-field">
                <label for="to">To</label>
                <input type="date" id="to" name="to" value="{{ $filters['to'] ?? '' }}">
            </div>
            <div class="mpo-filter-field">
                <label for="status">Status</label>
                <select id="status" name="status">
                    <option value="">All statuses</option>
                    @foreach ($statuses as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="mpo-filter-field">
                <label for="cohort_or_sector">Cohort / sector</label>
                <select id="cohort_or_sector" name="cohort_or_sector">
                    <option value="">All</option>
                    @foreach ($cohortOrSectors as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['cohort_or_sector'] ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="mpo-filter-field">
                <button type="submit" class="mpo-btn">Filter</button>
                <a href="{{ route($dashboardRoute) }}" class="mpo-btn mpo-btn--secondary" style="margin-top:0.35rem;">Reset</a>
            </div>
        </form>
    </div>

    <div class="mpo-table-wrap">
        @if ($totalCount === 0)
            <div class="mpo-empty">No partner outreach entries yet.</div>
        @else
            <table class="mpo-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Partner</th>
                        <th>Designation</th>
                        <th>Cohort / sector</th>
                        <th>POC</th>
                        <th>Phone</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr>
                            <td>{{ $row->outreach_date?->format('d M Y') }}</td>
                            <td>{{ $row->partner_name }}</td>
                            <td>{{ $row->partner_designation }}</td>
                            <td>{{ \App\Support\PartnerOutreachOptions::cohortOrSectorDisplay((string) $row->cohort_or_sector, $row->cohort_or_sector_other) }}</td>
                            <td>{{ $row->poc_name ?: '—' }}</td>
                            <td>{{ $row->poc_phone }}</td>
                            <td>
                                <span class="mpo-badge {{ \App\Support\PartnerOutreachOptions::statusBadgeClass((string) $row->status) }}">
                                    {{ \App\Support\PartnerOutreachOptions::statusLabel((string) $row->status) }}
                                </span>
                            </td>
                            <td>
                                <a href="{{ route($showRoute, $row) }}" class="mpo-link">
                                    @if (!empty($isAdminView))
                                        View
                                    @else
                                        View / update
                                    @endif
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            @if (!empty($isPaginated) && is_object($rows) && method_exists($rows, 'links'))
                <div style="padding:0.75rem;">{{ $rows->links() }}</div>
            @endif
        @endif
    </div>
</div>
@endsection
