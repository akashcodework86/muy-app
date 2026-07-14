@extends('layouts.admin')

@section('title', 'Services')
@section('heading', 'Service cases')

@section('content')
    <style>
        .svc-page-actions {
            display: flex;
            gap: 0.5rem;
            align-items: center;
            flex-wrap: wrap;
            margin: 0 0 0.9rem;
        }
        .svc-new-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            background: #4f46e5;
            color: #fff;
            text-decoration: none;
            padding: 0.5rem 0.9rem;
            border-radius: 9px;
            font-size: 0.86rem;
            font-weight: 700;
        }
        .svc-link {
            font-size: 0.86rem;
            color: #4338ca;
            text-decoration: none;
            font-weight: 600;
        }
        .svc-success {
            background: #dcfce7;
            color: #166534;
            padding: 0.5rem 0.75rem;
            border-radius: 6px;
            font-size: 0.88rem;
            margin: 0 0 0.75rem;
        }
        .svc-errors {
            color: #b91c1c;
            margin: 0 0 0.75rem;
            padding-left: 1.2rem;
            font-size: 0.88rem;
        }
        .svc-status-tabs {
            display: flex;
            flex-wrap: wrap;
            gap: 0.35rem;
            margin-bottom: 0.85rem;
        }
        .svc-status-tab {
            padding: 0.4rem 0.75rem;
            border-radius: 999px;
            font-size: 0.81rem;
            font-weight: 700;
            text-decoration: none;
            border: 1px solid #e4e4e7;
            background: #fff;
            color: #3f3f46;
        }
        .svc-status-tab.is-active {
            border-color: #4f46e5;
            background: #eef2ff;
            color: #3730a3;
        }
        .svc-toolbar {
            display: flex;
            gap: 0.6rem;
            flex-wrap: wrap;
            margin-bottom: 0.9rem;
        }
        .svc-input,
        .svc-select {
            border: 1px solid #d1d5db;
            border-radius: 10px;
            padding: 0.5rem 0.7rem;
            font-size: 0.88rem;
            min-height: 38px;
            background: #fff;
            color: #111827;
        }
        .svc-input {
            min-width: 260px;
            flex: 1 1 260px;
        }
        .svc-select {
            min-width: 220px;
        }
        .svc-card {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            overflow: hidden;
        }
        .svc-table-wrap {
            overflow-x: auto;
        }
        .svc-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            font-size: 0.86rem;
            min-width: 1020px;
        }
        .svc-table th {
            background: #f8fafc;
            text-align: left;
            padding: 0.65rem 0.75rem;
            border-bottom: 1px solid #e5e7eb;
            color: #374151;
            white-space: nowrap;
        }
        .svc-table th.svc-table__sr,
        .svc-table td.svc-table__sr {
            width: 2.5rem;
            text-align: center;
            color: #6b7280;
            font-weight: 600;
            padding-left: 0.45rem;
            padding-right: 0.45rem;
        }
        .svc-table td {
            padding: 0.62rem 0.75rem;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
        }
        .svc-table tr:hover td {
            background: #fafafa;
        }
        .svc-muted {
            color: #6b7280;
            font-size: 0.78rem;
            margin-top: 2px;
        }
        .svc-spoc-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            padding: 0.24rem 0.58rem;
            border-radius: 999px;
            border: 1px solid #c7d2fe;
            background: #eef2ff;
            color: #3730a3;
            font-size: 0.77rem;
            font-weight: 700;
            white-space: nowrap;
        }
        .svc-spoc-pill--empty {
            border-color: #e5e7eb;
            background: #f9fafb;
            color: #6b7280;
            font-weight: 600;
        }
        .svc-status-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.22rem 0.55rem;
            border-radius: 999px;
            font-size: 0.76rem;
            font-weight: 700;
            text-transform: capitalize;
        }
        .svc-status-pill--draft {
            background: #f3f4f6;
            color: #374151;
        }
        .svc-status-pill--pending_approval {
            background: #fff7ed;
            color: #9a3412;
        }
        .svc-status-pill--sent_back {
            background: #fee2e2;
            color: #b91c1c;
        }
        .svc-status-pill--approved {
            background: #dcfce7;
            color: #166534;
        }
        .svc-status-pill--rejected {
            background: #fce7f3;
            color: #9d174d;
        }
        .svc-response {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.28rem 0.6rem;
            border-radius: 8px;
            font-size: 0.76rem;
            font-weight: 600;
            line-height: 1.3;
            max-width: 280px;
        }
        .svc-response--approved {
            background: #ecfdf5;
            border: 1px solid #a7f3d0;
            color: #065f46;
            white-space: nowrap;
        }
        .svc-response--sent-back {
            background: #fff7ed;
            border: 1px solid #fed7aa;
            color: #9a3412;
        }
        .svc-response--rejected {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #991b1b;
        }
        .svc-response--none {
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            color: #6b7280;
        }
        .svc-action-group {
            display: flex;
            gap: 0.4rem;
            flex-wrap: wrap;
        }
        .svc-btn-xs {
            border: 1px solid transparent;
            border-radius: 8px;
            padding: 0.35rem 0.55rem;
            font-size: 0.78rem;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
            background: #fff;
            line-height: 1.2;
        }
        .svc-btn-xs--view {
            border-color: #c7d2fe;
            color: #3730a3;
            background: #eef2ff;
        }
        .svc-btn-xs--edit {
            border-color: #bfdbfe;
            color: #1d4ed8;
            background: #eff6ff;
        }
        .svc-btn-xs--doc {
            border-color: #cbd5e1;
            color: #0f172a;
            background: #f8fafc;
        }
        .svc-btn-xs--delete {
            border-color: #fecaca;
            color: #b91c1c;
            background: #fef2f2;
        }
        .svc-empty {
            color: #71717a;
            font-size: 0.9rem;
        }
        .svc-doc-modal {
            position: fixed;
            inset: 0;
            display: none;
            align-items: center;
            justify-content: center;
            background: rgba(15, 23, 42, 0.55);
            z-index: 80;
            padding: 1rem;
        }
        .svc-doc-modal.is-open {
            display: flex;
        }
        .svc-doc-modal__card {
            width: min(980px, 96vw);
            max-height: 92vh;
            background: #fff;
            border-radius: 12px;
            border: 1px solid #e5e7eb;
            overflow: hidden;
            box-shadow: 0 20px 45px rgba(15, 23, 42, 0.28);
            display: flex;
            flex-direction: column;
        }
        .svc-doc-modal__head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 0.8rem;
            padding: 0.7rem 0.9rem;
            border-bottom: 1px solid #e5e7eb;
        }
        .svc-doc-modal__title {
            font-size: 0.9rem;
            font-weight: 700;
            color: #0f172a;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .svc-doc-modal__close {
            border: 1px solid #cbd5e1;
            background: #f8fafc;
            color: #0f172a;
            border-radius: 8px;
            padding: 0.25rem 0.55rem;
            cursor: pointer;
            font-size: 0.8rem;
            font-weight: 700;
        }
        .svc-doc-modal__body {
            padding: 0.8rem;
            overflow: auto;
            background: #f8fafc;
            min-height: 320px;
        }
        .svc-doc-modal__frame {
            width: 100%;
            min-height: 72vh;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            background: #fff;
        }
        .svc-doc-modal__img {
            max-width: 100%;
            max-height: 72vh;
            display: block;
            margin: 0 auto;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            background: #fff;
        }
        .svc-doc-modal__fallback {
            font-size: 0.86rem;
            color: #334155;
        }
        .svc-doc-modal__fallback a {
            color: #4338ca;
            font-weight: 700;
        }
        .svc-through-reap-badge {
            display: inline-flex;
            align-items: center;
            margin-top: 0.28rem;
            padding: 0.16rem 0.5rem;
            border-radius: 999px;
            border: 1px solid #fdba74;
            background: linear-gradient(180deg, #fff7ed 0%, #ffedd5 100%);
            color: #9a3412;
            font-size: 0.72rem;
            font-weight: 800;
            letter-spacing: 0.02em;
            white-space: nowrap;
        }
        .svc-cell--approval {
            background: #fffbeb !important;
            box-shadow: inset 3px 0 0 #d97706;
        }
        .svc-row--field_mis td {
            background: #f8fafc;
        }
        .svc-row--field_mis:hover td.svc-cell--approval {
            background: #fef3c7 !important;
        }
    </style>

    <p class="svc-page-actions">
        <a href="{{ route('staff.services.create') }}" class="svc-new-btn">+ New submission</a>
        <a href="{{ route('staff.applications') }}" class="svc-link">Applications</a>
    </p>

    @php
        use App\Models\ServiceCase;
        use App\Support\ConvergenceReapSupport;
        $tabs = [
            '' => 'All',
            ServiceCase::STATUS_DRAFT => 'Draft',
            ServiceCase::STATUS_PENDING_APPROVAL => 'Pending approval',
            ServiceCase::STATUS_SENT_BACK => 'Sent back',
            ServiceCase::STATUS_APPROVED => 'Approved',
            ServiceCase::STATUS_REJECTED => 'Rejected',
        ];
        $serviceFilterParam = match ($filterRecordType ?? '') {
            'market_linkage' => 'market_linkage',
            ConvergenceReapSupport::MIS_8_2_LIST_FILTER => ConvergenceReapSupport::MIS_8_2_LIST_FILTER,
            default => \App\Services\MisFieldActivityListService::isListFilterValue((string) ($filterRecordType ?? ''))
                ? $filterRecordType
                : (((int) ($filterServiceId ?? 0) > 0) ? (int) $filterServiceId : null),
        };
    @endphp

    <div class="svc-status-tabs" style="margin-top:-0.15rem;">
        <a href="{{ route('staff.services.index', array_filter(['scope' => 'my', 'status' => $filterStatus ?: null, 'service_id' => $serviceFilterParam])) }}"
           class="svc-status-tab {{ (($filterScope ?? 'my') === 'my') ? 'is-active' : '' }}">
            My services
        </a>
        <a href="{{ route('staff.services.index', array_filter(['scope' => 'all', 'status' => $filterStatus ?: null, 'service_id' => $serviceFilterParam])) }}"
           class="svc-status-tab {{ (($filterScope ?? 'my') === 'all') ? 'is-active' : '' }}">
            All services (view only)
        </a>
    </div>

    @if (session('status'))
        <p class="svc-success">{{ session('status') }}</p>
    @endif
    @if ($errors->any())
        <ul class="svc-errors">
            @foreach ($errors->all() as $e)
                <li>{{ $e }}</li>
            @endforeach
        </ul>
    @endif

    @if (! empty($reapTargetsProgress))
        @include('partials.reap-incubatee-targets-panel', [
            'reapTargetsProgress' => $reapTargetsProgress,
            'reapTargetsInteractive' => false,
            'reapTargetsCompact' => false,
        ])
    @endif

    <div class="svc-status-tabs">
        @foreach ($tabs as $val => $label)
            <a href="{{ route('staff.services.index', array_filter(['scope' => $filterScope ?? 'my', 'status' => $val, 'service_id' => $serviceFilterParam])) }}"
               class="svc-status-tab {{ ($filterStatus === $val) ? 'is-active' : '' }}">
                {{ $label }}
            </a>
        @endforeach
    </div>

    <form method="get" class="svc-toolbar">
        <input type="hidden" name="scope" value="{{ $filterScope ?? 'my' }}">
        @if (!empty($filterStatus))
            <input type="hidden" name="status" value="{{ $filterStatus }}">
        @endif
        <input
            id="globalSearch"
            type="text"
            class="svc-input"
            placeholder="Search all fields (incubatee, application no, service, status)"
            autocomplete="off"
        >
        <select name="service_id" class="svc-select" onchange="this.form.submit()">
            <option value="" @selected(($filterRecordType ?? '') === '' && (int) ($filterServiceId ?? 0) === 0)>All services</option>
            <option value="market_linkage" @selected(($filterRecordType ?? '') === 'market_linkage')>{{ \App\Models\MarketLinkageSubmission::SERVICE_LIST_LABEL }}</option>
            <option value="{{ ConvergenceReapSupport::MIS_8_2_LIST_FILTER }}" @selected(($filterRecordType ?? '') === ConvergenceReapSupport::MIS_8_2_LIST_FILTER)>{{ ConvergenceReapSupport::MIS_8_2_LIST_LABEL }}</option>
            @foreach (\App\Support\MisFieldActivityApproval::modules() as $moduleKey => $moduleMeta)
                <option value="{{ $moduleKey }}" @selected(($filterRecordType ?? '') === $moduleKey)>
                    {{ ($moduleMeta['serial'] ?? '').' — '.($moduleMeta['label'] ?? $moduleKey) }}
                </option>
            @endforeach
            @foreach (($services ?? collect()) as $service)
                <option value="{{ $service->id }}" @selected(($filterRecordType ?? '') === '' && (int) ($filterServiceId ?? 0) === (int) $service->id)>
                    {{ $service->name }}
                </option>
            @endforeach
        </select>
    </form>

    @if ($cases->isEmpty())
        <p class="svc-empty">No service, market linkage, or field MIS records in this view.</p>
    @else
        <div class="svc-card">
            <div class="svc-table-wrap">
                <table class="svc-table" id="servicesTable">
                    <thead>
                        <tr>
                            <th class="svc-table__sr">Sr No</th>
                            <th>Incubatee</th>
                            <th>Service</th>
                            <th>Assigned by</th>
                            <th>Assigned SPOC</th>
                            <th>Responded by</th>
                            <th>Response</th>
                            <th>Status</th>
                            <th>Updated</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            $listSrNo = (int) ($cases->firstItem() ?? 1);
                        @endphp
                        @foreach ($cases as $row)
                            @php
                                $rowType = is_array($row) ? (string) ($row['type'] ?? '') : 'service_case';
                                $case = is_array($row) ? ($row['service_case'] ?? null) : $row;
                                $ml = is_array($row) ? ($row['market_linkage'] ?? null) : null;
                                $fm = is_array($row) ? ($row['field_mis'] ?? null) : null;
                                $fmModule = is_array($row) ? (string) ($row['field_mis_module'] ?? '') : '';
                            @endphp
                            @if ($rowType === 'field_mis' && $fm && $fmModule !== '')
                            @php
                                $fmMeta = \App\Support\MisFieldActivityApproval::module($fmModule);
                                $titleCol = (string) ($fmMeta['title_column'] ?? 'id');
                                $fmTitle = trim((string) ($fm->{$titleCol} ?? '')) ?: 'Entry #'.$fm->getKey();
                                $fmServiceLabel = trim(($fmMeta['serial'] ?? '').' — '.($fmMeta['label'] ?? $fmModule));
                                $fmDistrict = (string) ($fm->district_name ?? $fm->district?->name ?? '—');
                                $fmApprover = (string) ($fm->misFieldSpoc?->name ?? \App\Support\MisFieldActivityApproval::approverUser()?->name ?? 'Not assigned');
                                $statusSlug = strtolower(str_replace(' ', '_', (string) $fm->status));
                                $assignedByName = $fm->submitted_by_name ?? $fm->submitter?->name ?? '—';
                                $responseText = match ((string) $fm->status) {
                                    \App\Models\ServiceCase::STATUS_SENT_BACK => $fm->sent_back_note ?: 'Sent back for changes.',
                                    \App\Models\ServiceCase::STATUS_REJECTED => $fm->rejected_note ?: 'Rejected.',
                                    \App\Models\ServiceCase::STATUS_APPROVED => 'Approved',
                                    default => 'Awaiting approval',
                                };
                                $responseClass = match ((string) $fm->status) {
                                    \App\Models\ServiceCase::STATUS_APPROVED => 'svc-response--approved',
                                    \App\Models\ServiceCase::STATUS_SENT_BACK => 'svc-response--sent-back',
                                    \App\Models\ServiceCase::STATUS_REJECTED => 'svc-response--rejected',
                                    default => 'svc-response--none',
                                };
                                $fmShowRoute = match ($fmModule) {
                                    'technical_training' => route('staff.technical-trainings.show', $fm),
                                    'lakhpati_technical_training' => route('staff.lakhpati-technical-trainings.show', $fm),
                                    'line_department_meeting' => route('staff.line-department-meetings.show', $fm),
                                    'community_org_outreach' => route('staff.community-org-outreach.show', $fm),
                                    default => '#',
                                };
                                $searchText = strtolower(trim($fmTitle.' '.$fmServiceLabel.' '.$fmDistrict.' '.$assignedByName.' '.$fmApprover.' '.str_replace('_', ' ', (string) $fm->status).' '.$responseText));
                            @endphp
                            <tr class="svc-row svc-row--field_mis" data-search="{{ $searchText }}">
                                <td class="svc-table__sr">{{ $listSrNo }}</td>
                                <td>
                                    <strong>{{ $fmTitle }}</strong>
                                    <div class="svc-muted">{{ $fmDistrict }}</div>
                                </td>
                                <td>{{ $fmServiceLabel }}</td>
                                <td>{{ $assignedByName }}</td>
                                <td class="svc-cell--approval">
                                    <span class="svc-spoc-pill">{{ $fmApprover }}</span>
                                </td>
                                <td>{{ '—' }}</td>
                                <td>
                                    <span class="svc-response {{ $responseClass }}">{{ $responseText }}</span>
                                </td>
                                <td class="svc-cell--approval">
                                    <span class="svc-status-pill svc-status-pill--{{ $statusSlug }}">
                                        {{ method_exists($fm, 'misFieldStatusLabel') ? $fm->misFieldStatusLabel() : str_replace('_', ' ', (string) $fm->status) }}
                                    </span>
                                </td>
                                <td style="white-space:nowrap;">{{ $fm->updated_at?->timezone(config('app.timezone'))->format('d M Y H:i') }}</td>
                                <td>
                                    <div class="svc-action-group">
                                        <a href="{{ $fmShowRoute }}" class="svc-btn-xs svc-btn-xs--view">View</a>
                                    </div>
                                </td>
                            </tr>
                            @php $listSrNo++; @endphp
                            @elseif ($rowType === 'market_linkage' && $ml)
                            @php
                                $statusSlug = strtolower(str_replace(' ', '_', (string) $ml->status));
                                $pendingDays = null;
                                if ($ml->status === \App\Models\ServiceCase::STATUS_PENDING_APPROVAL) {
                                    $pendingSince = $ml->submitted_at ?? $ml->updated_at;
                                    $pendingDays = $pendingSince
                                        ? $pendingSince->copy()->startOfDay()->diffInDays(now()->startOfDay())
                                        : 0;
                                }
                                $responderName = $ml->approver?->name ?? $ml->rejector?->name ?? null;
                                $assignedByName = $ml->submitted_by_name ?? $ml->submitter?->name ?? '—';
                                $responseText = '—';
                                $responseClass = 'svc-response--none';
                                if ($ml->status === \App\Models\ServiceCase::STATUS_APPROVED) {
                                    $responseText = 'Checked';
                                    $responseClass = 'svc-response--approved';
                                } elseif ($ml->status === \App\Models\ServiceCase::STATUS_SENT_BACK) {
                                    $responseText = $ml->sent_back_note ?: 'Sent back for changes.';
                                    $responseClass = 'svc-response--sent-back';
                                } elseif ($ml->status === \App\Models\ServiceCase::STATUS_REJECTED) {
                                    $responseText = $ml->rejected_note ?: 'Rejected.';
                                    $responseClass = 'svc-response--rejected';
                                }
                                $searchText = strtolower(trim(
                                    ($ml->incubatee_name ?? '').' '.
                                    ($ml->application_no ?? '').' '.
                                    'market linkage '.
                                    ($assignedByName ?? '').' '.
                                    str_replace('_', ' ', (string) $ml->status).' '.
                                    ($ml->spoc?->name ?? '').' '.
                                    ($responderName ?? '').' '.
                                    $responseText
                                ));
                            @endphp
                            <tr class="svc-row" data-search="{{ $searchText }}">
                                <td class="svc-table__sr">{{ $listSrNo }}</td>
                                <td>
                                    <strong>{{ $ml->incubatee_name }}</strong>
                                    @if ($ml->application_no)
                                        <div class="svc-muted">{{ $ml->application_no }}</div>
                                    @endif
                                </td>
                                <td>{{ \App\Models\MarketLinkageSubmission::SERVICE_LIST_LABEL }} <span class="svc-muted">({{ $ml->partners->count() }} partner{{ $ml->partners->count() === 1 ? '' : 's' }})</span></td>
                                <td>{{ $assignedByName }}</td>
                                <td>
                                    <span class="svc-spoc-pill {{ $ml->spoc?->name ? '' : 'svc-spoc-pill--empty' }}">
                                        {{ $ml->spoc?->name ?? 'Not assigned' }}
                                    </span>
                                </td>
                                <td>{{ $responderName ?? '—' }}</td>
                                <td>
                                    <span class="svc-response {{ $responseClass }}">
                                        @if ($ml->status === \App\Models\ServiceCase::STATUS_APPROVED)
                                            <span aria-hidden="true">&#10003;</span>
                                        @endif
                                        {{ $responseText }}
                                    </span>
                                </td>
                                <td>
                                    <span class="svc-status-pill svc-status-pill--{{ $statusSlug }}">
                                        {{ str_replace('_', ' ', (string) $ml->status) }}
                                    </span>
                                    @if (!is_null($pendingDays))
                                        <div class="svc-muted">
                                            Pending from {{ $pendingDays }} day{{ $pendingDays === 1 ? '' : 's' }}
                                        </div>
                                    @endif
                                </td>
                                <td style="white-space:nowrap;">{{ $ml->updated_at?->timezone(config('app.timezone'))->format('d M Y H:i') }}</td>
                                <td>
                                    <div class="svc-action-group">
                                        <a href="{{ route('staff.market-linkages.show', $ml) }}" class="svc-btn-xs svc-btn-xs--view">View</a>
                                        @if (($filterScope ?? 'my') === 'my' && $ml->canBeEditedByStaff())
                                            <a href="{{ route('staff.market-linkages.edit', $ml) }}" class="svc-btn-xs svc-btn-xs--edit">Edit</a>
                                        @endif
                                        @if (($staffDeleteEnabled ?? true) && ($filterScope ?? 'my') === 'my' && $ml->canBeDeletedByStaff() && (int) $ml->submitted_by_user_id === (int) auth()->id())
                                            <form method="post" action="{{ route('staff.market-linkages.destroy', $ml) }}" style="display:inline;" onsubmit="return confirm('Delete this market linkage submission?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="svc-btn-xs svc-btn-xs--delete">Delete</button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                            @php $listSrNo++; @endphp
                            @elseif ($case)
                            @php
                                $lip = $case->legacyIncubateePreview ?? null;
                                $statusSlug = strtolower(str_replace(' ', '_', (string) $case->status));
                                $pendingDays = null;
                                if ($case->status === \App\Models\ServiceCase::STATUS_PENDING_APPROVAL) {
                                    $pendingSince = $case->submitted_at ?? $case->updated_at;
                                    $pendingDays = $pendingSince
                                        ? $pendingSince->copy()->startOfDay()->diffInDays(now()->startOfDay())
                                        : 0;
                                }
                                $responderName = $case->approver?->name
                                    ?? $case->rejector?->name
                                    ?? (($case->status === \App\Models\ServiceCase::STATUS_SENT_BACK) ? $case->spoc?->name : null)
                                    ?? null;
                                $assignedByName = $case->submitter?->name
                                    ?? $case->creator?->name
                                    ?? '—';
                                $responseText = '—';
                                $responseClass = 'svc-response--none';
                                if ($case->status === \App\Models\ServiceCase::STATUS_APPROVED) {
                                    $responseText = 'Checked';
                                    $responseClass = 'svc-response--approved';
                                } elseif ($case->status === \App\Models\ServiceCase::STATUS_SENT_BACK) {
                                    $responseText = $case->sent_back_note ?: 'Sent back for changes.';
                                    $responseClass = 'svc-response--sent-back';
                                } elseif ($case->status === \App\Models\ServiceCase::STATUS_REJECTED) {
                                    $responseText = $case->rejected_note ?: 'Rejected.';
                                    $responseClass = 'svc-response--rejected';
                                }
                                $searchText = strtolower(trim(
                                    ($case->cfaSubmission?->applicant_name ?? (is_array($lip) ? ($lip['applicant_name'] ?? '') : '')).' '.
                                    ($case->cfaSubmission?->application_no ?? (is_array($lip) ? ($lip['application_no'] ?? '') : '')).' '.
                                    ($case->displaysReapSupportRoute()
                                        ? ConvergenceReapSupport::MIS_8_2_LIST_LABEL.' '.($case->service?->name ?? '')
                                        : ($case->service?->name ?? '')).' '.
                                    ($assignedByName ?? '').' '.
                                    str_replace('_', ' ', (string) $case->status).' '.
                                    ($case->spoc?->name ?? '').' '.
                                    ($responderName ?? '').' '.
                                    $responseText
                                ));
                            @endphp
                            <tr class="svc-row" data-search="{{ $searchText }}">
                                <td class="svc-table__sr">{{ $listSrNo }}</td>
                                <td>
                                    <strong>{{ $case->cfaSubmission?->applicant_name ?? (is_array($lip) ? ($lip['applicant_name'] ?? '—') : '—') }}</strong>
                                    @if ($case->cfaSubmission?->application_no)
                                        <div class="svc-muted">{{ $case->cfaSubmission->application_no }}</div>
                                    @elseif (is_array($lip) && ($lip['application_no'] ?? '') !== '')
                                        <div class="svc-muted">{{ $lip['application_no'] }} <span style="color:#94a3b8;">(legacy)</span></div>
                                    @endif
                                </td>
                                <td>
                                    @include('staff.services.partials.reap-listing-service-cell', ['case' => $case])
                                </td>
                                <td>{{ $assignedByName }}</td>
                                <td>
                                    <span class="svc-spoc-pill {{ $case->spoc?->name ? '' : 'svc-spoc-pill--empty' }}">
                                        {{ $case->spoc?->name ?? 'Not assigned' }}
                                    </span>
                                </td>
                                <td>{{ $responderName ?? '—' }}</td>
                                <td>
                                    <span class="svc-response {{ $responseClass }}">
                                        @if ($case->status === \App\Models\ServiceCase::STATUS_APPROVED)
                                            <span aria-hidden="true">&#10003;</span>
                                        @endif
                                        {{ $responseText }}
                                    </span>
                                </td>
                                <td>
                                    <span class="svc-status-pill svc-status-pill--{{ $statusSlug }}">
                                        {{ str_replace('_', ' ', $case->status) }}
                                    </span>
                                    @if (!is_null($pendingDays))
                                        <div class="svc-muted">
                                            Pending from {{ $pendingDays }} day{{ $pendingDays === 1 ? '' : 's' }}
                                        </div>
                                    @endif
                                </td>
                                <td style="white-space:nowrap;">{{ $case->updated_at?->timezone(config('app.timezone'))->format('d M Y H:i') }}</td>
                                <td>
                                    <div class="svc-action-group">
                                        <a href="{{ route('staff.services.show', $case) }}" class="svc-btn-xs svc-btn-xs--view">View</a>
                                        @if (($filterScope ?? 'my') === 'my')
                                            <a href="{{ route('staff.services.edit', $case) }}" class="svc-btn-xs svc-btn-xs--edit">Edit</a>
                                        @endif
                                        @if ($case->attachments->isNotEmpty())
                                            @php
                                                $doc = $case->attachments->first();
                                            @endphp
                                            <button
                                                type="button"
                                                class="svc-btn-xs svc-btn-xs--doc js-doc-open"
                                                data-doc-url="{{ route('staff.services.attachments.download', [$case, $doc]) }}"
                                                data-doc-name="{{ $doc->original_name }}"
                                            >
                                                View document
                                            </button>
                                        @endif
                                        @if (($staffDeleteEnabled ?? true) && ($filterScope ?? 'my') === 'my' && $case->canBeDeletedByStaff())
                                            <form method="post" action="{{ route('staff.services.destroy', $case) }}" style="display:inline;" onsubmit="return confirm('Delete this case?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="svc-btn-xs svc-btn-xs--delete">Delete</button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                            @php $listSrNo++; @endphp
                            @endif
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        <div style="margin-top:0.75rem;">{{ $cases->links() }}</div>
    @endif

    <div id="svcDocModal" class="svc-doc-modal" aria-hidden="true">
        <div class="svc-doc-modal__card" role="dialog" aria-modal="true" aria-label="Document preview">
            <div class="svc-doc-modal__head">
                <div id="svcDocTitle" class="svc-doc-modal__title">Document</div>
                <button type="button" id="svcDocClose" class="svc-doc-modal__close">Close</button>
            </div>
            <div id="svcDocBody" class="svc-doc-modal__body"></div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var searchInput = document.getElementById('globalSearch');
            var rows = Array.prototype.slice.call(document.querySelectorAll('.svc-row'));
            if (searchInput) {
                searchInput.addEventListener('input', function () {
                    var q = (searchInput.value || '').trim().toLowerCase();
                    rows.forEach(function (row) {
                        var haystack = (row.getAttribute('data-search') || '').toLowerCase();
                        row.style.display = haystack.indexOf(q) !== -1 ? '' : 'none';
                    });
                });
            }

            var modal = document.getElementById('svcDocModal');
            var modalBody = document.getElementById('svcDocBody');
            var modalTitle = document.getElementById('svcDocTitle');
            var closeBtn = document.getElementById('svcDocClose');
            var openButtons = Array.prototype.slice.call(document.querySelectorAll('.js-doc-open'));
            var currentObjectUrl = null;

            function cleanupObjectUrl() {
                if (currentObjectUrl) {
                    URL.revokeObjectURL(currentObjectUrl);
                    currentObjectUrl = null;
                }
            }

            function closeModal() {
                cleanupObjectUrl();
                modal.classList.remove('is-open');
                modal.setAttribute('aria-hidden', 'true');
                modalBody.innerHTML = '';
            }

            function openModal(url, name) {
                cleanupObjectUrl();
                modalTitle.textContent = name || 'Document';
                modalBody.innerHTML = '';

                var lower = (name || url || '').toLowerCase();
                if (lower.endsWith('.pdf')) {
                    var frame = document.createElement('iframe');
                    frame.className = 'svc-doc-modal__frame';
                    frame.src = url;
                    frame.title = name || 'Document';
                    modalBody.appendChild(frame);
                } else if (/\.(png|jpg|jpeg|webp|gif)$/i.test(lower)) {
                    var img = document.createElement('img');
                    img.className = 'svc-doc-modal__img';
                    img.alt = name || 'Document image';
                    img.src = url;
                    modalBody.appendChild(img);
                } else {
                    var fallback = document.createElement('div');
                    fallback.className = 'svc-doc-modal__fallback';
                    fallback.innerHTML = 'Preview not supported for this file type. <a href="' + url + '" target="_blank" rel="noopener">Open document</a>.';
                    modalBody.appendChild(fallback);
                }

                modal.classList.add('is-open');
                modal.setAttribute('aria-hidden', 'false');
            }

            openButtons.forEach(function (btn) {
                btn.addEventListener('click', function () {
                    openModal(btn.getAttribute('data-doc-url') || '', btn.getAttribute('data-doc-name') || 'Document');
                });
            });

            if (closeBtn) {
                closeBtn.addEventListener('click', closeModal);
            }
            if (modal) {
                modal.addEventListener('click', function (e) {
                    if (e.target === modal) closeModal();
                });
            }
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape' && modal && modal.classList.contains('is-open')) {
                    closeModal();
                }
            });
        });
    </script>
@endsection
