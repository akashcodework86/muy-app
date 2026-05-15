@push('styles')
<style>
    .ees-photo-grid { display:flex; flex-wrap:wrap; gap:0.65rem; margin-top:0.55rem; }
    .ees-photo-tile-wrap { position:relative; width:88px; flex-shrink:0; }
    .ees-photo-tile-wrap .tt-media-tile { width:100%; margin:0; padding:0.45rem; }
    .ees-photo-remove {
        position:absolute; top:-6px; right:-6px; z-index:2;
        width:22px; height:22px; border-radius:999px;
        border:1px solid #fecaca; background:#fff; color:#b91c1c;
        font-size:1rem; line-height:1; font-weight:800; cursor:pointer;
        display:flex; align-items:center; justify-content:center;
        box-shadow:0 2px 6px rgba(15,23,42,0.15); padding:0;
    }
    .ees-photo-remove:hover { background:#fef2f2; }
    .ees-photo-existing-grid { display:flex; flex-wrap:wrap; gap:0.65rem; margin-top:0.5rem; }
    .ees-photo-existing-item { position:relative; width:88px; }
    .ees-photo-existing-item.is-marked-remove .ees-photo-existing-thumb { opacity:0.4; filter:grayscale(0.7); }
    .ees-photo-existing-thumb {
        display:block; width:88px; height:66px; object-fit:cover;
        border-radius:8px; border:1px solid #e2e8f0; cursor:pointer; background:#f8fafc;
    }
    .ees-photo-existing-remove {
        position:absolute; top:-6px; right:-6px; z-index:2;
        width:22px; height:22px; border-radius:999px;
        border:1px solid #fecaca; background:#fff; color:#b91c1c;
        font-size:1rem; line-height:1; font-weight:800; cursor:pointer;
        display:flex; align-items:center; justify-content:center;
        box-shadow:0 2px 6px rgba(15,23,42,0.15); padding:0;
    }
    .ees-photo-existing-remove:hover { background:#fef2f2; }
    .ees-dash-photos { display:flex; flex-wrap:wrap; gap:0.35rem; align-items:center; max-width:220px; }
    .ees-dash-photo {
        width:40px; height:40px; object-fit:cover;
        border-radius:6px; border:1px solid #e2e8f0; cursor:pointer; background:#f8fafc;
    }
    .ees-dash-photo-more {
        display:inline-flex; align-items:center; justify-content:center;
        min-width:40px; height:40px; padding:0 0.35rem; border-radius:6px;
        background:#f1f5f9; border:1px solid #e2e8f0;
        font-size:0.72rem; font-weight:800; color:#475569;
    }
    .ees-dash-photos-empty { font-size:0.76rem; color:#94a3b8; }
</style>
@endpush

@push('scripts')
<script>
(function () {
    function isLikelyImage(file) {
        if (!file) return false;
        if (file.type && file.type.indexOf('image/') === 0) return true;
        return /\.(jpe?g|png|webp|gif|bmp|heic|heif)$/i.test(file.name || '');
    }

    function initPhotoPicker(inputId, previewId) {
        const input = document.getElementById(inputId);
        const preview = document.getElementById(previewId);
        if (!input || !preview) return;

        const bucket = new DataTransfer();
        const urls = new Map();

        function key(file) {
            return [file.name, file.size, file.lastModified].join('::');
        }

        function syncToInput() {
            try {
                input.files = bucket.files;
            } catch (err) {
                console.warn('Could not sync photo files to input', err);
            }
        }

        function render() {
            urls.forEach(function (u) { URL.revokeObjectURL(u); });
            urls.clear();
            preview.innerHTML = '';

            const files = Array.from(bucket.files);
            if (!files.length) return;

            const grid = document.createElement('div');
            grid.className = 'ees-photo-grid';
            preview.appendChild(grid);

            files.forEach(function (file) {
                const k = key(file);
                const objectUrl = URL.createObjectURL(file);
                urls.set(k, objectUrl);

                const wrap = document.createElement('div');
                wrap.className = 'ees-photo-tile-wrap';

                const removeBtn = document.createElement('button');
                removeBtn.type = 'button';
                removeBtn.className = 'ees-photo-remove';
                removeBtn.textContent = '×';
                removeBtn.setAttribute('aria-label', 'Remove photo');
                removeBtn.addEventListener('click', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    const next = new DataTransfer();
                    Array.from(bucket.files).forEach(function (f) {
                        if (key(f) !== k) next.items.add(f);
                    });
                    bucket.items.clear();
                    Array.from(next.files).forEach(function (f) { bucket.items.add(f); });
                    syncToInput();
                    render();
                });

                const openBtn = document.createElement('button');
                openBtn.type = 'button';
                openBtn.className = 'tt-media-tile js-tt-media-open';
                openBtn.setAttribute('data-view-url', objectUrl);
                openBtn.setAttribute('data-download-url', objectUrl);
                openBtn.setAttribute('data-media-kind', 'image');
                openBtn.setAttribute('data-media-name', file.name || 'Photo');

                const img = document.createElement('img');
                img.className = 'tt-media-tile__thumb';
                img.src = objectUrl;
                img.alt = file.name || 'Photo';
                openBtn.appendChild(img);

                wrap.appendChild(removeBtn);
                wrap.appendChild(openBtn);
                grid.appendChild(wrap);
            });
        }

        input.addEventListener('change', function () {
            Array.from(input.files || []).forEach(function (file) {
                if (!isLikelyImage(file)) return;
                const k = key(file);
                const exists = Array.from(bucket.files).some(function (f) { return key(f) === k; });
                if (!exists) bucket.items.add(file);
            });
            syncToInput();
            render();
        });

        const form = input.closest('form');
        if (form) {
            form.addEventListener('submit', function () {
                syncToInput();
            }, true);
        }
    }

    function boot() {
        initPhotoPicker('eesSessionPhotosInput', 'eesSessionPhotosPreview');
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
</script>
@endpush
