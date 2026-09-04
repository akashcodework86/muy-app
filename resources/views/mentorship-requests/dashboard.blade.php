@extends('layouts.admin')

@section('title', 'Mentorship requests')
@section('heading', 'Mentorship requests')

@push('styles')
@include('mentorship-requests.partials.styles')
@endpush

@section('content')
@php
    $showRoute = $prefix.'mentorship-requests.show';
    $scheduleRoute = $prefix.'mentorship-requests.schedule';
    $dashRoute = $prefix.'mentorship-requests.dashboard';
    $exportRoute = $prefix.'mentorship-requests.export';
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
    $uniqueOn = ! empty($filters['unique']);
    $hasFilters = ($filters['q'] ?? '') !== ''
        || $currentStatus !== ''
        || ($filters['category'] ?? '') !== ''
        || (int) ($filters['district_id'] ?? 0) > 0
        || ($filters['from'] ?? '') !== ''
        || ($filters['to'] ?? '') !== ''
        || $uniqueOn;
    $showHub = ! empty($showHub);
    $listTotal = (! empty($isPaginated) && is_object($rows) && method_exists($rows, 'total'))
        ? (int) $rows->total()
        : (int) (is_countable($rows) ? count($rows) : 0);
    $colCount = 9 + (! empty($canHandle) ? 1 : 0) + ($showHub ? 1 : 0);
