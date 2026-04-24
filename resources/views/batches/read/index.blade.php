@extends('layouts.admin')

@section('title', 'Batches')
@section('heading', 'Batches')

@push('styles')
<style>
    .batches-shell { display: flex; flex-direction: column; gap: 1rem; }
    .batches-totals {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
        gap: 0.75rem;
    }
    .batches-totals .card {
        padding: 0.9rem 1rem;
        border-radius: 14px;
        background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
        border: 1px solid rgba(148, 163, 184, 0.3);
        box-shadow: 0 8px 24px -14px rgba(15, 23, 42, 0.15);
    }
    .batches-totals .card__label {
        font-size: 0.72rem;
        text-transform: uppercase;
        letter-spacing: 0.1em;
        color: #64748b;
        font-weight: 700;
    }
    .batches-totals .card__value {
        font-size: 1.6rem;
        font-weight: 700;
        color: #0f172a;
        line-height: 1.1;
        margin-top: 0.25rem;
    }
    .batches-totals .card--accent {
        background: linear-gradient(135deg, rgba(20, 184, 166, 0.1), rgba(99, 102, 241, 0.08));
        border-color: rgba(20, 184, 166, 0.3);
    }

    .batches-filters {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
        align-items: flex-end;
        padding: 0.9rem 1rem;
        border-radius: 14px;
        background: #fff;
        border: 1px solid rgba(148, 163, 184, 0.3);
    }
    .batches-filters .fld { display: flex; flex-direction: column; gap: 0.25rem; min-width: 140px; }
    .batches-filters label { font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.08em; color: #64748b; font-weight: 700; }
    .batches-filters select, .batches-filters input {
        padding: 0.45rem 0.6rem;
        border-radius: 10px;
        border: 1px solid #cbd5e1;
        font: inherit;
        background: #fff;
    }
    .batches-filters .actions { display: flex; gap: 0.4rem; margin-left: auto; }
    .btn-sm {
        padding: 0.45rem 0.8rem;
        border-radius: 10px;
        font-size: 0.82rem;
        font-weight: 600;
        border: 1px solid #cbd5e1;
        background: #fff;
        color: #334155;
        text-decoration: none;
        cursor: pointer;
    }
    .btn-primary {
        background: linear-gradient(135deg, #0d9488, #4f46e5);
        color: #fff;
        border-color: transparent;
    }
    .btn-primary:hover { filter: brightness(1.05); }

    .batches-table-wrap {
        background: #fff;
        border-radius: 14px;
        border: 1px solid rgba(148, 163, 184, 0.3);
        overflow: hidden;
    }
    .batches-table { width: 100%; border-collapse: collapse; }
    .batches-table thead th {
        text-align: left;
        padding: 0.7rem 0.9rem;
        background: #f8fafc;
        font-size: 0.72rem;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        color: #475569;
        font-weight: 700;
        border-bottom: 1px solid #e2e8f0;
        white-space: nowrap;
    }
    .batches-table tbody td {
        padding: 0.75rem 0.9rem;
        font-size: 0.875rem;
        border-bottom: 1px solid #f1f5f9;
        color: #0f172a;
        vertical-align: middle;
    }
    .batches-table tbody tr:hover { background: rgba(20, 184, 166, 0.05); }
    .batches-table a { color: #0d9488; text-decoration: none; font-weight: 600; }
    .batches-table a:hover { text-decoration: underline; }
    .tag {
        display: inline-flex;
        align-items: center;
        gap: 0.3rem;
        padding: 0.15rem 0.55rem;
        border-radius: 999px;
        font-size: 0.72rem;
        font-weight: 700;
        letter-spacing: 0.04em;
    }
    .tag--draft   { background: rgba(245, 158, 11, 0.15); color: #92400e; border: 1px solid rgba(245, 158, 11, 0.35); }
    .tag--locked  { background: rgba(20, 184, 166, 0.15); color: #0f766e; border: 1px solid rgba(20, 184, 166, 0.35); }
    .tag--overdue { background: rgba(239, 68, 68, 0.14); color: #991b1b; border: 1px solid rgba(239, 68, 68, 0.35); }
    .tag--pending { background: rgba(99, 102, 241, 0.12); color: #3730a3; border: 1px solid rgba(99, 102, 241, 0.3); }
    .tag--ok      { background: rgba(16, 185, 129, 0.14); color: #065f46; border: 1px solid rgba(16, 185, 129, 0.35); }
    .tag--muted   { background: #f1f5f9; color: #64748b; border: 1px solid #e2e8f0; }

    .muted { color: #64748b; font-size: 0.82rem; }
    .empty-state {
        padding: 2rem 1rem;
        text-align: center;
        color: #64748b;
    }
    .pager { padding: 0.75rem 0.9rem; border-top: 1px solid #f1f5f9; background: #f8fafc; }

    @media (max-width: 720px) {
        .batches-table thead { display: none; }
        .batches-table, .batches-table tbody, .batches-table tr, .batches-table td { display: block; width: 100%; }
        .batches-table tr { border-bottom: 1px solid #e2e8f0; padding: 0.55rem 0; }
        .batches-table td { border: none; padding: 0.2rem 0.9rem; }
        .batches-table td::before {
            content: attr(data-label);
            display: inline-block;
            min-width: 110px;
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #64748b;
            font-weight: 700;
            margin-right: 0.5rem;
        }
    }
</style>
@endpush

@section('content')
<div class="batches-shell">

    {{-- Totals --}}
    <div class="batches-totals">
        <div class="card card--accent">
            <div class="card__label">Total batches</div>
            <div class="card__value">{{ number_format($totals['total']) }}</div>
        </div>
        <div class="card">
            <div class="card__label">Locked</div>
            <div class="card__value">{{ number_format($totals['locked']) }}</div>
        </div>
        <div class="card">
            <div class="card__label">Draft</div>
            <div class="card__value">{{ number_format($totals['draft']) }}</div>
        </div>
        @if ($scope['type'] === 'state')
            <div class="card">
                <div class="card__label">Scope</div>
                <div class="card__value" style="font-size:1.05rem; padding-top:0.35rem;">All hubs · all districts</div>
            </div>
        @elseif ($scope['type'] === 'district')
            <div class="card">
                <div class="card__label">Your district</div>
                <div class="card__value" style="font-size:1.05rem; padding-top:0.35rem;">
                    {{ optional(\App\Models\District::find($scope['district_id']))->name ?? '—' }}
                </div>
            </div>
        @endif
    </div>

    {{-- Filters --}}
    <form method="get" action="{{ $routeIndex }}" class="batches-filters">
        @if ($scope['type'] === 'state')
            <div class="fld">
                <label for="fld-hub">Hub</label>
                <select id="fld-hub" name="hub" onchange="this.form.submit()">
                    <option value="">All hubs</option>
                    @foreach ($hubs as $h)
                        <option value="{{ $h->id }}" @selected((int) ($filters['hub'] ?? 0) === (int) $h->id)>{{ $h->name }}</option>
                    @endforeach
                </select>
            </div>
        @endif
        @if ($scope['type'] !== 'district')
            <div class="fld">
                <label for="fld-district">District</label>
                <select id="fld-district" name="district" onchange="this.form.submit()">
                    <option value="">All districts</option>
                    @foreach ($districts as $d)
                        @if ($scope['type'] === 'state' && $filters['hub'] && (int) $d->hub_id !== (int) $filters['hub'])
                            @continue
                        @endif
                        <option value="{{ $d->id }}" @selected((int) ($filters['district'] ?? 0) === (int) $d->id)>{{ $d->name }}</option>
                    @endforeach
                </select>
            </div>
        @endif
        <div class="fld">
            <label for="fld-status">Status</label>
            <select id="fld-status" name="status" onchange="this.form.submit()">
                <option value="">All statuses</option>
                <option value="locked"  @selected(($filters['status'] ?? '') === 'locked')>Locked</option>
                <option value="draft"   @selected(($filters['status'] ?? '') === 'draft')>Draft</option>
            </select>
        </div>
        <div class="actions">
            @if (($filters['hub'] ?? null) || ($filters['district'] ?? null) || ($filters['status'] ?? null))
                <a href="{{ $routeIndex }}" class="btn-sm">Clear</a>
            @endif
            <button type="submit" class="btn-sm btn-primary">Apply</button>
        </div>
    </form>

    {{-- Table --}}
    <div class="batches-table-wrap">
        <table class="batches-table">
            <thead>
                <tr>
                    <th>Batch</th>
                    @if ($scope['type'] === 'state')
                        <th>Hub</th>
                    @endif
                    @if ($scope['type'] !== 'district')
                        <th>District</th>
                    @endif
                    <th>Status</th>
                    <th>Members</th>
                    <th>CDO PDF</th>
                    <th>Onboarding date</th>
                    <th>Locked at</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($batches as $b)
                    <tr>
                        <td data-label="Batch">
                            <a href="{{ route($routeShowName, $b->id) }}">{{ $b->name }}</a>
                            <div class="muted">#{{ $b->id }}</div>
                        </td>
                        @if ($scope['type'] === 'state')
                            <td data-label="Hub">{{ $b->hub?->name ?? '—' }}</td>
                        @endif
                        @if ($scope['type'] !== 'district')
                            <td data-label="District">{{ $b->district?->name ?? '—' }}</td>
                        @endif
                        <td data-label="Status">
                            @if ($b->status === 'locked')
                                <span class="tag tag--locked">Locked</span>
                            @elseif ($b->status === 'draft')
                                <span class="tag tag--draft">Draft</span>
                            @else
                                <span class="tag tag--muted">{{ ucfirst($b->status) }}</span>
                            @endif
                        </td>
                        <td data-label="Members">
                            <strong>{{ $b->member_count }}</strong>
                            <span class="muted">/ {{ $b->target_size }}</span>
                        </td>
                        <td data-label="CDO PDF">
                            @if ($b->has_cdo_pdf)
                                <span class="tag tag--ok">Uploaded</span>
                            @elseif ($b->cdo_overdue)
                                <span class="tag tag--overdue">Overdue</span>
                            @elseif ($b->cdo_pending)
                                <span class="tag tag--pending">Pending</span>
                            @else
                                <span class="tag tag--muted">—</span>
                            @endif
                        </td>
                        <td data-label="Onboarding">
                            {{ optional($b->onboarding_date)->format('d M Y') ?? '—' }}
                        </td>
                        <td data-label="Locked at">
                            {{ optional($b->locked_at)->timezone(config('app.timezone'))->format('d M Y, H:i') ?? '—' }}
                        </td>
                        <td data-label="Actions">
                            <div style="display:flex;flex-wrap:wrap;gap:0.35rem;align-items:center;">
                                <a href="{{ route($routeShowName, $b->id) }}" class="btn-sm">View</a>
                                @if (($scope['type'] ?? '') === 'state' && $b->status === 'locked' && ! $b->has_cdo_pdf)
                                    <form method="post" action="{{ route('admin.hub-batch-compliance.extend') }}" style="display:flex;gap:0.35rem;align-items:center;">
                                        @csrf
                                        <input type="hidden" name="onboarding_batch_id" value="{{ $b->id }}">
                                        <input type="date" name="extended_until" required
                                            value="{{ optional($b->pdf_deadline_extended_until ?? $b->locked_at?->copy()->addDays(5))->format('Y-m-d') }}"
                                            style="padding:0.3rem 0.4rem;border:1px solid #cbd5e1;border-radius:8px;font-size:0.78rem;">
                                        <button type="submit" class="btn-sm" style="padding:0.3rem 0.55rem;">Extend</button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="empty-state">
                            No batches to show.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        @if ($batches->hasPages())
            <div class="pager">
                {{ $batches->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
