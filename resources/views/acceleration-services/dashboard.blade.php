@extends('layouts.admin')

@section('title', \App\Models\AccelerationServiceSession::MODULE_LABEL.' — Dashboard')
@section('heading', \App\Models\AccelerationServiceSession::MODULE_LABEL.' — Dashboard')

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

    <div class="accel-alert accel-alert--info">
        MIS <strong>7.2</strong> — Initiation of acceleration &amp; co-incubation services.
        Counts <strong>unique Phase 1 incubatees per FY</strong> on first initiation; follow-up visits add services without re-counting 7.2.
        @if (!empty($workflowReady))
            Entries count only after <strong>final approval</strong> (maker → state review → final approval).
        @endif
    </div>

    <div class="accel-stats">
        <div class="accel-stat">
            <div class="accel-stat__label">7.2 Unique initiations (FY)</div>
            <div class="accel-stat__value">{{ number_format((int) ($totals['initiations_fy'] ?? 0)) }}</div>
        </div>
        <a class="accel-stat accel-stat--link @if (($filters['status'] ?? '') === '') is-active @endif" href="{{ route($dashboardRoute, array_filter(array_merge($filters ?? [], ['status' => '', 'page' => null]), fn ($v) => $v !== null && $v !== '')) }}">
            <div class="accel-stat__label">All approvals</div>
            <div class="accel-stat__value">{{ number_format((int) ($totals['sessions'] ?? 0)) }}</div>
        </a>
        <div class="accel-stat">
            <div class="accel-stat__label">Buyer Seller ticks (FY)</div>
            <div class="accel-stat__value">{{ number_format((int) ($totals['buyer_seller_ticks'] ?? 0)) }}</div>
        </div>
        @if (!empty($isApprover) && !empty($workflowReady))
            <div class="accel-stat">
                <div class="accel-stat__label">Pending your approval</div>
                <div class="accel-stat__value">{{ number_format((int) ($totals['pending_mine'] ?? 0)) }}</div>
            </div>
        @endif
    </div>

    @if (!empty($workflowReady))
        @php
            $statusCardQuery = array_filter($filters ?? [], fn ($v) => $v !== null && $v !== '');
            unset($statusCardQuery['status'], $statusCardQuery['page']);
            $statusCards = [
                ['key' => 'approved', 'label' => 'Approved', 'tone' => 'approved'],
                ['key' => 'pending_approval', 'label' => 'Pending approval', 'tone' => 'pending'],
                ['key' => 'pending_review', 'label' => 'Pending state review', 'tone' => 'review'],
                ['key' => 'pending_final', 'label' => 'Pending final approval', 'tone' => 'final'],
                ['key' => 'sent_back', 'label' => 'Sent back', 'tone' => 'back'],
            ];
        @endphp
        <div class="accel-stats accel-stats--approvals">
            @foreach ($statusCards as $card)
                <a
                    class="accel-stat accel-stat--link accel-stat--{{ $card['tone'] }} @if (($filters['status'] ?? '') === $card['key']) is-active @endif"
                    href="{{ route($dashboardRoute, array_merge($statusCardQuery, ['status' => $card['key']])) }}"
                >
                    <div class="accel-stat__label">{{ $card['label'] }}</div>
                    <div class="accel-stat__value">{{ number_format((int) ($totals[$card['key']] ?? 0)) }}</div>
                </a>
            @endforeach
        </div>
    @endif

    <div class="accel-card">
        <div class="accel-card__toolbar">
            <h3 class="accel-card__title" style="margin:0;">
                @if (!empty($isAdminView))
                    All acceleration approvals
                    <span style="display:block;font-size:0.78rem;font-weight:600;color:#64748b;margin-top:0.2rem;">
                        From every staff member — not only state SPOC entries.
                    </span>
                @elseif (!empty($isApprover))
                    All submitted acceleration entries
                @else
                    Your acceleration sessions
                @endif
            </h3>
            <div style="display:flex; flex-wrap:wrap; gap:0.5rem;">
                @if (!empty($createRoute))
                    <a href="{{ route($createRoute) }}" class="accel-btn">New entry</a>
                @endif
                <a href="{{ route($exportRoute, $filterQuery) }}" class="accel-btn accel-btn--secondary">Export CSV</a>
            </div>
        </div>

        <form method="get" action="{{ route($dashboardRoute) }}" class="accel-filter-form" id="accelFilterForm">
            <div class="accel-field">
                <label for="filter_q">Search</label>
                <input type="text" id="filter_q" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Name, app no, phone">
            </div>
            <div class="accel-field">
                <label for="filter_service">Service</label>
                <select id="filter_service" name="service_key">
                    <option value="">All services</option>
                    @foreach (($serviceOptions ?? []) as $group)
                        <optgroup label="{{ $group['group'] }}">
                            @foreach ($group['items'] as $serviceItem)
                                <option value="{{ $serviceItem['key'] }}" @selected(($filters['service_key'] ?? '') === $serviceItem['key'])>
                                    {{ $serviceItem['label'] }}
                                </option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
            </div>
            <div class="accel-field">
                <label for="filter_district">District</label>
                <select id="filter_district" name="district">
                    <option value="">All districts</option>
                    @foreach (($districtOptions ?? collect()) as $districtName)
                        <option value="{{ $districtName }}" @selected(($filters['district'] ?? '') === $districtName)>{{ $districtName }}</option>
                    @endforeach
                </select>
            </div>
            <div class="accel-field">
                <label for="filter_month">Month</label>
                <select id="filter_month" name="month">
                    <option value="">All months</option>
                    @foreach (range(1, 12) as $m)
                        <option value="{{ $m }}" @selected((int) ($filters['month'] ?? 0) === $m)>{{ \Carbon\Carbon::create(null, $m, 1)->format('F') }}</option>
                    @endforeach
                </select>
            </div>
            <div class="accel-field">
                <label for="filter_from">From</label>
                <input type="date" id="filter_from" name="from" value="{{ $filters['from'] ?? '' }}">
            </div>
            <div class="accel-field">
                <label for="filter_to">To</label>
                <input type="date" id="filter_to" name="to" value="{{ $filters['to'] ?? '' }}">
            </div>
            @if (!empty($workflowReady))
                <div class="accel-field">
                    <label for="filter_status">Status</label>
                    <select id="filter_status" name="status">
                        <option value="">All statuses</option>
                        @php
                            $statusOptions = [
                                'pending_approval' => 'Pending approval',
                                \App\Support\AccelerationServicesApproval::STATUS_PENDING_REVIEW => \App\Support\AccelerationServicesApproval::statusLabel(\App\Support\AccelerationServicesApproval::STATUS_PENDING_REVIEW),
                                \App\Support\AccelerationServicesApproval::STATUS_PENDING_FINAL => \App\Support\AccelerationServicesApproval::statusLabel(\App\Support\AccelerationServicesApproval::STATUS_PENDING_FINAL),
                                \App\Support\AccelerationServicesApproval::STATUS_APPROVED => \App\Support\AccelerationServicesApproval::statusLabel(\App\Support\AccelerationServicesApproval::STATUS_APPROVED),
                                \App\Support\AccelerationServicesApproval::STATUS_SENT_BACK => \App\Support\AccelerationServicesApproval::statusLabel(\App\Support\AccelerationServicesApproval::STATUS_SENT_BACK),
                            ];
                            if (empty($isAdminView)) {
                                $statusOptions[\App\Support\AccelerationServicesApproval::STATUS_DRAFT] = \App\Support\AccelerationServicesApproval::statusLabel(\App\Support\AccelerationServicesApproval::STATUS_DRAFT);
                            }
                        @endphp
                        @foreach ($statusOptions as $statusOption => $statusOptionLabel)
                            <option value="{{ $statusOption }}" @selected(($filters['status'] ?? '') === $statusOption)>
                                {{ $statusOptionLabel }}
                            </option>
                        @endforeach
                    </select>
                </div>
            @endif
            @if (!empty($showCreatorFilter))
                <div class="accel-field">
                    <label for="filter_submitted_by">Submitted by</label>
                    <select id="filter_submitted_by" name="submitted_by_id">
                        <option value="0">All creators</option>
                        @foreach (($submitters ?? collect()) as $submitter)
                            <option value="{{ $submitter['id'] }}" @selected((int) ($filters['submitted_by_id'] ?? 0) === (int) $submitter['id'])>
                                {{ $submitter['name'] }} ({{ number_format((int) $submitter['total']) }})
                            </option>
                        @endforeach
                    </select>
                </div>
            @endif
            <div style="display:flex;gap:0.45rem;flex-wrap:wrap;">
                <button type="submit" class="accel-btn">Filter</button>
                <a href="{{ route($dashboardRoute) }}" class="accel-btn accel-btn--secondary">Clear</a>
            </div>
        </form>

        @if (!empty($showCreatorFilter) && ($submitters ?? collect())->isNotEmpty())
            @php
                $creatorQuery = array_filter($filters ?? [], fn ($v) => $v !== null && $v !== '');
                unset($creatorQuery['submitted_by_id'], $creatorQuery['page']);
                $selectedCreatorId = (int) ($filters['submitted_by_id'] ?? 0);
            @endphp
            <div class="accel-creator-pills" aria-label="Filter by creator">
                <a
                    class="accel-creator-pill @if ($selectedCreatorId === 0) is-active @endif"
                    href="{{ route($dashboardRoute, $creatorQuery) }}"
                >
                    <span>All creators</span>
                    <strong>{{ number_format((int) ($submitters->sum('total'))) }}</strong>
                </a>
                @foreach ($submitters as $submitter)
                    <a
                        class="accel-creator-pill @if ($selectedCreatorId === (int) $submitter['id']) is-active @endif"
                        href="{{ route($dashboardRoute, array_merge($creatorQuery, ['submitted_by_id' => $submitter['id']])) }}"
                    >
                        <span>{{ $submitter['name'] }}</span>
                        <strong>{{ number_format((int) $submitter['total']) }}</strong>
                    </a>
                @endforeach
            </div>
        @endif

        <div class="accel-table-wrap">
            <table class="accel-table">
                <thead>
                    <tr>
                        <th style="width:3rem;">#</th>
                        <th>Date</th>
                        <th>Applicant</th>
                        <th>App no</th>
                        <th>District</th>
                        <th>Services</th>
                        <th>7.2</th>
                        @if (!empty($workflowReady))
                            <th>Approval</th>
                        @endif
                        <th>By</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @php
                        $rowStart = method_exists($rows, 'firstItem') ? (int) ($rows->firstItem() ?? 1) : 1;
                    @endphp
                    @forelse ($rows as $index => $row)
                        @php
                            $items = $row->items ?? collect();
                            $sectionCounts = $items->groupBy('section')->map->count();
                            $categoryBits = [];
                            foreach ([
                                'service_detail' => 'In-house',
                                'cross_cutting' => 'Cross-cutting',
                                'partnership' => 'Partners',
                            ] as $sectionKey => $shortLabel) {
                                $n = (int) ($sectionCounts[$sectionKey] ?? 0);
                                if ($n > 0) {
                                    $categoryBits[] = $shortLabel.' '.$n;
                                }
                            }
                        @endphp
                        <tr>
                            <td class="accel-table__num">{{ $rowStart + $index }}</td>
                            <td>{{ $row->service_date?->format('d M Y') }}</td>
                            <td>{{ $row->applicant_name }}</td>
                            <td>{{ $row->application_no ?: '—' }}</td>
                            <td>{{ $row->district_name ?: '—' }}</td>
                            <td class="accel-services-cell">
                                @if ($items->isEmpty())
                                    <span class="accel-services-cell__empty">No services</span>
                                @else
                                    <ul class="accel-services-cell__names">
                                        @foreach ($items as $item)
                                            <li>{{ $item->item_label }}</li>
                                        @endforeach
                                    </ul>
                                    <div class="accel-services-cell__cats">
                                        {{ implode(' · ', $categoryBits) }}
                                        <span class="accel-services-cell__total">· {{ (int) ($row->items_count ?? $items->count()) }} total</span>
                                    </div>
                                @endif
                            </td>
                            <td>
                                @if ($row->counts_for_7_2)
                                    <span class="accel-badge accel-badge--init">Initiation</span>
                                @else
                                    <span class="accel-badge accel-badge--follow">Follow-up</span>
                                @endif
                            </td>
                            @if (!empty($workflowReady))
                                @php $rowStatus = (string) ($row->status ?? 'approved'); @endphp
                                <td>
                                    <span class="accel-status accel-status--{{ $rowStatus }}">{{ $row->statusLabel() }}</span>
                                    @if ($row->first_approved_by_name)
                                        <div style="font-size:0.7rem;color:#64748b;margin-top:0.2rem;">Reviewed: State Team · {{ $row->first_approved_at?->format('d M') }}</div>
                                    @endif
                                    @if ($row->final_approved_by_name)
                                        <div style="font-size:0.7rem;color:#64748b;margin-top:0.1rem;">Approved: State Team · {{ $row->final_approved_at?->format('d M') }}</div>
                                    @endif
                                    @if ($rowStatus === 'sent_back' && $row->sent_back_by_name)
                                        <div style="font-size:0.7rem;color:#b91c1c;margin-top:0.1rem;">By State Team · {{ $row->sent_back_at?->format('d M') }}</div>
                                    @endif
                                </td>
                            @endif
                            <td>{{ $row->submitted_by_name }}</td>
                            <td style="white-space:nowrap;">
                                <a class="accel-link" href="{{ route($showRoute, $row) }}">View</a>
                                @php
                                    $rowIsMine = (int) ($row->submitted_by_user_id ?? 0) === (int) ($currentUserId ?? 0);
                                    $rowLocked = !empty($workflowReady) && (string) ($row->status ?? '') === 'approved';
                                    $rowIsDraft = (bool) ($row->is_draft ?? false)
                                        || (string) ($row->status ?? '') === \App\Support\AccelerationServicesApproval::STATUS_DRAFT;
                                @endphp
                                @if (!empty($editRoute) && $rowIsMine && ! $rowLocked)
                                    · <a class="accel-link" href="{{ route($editRoute, $row) }}">Edit</a>
                                @endif
                                @if (!empty($createRoute) && ! $rowIsDraft)
                                    · <a class="accel-link" href="{{ route($createRoute, ['from_session' => $row->id]) }}#accel-form">Add services</a>
                                @endif
                                @if (!empty($destroyRoute) && $rowIsMine && $rowIsDraft)
                                    · <form method="post" action="{{ route($destroyRoute, $row) }}" style="display:inline;" onsubmit="return confirm('Delete this draft?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="accel-link" style="background:none;border:0;padding:0;cursor:pointer;color:#b91c1c;font:inherit;">Delete draft</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="10" class="accel-table__empty">No sessions yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if (method_exists($rows, 'links'))
            <div style="margin-top:0.85rem;">{{ $rows->links() }}</div>
        @endif
    </div>
