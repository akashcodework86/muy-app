@extends('layouts.admin')

@section('title', $batch->name ?? 'Batch')
@section('heading', $batch->name ?? 'Batch')

@push('styles')
<style>
    .batch-shell { display: flex; flex-direction: column; gap: 1rem; }
    .batch-crumbs { font-size: 0.82rem; color: #64748b; }
    .batch-crumbs a { color: #0d9488; text-decoration: none; font-weight: 600; }
    .batch-crumbs a:hover { text-decoration: underline; }

    .batch-hero {
        padding: 1.1rem 1.25rem;
        border-radius: 16px;
        background: linear-gradient(135deg, #ffffff 0%, #f0fdfa 60%, #eef2ff 100%);
        border: 1px solid rgba(20, 184, 166, 0.25);
        box-shadow: 0 12px 30px -16px rgba(79, 70, 229, 0.2);
        display: grid;
        grid-template-columns: 1.4fr 1fr;
        gap: 1rem;
        align-items: start;
    }
    .batch-hero__meta { display: flex; flex-direction: column; gap: 0.35rem; }
    .batch-hero__name { font-size: 1.25rem; font-weight: 700; color: #0f172a; margin: 0; }
    .batch-hero__sub  { font-size: 0.88rem; color: #475569; margin: 0; }
    .batch-hero__chips { display: flex; flex-wrap: wrap; gap: 0.4rem; margin-top: 0.25rem; }
    .chip {
        display: inline-flex; align-items: center; gap: 0.3rem;
        padding: 0.2rem 0.6rem; border-radius: 999px;
        font-size: 0.72rem; font-weight: 700; letter-spacing: 0.04em;
    }
    .chip--teal   { background: rgba(20, 184, 166, 0.14); color: #0f766e; border: 1px solid rgba(20, 184, 166, 0.35); }
    .chip--indigo { background: rgba(99, 102, 241, 0.12); color: #3730a3; border: 1px solid rgba(99, 102, 241, 0.3); }
    .chip--amber  { background: rgba(245, 158, 11, 0.15); color: #92400e; border: 1px solid rgba(245, 158, 11, 0.35); }
    .chip--rose   { background: rgba(239, 68, 68, 0.14); color: #991b1b; border: 1px solid rgba(239, 68, 68, 0.35); }
    .chip--green  { background: rgba(16, 185, 129, 0.14); color: #065f46; border: 1px solid rgba(16, 185, 129, 0.35); }
    .chip--muted  { background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; }

    .hero-stats { display: grid; grid-template-columns: repeat(2, 1fr); gap: 0.5rem; }
    .hero-stat {
        padding: 0.7rem 0.8rem;
        background: rgba(255, 255, 255, 0.85);
        border: 1px solid rgba(148, 163, 184, 0.3);
        border-radius: 12px;
    }
    .hero-stat__label { font-size: 0.68rem; text-transform: uppercase; letter-spacing: 0.08em; color: #64748b; font-weight: 700; }
    .hero-stat__value { font-size: 1.2rem; font-weight: 700; color: #0f172a; line-height: 1.1; margin-top: 0.2rem; }

    .mix-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
    .mix-card {
        padding: 1rem 1.1rem;
        background: #fff;
        border: 1px solid rgba(148, 163, 184, 0.3);
        border-radius: 14px;
    }
    .mix-card h3 {
        font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.08em;
        color: #64748b; font-weight: 700; margin: 0 0 0.6rem;
    }
    .mix-row { display: grid; grid-template-columns: 110px 1fr 40px; gap: 0.5rem; align-items: center; margin-bottom: 0.45rem; }
    .mix-row__label { font-size: 0.82rem; color: #334155; font-weight: 600; }
    .mix-row__bar { position: relative; height: 8px; background: #f1f5f9; border-radius: 999px; overflow: hidden; }
    .mix-row__bar-fill { position: absolute; inset: 0 auto 0 0; border-radius: inherit; }
    .mix-row__val { font-size: 0.82rem; color: #0f172a; font-weight: 700; text-align: right; }

    .seg--seed    { background: linear-gradient(90deg, #14b8a6, #22d3ee); }
    .seg--early   { background: linear-gradient(90deg, #6366f1, #8b5cf6); }
    .seg--growth  { background: linear-gradient(90deg, #f59e0b, #ef4444); }
    .seg--unknown { background: linear-gradient(90deg, #94a3b8, #cbd5e1); }
    .seg--cat     { background: linear-gradient(90deg, #0d9488, #4f46e5); }

    .members-wrap {
        background: #fff;
        border: 1px solid rgba(148, 163, 184, 0.3);
        border-radius: 14px;
        overflow: hidden;
    }
    .members-head {
        padding: 0.85rem 1rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: #f8fafc;
        border-bottom: 1px solid #e2e8f0;
    }
    .members-head h3 { margin: 0; font-size: 0.95rem; font-weight: 700; color: #0f172a; }
    .members-head .muted { color: #64748b; font-size: 0.82rem; }

    .member-search {
        position: relative;
        display: flex;
        align-items: center;
        flex: 0 1 22rem;
        min-width: 16rem;
    }
    .member-search__icon {
        position: absolute;
        left: 0.7rem;
        top: 50%;
        transform: translateY(-50%);
        color: #0d9488;
        pointer-events: none;
    }
    .member-search input[type="search"] {
        width: 100%;
        padding: 0.5rem 0.75rem 0.5rem 2.15rem;
        font-size: 0.88rem;
        color: #0f172a;
        background: #ffffff;
        border: 2px solid #14b8a6;
        border-radius: 10px;
        box-shadow: 0 6px 18px -10px rgba(20, 184, 166, 0.55), 0 0 0 4px rgba(20, 184, 166, 0.12);
        outline: none;
        transition: box-shadow 0.18s ease, border-color 0.18s ease;
    }
    .member-search input[type="search"]::placeholder { color: #94a3b8; }
    .member-search input[type="search"]:focus {
        border-color: #0d9488;
        box-shadow: 0 8px 22px -12px rgba(13, 148, 136, 0.6), 0 0 0 4px rgba(13, 148, 136, 0.2);
    }
    .m-table { width: 100%; border-collapse: collapse; }
    .m-table thead th {
        text-align: left; padding: 0.65rem 0.9rem;
        background: #ffffff; font-size: 0.72rem;
        letter-spacing: 0.08em; text-transform: uppercase;
        color: #475569; font-weight: 700;
        border-bottom: 1px solid #e2e8f0; white-space: nowrap;
    }
    .m-table tbody td { padding: 0.7rem 0.9rem; font-size: 0.875rem; border-bottom: 1px solid #f1f5f9; color: #0f172a; }
    .m-table tbody tr:hover { background: rgba(20, 184, 166, 0.05); }

    @media (max-width: 900px) {
        .batch-hero { grid-template-columns: 1fr; }
        .mix-grid { grid-template-columns: 1fr; }
    }
    @media (max-width: 620px) {
        .m-table thead { display: none; }
        .m-table, .m-table tbody, .m-table tr, .m-table td { display: block; width: 100%; }
        .m-table tr { padding: 0.55rem 0; border-bottom: 1px solid #e2e8f0; }
        .m-table td { padding: 0.2rem 0.9rem; border: none; }
        .m-table td::before {
            content: attr(data-label);
            display: inline-block; min-width: 110px;
            font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.08em;
            color: #64748b; font-weight: 700; margin-right: 0.5rem;
        }
    }
</style>
@endpush

@section('content')
@php
    $totalMembers = array_sum($stageMix);
    $pct = fn ($n) => $totalMembers > 0 ? round(($n / $totalMembers) * 100) : 0;
@endphp

<div class="batch-shell">
    <div class="batch-crumbs">
        <a href="{{ $routeIndex }}">← Back to batches</a>
    </div>

    <div class="batch-hero">
        <div class="batch-hero__meta">
            <p class="batch-hero__name">{{ $batch->name }}</p>
            <p class="batch-hero__sub">
                {{ $batch->hub?->name ?? '—' }} · {{ $batch->district?->name ?? '—' }}
            </p>
            <div class="batch-hero__chips">
                @if ($batch->status === 'locked')
                    <span class="chip chip--teal">Locked</span>
                @elseif ($batch->isDraft())
                    <span class="chip chip--amber">Draft</span>
                @else
                    <span class="chip chip--muted">{{ ucfirst($batch->status) }}</span>
                @endif

                @if ($hasCdoPdf)
                    <span class="chip chip--green">Onboarding Letter uploaded</span>
                @elseif ($cdoOverdue)
                    <span class="chip chip--rose">CDO overdue</span>
                @elseif ($cdoPending)
                    <span class="chip chip--indigo">CDO pending</span>
                @endif

                @if ($effectiveDeadline)
                    <span class="chip chip--muted">Deadline: {{ $effectiveDeadline->timezone(config('app.timezone'))->format('d M Y') }}</span>
                @endif
            </div>

            @if (auth()->user()->role === 'state_admin' && $batch->status === 'locked' && ! $hasCdoPdf)
                <form method="post" action="{{ route('admin.hub-batch-compliance.extend') }}" style="margin-top:0.65rem;display:flex;flex-wrap:wrap;gap:0.45rem;align-items:flex-end;">
                    @csrf
                    <input type="hidden" name="onboarding_batch_id" value="{{ $batch->id }}">
                    <label style="font-size:0.72rem;color:#475569;font-weight:700;">
                        Extend CDO deadline
                        <input type="date" name="extended_until" required
                            value="{{ optional($batch->pdf_deadline_extended_until ?? $effectiveDeadline)->format('Y-m-d') }}"
                            style="display:block;margin-top:0.22rem;padding:0.38rem 0.52rem;border:1px solid #cbd5e1;border-radius:8px;background:#fff;font:inherit;">
                    </label>
                    <button type="submit" style="padding:0.45rem 0.78rem;border-radius:10px;border:1px solid #cbd5e1;background:#fff;color:#334155;font-size:0.82rem;font-weight:700;cursor:pointer;">
                        Extend timeline
                    </button>
                </form>
            @endif
        </div>

        <div class="hero-stats">
            <div class="hero-stat">
                <div class="hero-stat__label">Members</div>
                <div class="hero-stat__value">{{ $totalMembers }} <span style="font-size:0.85rem; color:#64748b; font-weight:500;">/ {{ $batch->target_size }}</span></div>
            </div>
            <div class="hero-stat">
                <div class="hero-stat__label">Onboarding date</div>
                <div class="hero-stat__value" style="font-size:1rem;">
                    {{ optional($batch->onboarding_date)->format('d M Y') ?? '—' }}
                </div>
            </div>
            <div class="hero-stat">
                <div class="hero-stat__label">Locked at</div>
                <div class="hero-stat__value" style="font-size:1rem;">
                    {{ optional($batch->locked_at)->timezone(config('app.timezone'))->format('d M Y, H:i') ?? '—' }}
                </div>
            </div>
            <div class="hero-stat">
                <div class="hero-stat__label">Batch ID</div>
                <div class="hero-stat__value" style="font-size:1rem;">#{{ $batch->id }}</div>
            </div>
        </div>
    </div>

    {{-- Stage mix + Business category mix --}}
    <div class="mix-grid">
        <div class="mix-card">
            <h3>Stage mix</h3>
            @php $orderedStages = ['seed','early','growth','unknown']; @endphp
            @foreach ($orderedStages as $k)
                <div class="mix-row">
                    <div class="mix-row__label">{{ ucfirst($k) }}</div>
                    <div class="mix-row__bar">
                        <div class="mix-row__bar-fill seg--{{ $k }}" style="width: {{ $pct($stageMix[$k] ?? 0) }}%;"></div>
                    </div>
                    <div class="mix-row__val">{{ $stageMix[$k] ?? 0 }}</div>
                </div>
            @endforeach
        </div>

        <div class="mix-card">
            <h3>Business categories</h3>
            @if (empty($categoryMix))
                <p class="muted">No member data.</p>
            @else
                @php $topMax = max($categoryMix); @endphp
                @foreach (array_slice($categoryMix, 0, 8, true) as $cat => $count)
                    <div class="mix-row">
                        <div class="mix-row__label" style="min-width:0;">{{ $cat }}</div>
                        <div class="mix-row__bar">
                            <div class="mix-row__bar-fill seg--cat" style="width: {{ $topMax > 0 ? round(($count / $topMax) * 100) : 0 }}%;"></div>
                        </div>
                        <div class="mix-row__val">{{ $count }}</div>
                    </div>
                @endforeach
            @endif
        </div>
    </div>

    {{-- Members list --}}
    <div class="members-wrap">
        <div class="members-head" style="gap:0.75rem; flex-wrap:wrap;">
            <div class="member-search">
                <svg class="member-search__icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <circle cx="11" cy="11" r="7"></circle>
                    <line x1="20" y1="20" x2="16.65" y2="16.65"></line>
                </svg>
                <input
                    type="search"
                    id="memberSearch"
                    placeholder="Search members by name or phone…"
                    autocomplete="off"
                    aria-label="Search members by name or phone">
            </div>
            <div style="display:flex; align-items:center; gap:0.6rem; margin-left:auto;">
                <h3 style="margin:0;">Members</h3>
                <span class="muted" id="memberCount">{{ $totalMembers }} {{ Str::plural('member', $totalMembers) }}</span>
            </div>
        </div>
        @if (auth()->user()->role === 'district_staff' && ! ($serviceModuleOn ?? false))
            <p style="font-size:0.82rem; color:#92400e; background:#fffbeb; border:1px solid #fde68a; border-radius:8px; padding:0.5rem 0.75rem; margin:0 0 0.65rem;">
                Service module is off — <strong>Add intervention</strong> is hidden. Use <strong>View</strong> to check applicant details. Ask state admin to enable services under <strong>More → Service module settings</strong>.
            </p>
        @endif
        <table class="m-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Application no</th>
                    <th>Applicant</th>
                    <th>Phone</th>
                    <th>Stage</th>
                    <th>Business category</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($members as $i => $m)
                    <tr data-search="{{ Str::lower(($m['applicant_name'] ?? '').' '.($m['phone'] ?? '').' '.($m['application_no'] ?? '')) }}">
                        <td data-label="#">{{ $i + 1 }}</td>
                        <td data-label="Application no">{{ $m['application_no'] }}</td>
                        <td data-label="Applicant"><strong>{{ $m['applicant_name'] }}</strong></td>
                        <td data-label="Phone">{{ $m['phone'] ?: '—' }}</td>
                        <td data-label="Stage">
                            <span class="chip
                                @if($m['stage_key']==='seed') chip--teal
                                @elseif($m['stage_key']==='early') chip--indigo
                                @elseif($m['stage_key']==='growth') chip--amber
                                @else chip--muted
                                @endif
                            ">{{ $m['stage_label'] }}</span>
                        </td>
                        <td data-label="Business">{{ $m['business_category'] }}</td>
                        <td data-label="Actions" style="white-space:nowrap;">
                            @php $memberCfaId = (int) ($m['id'] ?? 0); @endphp
                            @if (auth()->user()->role === 'district_staff' && $memberCfaId > 0)
                                <a href="{{ route('staff.applications.show', $memberCfaId) }}" style="display:inline-block;padding:0.35rem 0.6rem;margin-right:0.35rem;border:1px solid #cbd5e1;background:#fff;color:#334155;border-radius:6px;font-size:0.78rem;font-weight:600;text-decoration:none;">View</a>
                                @if ($serviceModuleOn ?? false)
                                    <a href="{{ route('staff.services.create', ['cfa_submission_id' => $memberCfaId]) }}" style="display:inline-block;padding:0.35rem 0.6rem;background:#0f766e;color:#fff;border-radius:6px;font-size:0.78rem;font-weight:600;text-decoration:none;">Add intervention</a>
                                @endif
                            @elseif (auth()->user()->role === 'state_admin' && $memberCfaId > 0)
                                <a href="{{ route('admin.cfa.show', $memberCfaId) }}" style="font-size:0.82rem;color:#0d9488;font-weight:600;">View CFA</a>
                            @else
                                <span style="color:#a1a1aa;font-size:0.8rem;">—</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" style="padding:1.25rem; text-align:center; color:#64748b;">No members in this batch yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@push('scripts')
<script>
(function () {
    const input = document.getElementById('memberSearch');
    const countEl = document.getElementById('memberCount');
    const tbody = document.querySelector('.members-wrap .m-table tbody');
    if (!input || !tbody) return;

    const rows = Array.from(tbody.querySelectorAll('tr[data-search]'));
    const total = rows.length;
    const plural = (n) => n === 1 ? 'member' : 'members';

    let emptyRow = tbody.querySelector('tr.js-no-results');
    if (!emptyRow) {
        emptyRow = document.createElement('tr');
        emptyRow.className = 'js-no-results';
        emptyRow.style.display = 'none';
        emptyRow.innerHTML = '<td colspan="7" style="padding:1rem; text-align:center; color:#64748b;">No members match your search.</td>';
        tbody.appendChild(emptyRow);
    }

    function apply() {
        const q = input.value.trim().toLowerCase();
        let shown = 0;
        rows.forEach(r => {
            const hit = q === '' || (r.dataset.search || '').includes(q);
            r.style.display = hit ? '' : 'none';
            if (hit) shown++;
        });
        emptyRow.style.display = (shown === 0 && q !== '' && total > 0) ? '' : 'none';
        if (countEl) {
            countEl.textContent = q === ''
                ? total + ' ' + plural(total)
                : shown + ' of ' + total + ' ' + plural(total);
        }
    }

    input.addEventListener('input', apply);
})();
</script>
@endpush
@endsection
