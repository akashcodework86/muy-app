@push('styles')
<style>
    .ddy-media-preview { margin-top:0.55rem; }
    .ddy-photo-grid { display:flex; flex-wrap:wrap; gap:0.65rem; }
    .ddy-photo-tile-wrap { position:relative; width:88px; flex-shrink:0; }
    .ddy-photo-tile-wrap .tt-media-tile { width:100%; margin:0; padding:0.45rem; }
    .ddy-photo-remove {
        position:absolute; top:-6px; right:-6px; z-index:2;
        width:22px; height:22px; border-radius:999px;
        border:1px solid #fecaca; background:#fff; color:#b91c1c;
        font-size:1rem; line-height:1; font-weight:800; cursor:pointer;
        display:flex; align-items:center; justify-content:center;
        box-shadow:0 2px 6px rgba(15,23,42,0.15); padding:0;
    }
    .ddy-photo-remove:hover { background:#fef2f2; }
    .ddy-photo-tile-wrap .tt-media-tile__thumb {
        width:100%; height:66px; object-fit:cover; border-radius:6px;
    }
</style>
@endpush

@push('scripts')
<script>
(function () {
    const input = document.getElementById('ddyEventPhotosInput');
    const preview = document.getElementById('ddyEventPhotosPreview');
    if (!input || !preview) return;

    const maxPhotos = {{ (int) ($maxPhotos ?? 5) }};
    if (maxPhotos < 1) return;

    const bucket = new DataTransfer();
    const urls = new Map();

    function isLikelyImage(file) {
        if (!file) return false;
        if (file.type && file.type.indexOf('image/') === 0) return true;
        return /\.(jpe?g|png|webp|gif|bmp|heic|heif)$/i.test(file.name || '');
    }

    function key(file) {
        return [file.name, file.size, file.lastModified].join('::');
    }

    function syncToInput() {
        try {
            input.files = bucket.files;
        } catch (err) {
            console.warn('Could not sync event photos to input', err);
        }
    }

    function render() {
        urls.forEach(function (u) { URL.revokeObjectURL(u); });
        urls.clear();
        preview.innerHTML = '';

        const files = Array.from(bucket.files);
        if (!files.length) return;

        const grid = document.createElement('div');
        grid.className = 'ddy-photo-grid';
        preview.appendChild(grid);

        files.forEach(function (file) {
            const k = key(file);
            const objectUrl = URL.createObjectURL(file);
            urls.set(k, objectUrl);

            const wrap = document.createElement('div');
            wrap.className = 'ddy-photo-tile-wrap';

            const removeBtn = document.createElement('button');
            removeBtn.type = 'button';
            removeBtn.className = 'ddy-photo-remove';
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
            if (bucket.files.length >= maxPhotos) return;
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
})();
</script>
@endpush
