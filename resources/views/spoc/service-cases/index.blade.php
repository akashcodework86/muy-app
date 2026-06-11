@extends('layouts.admin')

@section('title', 'SPOC service queue')
@section('heading', 'Service approval queue')

@section('content')
    <style>
        .sq-wrap { display: grid; gap: 0.9rem; }
        .sq-alert-ok {
            background: #dcfce7; color: #166534; border: 1px solid #bbf7d0;
            padding: 0.55rem 0.75rem; border-radius: 8px; font-size: 0.88rem;
        }
        .sq-alert-err {
            color: #b91c1c; border: 1px solid #fecaca; background: #fef2f2;
            margin: 0; padding: 0.55rem 0.8rem 0.55rem 1.8rem; border-radius: 8px; font-size: 0.85rem;
        }
        .sq-tabs { display: flex; flex-wrap: wrap; gap: 0.45rem; }
        .sq-tab {
            text-decoration: none; border: 1px solid #e4e4e7; background: #fff; color: #3f3f46;
            padding: 0.42rem 0.78rem; border-radius: 999px; font-size: 0.81rem; font-weight: 700;
        }
        .sq-tab.is-active { border-color: #4f46e5; background: #eef2ff; color: #3730a3; }
        .sq-kpis {
            display: grid; gap: 0.65rem; grid-template-columns: repeat(auto-fit, minmax(10rem, 1fr));
        }
        .sq-kpi {
            border: 1px solid #e5e7eb; background: #fff; border-radius: 10px; padding: 0.55rem 0.7rem;
        }
        .sq-kpi-label { font-size: 0.7rem; color: #64748b; text-transform: uppercase; font-weight: 700; letter-spacing: 0.06em; }
        .sq-kpi-value { font-size: 1.2rem; font-weight: 800; color: #0f172a; margin-top: 0.12rem; }
        .sq-table-card { border: 1px solid #e5e7eb; border-radius: 12px; background: #fff; overflow: hidden; }
        .sq-toolbar { padding: 0.6rem 0.75rem; border-bottom: 1px solid #f1f5f9; background: #fafafa; }
        .sq-search {
            width: 100%; max-width: 26rem; border: 1px solid #d1d5db; border-radius: 9px;
            padding: 0.42rem 0.65rem; font-size: 0.86rem;
        }
        .sq-table-wrap { overflow-x: auto; }
        .sq-table { width: 100%; border-collapse: collapse; min-width: 980px; font-size: 0.84rem; }
        .sq-table th { text-align: left; font-size: 0.76rem; color: #64748b; background: #f8fafc; padding: 0.56rem 0.65rem; border-bottom: 1px solid #e5e7eb; }
        .sq-table td { padding: 0.56rem 0.65rem; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
        .sq-table tr:hover td { background: #fcfcff; }
        .sq-muted { font-size: 0.75rem; color: #71717a; margin-top: 0.1rem; }
        .sq-status {
            display: inline-flex; border-radius: 999px; padding: 0.15rem 0.55rem; font-size: 0.75rem; font-weight: 700;
            text-transform: capitalize;
        }
        .sq-status--pending_approval { background: #fff7ed; color: #9a3412; }
        .sq-status--sent_back { background: #fee2e2; color: #b91c1c; }
        .sq-status--approved { background: #dcfce7; color: #166534; }
        .sq-status--rejected { background: #fce7f3; color: #9d174d; }
        .sq-actions { display: inline-flex; gap: 0.35rem; flex-wrap: wrap; }
        .sq-btn {
            border: 1px solid #d1d5db; background: #fff; color: #111827; border-radius: 8px;
            padding: 0.3rem 0.55rem; font-size: 0.76rem; font-weight: 700; text-decoration: none; cursor: pointer;
        }
        .sq-btn--primary { border-color: #4f46e5; background: #eef2ff; color: #3730a3; }
        .sq-btn--ok { border-color: #86efac; background: #f0fdf4; color: #166534; }
        .sq-btn--warn { border-color: #fdba74; background: #fff7ed; color: #9a3412; }
        .sq-btn--danger { border-color: #fecaca; background: #fef2f2; color: #991b1b; }
        .sq-btn--doc { border-color: #cbd5e1; background: #f8fafc; color: #0f172a; }
        .sq-bulk-bar { display: flex; gap: 0.45rem; flex-wrap: wrap; align-items: center; margin-bottom: 0.5rem; }
        .sq-bulk-hint { font-size: 0.78rem; color: #64748b; }
        .sq-check { width: 1rem; height: 1rem; cursor: pointer; }

        .sq-modal { position: fixed; inset: 0; display: none; align-items: center; justify-content: center; background: rgba(15,23,42,0.55); z-index: 80; padding: 1rem; }
        .sq-modal.is-open { display: flex; }
        .sq-modal-card {
            width: min(840px, 96vw); max-height: 92vh; overflow: auto; background: #fff;
            border-radius: 12px; border: 1px solid #e5e7eb; box-shadow: 0 20px 45px rgba(15, 23, 42, 0.28);
        }
        .sq-modal-head {
            padding: 0.7rem 0.9rem; border-bottom: 1px solid #e5e7eb; display: flex; justify-content: space-between; align-items: center;
        }
        .sq-modal-title { font-size: 0.95rem; font-weight: 800; color: #111827; }
        .sq-modal-close { border: 1px solid #cbd5e1; background: #f8fafc; border-radius: 8px; padding: 0.22rem 0.5rem; cursor: pointer; font-weight: 700; }
        .sq-modal-body { padding: 0.85rem 0.95rem; display: grid; gap: 0.8rem; }
        .sq-meta { font-size: 0.84rem; color: #334155; display: grid; gap: 0.28rem; }
        .sq-meta b { color: #0f172a; }
        .sq-review-grid { display: grid; gap: 0.75rem; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); }
        .sq-review-box { border: 1px solid #e5e7eb; border-radius: 10px; padding: 0.65rem; background: #fff; }
        .sq-review-box h4 { margin: 0 0 0.35rem; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.06em; color: #64748b; }
        .sq-note { width: 100%; min-height: 86px; border: 1px solid #d1d5db; border-radius: 8px; padding: 0.45rem 0.55rem; font-size: 0.83rem; resize: vertical; }
    </style>

    <div class="sq-wrap">
    @if (session('status'))
        <p class="sq-alert-ok">{{ session('status') }}</p>
    @endif
    @if ($errors->any())
        <ul class="sq-alert-err">
            @foreach ($errors->all() as $e)
                <li>{{ $e }}</li>
            @endforeach
        </ul>
    @endif

    @php
        use App\Models\ServiceCase;
        $districtNameById = ($districtOptions ?? collect())->pluck('name', 'id');
        $tabs = [
            '' => 'All',
            ServiceCase::STATUS_PENDING_APPROVAL => 'Pending approval',
            ServiceCase::STATUS_SENT_BACK => 'Sent back',
            ServiceCase::STATUS_APPROVED => 'Approved',
            ServiceCase::STATUS_REJECTED => 'Rejected',
        ];
    @endphp

    <div class="sq-tabs">
        @foreach ($tabs as $val => $label)
            <a href="{{ route('spoc.service-cases.index', array_filter([
                'status' => $val !== '' ? $val : null,
                'district_id' => (int) ($filterDistrictId ?? 0) ?: null,
                'batch_id' => (int) ($filterBatchId ?? 0) ?: null,
            ])) }}"
                class="sq-tab {{ ($filterStatus === $val) ? 'is-active' : '' }}">
                {{ $label }} ({{ number_format((int) ($tabCounts[$val] ?? 0)) }})
            </a>
        @endforeach
    </div>

    <div class="sq-kpis">
        <div class="sq-kpi">
            <div class="sq-kpi-label">Pending approval</div>
            <div class="sq-kpi-value">{{ number_format((int) ($tabCounts[\App\Models\ServiceCase::STATUS_PENDING_APPROVAL] ?? 0)) }}</div>
        </div>
        <div class="sq-kpi">
            <div class="sq-kpi-label">Approved</div>
            <div class="sq-kpi-value">{{ number_format((int) ($tabCounts[\App\Models\ServiceCase::STATUS_APPROVED] ?? 0)) }}</div>
        </div>
        <div class="sq-kpi">
            <div class="sq-kpi-label">Sent back</div>
            <div class="sq-kpi-value">{{ number_format((int) ($tabCounts[\App\Models\ServiceCase::STATUS_SENT_BACK] ?? 0)) }}</div>
        </div>
        <div class="sq-kpi">
            <div class="sq-kpi-label">Rejected</div>
            <div class="sq-kpi-value">{{ number_format((int) ($tabCounts[\App\Models\ServiceCase::STATUS_REJECTED] ?? 0)) }}</div>
        </div>
    </div>

    @if (($spocDistrictIds ?? []) === [])
        <p style="color:#b45309;font-size:0.9rem;background:#fffbeb;border:1px solid #fcd34d;padding:0.65rem 0.85rem;border-radius:8px;">
            No districts are assigned to you yet. Ask the state admin to assign you on <strong>Service SPOCs</strong> before you can review submissions.
        </p>
    @elseif (!($marketLinkageWorkflowReady ?? true))
        <p style="color:#b45309;font-size:0.9rem;background:#fffbeb;border:1px solid #fcd34d;padding:0.65rem 0.85rem;border-radius:8px;margin-bottom:0.75rem;">
            Market linkage approval workflow is not active. Run <code>php artisan migrate</code> to enable market linkage in this queue.
        </p>
    @endif

    @if ($cases->isEmpty())
        <p style="color:#71717a;font-size:0.9rem;">No service cases or market linkage submissions in this view.</p>
    @else
        <div class="sq-table-card">
            <div class="sq-toolbar">
                <form method="get" style="display:flex;gap:0.45rem;flex-wrap:wrap;margin-bottom:0.5rem;">
                    @if (!empty($filterStatus))
                        <input type="hidden" name="status" value="{{ $filterStatus }}">
                    @endif
                    <select name="district_id" class="sq-search" style="max-width:15rem;">
                        <option value="">All districts</option>
                        @foreach (($districtOptions ?? collect()) as $district)
                            <option value="{{ (int) $district->id }}" @selected((int) ($filterDistrictId ?? 0) === (int) $district->id)>
                                {{ $district->name }}
                            </option>
                        @endforeach
                    </select>
                    <select name="batch_id" class="sq-search" style="max-width:20rem;">
                        <option value="">All batches</option>
                        @foreach (($batchOptions ?? collect()) as $batch)
                            @php $dName = $districtNameById->get($batch->district_id); @endphp
                            <option value="{{ (int) $batch->id }}" @selected((int) ($filterBatchId ?? 0) === (int) $batch->id)>
                                {{ $batch->name }}@if($dName) — {{ $dName }}@endif
                            </option>
                        @endforeach
                    </select>
                    <button type="submit" class="sq-btn">Apply</button>
                </form>
                <input id="sqSearch" type="text" class="sq-search" placeholder="Search incubatee, app no, service, submitter, status">
                @if ($canBulkApprove ?? false)
                    <form id="sqBulkForm" method="post" action="{{ route('spoc.service-cases.bulk-approve') }}" style="display:none;">
                        @csrf
                        <input type="hidden" name="redirect_to" value="{{ request()->fullUrl() }}">
                    </form>
                    <div class="sq-bulk-bar">
                        <label style="display:inline-flex;align-items:center;gap:0.35rem;font-size:0.82rem;font-weight:700;color:#334155;">
                            <input type="checkbox" id="sqBulkSelectAll" class="sq-check" aria-label="Select all pending on this page">
                            Select pending on page
                        </label>
                        <button type="submit" form="sqBulkForm" class="sq-btn sq-btn--ok" id="sqBulkSubmit" disabled>
                            Approve selected (<span id="sqBulkCount">0</span>)
                        </button>
                        <span class="sq-bulk-hint">Tick pending service cases, then approve in one go.</span>
                    </div>
                @endif
            </div>
            <div class="sq-table-wrap">
            <table class="sq-table">
                <thead>
                    <tr>
                        @if ($canBulkApprove ?? false)
                            <th style="width:2.4rem;"></th>
                        @endif
                        <th>Incubatee</th>
                        <th>Service</th>
                        <th>District</th>
                        <th>Batch</th>
                        <th>Submitted by</th>
                        <th>Status</th>
                        <th>Updated</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($cases as $row)
                        @php
                            $rowKind = is_array($row) ? (string) ($row['kind'] ?? '') : '';
                            $case = is_array($row) ? ($row['service_case'] ?? null) : null;
                            $ml = is_array($row) ? ($row['market_linkage'] ?? null) : null;
                        @endphp
                        @if ($rowKind === 'market_linkage' && $ml)
                        @php
                            $statusClass = strtolower((string) $ml->status);
                            $search = strtolower(trim(
                                ($ml->incubatee_name ?? '').' '.
                                ($ml->application_no ?? '').' '.
                                'market linkage '.
                                ($ml->district_name ?? $ml->district?->name ?? '').' '.
                                ($ml->submitted_by_name ?? $ml->submitter?->name ?? '').' '.
                                str_replace('_', ' ', (string) $ml->status)
                            ));
                            $isPending = $ml->status === \App\Models\ServiceCase::STATUS_PENDING_APPROVAL;
                        @endphp
                        <tr data-search="{{ $search }}" style="background:#faf5ff;">
                            @if ($canBulkApprove ?? false)
                                <td></td>
                            @endif
                            <td>
                                <strong>{{ $ml->incubatee_name }}</strong>
                                @if ($ml->application_no)
                                    <div class="sq-muted">{{ $ml->application_no }}</div>
                                @endif
                            </td>
                            <td>{{ \App\Models\MarketLinkageSubmission::SERVICE_LIST_LABEL }} <span class="sq-muted">({{ $ml->partners->count() }})</span></td>
                            <td>{{ $ml->district_name ?? $ml->district?->name ?? '—' }}</td>
                            <td>—</td>
                            <td>{{ $ml->submitted_by_name ?? $ml->submitter?->name ?? '—' }}</td>
                            <td>
                                <span class="sq-status sq-status--{{ $statusClass }}">{{ str_replace('_', ' ', (string) $ml->status) }}</span>
                            </td>
                            <td style="white-space:nowrap;">{{ $ml->updated_at?->timezone(config('app.timezone'))->format('d M Y H:i') }}</td>
                            <td>
                                <div class="sq-actions">
                                    <a href="{{ route('spoc.market-linkages.show', $ml) }}" class="sq-btn sq-btn--primary">Review</a>
                                </div>
                            </td>
                        </tr>
                        @elseif ($case)
                        @php
                            $lip = $case->legacyIncubateePreview ?? null;
                            $statusClass = strtolower((string) $case->status);
                            $search = strtolower(trim(
                                ($case->cfaSubmission?->applicant_name ?? (is_array($lip) ? ($lip['applicant_name'] ?? '') : '')).' '.
                                ($case->cfaSubmission?->application_no ?? (is_array($lip) ? ($lip['application_no'] ?? '') : '')).' '.
                                ($case->service?->name ?? '').' '.
                                ($case->cfaSubmission?->district?->name ?? (is_array($lip) ? ($lip['district'] ?? '') : '')).' '.
                                ($case->cfaSubmission?->onboardingBatchMembership?->batch?->name ?? '').' '.
                                ($case->submitter?->name ?? '').' '.
                                str_replace('_', ' ', (string) $case->status)
                            ));
                            $isPending = $case->status === \App\Models\ServiceCase::STATUS_PENDING_APPROVAL;
                        @endphp
                        <tr data-search="{{ $search }}">
                            @if ($canBulkApprove ?? false)
                                <td>
                                    @if ($isPending)
                                        <input
                                            type="checkbox"
                                            form="sqBulkForm"
                                            name="case_ids[]"
                                            value="{{ (int) $case->id }}"
                                            class="sq-check js-bulk-case"
                                            aria-label="Select case {{ $case->cfaSubmission?->application_no ?? $case->id }}"
                                        >
                                    @endif
                                </td>
                            @endif
                            <td>
                                <strong>{{ $case->cfaSubmission?->applicant_name ?? (is_array($lip) ? ($lip['applicant_name'] ?? '—') : '—') }}</strong>
                                @if ($case->cfaSubmission?->application_no)
                                    <div class="sq-muted">{{ $case->cfaSubmission->application_no }}</div>
                                @elseif (is_array($lip) && ($lip['application_no'] ?? '') !== '')
                                    <div class="sq-muted">{{ $lip['application_no'] }} <span style="color:#94a3b8;">(legacy)</span></div>
                                @endif
                            </td>
                            <td>{{ $case->service?->name ?? '—' }}</td>
                            <td>{{ $case->cfaSubmission?->district?->name ?? (is_array($lip) ? ($lip['district'] ?? '—') : '—') }}</td>
                            <td>{{ $case->cfaSubmission?->onboardingBatchMembership?->batch?->name ?? '—' }}</td>
                            <td>{{ $case->submitter?->name ?? '—' }}</td>
                            <td>
                                <span class="sq-status sq-status--{{ $statusClass }}">{{ str_replace('_', ' ', $case->status) }}</span>
                            </td>
                            <td style="white-space:nowrap;">{{ $case->updated_at?->timezone(config('app.timezone'))->format('d M Y H:i') }}</td>
                            <td>
                                <div class="sq-actions">
                                    @if ($isPending)
                                        <button
                                            type="button"
                                            class="sq-btn sq-btn--primary js-review-open"
                                            data-applicant="{{ $case->cfaSubmission?->applicant_name ?? (is_array($lip) ? ($lip['applicant_name'] ?? '—') : '—') }}"
                                            data-app-no="{{ $case->cfaSubmission?->application_no ?? (is_array($lip) ? ($lip['application_no'] ?? '—') : '—') }}"
                                            data-service="{{ $case->service?->name ?? '—' }}"
                                            data-submitter="{{ $case->submitter?->name ?? '—' }}"
                                            data-updated="{{ $case->updated_at?->timezone(config('app.timezone'))->format('d M Y H:i') }}"
                                            data-open-url="{{ route('spoc.service-cases.show', $case) }}"
                                            data-approve-url="{{ route('spoc.service-cases.approve', $case) }}"
                                            data-send-back-url="{{ route('spoc.service-cases.send-back', $case) }}"
                                            data-reject-url="{{ route('spoc.service-cases.reject', $case) }}"
                                        >Quick review</button>
                                    @endif
                                    @if ($case->attachments->isNotEmpty())
                                        @php $doc = $case->attachments->first(); @endphp
                                        <button
                                            type="button"
                                            class="sq-btn sq-btn--doc js-doc-open"
                                            data-doc-url="{{ route('spoc.service-cases.attachments.download', [$case, $doc]) }}"
                                            data-doc-name="{{ $doc->original_name }}"
                                        >View document</button>
                                    @endif
                                    <a href="{{ route('spoc.service-cases.show', $case) }}" class="sq-btn">Open full</a>
                                </div>
                            </td>
                        </tr>
                        @endif
                    @endforeach
                </tbody>
            </table>
            </div>
        </div>
        <div style="margin-top:0.75rem;">{{ $cases->links() }}</div>
    @endif
    </div>

    <div id="sqReviewModal" class="sq-modal" aria-hidden="true">
        <div class="sq-modal-card" role="dialog" aria-modal="true" aria-label="Quick review">
            <div class="sq-modal-head">
                <div class="sq-modal-title">Quick review</div>
                <button type="button" id="sqReviewClose" class="sq-modal-close">Close</button>
            </div>
            <div class="sq-modal-body">
                <div class="sq-meta">
                    <div><b>Incubatee:</b> <span id="sqMetaApplicant">—</span></div>
                    <div><b>Application no:</b> <span id="sqMetaAppNo">—</span></div>
                    <div><b>Service:</b> <span id="sqMetaService">—</span></div>
                    <div><b>Submitted by:</b> <span id="sqMetaSubmitter">—</span> · <b>Updated:</b> <span id="sqMetaUpdated">—</span></div>
                </div>

                <div class="sq-review-grid">
                    <div class="sq-review-box">
                        <h4>Approve</h4>
                        <form id="sqApproveForm" method="post" action="">
                            @csrf
                            <input id="sqApproveRedirect" type="hidden" name="redirect_to" value="">
                            <button type="submit" class="sq-btn sq-btn--ok" onclick="return confirm('Approve this case?')">Approve now</button>
                        </form>
                    </div>

                    <div class="sq-review-box">
                        <h4>Send back</h4>
                        <form id="sqSendBackForm" method="post" action="">
                            @csrf
                            <input id="sqSendBackRedirect" type="hidden" name="redirect_to" value="">
                            <textarea name="note" required class="sq-note" placeholder="What should staff fix?"></textarea>
                            <div style="margin-top:0.45rem;">
                                <button type="submit" class="sq-btn sq-btn--warn">Send back</button>
                            </div>
                        </form>
                    </div>

                    <div class="sq-review-box">
                        <h4>Reject</h4>
                        <form id="sqRejectForm" method="post" action="">
                            @csrf
                            <input id="sqRejectRedirect" type="hidden" name="redirect_to" value="">
                            <textarea name="note" required class="sq-note" placeholder="Why are you rejecting this case?"></textarea>
                            <div style="margin-top:0.45rem;">
                                <button type="submit" class="sq-btn sq-btn--danger">Reject</button>
                            </div>
                        </form>
                    </div>
                </div>

                <div>
                    <a id="sqOpenFull" href="#" class="sq-btn">Open full case page</a>
                </div>
            </div>
        </div>
    </div>

    <div id="sqDocModal" class="sq-modal" aria-hidden="true">
        <div class="sq-modal-card" role="dialog" aria-modal="true" aria-label="Document preview">
            <div class="sq-modal-head">
                <div id="sqDocTitle" class="sq-modal-title">Document</div>
                <button type="button" id="sqDocClose" class="sq-modal-close">Close</button>
            </div>
            <div id="sqDocBody" class="sq-modal-body"></div>
        </div>
    </div>

    <script>
        (function () {
            const rows = Array.from(document.querySelectorAll('tr[data-search]'));
            const search = document.getElementById('sqSearch');
            if (search) {
                search.addEventListener('input', function () {
                    const q = (search.value || '').trim().toLowerCase();
                    rows.forEach(function (row) {
                        const hay = row.getAttribute('data-search') || '';
                        row.style.display = hay.includes(q) ? '' : 'none';
                    });
                });
            }

            const modal = document.getElementById('sqReviewModal');
            const closeBtn = document.getElementById('sqReviewClose');
            const applicant = document.getElementById('sqMetaApplicant');
            const appNo = document.getElementById('sqMetaAppNo');
            const service = document.getElementById('sqMetaService');
            const submitter = document.getElementById('sqMetaSubmitter');
            const updated = document.getElementById('sqMetaUpdated');
            const openFull = document.getElementById('sqOpenFull');
            const approveForm = document.getElementById('sqApproveForm');
            const sendBackForm = document.getElementById('sqSendBackForm');
            const rejectForm = document.getElementById('sqRejectForm');
            const approveRedirect = document.getElementById('sqApproveRedirect');
            const sendBackRedirect = document.getElementById('sqSendBackRedirect');
            const rejectRedirect = document.getElementById('sqRejectRedirect');
            const openButtons = Array.from(document.querySelectorAll('.js-review-open'));
            const docButtons = Array.from(document.querySelectorAll('.js-doc-open'));
            const docModal = document.getElementById('sqDocModal');
            const docClose = document.getElementById('sqDocClose');
            const docBody = document.getElementById('sqDocBody');
            const docTitle = document.getElementById('sqDocTitle');

            function setModal(open) {
                if (!modal) return;
                modal.classList.toggle('is-open', open);
                modal.setAttribute('aria-hidden', open ? 'false' : 'true');
            }

            openButtons.forEach(function (btn) {
                btn.addEventListener('click', function () {
                    applicant.textContent = btn.dataset.applicant || '—';
                    appNo.textContent = btn.dataset.appNo || '—';
                    service.textContent = btn.dataset.service || '—';
                    submitter.textContent = btn.dataset.submitter || '—';
                    updated.textContent = btn.dataset.updated || '—';
                    openFull.href = btn.dataset.openUrl || '#';
                    approveForm.action = btn.dataset.approveUrl || '';
                    sendBackForm.action = btn.dataset.sendBackUrl || '';
                    rejectForm.action = btn.dataset.rejectUrl || '';
                    const currentUrl = window.location.href || '';
                    if (approveRedirect) approveRedirect.value = currentUrl;
                    if (sendBackRedirect) sendBackRedirect.value = currentUrl;
                    if (rejectRedirect) rejectRedirect.value = currentUrl;
                    sendBackForm.reset();
                    rejectForm.reset();
                    setModal(true);
                });
            });

            closeBtn && closeBtn.addEventListener('click', function () { setModal(false); });
            modal && modal.addEventListener('click', function (e) {
                if (e.target === modal) setModal(false);
            });
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') setModal(false);
            });

            function setDocModal(open) {
                if (!docModal) return;
                docModal.classList.toggle('is-open', open);
                docModal.setAttribute('aria-hidden', open ? 'false' : 'true');
            }
            function renderDoc(url, name) {
                docTitle.textContent = name || 'Document';
                docBody.innerHTML = '';
                const lower = (name || url || '').toLowerCase();
                if (lower.endsWith('.pdf')) {
                    const frame = document.createElement('iframe');
                    frame.src = url;
                    frame.style.width = '100%';
                    frame.style.minHeight = '72vh';
                    frame.style.border = '1px solid #e2e8f0';
                    frame.style.borderRadius = '10px';
                    docBody.appendChild(frame);
                } else if (/\.(png|jpg|jpeg|webp|gif)$/i.test(lower)) {
                    const img = document.createElement('img');
                    img.src = url;
                    img.alt = name || 'Document image';
                    img.style.maxWidth = '100%';
                    img.style.maxHeight = '72vh';
                    img.style.margin = '0 auto';
                    img.style.display = 'block';
                    img.style.border = '1px solid #e2e8f0';
                    img.style.borderRadius = '10px';
                    docBody.appendChild(img);
                } else {
                    const fallback = document.createElement('div');
                    fallback.style.fontSize = '0.86rem';
                    fallback.innerHTML = 'Preview not supported. <a href="' + url + '" target="_blank" rel="noopener">Open document</a>.';
                    docBody.appendChild(fallback);
                }
                setDocModal(true);
            }
            docButtons.forEach(function (btn) {
                btn.addEventListener('click', function () {
                    renderDoc(btn.dataset.docUrl || '', btn.dataset.docName || 'Document');
                });
            });
            docClose && docClose.addEventListener('click', function () { setDocModal(false); });
            docModal && docModal.addEventListener('click', function (e) {
                if (e.target === docModal) setDocModal(false);
            });
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') setDocModal(false);
            });

            @if ($canBulkApprove ?? false)
            (function () {
                const bulkForm = document.getElementById('sqBulkForm');
                const bulkChecks = Array.from(document.querySelectorAll('.js-bulk-case'));
                const bulkSelectAll = document.getElementById('sqBulkSelectAll');
                const bulkSubmit = document.getElementById('sqBulkSubmit');
                const bulkCount = document.getElementById('sqBulkCount');

                function visibleBulkChecks() {
                    return bulkChecks.filter(function (cb) {
                        const row = cb.closest('tr');
                        return row && row.style.display !== 'none';
                    });
                }

                function refreshBulkUi() {
                    if (!bulkSubmit || !bulkCount) return;
                    const selected = bulkChecks.filter(function (cb) { return cb.checked; }).length;
                    bulkCount.textContent = String(selected);
                    bulkSubmit.disabled = selected === 0;
                    if (bulkSelectAll) {
                        const visible = visibleBulkChecks();
                        bulkSelectAll.checked = visible.length > 0 && visible.every(function (cb) { return cb.checked; });
                        bulkSelectAll.indeterminate = visible.some(function (cb) { return cb.checked; }) && !bulkSelectAll.checked;
                    }
                }

                bulkChecks.forEach(function (cb) {
                    cb.addEventListener('change', refreshBulkUi);
                });

                bulkSelectAll && bulkSelectAll.addEventListener('change', function () {
                    const checked = bulkSelectAll.checked;
                    visibleBulkChecks().forEach(function (cb) { cb.checked = checked; });
                    refreshBulkUi();
                });

                if (search) {
                    search.addEventListener('input', refreshBulkUi);
                }

                bulkForm && bulkForm.addEventListener('submit', function (e) {
                    const selected = bulkChecks.filter(function (cb) { return cb.checked; }).length;
                    if (selected === 0) {
                        e.preventDefault();
                        return;
                    }
                    if (!confirm('Approve ' + selected + ' selected case' + (selected === 1 ? '' : 's') + '?')) {
                        e.preventDefault();
                    }
                });

                refreshBulkUi();
            })();
            @endif
        })();
    </script>
@endsection

