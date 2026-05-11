@extends('layouts.admin')

@section('title', 'Technical training to incubatees')
@section('heading', 'Technical training to incubatees')

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
    .tp-media-preview { display:flex; flex-wrap:wrap; gap:0.55rem; margin-top:0.55rem; }
    .tp-media-preview img { width:96px; height:72px; object-fit:cover; border-radius:8px; border:1px solid #e2e8f0; }
    .tp-media-chip { display:inline-flex; align-items:center; padding:0.35rem 0.55rem; border-radius:8px; border:1px solid #cbd5e1; background:#f8fafc; font-size:0.78rem; color:#334155; }
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
            <strong>Technical trainings table not found.</strong> Run <code>php artisan migrate</code> first.
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
        <form method="post" action="{{ route('staff.technical-trainings.store') }}" enctype="multipart/form-data">
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
                <div class="tp-field">
                    <label>Training Batch (optional custom)</label>
                    <input type="text" name="training_batch_name" value="{{ old('training_batch_name') }}" placeholder="Optional batch text">
                </div>
                <div class="tp-field tp-field--full">
                    <label>Technical training session name *</label>
                    <input type="text" name="session_name" value="{{ old('session_name') }}" maxlength="191" required placeholder="Session name">
                </div>
                <div class="tp-field tp-field--full">
                    <label>Session brief (optional)</label>
                    <textarea name="session_brief" maxlength="5000" placeholder="Short description of the session">{{ old('session_brief') }}</textarea>
                </div>
            </div>

            <div class="tp-section">
                <div class="tp-field">
                    <label>Uploaded attendance sheet (optional)</label>
                    <input id="tpMediaInput" type="file" name="attendance_media[]" accept=".pdf,.jpg,.jpeg,.png,.webp,.mp4,.mov,.avi,.mkv,.doc,.docx,.xls,.xlsx" multiple>
                    <p class="tp-field-hint">Each file can be up to 50 MB. PDF, image, video, Word, and Excel files are accepted.</p>
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
                            @forelse ($incubatees as $row)
                                <label class="tp-item" data-search="{{ strtolower($row['name'].' '.$row['application_no'].' '.$row['phone']) }}">
                                    <input type="checkbox" class="tp-check" value="{{ $row['incubatee_id'] }}" @checked(in_array((int) $row['incubatee_id'], $oldSelectedIds, true))>
                                    <div>
                                        <h4>{{ $row['name'] ?: 'Unnamed' }}</h4>
                                        <div class="tp-meta">
                                            <span class="tp-pill">App: {{ $row['application_no'] ?: 'NA' }}</span>
                                            <span class="tp-pill">Batch: {{ $row['onboarding_batch_name'] ?: 'NA' }}</span>
                                        </div>
                                        <div class="tp-meta">Phone: {{ $row['phone'] ?: 'NA' }} | Block: {{ $row['block_name'] ?: 'NA' }} | Village: {{ $row['village'] ?: 'NA' }}</div>
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
                <a class="tp-link" href="{{ route('staff.technical-trainings.dashboard') }}">View dashboard</a>
            </div>
        </form>
    </div>
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
        mediaInput.addEventListener('change', function () {
            mediaPreview.innerHTML = '';
            const files = Array.from(this.files || []);
            if (!files.length) {
                return;
            }
            files.forEach((file) => {
                if ((file.type || '').startsWith('image/')) {
                    const img = document.createElement('img');
                    img.alt = file.name;
                    img.src = URL.createObjectURL(file);
                    mediaPreview.appendChild(img);
                } else {
                    const chip = document.createElement('span');
                    chip.className = 'tp-media-chip';
                    chip.textContent = file.name;
                    mediaPreview.appendChild(chip);
                }
            });
        });
    }

    renderSelected();
}());
</script>
@endpush
