@extends('layouts.admin')

@section('title', 'CFA applications')
@section('heading', 'CFA applications')

@section('content')
    <p style="color:#64748b;font-size:0.9rem;margin:0 0 1rem;">Public form submissions (referral-linked). Newest first.</p>

    <div style="overflow-x:auto;">
        <table style="width:100%;border-collapse:collapse;background:#fff;border:1px solid #e4e4e7;border-radius:8px;font-size:0.875rem;">
            <thead>
                <tr style="text-align:left;background:#f8fafc;">
                    <th style="padding:0.55rem 0.65rem;border-bottom:1px solid #e4e4e7;">App. no.</th>
                    <th style="padding:0.55rem 0.65rem;border-bottom:1px solid #e4e4e7;">Date</th>
                    <th style="padding:0.55rem 0.65rem;border-bottom:1px solid #e4e4e7;">Applicant</th>
                    <th style="padding:0.55rem 0.65rem;border-bottom:1px solid #e4e4e7;">Phone</th>
                    <th style="padding:0.55rem 0.65rem;border-bottom:1px solid #e4e4e7;">District</th>
                    <th style="padding:0.55rem 0.65rem;border-bottom:1px solid #e4e4e7;">LGD (st / dist / blk)</th>
                    <th style="padding:0.55rem 0.65rem;border-bottom:1px solid #e4e4e7;">Referral staff</th>
                    <th style="padding:0.55rem 0.65rem;border-bottom:1px solid #e4e4e7;width:1%;white-space:nowrap;" class="no-print-col">View</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($submissions as $row)
                    <tr>
                        <td style="padding:0.45rem 0.65rem;border-bottom:1px solid #f4f4f5;font-weight:600;">{{ $row->application_no ?? '—' }}</td>
                        <td style="padding:0.45rem 0.65rem;border-bottom:1px solid #f4f4f5;color:#52525b;white-space:nowrap;">{{ $row->created_at?->format('Y-m-d H:i') }} IST</td>
                        <td style="padding:0.45rem 0.65rem;border-bottom:1px solid #f4f4f5;">{{ $row->applicant_name }}</td>
                        <td style="padding:0.45rem 0.65rem;border-bottom:1px solid #f4f4f5;color:#52525b;">{{ $row->phone }}</td>
                        <td style="padding:0.45rem 0.65rem;border-bottom:1px solid #f4f4f5;color:#52525b;">{{ $row->district?->name ?? '—' }}</td>
                        <td style="padding:0.45rem 0.65rem;border-bottom:1px solid #f4f4f5;color:#52525b;font-size:0.8rem;white-space:nowrap;" title="MoPR LGD snapshot at submit">{{ $row->lgd_state_code ?? '—' }} / {{ $row->lgd_district_code ?? '—' }} / {{ $row->lgd_block_code ?? '—' }}</td>
                        <td style="padding:0.45rem 0.65rem;border-bottom:1px solid #f4f4f5;color:#52525b;">{{ $row->referralUser?->name ?? '—' }}</td>
                        <td style="padding:0.45rem 0.65rem;border-bottom:1px solid #f4f4f5;" class="no-print-col">
                            <a href="{{ route('admin.cfa.show', $row) }}" style="display:inline-block;padding:0.35rem 0.65rem;background:#18181b;color:#fff;border-radius:6px;font-size:0.8rem;font-weight:600;text-decoration:none;">View</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" style="padding:1.25rem;color:#64748b;">No applications yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($submissions->hasPages())
        <div style="margin-top:1rem;">{{ $submissions->links() }}</div>
    @endif
@endsection
