@extends('layouts.admin')

@section('body_class', 'admin-app-body--dashboard')

@section('title', 'Dashboard')

@section('heading', 'Incubatee hub')

@push('styles')
<style>
    .inc-wrap { max-width: 72rem; margin: 0 auto; }
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
        box-shadow: 0 4px 18px rgba(15, 23, 42, 0.05);
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
        color: #4338ca;
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
        box-shadow: 0 4px 18px rgba(15, 23, 42, 0.05);
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
    .inc-badge--accent { background: linear-gradient(135deg, #eef2ff, #f5f3ff); border-color: #c7d2fe; color: #4338ca; }
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
        box-shadow: 0 4px 18px rgba(15, 23, 42, 0.05);
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
        box-shadow: 0 4px 18px rgba(15, 23, 42, 0.05);
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
        border: 2px dashed #c7d2fe;
        background: #fafafa;
        color: #4338ca;
    }
    .inc-soon__card h3 {
        font-size: 0.9rem;
        font-weight: 700;
        margin: 0 0 0.35rem;
        color: #3730a3;
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
    .inc-hero__intro { flex: 1; min-width: 12rem; }
    .inc-btn-mentor {
        flex-shrink: 0;
        background: linear-gradient(135deg, #4f46e5, #7c3aed);
        color: #fff;
        border: none;
        padding: 0.55rem 1.1rem;
        border-radius: 10px;
        font-weight: 600;
        font-size: 0.875rem;
        cursor: pointer;
        font-family: inherit;
        box-shadow: 0 10px 24px rgba(99, 102, 241, 0.22);
    }
    .inc-btn-mentor:hover { filter: brightness(1.05); }

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
    .mentorship-cat:hover { border-color: #a5b4fc; background: #f8fafc; }
    .mentorship-cat.is-selected {
        border-color: #6366f1;
        background: #eef2ff;
    }
    .mentorship-cat__label { font-size: 0.78rem; font-weight: 700; color: #0f172a; }
    .mentorship-cat__hint { font-size: 0.65rem; font-weight: 500; color: #64748b; line-height: 1.3; }
    .mentorship-icon-svg { width: 2rem; height: 2rem; color: #4f46e5; flex-shrink: 0; }

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
        background: linear-gradient(135deg, #4f46e5, #6366f1);
        color: #fff;
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
                <h2 class="inc-hero__h">Welcome back, {{ $user->name }}</h2>
                <p class="inc-hero__sub">
                    Your entrepreneur hub — track programme support, and soon showcase your products and pitch.
                </p>
            </div>
            <button type="button" class="inc-btn-mentor" id="openMentorshipModal">Request mentorship</button>
        </div>
        <div class="inc-hero__badges">
            <span class="inc-badge inc-badge--accent">CFA {{ $submission->application_no ?? '—' }}</span>
            @if($submission->district?->name)
                <span class="inc-badge">{{ $submission->district->name }}</span>
            @endif
            @if($batch?->name)
                <span class="inc-badge inc-badge--muted">Batch: {{ $batch->name }}</span>
            @endif
            @if($hubName)
                <span class="inc-badge">Hub: {{ $hubName }}</span>
            @endif
        </div>
    </section>

    <div class="inc-grid">
        <div class="inc-stat">
            <p class="inc-stat__label">Services received</p>
            <p class="inc-stat__val">{{ $servicesCompletedCount }}</p>
            <p class="inc-stat__hint">Delivered under MUY</p>
        </div>
        <div class="inc-stat">
            <p class="inc-stat__label">In progress</p>
            <p class="inc-stat__val">{{ $servicesOpenCount }}</p>
            <p class="inc-stat__hint">Open cases</p>
        </div>
        <div class="inc-stat">
            <p class="inc-stat__label">Total services tracked</p>
            <p class="inc-stat__val">{{ $serviceCases->count() }}</p>
            <p class="inc-stat__hint">On your profile</p>
        </div>
    </div>

    <section class="inc-panel">
        <h2 class="inc-panel__h"><span aria-hidden="true">👤</span> Profile snapshot</h2>
        <dl class="inc-dl">
            <dt>Applicant</dt>
            <dd>{{ $submission->applicant_name ?: '—' }}</dd>
            <dt>Phone</dt>
            <dd>{{ $submission->phone ?: '—' }}</dd>
            <dt>Email</dt>
            <dd>{{ $displayEmail }}</dd>
            <dt>Business stage</dt>
            <dd>{{ $displayFormStage }}</dd>
            <dt>Product / focus</dt>
            <dd>{{ $displayProduct }}</dd>
        </dl>
    </section>

    <section class="inc-panel">
        <h2 class="inc-panel__h"><span aria-hidden="true">🛠️</span> Services delivered</h2>
        @if ($serviceCases->isEmpty())
            <p style="margin:0; color:#64748b; font-size:0.9rem;">No service cases yet. Your hub team will add them as you progress.</p>
        @else
            <div style="overflow-x:auto;">
                <table class="inc-table">
                    <thead>
                        <tr>
                            <th>Service</th>
                            <th>Status</th>
                            <th>Reference</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($serviceCases as $case)
                            <tr>
                                <td><strong>{{ $case->service?->name ?? '—' }}</strong></td>
                                <td>
                                    @if($case->status === \App\Models\ServiceCase::STATUS_COMPLETED)
                                        <span class="inc-pill inc-pill--ok">Completed</span>
                                    @elseif($case->status === \App\Models\ServiceCase::STATUS_OPEN)
                                        <span class="inc-pill inc-pill--open">In progress</span>
                                    @else
                                        {{ $case->status }}
                                    @endif
                                </td>
                                <td>{{ $case->reference_number ?: '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    <section class="inc-panel">
        <h2 class="inc-panel__h"><span aria-hidden="true">🚀</span> Coming next</h2>
        <div class="inc-soon">
            <div class="inc-soon__card">
                <h3>Product catalogue</h3>
                <p>Add products with photos — your mini storefront for buyers & partners.</p>
            </div>
            <div class="inc-soon__card">
                <h3>Pitch & story</h3>
                <p>Build your pitch deck and “about” — one place for investors & mentors.</p>
            </div>
            <div class="inc-soon__card">
                <h3>Milestones</h3>
                <p>Track goals and wins as you grow.</p>
            </div>
        </div>
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
                <h3 class="jrny__h"><span aria-hidden="true">🛣️</span> Your journey</h3>
            </div>
            <p class="jrny__meta">
                @if ($startDate)
                    Started: <b>{{ $startDate->format('d M Y') }}</b>
                @else
                    Starting soon
                @endif
                <span class="jrny__status @if ($onTrack) jrny__status--on-track @endif">{{ $onTrack ? 'On track' : 'Early days' }}</span>
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
                                <p class="jrny__date">{{ \Carbon\Carbon::parse($at)->format('d M Y') }}</p>
                            @elseif ($step['status'] === 'current')
                                <p class="jrny__date">Up next — whenever you need it</p>
                            @else
                                <p class="jrny__date">Coming up</p>
                            @endif
                            @if (!empty($step['detail']))
                                <p class="jrny__detail">{{ $step['detail'] }}</p>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>

            <div class="jrny__foot">
                <span>Progress: <b>{{ $doneCount }}/{{ $totalCount }}</b></span>
                <span>Mentorship: <b>{{ $mentorshipCount }}</b></span>
            </div>
        </section>
    </aside>
    </div>{{-- /.inc-layout --}}

    <div class="mentorship-modal" id="mentorshipModal" role="dialog" aria-modal="true" aria-labelledby="mentorshipModalTitle" hidden>
        <div class="mentorship-modal__backdrop" id="mentorshipModalBackdrop" tabindex="-1"></div>
        <div class="mentorship-modal__panel">
            <button type="button" class="mentorship-modal__close" id="closeMentorshipModal" aria-label="Close">&times;</button>
            <h3 class="mentorship-modal__title" id="mentorshipModalTitle">Request mentorship</h3>
            <p class="mentorship-modal__lead">Choose the type of help you need. Your district hub, district staff, and state team will be notified.</p>

            <form method="post" action="{{ route('incubatee.mentorship-requests.store') }}" id="mentorshipForm">
                @csrf
                <input type="hidden" name="category" id="mentorshipCategoryField" value="">

                <div class="mentorship-cats" role="group" aria-label="Mentorship type">
                    @foreach(config('mentorship.categories') as $slug => $meta)
                        <button type="button" class="mentorship-cat" data-category="{{ $slug }}">
                            @include('incubatee.partials.mentorship-icon', ['slug' => $slug])
                            <span class="mentorship-cat__label">{{ $meta['label'] }}</span>
                            <span class="mentorship-cat__hint">{{ $meta['hint'] }}</span>
                        </button>
                    @endforeach
                </div>

                <div class="mentorship-field">
                    <label for="mentorshipComment">Your message (optional)</label>
                    <textarea id="mentorshipComment" name="comment" maxlength="2000" placeholder="Describe what you need — goals, timeline, or questions."></textarea>
                </div>

                <div class="mentorship-actions">
                    <button type="button" id="cancelMentorshipModal">Cancel</button>
                    <button type="submit">Send request</button>
                </div>
            </form>
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
            alert('Please select a mentorship category.');
        }
    });
})();
</script>
@endpush
