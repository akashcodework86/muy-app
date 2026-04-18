@extends('layouts.admin')

@section('body_class', 'admin-app-body--dashboard')

@section('title', 'Dashboard')

@section('heading', 'Incubatee hub')

@push('styles')
<style>
    .inc-wrap { max-width: 56rem; }
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
</style>
@endpush

@section('content')
<div class="inc-wrap">
    <section class="inc-hero">
        <h2 class="inc-hero__h">Welcome back, {{ $user->name }}</h2>
        <p class="inc-hero__sub">
            Your entrepreneur hub — track programme support, and soon showcase your products and pitch.
        </p>
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
            <p class="inc-stat__label">Completed services</p>
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
</div>
@endsection
