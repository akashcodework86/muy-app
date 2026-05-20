@php
    $mode = $preview['mode'] ?? 'empty';
    $platform = $preview['platform'] ?? '';
    $url = $preview['url'] ?? '';
    $iframeSrc = $preview['iframe_src'] ?? null;
    $thumbnail = $preview['thumbnail_url'] ?? null;
    $title = $preview['title'] ?? null;
    $author = $preview['author'] ?? null;
    $message = $preview['message'] ?? '';
    $compact = !empty($compact);
@endphp

<div class="smp-preview-panel @if($compact) smp-preview-panel--compact @endif" data-mode="{{ $mode }}">
    @if ($mode === 'iframe' && $iframeSrc)
        <iframe class="smp-preview-panel__iframe" src="{{ $iframeSrc }}" title="Post preview" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
    @elseif ($mode === 'thumbnail' && $thumbnail)
        <a class="smp-preview-panel__thumb-link" href="{{ $url }}" target="_blank" rel="noopener noreferrer">
            <img class="smp-preview-panel__thumb" src="{{ $thumbnail }}" alt="{{ $title ?: 'Post preview' }}" loading="lazy" referrerpolicy="no-referrer">
        </a>
        <div class="smp-preview-panel__meta">
            @if ($platform)
                <span class="smp-preview-panel__platform">{{ $platform }}</span>
            @endif
            @if ($title)
                <p class="smp-preview-panel__title">{{ $title }}</p>
            @endif
            @if ($author)
                <p class="smp-preview-panel__author">{{ $author }}</p>
            @endif
        </div>
    @elseif ($mode === 'card' && $url)
        <div class="smp-preview-panel__card">
            <span class="smp-preview-panel__platform smp-preview-panel__platform--lg">{{ $platform ?: 'Social post' }}</span>
            <p class="smp-preview-panel__card-url">{{ $url }}</p>
        </div>
    @else
        <div class="smp-preview-panel__empty">
            <span>{{ $message ?: 'Enter a valid URL to preview the post here.' }}</span>
        </div>
    @endif

    @if ($url && $mode !== 'empty')
        <div class="smp-preview-panel__footer">
            @if ($message && $mode !== 'iframe')
                <p class="smp-preview-panel__hint">{{ $message }}</p>
            @endif
            <a class="smp-preview-panel__open" href="{{ $url }}" target="_blank" rel="noopener noreferrer">Open post in new tab</a>
        </div>
    @endif
</div>
