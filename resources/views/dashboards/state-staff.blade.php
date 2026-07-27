@extends('layouts.admin')

@section('title', 'SPOC dashboard')
@section('heading', 'State staff (SPOC) dashboard')

@section('content')
@php
    $pending = (int) ($pendingApprovals ?? 0);
    $overdue = (int) ($overduePending ?? 0);
    $approved = (int) ($approvedByYou ?? 0);
    $districtCount = $spocDistricts->count();
@endphp

<style>
    :root {
        --ss-bg: #f4f7fb;
        --ss-card: #ffffff;
        --ss-text: #172033;
        --ss-muted: #667085;
        --ss-border: #dfe7f1;
        --ss-teal: #079b91;
        --ss-teal-dark: #05756f;
        --ss-blue: #2563eb;
        --ss-amber: #d97706;
        --ss-red: #dc2626;
        --ss-radius: 18px;
        --ss-shadow: 0 12px 30px rgba(15, 42, 68, 0.07);
    }
    .ss-dashboard {
        width: 100%;
        max-width: none;
        color: var(--ss-text);
        font-family: 'DM Sans', system-ui, sans-serif;
    }
    .ss-welcome {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        padding: 1.2rem 1.35rem;
        margin-bottom: 1rem;
        border: 1px solid rgba(7, 155, 145, 0.18);
        border-radius: var(--ss-radius);
        background:
            radial-gradient(circle at 95% 0%, rgba(37, 99, 235, 0.12), transparent 34%),
            linear-gradient(120deg, #ffffff 0%, #f0fdfa 100%);
        box-shadow: var(--ss-shadow);
    }
    .ss-eyebrow {
        margin: 0 0 0.3rem;
        color: var(--ss-teal-dark);
        font-size: 0.72rem;
        font-weight: 800;
        letter-spacing: 0.09em;
        text-transform: uppercase;
    }
    .ss-welcome h1 {
        margin: 0;
        color: var(--ss-text);
        font-size: clamp(1.35rem, 2vw, 1.9rem);
        line-height: 1.15;
        letter-spacing: -0.03em;
    }
    .ss-welcome p {
        max-width: 48rem;
        margin: 0.4rem 0 0;
        color: var(--ss-muted);
        font-size: 0.9rem;
        line-height: 1.5;
    }
    .ss-actions { display: flex; flex-wrap: wrap; gap: 0.55rem; flex: 0 0 auto; }
    .ss-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.4rem;
        min-height: 2.6rem;
        padding: 0.62rem 0.95rem;
        border: 1px solid transparent;
        border-radius: 12px;
        font-size: 0.82rem;
        font-weight: 800;
        line-height: 1;
        text-decoration: none;
        transition: transform 0.15s ease, box-shadow 0.15s ease, background 0.15s ease;
    }
    .ss-btn:hover { transform: translateY(-1px); }
    .ss-btn--primary {
        color: #fff;
        background: linear-gradient(135deg, var(--ss-teal-dark), var(--ss-teal));
        box-shadow: 0 7px 16px rgba(7, 155, 145, 0.22);
    }
    .ss-btn--secondary { color: #344054; background: #fff; border-color: var(--ss-border); }
    .ss-kpis {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 0.85rem;
        margin-bottom: 1rem;
    }
    .ss-kpi {
        position: relative;
        min-height: 8.25rem;
        padding: 1rem 1.05rem;
        overflow: hidden;
        border: 1px solid var(--ss-border);
        border-radius: 16px;
        background: var(--ss-card);
        box-shadow: var(--ss-shadow);
    }
    .ss-kpi::after {
        position: absolute;
        right: -1.25rem;
        bottom: -1.85rem;
        width: 5.5rem;
        height: 5.5rem;
        border-radius: 50%;
        background: rgba(7, 155, 145, 0.07);
        content: '';
    }
    .ss-kpi--warning { border-color: #fed7aa; background: #fffaf3; }
    .ss-kpi--warning::after { background: rgba(217, 119, 6, 0.09); }
    .ss-kpi__top { display: flex; align-items: center; justify-content: space-between; gap: 0.6rem; }
    .ss-kpi__label {
        color: #667085;
        font-size: 0.7rem;
        font-weight: 800;
        letter-spacing: 0.06em;
        text-transform: uppercase;
    }
    .ss-kpi__icon {
        display: grid;
        width: 2rem;
        height: 2rem;
        place-items: center;
        border-radius: 10px;
        color: var(--ss-teal-dark);
        background: #e8f8f6;
    }
    .ss-kpi--warning .ss-kpi__icon { color: var(--ss-amber); background: #ffedd5; }
    .ss-kpi__value {
        margin-top: 0.5rem;
        color: var(--ss-text);
        font-size: 2rem;
        font-weight: 850;
        line-height: 1;
        letter-spacing: -0.05em;
    }
    .ss-kpi--warning .ss-kpi__value { color: var(--ss-amber); }
    .ss-kpi__sub { position: relative; z-index: 1; margin-top: 0.45rem; color: var(--ss-muted); font-size: 0.77rem; }
    .ss-main-grid {
        display: grid;
        grid-template-columns: minmax(0, 1.65fr) minmax(18rem, 0.85fr);
        gap: 1rem;
        align-items: start;
    }
    .ss-panel {
        border: 1px solid var(--ss-border);
        border-radius: var(--ss-radius);
        background: var(--ss-card);
        box-shadow: var(--ss-shadow);
    }
    .ss-panel__head {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 1rem;
        padding: 1.1rem 1.2rem;
        border-bottom: 1px solid #edf1f6;
    }
    .ss-panel__head h2, .ss-panel__head h3 { margin: 0; color: var(--ss-text); font-size: 1rem; }
    .ss-panel__head p { margin: 0.28rem 0 0; color: var(--ss-muted); font-size: 0.8rem; }
    .ss-panel__body { padding: 1.15rem 1.2rem; }
    .ss-queue-callout {
        display: grid;
        grid-template-columns: auto 1fr auto;
        gap: 1rem;
        align-items: center;
        padding: 1.1rem;
        border: 1px solid #ccece8;
        border-radius: 14px;
        background: linear-gradient(120deg, #effcf9, #f8fbff);
    }
    .ss-queue-callout--urgent { border-color: #fed7aa; background: linear-gradient(120deg, #fff7ed, #fffdf8); }
    .ss-queue-number {
        display: grid;
        width: 4rem;
        height: 4rem;
        place-items: center;
        border-radius: 15px;
        color: #fff;
        background: linear-gradient(135deg, var(--ss-teal-dark), var(--ss-teal));
        font-size: 1.45rem;
        font-weight: 850;
    }
    .ss-queue-callout--urgent .ss-queue-number { background: linear-gradient(135deg, #b45309, #f59e0b); }
    .ss-queue-copy strong { display: block; margin-bottom: 0.25rem; font-size: 0.95rem; }
    .ss-queue-copy span { display: block; color: var(--ss-muted); font-size: 0.8rem; line-height: 1.45; }
    .ss-note {
        display: flex;
        gap: 0.65rem;
        align-items: flex-start;
        margin-top: 0.85rem;
        padding: 0.8rem 0.9rem;
        border-radius: 12px;
        color: #475467;
        background: #f7f9fc;
        font-size: 0.78rem;
        line-height: 1.45;
    }
    .ss-note i { margin-top: 0.12rem; color: var(--ss-blue); }
    .ss-district-list { display: flex; flex-wrap: wrap; gap: 0.5rem; }
    .ss-district {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.55rem 0.7rem;
        border: 1px solid #ccece8;
        border-radius: 11px;
        color: #075e59;
        background: #f0fdfa;
        font-size: 0.8rem;
        font-weight: 750;
    }
    .ss-empty { padding: 0.85rem; border-radius: 12px; color: #9a3412; background: #fff7ed; font-size: 0.82rem; }
    .ss-quick-links { display: grid; gap: 0.55rem; }
    .ss-quick-link {
        display: flex;
        align-items: center;
        gap: 0.7rem;
        padding: 0.72rem 0.78rem;
        border: 1px solid #e5eaf1;
        border-radius: 12px;
        color: #344054;
        background: #fff;
        text-decoration: none;
        transition: border-color 0.15s ease, background 0.15s ease;
    }
    .ss-quick-link:hover { border-color: #8ed7d1; background: #f7fffd; }
    .ss-quick-link__icon {
        display: grid;
        flex: 0 0 2.15rem;
        width: 2.15rem;
        height: 2.15rem;
        place-items: center;
        border-radius: 10px;
        color: var(--ss-teal-dark);
        background: #e8f8f6;
    }
    .ss-quick-link strong { display: block; font-size: 0.82rem; }
    .ss-quick-link span { display: block; margin-top: 0.14rem; color: var(--ss-muted); font-size: 0.71rem; }
    .ss-stack { display: grid; gap: 1rem; }

    @media (max-width: 1100px) {
        .ss-kpis { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .ss-main-grid { grid-template-columns: 1fr; }
        .ss-stack { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }
    @media (max-width: 720px) {
        .ss-welcome { align-items: flex-start; flex-direction: column; }
        .ss-actions, .ss-actions .ss-btn { width: 100%; }
        .ss-kpis, .ss-stack { grid-template-columns: 1fr; }
        .ss-queue-callout { grid-template-columns: auto 1fr; }
        .ss-queue-callout .ss-btn { grid-column: 1 / -1; }
    }
</style>

<div class="ss-dashboard">
    <section class="ss-welcome">
        <div>
            <p class="ss-eyebrow">State Staff · SPOC Workspace</p>
            <h1>Welcome back, {{ $user->name }}</h1>
            <p>Review district service cases, clear overdue decisions and monitor your assigned districts from one place.</p>
        </div>
        <div class="ss-actions">
            <a href="{{ route('spoc.service-cases.index') }}" class="ss-btn ss-btn--primary">
                <i class="fa-solid fa-inbox"></i> Open approval queue
            </a>
            <a href="{{ route('spoc.acceleration-services.dashboard') }}" class="ss-btn ss-btn--secondary">
                <i class="fa-solid fa-chart-line"></i> Acceleration services
            </a>
        </div>
    </section>

    <section class="ss-kpis" aria-label="SPOC summary">
        <article class="ss-kpi">
            <div class="ss-kpi__top"><span class="ss-kpi__label">Assigned districts</span><span class="ss-kpi__icon"><i class="fa-solid fa-location-dot"></i></span></div>
            <div class="ss-kpi__value">{{ number_format($districtCount) }}</div>
            <div class="ss-kpi__sub">Districts currently mapped to you</div>
        </article>
        <article class="ss-kpi">
            <div class="ss-kpi__top"><span class="ss-kpi__label">Pending approvals</span><span class="ss-kpi__icon"><i class="fa-solid fa-inbox"></i></span></div>
            <div class="ss-kpi__value">{{ number_format($pending) }}</div>
            <div class="ss-kpi__sub">Cases waiting for your decision</div>
        </article>
        <article class="ss-kpi {{ $overdue > 0 ? 'ss-kpi--warning' : '' }}">
            <div class="ss-kpi__top"><span class="ss-kpi__label">Overdue</span><span class="ss-kpi__icon"><i class="fa-solid fa-clock"></i></span></div>
            <div class="ss-kpi__value">{{ number_format($overdue) }}</div>
            <div class="ss-kpi__sub">Pending cases beyond SLA</div>
        </article>
        <article class="ss-kpi">
            <div class="ss-kpi__top"><span class="ss-kpi__label">Approved</span><span class="ss-kpi__icon"><i class="fa-solid fa-circle-check"></i></span></div>
            <div class="ss-kpi__value">{{ number_format($approved) }}</div>
            <div class="ss-kpi__sub">Approved cases in your queue</div>
        </article>
    </section>

    <div class="ss-main-grid">
        <section class="ss-panel">
            <div class="ss-panel__head">
                <div>
                    <h2>Your approval queue</h2>
                    <p>Start with overdue cases, then review the remaining pending submissions.</p>
                </div>
            </div>
            <div class="ss-panel__body">
                @if ($pending > 0)
                    <div class="ss-queue-callout {{ $overdue > 0 ? 'ss-queue-callout--urgent' : '' }}">
                        <div class="ss-queue-number">{{ number_format($overdue > 0 ? $overdue : $pending) }}</div>
                        <div class="ss-queue-copy">
                            @if ($overdue > 0)
                                <strong>{{ $overdue === 1 ? 'Overdue case needs attention' : 'Overdue cases need attention' }}</strong>
                                <span>These cases have crossed their SLA deadline. Review them before the rest of the queue.</span>
                            @else
                                <strong>{{ $pending === 1 ? 'Case ready for review' : 'Cases ready for review' }}</strong>
                                <span>No overdue cases right now. Continue with the pending approval queue.</span>
                            @endif
                        </div>
                        <a href="{{ route('spoc.service-cases.index') }}" class="ss-btn ss-btn--primary">Review now <i class="fa-solid fa-arrow-right"></i></a>
                    </div>
                @else
                    <div class="ss-queue-callout">
                        <div class="ss-queue-number"><i class="fa-solid fa-check"></i></div>
                        <div class="ss-queue-copy">
                            <strong>Your queue is clear</strong>
                            <span>There are no service cases waiting for your approval at the moment.</span>
                        </div>
                        <a href="{{ route('spoc.service-cases.index') }}" class="ss-btn ss-btn--secondary">View queue</a>
                    </div>
                @endif
                <div class="ss-note">
                    <i class="fa-solid fa-circle-info"></i>
                    <span>You can approve, send back with remarks or reject a case from the approval queue. Counts include your assigned districts.</span>
                </div>
            </div>
        </section>

        <aside class="ss-stack">
            <section class="ss-panel">
                <div class="ss-panel__head">
                    <div><h3>Assigned districts</h3><p>Your current SPOC coverage</p></div>
                </div>
                <div class="ss-panel__body">
                    @if ($spocDistricts->isEmpty())
                        <div class="ss-empty"><strong>No district assigned.</strong><br>Ask the state admin to map at least one district to your account.</div>
                    @else
                        <div class="ss-district-list">
                            @foreach ($spocDistricts as $district)
                                <span class="ss-district" title="{{ $district->hub?->name ? $district->hub->name.' Hub' : 'Assigned district' }}">
                                    <i class="fa-solid fa-location-dot"></i> {{ $district->name }}
                                </span>
                            @endforeach
                        </div>
                    @endif
                </div>
            </section>

            <section class="ss-panel">
                <div class="ss-panel__head">
                    <div><h3>Quick actions</h3><p>Frequently used workspaces</p></div>
                </div>
                <div class="ss-panel__body ss-quick-links">
                    <a href="{{ route('spoc.service-cases.index') }}" class="ss-quick-link">
                        <span class="ss-quick-link__icon"><i class="fa-solid fa-inbox"></i></span>
                        <span><strong>Approval queue</strong><span>Review pending service cases</span></span>
                    </a>
                    <a href="{{ route('spoc.acceleration-services.dashboard') }}" class="ss-quick-link">
                        <span class="ss-quick-link__icon"><i class="fa-solid fa-chart-line"></i></span>
                        <span><strong>Acceleration services</strong><span>Open the 7.2 workspace</span></span>
                    </a>
                    @if (!empty($canSubmitSocialMediaPost))
                        <a href="{{ route('spoc.social-media-posts.create') }}" class="ss-quick-link">
                            <span class="ss-quick-link__icon"><i class="fa-brands fa-instagram"></i></span>
                            <span><strong>Log social media post</strong><span>Add a state-level post</span></span>
                        </a>
                    @endif
                    <a href="{{ route('library.documents.index') }}" class="ss-quick-link">
                        <span class="ss-quick-link__icon"><i class="fa-solid fa-folder-open"></i></span>
                        <span><strong>Documents</strong><span>Open the document repository</span></span>
                    </a>
                </div>
            </section>
        </aside>
    </div>
</div>
@endsection
