@extends('layouts.admin')

@section('title', 'Training Package Dashboard')
@section('heading', 'Training Package Dashboard')

@push('styles')
<style>
    .tp-shell { display:flex; flex-direction:column; gap:1.25rem; }
    .tp-alert { border-radius:12px; padding:0.85rem 1rem; font-size:0.88rem; }
    .tp-alert--warning { background:#fffbeb; border:1px solid #fde68a; color:#92400e; }
    .tp-alert--success { background:#f0fdf4; border:1px solid #86efac; color:#166534; }
    .tp-stat-grid { display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:0.85rem; }
    .tp-stat-card { background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:0.9rem 1rem; }
    .tp-stat-card__label { font-size:0.74rem; color:#64748b; text-transform:uppercase; letter-spacing:0.06em; font-weight:700; }
    .tp-stat-card__value { margin-top:0.3rem; font-size:1.25rem; font-weight:800; color:#0f172a; }
    .tp-card { background:#fff; border:1px solid #e2e8f0; border-radius:14px; padding:1.25rem 1.35rem; }
    .tp-filters { display:grid; grid-template-columns:repeat(auto-fit, minmax(170px, 1fr)); gap:0.85rem; align-items:end; }
    .tp-filter-field { display:flex; flex-direction:column; gap:0.35rem; min-width:0; }
    .tp-filter-field label { font-size:0.78rem; font-weight:700; color:#0f172a; }
    .tp-filter-field input,
    .tp-filter-field select { width:100%; box-sizing:border-box; border:1px solid #cbd5e1; border-radius:8px; padding:0.55rem 0.65rem; font-size:0.88rem; }
    .tp-filter-actions { display:flex; flex-wrap:wrap; gap:0.55rem; align-items:center; }
    .tp-btn { border:none; border-radius:8px; background:#4f46e5; color:#fff; padding:0.58rem 0.9rem; font-weight:700; cursor:pointer; font-size:0.88rem; }
    .tp-btn--secondary { display:inline-flex; align-items:center; padding:0.58rem 0.9rem; border-radius:8px; border:1px solid #cbd5e1; background:#fff; color:#334155; text-decoration:none; font-size:0.88rem; font-weight:600; }
    .tp-table-card { background:#fff; border:1px solid #e2e8f0; border-radius:14px; overflow:auto; }
    .tp-table { width:100%; border-collapse:collapse; font-size:0.84rem; }
    .tp-table thead tr { background:#f8fafc; }
    .tp-table th,
    .tp-table td { text-align:left; padding:0.7rem 0.75rem; border-bottom:1px solid #e2e8f0; vertical-align:top; }
    .tp-table tbody tr:last-child td { border-bottom:none; }
    .tp-pill { display:inline-block; background:#eef2ff; color:#3730a3; border-radius:999px; padding:0.15rem 0.52rem; font-weight:700; }
    .tp-row-actions { display:flex; gap:0.55rem; align-items:center; flex-wrap:wrap; }
    .tp-btn--view {
        display:inline-flex;
        align-items:center;
        justify-content:center;
        padding:0.42rem 0.8rem;
        border-radius:8px;
        border:1px solid #c7d2fe;
        background:#eef2ff;
        color:#3730a3;
        text-decoration:none;
        font-size:0.8rem;
        font-weight:800;
        box-shadow:0 4px 12px rgba(79,70,229,0.12);
    }
    .tp-btn--view:hover { background:#e0e7ff; color:#312e81; }
    .tp-btn--edit {
        display:inline-flex;
        align-items:center;
        justify-content:center;
        padding:0.42rem 0.8rem;
        border-radius:8px;
        border:1px solid #cbd5e1;
        background:#fff;
        color:#334155;
        text-decoration:none;
        font-size:0.8rem;
        font-weight:700;
    }
    .tp-btn--edit:hover { background:#f8fafc; }
    .tp-empty { padding:1rem; color:#64748b; }
</style>
@endpush

@section('content')
<div class="tp-shell">
    @if (!empty($migrationMissing))
        <div class="tp-alert tp-alert--warning">
            <strong>Training packages table not found.</strong> Run <code>php artisan migrate</code> first.
        </div>
    @endif

    @if (session('status'))
        <div class="tp-alert tp-alert--success">
            {{ session('status') }}
        </div>
    @endif

    @php
        $totalBatches = (!empty($isPaginated) && $rows instanceof \Illuminate\Contracts\Pagination\Paginator)
            ? (int) (($rows instanceof \Illuminate\Pagination\LengthAwarePaginator) ? $rows->total() : $rows->count())
            : (int) (is_countable($rows) ? count($rows) : 0);
    @endphp

    <div class="tp-stat-grid">
        <div class="tp-stat-card">
            <div class="tp-stat-card__label">Total Batches</div>
            <div class="tp-stat-card__value">{{ number_format($totalBatches) }}</div>
        </div>
    </div>

    <div class="tp-card">
        <form method="get" class="tp-filters">
            <div class="tp-filter-field">
                <label>Search</label>
                <input type="text" name="q" value="{{ $filters['q'] ?? '' }}">
            </div>
            <div class="tp-filter-field">
                <label>Year</label>
                <select name="event_year">
                    <option value="">All</option>
                    @foreach (($yearOptions ?? []) as $yearOption)
                        <option value="{{ $yearOption }}" @selected((string) ($filters['event_year'] ?? '') === (string) $yearOption)>{{ $yearOption }}</option>
                    @endforeach
                </select>
            </div>
            <div class="tp-filter-field">
                <label>Month</label>
                <select name="event_month">
                    <option value="">All</option>
                    @foreach (($monthOptions ?? []) as $monthValue => $monthLabel)
                        <option value="{{ $monthValue }}" @selected((string) ($filters['event_month'] ?? '') === (string) $monthValue)>{{ $monthLabel }}</option>
                    @endforeach
                </select>
            </div>
            <div class="tp-filter-field">
                <label>Package</label>
                <select name="training_package">
                    <option value="">All</option>
                    <option value="t1" @selected(($filters['training_package'] ?? '')==='t1')>T1</option>
                    <option value="t2" @selected(($filters['training_package'] ?? '')==='t2')>T2</option>
                    <option value="t3" @selected(($filters['training_package'] ?? '')==='t3')>T3</option>
                    <option value="t4" @selected(($filters['training_package'] ?? '')==='t4')>T4</option>
                </select>
            </div>
            <div class="tp-filter-field">
                <label>From</label>
                <input type="date" name="from" value="{{ $filters['from'] ?? '' }}">
            </div>
            <div class="tp-filter-field">
                <label>To</label>
                <input type="date" name="to" value="{{ $filters['to'] ?? '' }}">
            </div>
            <div class="tp-filter-actions">
                <button type="submit" class="tp-btn">Filter</button>
                @if (auth()->user()->role === 'district_staff')
                    <a href="{{ route('staff.training-packages.create') }}" class="tp-btn--secondary">New entry</a>
                @endif
            </div>
        </form>
    </div>

    <div class="tp-table-card">
        <table class="tp-table">
            <thead>
            <tr>
                <th>Sr. No.</th>
                <th>Date of Session</th>
                <th>Session Taken By</th>
                <th>District</th>
                <th>Session Name</th>
                <th>Training Modules</th>
                <th>Attendees</th>
                <th>Actions</th>
            </tr>
            </thead>
            <tbody>
            @forelse ($rows as $row)
                @php
                    $rowNumber = (!empty($isPaginated) && $rows instanceof \Illuminate\Contracts\Pagination\Paginator)
                        ? ((int) ($rows->firstItem() ?? 1) + $loop->index)
                        : ($loop->iteration);
                    $moduleList = (array) ($row->training_packages ?? [$row->training_package]);
                    $moduleLabel = strtoupper(implode(', ', array_values(array_filter($moduleList))));
                    $sessionName = $row->monthSession?->session_name ?: '—';
                    $attendeeCount = is_array($row->selected_incubatee_ids) && count($row->selected_incubatee_ids) > 0
                        ? count($row->selected_incubatee_ids)
                        : count((array) $row->selected_incubatees_snapshot);
                @endphp
                <tr>
                    <td>{{ $rowNumber }}</td>
                    <td>{{ $row->event_date?->format('d M Y') ?: 'NA' }}</td>
                    <td>{{ $row->submitted_by_name }}</td>
                    <td>{{ $row->district_name ?: ($row->district?->name ?? 'NA') }}</td>
                    <td>
                        {{ $sessionName }}
                        @if ($row->monthSession?->is_extra)
                            <span class="tp-pill">Extra</span>
                        @endif
                    </td>
                    <td><span class="tp-pill">{{ $moduleLabel !== '' ? $moduleLabel : 'NA' }}</span></td>
                    <td>{{ number_format($attendeeCount) }}</td>
                    <td>
                        <div class="tp-row-actions">
                            <a class="tp-btn--view" href="{{ match ($currentRole ?? auth()->user()->role) {
                                'state_admin' => route('admin.training-packages.show', $row),
                                'state_staff' => route('spoc.training-packages.show', $row),
                                default => route('staff.training-packages.show', $row),
                            } }}">View</a>
                            @if (auth()->user()->role === 'district_staff' && (int) $row->submitted_by_user_id === (int) auth()->id())
                                <a class="tp-btn--edit" href="{{ route('staff.training-packages.edit', $row) }}">Edit</a>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="tp-empty">No entries found.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>

    @if (!empty($isPaginated) && $rows instanceof \Illuminate\Contracts\Pagination\Paginator && $rows->hasPages())
        <div>{{ $rows->links() }}</div>
    @endif
</div>
@endsection
