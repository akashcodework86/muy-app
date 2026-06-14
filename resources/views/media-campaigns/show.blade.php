@extends('layouts.admin')

@section('title', 'Media campaign entry')
@section('heading', \App\Models\MediaCampaignEntry::MODULE_LABEL)

@push('styles')
@include('branding-communication.partials.form-styles')
@endpush

@section('content')
<div class="bc-shell">
    <div class="bc-card">
        <p><a href="{{ route($dashboardRoute) }}" class="bc-link">← Back to dashboard</a></p>
        <h3 class="bc-card__title">{{ $row->campaign_title }}</h3>
        <dl style="display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:0.75rem 1rem; margin:0;">
            <div><dt style="font-size:0.72rem;font-weight:700;color:#64748b;">Date</dt><dd>{{ $row->campaign_date?->format('d M Y') }}</dd></div>
            <div><dt style="font-size:0.72rem;font-weight:700;color:#64748b;">Media type</dt><dd>{{ \App\Support\BrandingCommunicationOptions::mediaTypeLabel((string) $row->media_type) }}</dd></div>
            <div><dt style="font-size:0.72rem;font-weight:700;color:#64748b;">Channel</dt><dd>{{ $row->channel_name }}</dd></div>
            <div><dt style="font-size:0.72rem;font-weight:700;color:#64748b;">Coverage</dt><dd>{{ $row->coverage_area }}</dd></div>
            @if ($row->ad_size_or_duration)
            <div><dt style="font-size:0.72rem;font-weight:700;color:#64748b;">Ad size / duration</dt><dd>{{ $row->ad_size_or_duration }}</dd></div>
            @endif
            <div><dt style="font-size:0.72rem;font-weight:700;color:#64748b;">Submitted by</dt><dd>{{ $row->submitted_by_name }}</dd></div>
            @if ($row->remarks)
            <div style="grid-column:1/-1;"><dt style="font-size:0.72rem;font-weight:700;color:#64748b;">Note</dt><dd>{{ $row->remarks }}</dd></div>
            @endif
        </dl>
        @if ($row->hasDocument())
            <p style="margin-top:1rem;"><a href="{{ route($documentRoute, $row) }}" class="bc-link">Download document</a></p>
        @endif
        @if ($row->attachments->isNotEmpty())
            <div style="margin-top:1rem;">
                <p style="font-size:0.82rem;font-weight:700;margin:0 0 0.5rem;">Multimedia proof</p>
                <div class="bc-media-preview">
                    @foreach ($row->attachments as $attachment)
                        @if ($attachment->attachment_type === 'image')
                            <a href="{{ route($attachmentRoute, $row) }}?attachment={{ $attachment->id }}&amp;inline=1" target="_blank" rel="noopener">
                                <img class="bc-media-thumb" src="{{ route($attachmentRoute, $row) }}?attachment={{ $attachment->id }}&amp;inline=1" alt="{{ $attachment->original_name }}" loading="lazy">
                            </a>
                        @else
                            <a href="{{ route($attachmentRoute, $row) }}?attachment={{ $attachment->id }}" class="bc-link" style="display:inline-flex;align-items:center;min-height:72px;padding:0 0.75rem;border:1px solid #e2e8f0;border-radius:8px;">
                                {{ ucfirst($attachment->attachment_type) }}: {{ $attachment->original_name ?: 'File #'.$attachment->id }}
                            </a>
                        @endif
                    @endforeach
                </div>
            </div>
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
