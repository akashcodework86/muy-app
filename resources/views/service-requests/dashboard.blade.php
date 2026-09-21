@extends('layouts.admin')

@section('title', 'Service requests')
@section('heading', 'Service requests')

@push('styles')
@include('mentorship-requests.partials.styles')
@endpush

@section('content')
@php
    $showRoute = $prefix.'service-requests.show';
    $dashRoute = $prefix.'service-requests.dashboard';
    $filterQuery = collect(request()->query())->except('page')->all();
    $statUrl = function (array $overrides = []) use ($dashRoute, $filterQuery): string {
        $merged = array_merge($filterQuery, $overrides);
        $clean = [];
        foreach ($merged as $key => $value) {
            if ($value === null || $value === '' || $value === false) {
                continue;
            }
            $clean[$key] = $value;
        }

        return route($dashRoute, $clean);
    };
    $currentStatus = (string) ($filters['status'] ?? '');
    $hasFilters = ($filters['q'] ?? '') !== ''
        || $currentStatus !== ''
        || (int) ($filters['service_id'] ?? 0) > 0
        || (int) ($filters['district_id'] ?? 0) > 0
        || ($filters['from'] ?? '') !== ''
        || ($filters['to'] ?? '') !== '';
    $showHub = ! empty($showHub);
    $listTotal = is_object($rows) && method_exists($rows, 'total') ? (int) $rows->total() : (int) (is_countable($rows) ? count($rows) : 0);
    $colCount = 8 + ($showHub ? 1 : 0);
