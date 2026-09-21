@extends('layouts.admin')

@section('body_class', 'admin-app-body--dashboard')

@section('title', __('incubatee.dashboard_title'))

@section('heading', __('incubatee.dashboard_heading'))

@push('styles')
<style>
    .inc-wrap { max-width: none; width: 100%; margin: 0 auto; }
    .inc-layout { display: grid; grid-template-columns: 1fr; gap: 1.1rem; align-items: start; }
    @media (min-width: 960px) {
        .inc-layout { grid-template-columns: minmax(0, 1fr) 320px; }
    }
    .inc-main { min-width: 0; }
    .inc-side { position: relative; }
    @media (min-width: 960px) {
        .inc-side { position: sticky; top: 1rem; }
    }

    /* Journey timeline */
    .jrny {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        padding: 1.1rem 1.1rem 1.2rem;
        box-shadow: 0 2px 12px rgba(55, 71, 79, 0.06), 0 8px 24px rgba(38, 166, 154, 0.06);
    }
    .jrny__head {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        margin-bottom: 0.1rem;
    }
    .jrny__h {
        margin: 0;
        font-size: 1rem;
        font-weight: 700;
        color: #00897b;
        letter-spacing: -0.01em;
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
    }
    .jrny__meta {
        margin: 0.15rem 0 0.95rem;
        font-size: 0.78rem;
        color: #475569;
    }
    .jrny__meta b { color: #0f172a; }
    .jrny__status {
        display: inline-block;
        margin-left: 0.35rem;
        padding: 0.12rem 0.5rem;
        border-radius: 999px;
        background: #dbeafe;
        color: #1d4ed8;
        font-size: 0.68rem;
        font-weight: 700;
        letter-spacing: 0.04em;
        text-transform: uppercase;
    }
    .jrny__status--on-track { background: #dcfce7; color: #166534; }
    .jrny__list {
        position: relative;
        list-style: none;
        margin: 0;
        padding: 0.35rem 0 0 0;
    }
    .jrny__list::before {
        content: '';
        position: absolute;
        left: 12px;
        top: 16px;
        bottom: 16px;
        width: 2px;
        background: #e2e8f0;
        border-radius: 2px;
    }
    .jrny__item {
        position: relative;
        display: grid;
        grid-template-columns: 26px 1fr;
        gap: 0.7rem;
        padding: 0.55rem 0 0.55rem 0;
    }
    .jrny__dot {
        position: relative;
        z-index: 1;
        width: 26px;
        height: 26px;
        border-radius: 999px;
        background: #fff;
        border: 2px solid #cbd5e1;
        display: grid;
        place-items: center;
        color: #94a3b8;
        font-size: 0.8rem;
    }
    .jrny__item--done .jrny__dot {
        background: #dcfce7;
        border-color: #22c55e;
        color: #16a34a;
        box-shadow: 0 0 0 4px rgba(34, 197, 94, 0.12);
    }
    .jrny__item--current .jrny__dot {
        background: #dbeafe;
        border-color: #3b82f6;
        color: #1d4ed8;
        animation: jrnyPulse 1.6s ease-in-out infinite;
    }
    @keyframes jrnyPulse {
        0%, 100% { box-shadow: 0 0 0 0 rgba(59, 130, 246, 0.35); }
        50%      { box-shadow: 0 0 0 7px rgba(59, 130, 246, 0); }
    }
    .jrny__body { min-width: 0; padding-top: 0.1rem; }
    .jrny__title {
        margin: 0;
        font-size: 0.88rem;
        font-weight: 700;
        color: #0f172a;
        letter-spacing: -0.01em;
        display: flex;
        align-items: center;
        gap: 0.4rem;
    }
    .jrny__item--done .jrny__title { color: #166534; }
    .jrny__item--current .jrny__title { color: #1d4ed8; }
    .jrny__item--upcoming .jrny__title { color: #334155; }
    .jrny__date {
        margin: 0.1rem 0 0;
        font-size: 0.72rem;
        font-weight: 600;
        color: #64748b;
        letter-spacing: 0.02em;
    }
    .jrny__item--done .jrny__date { color: #16a34a; }
    .jrny__item--current .jrny__date { color: #1d4ed8; }
    .jrny__detail {
        margin: 0.25rem 0 0;
        font-size: 0.78rem;
        color: #64748b;
        line-height: 1.45;
    }
    .jrny__emoji { font-size: 0.95rem; }
    .jrny__foot {
        margin-top: 0.85rem;
        padding-top: 0.75rem;
        border-top: 1px dashed #e2e8f0;
        display: flex;
        justify-content: space-between;
        gap: 0.5rem;
        font-size: 0.72rem;
        color: #64748b;
    }
    .jrny__foot b { color: #0f172a; font-weight: 700; }
    .inc-hero {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 1.35rem 1.5rem;
        margin-bottom: 1.25rem;
        box-shadow: 0 2px 12px rgba(55, 71, 79, 0.06), 0 8px 24px rgba(38, 166, 154, 0.06);
    }
    .inc-hero__h {
        font-size: 1.25rem;
        font-weight: 700;
        margin: 0 0 0.35rem;
        color: #0f172a;
        letter-spacing: -0.02em;
    }
    .inc-hero__sub {
        margin: 0;
        color: #64748b;
        font-size: 0.9rem;
        line-height: 1.55;
        max-width: 40rem;
    }
    .inc-hero__badges {
        display: flex;
        flex-wrap: wrap;
        gap: 0.45rem;
        margin-top: 0.85rem;
    }
    .inc-badge {
        display: inline-flex;
        align-items: center;
        padding: 0.28rem 0.65rem;
        border-radius: 999px;
        font-size: 0.75rem;
        font-weight: 600;
        border: 1px solid #e2e8f0;
        background: #f8fafc;
        color: #334155;
    }
    .inc-badge--accent { background: #e0f2f1; border-color: #b2dfdb; color: #00695c; }
    .inc-badge--muted { background: #f1f5f9; color: #475569; }

    .inc-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
        margin-bottom: 1.25rem;
    }
    .inc-stat {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 1.1rem 1.2rem;
        box-shadow: 0 2px 12px rgba(55, 71, 79, 0.06), 0 8px 24px rgba(38, 166, 154, 0.06);
    }
    .inc-stat__label {
        font-size: 0.7rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        color: #64748b;
        margin: 0 0 0.35rem;
    }
    .inc-stat__val {
        font-size: 1.85rem;
        font-weight: 700;
        margin: 0;
        line-height: 1;
        color: #0f172a;
    }
    .inc-stat__hint { font-size: 0.8rem; color: #64748b; margin: 0.4rem 0 0; }

    .inc-panel {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 1.25rem 1.35rem;
        margin-bottom: 1.25rem;
        box-shadow: 0 2px 12px rgba(55, 71, 79, 0.06), 0 8px 24px rgba(38, 166, 154, 0.06);
    }
    .inc-panel__h {
        font-size: 0.95rem;
        font-weight: 700;
        margin: 0 0 1rem;
        color: #0f172a;
        letter-spacing: -0.02em;
        display: flex;
        align-items: center;
        gap: 0.45rem;
    }
    .inc-dl { display: grid; grid-template-columns: 1fr 2fr; gap: 0.6rem 1rem; font-size: 0.88rem; color: #334155; }
    .inc-dl dt { font-weight: 600; color: #64748b; margin: 0; }
    .inc-dl dd { margin: 0; }

    .inc-soon {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 0.85rem;
    }
    .inc-soon__card {
        border-radius: 10px;
        padding: 1rem;
        min-height: 100px;
        border: 2px dashed #b2dfdb;
        background: #f7fffe;
        color: #00695c;
    }
    .inc-soon__card h3 {
        font-size: 0.9rem;
        font-weight: 700;
        margin: 0 0 0.35rem;
        color: #00897b;
    }
    .inc-soon__card p { margin: 0; font-size: 0.8rem; color: #64748b; line-height: 1.45; }

    .inc-table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
    .inc-table th, .inc-table td { text-align: left; padding: 0.55rem 0.45rem; border-bottom: 1px solid #e2e8f0; }
    .inc-table th { color: #64748b; font-weight: 600; font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.04em; }
    .inc-pill { display: inline-block; padding: 0.2rem 0.55rem; border-radius: 999px; font-size: 0.72rem; font-weight: 700; }
    .inc-pill--ok { background: #d1fae5; color: #047857; }
    .inc-pill--open { background: #fef3c7; color: #b45309; }

    .inc-hero__top {
        display: flex;
        flex-wrap: wrap;
        gap: 1rem;
        align-items: flex-start;
        justify-content: space-between;
        margin-bottom: 0.75rem;
    }
    .inc-hero__intro { flex: 1; min-width: 0; }
    .inc-btn-mentor {
        flex-shrink: 0;
        background: linear-gradient(135deg, #00897b, #26a69a);
        color: #fff;
        border: none;
        padding: 0.55rem 1.1rem;
        border-radius: 10px;
        font-weight: 600;
        font-size: 0.875rem;
        cursor: pointer;
        font-family: inherit;
        box-shadow: 0 10px 24px rgba(38, 166, 154, 0.22);
    }
    .inc-btn-mentor:hover { filter: brightness(1.05); }
    .inc-hero__actions { display: flex; flex-wrap: wrap; gap: 0.5rem; flex-shrink: 0; }
    .inc-btn-service {
        flex-shrink: 0;
        background: #fff;
        color: #00695c;
        border: 1px solid #b2dfdb;
        padding: 0.55rem 1.1rem;
        border-radius: 10px;
        font-weight: 600;
        font-size: 0.875rem;
        cursor: pointer;
        font-family: inherit;
    }
    .inc-btn-service:hover { background: #e0f2f1; }

    .bmc-docs { display: flex; flex-direction: column; gap: 0.65rem; }
    .bmc-doc {
        display: flex;
        flex-wrap: wrap;
        gap: 0.55rem;
        align-items: center;
        justify-content: space-between;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        padding: 0.7rem 0.85rem;
        background: #f8fafc;
    }
    .bmc-doc__name { font-size: 0.85rem; font-weight: 600; color: #0f172a; }
    .bmc-doc__actions { display: flex; gap: 0.4rem; }
    .bmc-doc__btn {
        display: inline-flex;
        align-items: center;
        padding: 0.32rem 0.7rem;
        border-radius: 8px;
        font-size: 0.78rem;
        font-weight: 700;
        text-decoration: none;
        border: 1px solid #b2dfdb;
        background: #e0f2f1;
        color: #00695c;
    }
    .bmc-doc__btn--dl { background: #fff; color: #0f172a; border-color: #e2e8f0; }
    .bmc-viewer {
        display: none;
        position: fixed;
        inset: 0;
        z-index: 320;
        align-items: center;
        justify-content: center;
        padding: 1rem;
    }
    .bmc-viewer.is-open { display: flex; }
    .bmc-viewer__backdrop { position: absolute; inset: 0; background: rgba(15, 23, 42, 0.55); }
    .bmc-viewer__panel {
        position: relative;
        background: #fff;
        border-radius: 12px;
        width: min(920px, 100%);
        height: min(88vh, 780px);
        display: flex;
        flex-direction: column;
        overflow: hidden;
        box-shadow: 0 25px 50px rgba(0,0,0,0.2);
    }
    .bmc-viewer__bar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.5rem;
        padding: 0.65rem 0.85rem;
        border-bottom: 1px solid #e2e8f0;
    }
    .bmc-viewer__bar strong { font-size: 0.85rem; }
    .bmc-viewer__frame { flex: 1; border: 0; width: 100%; background: #f1f5f9; }

    .svc-groups { display: flex; flex-direction: column; gap: 0.85rem; max-height: 18rem; overflow: auto; margin-bottom: 1rem; }
    .svc-group__h { margin: 0 0 0.4rem; font-size: 0.72rem; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.06em; }
    .svc-picks { display: grid; grid-template-columns: repeat(2, 1fr); gap: 0.4rem; }
    .svc-pick {
        text-align: left;
        padding: 0.45rem 0.55rem;
        border: 1.5px solid #e2e8f0;
        border-radius: 8px;
        background: #fff;
        cursor: pointer;
        font-family: inherit;
        font-size: 0.75rem;
        font-weight: 600;
        color: #0f172a;
    }
    .svc-pick:hover { border-color: #80cbc4; }
    .svc-pick.is-selected { border-color: #26a69a; background: #e0f2f1; }

    .inc-flash {
        padding: 0.65rem 1rem;
        border-radius: 10px;
        font-size: 0.88rem;
        margin-bottom: 1rem;
        border: 1px solid #e2e8f0;
    }
    .inc-flash--ok { background: #ecfdf5; border-color: #a7f3d0; color: #047857; }
    .inc-flash--err { background: #fef2f2; border-color: #fecaca; color: #b91c1c; }

    .mentorship-modal {
        display: none;
        position: fixed;
        inset: 0;
        z-index: 300;
        align-items: center;
        justify-content: center;
        padding: 1rem;
    }
    .mentorship-modal.is-open { display: flex; }
    .mentorship-modal__backdrop {
        position: absolute;
        inset: 0;
        background: rgba(15, 23, 42, 0.45);
    }
    .mentorship-modal__panel {
        position: relative;
        background: #fff;
        border-radius: 14px;
        max-width: 28rem;
        width: 100%;
        max-height: min(90vh, 640px);
        overflow: auto;
        padding: 1.35rem 1.4rem;
        box-shadow: 0 25px 50px rgba(0, 0, 0, 0.18);
        border: 1px solid #e2e8f0;
    }
    .mentorship-modal__title {
        margin: 0 0 0.35rem;
        font-size: 1.1rem;
        font-weight: 700;
        color: #0f172a;
    }
    .mentorship-modal__lead {
        margin: 0 0 1rem;
        font-size: 0.85rem;
        color: #64748b;
        line-height: 1.45;
    }
    .mentorship-modal__close {
        position: absolute;
        top: 0.75rem;
        right: 0.75rem;
        width: 2rem;
        height: 2rem;
        border: none;
        background: #f1f5f9;
        border-radius: 8px;
        cursor: pointer;
        font-size: 1.1rem;
        line-height: 1;
        color: #64748b;
    }
    .mentorship-cats {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 0.5rem;
        margin-bottom: 1rem;
    }
    @media (min-width: 420px) {
        .mentorship-cats { grid-template-columns: repeat(3, 1fr); }
    }
    .mentorship-cat {
        display: flex;
        flex-direction: column;
        align-items: center;
        text-align: center;
        gap: 0.25rem;
        padding: 0.6rem 0.45rem;
        border: 2px solid #e2e8f0;
        border-radius: 10px;
        background: #fff;
        cursor: pointer;
        font-family: inherit;
        transition: border-color 0.15s ease, background 0.15s ease;
    }
    .mentorship-cat:hover { border-color: #80cbc4; background: #f7fffe; }
    .mentorship-cat.is-selected {
        border-color: #26a69a;
        background: #e0f2f1;
    }
    .mentorship-cat__label { font-size: 0.78rem; font-weight: 700; color: #0f172a; }
    .mentorship-cat__hint { font-size: 0.65rem; font-weight: 500; color: #64748b; line-height: 1.3; }
    .mentorship-icon-svg { width: 2rem; height: 2rem; color: #00897b; flex-shrink: 0; }

    .mentorship-field label {
        display: block;
        font-size: 0.78rem;
        font-weight: 600;
        color: #475569;
        margin-bottom: 0.35rem;
    }
    .mentorship-field textarea {
        width: 100%;
        min-height: 5rem;
        padding: 0.55rem 0.65rem;
        border: 1px solid #cbd5e1;
        border-radius: 8px;
        font-size: 0.9rem;
        font-family: inherit;
        resize: vertical;
    }
    .mentorship-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
        margin-top: 1rem;
        justify-content: flex-end;
    }
    .mentorship-actions button[type="button"] {
        padding: 0.5rem 1rem;
        border-radius: 8px;
        font-weight: 600;
        font-size: 0.875rem;
        cursor: pointer;
        font-family: inherit;
        background: #fff;
        border: 1px solid #e2e8f0;
        color: #475569;
    }
    .mentorship-actions button[type="submit"] {
        padding: 0.5rem 1rem;
        border-radius: 8px;
        font-weight: 600;
        font-size: 0.875rem;
        cursor: pointer;
        font-family: inherit;
        border: none;
        background: linear-gradient(135deg, #00897b, #26a69a);
        color: #fff;
    }

    .inc-meet-remind {
        border: 1px solid #99f6e4;
        background: linear-gradient(180deg, #f0fdfa 0%, #fff 70%);
        border-radius: 12px;
        padding: 0.85rem 0.95rem;
        margin: 0 0 0.85rem;
    }
    .inc-meet-remind__h {
        margin: 0 0 0.55rem;
        font-size: 0.78rem;
        font-weight: 800;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        color: #0f766e;
    }
    .inc-meet-item {
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        padding: 0.7rem 0.8rem;
        background: #fff;
    }
    .inc-meet-item + .inc-meet-item { margin-top: 0.55rem; }
    .inc-meet-item__top {
        display: flex;
        flex-wrap: wrap;
        gap: 0.4rem;
        align-items: center;
    }
    .inc-meet-item__title { font-size: 0.9rem; font-weight: 700; color: #0f172a; }
    .inc-meet-item__meta { margin: 0.28rem 0 0; font-size: 0.78rem; color: #475569; }
    .inc-meet-item__agenda { margin: 0.35rem 0 0; font-size: 0.8rem; color: #334155; white-space: pre-wrap; }
    .inc-meet-hist { margin-top: 0.85rem; }
    .inc-meet-hist__h {
        margin: 0 0 0.5rem;
        font-size: 0.78rem;
        font-weight: 800;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        color: #64748b;
    }
    .inc-meet-join {
        display: inline-block;
        margin-top: 0.35rem;
        font-size: 0.78rem;
        font-weight: 700;
        color: #00897b;
        text-decoration: none;
    }
    @media (max-width: 720px) {
        .inc-wrap { min-width: 0; overflow-x: hidden; }
        .inc-hero, .inc-panel, .jrny { padding: 1rem; }
        .inc-hero__h { font-size: 1.12rem; overflow-wrap: anywhere; }
        .inc-hero__intro { min-width: 0; width: 100%; }
        .inc-hero__top {
            flex-direction: column;
            align-items: stretch;
            gap: 0.75rem;
        }
        .inc-hero__actions {
            width: 100%;
            display: grid;
            grid-template-columns: 1fr;
            gap: 0.45rem;
        }
        .inc-btn-mentor,
        .inc-btn-service {
            width: 100%;
            text-align: center;
            box-sizing: border-box;
            white-space: normal;
        }
        .inc-hero__badges { gap: 0.35rem; }
        .inc-badge {
            max-width: 100%;
            white-space: normal;
            line-height: 1.35;
            overflow-wrap: anywhere;
        }
        .inc-dl {
            grid-template-columns: 1fr;
            gap: 0.15rem 0;
        }
        .inc-dl dt { margin-top: 0.45rem; font-size: 0.75rem; }
        .inc-dl dt:first-child { margin-top: 0; }
        .inc-dl dd { overflow-wrap: anywhere; }
        .bmc-doc {
            flex-direction: column;
            align-items: stretch;
        }
        .bmc-doc__name { overflow-wrap: anywhere; }
        .bmc-doc__actions {
            width: 100%;
            display: grid;
            grid-template-columns: 1fr 1fr;
        }
        .bmc-doc__btn { justify-content: center; width: 100%; box-sizing: border-box; }
        .bmc-viewer { padding: 0; align-items: stretch; }
        .bmc-viewer__panel {
            width: 100%;
            height: 100%;
            max-height: none;
            border-radius: 0;
        }
        .inc-soon { grid-template-columns: 1fr; }
        .mentorship-modal { padding: 0.5rem; align-items: flex-end; }
        .mentorship-modal__panel { max-width: 100%; padding: 1.1rem 1rem 1.2rem; }
        .mentorship-cats,
        .svc-picks { grid-template-columns: 1fr; }
        .jrny__foot { flex-direction: column; align-items: flex-start; }
    }
</style>
@endpush

@section('content')
<div class="inc-wrap">
    @if (session('status'))
        <p class="inc-flash inc-flash--ok" role="status">{{ session('status') }}</p>
    @endif
    @if ($errors->any())
        <div class="inc-flash inc-flash--err" role="alert">
            @foreach ($errors->all() as $err)
                <div>{{ $err }}</div>
            @endforeach
        </div>
    @endif

    <div class="inc-layout">
    <div class="inc-main">
    <section class="inc-hero">
        <div class="inc-hero__top">
            <div class="inc-hero__intro">
                <h2 class="inc-hero__h">{{ __('incubatee.welcome', ['name' => $user->name]) }}</h2>
                <p class="inc-hero__sub">{{ __('incubatee.hero_sub') }}</p>
            </div>
            <div class="inc-hero__actions">
                <button type="button" class="inc-btn-mentor" id="openMentorshipModal">{{ __('incubatee.request_mentorship') }}</button>
                <button type="button" class="inc-btn-service" id="openServiceModal">{{ __('incubatee.request_service') }}</button>
            </div>
        </div>
        <div class="inc-hero__badges">
            <span class="inc-badge inc-badge--accent">CFA {{ $submission->application_no ?? '—' }}</span>
            @if($submission->district?->name)
                <span class="inc-badge">{{ $submission->district->name }}</span>
            @endif
            @if($batch?->name)
                <span class="inc-badge inc-badge--muted">{{ __('incubatee.batch', ['name' => $batch->name]) }}</span>
            @endif
            @if($hubName)
                <span class="inc-badge">{{ __('incubatee.hub', ['name' => $hubName]) }}</span>
            @endif
        </div>
    </section>

    <section class="inc-panel">
        <h2 class="inc-panel__h"><span aria-hidden="true">🧩</span> {{ __('incubatee.bmc.title') }}</h2>
        @if (! $bmcCase)
            <p style="margin:0;color:#64748b;font-size:0.9rem">{{ __('incubatee.bmc.empty') }}</p>
        @else
            @php
                $bmcApproved = $bmcCase->status === \App\Models\ServiceCase::STATUS_APPROVED;
                $bmcDate = $bmcCase->delivered_on ?? $bmcCase->approved_at ?? $bmcCase->updated_at;
            @endphp
            <dl class="inc-dl" style="margin-bottom:1rem">
                <dt>{{ __('incubatee.bmc.status') }}</dt>
                <dd>
                    @if ($bmcApproved)
                        <span class="inc-pill inc-pill--ok">{{ __('incubatee.bmc.delivered') }}</span>
                    @else
                        <span class="inc-pill inc-pill--open">{{ __('incubatee.bmc.in_progress') }}</span>
                    @endif
                </dd>
                <dt>{{ __('incubatee.bmc.date') }}</dt>
                <dd>{{ \App\Support\Ist::date($bmcDate) }}</dd>
                <dt>{{ __('incubatee.bmc.reference') }}</dt>
                <dd>{{ $bmcCase->reference_number ?: '—' }}</dd>
            </dl>
            <p style="margin:0 0 0.55rem;font-size:0.8rem;font-weight:700;color:#334155">{{ __('incubatee.bmc.document') }}</p>
            @if (($bmcAttachments ?? collect())->isEmpty())
                <p style="margin:0;color:#64748b;font-size:0.88rem">{{ __('incubatee.bmc.no_document') }}</p>
            @else
                <div class="bmc-docs">
                    @foreach ($bmcAttachments as $att)
                        <div class="bmc-doc">
                            <span class="bmc-doc__name">{{ $att->original_name ?: 'BMC' }}</span>
                            <span class="bmc-doc__actions">
                                @if (\App\Support\ServiceCaseAttachmentFile::isPreviewable($att))
                                    <button type="button" class="bmc-doc__btn bmc-view-btn"
                                        data-src="{{ route('incubatee.bmc-documents.view', $att) }}"
                                        data-download="{{ route('incubatee.bmc-documents.download', $att) }}"
                                        data-name="{{ $att->original_name ?: 'BMC' }}">{{ __('incubatee.bmc.view') }}</button>
                                @endif
                                <a class="bmc-doc__btn bmc-doc__btn--dl" href="{{ route('incubatee.bmc-documents.download', $att) }}">{{ __('incubatee.bmc.download') }}</a>
                            </span>
                        </div>
                    @endforeach
                </div>
            @endif
        @endif
    </section>

    <section class="inc-panel">
        <h2 class="inc-panel__h"><span aria-hidden="true">📅</span> {{ __('incubatee.meetings_section') }}</h2>
        @php
            $upcomingMeetings = $upcomingMeetings ?? collect();
            $historyMeetings = $historyMeetings ?? collect();
        @endphp
        @if ($upcomingMeetings->isEmpty() && $historyMeetings->isEmpty())
            <p style="margin:0;color:#64748b;font-size:0.9rem">{{ __('incubatee.meetings_empty') }}</p>
        @else
            @if ($upcomingMeetings->isNotEmpty())
                <div class="inc-meet-remind">
                    <p class="inc-meet-remind__h">{{ __('incubatee.meetings_upcoming') }}</p>
                    @foreach ($upcomingMeetings as $mtg)
                        @include('incubatee.partials.meeting-item', ['meeting' => $mtg, 'upcoming' => true])
                    @endforeach
                </div>
            @else
                <p style="margin:0 0 0.75rem;color:#64748b;font-size:0.88rem">{{ __('incubatee.meetings_none_upcoming') }}</p>
            @endif
            @if ($historyMeetings->isNotEmpty())
                <div class="inc-meet-hist">
                    <p class="inc-meet-hist__h">{{ __('incubatee.meetings_history') }}</p>
                    @foreach ($historyMeetings as $mtg)
                        @include('incubatee.partials.meeting-item', ['meeting' => $mtg, 'upcoming' => false])
                    @endforeach
                </div>
            @endif
        @endif
    </section>

    <section class="inc-panel">
        <h2 class="inc-panel__h"><span aria-hidden="true">👤</span> {{ __('incubatee.profile') }}</h2>
        <dl class="inc-dl">
            <dt>{{ __('incubatee.applicant') }}</dt>
            <dd>{{ $submission->applicant_name ?: '—' }}</dd>
            <dt>{{ __('incubatee.phone') }}</dt>
            <dd>{{ $submission->phone ?: '—' }}</dd>
            <dt>{{ __('incubatee.email') }}</dt>
            <dd>{{ $displayEmail }}</dd>
            <dt>{{ __('incubatee.business_stage') }}</dt>
            <dd>{{ $displayFormStage }}</dd>
            <dt>{{ __('incubatee.product') }}</dt>
            <dd>{{ $displayProduct }}</dd>
        </dl>
    </section>

    <section class="inc-panel">
        <h2 class="inc-panel__h"><span aria-hidden="true">🤝</span> {{ __('incubatee.mentorship_section') }}</h2>
        @if (($mentorshipRequests ?? collect())->isEmpty())
            <p style="margin:0;color:#64748b;font-size:0.9rem">{{ __('incubatee.mentorship_empty') }}</p>
        @else
            <ul style="list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:0.55rem">
                @foreach ($mentorshipRequests as $mr)
                    @php
                        $catMeta = config('mentorship.categories.'.$mr->category, []);
                        $cat = app()->getLocale() === 'hi'
                            ? ($catMeta['label_hi'] ?? $catMeta['label'] ?? $mr->category)
                            : ($catMeta['label'] ?? $mr->category);
                    @endphp
                    <li style="border:1px solid #e2e8f0;border-radius:10px;padding:0.65rem 0.75rem">
                        <div style="display:flex;flex-wrap:wrap;gap:0.4rem;align-items:center">
                            <strong style="font-size:0.85rem">{{ $cat }}</strong>
                            <span style="font-size:0.68rem;font-weight:700;text-transform:uppercase;letter-spacing:0.04em;color:#64748b">{{ str_replace('_', ' ', $mr->status) }}</span>
                        </div>
                        @if ($mr->session)
                            <p style="margin:0.3rem 0 0;font-size:0.78rem;color:#475569">
                                {{ \App\Support\Ist::datetime($mr->session->scheduled_at) }}
                                @if ($mr->session->meeting_link && $mr->isScheduled())
                                    · <a href="{{ $mr->session->meeting_link }}" target="_blank" rel="noopener">{{ __('incubatee.join_meeting') }}</a>
                                @endif
                            </p>
                        @endif
                        @if ($mr->incubateeCanCancel())
                            <form method="post" action="{{ route('incubatee.mentorship-requests.cancel', $mr) }}" style="margin-top:0.4rem" onsubmit="return confirm(@json(__('incubatee.cancel_confirm')));">
                                @csrf
                                <button type="submit" style="background:none;border:0;color:#b91c1c;font-size:0.75rem;font-weight:600;cursor:pointer;padding:0">{{ __('incubatee.cancel') }}</button>
                            </form>
                        @endif
                    </li>
                @endforeach
            </ul>
            <p style="margin:0.75rem 0 0"><a href="{{ route('incubatee.mentorship.index') }}" style="font-size:0.82rem;font-weight:700;color:#00897b">{{ __('incubatee.open_mentorship_page') }}</a></p>
        @endif
    </section>

    <section class="inc-panel">
        <h2 class="inc-panel__h"><span aria-hidden="true">🛠️</span> {{ __('incubatee.service_section') }}</h2>
        @if (($serviceRequests ?? collect())->isEmpty())
            <p style="margin:0;color:#64748b;font-size:0.9rem">{{ __('incubatee.service_empty') }}</p>
        @else
            <ul style="list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:0.55rem">
                @foreach ($serviceRequests as $sr)
                    <li style="border:1px solid #e2e8f0;border-radius:10px;padding:0.65rem 0.75rem">
                        <div style="display:flex;flex-wrap:wrap;gap:0.4rem;align-items:center">
                            <strong style="font-size:0.85rem">{{ $sr->service ? \App\Support\IncubateeServiceCatalog::serviceLabel($sr->service) : '—' }}</strong>
                            <span style="font-size:0.68rem;font-weight:700;text-transform:uppercase;letter-spacing:0.04em;color:#64748b">{{ str_replace('_', ' ', $sr->status) }}</span>
                        </div>
                        <p style="margin:0.3rem 0 0;font-size:0.75rem;color:#64748b">{{ \App\Support\Ist::datetime($sr->created_at) }}</p>
                        @if ($sr->incubateeCanCancel())
                            <form method="post" action="{{ route('incubatee.service-requests.cancel', $sr) }}" style="margin-top:0.4rem" onsubmit="return confirm(@json(__('incubatee.cancel_confirm')));">
                                @csrf
                                <button type="submit" style="background:none;border:0;color:#b91c1c;font-size:0.75rem;font-weight:600;cursor:pointer;padding:0">{{ __('incubatee.cancel') }}</button>
                            </form>
                        @endif
                    </li>
                @endforeach
            </ul>
            <p style="margin:0.75rem 0 0"><a href="{{ route('incubatee.service-requests.index') }}" style="font-size:0.82rem;font-weight:700;color:#00897b">{{ __('incubatee.open_service_page') }}</a></p>
        @endif
    </section>

    <section class="inc-panel">
        <div class="inc-soon">
            <div class="inc-soon__card">
                <h3>{{ __('incubatee.soon_product') }}</h3>
                <p>{{ __('incubatee.soon_product_p') }}</p>
            </div>
            <div class="inc-soon__card">
                <h3>{{ __('incubatee.soon_pitch') }}</h3>
                <p>{{ __('incubatee.soon_pitch_p') }}</p>
            </div>
            <div class="inc-soon__card">
                <h3>{{ __('incubatee.soon_milestones') }}</h3>
                <p>{{ __('incubatee.soon_milestones_p') }}</p>
            </div>
        </div>
    </section>

    <section class="inc-panel">
        <h2 class="inc-panel__h"><span aria-hidden="true">📁</span> {{ __('incubatee.docs_title') }}</h2>
        <p style="margin:0 0 0.75rem;color:#64748b;font-size:0.9rem;line-height:1.5;">
            {{ __('incubatee.docs_lead') }}
        </p>
        <a href="{{ route('incubatee.documents.index') }}" style="display:inline-flex;align-items:center;gap:0.4rem;padding:0.5rem 0.85rem;border-radius:9px;background:#e0f2f1;border:1px solid #b2dfdb;color:#00695c;text-decoration:none;font-size:0.82rem;font-weight:700;">
            {{ __('incubatee.open_docs') }}
        </a>
    </section>
    </div>{{-- /.inc-main --}}

    <aside class="inc-side" aria-label="Your journey">
        @php
            $doneCount = collect($journey)->where('status', 'done')->count();
            $totalCount = count($journey);
            $onTrack = $doneCount >= 2;
            $firstStep = collect($journey)->firstWhere('key', 'cfa');
            $startDate = $firstStep['at'] ?? null;
        @endphp
        <section class="jrny">
            <div class="jrny__head">
                <h3 class="jrny__h"><span aria-hidden="true">🛣️</span> {{ __('incubatee.journey.title') }}</h3>
            </div>
            <p class="jrny__meta">
                @if ($startDate)
                    {!! __('incubatee.journey.started', ['date' => '<b>'.e(\App\Support\Ist::date($startDate)).'</b>']) !!}
                @else
                    {{ __('incubatee.journey.starting_soon') }}
                @endif
                <span class="jrny__status @if ($onTrack) jrny__status--on-track @endif">{{ $onTrack ? __('incubatee.journey.on_track') : __('incubatee.journey.early_days') }}</span>
            </p>

            <ul class="jrny__list">
                @foreach ($journey as $step)
                    @php
                        $cls = 'jrny__item jrny__item--'.$step['status'];
                        $at = $step['at'] ?? null;
                    @endphp
                    <li class="{{ $cls }}">
                        <span class="jrny__dot" aria-hidden="true">
                            @if ($step['status'] === 'done')
                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                            @elseif ($step['status'] === 'current')
                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="12" r="4"/></svg>
                            @else
                                <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="8"/></svg>
                            @endif
                        </span>
                        <div class="jrny__body">
                            <p class="jrny__title">
                                <span class="jrny__emoji" aria-hidden="true">{{ $step['icon'] }}</span>
                                {{ $step['title'] }}
                            </p>
                            @if ($at)
                                <p class="jrny__date">{{ \App\Support\Ist::date($at) }}</p>
                            @elseif ($step['status'] === 'current')
                                <p class="jrny__date">{{ __('incubatee.journey.up_next') }}</p>
                            @else
                                <p class="jrny__date">{{ __('incubatee.journey.coming_up') }}</p>
                            @endif
                            @if (!empty($step['detail']))
                                <p class="jrny__detail">{{ $step['detail'] }}</p>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>

            <div class="jrny__foot">
                <span>{{ __('incubatee.journey.progress') }}: <b>{{ $doneCount }}/{{ $totalCount }}</b></span>
                <span>{{ __('incubatee.journey.mentorship') }}: <b>{{ $mentorshipCount }}</b></span>
            </div>
        </section>
    </aside>
    </div>{{-- /.inc-layout --}}

    <div class="mentorship-modal" id="mentorshipModal" role="dialog" aria-modal="true" aria-labelledby="mentorshipModalTitle" hidden>
        <div class="mentorship-modal__backdrop" id="mentorshipModalBackdrop" tabindex="-1"></div>
        <div class="mentorship-modal__panel">
            <button type="button" class="mentorship-modal__close" id="closeMentorshipModal" aria-label="{{ __('incubatee.close') }}">&times;</button>
            <h3 class="mentorship-modal__title" id="mentorshipModalTitle">{{ __('incubatee.modal_title') }}</h3>
            <p class="mentorship-modal__lead">{{ __('incubatee.modal_lead') }}</p>

            <form method="post" action="{{ route('incubatee.mentorship-requests.store') }}" id="mentorshipForm">
                @csrf
                <input type="hidden" name="category" id="mentorshipCategoryField" value="">

                <div class="mentorship-cats" role="group" aria-label="{{ __('incubatee.modal_title') }}">
                    @foreach(config('mentorship.categories') as $slug => $meta)
                        <button type="button" class="mentorship-cat" data-category="{{ $slug }}">
                            @include('incubatee.partials.mentorship-icon', ['slug' => $slug])
                            <span class="mentorship-cat__label">{{ app()->getLocale() === 'hi' ? ($meta['label_hi'] ?? $meta['label']) : $meta['label'] }}</span>
                            <span class="mentorship-cat__hint">{{ app()->getLocale() === 'hi' ? ($meta['hint_hi'] ?? $meta['hint']) : $meta['hint'] }}</span>
                        </button>
                    @endforeach
                </div>

                <div class="mentorship-field">
                    <label for="mentorshipComment">{{ __('incubatee.your_message') }}</label>
                    <textarea id="mentorshipComment" name="comment" maxlength="2000" placeholder="{{ __('incubatee.message_placeholder') }}"></textarea>
                </div>

                <div class="mentorship-actions">
                    <button type="button" id="cancelMentorshipModal">{{ __('incubatee.cancel') }}</button>
                    <button type="submit">{{ __('incubatee.send_request') }}</button>
                </div>
            </form>
        </div>
    </div>

    <div class="mentorship-modal" id="serviceModal" role="dialog" aria-modal="true" aria-labelledby="serviceModalTitle" hidden>
        <div class="mentorship-modal__backdrop" id="serviceModalBackdrop" tabindex="-1"></div>
        <div class="mentorship-modal__panel" style="max-width:34rem">
            <button type="button" class="mentorship-modal__close" id="closeServiceModal" aria-label="{{ __('incubatee.close') }}">&times;</button>
            <h3 class="mentorship-modal__title" id="serviceModalTitle">{{ __('incubatee.service_modal_title') }}</h3>
            <p class="mentorship-modal__lead">{{ __('incubatee.service_modal_lead') }}</p>

            <form method="post" action="{{ route('incubatee.service-requests.store') }}" id="serviceForm">
                @csrf
                <input type="hidden" name="service_id" id="serviceIdField" value="">
                <div class="svc-groups">
                    @forelse ($serviceGroups ?? [] as $slug => $services)
                        <div>
                            <p class="svc-group__h">{{ \App\Support\IncubateeServiceCatalog::categoryLabel($slug) }}</p>
                            <div class="svc-picks">
                                @foreach ($services as $svc)
                                    <button type="button" class="svc-pick" data-service-id="{{ $svc->id }}">
                                        {{ \App\Support\IncubateeServiceCatalog::serviceLabel($svc) }}
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    @empty
                        <p style="margin:0;color:#64748b;font-size:0.85rem">{{ __('incubatee.service_page.no_services') }}</p>
                    @endforelse
                </div>
                <div class="mentorship-field">
                    <label for="serviceComment">{{ __('incubatee.your_message') }}</label>
                    <textarea id="serviceComment" name="comment" maxlength="2000" placeholder="{{ __('incubatee.message_placeholder') }}"></textarea>
                </div>
                <div class="mentorship-actions">
                    <button type="button" id="cancelServiceModal">{{ __('incubatee.cancel') }}</button>
                    <button type="submit">{{ __('incubatee.send_request') }}</button>
                </div>
            </form>
        </div>
    </div>

    <div class="bmc-viewer" id="bmcViewer" hidden>
        <div class="bmc-viewer__backdrop" id="bmcViewerBackdrop"></div>
        <div class="bmc-viewer__panel">
            <div class="bmc-viewer__bar">
                <strong id="bmcViewerTitle">{{ __('incubatee.bmc.open_viewer') }}</strong>
                <div class="bmc-doc__actions">
                    <a class="bmc-doc__btn bmc-doc__btn--dl" id="bmcViewerDownload" href="#">{{ __('incubatee.bmc.download') }}</a>
                    <button type="button" class="bmc-doc__btn" id="closeBmcViewer">{{ __('incubatee.bmc.close') }}</button>
                </div>
            </div>
            <iframe class="bmc-viewer__frame" id="bmcViewerFrame" title="{{ __('incubatee.bmc.document') }}"></iframe>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const modal = document.getElementById('mentorshipModal');
    const openBtn = document.getElementById('openMentorshipModal');
    const closeBtn = document.getElementById('closeMentorshipModal');
    const cancelBtn = document.getElementById('cancelMentorshipModal');
    const backdrop = document.getElementById('mentorshipModalBackdrop');
    const form = document.getElementById('mentorshipForm');
    const categoryField = document.getElementById('mentorshipCategoryField');

    function openModal() {
        modal.hidden = false;
        modal.classList.add('is-open');
        document.body.style.overflow = 'hidden';
    }
    function closeModal() {
        modal.classList.remove('is-open');
        modal.hidden = true;
        document.body.style.overflow = '';
    }

    openBtn?.addEventListener('click', openModal);
    closeBtn?.addEventListener('click', closeModal);
    cancelBtn?.addEventListener('click', closeModal);
    backdrop?.addEventListener('click', closeModal);
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && modal.classList.contains('is-open')) closeModal();
    });

    document.querySelectorAll('.mentorship-cat').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.querySelectorAll('.mentorship-cat').forEach(function (b) { b.classList.remove('is-selected'); });
            btn.classList.add('is-selected');
            categoryField.value = btn.getAttribute('data-category') || '';
        });
    });

    form?.addEventListener('submit', function (e) {
        if (!categoryField.value.trim()) {
            e.preventDefault();
            alert(@json(__('incubatee.select_category')));
        }
    });

    function wireModal(modalId, openId, closeId, cancelId, backdropId) {
        const modal = document.getElementById(modalId);
        if (!modal) return {
            open: function () {},
            close: function () {}
        };
        function open() { modal.hidden = false; modal.classList.add('is-open'); document.body.style.overflow = 'hidden'; }
        function close() { modal.classList.remove('is-open'); modal.hidden = true; document.body.style.overflow = ''; }
        document.getElementById(openId)?.addEventListener('click', open);
        document.getElementById(closeId)?.addEventListener('click', close);
        document.getElementById(cancelId)?.addEventListener('click', close);
        document.getElementById(backdropId)?.addEventListener('click', close);
        return { open: open, close: close, el: modal };
    }
    const serviceUi = wireModal('serviceModal', 'openServiceModal', 'closeServiceModal', 'cancelServiceModal', 'serviceModalBackdrop');
    const serviceField = document.getElementById('serviceIdField');
    document.querySelectorAll('.svc-pick').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.querySelectorAll('.svc-pick').forEach(function (b) { b.classList.remove('is-selected'); });
            btn.classList.add('is-selected');
            if (serviceField) serviceField.value = btn.getAttribute('data-service-id') || '';
        });
    });
    document.getElementById('serviceForm')?.addEventListener('submit', function (e) {
        if (!serviceField || !serviceField.value.trim()) {
            e.preventDefault();
            alert(@json(__('incubatee.select_service')));
        }
    });

    const viewer = document.getElementById('bmcViewer');
    const frame = document.getElementById('bmcViewerFrame');
    const viewerTitle = document.getElementById('bmcViewerTitle');
    const viewerDl = document.getElementById('bmcViewerDownload');
    function closeViewer() {
        if (!viewer) return;
        viewer.classList.remove('is-open');
        viewer.hidden = true;
        if (frame) frame.src = '';
        document.body.style.overflow = '';
    }
    document.querySelectorAll('.bmc-view-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const src = btn.getAttribute('data-src') || '';
            if (viewerTitle) viewerTitle.textContent = btn.getAttribute('data-name') || '';
            if (frame) frame.src = src;
            if (viewerDl) viewerDl.href = btn.getAttribute('data-download') || src;
            viewer.hidden = false;
            viewer.classList.add('is-open');
            document.body.style.overflow = 'hidden';
        });
    });
    document.getElementById('closeBmcViewer')?.addEventListener('click', closeViewer);
    document.getElementById('bmcViewerBackdrop')?.addEventListener('click', closeViewer);
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && serviceUi.el && serviceUi.el.classList.contains('is-open')) serviceUi.close();
        if (e.key === 'Escape' && viewer && viewer.classList.contains('is-open')) closeViewer();
    });
})();
</script>
@endpush
