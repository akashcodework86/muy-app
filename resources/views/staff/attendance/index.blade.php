@extends('layouts.admin')

@section('title', 'Attendance')
@section('heading', 'Field Coordinator Attendance')

@push('styles')
<style>
    :root {
        --att-indigo: #4f46e5;
        --att-teal:   #0d9488;
        --att-text:   #0f172a;
        --att-muted:  #64748b;
        --att-ink:    #334155;
        --att-border: #e2e8f0;
        --att-bg:     #f8fafc;
    }

    /* ── Page shell ────────────────────────────────── */
    .att-shell {
        display: flex;
        flex-direction: column;
        gap: 1.5rem;
        padding-bottom: 3rem;
        font-family: 'DM Sans', sans-serif;
        width: 100%;
    }

    /* ── Banner ─────────────────────────────────────── */
    .att-banner {
        background: linear-gradient(135deg, rgba(79,70,229,0.08), rgba(13,148,136,0.06));
        border: 1px solid rgba(99,102,241,0.18);
        border-radius: 18px;
        padding: 1.1rem 1.4rem;
        display: flex;
        align-items: center;
        gap: 1rem;
        flex-wrap: wrap;
    }
    .att-banner__icon {
        width: 2.8rem; height: 2.8rem;
        background: linear-gradient(135deg, var(--att-indigo), #7c3aed);
        border-radius: 12px;
        display: flex; align-items: center; justify-content: center;
        color: #fff; font-size: 1.2rem;
        flex-shrink: 0;
        box-shadow: 0 4px 14px rgba(79,70,229,0.3);
    }
    .att-banner__body h2 { margin: 0 0 0.2rem; font-size: 1rem; font-weight: 800; color: var(--att-text); }
    .att-banner__body p  { margin: 0; font-size: 0.82rem; color: var(--att-muted); }

    /* ── Form card ──────────────────────────────────── */
    .att-card {
        background: #fff;
        border: 1px solid var(--att-border);
        border-radius: 18px;
        box-shadow: 0 4px 20px rgba(15,23,42,0.05);
        overflow: hidden;
    }
    .att-card__head {
        padding: 1rem 1.4rem 0.85rem;
        border-bottom: 1px solid var(--att-border);
        display: flex;
        align-items: center;
        gap: 0.6rem;
    }
    .att-card__head-icon {
        width: 1.9rem; height: 1.9rem;
        background: linear-gradient(135deg, #eef2ff, #e0e7ff);
        border-radius: 8px;
        display: flex; align-items: center; justify-content: center;
        color: var(--att-indigo); font-size: 0.85rem;
    }
    .att-card__head h3 { margin: 0; font-size: 0.95rem; font-weight: 700; color: var(--att-text); }
    .att-card__body   { padding: 1.4rem; }

    /* ── Section labels ─────────────────────────────── */
    .att-section-label {
        font-size: 0.68rem;
        font-weight: 700;
        letter-spacing: 0.1em;
        text-transform: uppercase;
        color: var(--att-muted);
        margin: 0 0 0.75rem;
        padding-bottom: 0.4rem;
        border-bottom: 1px dashed var(--att-border);
    }

    /* ── Grid rows ──────────────────────────────────── */
    .att-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 1.25rem 1rem;
    }
    .att-grid + .att-section-label { margin-top: 1.6rem; }

    /* ── Individual field ───────────────────────────── */
    .att-field { display: flex; flex-direction: column; gap: 0.38rem; }

    .att-field label {
        font-size: 0.78rem;
        font-weight: 600;
        color: var(--att-ink);
        line-height: 1.3;
    }
    .att-field label .att-req {
        color: #e11d48;
        margin-left: 2px;
    }

    .att-input,
    .att-textarea {
        width: 100%;
        padding: 0.6rem 0.7rem;
        border: 1px solid #cbd5e1;
        border-radius: 9px;
        font-size: 0.88rem;
        color: var(--att-text);
        background: #fff;
        transition: border-color 0.18s, box-shadow 0.18s;
        box-sizing: border-box;
        font-family: inherit;
    }
    .att-input:focus,
    .att-textarea:focus {
        outline: none;
        border-color: var(--att-indigo);
        box-shadow: 0 0 0 3px rgba(79,70,229,0.12);
    }
    .att-input--readonly {
        background: var(--att-bg);
        color: var(--att-muted);
        cursor: default;
    }
    .att-input--number { font-variant-numeric: tabular-nums; }

    .att-textarea { resize: vertical; min-height: 90px; }

    /* ── File upload ────────────────────────────────── */
    .att-file-wrap {
        border: 1.5px dashed #c7d2fe;
        border-radius: 10px;
        background: #f5f3ff;
        padding: 0.9rem 1rem;
        display: flex;
        align-items: center;
        gap: 0.65rem;
        flex-wrap: wrap;
    }
    .att-file-wrap i { color: var(--att-indigo); font-size: 1.1rem; }
    .att-file-wrap span { font-size: 0.8rem; color: var(--att-muted); flex: 1; min-width: 0; }
    .att-file-input { flex: none; }

    /* ── Submit btn ─────────────────────────────────── */
    .att-submit-row { margin-top: 1.4rem; display: flex; align-items: center; gap: 0.75rem; }
    .att-btn {
        display: inline-flex; align-items: center; gap: 0.4rem;
        padding: 0.62rem 1.2rem;
        background: linear-gradient(135deg, var(--att-indigo), #7c3aed);
        color: #fff;
        border: none; border-radius: 10px;
        font-size: 0.88rem; font-weight: 700;
        cursor: pointer;
        box-shadow: 0 4px 14px rgba(79,70,229,0.28);
        transition: opacity 0.15s, transform 0.15s;
        font-family: inherit;
    }
    .att-btn:hover { opacity: 0.9; transform: translateY(-1px); }
    .att-btn:active { transform: none; opacity: 1; }

    /* ── History table ──────────────────────────────── */
    .att-table-wrap {
        background: #fff;
        border: 1px solid var(--att-border);
        border-radius: 18px;
        box-shadow: 0 4px 20px rgba(15,23,42,0.05);
        overflow: hidden;
    }
    .att-table-head {
        padding: 1rem 1.4rem 0.85rem;
        border-bottom: 1px solid var(--att-border);
        display: flex; align-items: center; gap: 0.6rem;
    }
    .att-table-head-icon {
        width: 1.9rem; height: 1.9rem;
        background: linear-gradient(135deg, #ccfbf1, #a7f3d0);
        border-radius: 8px;
        display: flex; align-items: center; justify-content: center;
        color: var(--att-teal); font-size: 0.85rem;
    }
    .att-table-head h3 { margin: 0; font-size: 0.95rem; font-weight: 700; color: var(--att-text); }
    .att-table { width: 100%; border-collapse: collapse; font-size: 0.855rem; }
    .att-table thead tr { background: #f8fafc; }
    .att-table th {
        padding: 0.7rem 1rem;
        text-align: left;
        font-size: 0.7rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        color: var(--att-muted);
        border-bottom: 1px solid var(--att-border);
        white-space: nowrap;
    }
    .att-table td {
        padding: 0.7rem 1rem;
        color: var(--att-ink);
        border-bottom: 1px solid #f1f5f9;
        vertical-align: middle;
    }
    .att-table tbody tr:last-child td { border-bottom: none; }
    .att-table tbody tr:hover td { background: #fafafa; }
    .att-badge {
        display: inline-block;
        padding: 0.2rem 0.5rem;
        border-radius: 999px;
        font-size: 0.72rem;
        font-weight: 700;
        background: #eef2ff;
        color: var(--att-indigo);
    }
    .att-badge--teal {
        background: #ccfbf1;
        color: #0f766e;
    }
    .att-empty {
        padding: 2.5rem 1rem;
        text-align: center;
        color: var(--att-muted);
        font-size: 0.88rem;
    }
    .att-empty i { display: block; font-size: 2rem; color: #c7d2fe; margin-bottom: 0.5rem; }

    /* ── Village tags ───────────────────────────────── */
    .att-village-tags { display: flex; flex-wrap: wrap; gap: 0.3rem; margin-top: 0.35rem; }
    .att-village-tag {
        display: inline-block;
        padding: 0.15rem 0.5rem;
        background: #f0fdf4;
        border: 1px solid #bbf7d0;
        color: #15803d;
        border-radius: 999px;
        font-size: 0.7rem;
        font-weight: 600;
    }

    /* ── CFA status (highlighted cell + brief) ─────── */
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
    .cfa-match    { display:inline-flex;align-items:center;gap:0.3rem;padding:0.28rem 0.65rem;border-radius:999px;font-size:0.74rem;font-weight:800;background:#15803d;color:#fff;border:1px solid #166534;box-shadow:0 1px 0 rgba(255,255,255,0.25) inset; }
    .cfa-mismatch { display:inline-flex;align-items:center;gap:0.3rem;padding:0.28rem 0.65rem;border-radius:999px;font-size:0.74rem;font-weight:800;background:#dc2626;color:#fff;border:1px solid #b91c1c;box-shadow:0 1px 0 rgba(255,255,255,0.2) inset; }
    .cfa-neutral  { display:inline-flex;align-items:center;gap:0.3rem;padding:0.28rem 0.65rem;border-radius:999px;font-size:0.74rem;font-weight:800;background:#d97706;color:#fff;border:1px solid #b45309;box-shadow:0 1px 0 rgba(255,255,255,0.2) inset; }
    .cfa-status-brief {
        margin-top: 0.38rem;
        font-size: 0.68rem;
        line-height: 1.35;
        color: var(--att-muted);
    }
    .cfa-status-cell--match .cfa-status-brief { color: #14532d; font-weight: 600; }
    .cfa-status-cell--mismatch .cfa-status-brief { color: #7f1d1d; font-weight: 600; }
    .cfa-status-cell--neutral .cfa-status-brief { color: #92400e; font-weight: 600; }

    @media (max-width: 640px) {
        .att-grid { grid-template-columns: 1fr; }
        .att-card__body { padding: 1rem; }
    }
</style>
@endpush

@section('content')
<div class="att-shell">

    {{-- Migration warning --}}
    @if (!empty($migrationMissing))
        <div style="background:#fffbeb;border:1px solid #fde68a;color:#92400e;border-radius:12px;padding:0.85rem 1rem;font-size:0.88rem;">
            <strong>Attendance table not found.</strong> Run <code>php artisan migrate</code> to activate this module.
        </div>
    @endif

    {{-- Success / error flash --}}
    @if (session('status'))
        <div style="background:#f0fdf4;border:1px solid #86efac;color:#166534;border-radius:12px;padding:0.85rem 1rem;font-size:0.88rem;display:flex;align-items:center;gap:0.5rem;">
            <i class="fa-solid fa-circle-check"></i> {{ session('status') }}
        </div>
    @endif
    @if ($errors->any())
        <div style="background:#fef2f2;border:1px solid #fca5a5;color:#991b1b;border-radius:12px;padding:0.85rem 1rem;font-size:0.88rem;">
            <strong>Please fix the following:</strong>
            <ul style="margin:0.4rem 0 0 1.1rem;padding:0;">
                @foreach ($errors->all() as $e)
                    <li>{{ $e }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Banner --}}
    <div class="att-banner">
        <div class="att-banner__icon"><i class="fa-solid fa-calendar-check"></i></div>
        <div class="att-banner__body">
            <h2>Field Coordinator Attendance</h2>
            <p>Submit your daily field visit report. All entries are visible to the state admin.</p>
        </div>
    </div>

    {{-- Submit form card --}}
    <div class="att-card">
        <div class="att-card__head">
            <div class="att-card__head-icon"><i class="fa-solid fa-pen-to-square"></i></div>
            <h3>Submit attendance report</h3>
        </div>
        <div class="att-card__body">
            <form method="post" action="{{ route('staff.attendance.store') }}" enctype="multipart/form-data">
                @csrf

                {{-- Section 1: Identity & Dates --}}
                <p class="att-section-label"><i class="fa-solid fa-user" style="margin-right:0.35rem;"></i>Identity &amp; Dates</p>
                <div class="att-grid">
                    <div class="att-field">
                        <label>Name of Field Coordinator</label>
                        <input type="text" value="{{ auth()->user()->name }}" readonly class="att-input att-input--readonly">
                    </div>
                    <div class="att-field">
                        <label>District</label>
                        <input type="text" value="{{ auth()->user()->district?->name ?? 'Not assigned' }}" readonly class="att-input att-input--readonly">
                    </div>
                    <div class="att-field">
                        <label>Date of the Visit <span class="att-req">*</span></label>
                        <input type="date" name="visit_date" value="{{ old('visit_date') }}" required class="att-input">
                    </div>
                    <div class="att-field">
                        <label>Date (Entry) <span class="att-req">*</span></label>
                        <input type="date" name="entry_date" value="{{ old('entry_date', now()->toDateString()) }}" required class="att-input">
                    </div>
                </div>

                {{-- Section 2: Location --}}
                <p class="att-section-label" style="margin-top:1.6rem;"><i class="fa-solid fa-location-dot" style="margin-right:0.35rem;"></i>Location</p>
                <div class="att-grid">
                    <div class="att-field">
                        <label>Area</label>
                        <input type="text" name="area" value="{{ old('area') }}" placeholder="Enter area name" class="att-input">
                    </div>
                    <div class="att-field">
                        <label>Block</label>
                        @if (!empty($blocks))
                            <select name="block" class="att-input">
                                <option value="">— Select block —</option>
                                @foreach ($blocks as $blockName)
                                    <option value="{{ $blockName }}" @selected(old('block') === $blockName)>
                                        {{ $blockName }}
                                    </option>
                                @endforeach
                            </select>
                        @else
                            <input type="text" name="block" value="{{ old('block') }}" placeholder="Enter block name" class="att-input">
                        @endif
                        @if (empty($blocks))
                            <span style="font-size:0.72rem;color:var(--att-muted);">No blocks configured for your district — type manually.</span>
                        @endif
                    </div>
                </div>

                {{-- Section 3: Visit stats --}}
                <p class="att-section-label" style="margin-top:1.6rem;"><i class="fa-solid fa-chart-bar" style="margin-right:0.35rem;"></i>Visit Statistics</p>
                <div class="att-grid">
                    <div class="att-field">
                        <label>Total Number of Villages Visited <span class="att-req">*</span></label>
                        <input type="number" name="villages_visited_total" value="{{ old('villages_visited_total', 0) }}" min="0" required class="att-input att-input--number">
                    </div>
                    <div class="att-field">
                        <label>Total Number of Participants <span class="att-req">*</span></label>
                        <input type="number" name="participants_total" value="{{ old('participants_total', 0) }}" min="0" required class="att-input att-input--number">
                    </div>
                    <div class="att-field">
                        <label>Total Number of CFAs Filled <span class="att-req">*</span></label>
                        <input type="number" name="cfas_filled_total" value="{{ old('cfas_filled_total', 0) }}" min="0" required class="att-input att-input--number">
                    </div>
                    <div class="att-field">
                        <label>Number of Outreach Programmes Conducted <span class="att-req">*</span></label>
                        <input type="number" name="outreach_programmes_total" value="{{ old('outreach_programmes_total', 0) }}" min="0" required class="att-input att-input--number">
                    </div>
                </div>

                {{-- Villages covered --}}
                <div class="att-field" style="margin-top:1.25rem;">
                    <label>Names of Villages Covered <span style="font-weight:400;color:var(--att-muted);">(one per line)</span></label>
                    <textarea name="villages_covered" rows="4" class="att-textarea" placeholder="Village 1&#10;Village 2&#10;Village 3">{{ old('villages_covered') }}</textarea>
                </div>

                {{-- Document attach --}}
                <div style="margin-top:1.25rem;">
                    <label style="display:block;font-size:0.78rem;font-weight:600;color:var(--att-ink);margin-bottom:0.5rem;">
                        Document Attachment <span style="font-weight:400;color:var(--att-muted);">(optional — pdf, jpg, png, max 5 MB)</span>
                    </label>
                    <div class="att-file-wrap">
                        <i class="fa-solid fa-paperclip"></i>
                        <span>Attach supporting document for this visit</span>
                        <input type="file" name="attachment" accept=".pdf,.jpg,.jpeg,.png,.webp" class="att-file-input att-input" style="max-width:260px;">
                    </div>
                </div>

                {{-- Submit --}}
                <div class="att-submit-row">
                    <button type="submit" class="att-btn">
                        <i class="fa-solid fa-paper-plane"></i> Submit report
                    </button>
                    <span style="font-size:0.78rem;color:var(--att-muted);"><span class="att-req">*</span> required</span>
                </div>

            </form>
        </div>
    </div>

    {{-- History table --}}
    <div class="att-table-wrap" id="att-history">
        <div class="att-table-head">
            <div class="att-table-head-icon"><i class="fa-solid fa-clock-rotate-left"></i></div>
            <h3>My submitted reports</h3>
        </div>
        <div style="overflow-x:auto;">
            <table class="att-table">
                <thead>
                    <tr>
                        <th>Visit date</th>
                        <th>District</th>
                        <th>Area / Block</th>
                        <th>Villages covered</th>
                        <th>Participants</th>
                        <th>CFAs filled</th>
                        <th>Outreach</th>
                        <th>CFA status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($reports as $row)
                        @php
                            $dateKey  = $row->visit_date?->format('Y-m-d');
                            $cfaCount = (int) ($cfaByDate[$dateKey] ?? 0);
                            $reported = (int) $row->cfas_filled_total;
                        @endphp
                        <tr>
                            <td><span class="att-badge">{{ $row->visit_date?->format('d M Y') }}</span></td>
                            <td>{{ $row->district?->name ?? '—' }}</td>
                            <td style="color:var(--att-muted);font-size:0.82rem;">
                                @if($row->area)<div>{{ $row->area }}</div>@endif
                                @if($row->block)<div>{{ $row->block }}</div>@endif
                                @if(!$row->area && !$row->block)—@endif
                            </td>
                            <td>
                                <div style="font-weight:600;margin-bottom:0.2rem;">
                                    {{ number_format((int) $row->villages_visited_total) }} village(s)
                                </div>
                                @if(is_array($row->villages_covered) && count($row->villages_covered))
                                    <div class="att-village-tags">
                                        @foreach($row->villages_covered as $v)
                                            <span class="att-village-tag">{{ $v }}</span>
                                        @endforeach
                                    </div>
                                @endif
                            </td>
                            <td>{{ number_format((int) $row->participants_total) }}</td>
                            <td>{{ number_format($reported) }}</td>
                            <td>{{ number_format((int) $row->outreach_programmes_total) }}</td>
                            <td>
                                @if ($cfaCount > 0 && $cfaCount >= $reported)
                                    <div class="cfa-status-cell cfa-status-cell--match">
                                        <span class="cfa-match">
                                            <i class="fa-solid fa-circle-check"></i>
                                            {{ $cfaCount }} CFA(s) match
                                        </span>
                                        <div class="cfa-status-brief">
                                            Referral CFAs created on this visit date meet or exceed the number you reported ({{ $reported }}).
                                        </div>
                                    </div>
                                @elseif ($cfaCount > 0)
                                    <div class="cfa-status-cell cfa-status-cell--mismatch">
                                        <span class="cfa-mismatch">
                                            <i class="fa-solid fa-triangle-exclamation"></i>
                                            {{ $cfaCount }} CFA(s) vs {{ $reported }} reported
                                        </span>
                                        <div class="cfa-status-brief">
                                            Fewer CFA applications on this date than you reported — check referrals or correct the count.
                                        </div>
                                    </div>
                                @else
                                    <div class="cfa-status-cell cfa-status-cell--neutral">
                                        <span class="cfa-neutral">
                                            <i class="fa-solid fa-circle-minus"></i>
                                            No CFAs on this date
                                        </span>
                                        <div class="cfa-status-brief">
                                            No CFA submissions via your referral link were created on this visit date (by submission date).
                                        </div>
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8">
                                <div class="att-empty">
                                    <i class="fa-regular fa-folder-open"></i>
                                    No attendance reports submitted yet. Use the form above to submit your first report.
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
