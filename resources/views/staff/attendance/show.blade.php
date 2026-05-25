@extends('layouts.admin')
@php $rp = $routePrefix ?? 'staff.attendance'; $mp = $modelParam ?? 'attendanceReport'; @endphp

@section('title', 'Workshop details — ' . ($report->visit_date?->format('d M Y') ?? ''))
@section('heading', 'Workshop details')

@push('styles')
<style>
    :root {
        --ws-indigo: #4f46e5;
        --ws-teal:   #0d9488;
        --ws-rose:   #e11d48;
        --ws-text:   #0f172a;
        --ws-muted:  #64748b;
        --ws-ink:    #334155;
        --ws-border: #e2e8f0;
        --ws-bg:     #f8fafc;
    }

    .ws-shell { display:flex;flex-direction:column;gap:1.5rem;padding-bottom:3.5rem;font-family:'DM Sans',sans-serif;width:100%; }

    /* Breadcrumb */
    .ws-breadcrumb { display:flex;align-items:center;gap:0.4rem;font-size:0.8rem;color:var(--ws-muted); }
    .ws-breadcrumb a { color:var(--ws-indigo);text-decoration:none;font-weight:600; }
    .ws-breadcrumb a:hover { text-decoration:underline; }
    .ws-breadcrumb__sep { color:var(--ws-border); }

    /* Banner */
    .ws-banner { background:linear-gradient(135deg,rgba(79,70,229,0.07),rgba(13,148,136,0.06));border:1px solid rgba(79,70,229,0.14);border-radius:18px;padding:1.1rem 1.4rem;display:flex;align-items:flex-start;justify-content:space-between;gap:1rem;flex-wrap:wrap; }
    .ws-banner__left { display:flex;align-items:center;gap:1rem; }
    .ws-banner__icon { width:3rem;height:3rem;background:linear-gradient(135deg,var(--ws-indigo),#7c3aed);border-radius:14px;display:flex;align-items:center;justify-content:center;color:#fff;font-size:1.3rem;flex-shrink:0;box-shadow:0 4px 14px rgba(79,70,229,0.3); }
    .ws-banner__title { margin:0 0 0.2rem;font-size:1.05rem;font-weight:800;color:var(--ws-text); }
    .ws-banner__sub   { margin:0;font-size:0.82rem;color:var(--ws-muted); }
    .ws-banner__actions { display:flex;gap:0.5rem;flex-wrap:wrap;align-items:flex-start;margin-top:0.15rem; }

    /* Buttons */
    .ws-btn { display:inline-flex;align-items:center;gap:0.38rem;padding:0.52rem 1rem;border:none;border-radius:9px;font-size:0.84rem;font-weight:700;cursor:pointer;text-decoration:none;font-family:inherit; }
    .ws-btn--indigo { background:linear-gradient(135deg,var(--ws-indigo),#7c3aed);color:#fff; }
    .ws-btn--teal   { background:linear-gradient(135deg,var(--ws-teal),#0f766e);color:#fff; }
    .ws-btn--ghost  { background:#fff;color:var(--ws-indigo);border:1px solid #c7d2fe; }
    .ws-btn--ghost:hover { background:#eef2ff; }
    .ws-btn--sm { padding:0.4rem 0.75rem;font-size:0.78rem; }

    /* Detail cards */
    .ws-card { background:#fff;border:1px solid var(--ws-border);border-radius:18px;box-shadow:0 4px 20px rgba(15,23,42,0.05);overflow:hidden; }
    .ws-card__head { padding:0.9rem 1.4rem;border-bottom:1px solid var(--ws-border);display:flex;align-items:center;gap:0.6rem;flex-wrap:wrap; }
    .ws-card__head-icon { width:2rem;height:2rem;border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:0.9rem;flex-shrink:0; }
    .ws-card__head-icon--indigo { background:#eef2ff;color:var(--ws-indigo); }
    .ws-card__head-icon--teal   { background:#ccfbf1;color:#0f766e; }
    .ws-card__head-icon--amber  { background:#fef9c3;color:#a16207; }
    .ws-card__head-icon--blue   { background:#dbeafe;color:#1d4ed8; }
    .ws-card__head h3 { margin:0;font-size:0.95rem;font-weight:700;color:var(--ws-text); }
    .ws-card__head-actions { margin-left:auto; }
    .ws-card__body { padding:1.4rem; }

    /* Detail grid */
    .ws-detail-grid { display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:1rem 1.5rem; }
    .ws-detail-item { display:flex;flex-direction:column;gap:0.25rem; }
    .ws-detail-label { font-size:0.68rem;font-weight:700;text-transform:uppercase;letter-spacing:0.08em;color:var(--ws-muted); }
    .ws-detail-value { font-size:0.9rem;font-weight:600;color:var(--ws-text); }
    .ws-detail-value--muted { color:var(--ws-muted);font-weight:400; }

    /* Participant count chips */
    .ws-count-chips { display:flex;flex-wrap:wrap;gap:0.65rem;margin-top:0.5rem; }
    .ws-count-chip { display:flex;align-items:center;gap:0.5rem;padding:0.6rem 1rem;border-radius:12px;border:1px solid var(--ws-border); }
    .ws-count-chip--total  { background:linear-gradient(135deg,#eef2ff,#e0e7ff);border-color:#c7d2fe; }
    .ws-count-chip--male   { background:linear-gradient(135deg,#dbeafe,#eff6ff);border-color:#bfdbfe; }
    .ws-count-chip--female { background:linear-gradient(135deg,#ffe4e6,#fff1f2);border-color:#fecdd3; }
    .ws-count-chip__num  { font-size:1.5rem;font-weight:800; }
    .ws-count-chip__label { font-size:0.72rem;font-weight:700;text-transform:uppercase;letter-spacing:0.06em; }
    .ws-count-chip--total  .ws-count-chip__num   { color:var(--ws-indigo); }
    .ws-count-chip--total  .ws-count-chip__label { color:var(--ws-indigo); }
    .ws-count-chip--male   .ws-count-chip__num   { color:#1d4ed8; }
    .ws-count-chip--male   .ws-count-chip__label { color:#1e40af; }
    .ws-count-chip--female .ws-count-chip__num   { color:var(--ws-rose); }
    .ws-count-chip--female .ws-count-chip__label { color:#be123c; }

    /* Photo grid */
    .ws-photo-grid { display:flex;flex-wrap:wrap;gap:0.65rem; }
    .ws-photo-item { position:relative;width:100px;height:100px;border-radius:10px;overflow:hidden;border:2px solid var(--ws-border); }
    .ws-photo-item img { width:100%;height:100%;object-fit:cover; }
    .ws-photo-item__overlay { position:absolute;inset:0;background:rgba(0,0,0,0.35);display:flex;align-items:center;justify-content:center;opacity:0;transition:opacity 0.15s;color:#fff;font-size:1.1rem; }
    .ws-photo-item:hover .ws-photo-item__overlay { opacity:1; }
    .ws-photo-empty { color:var(--ws-muted);font-size:0.88rem;padding:1rem 0; }

    /* Participant table */
    .ws-pt-scroll { overflow-x:auto;border:1px solid var(--ws-border);border-radius:12px;background:#fafbff; }
    .ws-pt-table { width:100%;border-collapse:collapse;font-size:0.84rem;min-width:700px; }
    .ws-pt-table thead tr { background:#f1f5f9; }
    .ws-pt-table th { padding:0.55rem 0.75rem;text-align:left;font-size:0.67rem;font-weight:700;text-transform:uppercase;letter-spacing:0.06em;color:var(--ws-muted);border-bottom:1px solid var(--ws-border);white-space:nowrap; }
    .ws-pt-table td { padding:0.55rem 0.75rem;color:var(--ws-ink);border-bottom:1px solid #ecf0f7;vertical-align:middle; }
    .ws-pt-table tbody tr:last-child td { border-bottom:none; }
    .ws-pt-table tbody tr:hover td { background:#f7f8ff; }
    .ws-pt-sr { text-align:center;font-weight:700;color:var(--ws-muted);font-size:0.77rem;width:2.5rem; }
    .ws-gender-m { display:inline-block;padding:0.15rem 0.5rem;border-radius:999px;background:#dbeafe;color:#1d4ed8;font-size:0.7rem;font-weight:800; }
    .ws-gender-f { display:inline-block;padding:0.15rem 0.5rem;border-radius:999px;background:#ffe4e6;color:var(--ws-rose);font-size:0.7rem;font-weight:800; }
    .ws-empty-row { text-align:center;padding:2.5rem 1rem;color:var(--ws-muted);font-size:0.88rem; }

    /* remark */
    .ws-remark { font-size:0.87rem;color:var(--ws-ink);background:var(--ws-bg);border-radius:10px;padding:0.75rem 1rem;border:1px solid var(--ws-border);line-height:1.6;white-space:pre-wrap; }

    /* status badge */
    .ws-status-badge { display:inline-flex;align-items:center;gap:0.35rem;padding:0.28rem 0.7rem;border-radius:999px;font-size:0.74rem;font-weight:700; }
    .ws-status-badge--submitted { background:#dcfce7;color:#15803d; }
    .ws-status-badge--draft { background:#fef9c3;color:#a16207; }
</style>
@endpush

@section('content')
<div class="ws-shell">

    {{-- Breadcrumb --}}
    <div class="ws-breadcrumb">
        <a href="{{ route($rp.'.view') }}"><i class="fa-solid fa-table-list"></i> Workshop records</a>
        <span class="ws-breadcrumb__sep">/</span>
        <span>{{ $report->visit_date?->format('d M Y') }} — {{ $report->block ?: 'Workshop' }}</span>
    </div>

    {{-- Banner --}}
    <div class="ws-banner">
        <div class="ws-banner__left">
            <div class="ws-banner__icon"><i class="fa-solid fa-people-group"></i></div>
            <div>
                <p class="ws-banner__title">{{ $report->block ?: 'Block Level Workshop' }}</p>
                <p class="ws-banner__sub">
                    {{ $report->visit_date?->format('d M Y') }}
                    @if ($report->district?->name) &middot; {{ $report->district->name }} @endif
                    &middot;
                    <span class="ws-status-badge {{ $report->isDraft() ? 'ws-status-badge--draft' : 'ws-status-badge--submitted' }}" style="vertical-align:middle;">
                        <i class="fa-solid {{ $report->isDraft() ? 'fa-pen-to-square' : 'fa-circle-check' }}"></i>
                        {{ $report->isDraft() ? 'Draft' : 'Submitted' }}
                    </span>
                </p>
            </div>
        </div>
        <div class="ws-banner__actions">
            @if ($report->isSubmitted() && (int) $report->field_coordinator_user_id === (int) auth()->id())
                <a href="{{ route($rp.'.edit', [$mp => $report]) }}" class="ws-btn ws-btn--ghost ws-btn--sm">
                    <i class="fa-solid fa-pen"></i> Edit
                </a>
                <form method="post" action="{{ route($rp.'.destroy', [$mp => $report]) }}" style="display:inline;" onsubmit="return confirm('Delete this workshop submission?');">
                    @csrf
                    @method('DELETE')
                    <input type="hidden" name="from_view" value="1">
                    <button type="submit" class="ws-btn ws-btn--ghost ws-btn--sm" style="color:#dc2626;border-color:#fecaca;">
                        <i class="fa-solid fa-trash"></i> Delete
                    </button>
                </form>
            @endif
            <a href="{{ route($rp.'.view') }}" class="ws-btn ws-btn--ghost ws-btn--sm">
                <i class="fa-solid fa-arrow-left"></i> Back
            </a>
        </div>
    </div>

    {{-- Workshop details card --}}
    <div class="ws-card">
        <div class="ws-card__head">
            <div class="ws-card__head-icon ws-card__head-icon--indigo"><i class="fa-solid fa-circle-info"></i></div>
            <h3>Workshop details</h3>
        </div>
        <div class="ws-card__body">
            <div class="ws-detail-grid">
                <div class="ws-detail-item">
                    <span class="ws-detail-label">Workshop date</span>
                    <span class="ws-detail-value">{{ $report->visit_date?->format('d M Y, l') ?? '—' }}</span>
                </div>
                <div class="ws-detail-item">
                    <span class="ws-detail-label">Submitted by</span>
                    <span class="ws-detail-value">{{ $report->field_coordinator_name ?: '—' }}</span>
                </div>
                <div class="ws-detail-item">
                    <span class="ws-detail-label">District</span>
                    <span class="ws-detail-value">{{ $report->district?->name ?? '—' }}</span>
                </div>
                <div class="ws-detail-item">
                    <span class="ws-detail-label">Block</span>
                    <span class="ws-detail-value">{{ $report->block ?: '—' }}</span>
                </div>
                <div class="ws-detail-item">
                    <span class="ws-detail-label">Gram panchayat</span>
                    <span class="ws-detail-value">{{ $report->gramPanchayat?->name ?? '—' }}</span>
                </div>
                <div class="ws-detail-item">
                    <span class="ws-detail-label">Area / Village</span>
                    <span class="ws-detail-value">{{ $report->area ?: '—' }}</span>
                </div>
                <div class="ws-detail-item">
                    <span class="ws-detail-label">Entry date</span>
                    <span class="ws-detail-value {{ $report->entry_date ? '' : 'ws-detail-value--muted' }}">
                        {{ $report->entry_date?->format('d M Y') ?? '—' }}
                    </span>
                </div>
            </div>

            <div class="ws-count-chips" style="margin-top:1.25rem;">
                <div class="ws-count-chip ws-count-chip--total">
                    <i class="fa-solid fa-users" style="color:var(--ws-indigo);font-size:1.1rem;"></i>
                    <div>
                        <div class="ws-count-chip__num">{{ number_format((int) $report->participants_total) }}</div>
                        <div class="ws-count-chip__label">Total</div>
                    </div>
                </div>
                <div class="ws-count-chip ws-count-chip--male">
                    <i class="fa-solid fa-person" style="color:#1d4ed8;font-size:1.1rem;"></i>
                    <div>
                        <div class="ws-count-chip__num">{{ number_format((int) $report->participants_male_count) }}</div>
                        <div class="ws-count-chip__label">Male</div>
                    </div>
                </div>
                <div class="ws-count-chip ws-count-chip--female">
                    <i class="fa-solid fa-person-dress" style="color:var(--ws-rose);font-size:1.1rem;"></i>
                    <div>
                        <div class="ws-count-chip__num">{{ number_format((int) $report->participants_female_count) }}</div>
                        <div class="ws-count-chip__label">Female</div>
                    </div>
                </div>
            </div>

            @if ($report->remark)
                <div style="margin-top:1.25rem;">
                    <div class="ws-detail-label" style="margin-bottom:0.4rem;">Remark / Notes</div>
                    <div class="ws-remark">{{ $report->remark }}</div>
                </div>
            @endif
        </div>
    </div>

    {{-- Workshop photos --}}
    <div class="ws-card">
        <div class="ws-card__head">
            <div class="ws-card__head-icon ws-card__head-icon--teal"><i class="fa-solid fa-images"></i></div>
            <h3>Workshop photos <span style="font-weight:400;color:var(--ws-muted);font-size:0.82rem;">({{ count($mediaItems) }})</span></h3>
        </div>
        <div class="ws-card__body">
            @if (count($mediaItems) > 0)
                <div class="ws-photo-grid">
                    @foreach ($mediaItems as $idx => $item)
                        <a href="{{ route($rp.'.attachment', [$mp => $report, 'index' => $idx, 'inline' => 1]) }}"
                           target="_blank" rel="noopener" class="ws-photo-item">
                            <img src="{{ route($rp.'.attachment', [$mp => $report, 'index' => $idx, 'inline' => 1]) }}"
                                 alt="Photo {{ $idx + 1 }}" loading="lazy">
                            <div class="ws-photo-item__overlay"><i class="fa-solid fa-magnifying-glass-plus"></i></div>
                        </a>
                    @endforeach
                </div>
            @elseif ($report->attachment_path)
                <div class="ws-photo-empty">
                    <a href="{{ route($rp.'.attachment', $report) }}" class="ws-btn ws-btn--teal ws-btn--sm">
                        <i class="fa-solid fa-download"></i> Download legacy attachment
                    </a>
                </div>
            @else
                <p class="ws-photo-empty"><i class="fa-regular fa-image" style="margin-right:0.35rem;"></i> No photos uploaded.</p>
            @endif
        </div>
    </div>

    {{-- Attendance sheet --}}
    <div class="ws-card">
        <div class="ws-card__head">
            <div class="ws-card__head-icon ws-card__head-icon--amber"><i class="fa-solid fa-file-excel"></i></div>
            <h3>Attendance sheet (Excel)</h3>
        </div>
        <div class="ws-card__body">
            @if ($report->hasAttendanceSheet())
                <div style="display:flex;align-items:center;gap:1rem;flex-wrap:wrap;">
                    <div>
                        <div style="font-weight:700;color:var(--ws-text);font-size:0.9rem;">
                            <i class="fa-solid fa-file-excel" style="color:#15803d;margin-right:0.35rem;"></i>
                            {{ $report->attendance_sheet_original_name ?? 'attendance-sheet.xlsx' }}
                        </div>
                        @if ($report->attendance_sheet_size_bytes)
                            <div style="font-size:0.75rem;color:var(--ws-muted);margin-top:0.15rem;">
                                {{ number_format(round($report->attendance_sheet_size_bytes / 1024, 1)) }} KB
                            </div>
                        @endif
                    </div>
                    <a href="{{ route($rp.'.sheet.download', $report) }}" class="ws-btn ws-btn--teal ws-btn--sm">
                        <i class="fa-solid fa-download"></i> Download sheet
                    </a>
                </div>
            @else
                <p style="color:var(--ws-muted);font-size:0.88rem;margin:0;">
                    No attendance sheet uploaded yet.
                    @if ((int) $report->participants_total > 0 && (int) $report->field_coordinator_user_id === (int) auth()->id())
                        <a href="{{ route($rp.'.sheet-template.report', $report) }}" class="ws-btn ws-btn--ghost ws-btn--sm" style="margin-left:0.75rem;">
                            <i class="fa-solid fa-download"></i> Download template
                        </a>
                    @endif
                </p>
                @if ((int) $report->participants_total > 0 && (int) $report->field_coordinator_user_id === (int) auth()->id())
                    <form method="post" action="{{ route($rp.'.sheet.upload', $report) }}" enctype="multipart/form-data" style="display:flex;gap:0.5rem;align-items:center;margin-top:0.75rem;flex-wrap:wrap;">
                        @csrf
                        <input type="file" name="attendance_sheet" accept=".xlsx,.xls,.csv" required style="font-size:0.82rem;">
                        <button type="submit" class="ws-btn ws-btn--teal ws-btn--sm">
                            <i class="fa-solid fa-cloud-arrow-up"></i> Upload filled sheet
                        </button>
                    </form>
                @endif
            @endif
        </div>
    </div>

    {{-- Participant register --}}
    <div class="ws-card">
        <div class="ws-card__head">
            <div class="ws-card__head-icon ws-card__head-icon--blue"><i class="fa-solid fa-list-ol"></i></div>
            <h3>
                Participant register
                <span style="font-weight:400;color:var(--ws-muted);font-size:0.82rem;">
                    ({{ count($participantRows) }} rows
                    @php
                        $filledCount = collect($participantRows)->filter(fn($p) => !empty($p['name']))->count();
                    @endphp
                    @if ($filledCount > 0), {{ $filledCount }} named)
                    @else )
                    @endif
                </span>
            </h3>
            <div class="ws-card__head-actions">
                @if (count($participantRows) > 0)
                    <a href="{{ route($rp.'.participants.export', $report) }}" class="ws-btn ws-btn--teal ws-btn--sm">
                        <i class="fa-solid fa-file-excel"></i> Export to Excel
                    </a>
                @endif
            </div>
        </div>

        <div class="ws-card__body" style="padding:0;">
            @if (count($participantRows) > 0)
                <div class="ws-pt-scroll">
                    <table class="ws-pt-table">
                        <thead>
                            <tr>
                                <th class="ws-pt-sr">#</th>
                                <th>Name</th>
                                <th>Mobile</th>
                                <th>Gender</th>
                                <th>District</th>
                                <th>Block</th>
                                <th>Gram panchayat</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($participantRows as $idx => $p)
                                <tr>
                                    <td class="ws-pt-sr">{{ (int) ($p['sr'] ?? $idx + 1) }}</td>
                                    <td>
                                        @if (!empty($p['name']))
                                            <span style="font-weight:600;color:var(--ws-text);">{{ $p['name'] }}</span>
                                        @else
                                            <span style="color:var(--ws-muted);font-size:0.8rem;">—</span>
                                        @endif
                                    </td>
                                    <td style="font-size:0.82rem;font-family:monospace;letter-spacing:0.02em;">
                                        {{ !empty($p['mobile']) ? $p['mobile'] : '—' }}
                                    </td>
                                    <td>
                                        @if (($p['gender'] ?? '') === 'M')
                                            <span class="ws-gender-m">M</span>
                                        @elseif (($p['gender'] ?? '') === 'F')
                                            <span class="ws-gender-f">F</span>
                                        @else
                                            <span style="color:var(--ws-muted);">—</span>
                                        @endif
                                    </td>
                                    <td style="font-size:0.82rem;">{{ !empty($p['district_name']) ? $p['district_name'] : '—' }}</td>
                                    <td style="font-size:0.82rem;">{{ !empty($p['block_name']) ? $p['block_name'] : '—' }}</td>
                                    <td style="font-size:0.82rem;">{{ !empty($p['gram_panchayat_name']) ? $p['gram_panchayat_name'] : '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr style="background:#f8fafc;border-top:2px solid var(--ws-border);">
                                <td colspan="3" style="padding:0.55rem 0.75rem;font-size:0.78rem;font-weight:700;color:var(--ws-muted);">Totals</td>
                                <td style="padding:0.55rem 0.75rem;">
                                    @php
                                        $mCount = collect($participantRows)->filter(fn($p) => ($p['gender'] ?? '') === 'M')->count();
                                        $fCount = collect($participantRows)->filter(fn($p) => ($p['gender'] ?? '') === 'F')->count();
                                    @endphp
                                    @if ($mCount > 0)
                                        <span class="ws-gender-m">{{ $mCount }} M</span>
                                    @endif
                                    @if ($fCount > 0)
                                        <span class="ws-gender-f" style="margin-left:0.25rem;">{{ $fCount }} F</span>
                                    @endif
                                    @if ($mCount === 0 && $fCount === 0)
                                        <span style="color:var(--ws-muted);">—</span>
                                    @endif
                                </td>
                                <td colspan="3" style="padding:0.55rem 0.75rem;font-size:0.78rem;color:var(--ws-muted);">
                                    {{ count($participantRows) }} rows total
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            @else
                <div class="ws-empty-row" style="padding:2.5rem 1.4rem;">
                    <i class="fa-regular fa-rectangle-list" style="font-size:1.8rem;display:block;margin-bottom:0.5rem;color:#c7d2fe;"></i>
                    No participant rows for this workshop.
                    @if ($report->isDraft() && (int) $report->field_coordinator_user_id === (int) auth()->id())
                        <br>
                        <a href="{{ route($rp.'.index', ['draft' => $report->id]) }}" class="ws-btn ws-btn--indigo ws-btn--sm" style="margin-top:0.65rem;">
                            <i class="fa-solid fa-pen-to-square"></i> Continue editing draft
                        </a>
                    @endif
                </div>
            @endif
        </div>
    </div>

</div>

@if (session('status'))
    <div style="position:fixed;bottom:1.25rem;right:1.25rem;z-index:9999;background:#f0fdf4;border:1px solid #86efac;color:#166534;border-radius:12px;padding:0.75rem 1.1rem;font-size:0.88rem;font-weight:600;box-shadow:0 4px 18px rgba(0,0,0,0.1);">
        <i class="fa-solid fa-circle-check"></i> {{ session('status') }}
    </div>
@endif
@endsection
