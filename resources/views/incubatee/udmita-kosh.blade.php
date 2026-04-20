@extends('layouts.admin')

@section('body_class', 'admin-app-body--dashboard')

@section('title', 'Udmita Kosh')

@section('heading', 'Udmita Kosh')

@php
    $totalVideos = collect($categories)->sum(fn ($c) => count($c['videos'] ?? []));
    $docsByType = collect($documents)->groupBy('type')->map->count();
    $pdfCount = $docsByType['PDF'] ?? 0;
    $pptCount = $docsByType['PPT'] ?? 0;
    $xlsxCount = $docsByType['XLSX'] ?? 0;
    $totalDocs = collect($documents)->count();
@endphp

@push('styles')
<style>
    .uk-shell {
        max-width: 82rem;
        margin: 0 auto;
        display: grid;
        grid-template-columns: 1fr;
        gap: 1.25rem;
    }
    @media (min-width: 1024px) {
        .uk-shell { grid-template-columns: 17.5rem minmax(0, 1fr); }
    }

    /* ------- Sidebar ------- */
    .uk-side {
        position: relative;
        border-radius: 18px;
        overflow: hidden;
        color: #f8fafc;
        background:
            radial-gradient(circle at 20% 0%, rgba(253, 224, 71, 0.25), transparent 50%),
            radial-gradient(circle at 100% 100%, rgba(45, 212, 191, 0.35), transparent 55%),
            linear-gradient(160deg, #1e1b4b 0%, #3730a3 45%, #0f766e 100%);
        box-shadow: 0 18px 40px rgba(79, 70, 229, 0.18);
        padding: 1.35rem 1.1rem 1.1rem;
    }
    @media (min-width: 1024px) {
        .uk-side {
            position: sticky;
            top: 5.5rem;
            align-self: start;
            max-height: calc(100vh - 6.5rem);
            overflow-y: auto;
        }
    }
    .uk-side::after {
        content: '';
        position: absolute;
        width: 200px;
        height: 200px;
        border-radius: 50%;
        top: -80px;
        left: -60px;
        background: radial-gradient(circle, rgba(251, 191, 36, 0.45), transparent 65%);
        filter: blur(20px);
        pointer-events: none;
    }

    /* Mascot */
    .uk-mascot {
        position: relative;
        z-index: 1;
        text-align: center;
        margin-bottom: 1rem;
    }
    .uk-mascot__stage {
        position: relative;
        display: inline-block;
        padding: 0.5rem;
        border-radius: 999px;
        background: radial-gradient(circle, rgba(253, 224, 71, 0.35) 0%, rgba(253, 224, 71, 0) 72%);
    }
    .uk-mascot__stage::before {
        content: '';
        position: absolute;
        inset: -6px;
        border-radius: 999px;
        background: conic-gradient(from 0deg, #fde68a, #5eead4, #a5b4fc, #fbbf24, #fde68a);
        filter: blur(14px);
        opacity: 0.45;
        animation: ukHalo 8s linear infinite;
    }
    @keyframes ukHalo { to { transform: rotate(360deg); } }
    .uk-mascot__img {
        position: relative;
        width: 170px;
        height: 170px;
        object-fit: contain;
        object-position: center bottom;
        border-radius: 999px;
        border: 3px solid rgba(255, 255, 255, 0.95);
        background:
            radial-gradient(circle at 50% 38%, rgba(255, 255, 255, 0.95) 0%, rgba(254, 243, 199, 0.95) 55%, rgba(196, 181, 253, 0.85) 100%);
        box-shadow: 0 18px 32px rgba(0, 0, 0, 0.3);
        animation: ukFloat 6s ease-in-out infinite;
        display: block;
        padding: 6px 6px 0;
    }
    .uk-mascot__img.is-broken {
        background: linear-gradient(135deg, #fde68a, #5eead4);
        color: #1e1b4b;
        font-weight: 800;
        font-size: 2.4rem;
        text-align: center;
        line-height: 164px;
        font-family: inherit;
        padding: 0;
    }
    @keyframes ukFloat {
        0%, 100% { transform: translateY(0); }
        50% { transform: translateY(-6px); }
    }
    .uk-mascot__name {
        margin: 0.75rem 0 0.15rem;
        font-size: 1.05rem;
        font-weight: 700;
        letter-spacing: -0.01em;
        color: #fff;
    }
    .uk-mascot__tag {
        margin: 0;
        font-size: 0.72rem;
        font-weight: 500;
        color: #fde68a;
        letter-spacing: 0.04em;
    }
    .uk-mascot__hi {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        margin-top: 0.6rem;
        padding: 0.22rem 0.6rem;
        border-radius: 999px;
        background: rgba(255, 255, 255, 0.16);
        border: 1px solid rgba(255, 255, 255, 0.22);
        font-size: 0.65rem;
        letter-spacing: 0.12em;
        text-transform: uppercase;
        color: #fef3c7;
    }
    .uk-mascot__hi::before {
        content: '';
        width: 6px; height: 6px; border-radius: 999px; background: #34d399;
        box-shadow: 0 0 0 3px rgba(52, 211, 153, 0.3);
        animation: ukPulse 1.8s ease-in-out infinite;
    }
    @keyframes ukPulse {
        0%, 100% { transform: scale(1); opacity: 1; }
        50% { transform: scale(1.25); opacity: 0.75; }
    }

    /* Stats quick row */
    .uk-quickstats {
        position: relative;
        z-index: 1;
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 0.45rem;
        margin-bottom: 1.1rem;
    }
    .uk-quickstats__cell {
        padding: 0.55rem 0.7rem;
        border-radius: 10px;
        background: rgba(255, 255, 255, 0.1);
        border: 1px solid rgba(255, 255, 255, 0.18);
        backdrop-filter: blur(10px);
    }
    .uk-quickstats__cell b {
        display: block;
        font-size: 1.1rem;
        font-weight: 800;
        line-height: 1;
        background: linear-gradient(90deg, #fde68a, #5eead4);
        -webkit-background-clip: text;
        background-clip: text;
        color: transparent;
    }
    .uk-quickstats__cell span {
        display: block;
        margin-top: 0.2rem;
        font-size: 0.65rem;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        color: rgba(226, 232, 240, 0.85);
    }

    .uk-side__kicker {
        position: relative;
        z-index: 1;
        margin: 0 0 0.55rem;
        padding: 0 0.1rem;
        font-size: 0.65rem;
        font-weight: 700;
        letter-spacing: 0.14em;
        text-transform: uppercase;
        color: #fef3c7;
    }
    .uk-navlist {
        position: relative;
        z-index: 1;
        list-style: none;
        margin: 0 0 1rem;
        padding: 0;
        display: flex;
        flex-direction: column;
        gap: 0.3rem;
    }
    .uk-navlink {
        display: flex;
        align-items: center;
        gap: 0.55rem;
        padding: 0.55rem 0.7rem;
        border-radius: 10px;
        background: rgba(255, 255, 255, 0.06);
        border: 1px solid rgba(255, 255, 255, 0.12);
        color: rgba(241, 245, 249, 0.95);
        text-decoration: none;
        transition: background 0.15s ease, transform 0.15s ease, border-color 0.15s ease;
    }
    .uk-navlink:hover {
        background: rgba(255, 255, 255, 0.14);
        border-color: rgba(255, 255, 255, 0.22);
        transform: translateX(2px);
    }
    .uk-navlink.is-active {
        background: linear-gradient(135deg, rgba(251, 191, 36, 0.25), rgba(94, 234, 212, 0.2));
        border-color: rgba(251, 191, 36, 0.45);
        color: #fff;
    }
    .uk-navlink__emoji {
        width: 1.7rem;
        height: 1.7rem;
        flex-shrink: 0;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 8px;
        background: rgba(255, 255, 255, 0.14);
        font-size: 0.95rem;
    }
    .uk-navlink__body {
        min-width: 0;
        flex: 1;
        display: flex;
        flex-direction: column;
        line-height: 1.15;
    }
    .uk-navlink__title {
        font-size: 0.82rem;
        font-weight: 700;
        color: #fff;
    }
    .uk-navlink__sub {
        font-size: 0.68rem;
        color: rgba(226, 232, 240, 0.8);
        margin-top: 0.1rem;
    }
    .uk-count {
        flex-shrink: 0;
        min-width: 1.6rem;
        padding: 0.12rem 0.45rem;
        border-radius: 999px;
        background: rgba(255, 255, 255, 0.18);
        color: #fff;
        font-size: 0.7rem;
        font-weight: 700;
        text-align: center;
    }
    .uk-navlink.is-active .uk-count {
        background: #fbbf24;
        color: #1e1b4b;
    }

    .uk-tip {
        position: relative;
        z-index: 1;
        padding: 0.75rem 0.85rem;
        border-radius: 12px;
        background: rgba(255, 255, 255, 0.07);
        border: 1px dashed rgba(255, 255, 255, 0.25);
        font-size: 0.72rem;
        line-height: 1.5;
        color: rgba(241, 245, 249, 0.9);
    }
    .uk-tip b {
        display: block;
        color: #fde68a;
        font-size: 0.7rem;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        margin-bottom: 0.25rem;
    }

    /* ------- Main content ------- */
    .uk-main { min-width: 0; }

    .uk-hero {
        position: relative;
        overflow: hidden;
        border-radius: 16px;
        padding: 1.5rem 1.65rem 1.35rem;
        margin-bottom: 1.5rem;
        color: #f8fafc;
        background:
            radial-gradient(circle at 15% 0%, rgba(253, 224, 71, 0.22), transparent 45%),
            radial-gradient(circle at 90% 100%, rgba(45, 212, 191, 0.35), transparent 55%),
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
        margin: 0 0 0.4rem;
        font-size: 1.55rem;
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
        font-size: 0.9rem;
        color: rgba(226, 232, 240, 0.92);
        line-height: 1.5;
        max-width: 44rem;
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
    .uk-section__count {
        margin-left: auto;
        font-size: 0.72rem;
        font-weight: 700;
        color: #4f46e5;
        background: #eef2ff;
        padding: 0.2rem 0.6rem;
        border-radius: 999px;
    }
    .uk-section__desc {
        margin: 0 0 1rem;
        color: #64748b;
        font-size: 0.88rem;
        line-height: 1.5;
    }

    .uk-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
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
        width: 28px;
        height: 28px;
        color: #0f172a;
        transition: transform 0.15s;
    }
    .uk-card__play-bg {
        width: 58px;
        height: 58px;
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
        font-size: 0.88rem;
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
        grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
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
<div class="uk-shell">
    <aside class="uk-side" aria-label="Udmita Kosh sidebar">
        <div class="uk-mascot">
            <div class="uk-mascot__stage">
                <img
                    class="uk-mascot__img"
                    src="{{ route('assets.mascot.lakhpati-didi') }}?v={{ time() }}"
                    alt="Lakhpati Didi mascot"
                    loading="eager"
                    onerror="this.classList.add('is-broken'); this.setAttribute('alt','LD');"
                >
            </div>
            <p class="uk-mascot__name">Lakhpati Didi</p>
            <p class="uk-mascot__tag">आपकी सीखने की साथी</p>
            <span class="uk-mascot__hi">Namaste, {{ explode(' ', (string) $user->name)[0] ?? 'friend' }}!</span>
        </div>

        <div class="uk-quickstats">
            <div class="uk-quickstats__cell">
                <b>{{ $totalVideos }}</b>
                <span>Videos</span>
            </div>
            <div class="uk-quickstats__cell">
                <b>{{ $totalDocs }}</b>
                <span>Documents</span>
            </div>
            <div class="uk-quickstats__cell">
                <b>{{ count($categories) }}</b>
                <span>Modules</span>
            </div>
            <div class="uk-quickstats__cell">
                <b>Hindi</b>
                <span>Subtitles</span>
            </div>
        </div>

        <p class="uk-side__kicker">Learning modules</p>
        <ul class="uk-navlist">
            @foreach ($categories as $cat)
                <li>
                    <a class="uk-navlink" href="#cat-{{ $cat['slug'] }}" data-target="cat-{{ $cat['slug'] }}">
                        <span class="uk-navlink__emoji">{{ $cat['emoji'] }}</span>
                        <span class="uk-navlink__body">
                            <span class="uk-navlink__title">{{ $cat['title'] }}</span>
                            <span class="uk-navlink__sub">{{ $cat['hindi'] }}</span>
                        </span>
                        <span class="uk-count">{{ count($cat['videos']) }}</span>
                    </a>
                </li>
            @endforeach
            <li>
                <a class="uk-navlink" href="#downloads" data-target="downloads">
                    <span class="uk-navlink__emoji">📂</span>
                    <span class="uk-navlink__body">
                        <span class="uk-navlink__title">Downloads</span>
                        <span class="uk-navlink__sub">PDF · PPT · XLSX</span>
                    </span>
                    <span class="uk-count">{{ $totalDocs }}</span>
                </a>
            </li>
        </ul>

        <p class="uk-side__kicker">Resource types</p>
        <ul class="uk-navlist" style="margin-bottom:1rem;">
            <li>
                <a class="uk-navlink" href="#cat-{{ $categories[0]['slug'] ?? '' }}">
                    <span class="uk-navlink__emoji">🎥</span>
                    <span class="uk-navlink__body">
                        <span class="uk-navlink__title">Videos</span>
                        <span class="uk-navlink__sub">Hindi tutorials</span>
                    </span>
                    <span class="uk-count">{{ $totalVideos }}</span>
                </a>
            </li>
            <li>
                <a class="uk-navlink" href="#downloads">
                    <span class="uk-navlink__emoji">📄</span>
                    <span class="uk-navlink__body">
                        <span class="uk-navlink__title">PDF guides</span>
                        <span class="uk-navlink__sub">Read anywhere</span>
                    </span>
                    <span class="uk-count">{{ $pdfCount }}</span>
                </a>
            </li>
            <li>
                <a class="uk-navlink" href="#downloads">
                    <span class="uk-navlink__emoji">📊</span>
                    <span class="uk-navlink__body">
                        <span class="uk-navlink__title">PPT decks</span>
                        <span class="uk-navlink__sub">Slide templates</span>
                    </span>
                    <span class="uk-count">{{ $pptCount }}</span>
                </a>
            </li>
            <li>
                <a class="uk-navlink" href="#downloads">
                    <span class="uk-navlink__emoji">📈</span>
                    <span class="uk-navlink__body">
                        <span class="uk-navlink__title">Spreadsheets</span>
                        <span class="uk-navlink__sub">Plug-and-play</span>
                    </span>
                    <span class="uk-count">{{ $xlsxCount }}</span>
                </a>
            </li>
        </ul>

        <div class="uk-tip">
            <b>💡 Didi ki tip</b>
            <span>Roz 15 minute sikhne se mahino mein bada farq padta hai. Ek module se shuru karo!</span>
        </div>
    </aside>

    <main class="uk-main">
        <section class="uk-hero">
            <span class="uk-hero__eyebrow">Self-learning library</span>
            <h2 class="uk-hero__h">Udmita Kosh — <em>knowledge for every founder</em></h2>
            <p class="uk-hero__sub">
                A curated library of videos, documents and templates — in Hindi and English — to help you go from an idea to a running business. Watch, download and learn at your own pace.
            </p>
        </section>

        @foreach ($categories as $cat)
            <section class="uk-section" id="cat-{{ $cat['slug'] }}">
                <header class="uk-section__head">
                    <span class="uk-section__emoji">{{ $cat['emoji'] }}</span>
                    <h2 class="uk-section__title">
                        {{ $cat['title'] }}
                        <small>{{ $cat['hindi'] }}</small>
                    </h2>
                    <span class="uk-section__count">{{ count($cat['videos']) }} videos</span>
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
                <span class="uk-section__count">{{ $totalDocs }} files</span>
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
    </main>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const links = document.querySelectorAll('.uk-navlink[data-target]');
    if (!links.length) return;
    const sections = Array.from(links).map((l) => document.getElementById(l.getAttribute('data-target'))).filter(Boolean);

    function setActive(id) {
        links.forEach((l) => l.classList.toggle('is-active', l.getAttribute('data-target') === id));
    }

    const io = new IntersectionObserver((entries) => {
        entries.forEach((e) => {
            if (e.isIntersecting) setActive(e.target.id);
        });
    }, { rootMargin: '-40% 0px -50% 0px', threshold: 0 });
    sections.forEach((s) => io.observe(s));

    links.forEach((link) => {
        link.addEventListener('click', function () {
            setActive(link.getAttribute('data-target'));
        });
    });
})();
</script>
@endpush
