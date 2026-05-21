@push('scripts')
<script>
(function () {
    const photosInput = document.getElementById('tpPhotosInput');
    const photosPreview = document.getElementById('tpPhotosPreview');
    if (!photosInput || !photosPreview) {
        return;
    }

    const maxPhotos = 5;
    const selectedFiles = new DataTransfer();
    const previewUrls = new Map();

    const fileKey = function (file) {
        return [file.name, file.size, file.lastModified].join('::');
    };

    const syncPhotosInput = function () {
        photosInput.files = selectedFiles.files;
    };

    const releasePreviewUrls = function () {
        previewUrls.forEach((url) => URL.revokeObjectURL(url));
        previewUrls.clear();
    };

    const renderPendingPreview = function () {
        releasePreviewUrls();
        photosPreview.innerHTML = '';
        const files = Array.from(selectedFiles.files || []);
        if (!files.length) {
            return;
        }

        const grid = document.createElement('div');
        grid.className = 'tt-media-grid';
        photosPreview.appendChild(grid);

        files.forEach((file) => {
            const objectUrl = URL.createObjectURL(file);
            previewUrls.set(fileKey(file), objectUrl);
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'tt-media-tile js-tt-media-open';
            button.setAttribute('data-view-url', objectUrl);
            button.setAttribute('data-download-url', objectUrl);
            button.setAttribute('data-media-kind', 'image');
            button.setAttribute('data-media-name', file.name || 'Photo');
            button.setAttribute('aria-label', 'Open ' + (file.name || 'Photo'));

            const img = document.createElement('img');
            img.className = 'tt-media-tile__thumb';
            img.alt = file.name || 'Photo preview';
            img.src = objectUrl;
            button.appendChild(img);

            const name = document.createElement('span');
            name.className = 'tt-media-tile__name';
            name.textContent = file.name || 'Photo';
            button.appendChild(name);
            grid.appendChild(button);
        });
    };

    const addFiles = function (fileList) {
        Array.from(fileList || []).forEach((file) => {
            if (selectedFiles.files.length >= maxPhotos) {
                return;
            }
            const key = fileKey(file);
            const alreadySelected = Array.from(selectedFiles.files).some((existing) => fileKey(existing) === key);
            if (!alreadySelected) {
                selectedFiles.items.add(file);
            }
        });
        syncPhotosInput();
        renderPendingPreview();
    };

    photosInput.addEventListener('change', function () {
        addFiles(this.files);
    });
}());
</script>
@endpush
