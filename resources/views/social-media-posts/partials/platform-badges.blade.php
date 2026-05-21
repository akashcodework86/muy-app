@php
    use App\Support\SocialMediaPostPlatforms;

    $slugs = is_array($row->posted_platforms ?? null) ? $row->posted_platforms : [];
    if ($slugs === [] && ! empty($row->platform)) {
        $fallback = SocialMediaPostPlatforms::slugFromPreviewPlatform((string) $row->platform);
        if ($fallback) {
            $slugs = [$fallback];
        }
    }
    $labels = SocialMediaPostPlatforms::labels($slugs);
@endphp

@if ($labels === [])
    <span class="smp-platforms smp-platforms--empty">—</span>
@else
    <div class="smp-platforms">
        @foreach ($labels as $label)
            <span class="smp-platforms__chip">{{ $label }}</span>
        @endforeach
    </div>
@endif
