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
    .mg-actions { display:flex; flex-wrap:wrap; gap:.45rem; }
    .mg-btn { display:inline-flex; align-items:center; gap:.35rem; padding:.5rem .9rem; border:none; border-radius:8px; background:var(--mg-accent); color:#fff; font:inherit; font-size:.84rem; font-weight:700; cursor:pointer; text-decoration:none; white-space:nowrap; }
    .mg-btn:hover { background:var(--mg-accent-2); color:#fff; }
    .mg-btn--ghost { background:transparent; color:var(--mg-accent); border:1px solid var(--mg-accent); }
    .mg-masonry { column-count:4; column-gap:.55rem; }
    @media (max-width:1100px) { .mg-masonry { column-count:3; } }
    @media (max-width:760px) { .mg-masonry { column-count:2; } }
    @media (max-width:420px) { .mg-masonry { column-count:1; } }
    .mg-shot { break-inside:avoid; margin:0 0 .55rem; position:relative; border-radius:12px; overflow:hidden; background:#e2e8f0; cursor:zoom-in; }
    .mg-shot img { width:100%; display:block; vertical-align:middle; }
    .mg-shot__bar { position:absolute; inset:auto 0 0 0; display:flex; justify-content:flex-end; gap:.35rem; padding:.45rem; background:linear-gradient(180deg,transparent,rgba(15,23,42,.7)); opacity:0; transition:opacity .15s ease; }
    .mg-shot:hover .mg-shot__bar, .mg-shot:focus-within .mg-shot__bar { opacity:1; }
    .mg-shot__dl { display:inline-flex; align-items:center; justify-content:center; width:2rem; height:2rem; border-radius:999px; background:rgba(255,255,255,.92); color:#0f172a; text-decoration:none; font-size:.85rem; font-weight:800; }
    .mg-shot__dl:hover { background:#fff; }
    .mg-empty { padding:2.5rem 1rem; text-align:center; color:var(--mg-muted); background:#fff; border:1px dashed var(--mg-line); border-radius:14px; }

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
        </div>
        <div class="mg-actions">
            @if (count($photos) > 0)
                <a class="mg-btn" href="{{ route('admin.media-gallery.zip', [$sectionKey, $album['id']]) }}">Download all</a>
            @endif
            <a class="mg-btn mg-btn--ghost" href="{{ $album['detail_url'] }}" target="_blank" rel="noopener">Open record</a>
        </div>
    </div>

    @if (count($photos) === 0)
        <div class="mg-empty">No viewable photos in this album.</div>
    @else
        <div class="mg-masonry" id="mg-gallery" data-photos='@json($photos)'>
            @foreach ($photos as $i => $photo)
                <figure class="mg-shot" id="photo-{{ $i }}" data-index="{{ $i }}" tabindex="0" role="button" aria-label="Open photo {{ $i + 1 }}">
                    <img src="{{ $photo['inline_url'] }}" alt="{{ $photo['name'] }}" loading="lazy">
                    <div class="mg-shot__bar">
                        <a class="mg-shot__dl" href="{{ $photo['download_url'] }}" download title="Download" onclick="event.stopPropagation()">↓</a>
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
@endsection

@push('scripts')
<script>
(() => {
    const root = document.getElementById('mg-gallery');
    if (!root) return;

    let photos = [];
    try { photos = JSON.parse(root.dataset.photos || '[]'); } catch (_) { photos = []; }
    if (!Array.isArray(photos) || photos.length === 0) return;

    const lb = document.getElementById('mg-lightbox');
    const img = document.getElementById('mg-lb-img');
    const meta = document.getElementById('mg-lb-meta');
    const dl = document.getElementById('mg-lb-download');
    let index = 0;

    const open = (i) => {
        index = (i + photos.length) % photos.length;
        const photo = photos[index];
        img.src = photo.inline_url;
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
        el.addEventListener('click', () => open(Number(el.dataset.index || 0)));
        el.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                open(Number(el.dataset.index || 0));
            }
        });
    });

    document.getElementById('mg-lb-close')?.addEventListener('click', close);
    document.getElementById('mg-lb-prev')?.addEventListener('click', () => open(index - 1));
    document.getElementById('mg-lb-next')?.addEventListener('click', () => open(index + 1));
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
