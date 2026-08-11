@extends('layouts.admin')

@section('title', $section['label'].' — Media Gallery')
@section('heading', $section['label'])

@push('styles')
<style>
    .mg { --mg-ink:#0f172a; --mg-muted:#64748b; --mg-line:#e2e8f0; --mg-accent:#0f766e; --mg-accent-2:#134e4a; font-family:'DM Sans',system-ui,sans-serif; color:var(--mg-ink); }
    .mg-crumb { display:flex; flex-wrap:wrap; gap:.4rem; align-items:center; font-size:.82rem; color:var(--mg-muted); margin-bottom:.85rem; }
    .mg-crumb a { color:var(--mg-accent); text-decoration:none; font-weight:600; }
    .mg-crumb a:hover { text-decoration:underline; }
    .mg-hero { display:flex; flex-wrap:wrap; align-items:flex-end; justify-content:space-between; gap:1rem; margin-bottom:1.1rem; }
    .mg-hero h1 { margin:0 0 .25rem; font-size:1.35rem; font-weight:800; letter-spacing:-.02em; }
    .mg-hero p { margin:0; color:var(--mg-muted); font-size:.88rem; }
    .mg-filter { background:#fff; border:1px solid var(--mg-line); border-radius:14px; padding:.85rem 1rem; margin-bottom:1.25rem; box-shadow:0 2px 10px rgba(15,23,42,.04); }
    .mg-filter__row { display:flex; flex-wrap:wrap; gap:.55rem; align-items:flex-end; }
    .mg-field { display:flex; flex-direction:column; gap:.22rem; flex:1; min-width:130px; }
    .mg-field label { font-size:.7rem; font-weight:700; color:#334155; text-transform:uppercase; letter-spacing:.06em; }
    .mg-input { width:100%; padding:.48rem .65rem; border:1px solid #cbd5e1; border-radius:8px; font:inherit; font-size:.85rem; background:#fff; }
    .mg-input:focus { outline:none; border-color:var(--mg-accent); box-shadow:0 0 0 3px rgba(15,118,110,.12); }
    .mg-btn { display:inline-flex; align-items:center; gap:.35rem; padding:.5rem .9rem; border:none; border-radius:8px; background:var(--mg-accent); color:#fff; font:inherit; font-size:.84rem; font-weight:700; cursor:pointer; text-decoration:none; white-space:nowrap; }
    .mg-btn:hover { background:var(--mg-accent-2); color:#fff; }
    .mg-btn--ghost { background:transparent; color:var(--mg-accent); border:1px solid var(--mg-accent); }
    .mg-events { display:flex; flex-direction:column; gap:1rem; }
    .mg-event { background:#fff; border:1px solid var(--mg-line); border-radius:16px; overflow:hidden; box-shadow:0 4px 16px rgba(15,23,42,.05); }
    .mg-event__head { display:flex; flex-wrap:wrap; gap:.75rem; align-items:center; justify-content:space-between; padding:.9rem 1.1rem; border-bottom:1px solid #f1f5f9; }
    .mg-event__title { margin:0; font-size:.98rem; font-weight:800; }
    .mg-event__title a { color:inherit; text-decoration:none; }
    .mg-event__title a:hover { color:var(--mg-accent); }
    .mg-event__meta { margin:.2rem 0 0; font-size:.78rem; color:var(--mg-muted); }
    .mg-event__actions { display:flex; gap:.45rem; flex-wrap:wrap; }
    .mg-thumbs { display:grid; grid-template-columns:repeat(auto-fill,minmax(140px,1fr)); gap:.45rem; padding:.75rem; background:#f8fafc; }
    .mg-thumb { position:relative; display:block; aspect-ratio:1; border-radius:10px; overflow:hidden; background:#e2e8f0; }
    .mg-thumb img { width:100%; height:100%; object-fit:cover; display:block; transition:transform .2s ease; }
    .mg-thumb:hover img { transform:scale(1.04); }
    .mg-thumb__more { position:absolute; inset:0; display:grid; place-items:center; background:rgba(15,23,42,.55); color:#fff; font-weight:800; font-size:1rem; }
    .mg-empty { padding:2.5rem 1rem; text-align:center; color:var(--mg-muted); background:#fff; border:1px dashed var(--mg-line); border-radius:14px; }
    .mg-pager { margin-top:1.25rem; }
</style>
@endpush

@section('content')
@php
    $filterQs = request()->except(['page']);
@endphp
<div class="mg">
    <nav class="mg-crumb" aria-label="Breadcrumb">
        <a href="{{ route('admin.media-gallery.index', $filterQs) }}">Media Gallery</a>
        <span>/</span>
        <span>{{ $section['label'] }}</span>
    </nav>

    <div class="mg-hero">
        <div>
            <h1>{{ $section['label'] }}</h1>
            <p>{{ $section['description'] }} · {{ $albums->total() }} events with media</p>
        </div>
    </div>

    @include('admin.media-gallery._filters', ['action' => route('admin.media-gallery.section', $sectionKey)])

    @if ($albums->isEmpty())
        <div class="mg-empty">No albums match these filters.</div>
    @else
        <div class="mg-events">
            @foreach ($albums as $album)
                <article class="mg-event">
                    <div class="mg-event__head">
                        <div>
                            <h2 class="mg-event__title">
                                <a href="{{ $album['url'] }}">{{ $album['title'] }}</a>
                            </h2>
                            <p class="mg-event__meta">{{ $album['district'] }} · {{ $album['date'] }} · {{ $album['photo_count'] }} photos</p>
                        </div>
                        <div class="mg-event__actions">
                            <a class="mg-btn" href="{{ $album['url'] }}">Open gallery</a>
                            <a class="mg-btn mg-btn--ghost" href="{{ route('admin.media-gallery.zip', [$sectionKey, $album['id']]) }}">Download all</a>
                        </div>
                    </div>
                    @if ($album['thumbs'] !== [])
                        <div class="mg-thumbs">
                            @foreach ($album['thumbs'] as $i => $thumb)
                                <a class="mg-thumb" href="{{ $album['url'] }}#photo-{{ $i }}">
                                    <img src="{{ $thumb['inline_url'] }}" alt="{{ $thumb['name'] }}" loading="lazy">
                                    @if ($i === 3 && $album['photo_count'] > 4)
                                        <span class="mg-thumb__more">+{{ $album['photo_count'] - 4 }}</span>
                                    @endif
                                </a>
                            @endforeach
                        </div>
                    @endif
                </article>
            @endforeach
        </div>
        <div class="mg-pager">
            {{ $albums->links() }}
        </div>
    @endif
</div>
@endsection
