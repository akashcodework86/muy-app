{{--
    Shared project footer for MUY (Mukhyamantri Udyamshala Yojana).
    Self-contained styles so it looks consistent across public, auth, and admin pages.
    Use: @include('partials.app-footer')
--}}
@php
    $appFooterYear = now()->year;
    $appFooterName = config('app.name', 'MUY');
    $appFooterContact = [
        'email' => 'mukhyamantriudyamshalayojana@gmail.com',
        'phone' => '+91-135-000-0000',
        'office' => 'Dehradun, Uttarakhand',
    ];
    $appFooterIsAuth = auth()->check();
    $appFooterRole = auth()->user()->role ?? null;
@endphp

<footer class="muy-footer" role="contentinfo">
    <div class="muy-footer__inner">
        <div class="muy-footer__grid">
            {{-- Brand --}}
            <div class="muy-footer__col muy-footer__col--brand">
                <div class="muy-footer__brand">
                    <span class="muy-footer__logo" aria-hidden="true">MUY</span>
                    <div>
                        <div class="muy-footer__brand-name">{{ $appFooterName }}</div>
                        <div class="muy-footer__brand-sub">Mukhyamantri Udyamshala Yojana · Uttarakhand</div>
                    </div>
                </div>
                <p class="muy-footer__tagline">
                    Empowering first-generation entrepreneurs across Uttarakhand — from idea to income, with guided mentorship and end-to-end business services.
                </p>
                <div class="muy-footer__milestones" aria-label="Programme milestones so far">
                    <div class="muy-footer__milestone">
                        <strong>38,000+</strong>
                        <span>CFA applications</span>
                    </div>
                    <div class="muy-footer__milestone">
                        <strong>10,000+</strong>
                        <span>Incubatees onboarded</span>
                    </div>
                    <div class="muy-footer__milestone">
                        <strong>50+</strong>
                        <span>In-house mentors</span>
                    </div>
                </div>
            </div>

            {{-- Platform links --}}
            <div class="muy-footer__col">
                <h4 class="muy-footer__heading">Platform</h4>
                <ul class="muy-footer__links">
                    @if ($appFooterIsAuth)
                        <li><a href="{{ url('/dashboard') }}">Dashboard</a></li>
                        @if ($appFooterRole === 'incubatee')
                            <li><a href="{{ route('incubatee.udmita-kosh') }}">Udmita Kosh</a></li>
                            <li><a href="{{ route('incubatee.mentorship.index') }}">Request Mentorship</a></li>
                        @endif
                        <li><a href="{{ route('notifications.index') }}">Notifications</a></li>
                        <li><a href="{{ route('account.settings.edit') }}">Account settings</a></li>
                    @else
                        <li><a href="{{ route('login') }}">Log in</a></li>
                        <li><span class="muy-footer__soft">Apply for CFA · via referral link</span></li>
                        <li><span class="muy-footer__soft">Self-learning · Udmita Kosh</span></li>
                    @endif
                </ul>
            </div>

            {{-- Reach us --}}
            <div class="muy-footer__col">
                <h4 class="muy-footer__heading">Reach us</h4>
                <ul class="muy-footer__links muy-footer__links--plain">
                    <li>
                        <span class="muy-footer__ico" aria-hidden="true">✉</span>
                        <a href="mailto:{{ $appFooterContact['email'] }}">{{ $appFooterContact['email'] }}</a>
                    </li>
                    <li>
                        <span class="muy-footer__ico" aria-hidden="true">☎</span>
                        <a href="tel:{{ str_replace([' ', '-'], '', $appFooterContact['phone']) }}">{{ $appFooterContact['phone'] }}</a>
                    </li>
                    <li>
                        <span class="muy-footer__ico" aria-hidden="true">◎</span>
                        <span>{{ $appFooterContact['office'] }}</span>
                    </li>
                </ul>
            </div>

            {{-- About strip --}}
            <div class="muy-footer__col">
                <h4 class="muy-footer__heading">About the programme</h4>
                <p class="muy-footer__about">
                    A flagship initiative of the Government of Uttarakhand to nurture local entrepreneurship through structured incubation, mentorship, and access to finance.
                </p>
                <div class="muy-footer__badges">
                    <span class="muy-footer__badge">Govt. of Uttarakhand</span>
                    <span class="muy-footer__badge muy-footer__badge--alt">Hub network · 2 hubs · 13 districts</span>
                </div>
            </div>
        </div>

        <div class="muy-footer__bottom">
            <div class="muy-footer__copy">
                © {{ $appFooterYear }} {{ $appFooterName }} · All rights reserved.
            </div>
            <div class="muy-footer__meta">
                <span>Built for the MUY programme team</span>
                <span class="muy-footer__dot" aria-hidden="true">·</span>
                <span>v{{ config('app.version', '1.0') }}</span>
            </div>
        </div>
    </div>
</footer>

