@extends('layouts.admin')

@section('title', 'Onboarded Applicants')
@section('heading', 'Onboarded Applicants')

@push('styles')
<style>
    .onb-shell { display: flex; flex-direction: column; gap: 1rem; }
    .onb-filters {
        display: flex;
        flex-wrap: wrap;
        gap: 0.6rem;
        align-items: flex-end;
        padding: 0.9rem 1rem;
        border-radius: 14px;
        background: #fff;
        border: 1px solid rgba(148, 163, 184, 0.3);
    }
    .onb-fld { display: flex; flex-direction: column; gap: 0.25rem; min-width: 180px; }
    .onb-fld--grow { flex: 1 1 260px; min-width: 220px; max-width: 480px; }
    .onb-fld label { font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.08em; color: #64748b; font-weight: 700; }
    .onb-fld select,
    .onb-fld input {
        padding: 0.45rem 0.6rem;
        border-radius: 10px;
        border: 1px solid #cbd5e1;
        font: inherit;
        background: #fff;
    }
    .onb-actions { display: flex; gap: 0.4rem; margin-left: auto; }
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
    .onb-table-wrap {
        background: #fff;
        border-radius: 14px;
        border: 1px solid rgba(148, 163, 184, 0.3);
        overflow: auto;
    }
    .onb-table { width: 100%; border-collapse: collapse; }
    .onb-table thead th {
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
    .onb-table tbody td {
        padding: 0.75rem 0.9rem;
        font-size: 0.86rem;
        border-bottom: 1px solid #f1f5f9;
        color: #0f172a;
        vertical-align: top;
    }
    .muted { color: #64748b; font-size: 0.79rem; }
    .empty-state {
        padding: 2rem 1rem;
        text-align: center;
        color: #64748b;
    }
    .pager { padding: 0.75rem 0.9rem; border-top: 1px solid #f1f5f9; background: #f8fafc; }
</style>
@endpush

@section('content')
<div class="onb-shell">
    <div class="onb-filters" style="align-items:center;">
        <div class="muted" style="font-size:0.9rem;">
            <strong>Total records:</strong> {{ number_format($rows->total()) }}
            @if ($rows->total() > 0)
                &nbsp;|&nbsp;
                <strong>Showing:</strong> {{ number_format($rows->firstItem() ?? 0) }} - {{ number_format($rows->lastItem() ?? 0) }}
            @endif
        </div>
    </div>

    <form method="get" action="{{ route('admin.onboarded.index') }}" class="onb-filters">
        <div class="onb-fld">
            <label for="fld-hub">Hub</label>
            <select id="fld-hub" name="hub" onchange="this.form.submit()">
                <option value="">All hubs</option>
                @foreach ($hubs as $hub)
                    <option value="{{ $hub->id }}" @selected((int) ($filters['hub'] ?? 0) === (int) $hub->id)>{{ $hub->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="onb-fld">
            <label for="fld-district">District</label>
            <select id="fld-district" name="district" onchange="this.form.submit()">
                <option value="">All districts</option>
                @foreach ($districts as $district)
                    <option value="{{ $district->id }}" @selected((int) ($filters['district'] ?? 0) === (int) $district->id)>{{ $district->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="onb-fld onb-fld--grow">
            <label for="fld-q">Search</label>
            <input
                id="fld-q"
                type="search"
                name="q"
                value="{{ $filters['q'] ?? '' }}"
                placeholder="Application no, applicant name, phone, CFA ID"
                autocomplete="off"
            >
        </div>
        <div class="onb-actions">
            @if (($filters['hub'] ?? null) || ($filters['district'] ?? null) || ($filters['q'] ?? null))
                <a href="{{ route('admin.onboarded.index') }}" class="btn-sm">Clear</a>
            @endif
            <a
                href="{{ route('admin.onboarded.export', array_filter(['hub' => $filters['hub'] ?? null, 'district' => $filters['district'] ?? null, 'q' => $filters['q'] ?? null])) }}"
                class="btn-sm"
            >Export Excel</a>
            <button type="submit" class="btn-sm btn-primary">Apply</button>
        </div>
    </form>

    <div class="onb-table-wrap">
        <table class="onb-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Application No</th>
                    <th>Source</th>
                    <th>District</th>
                    <th>Hub</th>
                    <th>Batch</th>
                    <th>Onboarded at</th>
                    @foreach (($commonColumns ?? []) as $columnKey => $column)
                        @if ($columnKey === 'application_no')
                            @continue
                        @endif
                        <th>{{ $column['label'] }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    <tr>
                        <td>
                            <strong>{{ (int) (($rows->firstItem() ?? 1) + $loop->index) }}</strong>
                        </td>
                        <td>
                            {{ $row['common_values']['application_no'] ?? $row['application_no'] ?? '—' }}
                        </td>
                        <td>
                            @if (($row['data_source'] ?? '') === 'legacy_phase2')
                                <span class="muted">Legacy Phase 2</span>
                            @else
                                <span class="muted">Phase 3</span>
                            @endif
                        </td>
                        <td>
                            <div>{{ $row['district'] ?: '—' }}</div>
                            <div class="muted">Block: {{ $row['block_name'] ?: '—' }}</div>
                        </td>
                        <td>{{ $row['hub_name'] ?: '—' }}</td>
                        <td>
                            <strong>{{ $row['onboarding_batch_name'] ?: '—' }}</strong>
                            <div class="muted">Batch #{{ $row['onboarding_batch_id'] ?: '—' }}</div>
                        </td>
                        <td>{{ !empty($row['onboarded_at']) ? \Illuminate\Support\Carbon::parse($row['onboarded_at'])->timezone(config('app.timezone'))->format('d M Y, H:i') : '—' }}</td>
                        @foreach (array_keys($commonColumns ?? []) as $columnKey)
                            @if ($columnKey === 'application_no')
                                @continue
                            @endif
                            <td>{{ $row['common_values'][$columnKey] ?? '—' }}</td>
                        @endforeach
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ 6 + count($commonColumns ?? []) }}" class="empty-state">No onboarded applicants found for the selected filters.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        @if ($rows->hasPages())
            <div class="pager">
                {{ $rows->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
