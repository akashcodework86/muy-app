@extends('layouts.admin')

@section('title', 'SPOC dashboard')
@section('heading', 'State staff (SPOC) dashboard')

@section('content')
<style>
    :root {
        --ss-bg: #eef0f5;
        --ss-card: #ffffff;
        --ss-radius: 16px;
        --ss-shadow: 0 2px 20px rgba(0,0,0,0.06);
        --ss-text: #1c1c1e;
        --ss-muted: #8e8e93;
        --ss-border: #e5e5ea;
        --ss-green: #34c759;
        --ss-blue: #007aff;
        --ss-pink: #ff2d55;
        --ss-orange: #ff9500;
        --ss-teal: #26a69a;
    }
    .ss-wrap {
        max-width: 56rem;
        font-family: 'DM Sans', system-ui, sans-serif;
    }
    .ss-hero {
        background: linear-gradient(120deg, #00897b 0%, #26a69a 50%, #4db6ac 100%);
        border-radius: var(--ss-radius);
        padding: 1.35rem 1.5rem;
        color: #fff;
        box-shadow: 0 8px 24px rgba(38, 166, 154, 0.22);
        margin-bottom: 1rem;
    }
    .ss-hero h2 {
        margin: 0 0 0.35rem;
        font-size: 1.3rem;
        font-weight: 800;
        letter-spacing: -0.02em;
        color: #fff;
    }
    .ss-hero p {
        margin: 0;
        font-size: 0.88rem;
        color: rgba(255,255,255,0.88);
        line-height: 1.5;
    }
    .ss-stat-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 0.75rem;
        margin-top: 1.1rem;
    }
    .ss-stat-card {
        background: rgba(255,255,255,0.15);
        border: 1px solid rgba(255,255,255,0.22);
        border-radius: 12px;
        padding: 0.85rem 1rem;
        backdrop-filter: blur(4px);
    }
    .ss-stat-card__label {
        font-size: 0.65rem;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        color: rgba(255,255,255,0.75);
        font-weight: 700;
        margin-bottom: 0.25rem;
    }
    .ss-stat-card__val {
        font-size: 1.5rem;
        font-weight: 800;
        color: #fff;
        letter-spacing: -0.03em;
        line-height: 1.1;
    }
    .ss-stat-card__sub {
        font-size: 0.75rem;
        color: rgba(255,255,255,0.72);
        margin-top: 0.25rem;
    }
    .ss-stat-card--warn .ss-stat-card__val { color: #ffca28; }
    .ss-card {
        background: var(--ss-card);
        border-radius: var(--ss-radius);
        padding: 1.1rem 1.25rem;
        box-shadow: var(--ss-shadow);
        margin-bottom: 0.85rem;
    }
    .ss-card h3 {
        margin: 0 0 0.5rem;
        font-size: 0.92rem;
        font-weight: 800;
        color: var(--ss-text);
    }
    .ss-card p {
        font-size: 0.85rem;
        color: var(--ss-muted);
        margin: 0 0 0.65rem;
        line-height: 1.5;
    }
    .ss-card ul {
        margin: 0;
        padding-left: 1.1rem;
        font-size: 0.85rem;
        color: var(--ss-muted);
        line-height: 1.7;
    }
    .ss-card ul strong { color: var(--ss-text); }
    .ss-districts {
        display: flex; flex-wrap: wrap; gap: 0.25rem; margin-top: 0.35rem;
    }
    .ss-district-pill {
        background: #e0f2f1; color: #00695c;
        border: 1px solid #80cbc4;
        padding: 0.15rem 0.45rem;
        border-radius: 999px;
        font-size: 0.7rem; font-weight: 600;
    }
    .ss-no-districts { font-size: 0.78rem; color: var(--ss-orange); font-weight: 600; }
    .ss-btn-row { display: flex; flex-wrap: wrap; gap: 0.5rem; }
    .ss-btn {
        display: inline-flex; align-items: center; gap: 0.35rem;
        padding: 0.48rem 0.85rem;
        border-radius: var(--ss-radius);
        text-decoration: none;
        font-size: 0.8rem; font-weight: 700;
        transition: transform 0.15s, box-shadow 0.15s;
    }
    .ss-btn:hover { transform: translateY(-1px); }
    .ss-btn--primary { background: linear-gradient(135deg, #00897b, #26a69a); color: #fff; box-shadow: 0 4px 12px rgba(38,166,154,0.25); }
    .ss-btn--secondary { background: var(--ss-card); border: 1px solid var(--ss-border); color: var(--ss-text); box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
</style>

<div class="ss-wrap">
    <div class="ss-hero">
        <h2>Welcome, {{ $user->name }}</h2>
        <p>You are a <strong>State Staff (SPOC)</strong> — checker for service cases that require maker-checker verification (e.g. GST registration).</p>
        <div class="ss-stat-grid">
            <div class="ss-stat-card">
                <div class="ss-stat-card__label">Districts assigned</div>
                <div class="ss-stat-card__val">{{ $spocDistricts->count() }}</div>
                @if ($spocDistricts->isEmpty())
                    <div class="ss-stat-card__sub">No districts assigned yet</div>
                @else
                    <div class="ss-districts">
                        @foreach ($spocDistricts as $d)
                            <span class="ss-district-pill" title="{{ $d->hub?->name ? $d->hub->name.' Hub' : '' }}">{{ $d->name }}</span>
                        @endforeach
                    </div>
                @endif
            </div>
            <div class="ss-stat-card">
                <div class="ss-stat-card__label">Pending approvals</div>
                <div class="ss-stat-card__val">{{ number_format((int) ($pendingApprovals ?? 0)) }}</div>
                <div class="ss-stat-card__sub">Cases waiting for your decision</div>
            </div>
            <div class="ss-stat-card {{ (int)($overduePending ?? 0) > 0 ? 'ss-stat-card--warn' : '' }}">
                <div class="ss-stat-card__label">Overdue (3+ biz days)</div>
                <div class="ss-stat-card__val">{{ number_format((int) ($overduePending ?? 0)) }}</div>
                <div class="ss-stat-card__sub">Pending cases past SLA deadline</div>
            </div>
            <div class="ss-stat-card">
                <div class="ss-stat-card__label">Approved by you</div>
                <div class="ss-stat-card__val">{{ number_format((int) ($approvedByYou ?? 0)) }}</div>
                <div class="ss-stat-card__sub">Total approvals completed</div>
            </div>
        </div>
    </div>

    <div class="ss-card">
        <h3><i class="fa-solid fa-circle-info" style="color:#26a69a;margin-right:0.35rem;"></i> What's next</h3>
        <ul>
            <li>Wait for the state admin to assign you one or more districts on the <em>Service SPOCs</em> page.</li>
            <li>Once services marked <em>Requires approval</em> are submitted by district staff, they will appear in your queue here.</li>
            <li>You will be able to <strong>approve</strong>, <strong>send back</strong> (with a note), or <strong>reject</strong> each case.</li>
        </ul>
    </div>

    @if (!empty($canSubmitSocialMediaPost))
    <div class="ss-card">
        <h3><i class="fa-brands fa-instagram" style="color:#ff2d55;margin-right:0.35rem;"></i> Social media posts</h3>
        <p>Log state-level social media posts (date, URL, optional note).</p>
        <div class="ss-btn-row">
            <a href="{{ route('spoc.social-media-posts.create') }}" class="ss-btn ss-btn--primary">
                <i class="fa-solid fa-plus"></i> Log new post
            </a>
            <a href="{{ route('spoc.social-media-posts.dashboard') }}" class="ss-btn ss-btn--secondary">
                <i class="fa-solid fa-list"></i> View my entries
            </a>
        </div>
    </div>
    @endif

    <div class="ss-card">
        <h3><i class="fa-solid fa-folder-open" style="color:#26a69a;margin-right:0.35rem;"></i> Documents</h3>
        <p>Open role-authorized documents uploaded by state admin and teams.</p>
        <a href="{{ route('library.documents.index') }}" class="ss-btn ss-btn--secondary">
            <i class="fa-solid fa-book-open"></i> Open document repository
        </a>
    </div>
</div>
@endsection
