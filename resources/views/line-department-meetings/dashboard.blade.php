@extends('layouts.admin')

@section('title', \App\Models\LineDepartmentMeeting::MODULE_LABEL)
@section('heading', \App\Models\LineDepartmentMeeting::MODULE_LABEL)

@push('styles')
@include('stakeholder-consultation-workshops.partials.list-styles')
@endpush

@section('content')
@php
    $prefix = match ($currentRole ?? '') {
        'state_admin' => 'admin.',
        'hub_admin' => 'hub.',
        'district_staff' => 'staff.',
        default => 'spoc.',
    };
    $showRoute = $prefix.'line-department-meetings.show';
    $createRoute = $prefix.'line-department-meetings.create';
    $editRoute = $prefix.'line-department-meetings.edit';
    $destroyRoute = $prefix.'line-department-meetings.destroy';
@endphp
<div class="ldm-list-shell">
    @if (!empty($migrationMissing))<div class="ldm-list-alert ldm-list-alert--warning">Run <code>php artisan migrate</code>.</div>@endif
    @if (session('status'))<div class="ldm-list-alert ldm-list-alert--success">{{ session('status') }}</div>@endif

    <div class="ldm-list-hero">
        <div>
            <h2 class="ldm-list-hero__title">{{ \App\Models\LineDepartmentMeeting::MODULE_LABEL }}</h2>
            <p class="ldm-list-hero__sub">Each saved meeting counts toward MIS 12.2.</p>
        </div>
        @if (!empty($canSubmit))
            <a href="{{ route($createRoute) }}" class="ldm-list-btn">New meeting</a>
        @endif
    </div>

    <div class="ldm-list-stat-grid">
        <div class="ldm-list-stat"><span class="ldm-list-stat__label">Meetings</span><span class="ldm-list-stat__value">{{ number_format($totals['meetings'] ?? 0) }}</span></div>
    </div>

    <div class="ldm-list-card">
        <form method="get" class="ldm-list-filters">
            <div class="ldm-list-filter-field"><label>Search</label><input type="text" name="q" value="{{ $filters['q'] ?? '' }}"></div>
            <div class="ldm-list-filter-field"><label>From</label><input type="date" name="from" value="{{ $filters['from'] ?? '' }}"></div>
            <div class="ldm-list-filter-field"><label>To</label><input type="date" name="to" value="{{ $filters['to'] ?? '' }}"></div>
            <div class="ldm-list-filter-field">
                <label>Level</label>
                <select name="level">
                    <option value="">All</option>
                    @foreach ($meetingLevels as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['level'] ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="ldm-list-btn ldm-list-btn--secondary">Filter</button>
            @if (!empty($exportRoute))<a href="{{ route($exportRoute, request()->query()) }}" class="ldm-list-btn ldm-list-btn--secondary">Export CSV</a>@endif
        </form>

        <div class="ldm-list-table-wrap">
            <table class="ldm-list-table">
                <thead><tr><th>Date</th><th>Level</th><th>Department</th><th>Official</th><th>District</th><th>Entered by</th>
                @if (\App\Models\LineDepartmentMeeting::supportsMisFieldWorkflow())
                <th>Approval status</th><th>Assigned SPOC</th>
                @endif
                <th></th></tr></thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td>{{ $row->meeting_date?->format('d M Y') }}</td>
                            <td>{{ $row->meetingLevelLabel() }}</td>
                            <td>{{ $row->department_name }}</td>
                            <td>{{ $row->official_name }}</td>
                            <td>{{ $row->district_name ?: '—' }}</td>
                            <td>{{ $row->submitted_by_name }}</td>
                            @include('partials.mis-field-workflow-dashboard-cells', ['row' => $row])
                            <td>
                                <a href="{{ route($showRoute, $row) }}">View</a>
                                @include('partials.mis-field-workflow-row-actions', [
                                    'row' => $row,
                                    'editRoute' => $editRoute,
                                    'destroyRoute' => $destroyRoute,
                                    'editClass' => '',
                                    'withdrawClass' => '',
                                ])
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="ldm-list-empty">No meetings logged yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if (!empty($isPaginated) && method_exists($rows, 'links')){{ $rows->links() }}@endif
    </div>
</div>
@endsection
