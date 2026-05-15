@php
    /** @var \App\Models\EapEdpSession $row */
    $photoRoute = (string) ($photoRouteName ?? 'staff.eap-edp-sessions.photo');
    $photos = collect(is_array($row->session_photos_json ?? null) ? $row->session_photos_json : [])
        ->filter(fn ($p): bool => is_array($p) && (string) ($p['path'] ?? '') !== '')
        ->values();
    $maxVisible = 4;
    $visible = $photos->take($maxVisible);
    $extra = max(0, $photos->count() - $maxVisible);
@endphp

@if ($photos->isEmpty())
    <span class="ees-dash-photos-empty">—</span>
@else
    <div class="ees-dash-photos" role="group" aria-label="Session photos">
        @foreach ($visible as $idx => $photo)
            @php
                $photoName = (string) ($photo['original_name'] ?? ('Photo '.($idx + 1)));
                $viewQuery = array_filter(['index' => $idx > 0 ? $idx : null, 'inline' => 1]);
                $viewUrl = route($photoRoute, $row).'?'.http_build_query($viewQuery);
                $dlQuery = $idx > 0 ? ['index' => $idx] : [];
                $downloadUrl = route($photoRoute, $row).($dlQuery !== [] ? '?'.http_build_query($dlQuery) : '');
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
