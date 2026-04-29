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
            <a href="{{ route('spoc.service-cases.index', $val !== '' ? ['status' => $val] : []) }}"
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

    @if ($cases->isEmpty())
        <p style="color:#71717a;font-size:0.9rem;">No service cases in your SPOC queue.</p>
    @else
        <div class="sq-table-card">
            <div class="sq-toolbar">
                <input id="sqSearch" type="text" class="sq-search" placeholder="Search incubatee, app no, service, submitter, status">
            </div>
            <div class="sq-table-wrap">
            <table class="sq-table">
                <thead>
                    <tr>
                        <th>Incubatee</th>
                        <th>Service</th>
                        <th>Submitted by</th>
                        <th>Status</th>
                        <th>Updated</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($cases as $case)
                        @php
                            $statusClass = strtolower((string) $case->status);
                            $search = strtolower(trim(
                                ($case->cfaSubmission?->applicant_name ?? '').' '.
                                ($case->cfaSubmission?->application_no ?? '').' '.
                                ($case->service?->name ?? '').' '.
                                ($case->submitter?->name ?? '').' '.
                                str_replace('_', ' ', (string) $case->status)
                            ));
                            $isPending = $case->status === \App\Models\ServiceCase::STATUS_PENDING_APPROVAL;
                        @endphp
                        <tr data-search="{{ $search }}">
                            <td>
                                <strong>{{ $case->cfaSubmission?->applicant_name ?? '—' }}</strong>
                                @if ($case->cfaSubmission?->application_no)
                                    <div class="sq-muted">{{ $case->cfaSubmission->application_no }}</div>
                                @endif
                            </td>
                            <td>{{ $case->service?->name ?? '—' }}</td>
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
                                            data-applicant="{{ $case->cfaSubmission?->applicant_name ?? '—' }}"
                                            data-app-no="{{ $case->cfaSubmission?->application_no ?? '—' }}"
                                            data-service="{{ $case->service?->name ?? '—' }}"
                                            data-submitter="{{ $case->submitter?->name ?? '—' }}"
                                            data-updated="{{ $case->updated_at?->timezone(config('app.timezone'))->format('d M Y H:i') }}"
                                            data-open-url="{{ route('spoc.service-cases.show', $case) }}"
                                            data-approve-url="{{ route('spoc.service-cases.approve', $case) }}"
                                            data-send-back-url="{{ route('spoc.service-cases.send-back', $case) }}"
                                            data-reject-url="{{ route('spoc.service-cases.reject', $case) }}"
                                        >Quick review</button>
                                    @endif
                                    <a href="{{ route('spoc.service-cases.show', $case) }}" class="sq-btn">Open full</a>
                                </div>
                            </td>
                        </tr>
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
                            <button type="submit" class="sq-btn sq-btn--ok" onclick="return confirm('Approve this case?')">Approve now</button>
                        </form>
                    </div>

                    <div class="sq-review-box">
                        <h4>Send back</h4>
                        <form id="sqSendBackForm" method="post" action="">
                            @csrf
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
            const openButtons = Array.from(document.querySelectorAll('.js-review-open'));

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
        })();
    </script>
@endsection

