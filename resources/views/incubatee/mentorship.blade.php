@extends('layouts.admin')

@section('body_class', 'admin-app-body--dashboard')

@section('title', 'Request mentorship')

@section('heading', 'Request mentorship')

@php
    /** @var \App\Models\User $user */
    /** @var \App\Models\CfaSubmission $submission */
    /** @var \Illuminate\Support\Collection<int, \App\Models\MentorshipRequest> $requests */
    /** @var array<string, array{label:string, hint:string}> $categories */
    $districtName = $submission->district?->name;
    $applicationNo = $submission->application_no ?? null;
    $firstName = trim((string) strtok((string) ($user->name ?? ''), ' ')) ?: 'there';
    $statusStyles = [
        'pending' => ['bg' => '#fef3c7', 'fg' => '#92400e', 'label' => 'Pending'],
        'in_review' => ['bg' => '#dbeafe', 'fg' => '#1e40af', 'label' => 'In review'],
        'scheduled' => ['bg' => '#e0e7ff', 'fg' => '#3730a3', 'label' => 'Scheduled'],
        'completed' => ['bg' => '#dcfce7', 'fg' => '#166534', 'label' => 'Completed'],
        'cancelled' => ['bg' => '#fee2e2', 'fg' => '#991b1b', 'label' => 'Cancelled'],
    ];
@endphp

