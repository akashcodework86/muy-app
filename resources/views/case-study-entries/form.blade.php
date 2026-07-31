@extends('layouts.admin')

@section('title', \App\Models\CaseStudyEntry::MODULE_LABEL)
@section('heading', \App\Models\CaseStudyEntry::MODULE_LABEL)

@push('styles')
@include('branding-communication.partials.form-styles')
@endpush

@section('content')
<div class="bc-shell">
    @if (!empty($migrationMissing))
        <div class="bc-alert bc-alert--warning">Run <code>php artisan migrate</code> for <code>case_study_entries</code>.</div>
    @endif
    @if (session('status'))<div class="bc-alert bc-alert--success">{{ session('status') }}</div>@endif
    @if ($errors->any())
        <div class="bc-alert bc-alert--error"><strong>Please fix:</strong><ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif

    <div class="bc-card">
        <h3 class="bc-card__title">New case study / testimonial</h3>
        <form method="post" action="{{ route($storeRoute) }}" enctype="multipart/form-data" id="cseForm">
            @csrf
            <div class="bc-grid">
                <div class="bc-field">
                    <label>Submitted by</label>
                    <input type="text" class="bc-readonly" value="{{ $user->name }}" readonly>
                </div>
                <div class="bc-field">
                    <label for="story_date">Date <span class="bc-req">*</span></label>
                    <input type="date" id="story_date" name="story_date" value="{{ old('story_date', now()->toDateString()) }}" required>
                </div>
                <div class="bc-field bc-field--full">
                    <label for="story_title">Story title <span class="bc-req">*</span></label>
                    <input type="text" id="story_title" name="story_title" maxlength="255" value="{{ old('story_title') }}" required>
                </div>
                <div class="bc-field">
                    <label for="story_type">Story type <span class="bc-req">*</span></label>
                    <select id="story_type" name="story_type" required>
                        <option value="">— Select —</option>
                        @foreach ($storyTypes as $value => $label)
                            <option value="{{ $value }}" @selected(old('story_type') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="bc-field bc-field--full">
                    @include('branding-communication.partials.incubatee-single-picker')
                </div>
                <div class="bc-field bc-field--full">
                    <label for="cseDocumentsInput">Document <span class="bc-req">*</span></label>
                    <input id="cseDocumentsInput" type="file" name="documents[]"
                        accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.webp,image/*,application/pdf"
                        multiple required>
                    <p class="bc-hint">PDF, Word, or images (JPG/PNG/WebP). Min 1, max {{ $maxAttachments }} files, 20 MB each. Preview below before submit.</p>
                    <div id="cseDocumentsPreview" class="bc-media-preview" aria-live="polite"></div>
                </div>
                <div class="bc-field bc-field--full">
                    <label for="remarks">Short note</label>
                    <textarea id="remarks" name="remarks" maxlength="5000">{{ old('remarks') }}</textarea>
                </div>
            </div>
            <div class="bc-actions">
                <button type="submit" class="bc-submit">Save entry</button>
                <a href="{{ route($dashboardRoute) }}" class="bc-link">View dashboard</a>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
@include('branding-communication.partials.incubatee-single-picker-script', ['searchRoute' => $searchRoute])
<script>
(function () {
    const input = document.getElementById('cseDocumentsInput');
    const preview = document.getElementById('cseDocumentsPreview');
    const maxFiles = {{ (int) $maxAttachments }};
    let selectedFiles = [];
    let objectUrls = [];

    function revokeUrls() {
        objectUrls.forEach(function (url) { URL.revokeObjectURL(url); });
        objectUrls = [];
    }

    function fileLabel(file) {
        const name = file.name || 'File';
        const ext = (name.split('.').pop() || '').toUpperCase();
        if (file.type.startsWith('image/')) {
            return name;
        }
        if (ext === 'PDF') return 'PDF';
        if (ext === 'DOC' || ext === 'DOCX') return 'Word';
        return ext || 'File';
    }

    function renderPreview() {
        if (!preview) return;
        revokeUrls();
        preview.innerHTML = '';

        selectedFiles.forEach(function (file, idx) {
            const wrap = document.createElement('div');
            wrap.style.cssText = 'position:relative;';

            if (file.type.startsWith('image/')) {
                const img = document.createElement('img');
                img.className = 'bc-media-thumb';
                const url = URL.createObjectURL(file);
                objectUrls.push(url);
                img.src = url;
                img.alt = file.name || ('Image ' + (idx + 1));
                wrap.appendChild(img);
            } else {
                const badge = document.createElement('div');
                badge.style.cssText = 'width:72px;height:72px;border-radius:8px;border:1px solid #e2e8f0;background:#f8fafc;display:flex;align-items:center;justify-content:center;font-size:0.65rem;font-weight:700;color:#475569;text-align:center;padding:0.25rem;word-break:break-word;';
                badge.title = file.name || '';
                badge.textContent = fileLabel(file);
                wrap.appendChild(badge);
            }

            const name = document.createElement('div');
            name.style.cssText = 'max-width:72px;margin-top:0.25rem;font-size:0.62rem;color:#64748b;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;';
            name.title = file.name || '';
            name.textContent = file.name || ('File ' + (idx + 1));
            wrap.appendChild(name);

            const remove = document.createElement('button');
            remove.type = 'button';
            remove.textContent = '×';
            remove.setAttribute('aria-label', 'Remove ' + (file.name || 'file'));
            remove.style.cssText = 'position:absolute;top:-6px;right:-6px;width:20px;height:20px;border-radius:999px;border:none;background:#b91c1c;color:#fff;font-weight:700;cursor:pointer;line-height:1;';
            remove.addEventListener('click', function () {
                selectedFiles.splice(idx, 1);
                syncInput();
                renderPreview();
            });
            wrap.appendChild(remove);
            preview.appendChild(wrap);
        });
    }

    function syncInput() {
        if (!input || typeof DataTransfer === 'undefined') return;
        const dt = new DataTransfer();
        selectedFiles.forEach(function (f) { dt.items.add(f); });
        input.files = dt.files;
        input.required = selectedFiles.length === 0;
    }

    input?.addEventListener('change', function () {
        const incoming = Array.from(input.files || []);
        // Merge newly picked files with existing selection (allows adding more).
        const merged = selectedFiles.concat(incoming);
        const unique = [];
        const seen = new Set();
        merged.forEach(function (file) {
            const key = [file.name, file.size, file.lastModified].join(':');
            if (seen.has(key)) return;
            seen.add(key);
            unique.push(file);
        });

        if (unique.length > maxFiles) {
            alert('Maximum ' + maxFiles + ' files allowed.');
            selectedFiles = unique.slice(0, maxFiles);
        } else {
            selectedFiles = unique;
        }
        syncInput();
        renderPreview();
    });
})();
</script>
@endpush
