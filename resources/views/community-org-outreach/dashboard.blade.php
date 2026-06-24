@extends('layouts.admin')

@section('title', 'Community organization outreach')
@section('heading', 'Community organization outreach (MIS 1.5)')

@push('styles')
<style>
    .coo-shell { display:flex; flex-direction:column; gap:1.25rem; }
    .coo-alert { border-radius:12px; padding:0.85rem 1rem; font-size:0.88rem; }
    .coo-alert--warning { background:#fffbeb; border:1px solid #fde68a; color:#92400e; }
    .coo-alert--success { background:#f0fdf4; border:1px solid #86efac; color:#166534; }
    .coo-card { background:#fff; border:1px solid #e2e8f0; border-radius:14px; padding:1.25rem 1.35rem; }
    .coo-head { display:flex; flex-wrap:wrap; justify-content:space-between; align-items:flex-start; gap:0.75rem; margin-bottom:1rem; }
    .coo-head__title { margin:0; font-size:1rem; font-weight:700; color:#0f172a; }
    .coo-head__meta { margin:0.2rem 0 0; font-size:0.82rem; color:#64748b; font-weight:600; }
    .coo-head__count { display:inline-flex; align-items:center; padding:0.2rem 0.55rem; border-radius:999px; background:#ccfbf1; border:1px solid #99f6e4; color:#115e59; font-size:0.78rem; font-weight:800; margin-left:0.35rem; }
    .coo-head__actions { display:flex; flex-wrap:wrap; gap:0.5rem; align-items:center; }
    .coo-filters { display:grid; grid-template-columns:repeat(auto-fit, minmax(160px, 1fr)); gap:0.85rem; align-items:end; }
    .coo-filter-field { display:flex; flex-direction:column; gap:0.35rem; }
    .coo-filter-field label { font-size:0.78rem; font-weight:700; color:#0f172a; }
    .coo-filter-field input, .coo-filter-field select { border:1px solid #cbd5e1; border-radius:8px; padding:0.55rem 0.65rem; font-size:0.88rem; }
    .coo-filter-actions { display:flex; flex-wrap:wrap; gap:0.55rem; }
    .coo-btn { border:none; border-radius:8px; background:#0d9488; color:#fff; padding:0.58rem 0.9rem; font-weight:700; cursor:pointer; font-size:0.88rem; text-decoration:none; display:inline-flex; align-items:center; }
    .coo-btn--secondary { background:#fff; color:#334155; border:1px solid #cbd5e1; }
    .coo-btn--export { background:#065f46; }
    .coo-table-wrap { background:#fff; border:1px solid #e2e8f0; border-radius:14px; overflow:auto; }
    .coo-table { width:100%; border-collapse:collapse; font-size:0.84rem; }
    .coo-table th, .coo-table td { text-align:left; padding:0.7rem 0.75rem; border-bottom:1px solid #e2e8f0; vertical-align:top; }
    .coo-table tbody tr:last-child td { border-bottom:none; }
    .coo-table thead tr { background:#f8fafc; }
    .coo-empty { padding:1rem; color:#64748b; }
    .coo-link { color:#0d9488; font-weight:700; text-decoration:none; }
    .coo-muted { color:#64748b; font-size:0.8rem; }
    .coo-none { color:#94a3b8; font-size:0.78rem; font-style:italic; }
    .coo-doc-links { display:flex; flex-direction:column; gap:0.25rem; }
    .coo-doc-links a { color:#0d9488; font-weight:700; text-decoration:none; font-size:0.8rem; }
    .coo-thumb-row { display:flex; flex-wrap:wrap; gap:0.35rem; }
    .coo-thumb-row a { display:block; width:42px; height:42px; border-radius:8px; overflow:hidden; border:1px solid #e2e8f0; }
    .coo-thumb-row img { display:block; width:100%; height:100%; object-fit:cover; }
</style>
@endpush

@section('content')
@php
    $filterQuery = array_filter([
        'q' => $filters['q'] ?? '',
        'from' => $filters['from'] ?? '',
        'to' => $filters['to'] ?? '',
        'district_id' => $filters['district_id'] ?? '',
        'hub_id' => $filters['hub_id'] ?? '',
    ], fn ($v) => $v !== null && $v !== '');
    $totalCount = (int) ($totals['total'] ?? 0);
@endphp
<div class="coo-shell">
    @if (!empty($migrationMissing))
        <div class="coo-alert coo-alert--warning">
            <strong>Database update required.</strong> Run <code>php artisan migrate</code> for the community organization outreach table.
        </div>
    @endif

    @if (session('status'))
        <div class="coo-alert coo-alert--success">{{ session('status') }}</div>
    @endif

    <div class="coo-card">
        <div class="coo-head">
            <div>
                <h3 class="coo-head__title">
                    @if (!empty($isAdminView))
                        All hub entries (state)
                    @else
                        Hub outreach visits
                    @endif
                    <span class="coo-head__count">{{ number_format($totalCount) }} visits</span>
                </h3>
                @if (!empty($isPaginated) && is_object($rows) && method_exists($rows, 'firstItem') && $totalCount > 0)
                    <p class="coo-head__meta">
                        Showing {{ number_format((int) $rows->firstItem()) }}–{{ number_format((int) $rows->lastItem()) }} of {{ number_format($totalCount) }}
                    </p>
                @elseif ($totalCount === 0)
                    <p class="coo-head__meta">No entries match the current filters.</p>
                @endif
            </div>
            <div class="coo-head__actions">
                @if ($createRoute)
                    <a href="{{ route($createRoute) }}" class="coo-btn">+ Log new visit</a>
                @endif
                <a href="{{ route($exportRoute, $filterQuery) }}" class="coo-btn coo-btn--export">Export CSV</a>
            </div>
        </div>

        <form method="get" action="{{ route($dashboardRoute) }}" class="coo-filters" style="margin-bottom:1rem;">
            <div class="coo-filter-field">
                <label for="q">Search</label>
                <input type="text" id="q" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Org, POC, person met…">
            </div>
            <div class="coo-filter-field">
                <label for="from">From</label>
                <input type="date" id="from" name="from" value="{{ $filters['from'] ?? '' }}">
            </div>
            <div class="coo-filter-field">
                <label for="to">To</label>
                <input type="date" id="to" name="to" value="{{ $filters['to'] ?? '' }}">
            </div>
            @if (!empty($isAdminView))
                <div class="coo-filter-field">
                    <label for="hub_id">Hub</label>
                    <select id="hub_id" name="hub_id">
                        <option value="">All hubs</option>
                        @foreach ($hubs as $h)
                            <option value="{{ $h->id }}" @selected((string) ($filters['hub_id'] ?? '') === (string) $h->id)>{{ $h->name }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            <div class="coo-filter-field">
                <label for="district_id">District</label>
                <select id="district_id" name="district_id">
                    <option value="">All districts</option>
                    @foreach ($districts as $d)
                        <option value="{{ $d->id }}" @selected((string) ($filters['district_id'] ?? '') === (string) $d->id)>
                            @if (!empty($isAdminView) && $d->relationLoaded('hub'))
                                {{ $d->hub?->name }} — {{ $d->name }}
                            @else
                                {{ $d->name }}
                            @endif
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="coo-filter-actions">
                <button type="submit" class="coo-btn">Apply</button>
                <a href="{{ route($dashboardRoute) }}" class="coo-btn coo-btn--secondary">Reset</a>
            </div>
        </form>

        <div class="coo-table-wrap">
            @if ($totalCount === 0)
                <p class="coo-empty">No outreach visits recorded yet.</p>
            @else
                <table class="coo-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            @if (!empty($isAdminView))<th>Hub</th>@endif
                            <th>District</th>
                            <th>Organisation</th>
                            <th>Person met</th>
                            <th>POC</th>
                            <th>Purpose</th>
                            <th>Documents</th>
                            <th>Photos</th>
                            <th>Submitted by</th>
                            @if (\App\Models\CommunityOrganizationOutreachVisit::supportsMisFieldWorkflow())
                            <th>Approval status</th>
                            <th>Assigned SPOC</th>
                            @endif
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            <tr>
                                <td>{{ $row->visit_date?->format('d M Y') }}</td>
                                @if (!empty($isAdminView))<td>{{ $row->hub_name }}</td>@endif
                                <td>{{ $row->district_name }}</td>
                                <td>
                                    <strong>{{ $row->organization_name }}</strong>
                                    <div class="coo-muted">{{ \App\Support\CommunityOrganizationOutreachOptions::organizationTypeDisplay((string) $row->organization_type, $row->organization_type_other) }}</div>
                                </td>
                                <td>
                                    {{ $row->person_met_name }}
                                    @if ($row->person_met_designation)
                                        <div class="coo-muted">{{ $row->person_met_designation }}</div>
                                    @endif
                                </td>
                                <td>
                                    {{ $row->poc_name }}
                                    <div class="coo-muted">{{ $row->poc_phone }}</div>
                                </td>
                                <td>{{ \App\Support\CommunityOrganizationOutreachOptions::labelFor('purpose', (string) $row->purpose) }}</td>
                                <td>
                                    @php $documents = array_values(array_filter((array) $row->documents_json, 'is_array')); @endphp
                                    @if ($documents === [])
                                        <span class="coo-none">No doc</span>
                                    @else
                                        <div class="coo-doc-links">
                                            @foreach ($documents as $docIndex => $doc)
                                                <a href="{{ route($documentRoute, [$row, 'index' => $docIndex]) }}" target="_blank" rel="noopener">
                                                    View doc{{ count($documents) > 1 ? ' '.($docIndex + 1) : '' }}
                                                </a>
                                            @endforeach
                                        </div>
                                    @endif
                                </td>
                                <td>
                                    @php $photos = array_values(array_filter((array) $row->photos_json, 'is_array')); @endphp
                                    @if ($photos === [])
                                        <span class="coo-none">No images</span>
                                    @else
                                        <div class="coo-thumb-row">
                                            @foreach ($photos as $photoIndex => $photo)
                                                <a href="{{ route($photoRoute, [$row, 'index' => $photoIndex, 'inline' => 1]) }}" target="_blank" rel="noopener" title="Photo {{ $photoIndex + 1 }}">
                                                    <img src="{{ route($photoRoute, [$row, 'index' => $photoIndex, 'inline' => 1]) }}" alt="Visit photo {{ $photoIndex + 1 }}">
                                                </a>
                                            @endforeach
                                        </div>
                                    @endif
                                </td>
                                <td>{{ $row->submitted_by_name }}</td>
                                @include('partials.mis-field-workflow-dashboard-cells', ['row' => $row])
                                <td>
                                    <a href="{{ route($showRoute, $row) }}" class="coo-link">View</a>
                                    @include('partials.mis-field-workflow-row-actions', [
                                        'row' => $row,
                                        'editRoute' => $editRoute ?? null,
                                        'destroyRoute' => $destroyRoute ?? null,
                                        'editClass' => 'coo-link',
                                        'withdrawClass' => 'coo-link',
                                    ])
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

        @if (!empty($isPaginated) && is_object($rows) && method_exists($rows, 'links'))
            <div style="margin-top:1rem;">{{ $rows->links() }}</div>
        @endif
    </div>
</div>
@endsection
