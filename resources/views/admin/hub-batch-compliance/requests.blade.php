@extends('layouts.admin')

@section('title', 'Batch unlock requests')
@section('heading', 'Batch unlock requests')

@section('content')
    <p style="font-size:0.9rem;color:#52525b;margin-top:-0.25rem;margin-bottom:1rem;max-width:46rem;">
        Hub admins raise unlock requests after batch lock. Review pending requests here and approve quickly.
    </p>

    <div style="background:#fff;border:1px solid #e4e4e7;border-radius:10px;padding:0.85rem 0.95rem;margin-bottom:1rem;">
        <form method="get" action="{{ route('admin.hub-batch-compliance.requests') }}" style="display:flex;gap:0.7rem;flex-wrap:wrap;align-items:flex-end;">
            <label style="font-size:0.8rem;color:#3f3f46;">
                Status
                <select name="status" style="display:block;margin-top:0.2rem;padding:0.4rem;border:1px solid #d4d4d8;border-radius:6px;min-width:130px;">
                    <option value="pending" @selected($status === 'pending')>Pending</option>
                    <option value="approved" @selected($status === 'approved')>Approved</option>
                    <option value="all" @selected($status === 'all')>All</option>
                </select>
            </label>
            <label style="font-size:0.8rem;color:#3f3f46;">
                Hub
                <select name="hub_id" style="display:block;margin-top:0.2rem;padding:0.4rem;border:1px solid #d4d4d8;border-radius:6px;min-width:170px;">
                    <option value="0">All hubs</option>
                    @foreach ($hubs as $hub)
                        <option value="{{ $hub->id }}" @selected($hubId === (int) $hub->id)>{{ $hub->name }}</option>
                    @endforeach
                </select>
            </label>
            <label style="font-size:0.8rem;color:#3f3f46;">
                District
                <select name="district_id" style="display:block;margin-top:0.2rem;padding:0.4rem;border:1px solid #d4d4d8;border-radius:6px;min-width:190px;">
                    <option value="0">All districts</option>
                    @foreach ($districts as $district)
                        <option value="{{ $district->id }}" @selected($districtId === (int) $district->id)>{{ $district->name }}</option>
                    @endforeach
                </select>
            </label>
            <button type="submit" style="background:#18181b;color:#fff;border:none;padding:0.46rem 0.85rem;border-radius:6px;font-size:0.8rem;cursor:pointer;">Apply</button>
            <a href="{{ route('admin.hub-batch-compliance.requests') }}" style="font-size:0.8rem;color:#4338ca;text-decoration:none;font-weight:600;">Reset</a>
        </form>
    </div>

    <div style="overflow-x:auto;">
        <table style="width:100%;border-collapse:collapse;background:#fff;border:1px solid #e4e4e7;border-radius:8px;font-size:0.875rem;">
            <thead>
                <tr style="background:#fafafa;text-align:left;">
                    <th style="padding:0.5rem 0.65rem;border-bottom:1px solid #e4e4e7;">Batch</th>
                    <th style="padding:0.5rem 0.65rem;border-bottom:1px solid #e4e4e7;">Hub / District</th>
                    <th style="padding:0.5rem 0.65rem;border-bottom:1px solid #e4e4e7;">Requested by</th>
                    <th style="padding:0.5rem 0.65rem;border-bottom:1px solid #e4e4e7;">Reason</th>
                    <th style="padding:0.5rem 0.65rem;border-bottom:1px solid #e4e4e7;">Expected changes</th>
                    <th style="padding:0.5rem 0.65rem;border-bottom:1px solid #e4e4e7;">Status</th>
                    <th style="padding:0.5rem 0.65rem;border-bottom:1px solid #e4e4e7;">Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($requests as $req)
                    @php
                        $isHighlighted = $highlightRequestId > 0 && $highlightRequestId === (int) $req->id;
                    @endphp
                    <tr style="{{ $isHighlighted ? 'background:#eff6ff;' : '' }}">
                        <td style="padding:0.45rem 0.65rem;border-bottom:1px solid #f4f4f5;">
                            <strong>{{ $req->batch?->name ?? ('Batch #'.$req->onboarding_batch_id) }}</strong>
                            <span class="pill">#{{ $req->onboarding_batch_id }}</span>
                        </td>
                        <td style="padding:0.45rem 0.65rem;border-bottom:1px solid #f4f4f5;">
                            {{ $req->batch?->hub?->name ?? '—' }} · {{ $req->batch?->district?->name ?? '—' }}
                        </td>
                        <td style="padding:0.45rem 0.65rem;border-bottom:1px solid #f4f4f5;">
                            {{ $req->requester?->name ?? '—' }}<br>
                            <span style="font-size:0.72rem;color:#71717a;">{{ optional($req->created_at)->diffForHumans() }}</span>
                        </td>
                        <td style="padding:0.45rem 0.65rem;border-bottom:1px solid #f4f4f5;">{{ $req->reason }}</td>
                        <td style="padding:0.45rem 0.65rem;border-bottom:1px solid #f4f4f5;">{{ $req->expected_changes }}</td>
                        <td style="padding:0.45rem 0.65rem;border-bottom:1px solid #f4f4f5;">
                            @if ($req->status === 'pending')
                                <span style="color:#b45309;font-weight:700;">Pending</span>
                            @elseif ($req->status === 'approved')
                                <span style="color:#15803d;font-weight:700;">Approved</span>
                                @if ($req->approver)
                                    <div style="font-size:0.72rem;color:#52525b;">by {{ $req->approver->name }}</div>
                                @endif
                                @if ($req->relocked_at)
                                    <div style="font-size:0.72rem;color:#52525b;">Relocked</div>
                                @endif
                            @else
                                <span>{{ ucfirst((string) $req->status) }}</span>
                            @endif
                        </td>
                        <td style="padding:0.45rem 0.65rem;border-bottom:1px solid #f4f4f5;">
                            @if ($req->status === 'pending')
                                <form method="post" action="{{ route('admin.hub-batch-compliance.approve-edit-request') }}" onsubmit="return confirm('Approve this unlock request?');">
                                    @csrf
                                    <input type="hidden" name="request_id" value="{{ $req->id }}">
                                    <button type="submit" style="background:#18181b;color:#fff;border:none;padding:0.38rem 0.65rem;border-radius:6px;font-size:0.78rem;cursor:pointer;">Approve unlock</button>
                                </form>
                            @else
                                <span style="font-size:0.76rem;color:#71717a;">No action</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" style="padding:1.2rem;text-align:center;color:#71717a;">No unlock requests found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if (method_exists($requests, 'links'))
        <div style="margin-top:0.85rem;">
            {{ $requests->links() }}
        </div>
    @endif
@endsection

