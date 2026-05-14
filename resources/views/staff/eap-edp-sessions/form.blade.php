@extends('layouts.admin')

@section('title', 'EAP / EDP sessions')
@section('heading', 'EAP / EDP sessions (combined)')

@push('styles')
<style>
    .tp-shell { display:flex; flex-direction:column; gap:1.25rem; }
    .tp-alert { border-radius:12px; padding:0.85rem 1rem; font-size:0.88rem; }
    .tp-alert--warning { background:#fffbeb; border:1px solid #fde68a; color:#92400e; }
    .tp-alert--success { background:#f0fdf4; border:1px solid #86efac; color:#166534; }
    .tp-alert--error { background:#fef2f2; border:1px solid #fca5a5; color:#991b1b; }
    .tp-alert--error ul { margin:0.35rem 0 0 1rem; }
    .tp-card { background:#fff; border:1px solid #e2e8f0; border-radius:14px; padding:1.25rem 1.35rem; }
    .tp-card__title { margin:0 0 1.15rem; font-size:1rem; font-weight:700; color:#0f172a; }
    .tp-section { margin-top:1.35rem; }
    .tp-section__title { margin:0 0 0.75rem; font-size:0.9rem; font-weight:700; color:#0f172a; }
    .tp-grid { display:grid; grid-template-columns:repeat(2, minmax(0, 1fr)); gap:1rem 1.1rem; align-items:start; }
    .tp-field { display:flex; flex-direction:column; gap:0.4rem; min-width:0; }
    .tp-field--full { grid-column:1 / -1; }
    .tp-field label { font-size:0.82rem; font-weight:700; color:#0f172a; line-height:1.35; }
    .tp-field input[type="text"],
    .tp-field input[type="date"],
    .tp-field input[type="file"],
    .tp-field textarea { width:100%; box-sizing:border-box; border:1px solid #cbd5e1; border-radius:8px; padding:0.58rem 0.7rem; font-size:0.88rem; background:#fff; }
    .tp-field textarea { min-height:5.5rem; resize:vertical; }
    .tp-field input[type="file"] { padding:0.45rem 0.55rem; background:#f8fafc; }
    .tp-readonly { background:#f8fafc; color:#64748b; }
    .tp-two-col { display:grid; grid-template-columns:minmax(0, 1.15fr) minmax(0, 0.85fr); gap:1rem; align-items:start; }
    .tp-col { display:flex; flex-direction:column; gap:0.55rem; min-width:0; }
    .tp-note { margin:0; color:#64748b; font-size:0.8rem; line-height:1.45; }
    .tp-search { width:100%; border:1px solid #cbd5e1; border-radius:8px; padding:0.55rem 0.7rem; font-size:0.88rem; box-sizing:border-box; }
    .tp-list { max-height:420px; overflow:auto; border:1px solid #e2e8f0; border-radius:10px; padding:0.55rem; background:#f8fafc; }
    .tp-item { display:flex; gap:0.65rem; align-items:flex-start; border:1px solid #e2e8f0; border-radius:10px; padding:0.6rem 0.65rem; background:#fff; margin-bottom:0.5rem; cursor:pointer; }
    .tp-item:last-child { margin-bottom:0; }
    .tp-item input { margin-top:0.15rem; flex-shrink:0; }
    .tp-item h4 { margin:0; font-size:0.86rem; line-height:1.35; }
    .tp-meta { margin-top:0.25rem; color:#64748b; font-size:0.76rem; line-height:1.4; }
    .tp-pill { display:inline-block; font-size:0.7rem; background:#eef2ff; color:#3730a3; border-radius:999px; padding:0.14rem 0.48rem; margin:0 0.3rem 0.25rem 0; }
    .tp-right-title { margin:0; font-size:0.86rem; font-weight:800; color:#0f172a; display:flex; align-items:center; gap:0.45rem; }
    .tp-selected-count { display:inline-flex; align-items:center; justify-content:center; min-width:1.45rem; height:1.45rem; border-radius:999px; background:#4f46e5; color:#fff; font-size:0.72rem; font-weight:800; }
    .tp-selected-empty { margin:0; padding:0.75rem; color:#64748b; font-size:0.84rem; }
    .tp-field-hint { margin:0.2rem 0 0; color:#64748b; font-size:0.78rem; line-height:1.4; }
    .tp-media-preview { margin-top:0.55rem; }
    .tp-actions { margin-top:1.25rem; display:flex; flex-wrap:wrap; gap:0.65rem; align-items:center; }
    .tp-submit { border:none; border-radius:8px; background:#4f46e5; color:#fff; padding:0.62rem 1rem; font-weight:700; cursor:pointer; font-size:0.88rem; }
    .tp-link { color:#4f46e5; font-weight:700; text-decoration:none; font-size:0.88rem; }
    .tp-btn-remove { margin-top:0.45rem; border:1px solid #fecaca; background:#fff; color:#b91c1c; border-radius:8px; padding:0.28rem 0.55rem; font-size:0.76rem; font-weight:700; cursor:pointer; }
</style>
@endpush

@section('content')
<div class="tp-shell">
    @if (!empty($migrationMissing))
        <div class="tp-alert tp-alert--warning">
            <strong>EAP/EDP sessions table not found.</strong> Run <code>php artisan migrate</code> first.
        </div>
    @endif

    @if (session('status'))
        <div class="tp-alert tp-alert--success">
            {{ session('status') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="tp-alert tp-alert--error">
            <strong>Please fix:</strong>
            <ul>
                @foreach ($errors->all() as $e)
                    <li>{{ $e }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @php
        $oldSelectedIds = collect((array) old('selected_incubatees', []))
            ->map(fn ($id) => (int) $id)
            ->all();
    @endphp

    <div class="tp-card">
        <h3 class="tp-card__title">Submission Form</h3>
        <form method="post" action="{{ route('staff.eap-edp-sessions.store') }}" enctype="multipart/form-data">
            @csrf
            <div class="tp-grid">
                <div class="tp-field">
                    <label>Session Taken By</label>
                    <input type="text" class="tp-readonly" value="{{ $user->name }}" readonly>
                </div>
                <div class="tp-field">
                    <label>Date of Session *</label>
                    <input type="date" name="session_date" value="{{ old('session_date') }}" required>
                </div>
                <div class="tp-field">
                    <label>District</label>
                    <input type="text" class="tp-readonly" value="{{ $user->district?->name ?? 'Not assigned' }}" readonly>
                </div>
                @include('staff.eap-edp-sessions.partials.workshop-mode-field', ['selected' => null])
                <div class="tp-field tp-field--full">
                    <label>Session topic *</label>
                    <input type="text" name="topic" value="{{ old('topic') }}" maxlength="191" required placeholder="Topic covered in this combined EAP/EDP session">
                </div>
                <div class="tp-field tp-field--full">
                    <label>Notes (optional)</label>
                    <textarea name="notes" maxlength="5000" placeholder="Optional notes">{{ old('notes') }}</textarea>
                </div>
            </div>

            <div class="tp-section">
                <div class="tp-field">
                    <label>Upload photos, videos, or documents (optional)</label>
                    <input id="tpMediaInput" type="file" name="attendance_media[]" accept=".pdf,.jpg,.jpeg,.png,.webp,.mp4,.mov,.avi,.mkv,.doc,.docx,.xls,.xlsx" multiple>
                    <p class="tp-field-hint">Select multiple files at once or use Choose files again to add more before submitting. Up to 25 files, 50 MB each. Images, videos, PDF, Word, and Excel are accepted.</p>
                    <div id="tpMediaPreview" class="tp-media-preview"></div>
                </div>
            </div>

            <div class="tp-section">
                <h4 class="tp-section__title">Manual Attendance Selection * (required)</h4>
                <div class="tp-two-col">
                    <div class="tp-col">
                        <p class="tp-note">
                            rbiphase3 onboarded applicants in district: <strong>{{ (int) ($totalOnboardedCount ?? $incubatees->count()) }}</strong>
                        </p>
                        <input id="tpSearch" class="tp-search" type="text" placeholder="Search rbiphase3 onboarded applicants by name/application/phone">
                        <div class="tp-list" id="tpSourceList">
                            @forelse ($incubatees as $applicant)
                                <label class="tp-item" data-search="{{ strtolower($applicant['name'].' '.$applicant['application_no'].' '.$applicant['phone']) }}">
                                    <input type="checkbox" class="tp-check" value="{{ $applicant['incubatee_id'] }}" @checked(in_array((int) $applicant['incubatee_id'], $oldSelectedIds, true))>
                                    <div>
                                        <h4>{{ $applicant['name'] ?: 'Unnamed' }}</h4>
                                        <div class="tp-meta">
                                            <span class="tp-pill">App: {{ $applicant['application_no'] ?: 'NA' }}</span>
                                            <span class="tp-pill">Batch: {{ $applicant['onboarding_batch_name'] ?: 'NA' }}</span>
                                        </div>
                                        <div class="tp-meta">Phone: {{ $applicant['phone'] ?: 'NA' }} | Block: {{ $applicant['block_name'] ?: 'NA' }} | Village: {{ $applicant['village'] ?: 'NA' }}</div>
                                    </div>
                                </label>
                            @empty
                                <p class="tp-selected-empty">No rbiphase3 onboarded applicants found for your district.</p>
                            @endforelse
                        </div>
                    </div>
                    <div class="tp-col">
                        <p class="tp-right-title">Selected Incubatees <span id="tpSelectedCount" class="tp-selected-count">0</span></p>
                        <div class="tp-list" id="tpSelectedPanel"></div>
                    </div>
                </div>
            </div>

            <div id="tpHiddenInputs"></div>
            <div class="tp-actions">
                <button class="tp-submit" type="submit">Submit attendance</button>
                <a class="tp-link" href="{{ route('staff.eap-edp-sessions.dashboard') }}">View dashboard</a>
            </div>
        </form>
    </div>

    @include('staff.technical-trainings.partials.attendance-media-preview', [
        'mediaItems' => [],
        'showEmptyMessage' => false,
        'record' => null,
    ])
</div>
@endsection

@push('scripts')
<script>
(function () {
    const checks = Array.from(document.querySelectorAll('.tp-check'));
    const selectedPanel = document.getElementById('tpSelectedPanel');
    const hiddenInputs = document.getElementById('tpHiddenInputs');
    const search = document.getElementById('tpSearch');
    const sourceList = document.getElementById('tpSourceList');
    const selectedCount = document.getElementById('tpSelectedCount');
    const mediaInput = document.getElementById('tpMediaInput');
    const mediaPreview = document.getElementById('tpMediaPreview');
    const selectedMap = new Map();

    function sourceCardForCheckbox(el) {
        return el && el.closest('.tp-item') ? el.closest('.tp-item') : null;
    }

    function syncMapFromCheckbox(el) {
        const id = String(el.value || '');
        if (id === '') return;
        if (el.checked) {
            const card = sourceCardForCheckbox(el);
            if (card) {
                selectedMap.set(id, card.cloneNode(true));
            }
        } else {
            selectedMap.delete(id);
        }
    }

    function renderSelected() {
        hiddenInputs.innerHTML = '';
        if (selectedCount) {
            selectedCount.textContent = String(selectedMap.size);
        }
        if (!selectedMap.size) {
            selectedPanel.innerHTML = '<p class="tp-selected-empty">No incubatee selected yet.</p>';
            return;
        }

        selectedPanel.innerHTML = '';
        selectedMap.forEach((storedCard, selectedId) => {
            const card = storedCard.cloneNode(true);
            const cardCheckbox = card.querySelector('.tp-check');
            if (cardCheckbox) {
                cardCheckbox.remove();
            }

            const removeBtn = document.createElement('button');
            removeBtn.type = 'button';
            removeBtn.textContent = 'Remove';
            removeBtn.className = 'tp-btn-remove';
            removeBtn.addEventListener('click', function () {
                const linkedCheckbox = checks.find((c) => String(c.value || '') === String(selectedId));
                if (linkedCheckbox) {
                    linkedCheckbox.checked = false;
                }
                selectedMap.delete(String(selectedId));
                renderSelected();
            });

            const content = card.querySelector('div');
            if (content) {
                content.appendChild(removeBtn);
            } else {
                card.appendChild(removeBtn);
            }

            selectedPanel.appendChild(card);

            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'selected_incubatees[]';
            input.value = String(selectedId);
            hiddenInputs.appendChild(input);
        });
    }

    checks.forEach((el) => {
        if (el.checked) {
            syncMapFromCheckbox(el);
        }
        el.addEventListener('change', function () {
            syncMapFromCheckbox(el);
            renderSelected();
        });
    });

    if (search && sourceList) {
        search.addEventListener('input', function () {
            const term = (this.value || '').toLowerCase().trim();
            sourceList.querySelectorAll('.tp-item').forEach((item) => {
                const hay = item.getAttribute('data-search') || '';
                item.style.display = term === '' || hay.includes(term) ? '' : 'none';
            });
        });
    }

    if (mediaInput && mediaPreview) {
        const selectedFiles = new DataTransfer();
        const previewUrls = new Map();

        const detectKind = function (file) {
            const type = file.type || '';
            const name = (file.name || '').toLowerCase();
            if (type.startsWith('image/')) {
                return 'image';
            }
            if (type.startsWith('video/')) {
                return 'video';
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
            if (kind === 'video') {
                return 'Video';
            }
            if (kind === 'file') {
                const parts = (file.name || '').split('.');
                return parts.length > 1 ? parts.pop().toUpperCase() : 'File';
            }
            return 'Photo';
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
                } else if (kind === 'video') {
                    const wrap = document.createElement('span');
                    wrap.className = 'tt-media-tile__video';
                    const video = document.createElement('video');
                    video.className = 'tt-media-tile__video-el';
                    video.muted = true;
                    video.playsInline = true;
                    video.preload = 'metadata';
                    video.src = objectUrl;
                    const play = document.createElement('span');
                    play.className = 'tt-media-tile__play';
                    play.textContent = '▶';
                    wrap.appendChild(video);
                    wrap.appendChild(play);
                    button.appendChild(wrap);
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
    }

    renderSelected();
}());
</script>
@endpush
