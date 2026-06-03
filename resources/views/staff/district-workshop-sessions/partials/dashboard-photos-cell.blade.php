@php
    /** @var \App\Models\DistrictWorkshopSession $row */
    $attachmentRoute = (string) ($attachmentRouteName ?? 'staff.district-workshop-sessions.attachment');
    $photos = collect(is_array($row->workshop_photos_json ?? null) ? $row->workshop_photos_json : [])
        ->filter(fn ($p): bool => is_array($p) && (string) ($p['path'] ?? '') !== '')
        ->values();
    $maxVisible = 4;
    $visible = $photos->take($maxVisible);
    $extra = max(0, $photos->count() - $maxVisible);
@endphp

@if ($photos->isEmpty())
    <span class="ees-dash-photos-empty">—</span>
@else
    <div class="ees-dash-photos" role="group" aria-label="Workshop photos">
        @foreach ($visible as $idx => $photo)
            @php
                $photoName = (string) ($photo['original_name'] ?? ('Photo '.($idx + 1)));
                $viewQuery = array_filter([
                    'collection' => 'photos',
                    'index' => $idx > 0 ? $idx : null,
                    'inline' => 1,
                ]);
                $viewUrl = route($attachmentRoute, $row).'?'.http_build_query($viewQuery);
                $dlQuery = array_filter(['collection' => 'photos', 'index' => $idx > 0 ? $idx : null]);
                $downloadUrl = route($attachmentRoute, $row).'?'.http_build_query($dlQuery);
            @endphp
            <button
                type="button"
                class="js-tt-media-open"
                style="padding:0;border:none;background:none;line-height:0;"
                data-view-url="{{ $viewUrl }}"
                data-download-url="{{ $downloadUrl }}"
                data-media-kind="image"
                data-media-name="{{ $photoName }}"
                aria-label="View {{ $photoName }}"
            >
                <img class="ees-dash-photo" src="{{ $viewUrl }}" alt="{{ $photoName }}" loading="lazy">
            </button>
        @endforeach
        @if ($extra > 0)
            <span class="ees-dash-photo-more" title="{{ $photos->count() }} photos total">+{{ $extra }}</span>
        @endif
    </div>
@endif
