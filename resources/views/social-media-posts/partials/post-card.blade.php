@php
    $rowNumber = $rowNumber ?? $loop->iteration;
    $destroyRoute = auth()->user()->role === 'state_admin'
        ? 'admin.social-media-posts.destroy'
        : 'spoc.social-media-posts.destroy';
@endphp
<article class="smp-post-card">
    <a class="smp-post-card__media" href="{{ $row->post_url }}" target="_blank" rel="noopener noreferrer" title="Open post">
        @include('social-media-posts.partials.list-thumbnail', ['row' => $row, 'size' => 'card', 'linkWrap' => false])
        <span class="smp-post-card__index">#{{ $rowNumber }}</span>
    </a>
    <div class="smp-post-card__body">
        <div class="smp-post-card__head">
            <time datetime="{{ $row->posted_on?->format('Y-m-d') }}">{{ $row->posted_on?->format('d M Y') }}</time>
            @if (!empty($isAdminView))
                <span class="smp-post-card__by">{{ $row->submitted_by_name }}</span>
            @endif
        </div>
        @if ($row->preview_title)
            <h4 class="smp-post-card__title">{{ $row->preview_title }}</h4>
        @endif
        @if ($row->description)
            <p class="smp-post-card__desc">{{ $row->description }}</p>
        @endif
        <div class="smp-post-card__platforms">
            @include('social-media-posts.partials.platform-badges', ['row' => $row])
        </div>
        <div class="smp-post-card__actions">
            <a href="{{ route($showRoute, $row) }}" class="smp-post-card__btn smp-post-card__btn--view">View</a>
            <a href="{{ $row->post_url }}" class="smp-post-card__btn smp-post-card__btn--link" target="_blank" rel="noopener noreferrer">Open</a>
            @if (\App\Support\SocialMediaPostAccess::canDelete(auth()->user(), $row))
                <form
                    class="smp-delete-inline"
                    method="post"
                    action="{{ route($destroyRoute, $row) }}"
                    onsubmit="return confirm('Delete this social media post permanently?');"
                >
                    @csrf
                    @method('delete')
                    <button type="submit" class="smp-post-card__btn smp-post-card__btn--delete">Delete</button>
                </form>
            @endif
        </div>
    </div>
</article>
