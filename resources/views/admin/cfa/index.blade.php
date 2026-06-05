@extends('layouts.admin')

@section('title', 'CFA applications (FY 2026–27)')
@section('heading', 'CFA applications (FY 2026–27)')

@push('styles')
    @include('partials.phase1-legacy.styles')
@endpush

@section('content')
<div class="p1l-page">
    <div class="p1l-banner">
        <strong>Live MIS — Phase 3.</strong> Source: <code>muy.cfa_submissions</code> (current FY scope).
        <strong>Onboarded</strong> = linked in an onboarding batch (<code>onboarding_batch_cfa</code>).
    </div>

    <div class="p1l-hero">
        <div>
            <h2 class="p1l-hero__title">CFA applications — FY 2026–27</h2>
            <p class="p1l-hero__sub">
                Default list matches the state dashboard Phase 3 scope. Application no. search spans full history.
            </p>
        </div>
        <div class="p1l-hero__badges">
            <span class="p1l-badge p1l-badge--fy">FY 2026–27</span>
            <span class="p1l-badge p1l-badge--district">{{ number_format($scopeCounts['total'] ?? 0) }} in scope</span>
            <span class="p1l-badge p1l-badge--hub">{{ number_format($fyOnboarding['total'] ?? $scopeCounts['onboarded'] ?? 0) }} onboarded (FY)</span>
        </div>
    </div>

    @include('partials.phase1-legacy.stats', [
        'scopeCounts' => $scopeCounts ?? [],
        'fyOnboarding' => $fyOnboarding ?? null,
        'rows' => $submissions,
        'totalLabel' => 'In scope',
        'totalHint' => 'Phase 3 FY filters (excl. onboard filter)',
        'countDistrictParam' => true,
        'usesCfaFilters' => true,
        'showGeoKpis' => true,
    ])

    @include('partials.phase3-cfa.filters', [
        'filters' => $filters,
        'districts' => $districts,
        'blocks' => $blocks ?? [],
        'sectors' => $sectors,
    ])

    @php
        $hasPaginator = $submissions->hasPages();
        $total = (int) $submissions->total();
        $serialFrom = $total > 0 ? (($submissions->currentPage() - 1) * $submissions->perPage()) + 1 : 0;
        $serialTo = min($submissions->currentPage() * $submissions->perPage(), $total);
        $filterActive = \App\Services\Cfa\CfaSubmissionListQuery::hasActiveFilters(request());
    @endphp

    <div class="p1l-toolbar">
        <p>
            @if ($total > 0)
                Showing <strong>{{ number_format($serialFrom) }}–{{ number_format($serialTo) }}</strong>
                of <strong>{{ number_format($total) }}</strong>
                @if ($filterActive)<span class="p1l-muted">(filtered)</span>@endif
            @else
                No applications
            @endif
        </p>
        @if ($hasPaginator)
            <p class="p1l-muted">Page {{ $submissions->currentPage() }} of {{ $submissions->lastPage() }}</p>
        @endif
    </div>

    <div class="p1l-table-wrap">
        <table class="p1l-table">
            <thead>
                <tr>
                    <th style="text-align:right;">Sr. No.</th>
                    <th>App. no.</th>
                    <th>Submitted</th>
                    <th>Applicant</th>
                    <th>Phone</th>
                    <th>District</th>
                    <th>Block</th>
                    <th>Onboard status</th>
                    <th>Source / staff</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($submissions as $row)
                    <tr>
                        <td class="p1l-sr">{{ number_format($serialFrom + $loop->iteration - 1) }}</td>
                        <td class="p1l-appno">{{ $row->application_no ?? '—' }}</td>
                        <td style="white-space:nowrap;font-size:0.8rem;color:#64748b;">
                            {{ $row->created_at?->timezone(config('app.timezone'))->format('d M Y H:i') }}
                        </td>
                        <td class="p1l-name">{{ $row->applicant_name }}</td>
                        <td style="white-space:nowrap;">{{ $row->phone }}</td>
                        <td>{{ $row->district?->name ?? '—' }}</td>
                        <td>{{ $row->block_name ?? '—' }}</td>
                        <td>
                            @if (($row->onboard_status ?? '') === 'onboarded')
                                <span class="p1l-pill p1l-pill--onboard-yes">{{ $row->onboard_label ?? 'Onboarded' }}</span>
                            @else
                                <span class="p1l-pill p1l-pill--onboard-no">{{ $row->onboard_label ?? 'Non onboarded' }}</span>
                            @endif
                        </td>
                        <td class="p1l-muted" style="font-size:0.8rem;">
                            @if ($row->source === 'public_form')
                                @php
                                    $submitMode = is_array($row->payload ?? null)
                                        ? (string) ($row->payload['public_cfa_submit_mode'] ?? 'self')
                                        : 'self';
                                @endphp
                                {{ $submitMode === 'gdc_team' ? 'GDC team' : 'Public / walk-in' }}
                            @else
                                {{ $row->referralUser?->name ?? $row->source ?? '—' }}
                            @endif
                        </td>
                        <td>
                            <a href="{{ route('admin.cfa.show', $row) }}" class="p1l-btn p1l-btn--primary" style="padding:0.35rem 0.65rem;font-size:0.75rem;">View</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10" class="p1l-empty">No applications match your filters.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($hasPaginator)
        <div class="p1l-pagination">{{ $submissions->links() }}</div>
    @endif
</div>
@endsection
