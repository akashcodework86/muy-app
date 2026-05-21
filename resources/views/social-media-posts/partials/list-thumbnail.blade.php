@php
    $platformLabel = $row->platform ?: 'Social post';
    if (! $row->platform && class_exists(\App\Services\SocialMediaPostPreviewService::class)) {
        try {
            $platformLabel = app(\App\Services\SocialMediaPostPreviewService::class)->platformLabel((string) $row->post_url);
        } catch (\Throwable) {
            $platformLabel = 'Social post';
        }
    }
@endphp
@if ($row->thumbnail_url)
    <a class="smp-list-thumb smp-list-thumb--image" href="{{ $row->post_url }}" target="_blank" rel="noopener noreferrer" title="{{ $row->preview_title ?: $platformLabel }}">
        <img src="{{ $row->thumbnail_url }}" alt="{{ $row->preview_title ?: 'Post thumbnail' }}" loading="lazy" referrerpolicy="no-referrer">
    </a>
@else
    <div class="smp-list-thumb" title="{{ $platformLabel }}">{{ $platformLabel }}</div>
@endif
