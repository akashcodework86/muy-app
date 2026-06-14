@extends('layouts.admin')

@section('title', 'Newsletter entry')
@section('heading', \App\Models\MuyNewsletterEntry::MODULE_LABEL)

@push('styles')
@include('branding-communication.partials.form-styles')
@endpush

@section('content')
<div class="bc-shell">
    <div class="bc-card">
        <p><a href="{{ route($dashboardRoute) }}" class="bc-link">← Back to dashboard</a></p>
        <h3 class="bc-card__title">{{ $row->title }}</h3>
        <dl style="display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:0.75rem 1rem; margin:0;">
            <div><dt style="font-size:0.72rem;font-weight:700;color:#64748b;">Issue date</dt><dd>{{ $row->issue_date?->format('d M Y') }}</dd></div>
            <div><dt style="font-size:0.72rem;font-weight:700;color:#64748b;">Edition</dt><dd>{{ $row->issue_edition }}</dd></div>
            <div><dt style="font-size:0.72rem;font-weight:700;color:#64748b;">Distribution</dt><dd>{{ \App\Support\BrandingCommunicationOptions::distributionModeLabel((string) $row->distribution_mode) }}</dd></div>
            <div><dt style="font-size:0.72rem;font-weight:700;color:#64748b;">Submitted by</dt><dd>{{ $row->submitted_by_name }}</dd></div>
            @if ($row->newsletter_url)
            <div style="grid-column:1/-1;"><dt style="font-size:0.72rem;font-weight:700;color:#64748b;">Link</dt><dd><a href="{{ $row->newsletter_url }}" target="_blank" rel="noopener" class="bc-link">{{ $row->newsletter_url }}</a></dd></div>
            @endif
            @if ($row->remarks)
            <div style="grid-column:1/-1;"><dt style="font-size:0.72rem;font-weight:700;color:#64748b;">Note</dt><dd>{{ $row->remarks }}</dd></div>
            @endif
        </dl>
        @if ($row->hasDocument())
            <p style="margin-top:1rem;"><a href="{{ route($documentRoute, $row) }}" class="bc-link">Download PDF</a></p>
        @endif
        @if ($canDelete && $destroyRoute)
            <form method="post" action="{{ route($destroyRoute, $row) }}" style="margin-top:1rem;" onsubmit="return confirm('Delete this entry?');">
                @csrf @method('DELETE')
                <button type="submit" class="bc-btn" style="background:#b91c1c;">Delete entry</button>
            </form>
        @endif
    </div>
</div>
@endsection
