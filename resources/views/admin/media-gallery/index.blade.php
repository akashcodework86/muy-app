@extends('layouts.admin')

@section('title', 'Media Gallery — State Admin')
@section('heading', 'Media Gallery')

@push('styles')
<style>
    .mg { --mg-ink:#0f172a; --mg-muted:#64748b; --mg-line:#e2e8f0; --mg-bg:#f1f5f9; --mg-card:#fff; --mg-accent:#0f766e; --mg-accent-2:#134e4a; font-family:'DM Sans',system-ui,sans-serif; color:var(--mg-ink); }
    .mg-hero { display:flex; flex-wrap:wrap; align-items:flex-end; justify-content:space-between; gap:1rem; margin-bottom:1.25rem; padding:1.25rem 1.35rem; border-radius:18px; background:linear-gradient(135deg,#ecfdf5 0%,#f0fdfa 45%,#e2e8f0 100%); border:1px solid #ccfbf1; }
    .mg-hero h1 { margin:0 0 .35rem; font-size:1.45rem; font-weight:800; letter-spacing:-.02em; }
    .mg-hero p { margin:0; color:var(--mg-muted); font-size:.9rem; max-width:40rem; line-height:1.45; }
    .mg-filter { background:var(--mg-card); border:1px solid var(--mg-line); border-radius:14px; padding:.85rem 1rem; margin-bottom:1.25rem; box-shadow:0 2px 10px rgba(15,23,42,.04); }
    .mg-filter__row { display:flex; flex-wrap:wrap; gap:.55rem; align-items:flex-end; }
    .mg-field { display:flex; flex-direction:column; gap:.22rem; flex:1; min-width:130px; }
    .mg-field label { font-size:.7rem; font-weight:700; color:#334155; text-transform:uppercase; letter-spacing:.06em; }
    .mg-input { width:100%; padding:.48rem .65rem; border:1px solid #cbd5e1; border-radius:8px; font:inherit; font-size:.85rem; background:#fff; }
    .mg-input:focus { outline:none; border-color:var(--mg-accent); box-shadow:0 0 0 3px rgba(15,118,110,.12); }
    .mg-btn { display:inline-flex; align-items:center; gap:.35rem; padding:.5rem .9rem; border:none; border-radius:8px; background:var(--mg-accent); color:#fff; font:inherit; font-size:.84rem; font-weight:700; cursor:pointer; text-decoration:none; white-space:nowrap; }
    .mg-btn:hover { background:var(--mg-accent-2); color:#fff; }
    .mg-btn--ghost { background:transparent; color:var(--mg-accent); border:1px solid var(--mg-accent); }
    .mg-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(240px,1fr)); gap:1rem; }
    .mg-album { position:relative; display:block; text-decoration:none; color:inherit; border-radius:16px; overflow:hidden; background:#0f172a; aspect-ratio:1; box-shadow:0 8px 24px rgba(15,23,42,.12); transition:transform .18s ease, box-shadow .18s ease; }
    .mg-album:hover { transform:translateY(-3px); box-shadow:0 14px 32px rgba(15,23,42,.18); }
    .mg-album__img { position:absolute; inset:0; width:100%; height:100%; object-fit:cover; opacity:.92; }
    .mg-album__ph { position:absolute; inset:0; display:grid; place-items:center; background:linear-gradient(145deg,#334155,#0f172a); color:#94a3b8; font-size:.85rem; font-weight:600; padding:1rem; text-align:center; }
    .mg-album__shade { position:absolute; inset:0; background:linear-gradient(180deg,transparent 35%,rgba(15,23,42,.88) 100%); }
    .mg-album__meta { position:absolute; left:0; right:0; bottom:0; padding:1rem; color:#fff; z-index:1; }
    .mg-album__title { margin:0 0 .2rem; font-size:1rem; font-weight:800; letter-spacing:-.01em; }
    .mg-album__sub { margin:0; font-size:.78rem; color:#cbd5e1; }
    .mg-album__count { position:absolute; top:.75rem; right:.75rem; z-index:1; background:rgba(15,23,42,.72); color:#fff; font-size:.72rem; font-weight:700; padding:.28rem .55rem; border-radius:999px; backdrop-filter:blur(6px); }
    .mg-empty { padding:2.5rem 1rem; text-align:center; color:var(--mg-muted); background:#fff; border:1px dashed var(--mg-line); border-radius:14px; }
</style>
@endpush

@section('content')
<div class="mg">
    <div class="mg-hero">
        <div>
            <h1>Media Gallery</h1>
            <p>Browse event and workshop photos by programme section. Open an album for a full gallery view with direct download.</p>
        </div>
    </div>

    @include('admin.media-gallery._filters', ['action' => route('admin.media-gallery.index')])

    @if (count($sections) === 0)
        <div class="mg-empty">No photo sections are available yet.</div>
    @else
        <div class="mg-grid">
            @foreach ($sections as $section)
                <a class="mg-album" href="{{ $section['url'] }}{{ request()->getQueryString() ? '?'.request()->getQueryString() : '' }}">
                    @if ($section['cover'])
                        <img class="mg-album__img" src="{{ $section['cover']['inline_url'] }}" alt="" loading="lazy">
                    @else
                        <div class="mg-album__ph">No photos yet</div>
                    @endif
                    <div class="mg-album__shade"></div>
                    <span class="mg-album__count">{{ number_format($section['album_count']) }} albums</span>
                    <div class="mg-album__meta">
                        <h2 class="mg-album__title">{{ $section['label'] }}</h2>
                        <p class="mg-album__sub">{{ $section['description'] }}</p>
                    </div>
                </a>
            @endforeach
        </div>
    @endif
</div>
@endsection
