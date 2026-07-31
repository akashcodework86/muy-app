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

        @php
            $attachments = $row->relationLoaded('attachments') ? $row->attachments : collect();
        @endphp

        @if ($attachments->isNotEmpty())
            <div style="margin-top:1rem;">
                <p style="font-size:0.82rem;font-weight:700;margin:0 0 0.5rem;">Documents &amp; images</p>
                <div class="bc-media-preview">
                    @foreach ($attachments as $attachment)
                        @if ($attachment->isImage())
                            <a href="{{ route($attachmentRoute, $row) }}?attachment={{ $attachment->id }}&amp;inline=1" target="_blank" rel="noopener" title="{{ $attachment->original_name }}">
                                <img class="bc-media-thumb" src="{{ route($attachmentRoute, $row) }}?attachment={{ $attachment->id }}&amp;inline=1" alt="{{ $attachment->original_name }}" loading="lazy">
                            </a>
                        @else
                            <a href="{{ route($attachmentRoute, $row) }}?attachment={{ $attachment->id }}" class="bc-link" style="display:inline-flex;align-items:center;min-height:72px;padding:0 0.75rem;border:1px solid #e2e8f0;border-radius:8px;">
                                {{ $attachment->original_name ?: 'Document #'.$attachment->id }}
                            </a>
                        @endif
                    @endforeach
                </div>
            </div>
        @elseif ($row->hasDocument())
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
