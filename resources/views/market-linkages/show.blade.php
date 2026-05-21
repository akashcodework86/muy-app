@extends('layouts.admin')

@section('title', 'Market linkage details')
@section('heading', 'Market linkage details')

@section('content')
    <p style="margin:0 0 1rem;">
        <a href="{{ route($dashboardRoute) }}">← Market linkage records</a>
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
        </div>
    </div>

    <div style="background:#fff;border:1px solid #e4e4e7;border-radius:10px;overflow:auto;">
        <table style="width:100%;border-collapse:collapse;font-size:0.84rem;">
            <thead>
                <tr style="background:#f8fafc;">
                    <th style="text-align:left;padding:0.6rem 0.7rem;border-bottom:1px solid #e5e7eb;">#</th>
                    <th style="text-align:left;padding:0.6rem 0.7rem;border-bottom:1px solid #e5e7eb;">Partner name</th>
                    <th style="text-align:left;padding:0.6rem 0.7rem;border-bottom:1px solid #e5e7eb;">Mode</th>
                    <th style="text-align:left;padding:0.6rem 0.7rem;border-bottom:1px solid #e5e7eb;">Date</th>
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