@once
<style>
    .muy-footer {
        position: relative;
        margin-top: 3rem;
        padding: 2.25rem 1.25rem 1.25rem;
        color: #e2e8f0;
        font-family: 'DM Sans', system-ui, -apple-system, Segoe UI, Roboto, sans-serif;
        background:
            radial-gradient(circle at 12% 0%, rgba(45, 212, 191, 0.22), transparent 40%),
            radial-gradient(circle at 88% 100%, rgba(99, 102, 241, 0.22), transparent 45%),
            linear-gradient(135deg, #0b1f2a 0%, #0f2e3f 55%, #0e3a3a 100%);
        border-top: 1px solid rgba(45, 212, 191, 0.25);
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.04);
    }
    .muy-footer::before {
        content: '';
        position: absolute;
        left: 0; right: 0; top: 0;
        height: 2px;
        background: linear-gradient(90deg, transparent, rgba(45, 212, 191, 0.75), rgba(129, 140, 248, 0.65), transparent);
    }
    .muy-footer__inner {
        max-width: 1280px;
        margin: 0 auto;
    }
    .muy-footer__grid {
        display: grid;
        grid-template-columns: 1.6fr 1fr 1fr 1.2fr;
        gap: 2rem;
        align-items: start;
    }
    .muy-footer__col { min-width: 0; }
    .muy-footer__col--brand { padding-right: 0.5rem; }

    .muy-footer__brand {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        margin-bottom: 0.75rem;
    }
    .muy-footer__logo {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 44px; height: 44px;
        border-radius: 12px;
        font-weight: 800;
        letter-spacing: 0.04em;
        font-size: 0.9rem;
        color: #0f2e3f;
        background: linear-gradient(135deg, #5eead4 0%, #99f6e4 50%, #fef3c7 100%);
        box-shadow: 0 8px 20px rgba(45, 212, 191, 0.35), inset 0 1px 0 rgba(255,255,255,0.6);
    }
    .muy-footer__brand-name {
        font-size: 1.05rem;
        font-weight: 700;
        color: #f8fafc;
        line-height: 1.15;
    }
    .muy-footer__brand-sub {
        font-size: 0.78rem;
        color: #94a3b8;
        margin-top: 0.15rem;
    }
    .muy-footer__tagline {
        font-size: 0.88rem;
        line-height: 1.55;
        color: #cbd5e1;
        margin: 0 0 1rem;
    }

    .muy-footer__milestones {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
    }
    .muy-footer__milestone {
        flex: 1 1 auto;
        min-width: 120px;
        padding: 0.55rem 0.7rem;
        border-radius: 10px;
        background: rgba(255, 255, 255, 0.04);
        border: 1px solid rgba(94, 234, 212, 0.18);
        line-height: 1.15;
    }
    .muy-footer__milestone strong {
        display: block;
        font-size: 1rem;
        color: #5eead4;
        font-weight: 700;
    }
    .muy-footer__milestone span {
        font-size: 0.72rem;
        color: #94a3b8;
    }

    .muy-footer__heading {
        font-size: 0.78rem;
        text-transform: uppercase;
        letter-spacing: 0.1em;
        color: #94a3b8;
        margin: 0 0 0.85rem;
        font-weight: 700;
    }
    .muy-footer__links {
        list-style: none;
        padding: 0;
        margin: 0;
        display: flex;
        flex-direction: column;
        gap: 0.55rem;
    }
    .muy-footer__links a {
        color: #e2e8f0;
        text-decoration: none;
        font-size: 0.88rem;
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        transition: color 0.15s ease, transform 0.15s ease;
    }
    .muy-footer__links a:hover {
        color: #5eead4;
        transform: translateX(2px);
    }
    .muy-footer__links--plain li {
        display: flex;
        align-items: flex-start;
        gap: 0.55rem;
        color: #cbd5e1;
        font-size: 0.88rem;
        word-break: break-all;
    }
    .muy-footer__ico {
        display: inline-flex;
        flex-shrink: 0;
        width: 22px; height: 22px;
        align-items: center;
        justify-content: center;
        border-radius: 6px;
        background: rgba(94, 234, 212, 0.14);
        color: #5eead4;
        font-size: 0.85rem;
    }
    .muy-footer__soft {
        color: #94a3b8;
        font-size: 0.85rem;
    }

    .muy-footer__about {
        font-size: 0.85rem;
        line-height: 1.55;
        color: #cbd5e1;
        margin: 0 0 0.75rem;
    }
    .muy-footer__badges {
        display: flex;
        flex-wrap: wrap;
        gap: 0.4rem;
    }
    .muy-footer__badge {
        font-size: 0.72rem;
        padding: 0.28rem 0.55rem;
        border-radius: 999px;
        background: rgba(94, 234, 212, 0.12);
        border: 1px solid rgba(94, 234, 212, 0.25);
        color: #a7f3d0;
    }
    .muy-footer__badge--alt {
        background: rgba(129, 140, 248, 0.12);
        border-color: rgba(129, 140, 248, 0.3);
        color: #c7d2fe;
    }

    .muy-footer__bottom {
        margin-top: 1.75rem;
        padding-top: 1rem;
        border-top: 1px solid rgba(255, 255, 255, 0.08);
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 0.5rem;
        font-size: 0.78rem;
        color: #94a3b8;
    }
    .muy-footer__meta {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
    }
    .muy-footer__dot { opacity: 0.6; }

    @media (max-width: 1024px) {
        .muy-footer__grid {
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
        }
        .muy-footer__col--brand { grid-column: 1 / -1; }
    }
    @media (max-width: 600px) {
        .muy-footer { padding: 1.75rem 1rem 1rem; }
        .muy-footer__grid {
            grid-template-columns: 1fr;
            gap: 1.25rem;
        }
        .muy-footer__bottom {
            justify-content: flex-start;
        }
    }
</style>
@endonce
