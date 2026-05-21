@extends('layouts.admin')

@section('title', 'Log social media post')
@section('heading', 'Social media post')

@push('styles')
@include('social-media-posts.partials.preview-styles')
<style>
    .smp-shell { display:flex; flex-direction:column; gap:1.25rem; }
    .smp-alert { border-radius:12px; padding:0.85rem 1rem; font-size:0.88rem; }
    .smp-alert--warning { background:#fffbeb; border:1px solid #fde68a; color:#92400e; }
    .smp-alert--success { background:#f0fdf4; border:1px solid #86efac; color:#166534; }
    .smp-alert--error { background:#fef2f2; border:1px solid #fca5a5; color:#991b1b; }
    .smp-alert--error ul { margin:0.35rem 0 0 1rem; }
    .smp-layout { display:grid; grid-template-columns:minmax(0, 1fr) minmax(220px, 320px); gap:1.25rem; align-items:start; }
    @media (max-width: 768px) { .smp-layout { grid-template-columns: 1fr; } }
    .smp-card { background:#fff; border:1px solid #e2e8f0; border-radius:14px; padding:1.25rem 1.35rem; }
    .smp-card__title { margin:0 0 1rem; font-size:1rem; font-weight:700; color:#0f172a; }
    .smp-field { display:flex; flex-direction:column; gap:0.4rem; margin-bottom:1rem; }
    .smp-field label { font-size:0.82rem; font-weight:700; color:#0f172a; }
    .smp-field input[type="text"],
    .smp-field input[type="date"],
    .smp-field input[type="url"],
    .smp-field textarea { width:100%; box-sizing:border-box; border:1px solid #cbd5e1; border-radius:8px; padding:0.58rem 0.7rem; font-size:0.88rem; }
    .smp-field textarea { min-height:4.5rem; resize:vertical; }
    .smp-readonly { background:#f8fafc; color:#64748b; }
    .smp-hint { margin:0.2rem 0 0; color:#64748b; font-size:0.78rem; line-height:1.4; }
    .smp-preview-wrap { display:flex; flex-direction:column; gap:0.5rem; }
    .smp-preview-label { font-size:0.72rem; font-weight:700; text-transform:uppercase; letter-spacing:0.06em; color:#64748b; }
    .smp-preview-loading { font-size:0.75rem; color:#94a3b8; padding:0.25rem 0; }
    .smp-actions { display:flex; flex-wrap:wrap; gap:0.65rem; align-items:center; margin-top:0.5rem; }
    .smp-submit { border:none; border-radius:8px; background:#4f46e5; color:#fff; padding:0.62rem 1rem; font-weight:700; cursor:pointer; font-size:0.88rem; }
    .smp-link { color:#4f46e5; font-weight:700; text-decoration:none; font-size:0.88rem; }
    .smp-platforms { display:flex; flex-wrap:wrap; gap:0.4rem; }
    .smp-platforms--empty { color:#94a3b8; font-size:0.84rem; }
    .smp-platforms__chip {
        display:inline-flex; align-items:center; gap:0.35rem;
        padding:0.28rem 0.55rem; border-radius:999px;
        background:#eef2ff; border:1px solid #c7d2fe; color:#3730a3;
        font-size:0.76rem; font-weight:700;
    }
    .smp-platform-checks {
        display:grid; grid-template-columns:repeat(auto-fill, minmax(9.5rem, 1fr));
        gap:0.45rem; margin-top:0.15rem;
    }
    .smp-platform-check {
        display:flex; align-items:center; gap:0.45rem;
        padding:0.45rem 0.55rem; border:1px solid #e2e8f0; border-radius:8px;
        background:#f8fafc; cursor:pointer; font-size:0.84rem; font-weight:600; color:#0f172a;
    }
    .smp-platform-check input { width:1rem; height:1rem; accent-color:#4f46e5; cursor:pointer; }
    .smp-platform-check:has(input:checked) { background:#eef2ff; border-color:#c7d2fe; }
</style>
@endpush

@section('content')
<div class="smp-shell">
    @if (!empty($migrationMissing))
        <div class="smp-alert smp-alert--warning">
            <strong>Database update required.</strong> Run <code>php artisan migrate</code> for the social media posts tables
            (<code>posted_platforms</code>, preview fields).
        </div>
    @endif

    @if (session('status'))
        <div class="smp-alert smp-alert--success">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="smp-alert smp-alert--error">
            <strong>Please fix:</strong>
            <ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="smp-layout">
        <div class="smp-card">
            <h3 class="smp-card__title">New entry</h3>
            <form method="post" action="{{ route($storeRoute) }}" id="smpForm">
                @csrf
                <div class="smp-field">
                    <label>Submitted by</label>
                    <input type="text" class="smp-readonly" value="{{ $user->name }}" readonly>
                </div>
                <div class="smp-field">
                    <label for="posted_on">Date *</label>
                    <input type="date" id="posted_on" name="posted_on" value="{{ old('posted_on', now()->toDateString()) }}" required>
                </div>
                <div class="smp-field">
                    <label for="post_url">Post URL *</label>
                    <input type="url" id="post_url" name="post_url" value="{{ old('post_url') }}" required placeholder="https://…" inputmode="url" autocomplete="url">
                    <p class="smp-hint">Paste the live link (Instagram, YouTube, Facebook, etc.). Preview loads automatically; Instagram and similar apps cannot be embedded directly.</p>
                </div>
                <div class="smp-field">
                    <label>Posted on platforms *</label>
                    <p class="smp-hint" style="margin-top:0;">Tick every platform where this post was published.</p>
                    <div class="smp-platform-checks">
                        @foreach ($platformOptions as $slug => $label)
                            <label class="smp-platform-check">
                                <input
                                    type="checkbox"
                                    name="posted_platforms[]"
                                    value="{{ $slug }}"
                                    data-platform-slug="{{ $slug }}"
                                    @checked(in_array($slug, old('posted_platforms', []), true))
                                >
                                <span>{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                    @error('posted_platforms')
                        <p class="smp-hint" style="color:#b91c1c;">{{ $message }}</p>
                    @enderror
                </div>
                <div class="smp-field">
                    <label for="description">Short description (optional)</label>
                    <textarea id="description" name="description" maxlength="500" placeholder="Brief note about this post">{{ old('description') }}</textarea>
                </div>
                <div class="smp-actions">
                    <button type="submit" class="smp-submit">Save entry</button>
                    <a href="{{ route($dashboardRoute) }}" class="smp-link">View my entries</a>
                </div>
            </form>
        </div>

        <div class="smp-preview-wrap">
            <span class="smp-preview-label">Live preview</span>
            <p id="smpPreviewLoading" class="smp-preview-loading is-hidden">Loading preview…</p>
            <div class="smp-preview-box" id="smpPreviewBox">
                @include('social-media-posts.partials.preview-panel', [
                    'preview' => ['mode' => 'empty', 'message' => 'Enter a valid URL to preview the post here.'],
                ])
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
(function () {
    const urlInput = document.getElementById('post_url');
    const box = document.getElementById('smpPreviewBox');
    const loading = document.getElementById('smpPreviewLoading');
    const previewUrl = @json(route($previewRoute));
    const csrf = @json(csrf_token());
    let debounceTimer = null;
    let fetchController = null;

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function renderPreview(data) {
        const mode = data.mode || 'empty';
        const platform = escapeHtml(data.platform || '');
        const url = escapeHtml(data.url || '');
        const iframeSrc = escapeHtml(data.iframe_src || '');
        const thumb = escapeHtml(data.thumbnail_url || '');
        const title = escapeHtml(data.title || '');
        const author = escapeHtml(data.author || '');
        const message = escapeHtml(data.message || '');

        let inner = '';

        if (mode === 'iframe' && iframeSrc) {
            inner = `<iframe class="smp-preview-panel__iframe" src="${iframeSrc}" title="Post preview" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>`;
        } else if (mode === 'instagram_embed' && url) {
            inner = `
                <div class="smp-preview-panel__embed" data-instagram-url="${url}">
                    <blockquote class="instagram-media" data-instgrm-captioned data-instgrm-permalink="${url}" data-instgrm-version="14"
                        style="background:#FFF;border:0;border-radius:12px;box-shadow:0 0 1px rgba(0,0,0,.12);margin:0 auto;max-width:100%;min-width:280px;padding:0;width:100%;">
                        <a href="${url}" target="_blank" rel="noopener noreferrer">View this post on Instagram</a>
                    </blockquote>
                </div>
                ${(title || author) ? `<div class="smp-preview-panel__meta">
                    ${platform ? `<span class="smp-preview-panel__platform">${platform}</span>` : ''}
                    ${title ? `<p class="smp-preview-panel__title">${title}</p>` : ''}
                    ${author ? `<p class="smp-preview-panel__author">${author}</p>` : ''}
                </div>` : ''}`;
        } else if (mode === 'thumbnail' && thumb) {
            inner = `
                <a class="smp-preview-panel__thumb-link" href="${url}" target="_blank" rel="noopener noreferrer">
                    <img class="smp-preview-panel__thumb" src="${thumb}" alt="${title || 'Post preview'}" loading="lazy">
                </a>
                <div class="smp-preview-panel__meta">
                    ${platform ? `<span class="smp-preview-panel__platform">${platform}</span>` : ''}
                    ${title ? `<p class="smp-preview-panel__title">${title}</p>` : ''}
                    ${author ? `<p class="smp-preview-panel__author">${author}</p>` : ''}
                </div>`;
        } else if (mode === 'thumbnail' && (title || author)) {
            inner = `<div class="smp-preview-panel__meta" style="flex:1;display:flex;flex-direction:column;justify-content:center;padding:1rem;">
                ${platform ? `<span class="smp-preview-panel__platform">${platform}</span>` : ''}
                ${title ? `<p class="smp-preview-panel__title">${title}</p>` : ''}
                ${author ? `<p class="smp-preview-panel__author">${author}</p>` : ''}
            </div>`;
        } else if (mode === 'card' && url) {
            inner = `
                <div class="smp-preview-panel__card">
                    <span class="smp-preview-panel__platform smp-preview-panel__platform--lg">${platform || 'Social post'}</span>
                    <p class="smp-preview-panel__card-url">${url}</p>
                </div>`;
        } else {
            inner = `<div class="smp-preview-panel__empty"><span>${message || 'Enter a valid URL to preview the post here.'}</span></div>`;
        }

        const footer = (url && mode !== 'empty')
            ? `<div class="smp-preview-panel__footer">
                ${message && mode !== 'iframe' ? `<p class="smp-preview-panel__hint">${message}</p>` : ''}
                <a class="smp-preview-panel__open" href="${url}" target="_blank" rel="noopener noreferrer">Open post in new tab</a>
               </div>`
            : '';

        box.innerHTML = `<div class="smp-preview-panel" data-mode="${mode}">${inner}${footer}</div>`;
        box.classList.toggle('smp-preview-box--embed', mode === 'instagram_embed');
        if (mode === 'instagram_embed' && window.smpMountInstagramEmbeds) {
            window.smpMountInstagramEmbeds(box);
        }
        suggestPlatformFromPreview(data.platform || '');
    }

    function suggestPlatformFromPreview(platformLabel) {
        const slugMap = {
            Instagram: 'instagram',
            YouTube: 'youtube',
            Facebook: 'facebook',
            LinkedIn: 'linkedin',
            X: 'x',
            Threads: 'threads',
        };
        const slug = slugMap[platformLabel] || null;
        if (!slug) {
            return;
        }
        const input = document.querySelector(`input[data-platform-slug="${slug}"]`);
        if (input && !input.checked) {
            input.checked = true;
        }
    }

    async function updatePreview() {
        const raw = (urlInput?.value || '').trim();
        if (fetchController) {
            fetchController.abort();
        }

        if (!raw) {
            loading?.classList.add('is-hidden');
            renderPreview({ mode: 'empty', message: 'Enter a valid URL to preview the post here.' });
            return;
        }

        loading?.classList.remove('is-hidden');
        fetchController = new AbortController();

        try {
            const endpoint = previewUrl + '?url=' + encodeURIComponent(raw);
            const res = await fetch(endpoint, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                signal: fetchController.signal,
            });
            if (!res.ok) {
                renderPreview({ mode: 'card', platform: 'Link', url: raw, message: 'Could not load preview. Open the link to verify.' });
                return;
            }
            const data = await res.json();
            renderPreview(data);
        } catch (err) {
            if (err.name !== 'AbortError') {
                renderPreview({ mode: 'card', platform: 'Link', url: raw, message: 'Preview unavailable. Open the link to view the post.' });
            }
        } finally {
            loading?.classList.add('is-hidden');
        }
    }

    urlInput?.addEventListener('input', function () {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(updatePreview, 500);
    });
    urlInput?.addEventListener('change', updatePreview);

    if ((urlInput?.value || '').trim()) {
        updatePreview();
    }
})();
</script>
@include('social-media-posts.partials.preview-script')
@endpush
@endsection
