@php
    use App\Support\SocialMediaPostThumbnail;

    $platformLabel = $row->platform ?: 'Social post';
    if (! $row->platform && class_exists(\App\Services\SocialMediaPostPreviewService::class)) {
        try {
            $platformLabel = app(\App\Services\SocialMediaPostPreviewService::class)->platformLabel((string) $row->post_url);
        } catch (\Throwable) {
            $platformLabel = 'Social post';
        }
    }
    $displayThumb = SocialMediaPostThumbnail::displayUrl($row->thumbnail_url);
    $size = $size ?? 'sm';
    $linkWrap = $linkWrap ?? true;
@endphp
@if ($displayThumb)
    @if ($linkWrap)
        <a class="smp-list-thumb smp-list-thumb--image smp-list-thumb--{{ $size }}" href="{{ $row->post_url }}" target="_blank" rel="noopener noreferrer" title="{{ $row->preview_title ?: $platformLabel }}">
            <img src="{{ $displayThumb }}" alt="{{ $row->preview_title ?: 'Post thumbnail' }}" loading="lazy">
        </a>
    @else
        <div class="smp-list-thumb smp-list-thumb--image smp-list-thumb--{{ $size }}">
            <img src="{{ $displayThumb }}" alt="{{ $row->preview_title ?: 'Post thumbnail' }}" loading="lazy">
        </div>
    @endif
@else
    <div class="smp-list-thumb smp-list-thumb--{{ $size }}" title="{{ $platformLabel }}">{{ $platformLabel }}</div>
@endif
