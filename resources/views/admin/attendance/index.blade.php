@extends('layouts.admin')

@section('title', 'Field Coordinator Attendance')
@section('heading', 'Field Coordinator Attendance')

@push('styles')
<style>
    :root {
        --adatt-indigo: #4f46e5;
        --adatt-teal:   #0d9488;
        --adatt-text:   #0f172a;
        --adatt-muted:  #64748b;
        --adatt-ink:    #334155;
        --adatt-border: #e2e8f0;
        --adatt-bg:     #f8fafc;
    }

    .adatt-shell {
        display: flex;
        flex-direction: column;
        gap: 1.25rem;
        padding-bottom: 3rem;
        font-family: 'DM Sans', sans-serif;
    }

    /* ── Filter card ────────────────────────────────── */
    .adatt-filter-card {
        background: #fff;
        border: 1px solid var(--adatt-border);
        border-radius: 16px;
        box-shadow: 0 4px 16px rgba(15,23,42,0.04);
        padding: 1rem 1.25rem;
    }
    .adatt-filter-card h3 {
        margin: 0 0 0.75rem;
        font-size: 0.82rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.07em;
        color: var(--adatt-muted);
    }
    .adatt-filter-row {
        display: flex;
        flex-wrap: wrap;
        gap: 0.55rem;
        align-items: flex-end;
    }
    .adatt-filter-field {
        display: flex;
        flex-direction: column;
        gap: 0.28rem;
        flex: 1;
        min-width: 160px;
    }
    .adatt-filter-field label {
        font-size: 0.72rem;
        font-weight: 600;
        color: var(--adatt-ink);
    }
    .adatt-filter-input {
        padding: 0.52rem 0.65rem;
        border: 1px solid #cbd5e1;
        border-radius: 8px;
        font-size: 0.86rem;
        color: var(--adatt-text);
        background: #fff;
        width: 100%;
        font-family: inherit;
    }
    .adatt-filter-input:focus {
        outline: none;
        border-color: var(--adatt-indigo);
        box-shadow: 0 0 0 3px rgba(79,70,229,0.1);
    }
    .adatt-btn {
        display: inline-flex; align-items: center; gap: 0.35rem;
        padding: 0.52rem 1rem;
        background: var(--adatt-indigo);
        color: #fff; border: none; border-radius: 8px;
        font-size: 0.85rem; font-weight: 700;
        cursor: pointer; font-family: inherit;
        white-space: nowrap;
    }
    .adatt-btn:hover { opacity: 0.88; }
    .adatt-btn--ghost {
        background: transparent;
        color: var(--adatt-indigo);
        border: 1px solid var(--adatt-indigo);
    }

    /* ── Summary chips ──────────────────────────────── */
    .adatt-chips {
        display: flex; flex-wrap: wrap; gap: 0.55rem;
    }
    .adatt-chip {
        display: inline-flex; align-items: center; gap: 0.45rem;
        background: #fff; border: 1px solid var(--adatt-border);
        border-radius: 12px; padding: 0.55rem 0.9rem;
        box-shadow: 0 2px 10px rgba(15,23,42,0.04);
    }
    .adatt-chip__icon {
        width: 1.7rem; height: 1.7rem;
        border-radius: 8px;
        display: flex; align-items: center; justify-content: center;
        font-size: 0.8rem;
    }
    .adatt-chip__icon--indigo { background:#eef2ff; color:var(--adatt-indigo); }
    .adatt-chip__icon--teal   { background:#ccfbf1; color:#0f766e; }
    .adatt-chip__icon--amber  { background:#fef9c3; color:#a16207; }
    .adatt-chip__icon--rose   { background:#ffe4e6; color:#be123c; }
    .adatt-chip__body {}
    .adatt-chip__label { font-size:0.68rem; color:var(--adatt-muted); font-weight:600; text-transform:uppercase; letter-spacing:0.07em; }
    .adatt-chip__value { font-size:1.05rem; font-weight:800; color:var(--adatt-text); }

    /* ── Table card ─────────────────────────────────── */
    .adatt-table-card {
        background: #fff;
        border: 1px solid var(--adatt-border);
        border-radius: 16px;
        box-shadow: 0 4px 16px rgba(15,23,42,0.04);
        overflow: hidden;
    }
    .adatt-table-head {
        padding: 0.9rem 1.25rem;
        border-bottom: 1px solid var(--adatt-border);
        display: flex; align-items: center; gap: 0.6rem;
    }
    .adatt-table-head-icon {
        width: 1.8rem; height: 1.8rem;
        background: linear-gradient(135deg, #eef2ff, #e0e7ff);
        border-radius: 8px;
        display: flex; align-items: center; justify-content: center;
        color: var(--adatt-indigo); font-size: 0.8rem;
    }
    .adatt-table-head h3 { margin: 0; font-size: 0.92rem; font-weight: 700; color: var(--adatt-text); }
    .adatt-table-head .adatt-count {
        margin-left: auto;
        font-size: 0.78rem; color: var(--adatt-muted);
    }

    .adatt-table { width: 100%; border-collapse: collapse; font-size: 0.845rem; }
    .adatt-table thead tr { background: #f8fafc; }
    .adatt-table th {
        padding: 0.65rem 1rem;
        text-align: left;
        font-size: 0.68rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.07em;
        color: var(--adatt-muted);
        border-bottom: 1px solid var(--adatt-border);
        white-space: nowrap;
    }
    .adatt-table td {
        padding: 0.7rem 1rem;
        color: var(--adatt-ink);
        border-bottom: 1px solid #f1f5f9;
        vertical-align: top;
    }
    .adatt-table tbody tr:last-child td { border-bottom: none; }
    .adatt-table tbody tr:hover td { background: #fafafa; }

    /* coordinator cell */
    .adatt-coord-name { font-weight: 700; color: var(--adatt-text); }
    .adatt-coord-meta { font-size: 0.72rem; color: var(--adatt-muted); margin-top: 0.1rem; }
    .adatt-coord-district {
        display: inline-block;
        padding: 0.12rem 0.45rem;
        background: #eef2ff; color: var(--adatt-indigo);
        border-radius: 999px; font-size: 0.68rem; font-weight: 700;
        margin-top: 0.2rem;
    }

    /* date badge */
    .adatt-date-badge {
        display: inline-block;
        padding: 0.2rem 0.5rem;
        background: #f0fdf4; color: #15803d;
        border: 1px solid #bbf7d0;
        border-radius: 8px; font-size: 0.75rem; font-weight: 700;
    }

    /* location cell */
    .adatt-loc-area   { font-weight: 600; color: var(--adatt-text); }
    .adatt-loc-block  { font-size: 0.75rem; color: var(--adatt-muted); }
    .adatt-loc-dist   { font-size: 0.72rem; color: var(--adatt-indigo); font-weight: 600; margin-top: 0.15rem; }

    /* village tags */
    .adatt-village-count { font-weight: 700; color: var(--adatt-text); }
    .adatt-village-tags { display: flex; flex-wrap: wrap; gap: 0.25rem; margin-top: 0.3rem; }
    .adatt-village-tag {
        display: inline-block;
        padding: 0.12rem 0.45rem;
        background: #f0fdf4; border: 1px solid #bbf7d0;
        color: #15803d; border-radius: 999px;
        font-size: 0.68rem; font-weight: 600;
    }

    /* stat number */
    .adatt-num { font-weight: 700; font-size: 0.95rem; color: var(--adatt-text); }

    /* CFA status */
    .cfa-match    { display:inline-flex;align-items:center;gap:0.28rem;padding:0.22rem 0.55rem;border-radius:999px;font-size:0.72rem;font-weight:700;background:#dcfce7;color:#15803d;border:1px solid #bbf7d0;white-space:nowrap; }
    .cfa-mismatch { display:inline-flex;align-items:center;gap:0.28rem;padding:0.22rem 0.55rem;border-radius:999px;font-size:0.72rem;font-weight:700;background:#fef2f2;color:#b91c1c;border:1px solid #fecaca;white-space:nowrap; }
    .cfa-neutral  { display:inline-flex;align-items:center;gap:0.28rem;padding:0.22rem 0.55rem;border-radius:999px;font-size:0.72rem;font-weight:700;background:#f1f5f9;color:#64748b;border:1px solid #e2e8f0;white-space:nowrap; }

    /* attachment btn */
    .adatt-dl-btn {
        display: inline-flex; align-items: center; gap: 0.3rem;
        padding: 0.28rem 0.6rem;
        background: #0f766e; color: #fff;
        border-radius: 6px; font-size: 0.74rem; font-weight: 700;
        text-decoration: none;
    }
    .adatt-dl-btn:hover { background: #0d9488; }

    .adatt-empty {
        padding: 3rem 1rem;
        text-align: center;
        color: var(--adatt-muted); font-size: 0.88rem;
    }
    .adatt-empty i { display: block; font-size: 2.2rem; color: #c7d2fe; margin-bottom: 0.5rem; }
</style>
@endpush

@section('content')
<div class="adatt-shell">

    {{-- Filter card --}}
    <div class="adatt-filter-card">
        <h3><i class="fa-solid fa-filter" style="margin-right:0.35rem;"></i>Filter records</h3>
        <form method="get" action="{{ route('admin.attendance.index') }}">
            <div class="adatt-filter-row">

                <div class="adatt-filter-field" style="max-width:280px;">
                    <label>Search (area, block, name)</label>
                    <input type="search" name="q" value="{{ $searchQuery ?? '' }}"
                        placeholder="Search…" class="adatt-filter-input">
                </div>

                <div class="adatt-filter-field" style="max-width:240px;">
                    <label>Field Coordinator</label>
                    <select name="coordinator_id" class="adatt-filter-input">
                        <option value="">— All coordinators —</option>
                        @foreach ($coordinators as $c)
                            <option value="{{ $c->id }}"
                                @selected((int)($coordinatorId ?? 0) === (int)$c->id)>
                                {{ $c->name }}
                                @if ($c->district?->name)
                                    — {{ $c->district->name }}
                                @endif
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="adatt-filter-field" style="max-width:160px;">
                    <label>From date</label>
                    <input type="date" name="from" value="{{ request('from') }}" class="adatt-filter-input">
                </div>

                <div class="adatt-filter-field" style="max-width:160px;">
                    <label>To date</label>
                    <input type="date" name="to" value="{{ request('to') }}" class="adatt-filter-input">
                </div>

                <div style="display:flex;gap:0.45rem;align-items:flex-end;">
                    <button type="submit" class="adatt-btn">
                        <i class="fa-solid fa-magnifying-glass"></i> Apply
                    </button>
                    <a href="{{ route('admin.attendance.index') }}" class="adatt-btn adatt-btn--ghost">Reset</a>
                </div>
            </div>
        </form>
    </div>

    {{-- Summary chips --}}
    @php
        $totalReports      = $reports->total();
        $totalParticipants = $reports->getCollection()->sum('participants_total');
        $totalCfas         = $reports->getCollection()->sum('cfas_filled_total');
        $totalOutreach     = $reports->getCollection()->sum('outreach_programmes_total');
    @endphp
    <div class="adatt-chips">
        <div class="adatt-chip">
            <div class="adatt-chip__icon adatt-chip__icon--indigo"><i class="fa-solid fa-calendar-check"></i></div>
            <div class="adatt-chip__body">
                <div class="adatt-chip__label">Total reports</div>
                <div class="adatt-chip__value">{{ number_format($totalReports) }}</div>
            </div>
        </div>
        <div class="adatt-chip">
            <div class="adatt-chip__icon adatt-chip__icon--teal"><i class="fa-solid fa-users"></i></div>
            <div class="adatt-chip__body">
                <div class="adatt-chip__label">Participants (page)</div>
                <div class="adatt-chip__value">{{ number_format($totalParticipants) }}</div>
            </div>
        </div>
        <div class="adatt-chip">
            <div class="adatt-chip__icon adatt-chip__icon--amber"><i class="fa-solid fa-file-pen"></i></div>
            <div class="adatt-chip__body">
                <div class="adatt-chip__label">CFAs filled (page)</div>
                <div class="adatt-chip__value">{{ number_format($totalCfas) }}</div>
            </div>
        </div>
        <div class="adatt-chip">
            <div class="adatt-chip__icon adatt-chip__icon--rose"><i class="fa-solid fa-bullhorn"></i></div>
            <div class="adatt-chip__body">
                <div class="adatt-chip__label">Outreach (page)</div>
                <div class="adatt-chip__value">{{ number_format($totalOutreach) }}</div>
            </div>
        </div>
    </div>

    {{-- Table card --}}
    <div class="adatt-table-card">
        <div class="adatt-table-head">
            <div class="adatt-table-head-icon"><i class="fa-solid fa-table-list"></i></div>
            <h3>Attendance records</h3>
            <span class="adatt-count">{{ number_format($reports->total()) }} total</span>
        </div>
        <div style="overflow-x:auto;">
            <table class="adatt-table">
                <thead>
                    <tr>
                        <th>Coordinator</th>
                        <th>Visit date</th>
                        <th>Entry date</th>
                        <th>Area / Block / District</th>
                        <th>Villages covered</th>
                        <th>Participants</th>
                        <th>CFAs filled</th>
                        <th>Outreach</th>
                        <th>CFA status</th>
                        <th>Doc</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($reports as $row)
                        @php
                            $mapKey   = $row->field_coordinator_user_id.'_'.$row->visit_date?->format('Y-m-d');
                            $cfaCount = (int) ($cfaMap[$mapKey] ?? 0);
                            $reported = (int) $row->cfas_filled_total;
                        @endphp
                        <tr>
                            {{-- Coordinator --}}
                            <td>
                                <div class="adatt-coord-name">{{ $row->field_coordinator_name }}</div>
                                @if ($row->district?->name)
                                    <div><span class="adatt-coord-district">
                                        <i class="fa-solid fa-location-dot" style="font-size:0.6rem;"></i>
                                        {{ $row->district->name }}
                                    </span></div>
                                @endif
                                <div class="adatt-coord-meta">ID {{ $row->field_coordinator_user_id }}</div>
                            </td>

                            {{-- Visit date --}}
                            <td>
                                <span class="adatt-date-badge">
                                    {{ $row->visit_date?->format('d M Y') }}
                                </span>
                            </td>

                            {{-- Entry date --}}
                            <td style="color:var(--adatt-muted);font-size:0.8rem;">
                                {{ $row->entry_date?->format('d M Y') }}
                            </td>

                            {{-- Location --}}
                            <td>
                                @if($row->area)
                                    <div class="adatt-loc-area">{{ $row->area }}</div>
                                @endif
                                @if($row->block)
                                    <div class="adatt-loc-block">{{ $row->block }}</div>
                                @endif
                                @if($row->district?->name)
                                    <div class="adatt-loc-dist">
                                        <i class="fa-solid fa-building" style="font-size:0.6rem;"></i>
                                        {{ $row->district->name }}
                                    </div>
                                @endif
                                @if(!$row->area && !$row->block && !$row->district?->name)
                                    <span style="color:var(--adatt-muted);">—</span>
                                @endif
                            </td>

                            {{-- Villages --}}
                            <td>
                                <div class="adatt-village-count">
                                    {{ number_format((int)$row->villages_visited_total) }} visited
                                </div>
                                @if(is_array($row->villages_covered) && count($row->villages_covered))
                                    <div class="adatt-village-tags">
                                        @foreach($row->villages_covered as $v)
                                            <span class="adatt-village-tag">{{ $v }}</span>
                                        @endforeach
                                    </div>
                                @endif
                            </td>

                            {{-- Stats --}}
                            <td><span class="adatt-num">{{ number_format((int)$row->participants_total) }}</span></td>
                            <td><span class="adatt-num">{{ number_format($reported) }}</span></td>
                            <td><span class="adatt-num">{{ number_format((int)$row->outreach_programmes_total) }}</span></td>

                            {{-- CFA status --}}
                            <td>
                                @if ($cfaCount > 0 && $cfaCount >= $reported)
                                    <span class="cfa-match">
                                        <i class="fa-solid fa-circle-check"></i>
                                        {{ $cfaCount }} CFA(s) match
                                    </span>
                                @elseif ($cfaCount > 0)
                                    <span class="cfa-mismatch">
                                        <i class="fa-solid fa-triangle-exclamation"></i>
                                        {{ $cfaCount }} CFA(s) — under reported
                                    </span>
                                @else
                                    <span class="cfa-neutral">
                                        <i class="fa-solid fa-circle-minus"></i>
                                        No CFAs on this date
                                    </span>
                                @endif
                            </td>

                            {{-- Attachment --}}
                            <td>
                                @if ($row->attachment_path)
                                    <a href="{{ route('admin.attendance.attachment', $row) }}" class="adatt-dl-btn">
                                        <i class="fa-solid fa-download"></i> Download
                                    </a>
                                @else
                                    <span style="color:#a1a1aa;">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10">
                                <div class="adatt-empty">
                                    <i class="fa-regular fa-folder-open"></i>
                                    No attendance records found for the selected filters.
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if ($reports->hasPages())
        <div>{{ $reports->links() }}</div>
    @endif

</div>
@endsection
