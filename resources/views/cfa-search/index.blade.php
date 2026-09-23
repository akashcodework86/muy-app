@extends('layouts.admin')

@section('title', 'Search CFA')
@section('heading', 'Search CFA')

@push('styles')
    @include('partials.phase1-legacy.styles')
    <style>
        .cfa-search-form { margin-bottom: 1rem; }
        .cfa-search-form .p1l-field--grow { min-width: 16rem; }
        .cfa-search-phase {
            display: inline-block; padding: 0.12rem 0.5rem; border-radius: 999px;
            font-size: 0.72rem; font-weight: 700; white-space: nowrap;
        }
        .cfa-search-phase--current { background: #eef2ff; color: #3730a3; }
        .cfa-search-phase--phase1 { background: #fff7ed; color: #9a3412; }
        .cfa-search-phase--phase2 { background: #ecfdf5; color: #047857; }
        .cfa-search-open { font-weight: 700; color: #3730a3; text-decoration: none; }
        .cfa-search-open:hover { text-decoration: underline; }
    </style>
@endpush

@section('content')
<div class="p1l-page">
    <div class="p1l-banner">
        Search any CFA by <strong>name</strong>, <strong>mobile number</strong>, or <strong>application number</strong>
        across current MIS, FY 2024-25, and FY 2025-26.
    </div>

    <div class="p1l-hero">
        <div>
            <h2 class="p1l-hero__title">Find an applicant</h2>
            <p class="p1l-hero__sub">
                Type at least {{ $minQueryLength }} characters. Results open the full applicant record.
            </p>
        </div>
        <div class="p1l-hero__badges">
            <span class="p1l-badge p1l-badge--fy">All phases</span>
            @if ($q !== '' && ! $tooShort)
                <span class="p1l-badge p1l-badge--district">{{ number_format($counts['total']) }} matches</span>
            @endif
        </div>
    </div>

    <form method="get" action="{{ route('cfa.search') }}" class="p1l-filters cfa-search-form" role="search">
        <div class="p1l-filters__row">
            <label class="p1l-field p1l-field--grow">
                <span class="p1l-label">Name, mobile, or application no.</span>
                <input
                    class="p1l-input"
                    type="search"
                    name="q"
                    value="{{ $q }}"
                    minlength="{{ $minQueryLength }}"
                    placeholder="e.g. Anita, 9876543210, or MUY-…"
                    autofocus
                    required
                >
            </label>
            <div class="p1l-filters__actions" style="grid-column:auto;padding-top:0;">
                <button type="submit" class="p1l-btn p1l-btn--primary">Search</button>
                @if ($q !== '')
                    <a href="{{ route('cfa.search') }}" class="p1l-btn p1l-btn--ghost">Clear</a>
                @endif
            </div>
        </div>
    </form>

    @if ($tooShort)
        <p class="p1l-alert p1l-alert--warn">Enter at least {{ $minQueryLength }} characters to search.</p>
    @elseif ($q === '')
        <p class="p1l-empty">Enter a name, mobile number, or application number to start.</p>
    @else
        <div class="p1l-stats">
            <div class="p1l-stat">
                <div class="p1l-stat__label">Matches</div>
                <div class="p1l-stat__value">{{ number_format($counts['total']) }}</div>
            </div>
            <div class="p1l-stat p1l-stat--geo">
                <div class="p1l-stat__label">Current MIS</div>
                <div class="p1l-stat__value">{{ number_format($counts['current']) }}</div>
            </div>
            <div class="p1l-stat">
                <div class="p1l-stat__label">FY 2024-25</div>
                <div class="p1l-stat__value">{{ number_format($counts['phase1']) }}</div>
            </div>
            <div class="p1l-stat p1l-stat--onboard-yes">
                <div class="p1l-stat__label">FY 2025-26</div>
                <div class="p1l-stat__value">{{ number_format($counts['phase2']) }}</div>
            </div>
        </div>

        <div class="p1l-table-wrap">
            <table class="p1l-table">
                <thead>
                    <tr>
                        <th>Phase</th>
                        <th>Applicant</th>
                        <th>Application no.</th>
                        <th>Mobile</th>
                        <th>District</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($results as $row)
                        <tr>
                            <td>
                                <span class="cfa-search-phase cfa-search-phase--{{ $row['source'] }}">{{ $row['source_label'] }}</span>
                            </td>
                            <td class="p1l-name">{{ $row['applicant_name'] !== '' ? $row['applicant_name'] : '—' }}</td>
                            <td class="p1l-appno">{{ $row['application_no'] !== '' ? $row['application_no'] : '—' }}</td>
                            <td>{{ $row['phone'] !== '' ? $row['phone'] : '—' }}</td>
                            <td>{{ $row['district'] !== '' ? $row['district'] : '—' }}</td>
                            <td>
                                <a class="cfa-search-open" href="{{ $row['url'] }}?q={{ urlencode($q) }}">View details</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="p1l-empty">No CFA matched “{{ $q }}” in current MIS, FY 2024-25, or FY 2025-26.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif
</div>
@endsection
