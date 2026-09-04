{{--
    Shared compact footer — PwC-themed, minimal detail.
    Use: @include('partials.app-footer')
--}}
@php
    $logoUrl = asset('https://ukrbi.in/new/admin/muy.png');
    $appFooterYear = now()->year;
    $appFooterName = config('app.name', 'MUY');
    $appFooterIsAuth = auth()->check();
@endphp

<footer class="muy-footer" role="contentinfo">
    <div class="muy-footer__inner">
        <div class="muy-footer__row">
            <a href="{{ $appFooterIsAuth ? url('/dashboard') : url('/') }}" class="muy-footer__brand" title="Mukhyamantri Udyamshala Yojana">
                <img src="{{ $logoUrl }}" alt="" class="muy-footer__logo" width="32" height="32">
                <span class="muy-footer__brand-text">
                    <span class="muy-footer__brand-name">{{ $appFooterName }}</span>
                    <span class="muy-footer__brand-sub">Uttarakhand</span>
                </span>
            </a>

            <nav class="muy-footer__nav" aria-label="Footer">
                @if ($appFooterIsAuth)
                    <a href="{{ url('/dashboard') }}">Dashboard</a>
                    <a href="{{ route('notifications.index') }}">Notifications</a>
                    <a href="{{ route('account.settings.edit') }}">Settings</a>
                @elseif (empty($hideGuestLogin))
                    <a href="{{ route('login') }}">Log in</a>
                @endif
            </nav>

            <p class="muy-footer__copy">© {{ $appFooterYear }} {{ $appFooterName }}</p>
        </div>
    </div>
</footer>

@once
<style>
    .muy-footer {
        margin-top: 2rem;
        padding: 0.85rem 1.25rem;
        font-family: 'DM Sans', system-ui, sans-serif;
        background: #2d2d2d;
        border-top: 3px solid #d04a02;
        color: rgba(255, 255, 255, 0.88);
    }
    .muy-footer__inner {
        max-width: 1280px;
        margin: 0 auto;
    }
    .muy-footer__row {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 0.65rem 1.25rem;
    }
    .muy-footer__brand {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        text-decoration: none;
        color: inherit;
        min-width: 0;
    }
    .muy-footer__logo {
        width: 32px;
        height: 32px;
        object-fit: contain;
        border-radius: 6px;
        background: #fff;
        flex-shrink: 0;
    }
    .muy-footer__brand-text {
        display: flex;
        flex-direction: column;
        line-height: 1.2;
        min-width: 0;
    }
    .muy-footer__brand-name {
        font-size: 0.88rem;
        font-weight: 700;
        color: #fff;
    }
    .muy-footer__brand-sub {
        font-size: 0.68rem;
        color: rgba(255, 255, 255, 0.55);
    }
    .muy-footer__nav {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 0.35rem 1rem;
    }
    .muy-footer__nav a {
        font-size: 0.8rem;
        font-weight: 600;
        color: rgba(255, 255, 255, 0.78);
        text-decoration: none;
        transition: color 0.15s ease;
    }
    .muy-footer__nav a:hover {
        color: #ffb600;
    }
    .muy-footer__copy {
        margin: 0;
        font-size: 0.75rem;
        color: rgba(255, 255, 255, 0.5);
        white-space: nowrap;
    }
    @media (max-width: 640px) {
        .muy-footer {
            padding: 0.75rem 1rem;
        }
        .muy-footer__row {
            flex-direction: column;
            align-items: flex-start;
            gap: 0.5rem;
        }
        .muy-footer__copy {
            order: 3;
        }
    }
</style>
@endonce