@endphp
<div class="mr-shell">
    @if (session('status'))<div class="ldm-list-alert ldm-list-alert--success">{{ session('status') }}</div>@endif
    @if ($errors->any())<div class="ldm-list-alert ldm-list-alert--warning">{{ $errors->first() }}</div>@endif

    <div class="mr-hero mr-hero--split">
        <div class="mr-hero__copy">
            <span class="mr-hero__kicker">Incubatee requests</span>
            <h2 class="mr-hero__title">Service requests</h2>
            <p class="mr-hero__sub">
                @if (!empty($canHandle))
                    Review incubatee service requests for your district. Mark them In progress or Done after you start work.
                @else
                    View service requests in your assigned area. Incubation Managers update status.
                @endif
            </p>
        </div>
    </div>

    <div class="ldm-list-stat-grid">
        <a class="ldm-list-stat @if ($currentStatus === '') is-on @endif" href="{{ $statUrl(['status' => '']) }}">
            <span class="ldm-list-stat__label">Total requests</span>
            <span class="ldm-list-stat__value">{{ number_format($totals['total'] ?? 0) }}</span>
        </a>
        <a class="ldm-list-stat @if ($currentStatus === 'pending') is-on @endif" href="{{ $statUrl(['status' => $currentStatus === 'pending' ? '' : 'pending']) }}">
            <span class="ldm-list-stat__label">Pending</span>
            <span class="ldm-list-stat__value">{{ number_format($totals['pending'] ?? 0) }}</span>
        </a>
        <a class="ldm-list-stat @if ($currentStatus === 'in_progress') is-on @endif" href="{{ $statUrl(['status' => $currentStatus === 'in_progress' ? '' : 'in_progress']) }}">
            <span class="ldm-list-stat__label">In progress</span>
            <span class="ldm-list-stat__value">{{ number_format($totals['in_progress'] ?? 0) }}</span>
        </a>
        <a class="ldm-list-stat @if ($currentStatus === 'done') is-on @endif" href="{{ $statUrl(['status' => $currentStatus === 'done' ? '' : 'done']) }}">
            <span class="ldm-list-stat__label">Done</span>
            <span class="ldm-list-stat__value">{{ number_format($totals['done'] ?? 0) }}</span>
        </a>
        <a class="ldm-list-stat @if ($currentStatus === 'cancelled') is-on @endif" href="{{ $statUrl(['status' => $currentStatus === 'cancelled' ? '' : 'cancelled']) }}">
            <span class="ldm-list-stat__label">Cancelled</span>
            <span class="ldm-list-stat__value">{{ number_format($totals['cancelled'] ?? 0) }}</span>
        </a>
    </div>

    <div class="ldm-list-card">
        <form method="get" class="ldm-list-filters">
            <div class="ldm-list-filter-field"><label>Search</label><input type="text" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Name, CFA, phone, service"></div>
            <div class="ldm-list-filter-field">
                <label>Status</label>
                <select name="status">
                    <option value="">All</option>
                    @foreach (['pending' => 'Pending', 'in_progress' => 'In progress', 'done' => 'Done', 'cancelled' => 'Cancelled'] as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="ldm-list-filter-field">
                <label>Service</label>
                <select name="service_id">
                    <option value="">All</option>
                    @foreach ($services as $svc)
                        <option value="{{ $svc->id }}" @selected((int) ($filters['service_id'] ?? 0) === (int) $svc->id)>{{ $svc->name }}</option>
                    @endforeach
                </select>
            </div>
            @if (count($districtOptions) > 1)
            <div class="ldm-list-filter-field">
                <label>District</label>
                <select name="district_id">
                    <option value="">All</option>
                    @foreach ($districtOptions as $d)
                        <option value="{{ $d['id'] }}" @selected((int) ($filters['district_id'] ?? 0) === (int) $d['id'])>{{ $d['name'] }}</option>
                    @endforeach
                </select>
            </div>
            @endif
            <div class="ldm-list-filter-field"><label>From</label><input type="date" name="from" value="{{ $filters['from'] ?? '' }}"></div>
            <div class="ldm-list-filter-field"><label>To</label><input type="date" name="to" value="{{ $filters['to'] ?? '' }}"></div>
            <div class="mr-filter-actions">
                <button type="submit" class="mr-btn mr-btn--ghost">Filter</button>
                @if ($hasFilters)
                    <a class="mr-btn mr-btn--ghost" href="{{ route($dashRoute) }}">Reset</a>
                @endif
            </div>
        </form>

        <div class="mr-table-bar">
            <div class="mr-table-bar__meta">{{ number_format($listTotal) }} request(s)</div>
        </div>

        <div class="ldm-list-table-wrap">
            <table class="ldm-list-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Requested</th>
                        <th>Incubatee</th>
                        <th>CFA</th>
                        <th>District</th>
                        @if ($showHub)<th>Hub</th>@endif
                        <th>Service</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        @php
                            $serial = method_exists($rows, 'firstItem') && $rows->firstItem()
                                ? (int) $rows->firstItem() + $loop->index
                                : $loop->iteration;
                            $badge = $row->status ?: 'pending';
                        @endphp
                        <tr>
                            <td>{{ $serial }}</td>
                            <td>{{ \App\Support\Ist::date($row->created_at) }}</td>
                            <td>
                                <div class="mr-name">{{ $row->cfaSubmission?->applicant_name ?: $row->requestedBy?->name ?: '—' }}</div>
                                @if ($row->cfaSubmission?->phone)
                                    <div class="mr-muted">{{ $row->cfaSubmission->phone }}</div>
                                @endif
                            </td>
                            <td>{{ $row->cfaSubmission?->application_no ?: '—' }}</td>
                            <td>{{ $row->cfaSubmission?->district?->name ?: '—' }}</td>
                            @if ($showHub)
                                <td>{{ $row->cfaSubmission?->district?->hub?->name ?: '—' }}</td>
                            @endif
                            <td>{{ $row->service?->name ?: '—' }}</td>
                            <td><span class="mr-badge mr-badge--{{ $badge === 'in_progress' ? 'scheduled' : $badge }}">{{ str_replace('_', ' ', $badge) }}</span></td>
                            <td><a class="mr-link" href="{{ route($showRoute, $row) }}">Open</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="{{ $colCount }}" class="ldm-list-empty">No service requests yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if (method_exists($rows, 'links')){{ $rows->links() }}@endif
    </div>
</div>
@endsection
