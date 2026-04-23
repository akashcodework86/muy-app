@extends('layouts.admin')

@section('title', 'My Field Reports')
@section('heading', 'My Field Reports')

@push('styles')
<style>
    :root { --fi-indigo:#4f46e5; --fi-teal:#0d9488; --fi-amber:#d97706; --fi-text:#0f172a; --fi-muted:#64748b; }
    .fi-shell { display:flex; flex-direction:column; gap:1rem; padding-bottom:3rem; font-family:'DM Sans',sans-serif; }
    .fi-header { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:.75rem; }
    .fi-header h2 { margin:0; font-size:1rem; font-weight:700; color:var(--fi-text); }
    .fi-btn {
        display:inline-flex; align-items:center; gap:.4rem;
        padding:.45rem 1.1rem; border-radius:9px;
        font-size:.85rem; font-weight:600; text-decoration:none;
        background:var(--fi-indigo); color:#fff;
        transition:background .15s;
    }
    .fi-btn:hover { background:#4338ca; }
    .fi-table-wrap {
        background:#fff; border:1px solid rgba(226,232,240,.9);
        border-radius:16px; box-shadow:0 4px 14px rgba(15,23,42,.05); overflow:hidden;
    }
    .fi-tbl { width:100%; border-collapse:collapse; font-size:.83rem; }
    .fi-tbl thead th {
        background:#f8fafc; text-align:left; padding:.55rem .9rem;
        font-size:.67rem; text-transform:uppercase; letter-spacing:.07em;
        font-weight:700; color:var(--fi-muted); border-bottom:1px solid #e2e8f0;
        white-space:nowrap;
    }
    .fi-tbl tbody tr { border-bottom:1px solid #f1f5f9; transition:background .1s; }
    .fi-tbl tbody tr:hover { background:#fafbff; }
    .fi-tbl tbody tr:last-child { border-bottom:none; }
    .fi-tbl td { padding:.6rem .9rem; color:var(--fi-text); vertical-align:middle; }
    .fi-muted { color:var(--fi-muted); }
    .fi-badge { display:inline-flex; align-items:center; gap:.25rem; padding:.18rem .5rem; border-radius:999px; font-size:.68rem; font-weight:700; }
    .fi-badge--ok   { background:#dcfce7; color:#15803d; }
    .fi-badge--warn { background:#fef9c3; color:#a16207; }
    .fi-badge--pending { background:#f1f5f9; color:#64748b; }
    .fi-doc-link { display:inline-flex; align-items:center; gap:.25rem; font-size:.78rem; color:var(--fi-indigo); font-weight:600; text-decoration:none; }
    .fi-doc-link:hover { text-decoration:underline; }
    .fi-edit-link { display:inline-flex; align-items:center; gap:.2rem; font-size:.75rem; color:var(--fi-muted); font-weight:600; text-decoration:none; padding:.18rem .5rem; border:1px solid #e2e8f0; border-radius:6px; transition:.12s; }
    .fi-edit-link:hover { background:#f1f5f9; color:var(--fi-text); border-color:#cbd5e1; }
    .fi-empty { text-align:center; padding:3rem 1rem; color:var(--fi-muted); }
    .fi-pagination { display:flex; gap:.35rem; justify-content:flex-end; padding:.75rem 1rem; border-top:1px solid #f1f5f9; flex-wrap:wrap; }
    .fi-pagination a, .fi-pagination span {
        display:inline-flex; align-items:center; justify-content:center;
        min-width:2rem; height:2rem; padding:0 .5rem;
        border:1px solid #e2e8f0; border-radius:7px;
        font-size:.8rem; color:var(--fi-muted); text-decoration:none; transition:.12s;
    }
    .fi-pagination a:hover { background:#f1f5f9; color:var(--fi-text); }
    .fi-pagination .active > span { background:var(--fi-indigo); color:#fff; border-color:var(--fi-indigo); }
    .fi-pagination .disabled span { opacity:.45; cursor:default; }
</style>
@endpush

@section('content')
<div class="fi-shell">

    <div class="fi-header">
        <h2>{{ $reports->total() }} report{{ $reports->total() === 1 ? '' : 's' }} submitted</h2>
        <a href="{{ route('staff.field-reports.create') }}" class="fi-btn">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14"/></svg>
            Submit New Report
        </a>
    </div>

    <div class="fi-table-wrap">
        @if ($reports->isEmpty())
            <p class="fi-empty">
                No reports yet. Click <a href="{{ route('staff.field-reports.create') }}" style="color:var(--fi-indigo); font-weight:600;">Submit New Report</a> to log your first field visit.
            </p>
        @else
        <div style="overflow-x:auto;">
            <table class="fi-tbl">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Date of Visit</th>
                        <th>District</th>
                        <th>Block</th>
                        <th>Villages</th>
                        <th>Participants</th>
                        <th>Outreach Progs</th>
                        <th>CFAs Reported</th>
                        <th>CFAs Verified</th>
                        <th>Match</th>
                        <th>Document</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($reports as $i => $r)
                    <tr>
                        <td class="fi-muted">{{ $reports->firstItem() + $i }}</td>
                        <td style="white-space:nowrap; font-weight:600;">{{ $r->visit_date?->format('d M Y') }}</td>
                        <td>{{ $r->district?->name ?? '—' }}</td>
                        <td>{{ $r->block?->name ?? '—' }}</td>
                        <td>
                            {{ $r->total_villages }}
                            @if ($r->village_names)
                                <span class="fi-muted" style="font-size:.72rem;">({{ Str::limit($r->village_names, 30) }})</span>
                            @endif
                        </td>
                        <td>{{ number_format($r->total_participants) }}</td>
                        <td>{{ $r->outreach_programmes }}</td>
                        <td>{{ $r->cfas_reported }}</td>
                        <td>
                            @if ($r->cfas_verified !== null)
                                {{ $r->cfas_verified }}
                            @else
                                <span class="fi-muted">—</span>
                            @endif
                        </td>
                        <td>
                            @if ($r->cfas_verified === null)
                                <span class="fi-badge fi-badge--pending">Pending</span>
                            @elseif ($r->has_discrepancy)
                                <span class="fi-badge fi-badge--warn">⚠ Mismatch</span>
                            @else
                                <span class="fi-badge fi-badge--ok">✓ Verified</span>
                            @endif
                        </td>
                        <td>
                            @if ($r->attachment_path)
                                <a href="{{ Storage::disk('public')->url($r->attachment_path) }}" target="_blank" class="fi-doc-link">
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8l-5-5Z"/><path d="M14 3v5h5"/></svg>
                                    View
                                </a>
                            @else
                                <span class="fi-muted">—</span>
                            @endif
                        </td>
                        <td>
                            <a href="{{ route('staff.field-reports.edit', $r) }}" class="fi-edit-link">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5Z"/></svg>
                                Edit
                            </a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if ($reports->hasPages())
        <div class="fi-pagination">
            {{ $reports->links() }}
        </div>
        @endif
        @endif
    </div>

</div>
@endsection
