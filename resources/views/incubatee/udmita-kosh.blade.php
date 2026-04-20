@extends('layouts.admin')

@section('body_class', 'admin-app-body--dashboard')

@section('title', 'Udmita Kosh')

@section('heading', 'Udmita Kosh')

@push('styles')
<style>
    .uk-wrap { max-width: 72rem; margin: 0 auto; }

    .uk-hero {
        position: relative;
        overflow: hidden;
        border-radius: 16px;
        padding: 1.75rem 1.75rem 1.5rem;
        margin-bottom: 1.5rem;
        color: #f8fafc;
        background:
            radial-gradient(circle at 15% 0%, rgba(253, 224, 71, 0.25), transparent 45%),
            radial-gradient(circle at 90% 100%, rgba(45, 212, 191, 0.4), transparent 55%),
            linear-gradient(135deg, #1e1b4b 0%, #3730a3 45%, #0f766e 100%);
        box-shadow: 0 12px 32px rgba(79, 70, 229, 0.15);
    }
    .uk-hero::before {
        content: '';
        position: absolute;
        width: 260px; height: 260px;
        border-radius: 50%;
        top: -80px; right: -70px;
        background: radial-gradient(circle, rgba(251, 191, 36, 0.45), transparent 65%);
        filter: blur(20px);
    }
    .uk-hero__eyebrow {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        padding: 0.22rem 0.65rem;
        border-radius: 999px;
        background: rgba(255, 255, 255, 0.15);
        border: 1px solid rgba(255, 255, 255, 0.22);
        font-size: 0.65rem;
        letter-spacing: 0.12em;
        text-transform: uppercase;
        color: #fef3c7;
        margin-bottom: 0.75rem;
        position: relative;
        z-index: 1;
    }
    .uk-hero__eyebrow::before {
        content: '';
        width: 6px; height: 6px;
        border-radius: 999px;
        background: #fbbf24;
        box-shadow: 0 0 10px #fbbf24;
    }
    .uk-hero__h {
        position: relative;
        z-index: 1;
        margin: 0 0 0.35rem;
        font-size: 1.65rem;
        font-weight: 700;
        letter-spacing: -0.02em;
        color: #fff;
    }
    .uk-hero__h em {
        font-style: normal;
        background: linear-gradient(90deg, #fde68a, #5eead4);
        -webkit-background-clip: text;
        background-clip: text;
        color: transparent;
    }
    .uk-hero__sub {
        position: relative;
        z-index: 1;
        margin: 0;
        font-size: 0.92rem;
        color: rgba(226, 232, 240, 0.92);
        line-height: 1.5;
        max-width: 44rem;
    }

    .uk-toc {
        position: relative;
        z-index: 1;
        display: flex;
        flex-wrap: wrap;
        gap: 0.4rem;
        margin-top: 1rem;
    }
    .uk-toc a {
        padding: 0.35rem 0.75rem;
        border-radius: 999px;
        background: rgba(255, 255, 255, 0.14);
        border: 1px solid rgba(255, 255, 255, 0.2);
        font-size: 0.78rem;
        font-weight: 600;
        color: #fef3c7;
        text-decoration: none;
        transition: background 0.15s, transform 0.15s;
    }
    .uk-toc a:hover {
        background: rgba(255, 255, 255, 0.22);
        transform: translateY(-1px);
    }

    .uk-section { margin-bottom: 2rem; scroll-margin-top: 5.5rem; }
    .uk-section__head {
        display: flex;
        align-items: center;
        gap: 0.65rem;
        margin: 0 0 0.85rem;
    }
    .uk-section__emoji {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 2.25rem;
        height: 2.25rem;
        border-radius: 10px;
        background: linear-gradient(135deg, #eef2ff, #ecfeff);
        border: 1px solid #e0e7ff;
        font-size: 1.1rem;
    }
    .uk-section__title {
        margin: 0;
        font-size: 1.1rem;
        font-weight: 700;
        color: #0f172a;
        letter-spacing: -0.015em;
    }
    .uk-section__title small {
        display: block;
        font-size: 0.72rem;
        font-weight: 500;
        color: #64748b;
        letter-spacing: 0.04em;
        margin-top: 0.1rem;
    }
    .uk-section__desc {
        margin: 0 0 1rem;
        color: #64748b;
        font-size: 0.88rem;
        line-height: 1.5;
    }

    .uk-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 1rem;
    }
    .uk-card {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        overflow: hidden;
        box-shadow: 0 4px 14px rgba(15, 23, 42, 0.05);
        transition: transform 0.2s ease, box-shadow 0.2s ease;
        display: flex;
        flex-direction: column;
    }
    .uk-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 12px 28px rgba(79, 70, 229, 0.12);
    }
    .uk-card__thumb {
        position: relative;
        aspect-ratio: 16 / 9;
        display: block;
        overflow: hidden;
        border: none;
        padding: 0;
        cursor: pointer;
        width: 100%;
        color: #fff;
        text-align: left;
    }
    .uk-card__thumb::after {
        content: '';
        position: absolute;
        inset: 0;
        background:
            radial-gradient(circle at 20% 20%, rgba(255, 255, 255, 0.18), transparent 45%),
            radial-gradient(circle at 80% 85%, rgba(255, 255, 255, 0.1), transparent 45%);
        pointer-events: none;
    }
    .uk-card__thumb--starting-business { background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 60%, #0d9488 100%); }
    .uk-card__thumb--finance-funding { background: linear-gradient(135deg, #047857 0%, #059669 55%, #fbbf24 100%); }
    .uk-card__thumb--marketing { background: linear-gradient(135deg, #db2777 0%, #f97316 60%, #facc15 100%); }
    .uk-card__thumb--legal-compliance { background: linear-gradient(135deg, #0f172a 0%, #1e293b 55%, #334155 100%); }
    .uk-card__thumb--pitch-growth { background: linear-gradient(135deg, #1e3a8a 0%, #2563eb 55%, #06b6d4 100%); }

    .uk-card__thumb-cat {
        position: absolute;
        top: 0.65rem;
        left: 0.65rem;
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
        padding: 0.2rem 0.55rem;
        background: rgba(15, 23, 42, 0.35);
        backdrop-filter: blur(6px);
        color: #fff;
        border-radius: 999px;
        font-size: 0.65rem;
        font-weight: 700;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        z-index: 2;
    }
    .uk-card__play {
        position: absolute;
        inset: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 1;
    }
    .uk-card__play svg {
        width: 30px;
        height: 30px;
        color: #0f172a;
        filter: none;
        transition: transform 0.15s;
    }
    .uk-card__play-bg {
        width: 64px;
        height: 64px;
        border-radius: 999px;
        background: #fff;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 12px 32px rgba(0, 0, 0, 0.35);
        transition: transform 0.15s;
    }
    .uk-card:hover .uk-card__play-bg { transform: scale(1.06); }
    .uk-card__duration {
        position: absolute;
        bottom: 0.55rem;
        right: 0.55rem;
        padding: 0.15rem 0.45rem;
        border-radius: 6px;
        background: rgba(15, 23, 42, 0.75);
        color: #fff;
        font-size: 0.7rem;
        font-weight: 600;
        z-index: 2;
    }
    .uk-card__body {
        padding: 0.85rem 0.95rem 1rem;
        flex: 1;
        display: flex;
        flex-direction: column;
        gap: 0.35rem;
    }
    .uk-card__title {
        margin: 0;
        font-size: 0.9rem;
        font-weight: 700;
        color: #0f172a;
        line-height: 1.35;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }
    .uk-card__meta {
        display: flex;
        align-items: center;
        gap: 0.4rem;
        font-size: 0.75rem;
        color: #64748b;
        margin-top: auto;
    }
    .uk-card__meta svg { width: 14px; height: 14px; flex-shrink: 0; }

    .uk-tag {
        display: inline-flex;
        align-items: center;
        padding: 0.15rem 0.5rem;
        border-radius: 6px;
        background: #fef3c7;
        color: #92400e;
        font-size: 0.65rem;
        font-weight: 700;
        letter-spacing: 0.05em;
        text-transform: uppercase;
    }

    .uk-docs {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 1rem;
    }
    .uk-doc {
        display: flex;
        gap: 0.85rem;
        padding: 1rem;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        background: #fff;
        text-decoration: none;
        color: inherit;
        transition: border-color 0.15s, box-shadow 0.15s, transform 0.15s;
        box-shadow: 0 3px 10px rgba(15, 23, 42, 0.04);
    }
    .uk-doc:hover {
        border-color: #c7d2fe;
        transform: translateY(-2px);
        box-shadow: 0 10px 24px rgba(79, 70, 229, 0.1);
    }
    .uk-doc__badge {
        flex-shrink: 0;
        width: 2.6rem;
        height: 2.6rem;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.72rem;
        font-weight: 800;
        letter-spacing: 0.04em;
        color: #fff;
    }
    .uk-doc__badge--pdf { background: linear-gradient(135deg, #ef4444, #dc2626); }
    .uk-doc__badge--ppt { background: linear-gradient(135deg, #f97316, #ea580c); }
    .uk-doc__badge--xlsx { background: linear-gradient(135deg, #10b981, #059669); }
    .uk-doc__badge--doc { background: linear-gradient(135deg, #3b82f6, #2563eb); }
    .uk-doc__body { min-width: 0; }
    .uk-doc__title {
        margin: 0 0 0.2rem;
        font-size: 0.9rem;
        font-weight: 700;
        color: #0f172a;
        line-height: 1.3;
    }
    .uk-doc__meta {
        font-size: 0.72rem;
        color: #94a3b8;
        margin: 0 0 0.35rem;
    }
    .uk-doc__desc {
        font-size: 0.8rem;
        color: #475569;
        margin: 0;
        line-height: 1.4;
    }

    .uk-coming {
        margin-top: 2rem;
        padding: 1.25rem 1.4rem;
        border-radius: 14px;
        border: 2px dashed #c7d2fe;
        background: linear-gradient(135deg, #eef2ff, #ecfeff);
        text-align: center;
        color: #4338ca;
    }
    .uk-coming h3 { margin: 0 0 0.35rem; font-size: 0.95rem; font-weight: 700; }
    .uk-coming p { margin: 0; font-size: 0.85rem; color: #64748b; line-height: 1.5; }

</style>
@endpush

@section('content')
<div class="uk-wrap">
    <section class="uk-hero">
        <span class="uk-hero__eyebrow">Self-learning library</span>
        <h2 class="uk-hero__h">Udmita Kosh — <em>knowledge for every founder</em></h2>
        <p class="uk-hero__sub">
            A curated library of videos, documents and templates — in Hindi and English — to help you go from an idea to a running business. Watch, download and learn at your own pace.
        </p>
        <nav class="uk-toc" aria-label="Sections">
            @foreach ($categories as $cat)
                <a href="#cat-{{ $cat['slug'] }}">{{ $cat['emoji'] }} {{ $cat['title'] }}</a>
            @endforeach
            <a href="#downloads">📂 Downloads</a>
        </nav>
    </section>

    @foreach ($categories as $cat)
        <section class="uk-section" id="cat-{{ $cat['slug'] }}">
            <header class="uk-section__head">
                <span class="uk-section__emoji">{{ $cat['emoji'] }}</span>
                <h2 class="uk-section__title">
                    {{ $cat['title'] }}
                    <small>{{ $cat['hindi'] }}</small>
                </h2>
            </header>
            <p class="uk-section__desc">{{ $cat['description'] }}</p>

            <div class="uk-grid">
                @foreach ($cat['videos'] as $v)
                    @php
                        $searchQ = urlencode($v['title'].' '.($v['channel'] ?? '').' Hindi');
                        $ytUrl = 'https://www.youtube.com/results?search_query='.$searchQ;
                    @endphp
                    <article class="uk-card">
                        <a
                            class="uk-card__thumb uk-card__thumb--{{ $cat['slug'] }}"
                            href="{{ $ytUrl }}"
                            target="_blank"
                            rel="noopener"
                            aria-label="Watch {{ $v['title'] }} on YouTube"
                        >
                            <span class="uk-card__thumb-cat">{{ $cat['emoji'] }} {{ $cat['title'] }}</span>
                            <span class="uk-card__play" aria-hidden="true">
                                <span class="uk-card__play-bg">
                                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7L8 5z"/></svg>
                                </span>
                            </span>
                            @if(!empty($v['duration']))
                                <span class="uk-card__duration">{{ $v['duration'] }}</span>
                            @endif
                        </a>
                        <div class="uk-card__body">
                            <span class="uk-tag">Video · Hindi</span>
                            <h3 class="uk-card__title">{{ $v['title'] }}</h3>
                            <div class="uk-card__meta">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M23 7l-7 5 7 5V7z"/><rect x="1" y="5" width="15" height="14" rx="2" ry="2"/></svg>
                                <span>{{ $v['channel'] }}</span>
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>
        </section>
    @endforeach

    <section class="uk-section" id="downloads">
        <header class="uk-section__head">
            <span class="uk-section__emoji">📂</span>
            <h2 class="uk-section__title">
                Downloads & Templates
                <small>टेम्पलेट और गाइड</small>
            </h2>
        </header>
        <p class="uk-section__desc">Handy PDFs, spreadsheets and slide templates you can download and use right away.</p>

        <div class="uk-docs">
            @foreach ($documents as $doc)
                @php $badgeClass = 'uk-doc__badge--' . strtolower($doc['type'] === 'XLSX' ? 'xlsx' : ($doc['type'] === 'PDF' ? 'pdf' : ($doc['type'] === 'PPT' ? 'ppt' : 'doc'))); @endphp
                <a class="uk-doc" href="{{ $doc['url'] }}" target="_blank" rel="noopener">
                    <span class="uk-doc__badge {{ $badgeClass }}">{{ $doc['type'] }}</span>
                    <div class="uk-doc__body">
                        <h3 class="uk-doc__title">{{ $doc['title'] }}</h3>
                        <p class="uk-doc__meta">{{ $doc['type'] }} · {{ $doc['size'] }}</p>
                        <p class="uk-doc__desc">{{ $doc['description'] }}</p>
                    </div>
                </a>
            @endforeach
        </div>
    </section>

    <div class="uk-coming">
        <h3>More modules coming soon</h3>
        <p>Admin team will keep adding new videos, case studies and expert sessions here. Check back every week.</p>
    </div>
</div>

@endsection
