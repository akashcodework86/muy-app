@extends('layouts.admin')

@section('title', 'Phase 2 data (FY 2025–26)')
@section('heading', 'Phase 2 data (FY 2025–26)')

@push('styles')
    @include('partials.phase1-legacy.styles')
@endpush

@section('content')
<div class="p1l-page">
    @if ($noDistrict ?? false)
        <div class="p1l-alert p1l-alert--warn">
            Your user account has no district assignment. Ask state admin to map your district first.
        </div>
    @elseif ($legacyUnavailable ?? false)
        <div class="p1l-alert p1l-alert--err">
            Legacy database is not configured. Set <code>LEGACY_DB_DATABASE</code> in <code>.env</code>.
        </div>
    @elseif ($legacyMissingTables ?? false)
        <div class="p1l-alert p1l-alert--err">
            Required legacy Phase 2 tables are missing.
        </div>
    @else
        <div class="p1l-hero">
            <div>
                <h2 class="p1l-hero__title">Phase 2 — {{ $districtName ?? $staff->district?->name ?? '—' }}</h2>
                <p class="p1l-hero__sub">
                    Read-only <code>rbiphase2</code> data for your district. Onboarded = present in
                    <code>rbi_onboarded_applicants</code> with a status.
                </p>
            </div>
            <div class="p1l-hero__badges">
                <span class="p1l-badge p1l-badge--fy">FY 2025–26</span>
                <span class="p1l-badge p1l-badge--district">{{ $districtName ?? '—' }}</span>
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

        @include('partials.phase2-legacy.filters', [
            'showDistrictFilter' => false,
            'filterOptions' => $filterOptions ?? [],
            'formAction' => route('staff.phase2-data'),
            'clearUrl' => route('staff.phase2-data'),
            'exportUrl' => route('staff.phase2-data.export', request()->query()),
        ])

        @php
            $hasPaginator = method_exists($rows, 'currentPage');
            $total = $hasPaginator ? (int) $rows->total() : $rows->count();
            $perPage = $hasPaginator ? (int) $rows->perPage() : max(1, $total);
            $currentPage = $hasPaginator ? (int) $rows->currentPage() : 1;
            $serialFrom = $total > 0 ? (($currentPage - 1) * $perPage) + 1 : 0;
        @endphp

        <div class="p1l-toolbar">
            <p>
                @if ($total > 0)
                    Showing <strong>{{ number_format($serialFrom) }}–{{ number_format(min($currentPage * $perPage, $total)) }}</strong>
                    of <strong>{{ number_format($total) }}</strong>
                @else
                    No records
                @endif
            </p>
            @if ($hasPaginator && $rows->hasPages())
                <p class="p1l-muted">Page {{ $currentPage }} of {{ $rows->lastPage() }}</p>
            @endif
        </div>

        <div class="p1l-table-wrap">
            <table class="p1l-table" style="font-size:0.8rem;">
                <thead>
                    <tr>
                        <th style="text-align:right;">Sr.</th>
                        <th>App. no</th>
                        <th>Applicant</th>
                        <th>Contact</th>
                        <th>Location</th>
                        <th>Form / product</th>
                        <th>Onboard</th>
                        <th>Services</th>
                        <th>Profile</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td class="p1l-sr">{{ number_format($serialFrom + $loop->iteration - 1) }}</td>
                            <td class="p1l-appno">{{ $row['application_no'] }}</td>
                            <td>
                                <div class="p1l-name">{{ $row['applicant_name'] }}</div>
                                <div class="p1l-muted">{{ $row['app_category'] }} · {{ $row['form_stage'] }}</div>
                            </td>
                            <td>
                                <div>{{ $row['phone'] }}</div>
                                <div class="p1l-muted">{{ $row['gender'] }}</div>
                            </td>
                            <td>
                                <div>{{ $row['block'] }}</div>
                                <div class="p1l-muted">{{ $row['village'] }}</div>
                            </td>
                            <td>
                                <div>{{ $row['product'] }}</div>
                                <div class="p1l-muted">{{ $row['submission_date'] }}</div>
                            </td>
                            <td>
                                @if (($row['onboard_status'] ?? '') === 'onboarded')
                                    <span class="p1l-pill p1l-pill--onboard-yes">{{ $row['onboard_label'] ?? 'Onboarded' }}</span>
                                @else
                                    <span class="p1l-pill p1l-pill--onboard-no">{{ $row['onboard_label'] ?? 'Non onboarded' }}</span>
                                @endif
                            </td>
                            <td class="p1l-muted" style="max-width:12rem;">{{ $row['all_services'] }}</td>
                            <td>
                                @php $legacyId = (int) ($row['legacy_application_id'] ?? 0); @endphp
                                @if ($legacyId > 0)
                                    <a href="{{ route('staff.phase2-profile.show', ['legacy_application' => $legacyId]) }}" style="font-weight:700;color:#0369a1;">View</a>
                                @else
                                    —
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="p1l-empty">No Phase 2 records for your filters.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($hasPaginator && $rows->hasPages())
            <div class="p1l-pagination">{{ $rows->links() }}</div>
        @endif
    @endif
</div>
@endsection
