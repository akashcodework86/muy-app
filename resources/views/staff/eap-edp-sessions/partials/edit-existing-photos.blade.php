@php
    $photoItems = is_array($photoItems ?? null) ? $photoItems : [];
    $photoRoute = (string) ($photoRoute ?? 'staff.eap-edp-sessions.photo');
    $photoUrl = static function (int $index, bool $inline = true) use ($photoRoute, $record): string {
        $query = array_filter([
            'index' => $index > 0 ? $index : null,
            'inline' => $inline ? 1 : null,
        ]);

        return route($photoRoute, $record).($query !== [] ? '?'.http_build_query($query) : '');
    };
    $removedIndices = array_map('strval', (array) old('remove_photo_indices', []));
@endphp

@if ($photoItems === [])
    <p class="tp-field-hint" style="margin-top:0.35rem;">No session photos yet.</p>
@else
    <p class="tp-field-hint" style="margin:0.35rem 0 0.5rem;">Click × to remove a photo. Changes apply when you save.</p>
    <div class="ees-photo-existing-grid" id="eesExistingPhotosGrid">
        @foreach ($photoItems as $idx => $photo)
            @if (! is_array($photo) || (string) ($photo['path'] ?? '') === '')
                @continue
            @endif
            @php
                $photoName = (string) ($photo['original_name'] ?? ('Photo '.($idx + 1)));
                $viewUrl = $photoUrl((int) $idx, true);
                $isMarked = in_array((string) $idx, $removedIndices, true);
            @endphp
            <div class="ees-photo-existing-item {{ $isMarked ? 'is-marked-remove' : '' }}" data-photo-index="{{ $idx }}">
                <input
                    type="checkbox"
                    name="remove_photo_indices[]"
                    value="{{ $idx }}"
                    class="ees-photo-remove-cb"
                    hidden
                    @checked($isMarked)
                >
                <button
                    type="button"
                    class="ees-photo-existing-remove js-ees-photo-toggle-remove"
                    aria-label="Remove {{ $photoName }}"
                    title="Remove photo"
                >×</button>
                <button
                    type="button"
                    class="js-tt-media-open"
                    style="padding:0;border:none;background:none;"
                    data-view-url="{{ $viewUrl }}"
                    data-download-url="{{ $photoUrl((int) $idx, false) }}"
                    data-media-kind="image"
                    data-media-name="{{ $photoName }}"
                    aria-label="Preview {{ $photoName }}"
                >
                    <img class="ees-photo-existing-thumb" src="{{ $viewUrl }}" alt="{{ $photoName }}" loading="lazy">
                </button>
            </div>
        @endforeach
    </div>
@endif

@once
    @push('scripts')
    <script>
    (function () {
        document.querySelectorAll('.js-ees-photo-toggle-remove').forEach(function (btn) {
            btn.addEventListener('click', function () {
                const item = btn.closest('.ees-photo-existing-item');
                if (!item) return;
                const cb = item.querySelector('.ees-photo-remove-cb');
                if (!cb) return;
                cb.checked = !cb.checked;
                item.classList.toggle('is-marked-remove', cb.checked);
            });
        });
    }());
    </script>
    @endpush
@endonce
