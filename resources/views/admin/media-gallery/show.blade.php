@extends('layouts.admin')

@section('title', $album['title'].' — Media Gallery')
@section('heading', $album['title'])

@push('styles')
<style>
    .mg { --mg-ink:#0f172a; --mg-muted:#64748b; --mg-line:#e2e8f0; --mg-accent:#0f766e; --mg-accent-2:#134e4a; font-family:'DM Sans',system-ui,sans-serif; color:var(--mg-ink); }
    .mg-crumb { display:flex; flex-wrap:wrap; gap:.4rem; align-items:center; font-size:.82rem; color:var(--mg-muted); margin-bottom:.85rem; }
    .mg-crumb a { color:var(--mg-accent); text-decoration:none; font-weight:600; }
    .mg-hero { display:flex; flex-wrap:wrap; gap:1rem; align-items:flex-start; justify-content:space-between; margin-bottom:1.15rem; }
    .mg-hero h1 { margin:0 0 .3rem; font-size:1.35rem; font-weight:800; letter-spacing:-.02em; }
    .mg-hero p { margin:0; color:var(--mg-muted); font-size:.88rem; }
    .mg-desc { margin:.45rem 0 0; color:#334155; font-size:.9rem; }
    .mg-brief { margin:.7rem 0 0; max-width:48rem; padding:.75rem .9rem; background:#f8fafc; border:1px solid var(--mg-line); border-radius:12px; }
    .mg-brief__label { display:block; font-size:.68rem; font-weight:800; letter-spacing:.06em; text-transform:uppercase; color:#0f766e; margin-bottom:.28rem; }
    .mg-brief__text { margin:0; white-space:pre-wrap; font-size:.88rem; line-height:1.5; color:#1e293b; }
    .mg-actions { display:flex; flex-wrap:wrap; gap:.45rem; }
    .mg-btn { display:inline-flex; align-items:center; gap:.35rem; padding:.5rem .9rem; border:none; border-radius:8px; background:var(--mg-accent); color:#fff; font:inherit; font-size:.84rem; font-weight:700; cursor:pointer; text-decoration:none; white-space:nowrap; }
    .mg-btn:hover { background:var(--mg-accent-2); color:#fff; }
    .mg-btn--ghost { background:transparent; color:var(--mg-accent); border:1px solid var(--mg-accent); }
    .mg-btn:disabled { opacity:.55; cursor:not-allowed; }
    .mg-selectbar { position:sticky; top:.75rem; z-index:20; display:none; align-items:center; justify-content:space-between; gap:.75rem; flex-wrap:wrap; margin-bottom:.85rem; padding:.65rem .85rem; background:#0f766e; color:#fff; border-radius:12px; box-shadow:0 10px 24px rgba(15,118,110,.28); }
    .mg-selectbar.is-on { display:flex; }
    .mg-selectbar__count { font-size:.88rem; font-weight:800; }
    .mg-selectbar .mg-btn { background:#fff; color:#0f766e; }
    .mg-selectbar .mg-btn--ghost { background:transparent; color:#fff; border-color:rgba(255,255,255,.45); }
    .mg-masonry { column-count:4; column-gap:.55rem; }
    @media (max-width:1100px) { .mg-masonry { column-count:3; } }
    @media (max-width:760px) { .mg-masonry { column-count:2; } }
    @media (max-width:420px) { .mg-masonry { column-count:1; } }
    .mg-shot { break-inside:avoid; margin:0 0 .55rem; position:relative; border-radius:12px; overflow:hidden; background:#e2e8f0; cursor:zoom-in; }
    .mg-shot.is-selected { outline:3px solid var(--mg-accent); outline-offset:1px; }
    .mg-shot img { width:100%; display:block; vertical-align:middle; }
    .mg-shot__pick { position:absolute; top:.45rem; left:.45rem; z-index:2; width:1.35rem; height:1.35rem; margin:0; accent-color:var(--mg-accent); cursor:pointer; }
    .mg-shot__bar { position:absolute; inset:auto 0 0 0; display:flex; justify-content:flex-end; gap:.35rem; padding:.45rem; background:linear-gradient(180deg,transparent,rgba(15,23,42,.7)); opacity:0; transition:opacity .15s ease; }
    .mg-shot:hover .mg-shot__bar, .mg-shot:focus-within .mg-shot__bar, .mg-shot.is-selected .mg-shot__bar { opacity:1; }
    .mg-shot__dl { display:inline-flex; align-items:center; justify-content:center; min-width:2rem; height:2rem; padding:0 .45rem; border:none; border-radius:999px; background:rgba(255,255,255,.92); color:#0f172a; text-decoration:none; font-size:.72rem; font-weight:800; cursor:pointer; }
    .mg-shot__dl:hover { background:#fff; }
    .mg-empty { padding:2.5rem 1rem; text-align:center; color:var(--mg-muted); background:#fff; border:1px dashed var(--mg-line); border-radius:14px; }
    .mg-toast { position:fixed; bottom:1.25rem; left:50%; transform:translateX(-50%) translateY(120%); z-index:1300; background:#0f172a; color:#fff; padding:.55rem 1rem; border-radius:999px; font-size:.82rem; font-weight:700; box-shadow:0 10px 30px rgba(15,23,42,.28); transition:transform .2s ease; pointer-events:none; }
    .mg-toast.is-on { transform:translateX(-50%) translateY(0); }

    .mg-lb { position:fixed; inset:0; z-index:1200; display:none; background:rgba(2,6,23,.92); color:#fff; }
    .mg-lb.is-open { display:flex; flex-direction:column; }
    .mg-lb__top { display:flex; align-items:center; justify-content:space-between; gap:.75rem; padding:.75rem 1rem; }
    .mg-lb__meta { font-size:.85rem; color:#cbd5e1; min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .mg-lb__tools { display:flex; gap:.4rem; flex-shrink:0; }
    .mg-lb__btn { appearance:none; border:1px solid rgba(255,255,255,.25); background:rgba(255,255,255,.08); color:#fff; border-radius:8px; padding:.45rem .75rem; font:inherit; font-size:.82rem; font-weight:700; cursor:pointer; text-decoration:none; }
    .mg-lb__btn:hover { background:rgba(255,255,255,.16); color:#fff; }
    .mg-lb__stage { flex:1; display:flex; align-items:center; justify-content:center; position:relative; min-height:0; padding:0 3.5rem 1.25rem; }
    .mg-lb__img { max-width:100%; max-height:100%; object-fit:contain; border-radius:8px; box-shadow:0 20px 50px rgba(0,0,0,.45); }
    .mg-lb__nav { position:absolute; top:50%; transform:translateY(-50%); width:2.6rem; height:2.6rem; border-radius:999px; border:1px solid rgba(255,255,255,.25); background:rgba(15,23,42,.55); color:#fff; font-size:1.4rem; cursor:pointer; display:grid; place-items:center; }
    .mg-lb__nav--prev { left:.75rem; }
    .mg-lb__nav--next { right:.75rem; }
    .mg-lb__nav:hover { background:rgba(15,23,42,.85); }
</style>
@endpush

@section('content')
<div class="mg">
    <nav class="mg-crumb" aria-label="Breadcrumb">
        <a href="{{ route('admin.media-gallery.index') }}">Media Gallery</a>
        <span>/</span>
        <a href="{{ route('admin.media-gallery.section', $sectionKey) }}">{{ $section['label'] }}</a>
        <span>/</span>
        <span>{{ $album['title'] }}</span>
    </nav>

    <div class="mg-hero">
        <div>
            <h1>{{ $album['title'] }}</h1>
            <p>{{ $album['district'] }} · {{ $album['date'] }} · {{ count($photos) }} photos</p>
            @if (($album['description'] ?? '') !== '')
                <p class="mg-desc">{{ $album['description'] }}</p>
            @endif
            @if (($album['briefing'] ?? '') !== '')
                <div class="mg-brief">
                    <span class="mg-brief__label">About this event</span>
                    <p class="mg-brief__text">{{ $album['briefing'] }}</p>
                </div>
            @endif
        </div>
        <div class="mg-actions">
            @if (count($photos) > 0)
                <button type="button" class="mg-btn mg-btn--ghost" id="mg-select-all">Select all</button>
                <a class="mg-btn" href="{{ route('admin.media-gallery.zip', [$sectionKey, $album['id']]) }}">Download all</a>
            @endif
            <a class="mg-btn mg-btn--ghost" href="{{ $album['detail_url'] }}" target="_blank" rel="noopener">Open record</a>
        </div>
    </div>

    <div class="mg-selectbar" id="mg-selectbar">
        <span class="mg-selectbar__count" id="mg-select-count">0 selected</span>
        <div class="mg-actions">
            <button type="button" class="mg-btn" id="mg-copy-selected">Copy</button>
            <button type="button" class="mg-btn" id="mg-download-selected">Download selected</button>
            <button type="button" class="mg-btn mg-btn--ghost" id="mg-clear-selected">Clear</button>
        </div>
    </div>

    @if (count($photos) === 0)
        <div class="mg-empty">No viewable photos in this album.</div>
    @else
        <div class="mg-masonry" id="mg-gallery"
             data-zip="{{ route('admin.media-gallery.zip', [$sectionKey, $album['id']]) }}"
             data-photos='@json($photos)'>
            @foreach ($photos as $i => $photo)
                <figure class="mg-shot" id="photo-{{ $i }}" data-index="{{ $i }}" tabindex="0" role="button" aria-label="Open photo {{ $i + 1 }}">
                    <input class="mg-shot__pick" type="checkbox" data-index="{{ $i }}" aria-label="Select photo {{ $i + 1 }}">
                    <img src="{{ $photo['thumb_url'] }}" alt="{{ $photo['name'] }}" loading="lazy" decoding="async">
                    <div class="mg-shot__bar">
                        <button type="button" class="mg-shot__dl" data-copy="{{ $i }}" title="Copy original">Copy</button>
                        <a class="mg-shot__dl" href="{{ $photo['download_url'] }}" download title="Download original">↓</a>
                    </div>
                </figure>
            @endforeach
        </div>
    @endif
</div>

<div class="mg-lb" id="mg-lightbox" aria-hidden="true">
    <div class="mg-lb__top">
        <div class="mg-lb__meta" id="mg-lb-meta">Photo</div>
        <div class="mg-lb__tools">
            <button type="button" class="mg-lb__btn" id="mg-lb-copy">Copy original</button>
            <a class="mg-lb__btn" id="mg-lb-download" href="#" download>Download</a>
            <button type="button" class="mg-lb__btn" id="mg-lb-close">Close</button>
        </div>
    </div>
    <div class="mg-lb__stage">
        <button type="button" class="mg-lb__nav mg-lb__nav--prev" id="mg-lb-prev" aria-label="Previous">‹</button>
        <img class="mg-lb__img" id="mg-lb-img" src="" alt="">
        <button type="button" class="mg-lb__nav mg-lb__nav--next" id="mg-lb-next" aria-label="Next">›</button>
    </div>
</div>
<div class="mg-toast" id="mg-toast" role="status"></div>
@endsection

@push('scripts')
<script>
(() => {
    const root = document.getElementById('mg-gallery');
    if (!root) return;

    let photos = [];
    try { photos = JSON.parse(root.dataset.photos || '[]'); } catch (_) { photos = []; }
    if (!Array.isArray(photos) || photos.length === 0) return;

    const zipBase = root.dataset.zip || '';
    const lb = document.getElementById('mg-lightbox');
    const img = document.getElementById('mg-lb-img');
    const meta = document.getElementById('mg-lb-meta');
    const dl = document.getElementById('mg-lb-download');
    const bar = document.getElementById('mg-selectbar');
    const countEl = document.getElementById('mg-select-count');
    const toast = document.getElementById('mg-toast');
    const selected = new Set();
    let index = 0;
    let toastTimer = 0;

    const showToast = (message) => {
        if (!toast) return;
        toast.textContent = message;
        toast.classList.add('is-on');
        window.clearTimeout(toastTimer);
        toastTimer = window.setTimeout(() => toast.classList.remove('is-on'), 2400);
    };

    const selectedIndices = () => Array.from(selected).sort((a, b) => a - b);

    const syncSelection = () => {
        const n = selected.size;
        bar?.classList.toggle('is-on', n > 0);
        if (countEl) countEl.textContent = `${n} selected`;
        root.querySelectorAll('.mg-shot').forEach((el) => {
            const i = Number(el.dataset.index || 0);
            const on = selected.has(i);
            el.classList.toggle('is-selected', on);
            const box = el.querySelector('.mg-shot__pick');
            if (box) box.checked = on;
        });
    };

    const toggleSelect = (i, on) => {
        if (on) selected.add(i); else selected.delete(i);
        syncSelection();
    };

    const copyOriginal = async (url) => {
        const res = await fetch(url, { credentials: 'same-origin' });
        if (!res.ok) throw new Error('fetch failed');
        const blob = await res.blob();
        try {
            await navigator.clipboard.write([new ClipboardItem({ [blob.type]: blob })]);
            return;
        } catch (_) {
            const bmp = await createImageBitmap(blob);
            const canvas = document.createElement('canvas');
            canvas.width = bmp.width;
            canvas.height = bmp.height;
            canvas.getContext('2d').drawImage(bmp, 0, 0);
            const png = await new Promise((resolve, reject) => {
                canvas.toBlob((b) => (b ? resolve(b) : reject(new Error('png'))), 'image/png');
            });
            await navigator.clipboard.write([new ClipboardItem({ 'image/png': png })]);
        }
    };

    const copyPhotos = async (indices) => {
        if (indices.length === 0) return;
        if (indices.length > 1) {
            window.location.href = `${zipBase}?indices=${indices.join(',')}`;
            showToast(`Downloading ${indices.length} originals`);
            return;
        }
        const photo = photos[indices[0]];
        if (!photo?.download_url) return;
        try {
            await copyOriginal(photo.download_url);
            showToast('Copied original image');
        } catch (_) {
            showToast('Copy failed — download instead');
        }
    };

    const open = (i) => {
        index = (i + photos.length) % photos.length;
        const photo = photos[index];
        img.src = photo.preview_url || photo.inline_url;
        img.alt = photo.name || '';
        meta.textContent = `${index + 1} / ${photos.length} · ${photo.name || 'Photo'}`;
        dl.href = photo.download_url;
        lb.classList.add('is-open');
        lb.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
    };

    const close = () => {
        lb.classList.remove('is-open');
        lb.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
        img.removeAttribute('src');
    };

    root.querySelectorAll('.mg-shot').forEach((el) => {
        el.addEventListener('click', (e) => {
            if (e.target.closest('.mg-shot__pick, .mg-shot__bar')) return;
            open(Number(el.dataset.index || 0));
        });
        el.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                open(Number(el.dataset.index || 0));
            }
        });
    });

    root.querySelectorAll('.mg-shot__pick').forEach((box) => {
        box.addEventListener('click', (e) => e.stopPropagation());
        box.addEventListener('change', () => toggleSelect(Number(box.dataset.index || 0), box.checked));
    });

    root.querySelectorAll('[data-copy]').forEach((btn) => {
        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            copyPhotos([Number(btn.getAttribute('data-copy') || 0)]);
        });
    });

    document.getElementById('mg-select-all')?.addEventListener('click', () => {
        photos.forEach((_, i) => selected.add(i));
        syncSelection();
    });
    document.getElementById('mg-clear-selected')?.addEventListener('click', () => {
        selected.clear();
        syncSelection();
    });
    document.getElementById('mg-copy-selected')?.addEventListener('click', () => copyPhotos(selectedIndices()));
    document.getElementById('mg-download-selected')?.addEventListener('click', () => {
        const indices = selectedIndices();
        if (indices.length === 0) return;
        window.location.href = `${zipBase}?indices=${indices.join(',')}`;
    });

    document.getElementById('mg-lb-close')?.addEventListener('click', close);
    document.getElementById('mg-lb-prev')?.addEventListener('click', () => open(index - 1));
    document.getElementById('mg-lb-next')?.addEventListener('click', () => open(index + 1));
    document.getElementById('mg-lb-copy')?.addEventListener('click', () => copyPhotos([index]));
    lb?.addEventListener('click', (e) => { if (e.target === lb) close(); });

    document.addEventListener('keydown', (e) => {
        if (!lb.classList.contains('is-open')) return;
        if (e.key === 'Escape') close();
        if (e.key === 'ArrowLeft') open(index - 1);
        if (e.key === 'ArrowRight') open(index + 1);
    });

    const hash = window.location.hash;
    if (hash.startsWith('#photo-')) {
        const i = Number(hash.replace('#photo-', ''));
        if (!Number.isNaN(i)) open(i);
    }
})();
</script>
@endpush
