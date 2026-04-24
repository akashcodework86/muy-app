@extends('layouts.admin')

@section('title', 'View Attendance')
@section('heading', 'My Attendance Records')

@push('styles')
<style>
    :root {
        --vatt-indigo: #4f46e5;
        --vatt-teal:   #0d9488;
        --vatt-text:   #0f172a;
        --vatt-muted:  #64748b;
        --vatt-ink:    #334155;
        --vatt-border: #e2e8f0;
        --vatt-bg:     #f8fafc;
    }

    .vatt-shell {
        display: flex;
        flex-direction: column;
        gap: 1.25rem;
        padding-bottom: 3rem;
        font-family: 'DM Sans', sans-serif;
    }

    /* ── Banner ────────────────────────────────────── */
    .vatt-banner {
        background: linear-gradient(135deg, rgba(13,148,136,0.09), rgba(79,70,229,0.06));
        border: 1px solid rgba(13,148,136,0.2);
        border-radius: 16px;
        padding: 1rem 1.3rem;
        display: flex;
        align-items: center;
        gap: 1rem;
        flex-wrap: wrap;
        justify-content: space-between;
    }
    .vatt-banner__left { display: flex; align-items: center; gap: 0.9rem; }
    .vatt-banner__icon {
        width: 2.8rem; height: 2.8rem;
        background: linear-gradient(135deg, var(--vatt-teal), #2dd4bf);
        border-radius: 12px;
        display: flex; align-items: center; justify-content: center;
        color: #fff; font-size: 1.2rem;
        box-shadow: 0 4px 12px rgba(13,148,136,0.28);
        flex-shrink: 0;
    }
    .vatt-banner__body h2 { margin: 0 0 0.18rem; font-size: 1rem; font-weight: 800; color: var(--vatt-text); }
    .vatt-banner__body p  { margin: 0; font-size: 0.82rem; color: var(--vatt-muted); }
    .vatt-banner__actions a {
        display: inline-flex; align-items: center; gap: 0.35rem;
        padding: 0.52rem 0.9rem;
        background: var(--vatt-indigo); color: #fff;
        border-radius: 9px; font-size: 0.83rem; font-weight: 700;
        text-decoration: none;
    }
    .vatt-banner__actions a:hover { opacity: 0.88; }

    /* ── Summary chips ──────────────────────────────── */
    .vatt-chips {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 0.7rem;
    }
    .vatt-chip {
        background: #fff;
        border: 1px solid var(--vatt-border);
        border-radius: 14px;
        padding: 0.75rem 1rem;
        box-shadow: 0 3px 12px rgba(15,23,42,0.04);
        display: flex; align-items: center; gap: 0.65rem;
    }
    .vatt-chip__icon {
        width: 2rem; height: 2rem;
        border-radius: 9px;
        display: flex; align-items: center; justify-content: center;
        font-size: 0.88rem; flex-shrink: 0;
    }
    .vatt-chip__icon--indigo { background: #eef2ff; color: var(--vatt-indigo); }
    .vatt-chip__icon--teal   { background: #ccfbf1; color: #0f766e; }
    .vatt-chip__icon--amber  { background: #fef9c3; color: #a16207; }
    .vatt-chip__icon--rose   { background: #ffe4e6; color: #be123c; }
    .vatt-chip__label { font-size: 0.68rem; text-transform: uppercase; letter-spacing: 0.07em; color: var(--vatt-muted); font-weight: 600; }
    .vatt-chip__value { font-size: 1.15rem; font-weight: 800; color: var(--vatt-text); }

    /* ── Filter bar ─────────────────────────────────── */
    .vatt-filter-bar {
        background: #fff;
        border: 1px solid var(--vatt-border);
        border-radius: 14px;
        padding: 0.85rem 1.1rem;
        box-shadow: 0 2px 10px rgba(15,23,42,0.04);
    }
    .vatt-filter-row {
        display: flex; flex-wrap: wrap; gap: 0.5rem; align-items: flex-end;
    }
    .vatt-filter-field { display: flex; flex-direction: column; gap: 0.25rem; flex: 1; min-width: 140px; }
    .vatt-filter-field label { font-size: 0.72rem; font-weight: 600; color: var(--vatt-ink); }
    .vatt-input {
        padding: 0.5rem 0.65rem;
        border: 1px solid #cbd5e1;
        border-radius: 8px;
        font-size: 0.86rem;
        color: var(--vatt-text);
        background: #fff;
        width: 100%;
        font-family: inherit;
    }
    .vatt-input:focus { outline: none; border-color: var(--vatt-indigo); box-shadow: 0 0 0 3px rgba(79,70,229,0.1); }
    .vatt-btn {
        display: inline-flex; align-items: center; gap: 0.35rem;
        padding: 0.5rem 0.9rem;
        background: var(--vatt-indigo); color: #fff;
        border: none; border-radius: 8px;
        font-size: 0.84rem; font-weight: 700;
        cursor: pointer; font-family: inherit; white-space: nowrap;
    }
    .vatt-btn:hover { opacity: 0.88; }
    .vatt-btn--ghost {
        background: transparent; color: var(--vatt-indigo);
        border: 1px solid var(--vatt-indigo);
    }

    /* ── Table card ─────────────────────────────────── */
    .vatt-card {
        background: #fff;
        border: 1px solid var(--vatt-border);
        border-radius: 16px;
        box-shadow: 0 4px 18px rgba(15,23,42,0.05);
        overflow: hidden;
    }
    .vatt-card-head {
        padding: 0.9rem 1.25rem;
        border-bottom: 1px solid var(--vatt-border);
        display: flex; align-items: center; gap: 0.55rem;
    }
    .vatt-card-head-icon {
        width: 1.8rem; height: 1.8rem;
        background: linear-gradient(135deg, #ccfbf1, #a7f3d0);
        border-radius: 8px;
        display: flex; align-items: center; justify-content: center;
        color: var(--vatt-teal); font-size: 0.82rem;
    }
    .vatt-card-head h3 { margin: 0; font-size: 0.92rem; font-weight: 700; color: var(--vatt-text); }
    .vatt-card-head .vatt-count { margin-left: auto; font-size: 0.78rem; color: var(--vatt-muted); }

    /* ── Table ──────────────────────────────────────── */
    .vatt-table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
    .vatt-table thead tr { background: #f8fafc; }
    .vatt-table th {
        padding: 0.65rem 1rem;
        text-align: left;
        font-size: 0.68rem; font-weight: 700;
        text-transform: uppercase; letter-spacing: 0.07em;
        color: var(--vatt-muted);
        border-bottom: 1px solid var(--vatt-border);
        white-space: nowrap;
    }
    .vatt-table td {
        padding: 0.75rem 1rem;
        color: var(--vatt-ink);
        border-bottom: 1px solid #f1f5f9;
        vertical-align: top;
    }
    .vatt-table tbody tr:last-child td { border-bottom: none; }
    .vatt-table tbody tr:hover td { background: #fafafe; }

    /* date badge */
    .vatt-date-badge {
        display: inline-block;
        padding: 0.22rem 0.55rem;
        background: #eef2ff; color: var(--vatt-indigo);
        border: 1px solid #c7d2fe;
        border-radius: 8px; font-size: 0.78rem; font-weight: 700;
        white-space: nowrap;
    }

    /* location */
    .vatt-loc-area  { font-weight: 600; color: var(--vatt-text); }
    .vatt-loc-block { font-size: 0.75rem; color: var(--vatt-muted); margin-top: 0.1rem; }
    .vatt-loc-dist  {
        display: inline-block; margin-top: 0.25rem;
        padding: 0.12rem 0.45rem;
        background: #f0fdf4; border: 1px solid #bbf7d0;
        color: #15803d; border-radius: 999px;
        font-size: 0.68rem; font-weight: 700;
    }

    /* village tags */
    .vatt-village-count { font-weight: 700; color: var(--vatt-text); margin-bottom: 0.3rem; }
    .vatt-village-tags { display: flex; flex-wrap: wrap; gap: 0.28rem; }
    .vatt-village-tag {
        display: inline-block;
        padding: 0.13rem 0.48rem;
        background: #f0fdf4; border: 1px solid #bbf7d0;
        color: #15803d; border-radius: 999px;
        font-size: 0.7rem; font-weight: 600;
    }

    /* stat */
    .vatt-num { font-weight: 700; font-size: 0.95rem; }

    /* CFA status badges */
    .cfa-match    { display:inline-flex;align-items:center;gap:0.28rem;padding:0.22rem 0.55rem;border-radius:999px;font-size:0.72rem;font-weight:700;background:#dcfce7;color:#15803d;border:1px solid #bbf7d0;white-space:nowrap; }
    .cfa-mismatch { display:inline-flex;align-items:center;gap:0.28rem;padding:0.22rem 0.55rem;border-radius:999px;font-size:0.72rem;font-weight:700;background:#fef2f2;color:#b91c1c;border:1px solid #fecaca;white-space:nowrap; }
    .cfa-neutral  { display:inline-flex;align-items:center;gap:0.28rem;padding:0.22rem 0.55rem;border-radius:999px;font-size:0.72rem;font-weight:700;background:#f1f5f9;color:#64748b;border:1px solid #e2e8f0;white-space:nowrap; }

    /* empty state */
    .vatt-empty { padding: 3rem 1rem; text-align: center; color: var(--vatt-muted); font-size: 0.88rem; }
    .vatt-empty i { display: block; font-size: 2.4rem; color: #c7d2fe; margin-bottom: 0.55rem; }
    .vatt-empty a {
        display: inline-flex; align-items: center; gap: 0.35rem;
        margin-top: 0.65rem; padding: 0.48rem 0.85rem;
        background: var(--vatt-indigo); color: #fff;
        border-radius: 9px; font-size: 0.83rem; font-weight: 700;
        text-decoration: none;
    }
</style>
@endpush

@section('content')
<div class="vatt-shell">

    {{-- Migration warning --}}
    @if (!empty($migrationMissing))
        <div style="background:#fffbeb;border:1px solid #fde68a;color:#92400e;border-radius:12px;padding:0.85rem 1rem;font-size:0.88rem;">
            <strong>Attendance table not found.</strong> Run <code>php artisan migrate</code> to activate this module.
        </div>
    @endif

    {{-- Banner --}}
    <div class="vatt-banner">
        <div class="vatt-banner__left">
            <div class="vatt-banner__icon"><i class="fa-solid fa-clock-rotate-left"></i></div>
            <div class="vatt-banner__body">
                <h2>My Attendance Records</h2>
                <p>{{ $user->name }} &middot;
                    <strong>{{ $user->district?->name ?? 'No district' }}</strong> &middot;
                    {{ $reports->total() }} report(s) found
                </p>
            </div>
        </div>
        <div class="vatt-banner__actions">
            <a href="{{ route('staff.attendance.index') }}">
                <i class="fa-solid fa-plus"></i> Submit new
            </a>
        </div>
    </div>

    {{-- Summary chips --}}
    <div class="vatt-chips">
        <div class="vatt-chip">
            <div class="vatt-chip__icon vatt-chip__icon--indigo"><i class="fa-solid fa-calendar-check"></i></div>
            <div>
                <div class="vatt-chip__label">Reports</div>
                <div class="vatt-chip__value">{{ number_format($reports->total()) }}</div>
            </div>
        </div>
        <div class="vatt-chip">
            <div class="vatt-chip__icon vatt-chip__icon--teal"><i class="fa-solid fa-map-pin"></i></div>
            <div>
                <div class="vatt-chip__label">Villages visited</div>
                <div class="vatt-chip__value">{{ number_format($totalVillages ?? 0) }}</div>
            </div>
        </div>
        <div class="vatt-chip">
            <div class="vatt-chip__icon vatt-chip__icon--amber"><i class="fa-solid fa-users"></i></div>
            <div>
                <div class="vatt-chip__label">Participants</div>
                <div class="vatt-chip__value">{{ number_format($totalParticipants ?? 0) }}</div>
            </div>
        </div>
        <div class="vatt-chip">
            <div class="vatt-chip__icon vatt-chip__icon--rose"><i class="fa-solid fa-file-pen"></i></div>
            <div>
                <div class="vatt-chip__label">CFAs filled</div>
                <div class="vatt-chip__value">{{ number_format($totalCfas ?? 0) }}</div>
            </div>
        </div>
        <div class="vatt-chip">
            <div class="vatt-chip__icon vatt-chip__icon--indigo"><i class="fa-solid fa-bullhorn"></i></div>
            <div>
                <div class="vatt-chip__label">Outreach</div>
                <div class="vatt-chip__value">{{ number_format($totalOutreach ?? 0) }}</div>
            </div>
        </div>
    </div>

    {{-- Filter bar --}}
    <div class="vatt-filter-bar">
        <form method="get" action="{{ route('staff.attendance.view') }}">
            <div class="vatt-filter-row">
                <div class="vatt-filter-field" style="max-width:160px;">
                    <label>From date</label>
                    <input type="date" name="from" value="{{ request('from') }}" class="vatt-input">
                </div>
                <div class="vatt-filter-field" style="max-width:160px;">
                    <label>To date</label>
                    <input type="date" name="to" value="{{ request('to') }}" class="vatt-input">
                </div>
                @if (!empty($blockOptions))
                    <div class="vatt-filter-field" style="max-width:200px;">
                        <label>Block</label>
                        <select name="block" class="vatt-input">
                            <option value="">— All blocks —</option>
                            @foreach ($blockOptions as $b)
                                <option value="{{ $b }}" @selected(request('block') === $b)>{{ $b }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
                <div style="display:flex;gap:0.4rem;align-items:flex-end;">
                    <button type="submit" class="vatt-btn">
                        <i class="fa-solid fa-filter"></i> Filter
                    </button>
                    <a href="{{ route('staff.attendance.view') }}" class="vatt-btn vatt-btn--ghost">Reset</a>
                </div>
            </div>
        </form>
    </div>

    {{-- Records table --}}
    <div class="vatt-card">
        <div class="vatt-card-head">
            <div class="vatt-card-head-icon"><i class="fa-solid fa-table-list"></i></div>
            <h3>Attendance records</h3>
            <span class="vatt-count">{{ number_format($reports->total()) }} total &middot; page {{ $reports->currentPage() }} of {{ $reports->lastPage() }}</span>
        </div>
        <div style="overflow-x:auto;">
            <table class="vatt-table">
                <thead>
                    <tr>
                        <th>#</th>
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
                    @forelse ($reports as $i => $row)
                        @php
                            $dateKey  = $row->visit_date?->format('Y-m-d');
                            $cfaCount = (int) ($cfaByDate[$dateKey] ?? 0);
                            $reported = (int) $row->cfas_filled_total;
                            $rowNum   = ($reports->currentPage() - 1) * $reports->perPage() + $i + 1;
                        @endphp
                        <tr>
                            {{-- Row number --}}
                            <td style="color:var(--vatt-muted);font-size:0.78rem;font-weight:600;">{{ $rowNum }}</td>

                            {{-- Visit date --}}
                            <td>
                                <span class="vatt-date-badge">
                                    {{ $row->visit_date?->format('d M Y') }}
                                </span>
                            </td>

                            {{-- Entry date --}}
                            <td style="color:var(--vatt-muted);font-size:0.8rem;">
                                {{ $row->entry_date?->format('d M Y') }}
                            </td>

                            {{-- Location --}}
                            <td>
                                @if($row->area)
                                    <div class="vatt-loc-area">{{ $row->area }}</div>
                                @endif
                                @if($row->block)
                                    <div class="vatt-loc-block">
                                        <i class="fa-solid fa-grip-lines-vertical" style="font-size:0.6rem;margin-right:2px;"></i>{{ $row->block }}
                                    </div>
                                @endif
                                @if($row->district?->name)
                                    <span class="vatt-loc-dist">
                                        <i class="fa-solid fa-location-dot" style="font-size:0.6rem;"></i>
                                        {{ $row->district->name }}
                                    </span>
                                @endif
                                @if(!$row->area && !$row->block && !$row->district?->name)
                                    <span style="color:var(--vatt-muted);">—</span>
                                @endif
                            </td>

                            {{-- Villages --}}
                            <td>
                                <div class="vatt-village-count">
                                    {{ number_format((int)$row->villages_visited_total) }} village(s)
                                </div>
                                @if(is_array($row->villages_covered) && count($row->villages_covered))
                                    <div class="vatt-village-tags">
                                        @foreach($row->villages_covered as $v)
                                            <span class="vatt-village-tag">{{ $v }}</span>
                                        @endforeach
                                    </div>
                                @endif
                            </td>

                            {{-- Stats --}}
                            <td><span class="vatt-num">{{ number_format((int)$row->participants_total) }}</span></td>
                            <td><span class="vatt-num">{{ number_format($reported) }}</span></td>
                            <td><span class="vatt-num">{{ number_format((int)$row->outreach_programmes_total) }}</span></td>

                            {{-- CFA status --}}
                            <td>
                                @if ($cfaCount > 0 && $cfaCount >= $reported)
                                    <span class="cfa-match">
                                        <i class="fa-solid fa-circle-check"></i>
                                        {{ $cfaCount }} CFA(s)
                                    </span>
                                @elseif ($cfaCount > 0)
                                    <span class="cfa-mismatch">
                                        <i class="fa-solid fa-triangle-exclamation"></i>
                                        {{ $cfaCount }} / {{ $reported }} reported
                                    </span>
                                @else
                                    <span class="cfa-neutral">
                                        <i class="fa-solid fa-circle-minus"></i>
                                        No CFAs
                                    </span>
                                @endif
                            </td>

                            {{-- Attachment --}}
                            <td>
                                @if ($row->attachment_path)
                                    <a href="{{ route('staff.attendance.attachment', $row) }}"
                                       style="display:inline-flex;align-items:center;gap:0.28rem;padding:0.28rem 0.55rem;background:#0f766e;color:#fff;border-radius:6px;font-size:0.74rem;font-weight:700;text-decoration:none;">
                                        <i class="fa-solid fa-download"></i> View
                                    </a>
                                @else
                                    <span style="color:#a1a1aa;">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10">
                                <div class="vatt-empty">
                                    <i class="fa-regular fa-folder-open"></i>
                                    No attendance records found.
                                    <br>
                                    <a href="{{ route('staff.attendance.index') }}">
                                        <i class="fa-solid fa-plus"></i> Submit your first report
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if ($reports instanceof \Illuminate\Contracts\Pagination\Paginator && $reports->hasPages())
        <div>{{ $reports->links() }}</div>
    @endif

</div>
@endsection
