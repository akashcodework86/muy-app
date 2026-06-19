@extends('layouts.admin')

@section('title', 'CFA — Phase 1 legacy')
@section('heading', 'CFA — Phase 1 legacy (FY 2024–25)')

@push('styles')
    @include('partials.phase1-legacy.styles')
@endpush

@section('content')
<div class="p1l-page">
    <div class="p1l-banner">
        <strong>Read-only.</strong> Source: <code>ukrbiin_rbi.tblapplication</code>.
        District = <code>FatherName</code> · Village = <code>City</code> · Region = <code>hub</code> ·
        Onboard = <code>onboard</code> (<strong>yes</strong> = onboarded, empty = non onboarded).
    </div>

    @if ($phase1Unavailable ?? false)
        <div class="p1l-alert p1l-alert--err">
            Phase 1 database is not configured. Set <code>PHASE1_DB_DATABASE</code> (and optionally <code>PHASE1_DB_*</code>)
            in <code>.env</code> — see connection <code>legacy_phase1</code> in <code>config/database.php</code>.
        </div>
    @elseif ($phase1MissingTables ?? false)
        <div class="p1l-alert p1l-alert--err">
            Phase 1 connection works but required table <code>tblapplication</code> was not found.
        </div>
    @else
        <div class="p1l-hero">
            <div>
                <h2 class="p1l-hero__title">State-wide Phase 1 applications</h2>
                <p class="p1l-hero__sub">
                    Use filters below for district, region, onboard, loan status, gender, education, or search.
                    Serial numbers continue across pages.
                </p>
            </div>
            <div class="p1l-hero__badges">
                <span class="p1l-badge p1l-badge--fy">FY 2024–25</span>
                <span class="p1l-badge p1l-badge--district">{{ number_format($scopeCounts['total'] ?? 0) }} in scope</span>
                <span class="p1l-badge p1l-badge--hub">{{ number_format($scopeCounts['onboarded'] ?? 0) }} onboarded</span>
            </div>
        </div>

        @include('partials.phase1-legacy.stats', [
            'scopeCounts' => $scopeCounts ?? [],
            'rows' => $rows,
            'totalLabel' => 'In scope',
            'totalHint' => 'All filters except onboard status',
            'countDistrictParam' => true,
        ])

        @include('partials.phase1-legacy.filters', [
            'showDistrictFilter' => true,
            'districts' => $districts ?? [],
            'filterOptions' => $filterOptions ?? [],
            'formAction' => route('admin.phase1-cfa.index'),
            'clearUrl' => route('admin.phase1-cfa.index'),
            'exportUrl' => route('admin.phase1-cfa.export', request()->query()),
        ])

        @include('partials.phase1-legacy.table', [
            'rows' => $rows,
            'showDistrict' => true,
            'filterActive' => \App\Services\LegacyPhase1\LegacyPhase1ListQuery::hasActiveFilters(request(), true),
            'emptyMessage' => 'No Phase 1 applications found for the selected filters.',
        ])
    @endif
</div>
@endsection
