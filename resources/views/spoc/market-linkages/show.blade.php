@extends('layouts.admin')

@section('title', 'Review market linkage')
@section('heading', 'Review market linkage')

@section('content')
    @php
        use App\Models\ServiceCase;
        $isPending = $submission->status === ServiceCase::STATUS_PENDING_APPROVAL;
    @endphp

    <p style="margin:0 0 1rem;">
        <a href="{{ route('spoc.service-cases.index', array_filter(['status' => $isPending ? ServiceCase::STATUS_PENDING_APPROVAL : null])) }}">← Back to approval queue</a>
    </p>

    @if (session('status'))
        <p style="background:#f0fdf4;border:1px solid #86efac;color:#166534;padding:0.75rem 1rem;border-radius:8px;font-size:0.88rem;margin-bottom:1rem;">{{ session('status') }}</p>
    @endif
    @if ($errors->any())
        <ul style="color:#b91c1c;margin:0 0 1rem;padding-left:1.2rem;font-size:0.88rem;">
            @foreach ($errors->all() as $e)
                <li>{{ $e }}</li>
            @endforeach
        </ul>
    @endif

    <div style="background:#fff;border:1px solid #e4e4e7;border-radius:10px;padding:0.85rem 1rem;margin-bottom:1rem;">
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:0.65rem;font-size:0.88rem;">
            <div><span style="color:#64748b;">Incubatee</span><br><strong>{{ $submission->incubatee_name }}</strong></div>
            <div><span style="color:#64748b;">Application no</span><br><strong>{{ $submission->application_no ?: '—' }}</strong></div>
            <div><span style="color:#64748b;">District</span><br><strong>{{ $submission->district_name ?? $submission->district?->name ?? '—' }}</strong></div>
            <div><span style="color:#64748b;">Submitted by</span><br><strong>{{ $submission->submitted_by_name }}</strong></div>
            <div><span style="color:#64748b;">Status</span><br><strong>{{ str_replace('_', ' ', (string) $submission->status) }}</strong></div>
            <div><span style="color:#64748b;">Submitted at</span><br><strong>{{ $submission->submitted_at?->timezone(config('app.timezone'))->format('d M Y H:i') ?? '—' }}</strong></div>
        </div>
        @if ($submission->status === ServiceCase::STATUS_SENT_BACK && $submission->sent_back_note)
            <p style="margin:0.75rem 0 0;font-size:0.85rem;color:#9a3412;"><strong>Sent back note:</strong> {{ $submission->sent_back_note }}</p>
        @endif
        @if ($submission->status === ServiceCase::STATUS_REJECTED && $submission->rejected_note)
            <p style="margin:0.75rem 0 0;font-size:0.85rem;color:#991b1b;"><strong>Rejection reason:</strong> {{ $submission->rejected_note }}</p>
        @endif
    </div>

    <div style="background:#fff;border:1px solid #e4e4e7;border-radius:10px;overflow:auto;margin-bottom:1rem;">
        <table style="width:100%;border-collapse:collapse;font-size:0.84rem;">
            <thead>
                <tr style="background:#f8fafc;">
                    <th style="text-align:left;padding:0.6rem 0.7rem;border-bottom:1px solid #e5e7eb;">#</th>
                    <th style="text-align:left;padding:0.6rem 0.7rem;border-bottom:1px solid #e5e7eb;">Partner name</th>
                    <th style="text-align:left;padding:0.6rem 0.7rem;border-bottom:1px solid #e5e7eb;">Mode</th>
                    <th style="text-align:left;padding:0.6rem 0.7rem;border-bottom:1px solid #e5e7eb;">Date</th>
                    <th style="text-align:left;padding:0.6rem 0.7rem;border-bottom:1px solid #e5e7eb;">Link / URL</th>
                    <th style="text-align:left;padding:0.6rem 0.7rem;border-bottom:1px solid #e5e7eb;">Document</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($submission->partners as $i => $partner)
                    <tr>
                        <td style="padding:0.6rem 0.7rem;border-bottom:1px solid #f1f5f9;">{{ $i + 1 }}</td>
                        <td style="padding:0.6rem 0.7rem;border-bottom:1px solid #f1f5f9;font-weight:600;">{{ $partner->partner_name }}</td>
                        <td style="padding:0.6rem 0.7rem;border-bottom:1px solid #f1f5f9;">{{ \App\Models\MarketLinkageSubmission::linkageModeLabel($partner->linkage_mode) }}</td>
                        <td style="padding:0.6rem 0.7rem;border-bottom:1px solid #f1f5f9;">{{ $partner->linkage_date?->format('d M Y') ?? '—' }}</td>
                        <td style="padding:0.6rem 0.7rem;border-bottom:1px solid #f1f5f9;max-width:14rem;">
                            @if ($partner->link_url)
                                @if ($partner->linkHref())
                                    <a href="{{ $partner->linkHref() }}" target="_blank" rel="noopener noreferrer" style="word-break:break-all;font-weight:600;">{{ $partner->link_url }}</a>
                                @else
                                    <span style="word-break:break-all;">{{ $partner->link_url }}</span>
                                @endif
                            @else
                                <span style="color:#94a3b8;">—</span>
                            @endif
                        </td>
                        <td style="padding:0.6rem 0.7rem;border-bottom:1px solid #f1f5f9;">
                            @if ($partner->hasDocument())
                                <a href="{{ route($documentRoutePrefix, [$submission, $partner]) }}">Download</a>
                            @else
                                <span style="color:#94a3b8;">—</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @if ($isPending)
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:0.75rem;max-width:52rem;">
            <form method="post" action="{{ route('spoc.market-linkages.approve', $submission) }}" onsubmit="return confirm('Approve this market linkage?');" style="background:#fff;border:1px solid #dcfce7;border-radius:10px;padding:0.8rem;">
                @csrf
                <button type="submit" style="background:#166534;color:#fff;border:none;padding:0.45rem 0.85rem;border-radius:8px;font-weight:600;cursor:pointer;">Approve</button>
                <p style="margin:0.5rem 0 0;font-size:0.78rem;color:#52525b;">Counts toward district deliverables after approval.</p>
            </form>

            <form method="post" action="{{ route('spoc.market-linkages.send-back', $submission) }}" style="background:#fff;border:1px solid #fed7aa;border-radius:10px;padding:0.8rem;">
                @csrf
                <label for="send_back_note" style="display:block;font-size:0.8rem;font-weight:600;margin-bottom:0.3rem;">Send back note</label>
                <textarea id="send_back_note" name="note" rows="3" required style="width:100%;padding:0.4rem 0.5rem;border:1px solid #d4d4d8;border-radius:6px;font-size:0.82rem;"></textarea>
                <button type="submit" style="margin-top:0.45rem;background:#9a3412;color:#fff;border:none;padding:0.45rem 0.85rem;border-radius:8px;font-weight:600;cursor:pointer;">Send back</button>
            </form>

            <form method="post" action="{{ route('spoc.market-linkages.reject', $submission) }}" style="background:#fff;border:1px solid #fecaca;border-radius:10px;padding:0.8rem;">
                @csrf
                <label for="reject_note" style="display:block;font-size:0.8rem;font-weight:600;margin-bottom:0.3rem;">Rejection reason</label>
                <textarea id="reject_note" name="note" rows="3" required style="width:100%;padding:0.4rem 0.5rem;border:1px solid #d4d4d8;border-radius:6px;font-size:0.82rem;"></textarea>
                <button type="submit" style="margin-top:0.45rem;background:#991b1b;color:#fff;border:none;padding:0.45rem 0.85rem;border-radius:8px;font-weight:600;cursor:pointer;">Reject</button>
            </form>
        </div>
    @endif
@endsection
