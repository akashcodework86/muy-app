@extends('layouts.admin')

@section('title', 'Incubatee meetings')
@section('heading', 'Incubatee meetings')

@push('styles')
@include('mentorship-requests.partials.styles')
<style>
    .im-row-actions { display:flex; flex-wrap:wrap; gap:.4rem; }
    .im-row-actions form { margin:0; }
    .mr-btn--danger { background:#b91c1c; color:#fff; }
    .mr-btn--sm { padding:.38rem .7rem; font-size:.78rem; border-radius:8px; }
</style>
@endpush

@section('content')
@php
    $dashRoute = $prefix.'incubatee-meetings.dashboard';
    $createRoute = $prefix.'incubatee-meetings.create';
    $editRoute = $prefix.'incubatee-meetings.edit';
    $cancelRoute = $prefix.'incubatee-meetings.cancel';
    $completeRoute = $prefix.'incubatee-meetings.complete';
    $proofRoute = $prefix.'incubatee-meetings.proof';
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
        || ($filters['from'] ?? '') !== ''
        || ($filters['to'] ?? '') !== '';
    $listTotal = is_object($rows) && method_exists($rows, 'total') ? (int) $rows->total() : (int) (is_countable($rows) ? count($rows) : 0);
@endphp
<div class="mr-shell">
    @if (session('status'))<div class="ldm-list-alert ldm-list-alert--success">{{ session('status') }}</div>@endif
    @if ($errors->any())<div class="ldm-list-alert ldm-list-alert--warning">{{ $errors->first() }}</div>@endif

    <div class="mr-hero mr-hero--split">
        <div class="mr-hero__copy">
            <span class="mr-hero__kicker">Incubatee hub</span>
            <h2 class="mr-hero__title">Incubatee meetings</h2>
            <p class="mr-hero__sub">
                Meetings are for all incubatees. Everyone on an incubatee dashboard can see them. Only the person who created a meeting can edit, cancel, or mark it done.
            </p>
        </div>
        <div class="mr-hero__actions">
            <a class="mr-btn mr-btn--on-dark" href="{{ route($createRoute) }}">Schedule meeting</a>
        </div>
    </div>

    <div class="ldm-list-stat-grid">
        <a class="ldm-list-stat @if ($currentStatus === '') is-on @endif" href="{{ $statUrl(['status' => '']) }}">
            <span class="ldm-list-stat__label">All meetings</span>
            <span class="ldm-list-stat__value">{{ number_format($totals['total'] ?? 0) }}</span>
        </a>
        <a class="ldm-list-stat @if ($currentStatus === 'upcoming') is-on @endif" href="{{ $statUrl(['status' => $currentStatus === 'upcoming' ? '' : 'upcoming']) }}">
            <span class="ldm-list-stat__label">Upcoming</span>
            <span class="ldm-list-stat__value">{{ number_format($totals['upcoming'] ?? 0) }}</span>
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
            <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Search title or agenda">
            <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" aria-label="From date">
            <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" aria-label="To date">
            <div class="mr-filter-actions">
                <button type="submit" class="mr-btn mr-btn--primary mr-btn--sm">Filter</button>
                @if ($hasFilters)
                    <a class="mr-btn mr-btn--ghost mr-btn--sm" href="{{ route($dashRoute) }}">Clear</a>
                @endif
            </div>
        </form>

        <div class="mr-table-bar">
            <span class="mr-table-bar__meta">{{ number_format($listTotal) }} meeting{{ $listTotal === 1 ? '' : 's' }}</span>
        </div>

        <div class="ldm-list-table-wrap">
            <table class="ldm-list-table">
                <thead>
                    <tr>
                        <th>When (IST)</th>
                        <th>Title</th>
                        <th>Mode</th>
                        <th>Organizer</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        @php
                            $canManage = \App\Support\IncubateeMeetingAccess::canManage($user, $row);
                            $modeLabel = $row->isOnline() ? 'Online' : 'In person';
                        @endphp
                        <tr>
                            <td>
                                {{ \App\Support\Ist::datetime($row->scheduled_at) }}
                                @if ($row->duration_minutes)
                                    <div style="font-size:.72rem;color:#64748b;font-weight:600">{{ $row->duration_minutes }} min</div>
                                @endif
                            </td>
                            <td>
                                <strong>{{ $row->title }}</strong>
                                @if ($row->agenda)
                                    <div style="font-size:.78rem;color:#64748b;margin-top:.2rem;white-space:pre-wrap">{{ \Illuminate\Support\Str::limit($row->agenda, 140) }}</div>
                                @endif
                            </td>
                            <td>
                                {{ $modeLabel }}
                                @if ($row->isOnline() && $row->meeting_link)
                                    <div><a href="{{ $row->meeting_link }}" target="_blank" rel="noopener">Open link</a></div>
                                @elseif ($row->venue)
                                    <div style="font-size:.78rem;color:#64748b">{{ $row->venue }}</div>
                                @endif
                            </td>
                            <td>{{ $row->createdBy?->name ?: '—' }}</td>
                            <td>
                                <span class="mr-badge mr-badge--{{ $row->status }}">{{ str_replace('_', ' ', $row->status) }}</span>
                                @if ($row->isDone() && $row->proof_path)
                                    <div style="margin-top:.35rem">
                                        <a href="{{ route($proofRoute, $row) }}">Proof</a>
                                    </div>
                                @endif
                            </td>
                            <td>
                                @if ($canManage)
                                    <div class="im-row-actions">
                                        <a class="mr-btn mr-btn--ghost mr-btn--sm" href="{{ route($editRoute, $row) }}">Edit</a>
                                        <a class="mr-btn mr-btn--success mr-btn--sm" href="{{ route($completeRoute, $row) }}">Mark done</a>
                                        <form method="post" action="{{ route($cancelRoute, $row) }}" onsubmit="return confirm('Cancel this meeting?');">
                                            @csrf
                                            <button type="submit" class="mr-btn mr-btn--danger mr-btn--sm">Cancel</button>
                                        </form>
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" style="text-align:center;color:#64748b;padding:1.4rem">No meetings yet. Schedule one for all incubatees.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if (method_exists($rows, 'links'))
            <div style="padding:0.85rem 0 0">{{ $rows->links() }}</div>
        @endif
    </div>
</div>
@endsection
