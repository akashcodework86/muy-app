@extends('layouts.admin')

@section('title', \App\Models\MediaCampaignEntry::MODULE_LABEL)
@section('heading', \App\Models\MediaCampaignEntry::MODULE_LABEL)

@push('styles')
@include('branding-communication.partials.form-styles')
@endpush

@section('content')
<div class="bc-shell">
    @if (!empty($migrationMissing))<div class="bc-alert bc-alert--warning">Run <code>php artisan migrate</code>.</div>@endif
    @if (session('status'))<div class="bc-alert bc-alert--success">{{ session('status') }}</div>@endif
    @if ($errors->any())<div class="bc-alert bc-alert--error"><ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

    <div class="bc-card">
        <h3 class="bc-card__title">New IEC &amp; Promotional Activities for MUY</h3>
        <form method="post" action="{{ route($storeRoute) }}" enctype="multipart/form-data" id="mcForm">
            @csrf
            <div class="bc-grid">
                <div class="bc-field">
                    <label>Submitted by</label>
                    <input type="text" class="bc-readonly" value="{{ $user->name }}" readonly>
                </div>
                <div class="bc-field">
                    <label for="campaign_date">Campaign date <span class="bc-req">*</span></label>
                    <x-activity-date-input name="campaign_date" id="campaign_date" />
                </div>
                <div class="bc-field">
                    <label for="media_type">Media type <span class="bc-req">*</span></label>
                    <select id="media_type" name="media_type" required>
                        <option value="">— Select —</option>
                        @foreach ($mediaTypes as $value => $label)
                            <option value="{{ $value }}" @selected(old('media_type') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="bc-field">
                    <label for="channel_name">Publication / channel <span class="bc-req">*</span></label>
                    <input type="text" id="channel_name" name="channel_name" maxlength="255" value="{{ old('channel_name') }}" required>
                </div>
                <div class="bc-field">
                    <label for="coverage_area">District / coverage <span class="bc-req">*</span></label>
                    <input type="text" id="coverage_area" name="coverage_area" maxlength="191" value="{{ old('coverage_area') }}" required>
                </div>
                <div class="bc-field">
                    <label for="ad_size_or_duration">Ad size / slot duration</label>
                    <input type="text" id="ad_size_or_duration" name="ad_size_or_duration" maxlength="128" value="{{ old('ad_size_or_duration') }}" placeholder="e.g. Half page / 30 sec">
                </div>
                <div class="bc-field bc-field--full">
                    <label for="campaign_title">Campaign topic / title <span class="bc-req">*</span></label>
                    <input type="text" id="campaign_title" name="campaign_title" maxlength="255" value="{{ old('campaign_title') }}" required>
                </div>
                <div class="bc-field bc-field--full">
                    <label for="document">Document upload <span class="bc-req">*</span></label>
                    <input type="file" id="document" name="document" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" required>
                    <p class="bc-hint">Clipping scan, approval letter, or script (PDF/DOC/image), max 20 MB.</p>
                </div>
                <div class="bc-field bc-field--full">
                    <label for="mcMultimediaInput">Live multimedia upload <span class="bc-req">*</span></label>
                    <input id="mcMultimediaInput" type="file" name="multimedia[]"
                        accept=".jpg,.jpeg,.png,.webp,.mp3,.m4a,.wav,.mp4,image/*,audio/*,video/*"
                        capture="environment" multiple required>
                    <p class="bc-hint">Upload photos, audio, or video proof (min 1, max {{ $maxAttachments }}). Preview below before submit.</p>
                    <div id="mcMultimediaPreview" class="bc-media-preview"></div>
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
<script>
(function () {
    const input = document.getElementById('mcMultimediaInput');
    const preview = document.getElementById('mcMultimediaPreview');
    const maxFiles = {{ (int) $maxAttachments }};
    let selectedFiles = [];

    function renderPreview() {
        if (!preview) return;
        preview.innerHTML = '';
        selectedFiles.forEach(function (file, idx) {
            const wrap = document.createElement('div');
            wrap.style.cssText = 'position:relative;';
            if (file.type.startsWith('image/')) {
                const img = document.createElement('img');
                img.className = 'bc-media-thumb';
                img.src = URL.createObjectURL(file);
                wrap.appendChild(img);
            } else {
                const badge = document.createElement('div');
                badge.style.cssText = 'width:72px;height:72px;border-radius:8px;border:1px solid #e2e8f0;background:#f8fafc;display:flex;align-items:center;justify-content:center;font-size:0.7rem;font-weight:700;color:#475569;text-align:center;padding:0.25rem;';
                badge.textContent = file.type.startsWith('audio/') ? 'Audio' : (file.type.startsWith('video/') ? 'Video' : 'File');
                wrap.appendChild(badge);
            }
            const remove = document.createElement('button');
            remove.type = 'button';
            remove.textContent = '×';
            remove.style.cssText = 'position:absolute;top:-6px;right:-6px;width:20px;height:20px;border-radius:999px;border:none;background:#b91c1c;color:#fff;font-weight:700;cursor:pointer;';
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
    }

    input?.addEventListener('change', function () {
        const incoming = Array.from(input.files || []);
        selectedFiles = incoming.slice(0, maxFiles);
        if (incoming.length > maxFiles) {
            alert('Maximum ' + maxFiles + ' multimedia files allowed.');
        }
        syncInput();
        renderPreview();
    });
})();
</script>
@endpush
