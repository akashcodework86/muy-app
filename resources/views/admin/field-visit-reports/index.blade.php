@extends('layouts.admin')

@section('title', 'Field Visit Reports')
@section('heading', 'Field Visit Reports')

@push('styles')
<script src="https://cdn.tailwindcss.com"></script>
<style>
    :root {
        --fv-indigo: #4f46e5;
        --fv-teal:   #0d9488;
        --fv-amber:  #d97706;
        --fv-red:    #dc2626;
        --fv-text:   #0f172a;
        --fv-muted:  #64748b;
    }
    .fv-shell { display:flex; flex-direction:column; gap:1rem; padding-bottom:3rem; font-family:'DM Sans',sans-serif; }

    /* ── Stat tiles ── */
    .fv-stat-row { display:grid; grid-template-columns:repeat(auto-fit,minmax(200px,1fr)); gap:.75rem; }
    .fv-stat {
        background:#fff;
        border:1px solid rgba(226,232,240,.9);
        border-radius:16px;
        padding:1rem 1.25rem;
        box-shadow:0 4px 14px rgba(15,23,42,.05);
        display:flex; flex-direction:column; gap:.25rem;
    }
    .fv-stat__label { font-size:.68rem; text-transform:uppercase; letter-spacing:.08em; font-weight:700; color:var(--fv-muted); }
    .fv-stat__value { font-size:1.7rem; font-weight:800; color:var(--fv-text); line-height:1.1; }
    .fv-stat__sub   { font-size:.72rem; color:var(--fv-muted); }
    .fv-stat--warn  .fv-stat__value { color:var(--fv-amber); }

    /* ── Filters card ── */
    .fv-filters {
        background:#fff;
        border:1px solid rgba(226,232,240,.9);
        border-radius:16px;
        padding:1rem 1.25rem;
        box-shadow:0 4px 14px rgba(15,23,42,.04);
    }
    .fv-filters form { display:flex; flex-wrap:wrap; gap:.65rem; align-items:flex-end; }
    .fv-field { display:flex; flex-direction:column; gap:.25rem; }
    .fv-field label { font-size:.68rem; text-transform:uppercase; letter-spacing:.07em; font-weight:700; color:var(--fv-muted); }
    .fv-input, .fv-select {
        border:1px solid #cbd5e1;
        border-radius:8px;
        padding:.4rem .75rem;
        font-size:.85rem;
        color:var(--fv-text);
        background:#fff;
        outline:none;
        transition:border-color .15s;
    }
    .fv-input:focus, .fv-select:focus { border-color:var(--fv-indigo); box-shadow:0 0 0 3px rgba(79,70,229,.1); }
    .fv-select { min-width:160px; appearance:none; padding-right:2rem;
        background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='none' viewBox='0 0 24 24'%3E%3Cpath stroke='%2364748b' stroke-width='1.8' stroke-linecap='round' stroke-linejoin='round' d='M6 9l6 6 6-6'/%3E%3C/svg%3E");
        background-repeat:no-repeat; background-position:right .5rem center; }
    .fv-btn { display:inline-flex; align-items:center; gap:.4rem; padding:.42rem 1rem; border-radius:8px; font-size:.84rem; font-weight:600; cursor:pointer; border:none; transition:.15s; }
    .fv-btn--primary  { background:var(--fv-indigo); color:#fff; }
    .fv-btn--primary:hover { background:#4338ca; }
    .fv-btn--ghost    { background:#f1f5f9; color:var(--fv-text); }
    .fv-btn--ghost:hover { background:#e2e8f0; }
    .fv-btn--export   { background:#0f766e; color:#fff; }
    .fv-btn--export:hover { background:#0d6b64; }
    .fv-checkbox-wrap { display:flex; align-items:center; gap:.4rem; font-size:.84rem; font-weight:500; color:var(--fv-text); cursor:pointer; margin-bottom:.15rem; }
    .fv-checkbox-wrap input[type=checkbox] { width:15px; height:15px; accent-color:var(--fv-amber); }

    /* ── Table ── */
    .fv-table-wrap {
        background:#fff;
        border:1px solid rgba(226,232,240,.9);
        border-radius:16px;
        box-shadow:0 4px 14px rgba(15,23,42,.04);
        overflow:hidden;
    }
    .fv-table-head {
        display:flex; align-items:center; justify-content:space-between;
        padding:.85rem 1.25rem;
        border-bottom:1px solid #f1f5f9;
        gap:.5rem; flex-wrap:wrap;
    }
    .fv-table-head h2 { margin:0; font-size:.95rem; font-weight:700; color:var(--fv-text); }
    .fv-table-head span { font-size:.75rem; color:var(--fv-muted); }
    .fv-tbl { width:100%; border-collapse:collapse; font-size:.82rem; }
    .fv-tbl thead th {
        background:#f8fafc;
        text-align:left;
        padding:.55rem .85rem;
        font-size:.68rem;
        text-transform:uppercase;
        letter-spacing:.07em;
        font-weight:700;
        color:var(--fv-muted);
        border-bottom:1px solid #e2e8f0;
        white-space:nowrap;
    }
    .fv-tbl tbody tr { border-bottom:1px solid #f1f5f9; transition:background .1s; }
    .fv-tbl tbody tr:hover { background:#fafbff; }
    .fv-tbl tbody tr:last-child { border-bottom:none; }
    .fv-tbl td { padding:.6rem .85rem; color:var(--fv-text); vertical-align:middle; }
    .fv-badge {
        display:inline-flex; align-items:center; gap:.25rem;
        padding:.18rem .5rem; border-radius:999px;
        font-size:.68rem; font-weight:700;
    }
    .fv-badge--ok   { background:#dcfce7; color:#15803d; }
    .fv-badge--warn { background:#fef9c3; color:#a16207; }
    .fv-coordinator { font-weight:600; color:var(--fv-text); }
    .fv-muted-cell  { color:var(--fv-muted); }
    .fv-doc-link    { display:inline-flex; align-items:center; gap:.25rem; font-size:.78rem; color:var(--fv-indigo); font-weight:600; text-decoration:none; }
    .fv-doc-link:hover { text-decoration:underline; }
    .fv-empty { text-align:center; padding:3rem 1rem; color:var(--fv-muted); }

    /* pagination */
    .fv-pagination { display:flex; gap:.35rem; justify-content:flex-end; padding:.75rem 1.25rem; border-top:1px solid #f1f5f9; flex-wrap:wrap; }
    .fv-pagination a, .fv-pagination span {
        display:inline-flex; align-items:center; justify-content:center;
        min-width:2rem; height:2rem; padding:0 .5rem;
        border:1px solid #e2e8f0; border-radius:7px;
        font-size:.8rem; color:var(--fv-muted); text-decoration:none;
        transition:.12s;
    }
    .fv-pagination a:hover { background:#f1f5f9; color:var(--fv-text); }
    .fv-pagination .active > span { background:var(--fv-indigo); color:#fff; border-color:var(--fv-indigo); }
    .fv-pagination .disabled span { opacity:.45; cursor:default; }
</style>
@endpush

@section('content')
<div class="fv-shell">

    {{-- ── Summary tiles ── --}}
    <div class="fv-stat-row">

        <div class="fv-stat">
            <div class="fv-stat__label">Field Coordinators</div>
            <div class="fv-stat__value">{{ number_format($totalCoordinators) }}</div>
            <div class="fv-stat__sub">
                Active: <strong>{{ $activeCoordinators }}</strong> &nbsp;·&nbsp;
                Inactive: <strong>{{ $totalCoordinators - $activeCoordinators }}</strong>
            </div>
        </div>

        <div class="fv-stat">
            <div class="fv-stat__label">CFAs from Field Work</div>
            <div class="fv-stat__value">{{ number_format($totalCfasVerified) }}</div>
            <div class="fv-stat__sub">
                Verified from referral records
                @if ($discrepancyCount > 0)
                    &nbsp;·&nbsp;
                    <span style="color:var(--fv-amber); font-weight:700;">⚠ {{ $discrepancyCount }} discrepanc{{ $discrepancyCount === 1 ? 'y' : 'ies' }}</span>
                @endif
            </div>
        </div>

        <div class="fv-stat">
            <div class="fv-stat__label">Total Participants</div>
            <div class="fv-stat__value">{{ number_format($totalParticipants) }}</div>
            <div class="fv-stat__sub">Across all field visits</div>
        </div>

    </div>

    {{-- ── Filters ── --}}
    <div class="fv-filters">
        <form method="GET" action="{{ route('admin.field-reports.index') }}" id="fvFilterForm">
            <div class="fv-field">
                <label for="fv-coordinator">Coordinator</label>
                <select id="fv-coordinator" name="coordinator_id" class="fv-select">
                    <option value="">All coordinators</option>
                    @foreach ($coordinators as $c)
                        <option value="{{ $c->id }}" @selected(request('coordinator_id') == $c->id)>{{ $c->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="fv-field">
                <label for="fv-district">District</label>
                <select id="fv-district" name="district_id" class="fv-select" onchange="this.form.submit()">
                    <option value="">All districts</option>
                    @foreach ($districts as $d)
                        <option value="{{ $d->id }}" @selected(request('district_id') == $d->id)>{{ $d->name }}</option>
                    @endforeach
                </select>
            </div>

            @if ($blocks->isNotEmpty())
            <div class="fv-field">
                <label for="fv-block">Block</label>
                <select id="fv-block" name="block_id" class="fv-select">
                    <option value="">All blocks</option>
                    @foreach ($blocks as $b)
                        <option value="{{ $b->id }}" @selected(request('block_id') == $b->id)>{{ $b->name }}</option>
                    @endforeach
                </select>
            </div>
            @endif

            <div class="fv-field">
                <label for="fv-date-from">From date</label>
                <input id="fv-date-from" type="date" name="date_from" value="{{ request('date_from') }}" class="fv-input">
            </div>

            <div class="fv-field">
                <label for="fv-date-to">To date</label>
                <input id="fv-date-to" type="date" name="date_to" value="{{ request('date_to') }}" class="fv-input">
            </div>

            <div class="fv-field">
                <label>&nbsp;</label>
                <label class="fv-checkbox-wrap">
                    <input type="checkbox" name="only_discrepancies" value="1" @checked(request('only_discrepancies'))>
                    Only ⚠ discrepancies
                </label>
            </div>

            <div class="fv-field" style="flex-direction:row; gap:.45rem; align-items:flex-end;">
                <button type="submit" class="fv-btn fv-btn--primary">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.35-4.35"/></svg>
                    Filter
                </button>
                <a href="{{ route('admin.field-reports.index') }}" class="fv-btn fv-btn--ghost">Reset</a>
                <a href="{{ route('admin.field-reports.export', request()->query()) }}" class="fv-btn fv-btn--export">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8l-5-5Z"/><path d="M14 3v5h5"/><path d="M12 11v6M9 14l3 3 3-3"/></svg>
                    Export CSV
                </a>
            </div>
        </form>
    </div>

    {{-- ── Table ── --}}
    <div class="fv-table-wrap">
        <div class="fv-table-head">
            <h2>Visit Reports</h2>
            <span>{{ $reports->total() }} record{{ $reports->total() === 1 ? '' : 's' }}</span>
        </div>

        @if ($reports->isEmpty())
            <p class="fv-empty">No field visit reports found. Adjust the filters above.</p>
        @else
        <div style="overflow-x:auto;">
            <table class="fv-tbl">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Coordinator</th>
                        <th>District</th>
                        <th>Block</th>
                        <th>Date of Visit</th>
                        <th>Villages</th>
                        <th>Participants</th>
                        <th>Outreach Progs</th>
                        <th>CFAs Reported</th>
                        <th>CFAs Verified</th>
                        <th>Match</th>
                        <th>Document</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($reports as $i => $r)
                    <tr>
                        <td class="fv-muted-cell">{{ $reports->firstItem() + $i }}</td>
                        <td>
                            <div class="fv-coordinator">{{ $r->user?->name ?? '—' }}</div>
                            @if ($r->area)
                                <div class="fv-muted-cell" style="font-size:.72rem;">{{ $r->area }}</div>
                            @endif
                        </td>
                        <td>{{ $r->district?->name ?? '—' }}</td>
                        <td>{{ $r->block?->name ?? '—' }}</td>
                        <td style="white-space:nowrap;">{{ $r->visit_date?->format('d M Y') }}</td>
                        <td>
                            {{ $r->total_villages }}
                            @if ($r->village_names)
                                <details style="display:inline; margin-left:.25rem;">
                                    <summary style="cursor:pointer; font-size:.7rem; color:var(--fv-indigo); list-style:none;">view</summary>
                                    <div style="position:absolute; z-index:10; background:#fff; border:1px solid #e2e8f0; border-radius:8px; padding:.5rem .75rem; font-size:.75rem; color:var(--fv-text); max-width:200px; box-shadow:0 4px 16px rgba(0,0,0,.1); white-space:pre-wrap;">{{ $r->village_names }}</div>
                                </details>
                            @endif
                        </td>
                        <td>{{ number_format($r->total_participants) }}</td>
                        <td>{{ $r->outreach_programmes }}</td>
                        <td>{{ $r->cfas_reported }}</td>
                        <td>
                            @if ($r->cfas_verified !== null)
                                {{ $r->cfas_verified }}
                            @else
                                <span class="fv-muted-cell">—</span>
                            @endif
                        </td>
                        <td>
                            @if ($r->cfas_verified === null)
                                <span class="fv-muted-cell" style="font-size:.72rem;">pending</span>
                            @elseif ($r->has_discrepancy)
                                <span class="fv-badge fv-badge--warn">
                                    ⚠ {{ (int)$r->cfas_reported - (int)$r->cfas_verified > 0 ? '+' : '' }}{{ (int)$r->cfas_reported - (int)$r->cfas_verified }}
                                </span>
                            @else
                                <span class="fv-badge fv-badge--ok">✓ Match</span>
                            @endif
                        </td>
                        <td>
                            @if ($r->attachment_path)
                                <a href="{{ Storage::disk('public')->url($r->attachment_path) }}" target="_blank" class="fv-doc-link">
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8l-5-5Z"/><path d="M14 3v5h5"/></svg>
                                    View
                                </a>
                            @else
                                <span class="fv-muted-cell">—</span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if ($reports->hasPages())
        <div class="fv-pagination">
            {{ $reports->links() }}
        </div>
        @endif
        @endif
    </div>

</div>
@endsection
