@extends('layouts.admin')

@section('title', 'Partner outreach detail')
@section('heading', \App\Models\BusinessAccelerationPartnerOutreachEntry::MODULE_LABEL)

@push('styles')
<style>
    .bapo-shell { display:flex; flex-direction:column; gap:1rem; max-width:52rem; }
    .bapo-card { background:#fff; border:1px solid #e2e8f0; border-radius:14px; padding:1.15rem 1.25rem; }
    .bapo-meta { display:grid; grid-template-columns:repeat(auto-fit, minmax(160px, 1fr)); gap:0.75rem; margin-bottom:1rem; font-size:0.88rem; }
    .bapo-meta dt { font-size:0.72rem; font-weight:700; text-transform:uppercase; color:#64748b; margin:0 0 0.2rem; }
    .bapo-meta dd { margin:0; font-weight:600; color:#0f172a; }
    .bapo-table { width:100%; border-collapse:collapse; font-size:0.85rem; }
    .bapo-table th, .bapo-table td { text-align:left; padding:0.55rem 0.5rem; border-bottom:1px solid #e2e8f0; }
    .bapo-table thead tr { background:#f8fafc; }
    .bapo-actions { display:flex; flex-wrap:wrap; gap:0.65rem; margin-top:0.75rem; }
    .bapo-link { color:#0f766e; font-weight:700; text-decoration:none; font-size:0.88rem; }
    .bapo-danger { background:#fef2f2; color:#b91c1c; border:1px solid #fecaca; padding:0.5rem 0.85rem; border-radius:8px; font-weight:700; cursor:pointer; font-size:0.85rem; }
</style>
@endpush

@section('content')
<div class="bapo-shell">
    <div class="bapo-card">
        <dl class="bapo-meta">
            <div>
                <dt>Outreach date</dt>
                <dd>{{ $header->outreach_date?->format('d M Y') ?? '—' }}</dd>
            </div>
            <div>
                <dt>Mode</dt>
                <dd>{{ \App\Support\BusinessAccelerationPartnersOutreachOptions::outreachModeLabel((string) $header->outreach_mode) }}</dd>
            </div>
            <div>
                <dt>Submitted by</dt>
                <dd>{{ $header->submitted_by_name }}</dd>
            </div>
            <div>
                <dt>Partners in batch</dt>
                <dd>{{ $batchRows->count() }}</dd>
            </div>
        </dl>

        <table class="bapo-table">
            <thead>
                <tr>
                    <th>Partner</th>
                    <th>Type</th>
                    <th>POC</th>
                    <th>Remarks</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($batchRows as $row)
                    <tr>
                        <td>{{ $row->partner_name }}</td>
                        <td>{{ \App\Support\BusinessAccelerationPartnersOutreachOptions::partnerTypeLabel((string) $row->partner_type, $row->partner_type_other) }}</td>
                        <td>
                            {{ $row->poc_name }}
                            @if ($row->poc_phone)
                                <br><span style="color:#64748b;font-size:0.78rem;">{{ $row->poc_phone }}</span>
                            @endif
                        </td>
                        <td>{{ $row->remarks ?: '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="bapo-actions">
            <a href="{{ route($dashboardRoute) }}" class="bapo-link">← Back to dashboard</a>
            @if ($canDelete && $destroyRoute)
                <form method="post" action="{{ route($destroyRoute, $header) }}" onsubmit="return confirm('Delete this entire outreach batch?');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="bapo-danger">Delete batch</button>
                </form>
            @endif
        </div>
    </div>
</div>
@endsection
