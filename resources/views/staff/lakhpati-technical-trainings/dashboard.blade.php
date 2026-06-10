@extends('layouts.admin')

@section('title', \App\Models\LakhpatiTechnicalTraining::MODULE_LABEL)
@section('heading', \App\Models\LakhpatiTechnicalTraining::MODULE_LABEL)

@push('styles')
<style>
    .ltt-shell { display:flex; flex-direction:column; gap:1.25rem; }
    .ltt-alert { border-radius:12px; padding:0.85rem 1rem; font-size:0.88rem; }
    .ltt-alert--warning { background:#fffbeb; border:1px solid #fde68a; color:#92400e; }
    .ltt-alert--success { background:#f0fdf4; border:1px solid #86efac; color:#166534; }
    .ltt-hero {
        display:flex; flex-wrap:wrap; align-items:flex-start; justify-content:space-between; gap:1rem;
        padding:1.1rem 1.25rem; border-radius:16px;
        background:linear-gradient(135deg, #eef2ff 0%, #f8fafc 55%, #ecfdf5 100%);
        border:1px solid #e2e8f0;
    }
    .ltt-hero__title { margin:0; font-size:1rem; font-weight:800; color:#0f172a; line-height:1.45; max-width:42rem; }
    .ltt-hero__sub { margin:0.35rem 0 0; font-size:0.84rem; color:#64748b; }
    .ltt-stat-grid { display:grid; grid-template-columns:repeat(auto-fit, minmax(170px, 1fr)); gap:0.85rem; }
    .ltt-stat-card {
        background:#fff; border:1px solid #e2e8f0; border-radius:14px; padding:0.95rem 1.05rem;
        box-shadow:0 1px 2px rgba(15,23,42,0.04);
    }
    .ltt-stat-card--accent { border-color:#c7d2fe; background:linear-gradient(180deg,#fff,#f5f7ff); }
    .ltt-stat-card__label { font-size:0.72rem; color:#64748b; text-transform:uppercase; letter-spacing:0.07em; font-weight:700; }
    .ltt-stat-card__value { margin-top:0.35rem; font-size:1.35rem; font-weight:800; color:#0f172a; line-height:1.1; }
    .ltt-stat-card__hint { margin-top:0.25rem; font-size:0.76rem; color:#94a3b8; }
    .ltt-card { background:#fff; border:1px solid #e2e8f0; border-radius:14px; padding:1.2rem 1.3rem; }
    .ltt-card__head { margin:0 0 1rem; font-size:0.72rem; font-weight:700; letter-spacing:0.08em; text-transform:uppercase; color:#64748b; }
    .ltt-filters { display:grid; grid-template-columns:repeat(auto-fit, minmax(155px, 1fr)); gap:0.85rem; align-items:end; }
    .ltt-filter-field { display:flex; flex-direction:column; gap:0.35rem; min-width:0; }
    .ltt-filter-field label { font-size:0.78rem; font-weight:700; color:#0f172a; }
    .ltt-filter-field input, .ltt-filter-field select {
        width:100%; box-sizing:border-box; border:1px solid #cbd5e1; border-radius:8px;
        padding:0.55rem 0.65rem; font-size:0.88rem; background:#fff;
    }
    .ltt-filter-actions { display:flex; flex-wrap:wrap; gap:0.55rem; align-items:center; grid-column:1 / -1; padding-top:0.15rem; }
    .ltt-btn { border:none; border-radius:8px; background:#4f46e5; color:#fff; padding:0.58rem 0.95rem; font-weight:700; cursor:pointer; font-size:0.88rem; }
    .ltt-btn--secondary {
        display:inline-flex; align-items:center; padding:0.58rem 0.95rem; border-radius:8px;
        border:1px solid #cbd5e1; background:#fff; color:#334155; text-decoration:none; font-weight:600; font-size:0.88rem;
    }
    .ltt-btn--secondary:hover { background:#f8fafc; }
    .ltt-table-card { background:#fff; border:1px solid #e2e8f0; border-radius:14px; overflow:auto; box-shadow:0 1px 2px rgba(15,23,42,0.04); }
    .ltt-table { width:100%; border-collapse:collapse; font-size:0.84rem; min-width:1080px; }
    .ltt-table thead tr { background:linear-gradient(180deg,#f8fafc,#f1f5f9); }
    .ltt-table th {
        text-align:left; padding:0.72rem 0.75rem; border-bottom:1px solid #e2e8f0;
        font-size:0.68rem; font-weight:700; text-transform:uppercase; letter-spacing:0.05em; color:#64748b; white-space:nowrap;
    }
    .ltt-table td { padding:0.72rem 0.75rem; border-bottom:1px solid #e2e8f0; vertical-align:top; }
    .ltt-table tbody tr:hover { background:#fafbff; }
    .ltt-table tbody tr:last-child td { border-bottom:none; }
    .ltt-table tfoot tr { background:#f8fafc; }
    .ltt-table tfoot td { font-weight:800; color:#0f172a; border-top:2px solid #cbd5e1; }
    .ltt-title { font-weight:800; color:#0f172a; line-height:1.35; }
    .ltt-brief { color:#64748b; font-size:0.78rem; line-height:1.4; margin-top:0.2rem; max-width:18rem; }
    .ltt-venue { font-size:0.8rem; color:#475569; max-width:10rem; }
    .ltt-agency {
        display:inline-flex; align-items:center; padding:0.2rem 0.55rem; border-radius:999px;
        font-size:0.72rem; font-weight:700; background:#f1f5f9; color:#334155; border:1px solid #e2e8f0;
        max-width:11rem; white-space:normal; line-height:1.3;
    }
    .ltt-pill { display:inline-flex; padding:0.2rem 0.55rem; border-radius:999px; font-size:0.72rem; font-weight:800; white-space:nowrap; }
    .ltt-pill--virtual { background:#e0f2fe; color:#0369a1; border:1px solid #7dd3fc; }
    .ltt-pill--physical { background:#ecfdf5; color:#047857; border:1px solid #6ee7b7; }
    .ltt-row-actions { display:flex; gap:0.45rem; flex-wrap:wrap; align-items:center; }
    .ltt-btn--view, .ltt-btn--edit {
        display:inline-flex; align-items:center; justify-content:center; padding:0.42rem 0.75rem;
        border-radius:8px; font-size:0.78rem; font-weight:800; text-decoration:none;
    }
    .ltt-btn--view { background:#eef2ff; color:#3730a3; border:1px solid #c7d2fe; }
    .ltt-btn--view:hover { background:#e0e7ff; }
    .ltt-btn--edit { background:#fff; color:#334155; border:1px solid #cbd5e1; }
    .ltt-btn--edit:hover { background:#f8fafc; }
    .ltt-empty { padding:1.25rem; color:#64748b; text-align:center; }
    .ees-dash-photos { display:flex; flex-wrap:wrap; gap:0.35rem; align-items:center; max-width:220px; }
    .ees-dash-photo { width:42px; height:42px; object-fit:cover; border-radius:8px; border:1px solid #e2e8f0; cursor:pointer; background:#f8fafc; }
    .ees-dash-photo-more {
        display:inline-flex; align-items:center; justify-content:center; min-width:42px; height:42px;
        padding:0 0.35rem; border-radius:8px; background:#f1f5f9; border:1px solid #e2e8f0;
        font-size:0.72rem; font-weight:800; color:#475569;
    }
    .ees-dash-photos-empty { font-size:0.76rem; color:#94a3b8; font-weight:600; }
    .ees-dash-att__no-doc { font-size:0.76rem; color:#94a3b8; font-weight:600; }
</style>
@endpush

@section('content')
<div class="ltt-shell">
    @if (!empty($migrationMissing))
        <div class="ltt-alert ltt-alert--warning">Run <code>php artisan migrate</code> first.</div>
    @endif
    @if (session('status'))
        <div class="ltt-alert ltt-alert--success">{{ session('status') }}</div>
    @endif

    <div class="ltt-hero">
        <div>
            <h2 class="ltt-hero__title">{{ \App\Models\LakhpatiTechnicalTraining::MODULE_LABEL }}</h2>
            <p class="ltt-hero__sub">Partner-requested technical training sessions — MIS counts <strong>sessions</strong>, not participants.</p>
        </div>
        @if (auth()->user()->role === 'district_staff')
            <a href="{{ route('staff.lakhpati-technical-trainings.create') }}" class="ltt-btn--secondary">+ New session entry</a>
        @endif
    </div>

    <div class="ltt-stat-grid">
        <div class="ltt-stat-card ltt-stat-card--accent">
            <div class="ltt-stat-card__label">Sessions (3.3.1)</div>
            <div class="ltt-stat-card__value">{{ number_format((int) ($totals['sessions'] ?? 0)) }}</div>
            <div class="ltt-stat-card__hint">Filtered results</div>
        </div>
        <div class="ltt-stat-card">
            <div class="ltt-stat-card__label">Male participants</div>
            <div class="ltt-stat-card__value">{{ number_format((int) ($totals['male'] ?? 0)) }}</div>
        </div>
        <div class="ltt-stat-card">
            <div class="ltt-stat-card__label">Female participants</div>
            <div class="ltt-stat-card__value">{{ number_format((int) ($totals['female'] ?? 0)) }}</div>
        </div>
        <div class="ltt-stat-card">
            <div class="ltt-stat-card__label">Total participants</div>
            <div class="ltt-stat-card__value">{{ number_format((int) ($totals['participants'] ?? 0)) }}</div>
        </div>
        <div class="ltt-stat-card">
            <div class="ltt-stat-card__label">Sessions with photos</div>
            <div class="ltt-stat-card__value">{{ number_format((int) ($totals['with_photos'] ?? 0)) }}</div>
        </div>
    </div>

    <div class="ltt-card">
        <h3 class="ltt-card__head">Filters</h3>
        <form method="get" class="ltt-filters">
            <div class="ltt-filter-field"><label>Search</label><input type="text" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Title, district, block…"></div>
            <div class="ltt-filter-field">
                <label>Agency</label>
                <select name="agency">
                    <option value="">All agencies</option>
                    @foreach (($agencyTypes ?? []) as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['agency'] ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="ltt-filter-field">
                <label>Year</label>
                <select name="event_year">
                    <option value="">All</option>
                    @foreach (($yearOptions ?? []) as $y)
                        <option value="{{ $y }}" @selected((string) ($filters['event_year'] ?? '') === (string) $y)>{{ $y }}</option>
                    @endforeach
                </select>
            </div>
            <div class="ltt-filter-field">
                <label>Month</label>
                <select name="event_month">
                    <option value="">All</option>
                    @foreach (($monthOptions ?? []) as $mv => $ml)
                        <option value="{{ $mv }}" @selected((string) ($filters['event_month'] ?? '') === (string) $mv)>{{ $ml }}</option>
                    @endforeach
                </select>
            </div>
            <div class="ltt-filter-field"><label>From</label><input type="date" name="from" value="{{ $filters['from'] ?? '' }}"></div>
            <div class="ltt-filter-field"><label>To</label><input type="date" name="to" value="{{ $filters['to'] ?? '' }}"></div>
            <div class="ltt-filter-actions">
                <button type="submit" class="ltt-btn">Apply filters</button>
                @if (!empty($exportRoute))
                    <a href="{{ route($exportRoute, request()->query()) }}" class="ltt-btn--secondary">Export CSV</a>
                @endif
            </div>
        </form>
    </div>

    @php
        $attachRoute = match ($currentRole ?? auth()->user()->role) {
            'state_admin' => 'admin.lakhpati-technical-trainings.attachment',
            'state_staff' => 'spoc.lakhpati-technical-trainings.attachment',
            default => 'staff.lakhpati-technical-trainings.attachment',
        };
        $showRoutePrefix = match ($currentRole ?? auth()->user()->role) {
            'state_admin' => 'admin.lakhpati-technical-trainings.show',
            'state_staff' => 'spoc.lakhpati-technical-trainings.show',
            default => 'staff.lakhpati-technical-trainings.show',
        };
    @endphp

    <div class="ltt-table-card">
        <table class="ltt-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Date</th>
                    <th>District</th>
                    <th>Block</th>
                    <th>Venue</th>
                    <th>Training title</th>
                    <th>Agency</th>
                    <th>Mode</th>
                    <th>M</th>
                    <th>F</th>
                    <th>Total</th>
                    <th>Photos</th>
                    <th>Attendance</th>
                    <th>Entered by</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            @forelse ($rows as $row)
                @php
                    $n = ($rows instanceof \Illuminate\Contracts\Pagination\Paginator)
                        ? ((int) ($rows->firstItem() ?? 1) + $loop->index)
                        : $loop->iteration;
                @endphp
                <tr>
                    <td>{{ $n }}</td>
                    <td>{{ $row->session_date?->format('d M Y') ?: '—' }}</td>
                    <td>{{ $row->district_name ?: ($row->district?->name ?? '—') }}</td>
                    <td>{{ $row->block ?: '—' }}</td>
                    <td><span class="ltt-venue">{{ \Illuminate\Support\Str::limit($row->area ?? '—', 40) }}</span></td>
                    <td>
                        <div class="ltt-title">{{ $row->session_title }}</div>
                        @if ($row->session_brief)
                            <div class="ltt-brief">{{ \Illuminate\Support\Str::limit($row->session_brief, 90) }}</div>
                        @endif
                    </td>
                    <td><span class="ltt-agency">{{ $row->agencyTypeLabel() }}</span></td>
                    <td>
                        <span class="ltt-pill {{ ($row->workshop_mode ?? '') === 'virtual' ? 'ltt-pill--virtual' : 'ltt-pill--physical' }}">
                            {{ $row->formattedWorkshopMode() }}
                        </span>
                    </td>
                    <td>{{ number_format((int) $row->male_participants) }}</td>
                    <td>{{ number_format((int) $row->female_participants) }}</td>
                    <td>{{ number_format($row->totalParticipantCount()) }}</td>
                    <td>
                        @include('staff.district-workshop-sessions.partials.dashboard-photos-cell', [
                            'row' => $row,
                            'attachmentRouteName' => $attachRoute,
                        ])
                    </td>
                    <td>
                        @include('staff.district-workshop-sessions.partials.dashboard-attendance-cell', [
                            'row' => $row,
                            'attachmentRouteName' => $attachRoute,
                        ])
                    </td>
                    <td>{{ $row->submitted_by_name }}</td>
                    <td>
                        <div class="ltt-row-actions">
                            <a class="ltt-btn--view" href="{{ route($showRoutePrefix, $row) }}">View</a>
                            @if (auth()->user()->role === 'district_staff' && (int) $row->submitted_by_user_id === (int) auth()->id())
                                <a class="ltt-btn--edit" href="{{ route('staff.lakhpati-technical-trainings.edit', $row) }}">Edit</a>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="15" class="ltt-empty">No sessions found for the current filters.</td></tr>
            @endforelse
            </tbody>
            @if (($rows instanceof \Countable ? count($rows) : 0) > 0)
            <tfoot>
                <tr>
                    <td colspan="8"><strong>Totals (filtered)</strong></td>
                    <td>{{ number_format((int) ($totals['male'] ?? 0)) }}</td>
                    <td>{{ number_format((int) ($totals['female'] ?? 0)) }}</td>
                    <td>{{ number_format((int) ($totals['participants'] ?? 0)) }}</td>
                    <td colspan="4"></td>
                </tr>
            </tfoot>
            @endif
        </table>
    </div>

    @if (!empty($isPaginated) && $rows instanceof \Illuminate\Contracts\Pagination\Paginator && $rows->hasPages())
        <div>{{ $rows->links() }}</div>
    @endif

    @include('staff.technical-trainings.partials.attendance-media-preview', [
        'mediaItems' => [],
        'attachmentRoute' => $attachRoute,
        'record' => \App\Models\LakhpatiTechnicalTraining::make(),
        'showEmptyMessage' => false,
    ])
</div>
@endsection
