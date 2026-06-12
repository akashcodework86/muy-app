@extends('layouts.admin')

@section('title', 'Market linkage details')
@section('heading', 'Market linkage details')

@section('content')
    <p style="margin:0 0 1rem;">
        @if ($staffListRoute ?? null)
            <a href="{{ route($staffListRoute) }}">← My services</a>
            ·
        @endif
        <a href="{{ route($dashboardRoute) }}">Market linkage records</a>
        @if ($editRoute ?? null)
            · <a href="{{ route($editRoute, $submission) }}">Edit & resubmit</a>
        @endif
        @if ($canDelete ?? false)
            ·
            <form method="post" action="{{ route($deleteRoute ?? 'staff.market-linkages.destroy', $submission) }}" style="display:inline;" onsubmit="return confirm('Delete this market linkage submission?');">
                @csrf
                @method('DELETE')
                <button type="submit" style="background:none;border:none;padding:0;color:#b91c1c;font-weight:600;cursor:pointer;font-size:inherit;">Delete</button>
            </form>
        @endif
        @if ($createRoute)
            · <a href="{{ route($createRoute, array_filter([
                'cfa_submission_id' => $submission->cfa_submission_id,
                'legacy_application_id' => $submission->legacy_application_id,
            ])) }}">Add another for this incubatee</a>
        @endif
    </p>

    @if (session('status'))
        <p style="background:#f0fdf4;border:1px solid #86efac;color:#166534;padding:0.75rem 1rem;border-radius:8px;font-size:0.88rem;margin-bottom:1rem;">{{ session('status') }}</p>
    @endif

    <div style="background:#fff;border:1px solid #e4e4e7;border-radius:10px;padding:0.85rem 1rem;margin-bottom:1rem;">
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:0.65rem;font-size:0.88rem;">
            <div><span style="color:#64748b;">Incubatee</span><br><strong>{{ $submission->incubatee_name }}</strong></div>
            <div><span style="color:#64748b;">Application no</span><br><strong>{{ $submission->application_no ?: '—' }}</strong></div>
            <div><span style="color:#64748b;">District</span><br><strong>{{ $submission->district_name ?? $submission->district?->name ?? '—' }}</strong></div>
            <div><span style="color:#64748b;">Submitted by</span><br><strong>{{ $submission->submitted_by_name }}</strong></div>
            <div><span style="color:#64748b;">Recorded on</span><br><strong>{{ $submission->created_at?->timezone(config('app.timezone'))->format('d M Y H:i') ?? '—' }}</strong></div>
            @if (\App\Models\MarketLinkageSubmission::supportsWorkflow())
                <div><span style="color:#64748b;">Status</span><br><strong>{{ str_replace('_', ' ', (string) ($submission->status ?? 'approved')) }}</strong></div>
                <div><span style="color:#64748b;">Assigned SPOC</span><br><strong>{{ $submission->spoc?->name ?? 'Not assigned' }}</strong></div>
            @endif
        </div>
        @if (($submission->sent_back_note ?? '') !== '')
            <p style="margin:0.75rem 0 0;font-size:0.85rem;color:#9a3412;"><strong>SPOC note:</strong> {{ $submission->sent_back_note }}</p>
        @endif
        @if (($submission->rejected_note ?? '') !== '')
            <p style="margin:0.75rem 0 0;font-size:0.85rem;color:#991b1b;"><strong>Rejection reason:</strong> {{ $submission->rejected_note }}</p>
        @endif
    </div>

    <div style="background:#fff;border:1px solid #e4e4e7;border-radius:10px;overflow:auto;">
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
                                    <a href="{{ $partner->linkHref() }}" target="_blank" rel="noopener noreferrer" title="{{ $partner->link_url }}" style="display:inline-flex;align-items:center;gap:0.28rem;font-weight:700;color:#4f46e5;text-decoration:none;">
                                        <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
                                        <span>Link</span>
                                    </a>
                                @else
                                    <span title="{{ $partner->link_url }}" style="display:inline-flex;align-items:center;gap:0.28rem;color:#64748b;font-weight:600;">
                                        <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
                                        <span>Link</span>
                                    </span>
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
@endsection
