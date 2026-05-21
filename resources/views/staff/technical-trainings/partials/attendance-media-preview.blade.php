@php
    $mediaItems = is_array($mediaItems ?? null) ? $mediaItems : [];
    $attachmentRoute = (string) ($attachmentRoute ?? 'staff.technical-trainings.attachment');
    $extraQuery = (array) ($attachmentQuery ?? []);
    $attachmentUrl = static function (int $index, bool $inline = false) use ($attachmentRoute, $record, $extraQuery): string {
        $query = array_filter(array_merge($extraQuery, [
            'index' => $index > 0 ? $index : null,
            'inline' => $inline ? 1 : null,
        ]));

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
    @if ($showEmptyMessage ?? true)
        <p class="tt-media-empty">No media uploaded.</p>
    @endif
@else
    <div class="tt-media-grid">
        @foreach ($mediaItems as $idx => $media)
            @if (! is_array($media))
                @continue
            @endif
            @php
                $mediaPath = (string) ($media['path'] ?? '');
                $mediaMime = (string) ($media['mime'] ?? '');
                $mediaName = (string) ($media['original_name'] ?? ('Media '.($idx + 1)));
                $kind = $mediaKind($mediaMime, $mediaName);
                $viewUrl = $attachmentUrl($idx, true);
                $downloadUrl = $attachmentUrl($idx);
            @endphp
            @if ($mediaPath !== '')
                <button
                    type="button"
                    class="tt-media-tile js-tt-media-open"
                    data-view-url="{{ $viewUrl }}"
                    data-download-url="{{ $downloadUrl }}"
                    data-media-kind="{{ $kind }}"
                    data-media-name="{{ $mediaName }}"
                    aria-label="Open {{ $mediaName }}"
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
            @endif
        @endforeach
    </div>
@endif

@once
    @push('styles')
    <style>
        .tt-media-empty { margin:0; color:#64748b; font-size:0.88rem; }
        .tt-media-grid { display:flex; flex-wrap:wrap; gap:0.75rem; }
        .tt-media-tile {
            width:132px;
            border:1px solid #e2e8f0;
            border-radius:12px;
            background:#fff;
            padding:0.45rem;
            display:flex;
            flex-direction:column;
            gap:0.4rem;
            cursor:pointer;
            text-align:left;
            box-shadow:0 4px 14px rgba(15,23,42,0.05);
            transition:transform 0.15s ease, box-shadow 0.15s ease, border-color 0.15s ease;
        }
        .tt-media-tile:hover {
            transform:translateY(-1px);
            border-color:#c7d2fe;
            box-shadow:0 8px 18px rgba(79,70,229,0.12);
        }
        .tt-media-tile__thumb,
        .tt-media-tile__video,
        .tt-media-tile__doc {
            width:100%;
            height:84px;
            border-radius:8px;
            overflow:hidden;
            background:#f8fafc;
            border:1px solid #e2e8f0;
            display:block;
        }
        .tt-media-tile__thumb { object-fit:cover; }
        .tt-media-tile__video { position:relative; }
        .tt-media-tile__video-el { width:100%; height:100%; object-fit:cover; display:block; background:#0f172a; }
        .tt-media-tile__play {
            position:absolute;
            inset:0;
            display:flex;
            align-items:center;
            justify-content:center;
            color:#fff;
            font-size:1.35rem;
            text-shadow:0 2px 8px rgba(15,23,42,0.45);
            background:linear-gradient(180deg, rgba(15,23,42,0.08), rgba(15,23,42,0.45));
        }
        .tt-media-tile__doc {
            display:flex;
            align-items:center;
            justify-content:center;
            background:linear-gradient(135deg, #eef2ff 0%, #f8fafc 100%);
        }
        .tt-media-tile__doc-badge {
            display:inline-flex;
            align-items:center;
            justify-content:center;
            min-width:3rem;
            padding:0.28rem 0.55rem;
            border-radius:999px;
            background:#4f46e5;
            color:#fff;
            font-size:0.72rem;
            font-weight:800;
            letter-spacing:0.04em;
        }
        .tt-media-tile__name {
            font-size:0.72rem;
            line-height:1.35;
            color:#334155;
            font-weight:600;
            display:-webkit-box;
            -webkit-line-clamp:2;
            -webkit-box-orient:vertical;
            overflow:hidden;
            word-break:break-word;
        }
        .tt-media-modal {
            position:fixed;
            inset:0;
            display:none;
            align-items:center;
            justify-content:center;
            background:rgba(15,23,42,0.58);
            z-index:90;
            padding:1rem;
        }
        .tt-media-modal.is-open { display:flex; }
        .tt-media-modal__card {
            width:min(980px, 96vw);
            max-height:92vh;
            background:#fff;
            border-radius:12px;
            border:1px solid #e5e7eb;
            overflow:hidden;
            box-shadow:0 20px 45px rgba(15,23,42,0.28);
            display:flex;
            flex-direction:column;
        }
        .tt-media-modal__head {
            display:flex;
            justify-content:space-between;
            align-items:center;
            gap:0.8rem;
            padding:0.7rem 0.9rem;
            border-bottom:1px solid #e5e7eb;
        }
        .tt-media-modal__title {
            font-size:0.9rem;
            font-weight:700;
            color:#0f172a;
            white-space:nowrap;
            overflow:hidden;
            text-overflow:ellipsis;
        }
        .tt-media-modal__actions { display:flex; gap:0.45rem; align-items:center; flex-shrink:0; }
        .tt-media-modal__btn {
            border:1px solid #cbd5e1;
            background:#f8fafc;
            color:#0f172a;
            border-radius:8px;
            padding:0.25rem 0.55rem;
            cursor:pointer;
            font-size:0.8rem;
            font-weight:700;
            text-decoration:none;
        }
        .tt-media-modal__body {
            padding:0.8rem;
            overflow:auto;
            background:#f8fafc;
            min-height:320px;
        }
        .tt-media-modal__frame {
            width:100%;
            min-height:72vh;
            border:1px solid #e2e8f0;
            border-radius:10px;
            background:#fff;
        }
        .tt-media-modal__img,
        .tt-media-modal__video {
            max-width:100%;
            max-height:72vh;
            display:block;
            margin:0 auto;
            border:1px solid #e2e8f0;
            border-radius:10px;
            background:#fff;
        }
        .tt-media-modal__video { width:100%; background:#0f172a; }
        .tt-media-modal__fallback {
            max-width:28rem;
            margin:1.5rem auto;
            padding:1.1rem 1.15rem;
            text-align:center;
            background:#fff;
            border:1px solid #e2e8f0;
            border-radius:12px;
            box-shadow:0 4px 14px rgba(15,23,42,0.06);
        }
        .tt-media-modal__fallback-lead {
            margin:0 0 0.55rem;
            font-size:0.92rem;
            font-weight:700;
            color:#0f172a;
            line-height:1.45;
        }
        .tt-media-modal__fallback-sub {
            margin:0 0 1rem;
            font-size:0.82rem;
            color:#64748b;
            line-height:1.5;
        }
        .tt-media-modal__fallback-download {
            display:inline-flex;
            align-items:center;
            justify-content:center;
            padding:0.55rem 1.15rem;
            border-radius:9px;
            background:#4f46e5;
            color:#fff !important;
            font-weight:800;
            font-size:0.86rem;
            text-decoration:none;
            border:1px solid #4338ca;
            box-shadow:0 4px 12px rgba(79,70,229,0.25);
        }
        .tt-media-modal__fallback-download:hover { background:#4338ca; color:#fff !important; }
    </style>
    @endpush

    @push('scripts')
    <script>
    (function () {
        const modal = document.getElementById('ttMediaModal');
        if (!modal) {
            return;
        }

        const modalBody = document.getElementById('ttMediaModalBody');
        const modalTitle = document.getElementById('ttMediaModalTitle');
        const closeBtn = document.getElementById('ttMediaModalClose');
        const downloadBtn = document.getElementById('ttMediaModalDownload');

        function closeModal() {
            modal.classList.remove('is-open');
            modal.setAttribute('aria-hidden', 'true');
            if (modalBody) {
                modalBody.innerHTML = '';
            }
        }

        function previewHintForFileName(fileName) {
            const n = (fileName || '').toLowerCase();
            if (n.endsWith('.docx') || n.endsWith('.doc')) {
                return {
                    lead: 'Word file — no in-browser preview',
                    sub: 'Browsers cannot show .doc / .docx inside this window. Download the attendance sheet and open it in Microsoft Word or another compatible app.',
                };
            }
            if (n.endsWith('.xlsx') || n.endsWith('.xls')) {
                return {
                    lead: 'Excel file — no in-browser preview',
                    sub: 'Browsers cannot show .xls / .xlsx here. Download the file and open it in Microsoft Excel or another compatible app.',
                };
            }
            if (n.endsWith('.pptx') || n.endsWith('.ppt')) {
                return {
                    lead: 'Presentation — no in-browser preview',
                    sub: 'Download the file to open it in PowerPoint or another compatible app.',
                };
            }

            return {
                lead: 'Preview not available',
                sub: 'This file type cannot be shown inside the browser. Download the file to open it on your device.',
            };
        }

        function openModal(viewUrl, downloadUrl, kind, name) {
            if (!modalBody || !modalTitle) {
                return;
            }

            const safeName = name || 'Attachment';
            modalTitle.textContent = safeName;
            modalBody.innerHTML = '';

            const href = downloadUrl || viewUrl || '#';
            if (downloadBtn) {
                downloadBtn.href = href;
                downloadBtn.textContent = 'Download';
            }

            if (kind === 'image') {
                const img = document.createElement('img');
                img.className = 'tt-media-modal__img';
                img.alt = safeName + ' preview';
                img.src = viewUrl;
                modalBody.appendChild(img);
            } else if (kind === 'video') {
                const video = document.createElement('video');
                video.className = 'tt-media-modal__video';
                video.controls = true;
                video.autoplay = true;
                video.playsInline = true;
                video.src = viewUrl;
                modalBody.appendChild(video);
            } else if (kind === 'pdf') {
                const frame = document.createElement('iframe');
                frame.className = 'tt-media-modal__frame';
                frame.src = viewUrl;
                frame.title = safeName + ' preview';
                modalBody.appendChild(frame);
            } else {
                const hint = previewHintForFileName(safeName);
                const wrap = document.createElement('div');
                wrap.className = 'tt-media-modal__fallback';
                const lead = document.createElement('p');
                lead.className = 'tt-media-modal__fallback-lead';
                lead.textContent = hint.lead;
                const sub = document.createElement('p');
                sub.className = 'tt-media-modal__fallback-sub';
                sub.textContent = hint.sub;
                const dl = document.createElement('a');
                dl.className = 'tt-media-modal__fallback-download';
                dl.href = href;
                dl.target = '_blank';
                dl.rel = 'noopener';
                dl.textContent = 'Download file';
                wrap.appendChild(lead);
                wrap.appendChild(sub);
                wrap.appendChild(dl);
                modalBody.appendChild(wrap);
            }

            modal.classList.add('is-open');
            modal.setAttribute('aria-hidden', 'false');
        }

        document.addEventListener('click', function (event) {
            const button = event.target.closest('.js-tt-media-open');
            if (!button) {
                return;
            }

            openModal(
                button.getAttribute('data-view-url') || '',
                button.getAttribute('data-download-url') || '',
                button.getAttribute('data-media-kind') || 'file',
                button.getAttribute('data-media-name') || 'Attachment'
            );
        });

        if (closeBtn) {
            closeBtn.addEventListener('click', closeModal);
        }

        modal.addEventListener('click', function (event) {
            if (event.target === modal) {
                closeModal();
            }
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && modal.classList.contains('is-open')) {
                closeModal();
            }
        });
    }());
    </script>
    @endpush
@endonce

@once
<div id="ttMediaModal" class="tt-media-modal" aria-hidden="true">
    <div class="tt-media-modal__card" role="dialog" aria-modal="true" aria-label="Attachment preview">
        <div class="tt-media-modal__head">
            <div id="ttMediaModalTitle" class="tt-media-modal__title">Attachment</div>
            <div class="tt-media-modal__actions">
                <a id="ttMediaModalDownload" class="tt-media-modal__btn" href="#" target="_blank" rel="noopener">Download</a>
                <button type="button" id="ttMediaModalClose" class="tt-media-modal__btn">Close</button>
            </div>
        </div>
        <div id="ttMediaModalBody" class="tt-media-modal__body"></div>
    </div>
</div>
@endonce