</div>
<script>
    (function () {
        const form = document.getElementById('accelFilterForm');
        const monthEl = document.getElementById('filter_month');
        const dateFromEl = document.getElementById('filter_from');
        const dateToEl = document.getElementById('filter_to');
        if (!form || !monthEl || !dateFromEl || !dateToEl) return;

        const fiscalStartYear = @json((int) ($fiscalYear?->starts_on?->year ?? now()->year));
        const fiscalStartMonth = @json((int) ($fiscalYear?->starts_on?->month ?? 4));
        let syncing = false;

        function pad2(n) { return String(n).padStart(2, '0'); }
        function calendarYearForMonth(month) {
            return month >= fiscalStartMonth ? fiscalStartYear : fiscalStartYear + 1;
        }
        function syncDatesFromMonth() {
            const month = parseInt(monthEl.value, 10);
            if (!month || month < 1 || month > 12) {
                return;
            }
            const year = calendarYearForMonth(month);
            const lastDay = new Date(year, month, 0).getDate();
            syncing = true;
            dateFromEl.value = year + '-' + pad2(month) + '-01';
            dateToEl.value = year + '-' + pad2(month) + '-' + pad2(lastDay);
            syncing = false;
        }
        monthEl.addEventListener('change', syncDatesFromMonth);
        dateFromEl.addEventListener('change', function () { if (!syncing) monthEl.value = ''; });
        dateToEl.addEventListener('change', function () { if (!syncing) monthEl.value = ''; });
    })();
</script>
@endsection
