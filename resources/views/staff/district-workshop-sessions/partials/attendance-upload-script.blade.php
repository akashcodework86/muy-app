@push('scripts')
<script>
(function () {
    const mediaInput = document.getElementById('tpMediaInput');
    const mediaPreview = document.getElementById('tpMediaPreview');
    if (!mediaInput || !mediaPreview) {
        return;
    }

    const selectedFiles = new DataTransfer();
    const previewUrls = new Map();

    const detectKind = function (file) {
        const type = file.type || '';
        const name = (file.name || '').toLowerCase();
        if (type.startsWith('image/')) {
            return 'image';
        }
        if (type === 'application/pdf' || name.endsWith('.pdf')) {
            return 'pdf';
        }
        return 'file';
    };

    const fileLabel = function (kind, file) {
        if (kind === 'pdf') {
            return 'PDF';
        }
        const parts = (file.name || '').split('.');
        return parts.length > 1 ? parts.pop().toUpperCase() : 'File';
    };

    const fileKey = function (file) {
        return [file.name, file.size, file.lastModified].join('::');
    };

    const syncMediaInput = function () {
        mediaInput.files = selectedFiles.files;
    };

    const releasePreviewUrls = function () {
        previewUrls.forEach((url) => URL.revokeObjectURL(url));
        previewUrls.clear();
    };

    const renderPendingPreview = function () {
        releasePreviewUrls();
        mediaPreview.innerHTML = '';
        const files = Array.from(selectedFiles.files || []);
        if (!files.length) {
            return;
        }

        const grid = document.createElement('div');
        grid.className = 'tt-media-grid';
        mediaPreview.appendChild(grid);

        files.forEach((file) => {
            const kind = detectKind(file);
            const objectUrl = URL.createObjectURL(file);
            previewUrls.set(fileKey(file), objectUrl);
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'tt-media-tile js-tt-media-open';
            button.setAttribute('data-view-url', objectUrl);
            button.setAttribute('data-download-url', objectUrl);
            button.setAttribute('data-media-kind', kind);
            button.setAttribute('data-media-name', file.name || 'Attachment');
            button.setAttribute('aria-label', 'Open ' + (file.name || 'Attachment'));

            if (kind === 'image') {
                const img = document.createElement('img');
                img.className = 'tt-media-tile__thumb';
                img.alt = file.name || 'Image preview';
                img.src = objectUrl;
                button.appendChild(img);
            } else {
                const doc = document.createElement('span');
                doc.className = 'tt-media-tile__doc';
                const badge = document.createElement('span');
                badge.className = 'tt-media-tile__doc-badge';
                badge.textContent = fileLabel(kind, file);
                doc.appendChild(badge);
                button.appendChild(doc);
            }

            const name = document.createElement('span');
            name.className = 'tt-media-tile__name';
            name.textContent = file.name || 'Attachment';
            button.appendChild(name);
            grid.appendChild(button);
        });
    };

    const addFiles = function (fileList) {
        Array.from(fileList || []).forEach((file) => {
            const key = fileKey(file);
            const alreadySelected = Array.from(selectedFiles.files).some((existing) => fileKey(existing) === key);
            if (!alreadySelected) {
                selectedFiles.items.add(file);
            }
        });
        syncMediaInput();
        renderPendingPreview();
    };

    mediaInput.addEventListener('change', function () {
        addFiles(this.files);
    });
}());
</script>
@endpush
