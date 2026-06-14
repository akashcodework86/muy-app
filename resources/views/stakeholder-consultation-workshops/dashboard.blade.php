@extends('layouts.admin')

@section('title', \App\Models\StakeholderConsultationWorkshop::MODULE_LABEL)
@section('heading', \App\Models\StakeholderConsultationWorkshop::MODULE_LABEL)

@push('styles')
@include('stakeholder-consultation-workshops.partials.list-styles')
@endpush

@section('content')
@php
    $showRoute = match ($currentRole ?? '') {
        'state_admin' => 'admin.stakeholder-consultation-workshops.show',
        default => 'spoc.stakeholder-consultation-workshops.show',
    };
    $createRoute = 'spoc.stakeholder-consultation-workshops.create';
    $editRoute = 'spoc.stakeholder-consultation-workshops.edit';
    $destroyRoute = 'spoc.stakeholder-consultation-workshops.destroy';
@endphp
<div class="scw-list-shell">
    @if (!empty($migrationMissing))
        <div class="scw-list-alert scw-list-alert--warning">Run <code>php artisan migrate</code>.</div>
    @endif
    @if (session('status'))<div class="scw-list-alert scw-list-alert--success">{{ session('status') }}</div>@endif

    <div class="scw-list-hero">
        <div>
            <h2 class="scw-list-hero__title">{{ \App\Models\StakeholderConsultationWorkshop::MODULE_LABEL }}</h2>
            <p class="scw-list-hero__sub">Each saved workshop counts toward MIS 12.1.</p>
        </div>
        @if (!empty($canSubmit))
            <a href="{{ route($createRoute) }}" class="scw-list-btn">New workshop</a>
        @endif
    </div>

    <div class="scw-list-stat-grid">
        <div class="scw-list-stat"><span class="scw-list-stat__label">Workshops</span><span class="scw-list-stat__value">{{ number_format($totals['workshops'] ?? 0) }}</span></div>
        <div class="scw-list-stat"><span class="scw-list-stat__label">Participants</span><span class="scw-list-stat__value">{{ number_format($totals['participants'] ?? 0) }}</span></div>
    </div>

    <div class="scw-list-card">
        <form method="get" class="scw-list-filters">
            <div class="scw-list-filter-field"><label>Search</label><input type="text" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Title, venue…"></div>
            <div class="scw-list-filter-field"><label>From</label><input type="date" name="from" value="{{ $filters['from'] ?? '' }}"></div>
            <div class="scw-list-filter-field"><label>To</label><input type="date" name="to" value="{{ $filters['to'] ?? '' }}"></div>
            <button type="submit" class="scw-list-btn scw-list-btn--secondary">Filter</button>
            @if (!empty($exportRoute))<a href="{{ route($exportRoute, request()->query()) }}" class="scw-list-btn scw-list-btn--secondary">Export CSV</a>@endif
        </form>

        <div class="scw-list-table-wrap">
            <table class="scw-list-table">
                <thead>
                    <tr>
                        <th>Date</th><th>Title</th><th>Level</th><th>Departments</th><th>Participants</th><th>Entered by</th><th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td>{{ $row->workshop_date?->format('d M Y') }}</td>
                            <td><span class="scw-list-title">{{ $row->workshop_title }}</span></td>
                            <td>{{ $row->organizingLevelLabel() }}</td>
                            <td>{{ $row->primaryDepartmentsLabel() }}</td>
                            <td>{{ number_format((int) $row->total_participants) }}</td>
                            <td>{{ $row->submitted_by_name }}</td>
                            <td><a href="{{ route($showRoute, $row) }}">View</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="scw-list-empty">No workshops logged yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if (!empty($isPaginated) && method_exists($rows, 'links')){{ $rows->links() }}@endif
    </div>
</div>
@endsection
