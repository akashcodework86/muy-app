@extends('layouts.admin')

@section('title', 'Hub batch CDO compliance')
@section('heading', 'Batch Onboarding Letter — extend / waive')

@section('content')
    <p style="font-size:0.9rem;color:#52525b;margin-top:-0.25rem;margin-bottom:1.25rem;max-width:42rem">
        Locked batches with <code>locked_at</code> set. Hub admins upload Onboarding Letter from <a href="{{ url('/hub/batches') }}">Hub → Batches</a>.
    </p>

    <div style="overflow-x:auto;">
        <table style="width:100%;border-collapse:collapse;background:#fff;border:1px solid #e4e4e7;border-radius:8px;font-size:0.875rem;">
            <thead>
                <tr style="background:#fafafa;text-align:left;">
                    <th style="padding:0.5rem 0.65rem;border-bottom:1px solid #e4e4e7;">Batch</th>
                    <th style="padding:0.5rem 0.65rem;border-bottom:1px solid #e4e4e7;">Hub / District</th>
                    <th style="padding:0.5rem 0.65rem;border-bottom:1px solid #e4e4e7;">Onboarding Letter</th>
                    <th style="padding:0.5rem 0.65rem;border-bottom:1px solid #e4e4e7;">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    @php($b = $row['batch'])
                    <tr>
                        <td style="padding:0.45rem 0.65rem;border-bottom:1px solid #f4f4f5;"><strong>{{ $b->name }}</strong> <span class="pill">#{{ $b->id }}</span></td>
                        <td style="padding:0.45rem 0.65rem;border-bottom:1px solid #f4f4f5;">{{ $b->hub?->name ?? '—' }} · {{ $b->district?->name ?? '—' }}</td>
                        <td style="padding:0.45rem 0.65rem;border-bottom:1px solid #f4f4f5;">
                            @if ($b->pdf_compliance_waived)
                                <span class="pill">Waived</span>
                            @elseif ($row['has_cdo'])
                                <span style="color:#059669;font-weight:600">Uploaded</span>
                            @elseif ($row['overdue'])
                                <span style="color:#dc2626;font-weight:700">Overdue</span>
                            @else
                                <span style="color:#d97706;font-weight:600">Pending</span>
                            @endif
                        </td>
                        <td style="padding:0.45rem 0.65rem;border-bottom:1px solid #f4f4f5;vertical-align:top;">
                            <form method="post" action="{{ route('admin.hub-batch-compliance.extend') }}" style="display:flex;flex-wrap:wrap;gap:0.5rem;align-items:flex-end">
                                @csrf
                                <input type="hidden" name="onboarding_batch_id" value="{{ $b->id }}">
                                <label style="font-size:0.75rem">New deadline (date)
                                    <input type="date" name="extended_until" required style="display:block;margin-top:0.2rem;padding:0.35rem;border:1px solid #e4e4e7;border-radius:6px;">
                                </label>
                                <button type="submit" style="background:#18181b;color:#fff;border:none;padding:0.4rem 0.75rem;border-radius:6px;font-size:0.8rem;cursor:pointer;">Extend</button>
                            </form>
                            @if (! $b->pdf_compliance_waived && ! $row['has_cdo'])
                                <form method="post" action="{{ route('admin.hub-batch-compliance.waive') }}" style="margin-top:0.5rem" onsubmit="return confirm('Waive CDO requirement for this batch?');">
                                    @csrf
                                    <input type="hidden" name="onboarding_batch_id" value="{{ $b->id }}">
                                    <button type="submit" style="background:none;border:none;color:#dc2626;font-size:0.75rem;cursor:pointer;font-weight:600;">Waive</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" style="padding:1.5rem;text-align:center;color:#71717a;">No locked batches with lock timestamp yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div style="margin-top:1.5rem;overflow-x:auto;">
        <h2 style="margin:0 0 0.55rem;font-size:1rem;">Pending locked-batch edit requests</h2>
        <table style="width:100%;border-collapse:collapse;background:#fff;border:1px solid #e4e4e7;border-radius:8px;font-size:0.875rem;">
            <thead>
                <tr style="background:#fafafa;text-align:left;">
                    <th style="padding:0.5rem 0.65rem;border-bottom:1px solid #e4e4e7;">Batch</th>
                    <th style="padding:0.5rem 0.65rem;border-bottom:1px solid #e4e4e7;">Hub / District</th>
                    <th style="padding:0.5rem 0.65rem;border-bottom:1px solid #e4e4e7;">Requested by</th>
                    <th style="padding:0.5rem 0.65rem;border-bottom:1px solid #e4e4e7;">Reason</th>
                    <th style="padding:0.5rem 0.65rem;border-bottom:1px solid #e4e4e7;">Expected changes</th>
                    <th style="padding:0.5rem 0.65rem;border-bottom:1px solid #e4e4e7;">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse (($pendingEditRequests ?? []) as $req)
                    <tr>
                        <td style="padding:0.45rem 0.65rem;border-bottom:1px solid #f4f4f5;"><strong>{{ $req->batch?->name ?? ('Batch #'.$req->onboarding_batch_id) }}</strong> <span class="pill">#{{ $req->onboarding_batch_id }}</span></td>
                        <td style="padding:0.45rem 0.65rem;border-bottom:1px solid #f4f4f5;">{{ $req->batch?->hub?->name ?? '—' }} · {{ $req->batch?->district?->name ?? '—' }}</td>
                        <td style="padding:0.45rem 0.65rem;border-bottom:1px solid #f4f4f5;">{{ $req->requester?->name ?? '—' }}</td>
                        <td style="padding:0.45rem 0.65rem;border-bottom:1px solid #f4f4f5;">{{ $req->reason }}</td>
                        <td style="padding:0.45rem 0.65rem;border-bottom:1px solid #f4f4f5;">{{ $req->expected_changes }}</td>
                        <td style="padding:0.45rem 0.65rem;border-bottom:1px solid #f4f4f5;">
                            <form method="post" action="{{ route('admin.hub-batch-compliance.approve-edit-request') }}" onsubmit="return confirm('Approve this edit request?');">
                                @csrf
                                <input type="hidden" name="request_id" value="{{ $req->id }}">
                                <button type="submit" style="background:#18181b;color:#fff;border:none;padding:0.38rem 0.65rem;border-radius:6px;font-size:0.78rem;cursor:pointer;">Approve unlock</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" style="padding:1.2rem;text-align:center;color:#71717a;">No pending edit requests.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div style="margin-top:2rem;padding:1.25rem;background:#fff;border:1px solid #e4e4e7;border-radius:8px;">
        <h2 style="margin:0 0 0.5rem;font-size:1rem;">Undo reject (by ID)</h2>
        <p style="font-size:0.85rem;color:#71717a;margin:0 0 1rem">Clears reject state for a CFA in a hub/district.</p>
        <form method="post" action="{{ route('admin.hub-batch-compliance.undo-reject') }}" style="display:grid;gap:0.75rem;max-width:28rem;">
            @csrf
            <label style="font-size:0.85rem">Hub ID <input type="number" name="hub_id" required style="width:100%;margin-top:0.25rem;padding:0.4rem;border:1px solid #e4e4e7;border-radius:6px;"></label>
            <label style="font-size:0.85rem">District ID <input type="number" name="district_id" required style="width:100%;margin-top:0.25rem;padding:0.4rem;border:1px solid #e4e4e7;border-radius:6px;"></label>
            <label style="font-size:0.85rem">CFA submission ID <input type="number" name="cfa_submission_id" required style="width:100%;margin-top:0.25rem;padding:0.4rem;border:1px solid #e4e4e7;border-radius:6px;"></label>
            <button type="submit" style="background:#18181b;color:#fff;border:none;padding:0.5rem 1rem;border-radius:6px;cursor:pointer;width:fit-content;">Undo reject</button>
        </form>
    </div>
@endsection
