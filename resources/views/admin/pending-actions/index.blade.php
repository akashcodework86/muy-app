@extends('layouts.admin')

@section('title', 'Pending Actions')
@section('heading', 'Pending Actions' . (! empty($scopeLabel) ? ' — '.$scopeLabel : ''))

@section('content')
    <style>
        .pa-grid { display:grid; gap:0.8rem; }
        .pa-cards { display:grid; gap:0.7rem; grid-template-columns:repeat(auto-fit,minmax(11rem,1fr)); }
        .pa-card { background:#fff; border:1px solid #e5e7eb; border-radius:12px; padding:0.75rem 0.85rem; }
        .pa-k { font-size:0.7rem; text-transform:uppercase; letter-spacing:.06em; color:#64748b; font-weight:700; }
        .pa-v { margin-top:0.15rem; font-size:1.3rem; font-weight:800; color:#0f172a; }
        .pa-sub { margin-top:0.1rem; font-size:0.75rem; color:#94a3b8; }
        .pa-layout { display:grid; gap:0.75rem; grid-template-columns: minmax(0,1.2fr) minmax(0,0.8fr); }
        .pa-panel { background:#fff; border:1px solid #e5e7eb; border-radius:12px; overflow:hidden; }
        .pa-head { padding:0.62rem 0.75rem; border-bottom:1px solid #f1f5f9; font-size:0.8rem; font-weight:800; color:#334155; }
        .pa-list { margin:0; padding:0; list-style:none; }
        .pa-list li { padding:0.58rem 0.75rem; border-bottom:1px solid #f8fafc; display:flex; justify-content:space-between; gap:0.45rem; align-items:center; }
        .pa-list li:last-child { border-bottom:0; }
        .pa-name { font-size:0.82rem; color:#0f172a; font-weight:700; }
        .pa-meta { font-size:0.73rem; color:#64748b; margin-top:0.08rem; }
        .pa-pill { display:inline-flex; align-items:center; border-radius:999px; background:#eef2ff; color:#3730a3; border:1px solid #c7d2fe; font-size:0.74rem; font-weight:800; padding:0.14rem 0.52rem; white-space:nowrap; }
        .pa-tools { background:#fff; border:1px solid #e5e7eb; border-radius:12px; padding:0.65rem 0.75rem; display:flex; gap:0.5rem; flex-wrap:wrap; align-items:flex-end; }
        .pa-lbl { display:block; font-size:0.74rem; color:#64748b; margin-bottom:0.2rem; font-weight:700; }
        .pa-sel { border:1px solid #d1d5db; border-radius:9px; padding:0.42rem 0.55rem; font-size:0.84rem; min-width:14rem; }
        .pa-btn { border:1px solid #d1d5db; background:#fff; border-radius:9px; padding:0.42rem 0.72rem; font-size:0.82rem; font-weight:700; cursor:pointer; }
        .pa-table-wrap { background:#fff; border:1px solid #e5e7eb; border-radius:12px; overflow:auto; }
        .pa-table { width:100%; min-width:1050px; border-collapse:collapse; font-size:0.83rem; }
        .pa-table th { text-align:left; padding:0.55rem 0.62rem; border-bottom:1px solid #e5e7eb; background:#f8fafc; color:#64748b; font-size:0.74rem; text-transform:uppercase; letter-spacing:.05em; }
        .pa-table td { padding:0.55rem 0.62rem; border-bottom:1px solid #f8fafc; vertical-align:middle; }
        .pa-table tr:hover td { background:#fcfcff; }
        .pa-metric-table { min-width: 860px; }
        .pa-act { display:inline-flex; gap:0.34rem; }
        .pa-link { border:1px solid #c7d2fe; color:#3730a3; background:#eef2ff; border-radius:8px; padding:0.28rem 0.5rem; font-size:0.75rem; font-weight:700; text-decoration:none; }
        .pa-link--ghost { border-color:#d1d5db; color:#334155; background:#fff; }
        .pa-mini { font-size:0.74rem; color:#64748b; margin-top:0.12rem; }
        .pa-status { font-size:0.74rem; font-weight:800; color:#9a3412; background:#fff7ed; border:1px solid #fed7aa; border-radius:999px; padding:0.12rem 0.48rem; display:inline-flex; }
        .pa-modal { position:fixed; inset:0; display:none; align-items:center; justify-content:center; background:rgba(15,23,42,0.55); z-index:80; padding:1rem; }
        .pa-modal.is-open { display:flex; }
        .pa-modal-card { width:min(760px,96vw); max-height:90vh; overflow:auto; background:#fff; border-radius:12px; border:1px solid #e5e7eb; box-shadow:0 20px 45px rgba(15,23,42,.3); }
        .pa-modal-head { display:flex; justify-content:space-between; align-items:center; gap:0.8rem; padding:0.7rem 0.85rem; border-bottom:1px solid #e5e7eb; }
        .pa-modal-title { font-size:0.9rem; font-weight:800; color:#111827; }
        .pa-close { border:1px solid #cbd5e1; background:#f8fafc; border-radius:8px; font-size:0.8rem; padding:0.2rem 0.48rem; cursor:pointer; font-weight:700; }
        .pa-modal-body { padding:0.82rem 0.9rem; display:grid; gap:0.58rem; }
        .pa-row { font-size:0.84rem; color:#334155; }
        .pa-row b { color:#0f172a; }
        @media (max-width: 1100px) { .pa-layout { grid-template-columns: 1fr; } }
    </style>

    <div class="pa-grid">
        @if (! empty($scopeLabel))
            <p style="margin:0;font-size:0.85rem;color:#64748b;">Showing service-case backlog for districts under <strong>{{ $scopeLabel }}</strong>.</p>
        @endif
        <div class="pa-cards">
            <div class="pa-card">
                <div class="pa-k">Total submissions</div>
                <div class="pa-v">{{ number_format((int) $totalSubmissions) }}</div>
                <div class="pa-sub">District staff service submissions</div>
            </div>
            <div class="pa-card">
                <div class="pa-k">Total pending</div>
                <div class="pa-v">{{ number_format((int) $totalPending) }}</div>
                <div class="pa-sub">{{ number_format((int) $totalPending) }} of {{ number_format((int) $totalSubmissions) }} awaiting action</div>
            </div>
            <div class="pa-card">
                <div class="pa-k">Pending ratio</div>
                <div class="pa-v">{{ number_format((float) $pendingRate, 1) }}%</div>
                <div class="pa-sub">Pending vs total submissions</div>
            </div>
            <div class="pa-card">
                <div class="pa-k">SPOCs with pending</div>
                <div class="pa-v">{{ number_format((int) $spocsWithPending) }}</div>
            </div>
            <div class="pa-card">
                <div class="pa-k">Districts impacted</div>
                <div class="pa-v">{{ number_format((int) $districtsImpacted) }}</div>
            </div>
            <div class="pa-card">
                <div class="pa-k">Applications affected</div>
                <div class="pa-v">{{ number_format((int) $pendingUniqueApplications) }}</div>
                <div class="pa-sub">{{ number_format((float) $affectedApplicationRate, 1) }}% of {{ number_format((int) $totalUniqueApplications) }} unique applications</div>
            </div>
            <div class="pa-card">
                <div class="pa-k">Average age (days)</div>
                <div class="pa-v">{{ number_format((int) $avgPendingDays) }}</div>
                <div class="pa-sub">
                    @if ($oldestPendingAt)
                        Oldest from {{ \Illuminate\Support\Carbon::parse($oldestPendingAt)->format('d M Y H:i') }}
                    @else
                        No pending cases
                    @endif
                </div>
            </div>
        </div>

        <div class="pa-layout">
            <div class="pa-panel">
                <div class="pa-head">SPOC-wise backlog</div>
                <ul class="pa-list">
                    @forelse ($spocStats as $row)
                        <li>
                            <div>
                                <div class="pa-name">{{ $row['spoc_name'] }}</div>
                                <div class="pa-meta">{{ number_format((int) $row['district_count']) }} district(s)</div>
                            </div>
                            <span class="pa-pill">{{ number_format((int) $row['pending_count']) }} pending</span>
                        </li>
                    @empty
                        <li><span class="pa-meta">No pending actions.</span></li>
                    @endforelse
                </ul>
            </div>
            <div class="pa-panel">
                <div class="pa-head">District-wise pending</div>
                <ul class="pa-list">
                    @forelse ($districtStats as $row)
                        <li>
                            <div class="pa-name">{{ $row->district_name }}</div>
                            <span class="pa-pill">{{ number_format((int) $row->pending_count) }}</span>
                        </li>
                    @empty
                        <li><span class="pa-meta">No district backlog.</span></li>
                    @endforelse
                </ul>
            </div>
        </div>

        <div class="pa-table-wrap">
            <table class="pa-table pa-metric-table">
                <thead>
                    <tr>
                        <th>SPOC</th>
                        <th>Entries received</th>
                        <th>Approved</th>
                        <th>Sent back</th>
                        <th>Rejected</th>
                        <th>Currently pending</th>
                        <th>Pending %</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($spocPerformance as $row)
                        <tr>
                            <td><strong>{{ $row['spoc_name'] }}</strong></td>
                            <td>{{ number_format((int) $row['entries_received']) }}</td>
                            <td>{{ number_format((int) $row['approved_count']) }}</td>
                            <td>{{ number_format((int) $row['sent_back_count']) }}</td>
                            <td>{{ number_format((int) $row['rejected_count']) }}</td>
                            <td><span class="pa-pill">{{ number_format((int) $row['pending_count']) }}</span></td>
                            <td>{{ number_format((float) $row['pending_rate'], 1) }}%</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="pa-mini">No SPOC-level action data found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <form method="get" action="{{ route($pageRoute ?? 'admin.pending-actions.index') }}" class="pa-tools">
            <div>
                <label class="pa-lbl" for="spoc_id">Filter by SPOC</label>
                <select id="spoc_id" name="spoc_id" class="pa-sel">
                    <option value="0" @selected((int) ($filterSpocId ?? 0) === 0)>All SPOCs</option>
                    <option value="-1" @selected((int) ($filterSpocId ?? 0) === -1)>Unassigned</option>
                    @foreach ($spocStats as $row)
                        @if ((int) $row['spoc_id'] > 0)
                            <option value="{{ (int) $row['spoc_id'] }}" @selected((int) ($filterSpocId ?? 0) === (int) $row['spoc_id'])>
                                {{ $row['spoc_name'] }}
                            </option>
                        @endif
                    @endforeach
                </select>
            </div>
            <div>
                <label class="pa-lbl" for="district_id">Filter by district</label>
                <select id="district_id" name="district_id" class="pa-sel">
                    <option value="0" @selected((int) ($filterDistrictId ?? 0) === 0)>All districts</option>
                    @foreach ($districtOptions as $district)
                        <option value="{{ (int) $district->id }}" @selected((int) ($filterDistrictId ?? 0) === (int) $district->id)>
                            {{ $district->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="pa-btn">Apply</button>
        </form>

        <div class="pa-table-wrap">
            <table class="pa-table">
                <thead>
                    <tr>
                        <th>Incubatee</th>
                        <th>Service</th>
                        <th>District</th>
                        <th>Submitted by</th>
                        <th>Assigned SPOC</th>
                        <th>Updated</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($cases as $case)
                        <tr>
                            <td>
                                <strong>{{ $case->cfaSubmission?->applicant_name ?? '—' }}</strong>
                                <div class="pa-mini">{{ $case->cfaSubmission?->application_no ?? '—' }}</div>
                            </td>
                            <td>{{ $case->service?->name ?? '—' }}</td>
                            <td>{{ $case->cfaSubmission?->district?->name ?? '—' }}</td>
                            <td>{{ $case->submitter?->name ?? '—' }}</td>
                            <td>{{ $case->spoc?->name ?? 'Unassigned' }}</td>
                            <td>{{ $case->updated_at?->timezone(config('app.timezone'))->format('d M Y H:i') }}</td>
                            <td><span class="pa-status">Pending approval</span></td>
                            <td>
                                <div class="pa-act">
                                    <button
                                        type="button"
                                        class="pa-link pa-link--ghost js-pa-open"
                                        data-applicant="{{ $case->cfaSubmission?->applicant_name ?? '—' }}"
                                        data-app-no="{{ $case->cfaSubmission?->application_no ?? '—' }}"
                                        data-service="{{ $case->service?->name ?? '—' }}"
                                        data-district="{{ $case->cfaSubmission?->district?->name ?? '—' }}"
                                        data-submitter="{{ $case->submitter?->name ?? '—' }}"
                                        data-spoc="{{ $case->spoc?->name ?? 'Unassigned' }}"
                                        data-updated="{{ $case->updated_at?->timezone(config('app.timezone'))->format('d M Y H:i') }}"
                                    >Quick view</button>
                                    @if (\Illuminate\Support\Facades\Route::has('spoc.service-cases.show'))
                                        <a
                                            href="{{ route('spoc.service-cases.show', $case) }}"
                                            target="_blank"
                                            rel="noopener"
                                            class="pa-link"
                                        >Open queue case</a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="pa-mini">No pending cases found for selected filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div>{{ $cases->links() }}</div>
    </div>

    <div id="paModal" class="pa-modal" aria-hidden="true">
        <div class="pa-modal-card" role="dialog" aria-modal="true" aria-label="Pending case details">
            <div class="pa-modal-head">
                <div class="pa-modal-title">Pending case details</div>
                <button type="button" id="paModalClose" class="pa-close">Close</button>
            </div>
            <div class="pa-modal-body">
                <div class="pa-row"><b>Incubatee:</b> <span id="paApplicant">—</span></div>
                <div class="pa-row"><b>Application no:</b> <span id="paAppNo">—</span></div>
                <div class="pa-row"><b>Service:</b> <span id="paService">—</span></div>
                <div class="pa-row"><b>District:</b> <span id="paDistrict">—</span></div>
                <div class="pa-row"><b>Submitted by:</b> <span id="paSubmitter">—</span></div>
                <div class="pa-row"><b>Assigned SPOC:</b> <span id="paSpoc">—</span></div>
                <div class="pa-row"><b>Last updated:</b> <span id="paUpdated">—</span></div>
            </div>
        </div>
    </div>

    <script>
        (function () {
            const modal = document.getElementById('paModal');
            const closeBtn = document.getElementById('paModalClose');
            const buttons = Array.from(document.querySelectorAll('.js-pa-open'));
            const applicant = document.getElementById('paApplicant');
            const appNo = document.getElementById('paAppNo');
            const service = document.getElementById('paService');
            const district = document.getElementById('paDistrict');
            const submitter = document.getElementById('paSubmitter');
            const spoc = document.getElementById('paSpoc');
            const updated = document.getElementById('paUpdated');

            function setOpen(open) {
                modal.classList.toggle('is-open', open);
                modal.setAttribute('aria-hidden', open ? 'false' : 'true');
            }

            buttons.forEach(function (btn) {
                btn.addEventListener('click', function () {
                    applicant.textContent = btn.dataset.applicant || '—';
                    appNo.textContent = btn.dataset.appNo || '—';
                    service.textContent = btn.dataset.service || '—';
                    district.textContent = btn.dataset.district || '—';
                    submitter.textContent = btn.dataset.submitter || '—';
                    spoc.textContent = btn.dataset.spoc || '—';
                    updated.textContent = btn.dataset.updated || '—';
                    setOpen(true);
                });
            });

            closeBtn && closeBtn.addEventListener('click', function () { setOpen(false); });
            modal && modal.addEventListener('click', function (e) {
                if (e.target === modal) setOpen(false);
            });
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') setOpen(false);
            });
        })();
    </script>
@endsection

