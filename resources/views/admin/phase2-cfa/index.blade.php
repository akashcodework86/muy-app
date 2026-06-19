@extends('layouts.admin')

@section('title', 'CFA — Phase 2 legacy')
@section('heading', 'CFA — Phase 2 legacy (FY 2025–26)')

@push('styles')
    @include('partials.phase1-legacy.styles')
@endpush

@section('content')
<div class="p1l-page">
    <div class="p1l-banner">
        <strong>Read-only.</strong> Source: <code>rbiphase2.rbi_applications</code> + <code>rbi_applicant_details</code>.
        Onboard = row in <code>rbi_onboarded_applicants</code> with a non-empty <code>status</code>.
        FY window uses <code>submission_date</code> (same as Phase 2 targets).
    </div>

    @if ($legacyUnavailable ?? false)
        <div class="p1l-alert p1l-alert--err">
            Legacy database is not configured. Set <code>LEGACY_DB_DATABASE</code> in <code>.env</code>.
        </div>
    @elseif ($legacyMissingTables ?? false)
        <div class="p1l-alert p1l-alert--err">
            Required legacy tables were not found (<code>rbi_applications</code>, <code>rbi_applicant_details</code>).
        </div>
    @elseif ($fiscalYears->isEmpty())
        <div class="p1l-alert p1l-alert--warn">No fiscal year configured.</div>
    @else
        <div class="p1l-hero">
            <div>
                <h2 class="p1l-hero__title">Phase 2 applications ({{ $fiscalYear?->name ?? 'FY' }})</h2>
                <p class="p1l-hero__sub">
                    @if ($fiscalYear)
                        Window: {{ $fiscalYear->starts_on?->format('d M Y') }} — {{ $fiscalYear->ends_on?->format('d M Y') }}.
                    @endif
                    Filter by district, category, stage, gender, onboard, or search.
                </p>
            </div>
            <div class="p1l-hero__badges">
                <span class="p1l-badge p1l-badge--fy">{{ $fiscalYear?->name ?? 'FY 2025–26' }}</span>
                <span class="p1l-badge p1l-badge--district">{{ number_format($scopeCounts['total'] ?? 0) }} in scope</span>
                <span class="p1l-badge p1l-badge--hub">{{ number_format($scopeCounts['onboarded'] ?? 0) }} onboarded</span>
            </div>
        </div>

        @include('partials.phase1-legacy.stats', [
            'scopeCounts' => $scopeCounts ?? [],
            'rows' => $rows,
            'totalLabel' => 'In FY scope',
            'totalHint' => 'All filters except onboard status',
            'countDistrictParam' => true,
        ])

        <div class="p1l-filters" style="margin-bottom:0.75rem;">
            <form method="get" action="{{ route('admin.phase2-cfa.index') }}" class="p1l-filters__row" style="align-items:flex-end;">
                <div class="p1l-field">
                    <label class="p1l-label" for="p2-fy">Fiscal year</label>
                    <select id="p2-fy" name="fiscal_year_id" class="p1l-select" onchange="this.form.submit()">
                        @foreach ($fiscalYears as $fy)
                            <option value="{{ $fy->id }}" @selected((int) $fiscalYearId === (int) $fy->id)>{{ $fy->name }}</option>
                        @endforeach
                    </select>
                </div>
            </form>
        </div>

        @include('partials.phase2-legacy.filters', [
            'showDistrictFilter' => true,
            'districts' => $districts ?? [],
            'filterOptions' => $filterOptions ?? [],
            'formAction' => route('admin.phase2-cfa.index'),
            'clearUrl' => route('admin.phase2-cfa.index', ['fiscal_year_id' => $fiscalYearId]),
            'preserveParams' => ['fiscal_year_id' => $fiscalYearId],
            'exportUrl' => route('admin.phase2-cfa.export', request()->query()),
        ])

        @include('partials.phase2-legacy.table-admin', [
            'rows' => $rows,
            'filterActive' => \App\Services\LegacyPhase2\LegacyPhase2ListQuery::hasActiveFilters(request(), true),
            'emptyMessage' => 'No Phase 2 applications found for the selected filters.',
        ])
    @endif
</div>
@endsection
