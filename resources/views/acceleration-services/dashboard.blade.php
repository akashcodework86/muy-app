@extends('layouts.admin')

@section('title', \App\Models\AccelerationServiceSession::MODULE_LABEL)
@section('heading', \App\Models\AccelerationServiceSession::MODULE_LABEL)

@include('acceleration-services.partials.styles')

@section('content')
@php
    $filterQuery = array_filter($filters ?? [], fn ($v) => $v !== null && $v !== '');
@endphp
<div class="accel-shell">
    @if (!empty($migrationMissing))
        <div class="accel-alert accel-alert--warning"><strong>Database update required.</strong> Run <code>php artisan migrate</code>.</div>
    @endif

    @if (session('status'))
        <div class="accel-alert accel-alert--success">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="accel-alert accel-alert--error">
            <strong>Please fix:</strong>
            <ul style="margin:0.35rem 0 0 1rem;">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="accel-alert accel-alert--info">
        MIS <strong>7.2</strong> — Initiation of acceleration &amp; co-incubation services.
        Counts <strong>unique Phase 1 incubatees per FY</strong> on first initiation; follow-up visits add services without re-counting 7.2.
    </div>

    <div class="accel-stats">
        <div class="accel-stat">
            <div class="accel-stat__label">7.2 Unique initiations (FY)</div>
            <div class="accel-stat__value">{{ number_format((int) ($totals['initiations_fy'] ?? 0)) }}</div>
        </div>
        <div class="accel-stat">
            <div class="accel-stat__label">Sessions logged</div>
            <div class="accel-stat__value">{{ number_format((int) ($totals['sessions'] ?? 0)) }}</div>
        </div>
        <div class="accel-stat">
            <div class="accel-stat__label">Buyer Seller ticks (FY)</div>
            <div class="accel-stat__value">{{ number_format((int) ($totals['buyer_seller_ticks'] ?? 0)) }}</div>
        </div>
    </div>

    @if (!empty($canSubmit) && empty($migrationMissing))
        @include('acceleration-services.partials.form')
    @endif

    <div class="accel-card">
        <div class="accel-card__toolbar">
            <h3 class="accel-card__title" style="margin:0;">
                @if (!empty($isAdminView)) All acceleration sessions (state) @else Your acceleration sessions @endif
            </h3>
            <a href="{{ route($exportRoute, $filterQuery) }}" class="accel-btn accel-btn--secondary">Export CSV</a>
        </div>

        <form method="get" action="{{ route($dashboardRoute) }}" class="accel-filter-form">
            <div class="accel-field">
                <label for="filter_q">Search</label>
                <input type="text" id="filter_q" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Name, app no, phone">
            </div>
            <div class="accel-field">
                <label for="filter_from">From</label>
                <input type="date" id="filter_from" name="from" value="{{ $filters['from'] ?? '' }}">
            </div>
            <div class="accel-field">
                <label for="filter_to">To</label>
                <input type="date" id="filter_to" name="to" value="{{ $filters['to'] ?? '' }}">
            </div>
            <div>
                <button type="submit" class="accel-btn">Filter</button>
            </div>
        </form>

        <div class="accel-table-wrap">
            <table class="accel-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Applicant</th>
                        <th>App no</th>
                        <th>District</th>
                        <th>Services</th>
                        <th>7.2</th>
                        <th>By</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td>{{ $row->service_date?->format('d M Y') }}</td>
                            <td>{{ $row->applicant_name }}</td>
                            <td>{{ $row->application_no ?: '—' }}</td>
                            <td>{{ $row->district_name ?: '—' }}</td>
                            <td>{{ (int) ($row->items_count ?? 0) }} item(s)</td>
                            <td>
                                @if ($row->counts_for_7_2)
                                    <span class="accel-badge accel-badge--init">Initiation</span>
                                @else
                                    <span class="accel-badge accel-badge--follow">Follow-up</span>
                                @endif
                            </td>
                            <td>{{ $row->submitted_by_name }}</td>
                            <td style="white-space:nowrap;">
                                <a class="accel-link" href="{{ route($showRoute, $row) }}">View</a>
                                @if (!empty($canSubmit))
                                    · <a class="accel-link" href="{{ route('spoc.acceleration-services.dashboard', ['from_session' => $row->id]) }}#accel-form">Add services</a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="accel-table__empty">No sessions yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if (method_exists($rows, 'links'))
            <div style="margin-top:0.85rem;">{{ $rows->links() }}</div>
        @endif
    </div>
</div>
@endsection
