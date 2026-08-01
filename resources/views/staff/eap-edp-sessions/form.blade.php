@extends('layouts.admin')

@section('title', 'EAP / EDP sessions')
@section('heading', 'EAP / EDP sessions (combined)')

@include('staff.eap-edp-sessions.partials.photo-upload-scripts')

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
    .tp-field input[type="number"],
    .tp-field input[type="file"],
    .tp-field textarea { width:100%; box-sizing:border-box; border:1px solid #cbd5e1; border-radius:8px; padding:0.58rem 0.7rem; font-size:0.88rem; background:#fff; }
    .tp-field textarea { min-height:5.5rem; resize:vertical; }
    .tp-field input[type="file"] { padding:0.45rem 0.55rem; background:#f8fafc; }
    .tp-readonly { background:#f8fafc; color:#64748b; }
    .tp-field-hint { margin:0.2rem 0 0; color:#64748b; font-size:0.78rem; line-height:1.4; }
    .tp-media-preview { margin-top:0.55rem; }
    .tp-actions { margin-top:1.25rem; display:flex; flex-wrap:wrap; gap:0.65rem; align-items:center; }
    .tp-submit { border:none; border-radius:8px; background:#4f46e5; color:#fff; padding:0.62rem 1rem; font-weight:700; cursor:pointer; font-size:0.88rem; }
    .tp-link { color:#4f46e5; font-weight:700; text-decoration:none; font-size:0.88rem; }
    .ees-att-grid { display:grid; grid-template-columns:repeat(3, minmax(0, 1fr)); gap:0.85rem 1rem; }
    @media (max-width:640px) { .ees-att-grid { grid-template-columns:1fr; } }
    .ees-att-total input { font-weight:800; color:#0f172a; letter-spacing:0.02em; }
    .tp-req { color:#e11d48; margin-left:1px; }
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

    <div class="tp-card">
        <h3 class="tp-card__title">Submission Form</h3>
        <form id="eesWorkshopForm" method="post" action="{{ route('staff.eap-edp-sessions.store') }}" enctype="multipart/form-data">
            @csrf
            <div class="tp-grid">
                <div class="tp-field">
                    <label>Session Taken By</label>
                    <input type="text" class="tp-readonly" value="{{ $user->name }}" readonly>
                </div>
                <div class="tp-field">
                    <label>Date of Session <span class="tp-req">*</span></label>
                    <x-activity-date-input name="session_date" />
                </div>
                <div class="tp-field">
                    <label>District</label>
                    <input type="text" class="tp-readonly" value="{{ $user->district?->name ?? 'Not assigned' }}" readonly>
                </div>
                @include('staff.partials.workshop-participants.location-fields', [
                    'user' => $user,
                    'blockRows' => $blockRows ?? collect(),
                    'gramPanchayatsEnabled' => $gramPanchayatsEnabled ?? false,
                    'defaultBlockId' => $defaultBlockId ?? 0,
                    'defaultGpId' => $defaultGpId ?? 0,
                ])
                @include('staff.eap-edp-sessions.partials.workshop-mode-field', ['selected' => null])
                <div class="tp-field tp-field--full">
                    <label>Venue address <span class="tp-req">*</span></label>
                    <textarea name="venue_name_address" maxlength="5000" required placeholder="Enter venue name and full address">{{ old('venue_name_address') }}</textarea>
                </div>
                <div class="tp-field tp-field--full">
                    <label>Notes (optional)</label>
                    <textarea name="notes" maxlength="5000" placeholder="Optional notes">{{ old('notes') }}</textarea>
                </div>
            </div>

            <div class="tp-section">
                <h4 class="tp-section__title">Attendance (headcount) <span class="tp-req">*</span></h4>
                <p class="tp-field-hint" style="margin:0 0 0.75rem;">Open sessions: record how many participants attended by gender. Total updates automatically.</p>
                <div class="ees-att-grid">
                    <div class="tp-field">
                        <label for="attendance_male_count">No. of male <span class="tp-req">*</span></label>
                        <input id="attendance_male_count" type="number" name="attendance_male_count" min="0" step="1" value="{{ old('attendance_male_count', 0) }}" required inputmode="numeric">
                    </div>
                    <div class="tp-field">
                        <label for="attendance_female_count">No. of female <span class="tp-req">*</span></label>
                        <input id="attendance_female_count" type="number" name="attendance_female_count" min="0" step="1" value="{{ old('attendance_female_count', 0) }}" required inputmode="numeric">
                    </div>
                    <div class="tp-field ees-att-total">
                        <label for="eesAttendanceTotalDisplay">Total attendance</label>
                        <input id="eesAttendanceTotalDisplay" type="text" class="tp-readonly" value="0" readonly aria-live="polite">
                    </div>
                </div>
            </div>

            @include('staff.partials.workshop-participants.registry')

            <div class="tp-section">
                <div class="tp-field tp-field--full">
                    <label>Session photos <span class="tp-req">*</span></label>
                    <input id="eesSessionPhotosInput" type="file" name="session_photos[]" accept="image/*,.jpg,.jpeg,.png,.webp,.gif,.heic,.heif" multiple required>
                    <p class="tp-field-hint">Upload at least one photo from the session. JPG, PNG, or WebP, up to 25 photos (10 MB each). Click × on a thumbnail to remove before submitting.</p>
                    <div id="eesSessionPhotosPreview"></div>
                </div>
            </div>

            <div class="tp-section">
                <div class="tp-field tp-field--full">
                    <label>Upload attendance sheet (can be upload later)</label>
                    <input id="tpMediaInput" type="file" name="attendance_media[]" accept=".pdf,.jpg,.jpeg,.png,.webp,.mp4,.mov,.avi,.mkv,.doc,.docx,.xls,.xlsx" multiple>
                    <p class="tp-field-hint">Optional for now — you can upload later from Edit. PDF, images, Word, Excel, or short video clips — up to 25 files, 50 MB each.</p>
                    <div id="tpMediaPreview" class="tp-media-preview"></div>
                </div>
            </div>

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

@include('staff.partials.workshop-participants.script', [
    'maleInputId' => 'attendance_male_count',
    'femaleInputId' => 'attendance_female_count',
    'formId' => 'eesWorkshopForm',
    'gramPanchayatsUrl' => route('staff.eap-edp-sessions.gram-panchayats'),
    'districtLabel' => $districtLabel ?? ($user->district?->name ?? '—'),
    'initialRows' => $initialRows ?? [],
    'defaultBlockId' => $defaultBlockId ?? 0,
    'defaultGpId' => $defaultGpId ?? 0,
])

@push('scripts')
<script>
(function () {
    const maleEl = document.getElementById('attendance_male_count');
    const femaleEl = document.getElementById('attendance_female_count');
    const totalEl = document.getElementById('eesAttendanceTotalDisplay');
    const mediaInput = document.getElementById('tpMediaInput');
    const mediaPreview = document.getElementById('tpMediaPreview');

    function syncTotal() {
        if (!totalEl) return;
        const m = parseInt(String(maleEl && maleEl.value !== '' ? maleEl.value : '0'), 10) || 0;
        const f = parseInt(String(femaleEl && femaleEl.value !== '' ? femaleEl.value : '0'), 10) || 0;
        totalEl.value = String(m + f);
    }

    if (maleEl) maleEl.addEventListener('input', syncTotal);
    if (femaleEl) femaleEl.addEventListener('input', syncTotal);
    syncTotal();

    if (mediaInput && mediaPreview) {
        const selectedFiles = new DataTransfer();
        const previewUrls = new Map();

        const detectKind = function (file) {
            const type = file.type || '';
            const name = (file.name || '').toLowerCase();
            if (type.startsWith('image/')) return 'image';
            if (type.startsWith('video/')) return 'video';
            if (type === 'application/pdf' || name.endsWith('.pdf')) return 'pdf';
            return 'file';
        };

        const fileLabel = function (kind, file) {
            if (kind === 'pdf') return 'PDF';
            if (kind === 'video') return 'Video';
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
            if (!files.length) return;

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
                if (!alreadySelected) selectedFiles.items.add(file);
            });
            syncMediaInput();
            renderPendingPreview();
        };

        mediaInput.addEventListener('change', function () {
            addFiles(this.files);
        });
    }
}());
</script>
@endpush
