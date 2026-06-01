@extends('layouts.admin')

@section('title', 'Phase 1 data (FY 2024–25)')
@section('heading', 'Phase 1 data (FY 2024–25)')

@push('styles')
    @include('partials.phase1-legacy.styles')
@endpush

@section('content')
<div class="p1l-page">
    @if ($noDistrict ?? false)
        <div class="p1l-alert p1l-alert--warn">
            Your user account has no district assignment. Ask state admin to map your district first.
        </div>
    @elseif ($phase1Unavailable ?? false)
        <div class="p1l-alert p1l-alert--err">
            Phase 1 database is not configured. Set <code>PHASE1_DB_DATABASE</code> and related <code>PHASE1_DB_*</code> values in <code>.env</code>.
        </div>
    @elseif ($phase1MissingTables ?? false)
        <div class="p1l-alert p1l-alert--err">
            Phase 1 connection works, but required table <code>tblapplication</code> is missing.
        </div>
    @else
        <div class="p1l-hero">
            <div>
                <h2 class="p1l-hero__title">Applications — {{ $districtName ?? $staff->district?->name ?? '—' }}</h2>
                <p class="p1l-hero__sub">
                    Legacy Phase 1 scoped to your district via <code>FatherName</code>.
                    Same filters as state admin (except district, which is fixed to your assignment).
                </p>
            </div>
            <div class="p1l-hero__badges">
                <span class="p1l-badge p1l-badge--fy">FY 2024–25</span>
                <span class="p1l-badge p1l-badge--district">{{ $districtName ?? $staff->district?->name ?? '—' }}</span>
                @if ($staff->hub?->name)
                    <span class="p1l-badge p1l-badge--hub">{{ $staff->hub->name }}</span>
                @endif
                <span class="p1l-badge p1l-badge--hub">{{ number_format($scopeCounts['onboarded'] ?? 0) }} onboarded</span>
            </div>
        </div>

        @include('partials.phase1-legacy.stats', [
            'scopeCounts' => $scopeCounts ?? [],
            'rows' => $rows,
            'totalLabel' => 'In your district',
            'totalHint' => 'All filters except onboard status',
            'countDistrictParam' => false,
        ])

        @include('partials.phase1-legacy.filters', [
            'showDistrictFilter' => false,
            'filterOptions' => $filterOptions ?? [],
            'formAction' => route('staff.phase1-data'),
            'clearUrl' => route('staff.phase1-data'),
        ])

        @include('partials.phase1-legacy.table', [
            'rows' => $rows,
            'showDistrict' => false,
            'filterActive' => \App\Services\LegacyPhase1\LegacyPhase1ListQuery::hasActiveFilters(request(), false),
            'emptyMessage' => 'No Phase 1 records found for your district with the current filters.',
        ])
    @endif
</div>
@endsection
