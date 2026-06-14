@extends('layouts.admin')

@section('title', \App\Models\DemoDay::MODULE_LABEL)
@section('heading', \App\Models\DemoDay::MODULE_LABEL)

@push('styles')
<style>
    .ddy-shell { display:flex; flex-direction:column; gap:1.25rem; }
    .ddy-alert { border-radius:12px; padding:0.85rem 1rem; font-size:0.88rem; }
    .ddy-alert--warning { background:#fffbeb; border:1px solid #fde68a; color:#92400e; }
    .ddy-alert--success { background:#f0fdf4; border:1px solid #86efac; color:#166534; }
    .ddy-stats { display:grid; grid-template-columns:repeat(auto-fit, minmax(120px, 1fr)); gap:0.65rem; }
    .ddy-stat { background:#fff; border:1px solid #e2e8f0; border-radius:14px; padding:0.85rem 1rem; }
    .ddy-stat__label { font-size:0.72rem; font-weight:700; text-transform:uppercase; color:#64748b; }
    .ddy-stat__value { margin-top:0.3rem; font-size:1.45rem; font-weight:800; }
    .ddy-stat__hint { margin-top:0.2rem; font-size:0.72rem; color:#64748b; }
    .ddy-card { background:#fff; border:1px solid #e2e8f0; border-radius:14px; padding:1.25rem; }
    .ddy-filters { display:grid; grid-template-columns:repeat(auto-fit, minmax(150px, 1fr)); gap:0.85rem; align-items:end; margin-bottom:1rem; }
    .ddy-btn { border:none; border-radius:8px; background:#7c3aed; color:#fff; padding:0.58rem 0.9rem; font-weight:700; text-decoration:none; display:inline-flex; font-size:0.88rem; }
    .ddy-btn--secondary { background:#fff; color:#334155; border:1px solid #cbd5e1; }
    .ddy-table-wrap { overflow:auto; border:1px solid #e2e8f0; border-radius:14px; background:#fff; }
    .ddy-table { width:100%; border-collapse:collapse; font-size:0.84rem; }
    .ddy-table th, .ddy-table td { padding:0.7rem 0.75rem; border-bottom:1px solid #e2e8f0; text-align:left; vertical-align:top; }
    .ddy-table thead tr { background:#f8fafc; }
    .ddy-table .ddy-serial { width:2.5rem; color:#64748b; font-weight:700; }
    .ddy-link { color:#7c3aed; font-weight:700; text-decoration:none; }
    .ddy-photo-thumbs { display:flex; flex-wrap:wrap; gap:0.35rem; align-items:center; max-width:11rem; }
    .ddy-photo-thumb {
        width:42px; height:42px; border-radius:8px; object-fit:cover;
        border:1px solid #e2e8f0; background:#f8fafc; flex-shrink:0;
    }
    .ddy-photo-more {
        display:inline-flex; align-items:center; justify-content:center;
        min-width:42px; height:42px; border-radius:8px; background:#f1f5f9;
        border:1px solid #e2e8f0; font-size:0.72rem; font-weight:800; color:#475569;
        text-decoration:none;
    }
    .ddy-photo-empty { color:#94a3b8; font-size:0.78rem; }
</style>
@endpush

@section('content')
<div class="ddy-shell">
    @if (!empty($migrationMissing))<div class="ddy-alert ddy-alert--warning">Run <code>php artisan migrate</code>.</div>@endif
    @if (session('status'))<div class="ddy-alert ddy-alert--success">{{ session('status') }}</div>@endif

    <div class="ddy-stats">
        <div class="ddy-stat">
            <div class="ddy-stat__label">8.4 Events (FY)</div>
            <div class="ddy-stat__value">{{ number_format((int) ($deliverableStats['events_fy'] ?? 0)) }}</div>
            <div class="ddy-stat__hint">Deliverable achievement</div>
        </div>
        <div class="ddy-stat">
            <div class="ddy-stat__label">Filtered events</div>
            <div class="ddy-stat__value">{{ number_format((int) ($totals['events'] ?? 0)) }}</div>
        </div>
        <div class="ddy-stat">
            <div class="ddy-stat__label">Total participants</div>
            <div class="ddy-stat__value">{{ number_format((int) ($totals['total_participants'] ?? 0)) }}</div>
            <div class="ddy-stat__hint">Selected incubatees (filtered)</div>
        </div>
    </div>

    <div class="ddy-card">
        <div style="display:flex; justify-content:space-between; flex-wrap:wrap; gap:0.5rem; margin-bottom:1rem;">
            <h3 style="margin:0; font-size:1rem;">{{ !empty($isAdminView) ? 'All demo days' : 'Your demo days' }}</h3>
            <div style="display:flex; gap:0.5rem;">
                @if ($createRoute)<a href="{{ route($createRoute) }}" class="ddy-btn">+ New demo day</a>@endif
                <a href="{{ route($exportRoute, request()->only(['q','from','to','event_type'])) }}" class="ddy-btn">Export CSV</a>
            </div>
        </div>
        <form method="get" action="{{ route($dashboardRoute) }}">
            <div class="ddy-filters">
                <div><label style="font-size:0.78rem;font-weight:700;">Search</label><input type="search" name="q" value="{{ $filters['q'] ?? '' }}" style="width:100%;padding:0.55rem;border:1px solid #cbd5e1;border-radius:8px;"></div>
                <div><label style="font-size:0.78rem;font-weight:700;">From</label><input type="date" name="from" value="{{ $filters['from'] ?? '' }}" style="width:100%;padding:0.55rem;border:1px solid #cbd5e1;border-radius:8px;"></div>
                <div><label style="font-size:0.78rem;font-weight:700;">To</label><input type="date" name="to" value="{{ $filters['to'] ?? '' }}" style="width:100%;padding:0.55rem;border:1px solid #cbd5e1;border-radius:8px;"></div>
                <div><label style="font-size:0.78rem;font-weight:700;">Type</label>
                    <select name="event_type" style="width:100%;padding:0.55rem;border:1px solid #cbd5e1;border-radius:8px;">
                        <option value="">All</option>
                        @foreach ($eventTypes as $v => $l)<option value="{{ $v }}" @selected(($filters['event_type'] ?? '') === $v)>{{ $l }}</option>@endforeach
                    </select>
                </div>
                <div><button type="submit" class="ddy-btn">Filter</button></div>
                <div><a href="{{ route($dashboardRoute) }}" class="ddy-btn ddy-btn--secondary">Reset</a></div>
            </div>
        </form>

        <div class="ddy-table-wrap">
            <table class="ddy-table">
                <thead>
                    <tr>
                        <th class="ddy-serial">#</th>
                        <th>Date</th>
                        <th>Event</th>
                        <th>Type</th>
                        <th>Participants</th>
                        <th>Photos</th>
                        <th>Incubatee</th>
                        <th>District</th>
                        <th>By</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                @forelse ($rows as $row)
                    @php
                        $photos = $row->eventPhotoItems();
                        $photoLimit = 3;
                        $serial = method_exists($rows, 'firstItem') && $rows->firstItem()
                            ? (int) $rows->firstItem() + $loop->index
                            : $loop->iteration;
                    @endphp
                    <tr>
                        <td class="ddy-serial">{{ $serial }}</td>
                        <td>{{ $row->event_date?->format('d M Y') }}</td>
                        <td>{{ $row->event_name }}</td>
                        <td>{{ \App\Support\DemoDayOptions::eventTypeLabel((string) $row->event_type, $row->event_type_other) }}</td>
                        <td>{{ $row->participantCounts()['total'] }}</td>
                        <td>
                            @if ($photos === [])
                                <span class="ddy-photo-empty">—</span>
                            @else
                                <div class="ddy-photo-thumbs">
                                    @foreach (array_slice($photos, 0, $photoLimit) as $photoIdx => $photo)
                                        <a href="{{ route($attachmentRoute, $row) }}?index={{ $photoIdx }}&amp;inline=1" target="_blank" rel="noopener" title="{{ $photo['original_name'] ?? 'Photo' }}">
                                            <img
                                                class="ddy-photo-thumb"
                                                src="{{ route($attachmentRoute, $row) }}?index={{ $photoIdx }}&amp;inline=1"
                                                alt="{{ $photo['original_name'] ?? 'Event photo' }}"
                                                loading="lazy">
                                        </a>
                                    @endforeach
                                    @if (count($photos) > $photoLimit)
                                        <a class="ddy-photo-more" href="{{ route($showRoute, $row) }}" title="View all photos">+{{ count($photos) - $photoLimit }}</a>
                                    @endif
                                </div>
                            @endif
                        </td>
                        <td>
                            @php $count = $row->participatingIncubateeCount(); @endphp
                            {{ $row->incubateeNamesSummary() }}
                            @if ($count > 1)<span style="color:#64748b;font-size:0.76rem;"> ({{ $count }})</span>@endif
                        </td>
                        <td>{{ $row->district?->name ?? '—' }}</td>
                        <td>{{ $row->entered_by_name }}</td>
                        <td><a class="ddy-link" href="{{ route($showRoute, $row) }}">View</a></td>
                    </tr>
                @empty
                    <tr><td colspan="10" style="color:#64748b;">No demo day entries yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if (!empty($isPaginated) && method_exists($rows, 'links'))<div style="margin-top:1rem;">{{ $rows->links() }}</div>@endif
    </div>
</div>
@endsection
