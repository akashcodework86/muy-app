@extends('layouts.admin')

@section('title', \App\Models\StakeholderCapacityBuildingSession::MODULE_LABEL)
@section('heading', \App\Models\StakeholderCapacityBuildingSession::MODULE_LABEL)

@push('styles')
<style>
    .cbs-shell { display:flex; flex-direction:column; gap:1.25rem; }
    .cbs-alert { border-radius:12px; padding:0.85rem 1rem; font-size:0.88rem; }
    .cbs-alert--warning { background:#fffbeb; border:1px solid #fde68a; color:#92400e; }
    .cbs-alert--success { background:#f0fdf4; border:1px solid #86efac; color:#166534; }
    .cbs-hero {
        display:flex; flex-wrap:wrap; align-items:flex-start; justify-content:space-between; gap:1rem;
        padding:1.1rem 1.25rem; border-radius:16px;
        background:linear-gradient(135deg, #eef2ff 0%, #f8fafc 55%, #ecfdf5 100%);
        border:1px solid #e2e8f0;
    }
    .cbs-hero__title { margin:0; font-size:1rem; font-weight:800; color:#0f172a; line-height:1.45; max-width:42rem; }
    .cbs-hero__sub { margin:0.35rem 0 0; font-size:0.84rem; color:#64748b; }
    .cbs-stat-grid { display:grid; grid-template-columns:repeat(auto-fit, minmax(160px, 1fr)); gap:0.85rem; }
    .cbs-stat-card { background:#fff; border:1px solid #e2e8f0; border-radius:14px; padding:0.95rem 1.05rem; }
    .cbs-stat-card__label { font-size:0.72rem; color:#64748b; text-transform:uppercase; letter-spacing:0.07em; font-weight:700; }
    .cbs-stat-card__value { margin-top:0.35rem; font-size:1.35rem; font-weight:800; color:#0f172a; }
    .cbs-card { background:#fff; border:1px solid #e2e8f0; border-radius:14px; padding:1.2rem 1.3rem; }
    .cbs-filters { display:grid; grid-template-columns:repeat(auto-fit, minmax(150px, 1fr)); gap:0.85rem; align-items:end; }
    .cbs-filter-field { display:flex; flex-direction:column; gap:0.35rem; }
    .cbs-filter-field label { font-size:0.78rem; font-weight:700; color:#0f172a; }
    .cbs-filter-field input, .cbs-filter-field select {
        width:100%; box-sizing:border-box; border:1px solid #cbd5e1; border-radius:8px;
        padding:0.55rem 0.65rem; font-size:0.88rem; background:#fff;
    }
    .cbs-btn { border:none; border-radius:8px; background:#4f46e5; color:#fff; padding:0.58rem 0.95rem; font-weight:700; cursor:pointer; font-size:0.88rem; text-decoration:none; display:inline-flex; }
    .cbs-btn--secondary { background:#fff; color:#334155; border:1px solid #cbd5e1; }
    .cbs-table-card { background:#fff; border:1px solid #e2e8f0; border-radius:14px; overflow:auto; }
    .cbs-table { width:100%; border-collapse:collapse; font-size:0.84rem; min-width:900px; }
    .cbs-table th { text-align:left; padding:0.72rem 0.75rem; border-bottom:1px solid #e2e8f0; font-size:0.68rem; font-weight:700; text-transform:uppercase; color:#64748b; }
    .cbs-table td { padding:0.72rem 0.75rem; border-bottom:1px solid #e2e8f0; vertical-align:top; }
    .cbs-pill { display:inline-flex; padding:0.2rem 0.55rem; border-radius:999px; font-size:0.72rem; font-weight:800; }
    .cbs-pill--virtual { background:#e0f2fe; color:#0369a1; }
    .cbs-pill--physical { background:#ecfdf5; color:#047857; }
    .cbs-empty { padding:1.25rem; color:#64748b; text-align:center; }
    .cbs-title { font-weight:800; color:#0f172a; line-height:1.35; max-width:16rem; }
    .ees-dash-photos { display:flex; flex-wrap:wrap; gap:0.35rem; align-items:center; max-width:180px; }
    .ees-dash-photo { width:40px; height:40px; object-fit:cover; border-radius:6px; border:1px solid #e2e8f0; cursor:pointer; background:#f8fafc; }
    .ees-dash-photo-more {
        display:inline-flex; align-items:center; justify-content:center;
        min-width:40px; height:40px; padding:0 0.35rem; border-radius:6px;
        background:#f1f5f9; border:1px solid #e2e8f0;
        font-size:0.72rem; font-weight:800; color:#475569;
    }
    .ees-dash-photos-empty { font-size:0.76rem; color:#94a3b8; }
</style>
@endpush

@section('content')
@php
    $showRoute = match ($currentRole ?? '') {
        'state_admin' => 'admin.capacity-building-stakeholders.show',
        default => 'spoc.capacity-building-stakeholders.show',
    };
    $attachRoute = match ($currentRole ?? '') {
        'state_admin' => 'admin.capacity-building-stakeholders.attachment',
        default => 'spoc.capacity-building-stakeholders.attachment',
    };
    $createRoute = 'spoc.capacity-building-stakeholders.create';
    $editRoute = 'spoc.capacity-building-stakeholders.edit';
    $destroyRoute = 'spoc.capacity-building-stakeholders.destroy';
@endphp
<div class="cbs-shell">
    @if (!empty($migrationMissing))
        <div class="cbs-alert cbs-alert--warning"><strong>Table not found.</strong> Run <code>php artisan migrate</code>.</div>
    @endif

    @if (session('status'))
        <div class="cbs-alert cbs-alert--success">{{ session('status') }}</div>
    @endif

    <div class="cbs-hero">
        <div>
            <h2 class="cbs-hero__title">{{ \App\Models\StakeholderCapacityBuildingSession::MODULE_LABEL }}</h2>
            <p class="cbs-hero__sub">Sessions count toward MIS indicator <strong>3.4</strong>.</p>
        </div>
        @if (!empty($canSubmit))
            <a class="cbs-btn" href="{{ route($createRoute) }}">New session</a>
        @endif
    </div>

    <div class="cbs-stat-grid">
        <div class="cbs-stat-card"><div class="cbs-stat-card__label">Sessions</div><div class="cbs-stat-card__value">{{ number_format($totals['sessions'] ?? 0) }}</div></div>
        <div class="cbs-stat-card"><div class="cbs-stat-card__label">Staff trained</div><div class="cbs-stat-card__value">{{ number_format($totals['staff'] ?? 0) }}</div></div>
        <div class="cbs-stat-card"><div class="cbs-stat-card__label">With photos</div><div class="cbs-stat-card__value">{{ number_format($totals['with_photos'] ?? 0) }}</div></div>
    </div>

    <div class="cbs-card">
        <form method="get" class="cbs-filters">
            <div class="cbs-filter-field">
                <label>Search</label>
                <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Title, venue…">
            </div>
            <div class="cbs-filter-field">
                <label>Stakeholder</label>
                <select name="stakeholder">
                    <option value="">All</option>
                    @foreach ($stakeholderTypes ?? [] as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['stakeholder'] ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="cbs-filter-field"><label>From</label><input type="date" name="from" value="{{ $filters['from'] ?? '' }}"></div>
            <div class="cbs-filter-field"><label>To</label><input type="date" name="to" value="{{ $filters['to'] ?? '' }}"></div>
            <div class="cbs-filter-field" style="display:flex;gap:0.5rem;flex-wrap:wrap;">
                <button class="cbs-btn" type="submit">Filter</button>
                @if (!empty($exportRoute) && Route::has($exportRoute))
                    <a class="cbs-btn cbs-btn--secondary" href="{{ route($exportRoute, request()->query()) }}">Export CSV</a>
                @endif
            </div>
        </form>
    </div>

    <div class="cbs-table-card">
        <table class="cbs-table">
            <thead>
                <tr>
                    <th>S.No.</th>
                    <th>Date</th>
                    <th>Training title</th>
                    <th>Stakeholder</th>
                    <th>Mode</th>
                    <th>Venue</th>
                    <th>Staff</th>
                    <th>Photos</th>
                    <th>Entered by</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            @forelse ($rows as $row)
                @php
                    $serial = ($rows instanceof \Illuminate\Contracts\Pagination\Paginator)
                        ? ((int) ($rows->firstItem() ?? 1) + $loop->index)
                        : $loop->iteration;
                @endphp
                <tr>
                    <td>{{ $serial }}</td>
                    <td>{{ $row->session_date?->format('d M Y') ?: '—' }}</td>
                    <td><span class="cbs-title">{{ $row->session_title }}</span></td>
                    <td>{{ $row->stakeholderTypeLabel() }}</td>
                    <td>
                        <span class="cbs-pill cbs-pill--{{ $row->workshop_mode === 'virtual' ? 'virtual' : 'physical' }}">
                            {{ $row->formattedWorkshopMode() }}
                        </span>
                    </td>
                    <td>{{ $row->venue }}</td>
                    <td>{{ number_format((int) $row->staff_trained_total) }}</td>
                    <td>
                        @include('staff.district-workshop-sessions.partials.dashboard-photos-cell', [
                            'row' => $row,
                            'attachmentRouteName' => $attachRoute,
                        ])
                    </td>
                    <td>{{ $row->submitted_by_name }}</td>
                    <td style="white-space:nowrap;">
                        <a class="cbs-btn cbs-btn--secondary" style="padding:0.35rem 0.65rem;font-size:0.78rem;" href="{{ route($showRoute, $row) }}">View</a>
                        @if (\App\Support\CapacityBuildingStakeholdersAccess::canEdit(auth()->user(), $row))
                            <a class="cbs-btn cbs-btn--secondary" style="padding:0.35rem 0.65rem;font-size:0.78rem;margin-left:0.25rem;" href="{{ route($editRoute, $row) }}">Edit</a>
                            <form
                                method="post"
                                action="{{ route($destroyRoute, $row) }}"
                                style="display:inline;margin-left:0.25rem;"
                                onsubmit="return confirm('Delete this session permanently?');"
                            >
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="cbs-btn cbs-btn--secondary" style="padding:0.35rem 0.65rem;font-size:0.78rem;color:#b91c1c;border-color:#fecaca;">Delete</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="10" class="cbs-empty">No sessions yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    @if (!empty($isPaginated) && method_exists($rows, 'links'))
        <div>{{ $rows->links() }}</div>
    @endif

    @include('staff.technical-trainings.partials.attendance-media-preview', [
        'mediaItems' => [],
        'attachmentRoute' => $attachRoute,
        'record' => \App\Models\StakeholderCapacityBuildingSession::make(),
        'showEmptyMessage' => false,
    ])
</div>
@endsection
