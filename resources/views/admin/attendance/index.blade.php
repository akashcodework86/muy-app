@extends('layouts.admin')

@section('title', 'Field visit photos')
@section('heading', 'Field visit photos')

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

    /* CFA status (highlighted cell + brief) */
    .cfa-status-cell {
        position: relative;
        border-radius: 10px;
        padding: 0.55rem 0.65rem 0.55rem 0.85rem;
        border: 1px solid transparent;
        max-width: 15rem;
    }
    .cfa-status-cell--match {
        background: linear-gradient(135deg, #bbf7d0 0%, #dcfce7 55%, #ecfdf5 100%);
        border-color: #22c55e;
        box-shadow: inset 0 0 0 1px rgba(34, 197, 94, 0.15), 0 2px 8px rgba(22, 163, 74, 0.12);
    }
    .cfa-status-cell--mismatch {
        background: linear-gradient(135deg, #fecaca 0%, #fee2e2 50%, #fef2f2 100%);
        border-color: #ef4444;
        box-shadow: inset 0 0 0 1px rgba(239, 68, 68, 0.12), 0 2px 8px rgba(220, 38, 38, 0.1);
    }
    .cfa-status-cell--neutral {
        background: linear-gradient(135deg, #fde68a 0%, #fef9c3 55%, #fffbeb 100%);
        border-color: #f59e0b;
        box-shadow: inset 0 0 0 1px rgba(245, 158, 11, 0.15), 0 2px 8px rgba(217, 119, 6, 0.08);
    }
    .cfa-status-cell::before {
        content: '';
        position: absolute;
        left: 0;
        top: 0.45rem;
        bottom: 0.45rem;
        width: 3px;
        border-radius: 3px;
    }
    .cfa-status-cell--match::before { background: #15803d; box-shadow: 0 0 6px rgba(22, 163, 74, 0.55); }
    .cfa-status-cell--mismatch::before { background: #b91c1c; box-shadow: 0 0 6px rgba(239, 68, 68, 0.45); }
    .cfa-status-cell--neutral::before { background: #d97706; box-shadow: 0 0 6px rgba(245, 158, 11, 0.45); }
    .cfa-match    { display:inline-flex;align-items:center;gap:0.28rem;padding:0.28rem 0.65rem;border-radius:999px;font-size:0.74rem;font-weight:800;background:#15803d;color:#fff;border:1px solid #166534;white-space:nowrap;box-shadow:0 1px 0 rgba(255,255,255,0.25) inset; }
    .cfa-mismatch { display:inline-flex;align-items:center;gap:0.28rem;padding:0.28rem 0.65rem;border-radius:999px;font-size:0.74rem;font-weight:800;background:#dc2626;color:#fff;border:1px solid #b91c1c;white-space:nowrap;box-shadow:0 1px 0 rgba(255,255,255,0.2) inset; }
    .cfa-neutral  { display:inline-flex;align-items:center;gap:0.28rem;padding:0.28rem 0.65rem;border-radius:999px;font-size:0.74rem;font-weight:800;background:#d97706;color:#fff;border:1px solid #b45309;white-space:nowrap;box-shadow:0 1px 0 rgba(255,255,255,0.2) inset; }
    .cfa-status-brief {
        margin-top: 0.38rem;
        font-size: 0.68rem;
        line-height: 1.35;
    }
    .cfa-status-cell--match .cfa-status-brief { color: #14532d; font-weight: 600; }
    .cfa-status-cell--mismatch .cfa-status-brief { color: #7f1d1d; font-weight: 600; }
    .cfa-status-cell--neutral .cfa-status-brief { color: #92400e; font-weight: 600; }

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
        <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:0.5rem;margin-bottom:0.75rem;">
            <h3 style="margin:0;"><i class="fa-solid fa-filter" style="margin-right:0.35rem;"></i>Filter records</h3>
            <a href="{{ route('admin.gram-panchayats.import') }}" class="adatt-btn" style="text-decoration:none;font-size:0.8rem;">
                <i class="fa-solid fa-file-import"></i> Import gram panchayats CSV
            </a>
        </div>
        <form method="get" action="{{ route('admin.attendance.index') }}">
            <div class="adatt-filter-row">

                <div class="adatt-filter-field" style="max-width:280px;">
                    <label>Search (name, block, GP, remark)</label>
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

    <div class="adatt-chips">
        <div class="adatt-chip">
            <div class="adatt-chip__icon adatt-chip__icon--indigo"><i class="fa-solid fa-camera"></i></div>
            <div class="adatt-chip__body">
                <div class="adatt-chip__label">Total submissions</div>
                <div class="adatt-chip__value">{{ number_format($reports->total()) }}</div>
            </div>
        </div>
    </div>

    <div class="adatt-table-card">
        <div class="adatt-table-head">
            <div class="adatt-table-head-icon"><i class="fa-solid fa-table-list"></i></div>
            <h3>Field visit submissions</h3>
            <span class="adatt-count">{{ number_format($reports->total()) }} total</span>
        </div>
        <div style="overflow-x:auto;">
            <table class="adatt-table">
                <thead>
                    <tr>
                        <th>Coordinator</th>
                        <th>Visit date</th>
                        <th>District</th>
                        <th>Block</th>
                        <th>Gram panchayat</th>
                        <th>Area / village</th>
                        <th>Participants</th>
                        <th>Photos</th>
                        <th>Remark</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($reports as $row)
                        @php $media = $row->visitMediaItems(); @endphp
                        <tr>
                            <td><div class="adatt-coord-name">{{ $row->field_coordinator_name }}</div></td>
                            <td><span class="adatt-date-badge">{{ $row->visit_date?->format('d M Y') }}</span></td>
                            <td>{{ $row->district?->name ?? '—' }}</td>
                            <td>{{ $row->block ?: '—' }}</td>
                            <td>{{ $row->gramPanchayat?->name ?? '—' }}</td>
                            <td>{{ $row->area ?: '—' }}</td>
                            <td style="font-size:0.82rem;">
                                @if ((int) $row->participants_total > 0)
                                    <strong>{{ number_format((int) $row->participants_total) }}</strong><br>
                                    <span style="color:var(--adatt-muted);">M {{ number_format((int) $row->participants_male_count) }} · F {{ number_format((int) $row->participants_female_count) }}</span>
                                @else
                                    —
                                @endif
                            </td>
                            <td>
                                @if ($media !== [])
                                    {{ count($media) }} photo(s)
                                    @foreach ($media as $idx => $item)
                                        <a href="{{ route('admin.attendance.attachment', ['attendanceReport' => $row, 'index' => $idx]) }}" class="adatt-dl-btn" style="margin:0.15rem 0.15rem 0 0;display:inline-flex;"><i class="fa-solid fa-image"></i> {{ $idx + 1 }}</a>
                                    @endforeach
                                @elseif ($row->attachment_path)
                                    <a href="{{ route('admin.attendance.attachment', $row) }}" class="adatt-dl-btn">Legacy</a>
                                @else
                                    —
                                @endif
                            </td>
                            <td style="font-size:0.82rem;color:var(--adatt-muted);max-width:14rem;">{{ $row->remark ?: '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="9"><div class="adatt-empty">No submissions found.</div></td></tr>
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
