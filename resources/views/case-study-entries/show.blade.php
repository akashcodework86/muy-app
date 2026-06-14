@extends('layouts.admin')

@section('title', 'Case study entry')
@section('heading', \App\Models\CaseStudyEntry::MODULE_LABEL)

@push('styles')
@include('branding-communication.partials.form-styles')
@endpush

@section('content')
<div class="bc-shell">
    <div class="bc-card">
        <p><a href="{{ route($dashboardRoute) }}" class="bc-link">← Back to dashboard</a></p>
        <h3 class="bc-card__title">{{ $row->story_title }}</h3>
        <dl style="display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:0.75rem 1rem; margin:0;">
            <div><dt style="font-size:0.72rem;font-weight:700;color:#64748b;">Date</dt><dd>{{ $row->story_date?->format('d M Y') }}</dd></div>
            <div><dt style="font-size:0.72rem;font-weight:700;color:#64748b;">Type</dt><dd>{{ \App\Support\BrandingCommunicationOptions::storyTypeLabel((string) $row->story_type) }}</dd></div>
            <div><dt style="font-size:0.72rem;font-weight:700;color:#64748b;">Incubatee</dt><dd>{{ $row->incubatee_name }}@if($row->application_no) · {{ $row->application_no }}@endif</dd></div>
            <div><dt style="font-size:0.72rem;font-weight:700;color:#64748b;">Submitted by</dt><dd>{{ $row->submitted_by_name }}</dd></div>
            @if ($row->remarks)
            <div style="grid-column:1/-1;"><dt style="font-size:0.72rem;font-weight:700;color:#64748b;">Note</dt><dd>{{ $row->remarks }}</dd></div>
            @endif
        </dl>
        @if ($row->hasDocument())
            <p style="margin-top:1rem;"><a href="{{ route($documentRoute, $row) }}" class="bc-link">Download document</a></p>
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
