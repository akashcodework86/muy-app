@extends('layouts.admin')

@section('body_class', 'admin-app-body--dashboard')

@section('title', __('incubatee.service_page.title'))
@section('heading', __('incubatee.service_page.title'))

@php
    $districtName = $submission->district?->name;
    $applicationNo = $submission->application_no ?? null;
    $firstName = trim((string) strtok((string) ($user->name ?? ''), ' ')) ?: 'there';
    $statusStyles = [
        'pending' => ['bg' => '#fef3c7', 'fg' => '#92400e', 'label' => __('incubatee.service_page.status_pending')],
        'in_progress' => ['bg' => '#e0e7ff', 'fg' => '#3730a3', 'label' => __('incubatee.service_page.status_in_progress')],
        'done' => ['bg' => '#dcfce7', 'fg' => '#166534', 'label' => __('incubatee.service_page.status_done')],
        'cancelled' => ['bg' => '#fee2e2', 'fg' => '#991b1b', 'label' => __('incubatee.service_page.status_cancelled')],
    ];
@endphp

@push('styles')
<style>
    .mnt-wrap { max-width: 64rem; margin: 0 auto; }
    .mnt-hero {
        position: relative; overflow: hidden; border-radius: 18px;
        padding: 1.6rem 1.8rem 1.7rem; margin-bottom: 1.35rem; color: #fff;
        background:
            radial-gradient(circle at 85% 0%, rgba(253, 224, 71, 0.35), transparent 50%),
            radial-gradient(circle at 10% 100%, rgba(94, 234, 212, 0.3), transparent 55%),
            linear-gradient(135deg, #0f766e 0%, #4338ca 55%, #7c3aed 100%);
        box-shadow: 0 18px 40px rgba(15, 118, 110, 0.22);
    }
    .mnt-hero__kicker {
        display: inline-flex; align-items: center; gap: 0.45rem;
        padding: 0.3rem 0.75rem; border-radius: 999px;
        background: rgba(255, 255, 255, 0.15); border: 1px solid rgba(255, 255, 255, 0.22);
        color: #fef3c7; font-size: 0.68rem; font-weight: 600; letter-spacing: 0.12em; text-transform: uppercase;
    }
    .mnt-hero__h { margin: 0.8rem 0 0.4rem; font-size: 1.5rem; font-weight: 800; letter-spacing: -0.025em; line-height: 1.2; }
    .mnt-hero__h b { background: linear-gradient(90deg, #fde68a, #5eead4); -webkit-background-clip: text; background-clip: text; color: transparent; }
    .mnt-hero__sub { margin: 0; color: rgba(226, 232, 240, 0.92); font-size: 0.92rem; line-height: 1.55; max-width: 42rem; }
    .mnt-hero__meta { display: flex; flex-wrap: wrap; gap: 0.5rem; margin-top: 1rem; }
    .mnt-hero__chip { display: inline-flex; padding: 0.3rem 0.65rem; border-radius: 999px; background: rgba(255,255,255,0.14); border: 1px solid rgba(255,255,255,0.22); font-size: 0.72rem; color: #eef2ff; }
    .mnt-flash { margin-bottom: 1rem; padding: 0.75rem 0.95rem; border-radius: 10px; font-size: 0.85rem; }
    .mnt-flash--ok { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
    .mnt-flash--err { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
    .mnt-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 1.35rem 1.5rem; margin-bottom: 1.25rem; box-shadow: 0 4px 18px rgba(15,23,42,0.04); }
    .mnt-card__title { margin: 0 0 0.2rem; font-size: 1.05rem; font-weight: 700; color: #0f172a; }
    .mnt-card__lead { margin: 0 0 1.1rem; color: #64748b; font-size: 0.85rem; line-height: 1.5; }
    .svc-group { margin-bottom: 1rem; }
    .svc-group__h { margin: 0 0 0.45rem; font-size: 0.72rem; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase; color: #64748b; }
    .svc-picks { display: grid; grid-template-columns: repeat(2, 1fr); gap: 0.45rem; }
    @media (min-width: 720px) { .svc-picks { grid-template-columns: repeat(3, 1fr); } }
    .svc-pick {
        appearance: none; background: #fff; border: 1.5px solid #e2e8f0; border-radius: 10px;
        padding: 0.55rem 0.65rem; text-align: left; font-family: inherit; font-size: 0.8rem; font-weight: 600;
        color: #0f172a; cursor: pointer;
    }
    .svc-pick:hover { border-color: #a5b4fc; }
    .svc-pick.is-selected { border-color: #4f46e5; background: #eef2ff; }
    .mnt-field label { display: block; font-size: 0.8rem; font-weight: 600; color: #334155; margin-bottom: 0.35rem; }
    .mnt-field textarea { width: 100%; min-height: 5.5rem; padding: 0.6rem 0.7rem; border: 1px solid #cbd5e1; border-radius: 8px; font-family: inherit; }
    .mnt-actions { display: flex; justify-content: flex-end; gap: 0.6rem; align-items: center; margin-top: 1rem; }
    .mnt-actions__note { margin-right: auto; font-size: 0.75rem; color: #64748b; }
    .mnt-submit { appearance: none; background: linear-gradient(135deg, #0f766e, #4338ca); color: #fff; border: 0; border-radius: 10px; padding: 0.6rem 1.1rem; font-size: 0.88rem; font-weight: 600; cursor: pointer; font-family: inherit; }
    .mnt-hist__empty { text-align: center; padding: 1.5rem 1rem; color: #64748b; font-size: 0.85rem; }
    .mnt-hist__list { display: flex; flex-direction: column; gap: 0.65rem; }
    .mnt-hist__row { border: 1px solid #e2e8f0; border-radius: 12px; padding: 0.85rem 1rem; }
    .mnt-hist__top { display: flex; align-items: center; gap: 0.6rem; flex-wrap: wrap; }
    .mnt-hist__cat { font-size: 0.85rem; font-weight: 700; color: #0f172a; }
    .mnt-hist__status { font-size: 0.68rem; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase; padding: 0.2rem 0.55rem; border-radius: 999px; }
    .mnt-hist__date { margin-left: auto; font-size: 0.72rem; color: #94a3b8; }
</style>
@endpush

@section('content')
<div class="mnt-wrap">
    @if (session('status'))
        <div class="mnt-flash mnt-flash--ok">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="mnt-flash mnt-flash--err">{{ $errors->first() }}</div>
    @endif

    <section class="mnt-hero">
        <span class="mnt-hero__kicker">{{ __('incubatee.service_page.kicker') }}</span>
        <h2 class="mnt-hero__h">{!! __('incubatee.service_page.hero', ['name' => e($firstName)]) !!}</h2>
        <p class="mnt-hero__sub">{{ __('incubatee.service_page.sub') }}</p>
        <div class="mnt-hero__meta">
            @if ($applicationNo)<span class="mnt-hero__chip">CFA {{ $applicationNo }}</span>@endif
            @if ($districtName)<span class="mnt-hero__chip">{{ $districtName }}</span>@endif
        </div>
    </section>

    <section class="mnt-card">
        <h3 class="mnt-card__title">{{ __('incubatee.service_page.new') }}</h3>
        <p class="mnt-card__lead">{{ __('incubatee.service_page.lead') }}</p>

        <form method="post" action="{{ route('incubatee.service-requests.store') }}" id="svcForm">
            @csrf
            <input type="hidden" name="service_id" id="svcIdField" value="{{ old('service_id') }}">

            @forelse ($serviceGroups as $slug => $services)
                <div class="svc-group">
                    <p class="svc-group__h">{{ \App\Support\IncubateeServiceCatalog::categoryLabel($slug) }}</p>
                    <div class="svc-picks">
                        @foreach ($services as $svc)
                            <button type="button" class="svc-pick @if ((string) old('service_id') === (string) $svc->id) is-selected @endif" data-service-id="{{ $svc->id }}">
                                {{ \App\Support\IncubateeServiceCatalog::serviceLabel($svc) }}
                            </button>
                        @endforeach
                    </div>
                </div>
            @empty
                <p style="margin:0;color:#64748b">{{ __('incubatee.service_page.no_services') }}</p>
            @endforelse

            <div class="mnt-field">
                <label for="svcComment">{!! __('incubatee.mentorship_page.message') !!}</label>
                <textarea id="svcComment" name="comment" maxlength="2000" placeholder="{{ __('incubatee.mentorship_page.placeholder') }}">{{ old('comment') }}</textarea>
            </div>
            <div class="mnt-actions">
                <span class="mnt-actions__note">{{ __('incubatee.mentorship_page.notify_note') }}</span>
                <button type="submit" class="mnt-submit" id="svcSubmit">{{ __('incubatee.send_request') }}</button>
            </div>
        </form>
    </section>

    <section class="mnt-card">
        <h3 class="mnt-card__title">{{ __('incubatee.service_page.history') }}</h3>
        <p class="mnt-card__lead">{{ __('incubatee.service_page.history_lead') }}</p>
        @if ($requests->isEmpty())
            <div class="mnt-hist__empty">{{ __('incubatee.service_page.history_empty') }}</div>
        @else
            <div class="mnt-hist__list">
                @foreach ($requests as $r)
                    @php $style = $statusStyles[$r->status] ?? ['bg' => '#e2e8f0', 'fg' => '#334155', 'label' => ucfirst((string) $r->status)]; @endphp
                    <article class="mnt-hist__row">
                        <div class="mnt-hist__top">
                            <span class="mnt-hist__cat">{{ $r->service ? \App\Support\IncubateeServiceCatalog::serviceLabel($r->service) : '—' }}</span>
                            <span class="mnt-hist__status" style="background: {{ $style['bg'] }}; color: {{ $style['fg'] }};">{{ $style['label'] }}</span>
                            <span class="mnt-hist__date">{{ \App\Support\Ist::datetime($r->created_at) }}</span>
                        </div>
                        @if (filled($r->comment))
                            <p style="margin:0.45rem 0 0;font-size:0.82rem;color:#475569">{{ $r->comment }}</p>
                        @endif
                        @if ($r->incubateeCanCancel())
                            <form method="post" action="{{ route('incubatee.service-requests.cancel', $r) }}" style="margin-top:0.5rem" onsubmit="return confirm(@json(__('incubatee.cancel_confirm')));">
                                @csrf
                                <button type="submit" style="background:none;border:0;color:#b91c1c;font-size:0.78rem;font-weight:600;cursor:pointer;padding:0">{{ __('incubatee.mentorship_page.cancel_request') }}</button>
                            </form>
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
    const field = document.getElementById('svcIdField');
    document.querySelectorAll('.svc-pick').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.querySelectorAll('.svc-pick').forEach(function (b) { b.classList.remove('is-selected'); });
            btn.classList.add('is-selected');
            field.value = btn.getAttribute('data-service-id') || '';
        });
    });
    document.getElementById('svcForm')?.addEventListener('submit', function (e) {
        if (!field.value.trim()) {
            e.preventDefault();
            alert(@json(__('incubatee.select_service')));
            return;
        }
        const submitBtn = document.getElementById('svcSubmit');
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.textContent = @json(__('incubatee.mentorship_page.sending'));
        }
    });
})();
</script>
@endpush
