@extends('layouts.incubatee')

@section('title', 'Dashboard')

@push('styles')
<style>
    .inc-hero {
        position: relative;
        overflow: hidden;
        border-radius: 24px;
        padding: 2rem 1.75rem;
        margin-bottom: 1.5rem;
        background: linear-gradient(125deg, rgba(255,255,255,0.95) 0%, rgba(250, 245, 255, 0.92) 40%, rgba(224, 242, 254, 0.88) 100%);
        border: 1px solid rgba(255,255,255,0.6);
        box-shadow: 0 25px 60px -15px rgba(15, 23, 42, 0.25);
    }
    .inc-hero::before {
        content: '';
        position: absolute;
        inset: -40% -20% auto auto;
        width: 60%;
        height: 120%;
        background: radial-gradient(circle, rgba(168, 85, 247, 0.25), transparent 65%);
        pointer-events: none;
    }
    .inc-hero__h {
        font-family: 'Outfit', sans-serif;
        font-weight: 800;
        font-size: clamp(1.5rem, 4vw, 2rem);
        letter-spacing: -0.03em;
        margin: 0 0 0.35rem;
        position: relative;
        background: linear-gradient(90deg, #5b21b6, #db2777, #0e7490);
        -webkit-background-clip: text;
        background-clip: text;
        color: transparent;
    }
    .inc-hero__sub {
        margin: 0;
        color: #475569;
        font-size: 0.95rem;
        position: relative;
        max-width: 42rem;
        line-height: 1.55;
    }
    .inc-hero__badges {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
        margin-top: 1rem;
        position: relative;
    }
    .inc-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        padding: 0.35rem 0.75rem;
        border-radius: 999px;
        font-size: 0.78rem;
        font-weight: 600;
    }
    .inc-badge--violet { background: linear-gradient(135deg, #ede9fe, #fae8ff); color: #5b21b6; }
    .inc-badge--rose { background: linear-gradient(135deg, #ffe4e6, #fce7f3); color: #be185d; }
    .inc-badge--cyan { background: linear-gradient(135deg, #cffafe, #e0f2fe); color: #0e7490; }

    .inc-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 1rem;
        margin-bottom: 1.5rem;
    }
    .inc-stat {
        border-radius: 18px;
        padding: 1.15rem 1.2rem;
        border: 1px solid rgba(255,255,255,0.55);
        backdrop-filter: blur(12px);
        color: #0f172a;
        position: relative;
        overflow: hidden;
    }
    .inc-stat--a { background: linear-gradient(145deg, rgba(255,255,255,0.88), rgba(237, 233, 254, 0.75)); }
    .inc-stat--b { background: linear-gradient(145deg, rgba(255,255,255,0.88), rgba(252, 231, 243, 0.75)); }
    .inc-stat--c { background: linear-gradient(145deg, rgba(255,255,255,0.88), rgba(207, 250, 254, 0.75)); }
    .inc-stat__label { font-size: 0.75rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; color: #64748b; margin: 0 0 0.35rem; }
    .inc-stat__val { font-family: 'Outfit', sans-serif; font-size: 2rem; font-weight: 800; margin: 0; line-height: 1; }
    .inc-stat__hint { font-size: 0.8rem; color: #64748b; margin: 0.35rem 0 0; }

    .inc-panel {
        border-radius: 20px;
        background: rgba(255,255,255,0.75);
        backdrop-filter: blur(14px);
        border: 1px solid rgba(255,255,255,0.55);
        padding: 1.25rem 1.35rem;
        margin-bottom: 1.25rem;
        box-shadow: 0 20px 40px -20px rgba(15, 23, 42, 0.2);
    }
    .inc-panel__h {
        font-family: 'Outfit', sans-serif;
        font-weight: 800;
        font-size: 1.1rem;
        margin: 0 0 0.85rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        color: #1e1b4b;
    }
    .inc-panel__h span { font-size: 1.25rem; }
    .inc-dl { display: grid; grid-template-columns: 1fr 2fr; gap: 0.65rem 1rem; font-size: 0.88rem; color: #334155; }
    .inc-dl dt { font-weight: 600; color: #64748b; margin: 0; }
    .inc-dl dd { margin: 0; }

    .inc-soon {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
    }
    .inc-soon__card {
        border-radius: 16px;
        padding: 1.15rem;
        min-height: 120px;
        border: 2px dashed rgba(124, 58, 237, 0.35);
        background: linear-gradient(160deg, rgba(255,255,255,0.5), rgba(243, 232, 255, 0.45));
        color: #5b21b6;
    }
    .inc-soon__card h3 {
        font-family: 'Outfit', sans-serif;
        font-weight: 800;
        font-size: 1rem;
        margin: 0 0 0.35rem;
    }
    .inc-soon__card p { margin: 0; font-size: 0.82rem; color: #7c3aed; opacity: 0.9; line-height: 1.45; }

    .inc-table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
    .inc-table th, .inc-table td { text-align: left; padding: 0.55rem 0.45rem; border-bottom: 1px solid rgba(148, 163, 184, 0.25); }
    .inc-table th { color: #64748b; font-weight: 600; font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.04em; }
    .inc-pill { display: inline-block; padding: 0.2rem 0.55rem; border-radius: 999px; font-size: 0.72rem; font-weight: 700; }
    .inc-pill--ok { background: #d1fae5; color: #047857; }
    .inc-pill--open { background: #fef3c7; color: #b45309; }
</style>
@endpush

@section('content')
    <section class="inc-hero">
        <h1 class="inc-hero__h">Welcome back, {{ $user->name }}</h1>
        <p class="inc-hero__sub">
            Your entrepreneur hub — track programme support, and soon showcase your products and pitch.
        </p>
        <div class="inc-hero__badges">
            <span class="inc-badge inc-badge--violet">CFA {{ $submission->application_no ?? '—' }}</span>
            @if($submission->district?->name)
                <span class="inc-badge inc-badge--rose">{{ $submission->district->name }}</span>
            @endif
            @if($batch?->name)
                <span class="inc-badge inc-badge--cyan">Batch: {{ $batch->name }}</span>
            @endif
            @if($hubName)
                <span class="inc-badge inc-badge--rose">Hub: {{ $hubName }}</span>
            @endif
        </div>
    </section>

    <div class="inc-grid">
        <div class="inc-stat inc-stat--a">
            <p class="inc-stat__label">Completed services</p>
            <p class="inc-stat__val">{{ $servicesCompletedCount }}</p>
            <p class="inc-stat__hint">Delivered under MUY</p>
        </div>
        <div class="inc-stat inc-stat--b">
            <p class="inc-stat__label">In progress</p>
            <p class="inc-stat__val">{{ $servicesOpenCount }}</p>
            <p class="inc-stat__hint">Open cases</p>
        </div>
        <div class="inc-stat inc-stat--c">
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
@endsection
