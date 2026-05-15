@php
    $mediaItems = is_array($mediaItems ?? null) ? $mediaItems : [];
    $attachmentRoute = (string) ($attachmentRoute ?? 'staff.eap-edp-sessions.attachment');
    $attachmentUrl = static function (int $index, bool $inline = false) use ($attachmentRoute, $record): string {
        $query = array_filter([
            'index' => $index > 0 ? $index : null,
            'inline' => $inline ? 1 : null,
        ]);

        return route($attachmentRoute, $record).($query !== [] ? '?'.http_build_query($query) : '');
    };
    $mediaKind = static function (string $mime, string $name): string {
        if (str_starts_with($mime, 'image/')) {
            return 'image';
        }
        if (str_starts_with($mime, 'video/')) {
            return 'video';
        }
        if ($mime === 'application/pdf' || str_ends_with(strtolower($name), '.pdf')) {
            return 'pdf';
        }

        return 'file';
    };
    $mediaLabel = static function (string $kind, string $name): string {
        if ($kind === 'pdf') {
            return 'PDF';
        }
        if ($kind === 'video') {
            return 'Video';
        }
        if ($kind === 'file') {
            $extension = pathinfo($name, PATHINFO_EXTENSION);

            return $extension !== '' ? strtoupper($extension) : 'File';
        }

        return 'Photo';
    };
@endphp

@if ($mediaItems === [])
    <p class="ees-edit-media-empty">No files on record — upload at least one attendance sheet before saving.</p>
@else
    <p class="ees-edit-media-hint">Check files to remove, or add new uploads below. At least one sheet must remain on file.</p>
    <div class="ees-edit-media-grid" id="eesExistingMediaGrid">
        @foreach ($mediaItems as $idx => $media)
            @if (! is_array($media))
                @continue
            @endif
            @php
                $mediaPath = (string) ($media['path'] ?? '');
                $mediaMime = (string) ($media['mime'] ?? '');
                $mediaName = (string) ($media['original_name'] ?? ('Media '.($idx + 1)));
                $kind = $mediaKind($mediaMime, $mediaName);
                $viewUrl = $attachmentUrl((int) $idx, true);
                $downloadUrl = $attachmentUrl((int) $idx);
            @endphp
            @if ($mediaPath !== '')
                <div class="ees-edit-media-item" data-media-index="{{ $idx }}">
                    <label class="ees-edit-media-item__remove">
                        <input
                            type="checkbox"
                            name="remove_media_indices[]"
                            value="{{ $idx }}"
                            class="ees-edit-media-remove-cb"
                            @checked(in_array((string) $idx, array_map('strval', (array) old('remove_media_indices', [])), true))
                        >
                        <span>Remove</span>
                    </label>
                    <button
                        type="button"
                        class="tt-media-tile js-tt-media-open ees-edit-media-item__preview"
                        data-view-url="{{ $viewUrl }}"
                        data-download-url="{{ $downloadUrl }}"
                        data-media-kind="{{ $kind }}"
                        data-media-name="{{ $mediaName }}"
                        aria-label="Preview {{ $mediaName }}"
                    >
                        @if ($kind === 'image')
                            <img class="tt-media-tile__thumb" src="{{ $viewUrl }}" alt="{{ $mediaName }}" loading="lazy">
                        @elseif ($kind === 'video')
                            <span class="tt-media-tile__video">
                                <video class="tt-media-tile__video-el" muted playsinline preload="metadata" aria-hidden="true">
                                    <source src="{{ $viewUrl }}" type="{{ $mediaMime !== '' ? $mediaMime : 'video/mp4' }}">
                                </video>
                                <span class="tt-media-tile__play" aria-hidden="true">▶</span>
                            </span>
                        @else
                            <span class="tt-media-tile__doc">
                                <span class="tt-media-tile__doc-badge">{{ $mediaLabel($kind, $mediaName) }}</span>
                            </span>
                        @endif
                        <span class="tt-media-tile__name">{{ $mediaName }}</span>
                    </button>
                </div>
            @endif
        @endforeach
    </div>
@endif

@once
    @push('styles')
    <style>
        .ees-edit-media-empty { margin:0.35rem 0 0; color:#b45309; font-size:0.82rem; font-weight:600; }
        .ees-edit-media-hint { margin:0 0 0.65rem; color:#64748b; font-size:0.78rem; line-height:1.4; }
        .ees-edit-media-grid { display:flex; flex-wrap:wrap; gap:0.75rem; }
        .ees-edit-media-item {
            position:relative;
            width:132px;
            display:flex;
            flex-direction:column;
            gap:0.35rem;
        }
        .ees-edit-media-item.is-marked-remove .ees-edit-media-item__preview {
            opacity:0.45;
            filter:grayscale(0.6);
        }
        .ees-edit-media-item__remove {
            display:inline-flex;
            align-items:center;
            gap:0.35rem;
            font-size:0.74rem;
            font-weight:700;
            color:#b91c1c;
            cursor:pointer;
            user-select:none;
        }
        .ees-edit-media-item__remove input { accent-color:#dc2626; }
        .ees-edit-media-item__preview {
            width:100%;
            border:1px solid #e2e8f0;
            border-radius:12px;
            background:#fff;
            padding:0.45rem;
            cursor:pointer;
            text-align:left;
            box-shadow:0 4px 14px rgba(15,23,42,0.05);
        }
    </style>
    @endpush
    @push('scripts')
    <script>
    (function () {
        document.querySelectorAll('.ees-edit-media-remove-cb').forEach(function (cb) {
            const item = cb.closest('.ees-edit-media-item');
            const sync = function () {
                if (item) {
                    item.classList.toggle('is-marked-remove', cb.checked);
                }
            };
            cb.addEventListener('change', sync);
            sync();
        });
    }());
    </script>
    @endpush
@endonce