@push('styles')
<style>
    .mnt-wrap { max-width: 64rem; margin: 0 auto; }

    .mnt-hero {
        position: relative;
        overflow: hidden;
        border-radius: 18px;
        padding: 1.6rem 1.8rem 1.7rem;
        margin-bottom: 1.35rem;
        color: #fff;
        background:
            radial-gradient(circle at 85% 0%, rgba(253, 224, 71, 0.35), transparent 50%),
            radial-gradient(circle at 10% 100%, rgba(94, 234, 212, 0.3), transparent 55%),
            linear-gradient(135deg, #4338ca 0%, #7c3aed 55%, #c026d3 100%);
        box-shadow: 0 18px 40px rgba(49, 46, 129, 0.25);
    }
    .mnt-hero__kicker {
        display: inline-flex;
        align-items: center;
        gap: 0.45rem;
        padding: 0.3rem 0.75rem;
        border-radius: 999px;
        background: rgba(255, 255, 255, 0.15);
        border: 1px solid rgba(255, 255, 255, 0.22);
        color: #fef3c7;
        font-size: 0.68rem;
        font-weight: 600;
        letter-spacing: 0.12em;
        text-transform: uppercase;
    }
    .mnt-hero__kicker::before {
        content: '';
        width: 6px; height: 6px; border-radius: 999px;
        background: #fde68a;
        box-shadow: 0 0 0 4px rgba(253, 230, 138, 0.25);
    }
    .mnt-hero__h {
        margin: 0.8rem 0 0.4rem;
        font-size: 1.6rem;
        font-weight: 800;
        letter-spacing: -0.025em;
        line-height: 1.15;
    }
    .mnt-hero__h b {
        background: linear-gradient(90deg, #fde68a, #5eead4);
        -webkit-background-clip: text;
        background-clip: text;
        color: transparent;
        font-weight: 800;
    }
    .mnt-hero__sub {
        margin: 0;
        color: rgba(226, 232, 240, 0.92);
        font-size: 0.92rem;
        line-height: 1.55;
        max-width: 40rem;
    }
    .mnt-hero__meta {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
        margin-top: 1rem;
    }
    .mnt-hero__chip {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        padding: 0.3rem 0.65rem;
        border-radius: 999px;
        background: rgba(255, 255, 255, 0.14);
        border: 1px solid rgba(255, 255, 255, 0.22);
        font-size: 0.72rem;
        font-weight: 500;
        color: #eef2ff;
    }

    .mnt-flash {
        margin-bottom: 1rem;
        padding: 0.75rem 0.95rem;
        border-radius: 10px;
        font-size: 0.85rem;
        font-weight: 500;
    }
    .mnt-flash--ok { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
    .mnt-flash--err { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }

    .mnt-card {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        padding: 1.35rem 1.5rem;
        margin-bottom: 1.25rem;
        box-shadow: 0 4px 18px rgba(15, 23, 42, 0.04);
    }
    .mnt-card__title {
        margin: 0 0 0.2rem;
        font-size: 1.05rem;
        font-weight: 700;
        color: #0f172a;
        letter-spacing: -0.015em;
    }
    .mnt-card__lead {
        margin: 0 0 1.1rem;
        color: #64748b;
        font-size: 0.85rem;
        line-height: 1.5;
    }

    /* Category grid — same visual language as dashboard modal */
    .mnt-cats {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 0.65rem;
        margin-bottom: 1.15rem;
    }
    @media (min-width: 640px) {
        .mnt-cats { grid-template-columns: repeat(3, 1fr); }
    }
    @media (min-width: 960px) {
        .mnt-cats { grid-template-columns: repeat(5, 1fr); }
    }
    .mnt-cat {
        appearance: none;
        background: #fff;
        border: 1.5px solid #e2e8f0;
        border-radius: 12px;
        padding: 0.95rem 0.6rem 0.85rem;
        text-align: center;
        cursor: pointer;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 0.4rem;
        transition: border-color 120ms ease, background 120ms ease, transform 120ms ease, box-shadow 120ms ease;
    }
    .mnt-cat:hover { border-color: #a5b4fc; background: #f8fafc; transform: translateY(-1px); }
    .mnt-cat.is-selected {
        border-color: #4f46e5;
        background: linear-gradient(135deg, #eef2ff, #ede9fe);
        box-shadow: 0 6px 16px rgba(79, 70, 229, 0.18);
    }
    .mnt-cat__label { font-size: 0.8rem; font-weight: 700; color: #0f172a; }
    .mnt-cat__hint { font-size: 0.68rem; font-weight: 500; color: #64748b; line-height: 1.35; }
    .mentorship-icon-svg { width: 2rem; height: 2rem; color: #4f46e5; flex-shrink: 0; }
    .mnt-cat.is-selected .mentorship-icon-svg { color: #4338ca; }

    .mnt-field { display: flex; flex-direction: column; gap: 0.4rem; margin-bottom: 1.1rem; }
    .mnt-field label {
        font-size: 0.8rem;
        font-weight: 600;
        color: #334155;
        letter-spacing: 0.01em;
    }
    .mnt-field textarea {
        width: 100%;
        min-height: 120px;
        padding: 0.75rem 0.9rem;
        border: 1.5px solid #e2e8f0;
        border-radius: 10px;
        font-family: inherit;
        font-size: 0.88rem;
        line-height: 1.5;
        color: #0f172a;
        resize: vertical;
        transition: border-color 120ms ease, box-shadow 120ms ease;
    }
    .mnt-field textarea:focus {
        outline: none;
        border-color: #6366f1;
        box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15);
    }
    .mnt-field__hint { font-size: 0.72rem; color: #94a3b8; }

    .mnt-actions {
        display: flex;
        justify-content: flex-end;
        gap: 0.6rem;
        align-items: center;
    }
    .mnt-actions__note { margin-right: auto; font-size: 0.75rem; color: #64748b; }
    .mnt-submit {
        appearance: none;
        background: linear-gradient(135deg, #4f46e5, #7c3aed);
        color: #fff;
        border: 0;
        border-radius: 10px;
        padding: 0.6rem 1.1rem;
        font-size: 0.88rem;
        font-weight: 600;
        cursor: pointer;
        letter-spacing: 0.005em;
        box-shadow: 0 6px 14px rgba(79, 70, 229, 0.25);
        transition: filter 120ms ease, transform 120ms ease;
    }
    .mnt-submit:hover { filter: brightness(1.05); transform: translateY(-1px); }
    .mnt-submit:disabled { opacity: 0.6; cursor: not-allowed; transform: none; }

    /* History list */
    .mnt-hist__empty {
        text-align: center;
        padding: 1.5rem 1rem;
        color: #64748b;
        font-size: 0.85rem;
    }
    .mnt-hist__list { display: flex; flex-direction: column; gap: 0.65rem; }
    .mnt-hist__row {
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 0.85rem 1rem;
        background: #fff;
        display: grid;
        gap: 0.4rem;
    }
    .mnt-hist__top {
        display: flex;
        align-items: center;
        gap: 0.6rem;
        flex-wrap: wrap;
    }
    .mnt-hist__cat {
        font-size: 0.85rem;
        font-weight: 700;
        color: #0f172a;
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
    }
    .mnt-hist__cat .mentorship-icon-svg { width: 1.25rem; height: 1.25rem; color: #4f46e5; }
    .mnt-hist__status {
        font-size: 0.68rem;
        font-weight: 700;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        padding: 0.2rem 0.55rem;
        border-radius: 999px;
    }
    .mnt-hist__date { margin-left: auto; font-size: 0.72rem; color: #94a3b8; }
    .mnt-hist__msg {
        font-size: 0.82rem;
        color: #475569;
        line-height: 1.5;
        background: #f8fafc;
        border-left: 3px solid #c7d2fe;
        padding: 0.55rem 0.75rem;
        border-radius: 0 8px 8px 0;
    }
</style>
@endpush

@section('content')
<div class="mnt-wrap">
    @if (session('status'))
        <div class="mnt-flash mnt-flash--ok">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="mnt-flash mnt-flash--err">
            {{ $errors->first() }}
        </div>
    @endif

    <section class="mnt-hero">
        <span class="mnt-hero__kicker">Mentorship hub</span>
        <h2 class="mnt-hero__h">Namaste {{ $firstName }}, <b>ask for expert guidance</b> in a minute.</h2>
        <p class="mnt-hero__sub">Pick the area where you need help, drop your question or goal, and your district hub, staff and the state team will be notified together. You stay focused — we route it to the right mentor.</p>
        <div class="mnt-hero__meta">
            @if ($applicationNo)
                <span class="mnt-hero__chip">CFA {{ $applicationNo }}</span>
            @endif
            @if ($districtName)
                <span class="mnt-hero__chip">{{ $districtName }}</span>
            @endif
            <span class="mnt-hero__chip">5 focus areas · Financial · Marketing · Legal · Technical · Strategy</span>
        </div>
    </section>

    <section class="mnt-card">
        <h3 class="mnt-card__title">New mentorship request</h3>
        <p class="mnt-card__lead">Choose the category that matches your need — you can add context in the message below.</p>

        <form method="post" action="{{ route('incubatee.mentorship-requests.store') }}" id="mntForm">
            @csrf
            <input type="hidden" name="category" id="mntCategoryField" value="{{ old('category') }}">

            <div class="mnt-cats" role="group" aria-label="Mentorship category">
                @foreach ($categories as $slug => $meta)
                    <button
                        type="button"
                        class="mnt-cat @if (old('category') === $slug) is-selected @endif"
                        data-category="{{ $slug }}"
                        aria-pressed="{{ old('category') === $slug ? 'true' : 'false' }}"
                    >
                        @include('incubatee.partials.mentorship-icon', ['slug' => $slug])
                        <span class="mnt-cat__label">{{ $meta['label'] }}</span>
                        <span class="mnt-cat__hint">{{ $meta['hint'] }}</span>
                    </button>
                @endforeach
            </div>

            <div class="mnt-field">
                <label for="mntComment">Your message <span style="color:#94a3b8; font-weight: 500;">(optional, up to 2000 chars)</span></label>
                <textarea
                    id="mntComment"
                    name="comment"
                    maxlength="2000"
                    placeholder="Describe what you need — your goal, specific question, timeline, or decision you're stuck on."
                >{{ old('comment') }}</textarea>
                <span class="mnt-field__hint" id="mntCounter">0 / 2000</span>
            </div>

            <div class="mnt-actions">
                <span class="mnt-actions__note">Your hub admin, district staff, and the state team will be notified.</span>
                <button type="submit" class="mnt-submit" id="mntSubmit">Send request</button>
            </div>
        </form>
    </section>

    <section class="mnt-card">
        <h3 class="mnt-card__title">Your previous requests</h3>
        <p class="mnt-card__lead">Latest 50 requests linked to your CFA profile.</p>

        @if ($requests->isEmpty())
            <div class="mnt-hist__empty">No mentorship requests yet. Send your first one above.</div>
        @else
            <div class="mnt-hist__list">
                @foreach ($requests as $r)
                    @php
                        $meta = $categories[$r->category] ?? ['label' => ucwords(str_replace('_', ' ', (string) $r->category))];
                        $style = $statusStyles[$r->status] ?? ['bg' => '#e2e8f0', 'fg' => '#334155', 'label' => ucfirst((string) $r->status)];
                    @endphp
                    <article class="mnt-hist__row">
                        <div class="mnt-hist__top">
                            <span class="mnt-hist__cat">
                                @include('incubatee.partials.mentorship-icon', ['slug' => $r->category])
                                {{ $meta['label'] }}
                            </span>
                            <span class="mnt-hist__status" style="background: {{ $style['bg'] }}; color: {{ $style['fg'] }};">{{ $style['label'] }}</span>
                            <span class="mnt-hist__date">{{ optional($r->created_at)->diffForHumans() }} · {{ optional($r->created_at)->format('d M Y, h:i A') }}</span>
                        </div>
                        @if (filled($r->comment))
                            <div class="mnt-hist__msg">{{ $r->comment }}</div>
                        @endif
                    </article>
                @endforeach
            </div>
        @endif
    </section>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const form = document.getElementById('mntForm');
    const field = document.getElementById('mntCategoryField');
    const submitBtn = document.getElementById('mntSubmit');
    const comment = document.getElementById('mntComment');
    const counter = document.getElementById('mntCounter');

    document.querySelectorAll('.mnt-cat').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.querySelectorAll('.mnt-cat').forEach(function (b) {
                b.classList.remove('is-selected');
                b.setAttribute('aria-pressed', 'false');
            });
            btn.classList.add('is-selected');
            btn.setAttribute('aria-pressed', 'true');
            field.value = btn.getAttribute('data-category') || '';
        });
    });

    function updateCounter() {
        const len = (comment.value || '').length;
        counter.textContent = len + ' / 2000';
    }
    comment?.addEventListener('input', updateCounter);
    updateCounter();

    form?.addEventListener('submit', function (e) {
        if (!field.value.trim()) {
            e.preventDefault();
            alert('Please select a mentorship category first.');
            return;
        }
        submitBtn.disabled = true;
        submitBtn.textContent = 'Sending…';
    });
})();
</script>
@endpush
