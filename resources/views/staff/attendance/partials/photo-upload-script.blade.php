@php
    $rp = $routePrefix ?? 'staff.attendance';
    $mp = $modelParam ?? 'attendanceReport';
    $initialPhotoItems = [];
    if (! empty($activeDraft)) {
        foreach ($activeDraft->visitMediaItems() as $photoIndex => $photoItem) {
            $initialPhotoItems[] = [
                'index' => $photoIndex,
                'url' => route($rp.'.attachment', [
                    $mp => $activeDraft,
                    'index' => $photoIndex,
                    'inline' => 1,
                ]),
                'name' => $photoItem['original_name'] ?? 'photo',
            ];
        }
    }
@endphp
<script>
(function () {
    const draftWorkflow = @json($draftWorkflow ?? false);
    if (!draftWorkflow) return;

    const csrf = @json(csrf_token());
    const maxPhotos = 15;
    const photoInput = document.getElementById('attPhotoInput');
    const preview = document.getElementById('attPhotoPreview');
    const statusEl = document.getElementById('attPhotoStatus');
    const pickerHint = document.getElementById('attPhotoPickerHint');
    const photosFlag = document.getElementById('attPhotosUploadedFlag');
    const workshopForm = document.getElementById('attWorkshopForm');
    const initialItems = @json($initialPhotoItems);

    let draftId = @json($activeDraft?->id);
    let photoItems = Array.isArray(initialItems) ? initialItems.slice() : [];
    let uploading = false;

    const routes = {
        createDraft: @json(route($rp.'.draft.create')),
        uploadPhotos: @json(route($rp.'.photos.upload', [$mp => '__ID__'])),
        deletePhoto: @json(route($rp.'.photos.delete', [$mp => '__ID__', 'photoIndex' => '__INDEX__'])),
    };

    function routeFor(template, id, index) {
        return template.replace('__ID__', String(id)).replace('__INDEX__', String(index));
    }

    function setStatus(text, type) {
        if (!statusEl) return;
        statusEl.textContent = text;
        statusEl.className = 'att-photo-status' + (type ? ' att-photo-status--' + type : '');
    }

    function updatePhotosFlag() {
        if (photosFlag) {
            photosFlag.value = photoItems.length > 0 ? '1' : '0';
        }
        if (pickerHint) {
            const left = maxPhotos - photoItems.length;
            pickerHint.textContent = left > 0
                ? 'Choose photos (' + photoItems.length + '/' + maxPhotos + ' uploaded)'
                : 'Maximum ' + maxPhotos + ' photos reached';
        }
        if (photoInput) {
            photoInput.disabled = photoItems.length >= maxPhotos;
        }
    }

    function renderPreview() {
        if (!preview) return;
        preview.innerHTML = '';
        photoItems.forEach(function (item) {
            const wrap = document.createElement('div');
            wrap.className = 'att-photo-preview__item';
            wrap.innerHTML =
                '<img src="' + item.url + '" alt="">' +
                '<button type="button" class="att-photo-preview__remove" data-index="' + item.index + '" title="Remove">&times;</button>';
            preview.appendChild(wrap);
        });
        preview.querySelectorAll('.att-photo-preview__remove').forEach(function (btn) {
            btn.addEventListener('click', function () {
                deletePhoto(parseInt(btn.getAttribute('data-index'), 10));
            });
        });
        updatePhotosFlag();
    }

    async function ensureDraft() {
        if (draftId) return draftId;
        const res = await fetch(routes.createDraft, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: '{}',
        });
        const data = await res.json();
        if (!res.ok) throw new Error(data.message || 'Could not start draft');
        draftId = Number(data.id);
        if (workshopForm) {
            workshopForm.action = routeFor(@json(route($rp.'.draft.submit', [$mp => '__ID__'])), draftId);
        }
        return draftId;
    }

    async function uploadFiles(fileList) {
        if (!fileList || fileList.length === 0) return;
        const files = Array.from(fileList);
        const remaining = maxPhotos - photoItems.length;
        if (remaining <= 0) {
            setStatus('Maximum ' + maxPhotos + ' photos already uploaded.', 'err');
            return;
        }
        const batch = files.slice(0, remaining);

        uploading = true;
        setStatus('Uploading ' + batch.length + ' photo(s)…', '');

        try {
            await ensureDraft();
            const formData = new FormData();
            batch.forEach(function (file) {
                formData.append('visit_media[]', file);
            });

            const res = await fetch(routeFor(routes.uploadPhotos, draftId), {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: formData,
            });
            const data = await res.json();
            if (!res.ok) {
                throw new Error(data.message || 'Upload failed');
            }
            photoItems = Array.isArray(data.items) ? data.items : [];
            renderPreview();
            setStatus(photoItems.length + ' photo(s) saved.', 'ok');
        } catch (e) {
            setStatus(e.message || 'Upload failed. Try fewer or smaller photos.', 'err');
        } finally {
            uploading = false;
            if (photoInput) photoInput.value = '';
        }
    }

    async function deletePhoto(index) {
        if (!draftId || uploading) return;
        uploading = true;
        setStatus('Removing photo…', '');
        try {
            const res = await fetch(routeFor(routes.deletePhoto, draftId, index), {
                method: 'DELETE',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
            const data = await res.json();
            if (!res.ok) throw new Error(data.message || 'Could not remove photo');
            photoItems = Array.isArray(data.items) ? data.items : [];
            renderPreview();
            setStatus(photoItems.length + ' photo(s) saved.', 'ok');
        } catch (e) {
            setStatus(e.message || 'Could not remove photo', 'err');
        } finally {
            uploading = false;
        }
    }

    photoInput?.addEventListener('change', function () {
        uploadFiles(photoInput.files);
    });

    workshopForm?.addEventListener('submit', function (e) {
        if (photoItems.length === 0) {
            e.preventDefault();
            setStatus('Upload at least one photo before submitting.', 'err');
            document.getElementById('attPhotoSection')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
            return;
        }
        if (photoInput) {
            photoInput.removeAttribute('name');
        }
    });

    renderPreview();
    if (photoItems.length > 0) {
        setStatus(photoItems.length + ' photo(s) saved.', 'ok');
    }
})();
</script>