@endphp
<div class="mr-shell">
    @if (session('status'))<div class="ldm-list-alert ldm-list-alert--success">{{ session('status') }}</div>@endif
    @if ($errors->any())<div class="ldm-list-alert ldm-list-alert--warning">{{ $errors->first() }}</div>@endif

    <div class="mr-hero mr-hero--split">
        <div class="mr-hero__copy">
            <span class="mr-hero__kicker">Deliverable 5.2</span>
            <h2 class="mr-hero__title">Online portal mentorship</h2>
            <p class="mr-hero__sub">
                @if (!empty($canHandle))
                    Schedule online sessions and mark them Done. Unique incubatees with a Done session count toward 5.2.
                @else
                    View requests in your assigned area. Incubation Managers schedule sessions and mark them Done.
                @endif
            </p>
        </div>
        <div class="mr-hero__actions">
            <a href="{{ route($exportRoute, $filterQuery) }}" class="mr-btn mr-btn--on-dark">Export Excel</a>
        </div>
    </div>

    <div class="ldm-list-stat-grid">
        <a class="ldm-list-stat @if ($currentStatus === '') is-on @endif" href="{{ $statUrl(['status' => '']) }}">
            <span class="ldm-list-stat__label">Total requests</span>
            <span class="ldm-list-stat__value">{{ number_format($totals['total'] ?? 0) }}</span>
            <span class="ldm-list-stat__hint">All statuses</span>
        </a>
        <a class="ldm-list-stat @if ($uniqueOn) is-on @endif" href="{{ $statUrl(['unique' => $uniqueOn ? '' : 1]) }}">
            <span class="ldm-list-stat__label">Unique incubatees</span>
            <span class="ldm-list-stat__value">{{ number_format($totals['unique'] ?? 0) }}</span>
            <span class="ldm-list-stat__hint">{{ $uniqueOn ? 'Showing latest only' : 'Latest request per person' }}</span>
        </a>
        <a class="ldm-list-stat @if ($currentStatus === 'pending') is-on @endif" href="{{ $statUrl(['status' => $currentStatus === 'pending' ? '' : 'pending']) }}">
            <span class="ldm-list-stat__label">Pending</span>
            <span class="ldm-list-stat__value">{{ number_format($totals['pending'] ?? 0) }}</span>
        </a>
        <a class="ldm-list-stat @if ($currentStatus === 'scheduled') is-on @endif" href="{{ $statUrl(['status' => $currentStatus === 'scheduled' ? '' : 'scheduled']) }}">
            <span class="ldm-list-stat__label">Scheduled</span>
            <span class="ldm-list-stat__value">{{ number_format($totals['scheduled'] ?? 0) }}</span>
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
            <div class="ldm-list-filter-field"><label>Search</label><input type="text" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Name, CFA, phone"></div>
            <div class="ldm-list-filter-field">
                <label>Status</label>
                <select name="status">
                    <option value="">All</option>
                    @foreach (['pending' => 'Pending', 'scheduled' => 'Scheduled', 'done' => 'Done', 'cancelled' => 'Cancelled'] as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="ldm-list-filter-field">
                <label>Category</label>
                <select name="category">
                    <option value="">All</option>
                    @foreach ($categories as $slug => $meta)
                        <option value="{{ $slug }}" @selected(($filters['category'] ?? '') === $slug)>{{ $meta['label'] ?? $slug }}</option>
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
            <div class="ldm-list-filter-field">
                <label>Unique incubatees</label>
                <label style="display:flex;align-items:center;gap:0.4rem;font-weight:500">
                    <input type="checkbox" name="unique" value="1" @checked(!empty($filters['unique']))>
                    Show latest request only
                </label>
            </div>
            <div class="mr-filter-actions">
                <button type="submit" class="mr-btn mr-btn--ghost">Filter</button>
                @if ($hasFilters)
                    <a class="mr-btn mr-btn--ghost" href="{{ route($dashRoute) }}">Reset</a>
                @endif
            </div>
        </form>

        <div class="mr-table-bar">
            <div class="mr-table-bar__meta">
                {{ number_format($listTotal) }} {{ \Illuminate\Support\Str::plural('request', $listTotal) }}
                @if ($uniqueOn)
                    · latest per incubatee
                @endif
                @if ($currentStatus !== '')
                    · {{ $currentStatus }}
                @endif
            </div>
            <a href="{{ route($exportRoute, $filterQuery) }}" class="mr-btn mr-btn--ghost">Export Excel</a>
        </div>

        @if (!empty($canHandle))
        <form method="get" action="{{ route($scheduleRoute) }}" id="mentorshipScheduleForm">
        @endif
        <div class="ldm-list-table-wrap">
            <table class="ldm-list-table">
                <thead>
                    <tr>
                        @if (!empty($canHandle))<th></th>@endif
                        <th>#</th>
                        <th>Requested</th>
                        <th>Incubatee</th>
                        <th>CFA</th>
                        <th>District</th>
                        @if ($showHub)<th>Hub</th>@endif
                        <th>Category</th>
                        <th>Status</th>
                        <th>Session</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        @php
                            $serial = method_exists($rows, 'firstItem') && $rows->firstItem()
                                ? (int) $rows->firstItem() + $loop->index
                                : $loop->iteration;
                            $cat = $categories[$row->category]['label'] ?? $row->category;
                            $badge = $row->status ?: 'pending';
                        @endphp
                        <tr>
                            @if (!empty($canHandle))
                            <td>
                                @if ($row->isPending())
                                    <input type="checkbox" name="ids[]" value="{{ $row->id }}" form="mentorshipScheduleForm" data-category="{{ $row->category }}">
                                @endif
                            </td>
                            @endif
                            <td>{{ $serial }}</td>
                            <td>{{ $row->created_at?->format('d M Y') }}</td>
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
                            <td>{{ $cat }}</td>
                            <td><span class="mr-badge mr-badge--{{ $badge }}">{{ str_replace('_', ' ', $badge) }}</span></td>
                            <td>
                                @if ($row->session)
                                    {{ $row->session->kind === 'batch' ? 'Batch' : 'Individual' }}
                                    @if ($row->session->scheduled_at)
                                        <div class="mr-muted">{{ $row->session->scheduled_at->format('d M Y, h:i A') }}</div>
                                    @endif
                                @else
                                    —
                                @endif
                                @if ($row->done_at)
                                    <div class="mr-muted">Done {{ $row->done_at->format('d M Y') }}</div>
                                @endif
                            </td>
                            <td><a class="mr-link" href="{{ route($showRoute, $row) }}">Open</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="{{ $colCount }}" class="ldm-list-empty">No mentorship requests yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if (!empty($canHandle))
            <div class="mr-actions" style="padding-top:0.35rem">
                <button type="submit" class="mr-btn mr-btn--primary">Schedule selected (same category)</button>
            </div>
        </form>
        @endif
        @if (!empty($isPaginated) && method_exists($rows, 'links')){{ $rows->links() }}@endif
    </div>
</div>
@endsection
