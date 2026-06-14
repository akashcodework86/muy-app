@extends('layouts.admin')

@section('title', \App\Models\FundingSchematicPartnerOutreachEntry::MODULE_LABEL)
@section('heading', \App\Models\FundingSchematicPartnerOutreachEntry::MODULE_LABEL)

@push('styles')
<style>
    .fspoe-shell { display:flex; flex-direction:column; gap:1.25rem; }
    .fspoe-alert { border-radius:12px; padding:0.85rem 1rem; font-size:0.88rem; }
    .fspoe-alert--warning { background:#fffbeb; border:1px solid #fde68a; color:#92400e; }
    .fspoe-alert--success { background:#f0fdf4; border:1px solid #86efac; color:#166534; }
    .fspoe-stats { display:grid; grid-template-columns:repeat(auto-fit, minmax(140px, 1fr)); gap:0.65rem; }
    .fspoe-stat { background:#fff; border:1px solid #e2e8f0; border-radius:14px; padding:0.85rem 1rem; }
    .fspoe-stat__label { font-size:0.72rem; font-weight:700; text-transform:uppercase; color:#64748b; }
    .fspoe-stat__value { margin-top:0.3rem; font-size:1.45rem; font-weight:800; }
    .fspoe-card { background:#fff; border:1px solid #e2e8f0; border-radius:14px; padding:1.25rem; }
    .fspoe-btn { border:none; border-radius:8px; background:#7c3aed; color:#fff; padding:0.58rem 0.9rem; font-weight:700; text-decoration:none; font-size:0.88rem; display:inline-flex; }
    .fspoe-table-wrap { overflow:auto; border:1px solid #e2e8f0; border-radius:14px; background:#fff; }
    .fspoe-table { width:100%; border-collapse:collapse; font-size:0.84rem; }
    .fspoe-table th, .fspoe-table td { padding:0.7rem 0.75rem; border-bottom:1px solid #e2e8f0; text-align:left; }
    .fspoe-table .fspoe-serial { width:2.5rem; color:#64748b; font-weight:700; }
    .fspoe-table thead tr { background:#f8fafc; }
    .fspoe-link { color:#7c3aed; font-weight:700; text-decoration:none; }
</style>
@endpush

@section('content')
<div class="fspoe-shell">
    @if (!empty($migrationMissing))<div class="fspoe-alert fspoe-alert--warning">Run <code>php artisan migrate</code>.</div>@endif
    @if (session('status'))<div class="fspoe-alert fspoe-alert--success">{{ session('status') }}</div>@endif

    <div class="fspoe-stats">
        <div class="fspoe-stat">
            <div class="fspoe-stat__label">8.5 Unique partners (FY)</div>
            <div class="fspoe-stat__value">{{ number_format((int) ($deliverableStats['unique_partners_fy'] ?? 0)) }}</div>
        </div>
        <div class="fspoe-stat">
            <div class="fspoe-stat__label">Filtered entries</div>
            <div class="fspoe-stat__value">{{ number_format((int) ($totals['entries'] ?? 0)) }}</div>
        </div>
        <div class="fspoe-stat">
            <div class="fspoe-stat__label">Submissions</div>
            <div class="fspoe-stat__value">{{ number_format((int) ($totals['batches'] ?? 0)) }}</div>
        </div>
    </div>

    <div class="fspoe-card">
        <div style="display:flex;justify-content:space-between;flex-wrap:wrap;gap:0.5rem;margin-bottom:1rem;">
            <h3 style="margin:0;">{{ !empty($isAdminView) ? 'All funding partner outreach' : 'Your partner outreach' }}</h3>
            <div style="display:flex;gap:0.5rem;">
                @if ($createRoute)<a href="{{ route($createRoute) }}" class="fspoe-btn">+ New outreach</a>@endif
                <a href="{{ route($exportRoute, request()->only(['q','from','to','partner_type'])) }}" class="fspoe-btn">Export CSV</a>
            </div>
        </div>
        <form method="get" action="{{ route($dashboardRoute) }}">
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:0.85rem;align-items:end;margin-bottom:1rem;">
                <div><label style="font-size:0.78rem;font-weight:700;">Search</label><input type="search" name="q" value="{{ $filters['q'] ?? '' }}" style="width:100%;padding:0.55rem;border:1px solid #cbd5e1;border-radius:8px;"></div>
                <div><label style="font-size:0.78rem;font-weight:700;">From</label><input type="date" name="from" value="{{ $filters['from'] ?? '' }}" style="width:100%;padding:0.55rem;border:1px solid #cbd5e1;border-radius:8px;"></div>
                <div><label style="font-size:0.78rem;font-weight:700;">To</label><input type="date" name="to" value="{{ $filters['to'] ?? '' }}" style="width:100%;padding:0.55rem;border:1px solid #cbd5e1;border-radius:8px;"></div>
                <div><label style="font-size:0.78rem;font-weight:700;">Type</label>
                    <select name="partner_type" style="width:100%;padding:0.55rem;border:1px solid #cbd5e1;border-radius:8px;">
                        <option value="">All</option>
                        @foreach ($partnerTypes as $v => $l)<option value="{{ $v }}" @selected(($filters['partner_type'] ?? '') === $v)>{{ $l }}</option>@endforeach
                    </select>
                </div>
                <div><button type="submit" class="fspoe-btn">Filter</button></div>
            </div>
        </form>
        <div class="fspoe-table-wrap">
            <table class="fspoe-table">
                <thead><tr><th class="fspoe-serial">#</th><th>Date</th><th>Partner</th><th>Type</th><th>Mode</th><th>Contact</th><th>Phone</th><th>By</th><th></th></tr></thead>
                <tbody>
                @forelse ($rows as $row)
                    @php
                        $serial = method_exists($rows, 'firstItem') && $rows->firstItem()
                            ? (int) $rows->firstItem() + $loop->index
                            : $loop->iteration;
                    @endphp
                    <tr>
                        <td class="fspoe-serial">{{ $serial }}</td>
                        <td>{{ $row->outreach_date?->format('d M Y') }}</td>
                        <td>{{ $row->partner_name }}</td>
                        <td>{{ \App\Support\FundingSchematicPartnersOutreachOptions::partnerTypeLabel((string) $row->partner_type, $row->partner_type_other) }}</td>
                        <td>{{ \App\Support\FundingSchematicPartnersOutreachOptions::outreachModeLabel((string) $row->outreach_mode) }}</td>
                        <td>{{ $row->contact_name ?: '—' }}</td>
                        <td>{{ $row->poc_phone }}</td>
                        <td>{{ $row->submitted_by_name }}</td>
                        <td><a class="fspoe-link" href="{{ route($showRoute, $row) }}">View</a></td>
                    </tr>
                @empty
                    <tr><td colspan="9" style="color:#64748b;">No entries yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if (!empty($isPaginated) && method_exists($rows, 'links'))<div style="margin-top:1rem;">{{ $rows->links() }}</div>@endif
    </div>
</div>
@endsection
