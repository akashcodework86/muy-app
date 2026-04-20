<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Log in — Welcome to MIS</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700&display=swap" rel="stylesheet">
    @include('partials.admin-shell-styles')
    <style>
        .muy-login {
            min-height: 100vh;
            display: grid;
            grid-template-columns: 1fr;
            font-family: 'DM Sans', system-ui, sans-serif;
            color: #0f172a;
            background:
                radial-gradient(ellipse 70% 50% at 100% 0%, rgba(45, 212, 191, 0.18), transparent 55%),
                radial-gradient(ellipse 60% 45% at 0% 100%, rgba(129, 140, 248, 0.18), transparent 55%),
                linear-gradient(160deg, #f0f9ff 0%, #f5f3ff 38%, #ecfeff 72%, #f8fafc 100%);
        }
        @media (min-width: 960px) {
            .muy-login { grid-template-columns: minmax(0, 1.05fr) minmax(0, 0.95fr); }
        }

        /* Left hero / purpose panel */
        .muy-hero {
            position: relative;
            overflow: hidden;
            padding: 3rem 2.5rem 3.25rem;
            color: #f8fafc;
            background:
                radial-gradient(circle at 20% 0%, rgba(253, 224, 71, 0.25), transparent 45%),
                radial-gradient(circle at 90% 100%, rgba(45, 212, 191, 0.35), transparent 50%),
                linear-gradient(155deg, #1e1b4b 0%, #3730a3 40%, #0f766e 100%);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            gap: 2rem;
        }
        .muy-hero::before,
        .muy-hero::after {
            content: '';
            position: absolute;
            border-radius: 999px;
            pointer-events: none;
            filter: blur(40px);
            opacity: 0.55;
        }
        .muy-hero::before {
            width: 320px;
            height: 320px;
            background: radial-gradient(circle, rgba(251, 191, 36, 0.5), transparent 65%);
            top: -80px;
            right: -80px;
        }
        .muy-hero::after {
            width: 360px;
            height: 360px;
            background: radial-gradient(circle, rgba(45, 212, 191, 0.55), transparent 65%);
            bottom: -110px;
            left: -90px;
        }
        @media (max-width: 959px) {
            .muy-hero {
                padding: 2.25rem 1.5rem 2.5rem;
            }
        }

        .muy-hero__top {
            display: flex;
            align-items: center;
            gap: 0.85rem;
            position: relative;
            z-index: 1;
        }
        .muy-hero__logo {
            width: 52px;
            height: 52px;
            object-fit: cover;
            border-radius: 12px;
            background: #fff;
            padding: 4px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.25);
        }
        .muy-hero__brand { display: flex; flex-direction: column; line-height: 1.2; }
        .muy-hero__brand b {
            font-size: 0.95rem;
            font-weight: 700;
            color: #fef3c7;
            letter-spacing: 0.01em;
        }
        .muy-hero__brand span {
            font-size: 0.72rem;
            color: rgba(226, 232, 240, 0.8);
            letter-spacing: 0.12em;
            text-transform: uppercase;
        }

        .muy-hero__center { position: relative; z-index: 1; }
        .muy-hero__eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.3rem 0.7rem;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.14);
            border: 1px solid rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(8px);
            font-size: 0.72rem;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            color: #fef3c7;
            margin-bottom: 1.1rem;
        }
        .muy-hero__eyebrow::before {
            content: '';
            width: 6px;
            height: 6px;
            border-radius: 999px;
            background: #fbbf24;
            box-shadow: 0 0 10px #fbbf24;
        }
        .muy-hero__title {
            margin: 0 0 0.9rem;
            font-size: clamp(1.8rem, 3.4vw, 2.55rem);
            line-height: 1.08;
            letter-spacing: -0.025em;
            font-weight: 700;
            color: #fff;
        }
        .muy-hero__title em {
            font-style: normal;
            background: linear-gradient(90deg, #fde68a, #5eead4);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }
        .muy-hero__lead {
            margin: 0 0 1.85rem;
            max-width: 34rem;
            font-size: 1rem;
            line-height: 1.6;
            color: rgba(241, 245, 249, 0.92);
        }

        .muy-pillars {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(11rem, 1fr));
            gap: 0.75rem;
        }
        .muy-pillar {
            display: flex;
            align-items: flex-start;
            gap: 0.6rem;
            padding: 0.75rem 0.9rem;
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.09);
            border: 1px solid rgba(255, 255, 255, 0.18);
            backdrop-filter: blur(10px);
            transition: transform 0.2s ease, background 0.2s ease;
        }
        .muy-pillar:hover {
            transform: translateY(-2px);
            background: rgba(255, 255, 255, 0.14);
        }
        .muy-pillar__icon {
            flex-shrink: 0;
            width: 32px;
            height: 32px;
            border-radius: 9px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, rgba(251, 191, 36, 0.35), rgba(94, 234, 212, 0.35));
            color: #fff;
        }
        .muy-pillar__icon svg { width: 18px; height: 18px; }
        .muy-pillar__txt b {
            display: block;
            font-size: 0.85rem;
            font-weight: 700;
            color: #fef3c7;
            margin-bottom: 0.1rem;
        }
        .muy-pillar__txt span {
            font-size: 0.72rem;
            color: rgba(226, 232, 240, 0.85);
            line-height: 1.4;
        }

        .muy-hero__foot {
            position: relative;
            z-index: 1;
            padding-top: 1.25rem;
            border-top: 1px solid rgba(255, 255, 255, 0.15);
        }
        .muy-hero__foot-title {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            font-size: 0.7rem;
            font-weight: 700;
            letter-spacing: 0.14em;
            text-transform: uppercase;
            color: #fef3c7;
            margin: 0 0 0.9rem;
        }
        .muy-hero__foot-title::before {
            content: '';
            width: 8px;
            height: 8px;
            border-radius: 999px;
            background: #34d399;
            box-shadow: 0 0 0 4px rgba(52, 211, 153, 0.25);
            animation: muyPulse 1.8s ease-in-out infinite;
        }
        @keyframes muyPulse {
            0%, 100% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.25); opacity: 0.75; }
        }
        .muy-achievements {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(9rem, 1fr));
            gap: 0.75rem;
        }
        .muy-achieve {
            padding: 0.85rem 0.95rem;
            border-radius: 12px;
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.12), rgba(255, 255, 255, 0.04));
            border: 1px solid rgba(255, 255, 255, 0.18);
            backdrop-filter: blur(10px);
        }
        .muy-achieve b {
            display: block;
            font-size: 1.35rem;
            font-weight: 800;
            line-height: 1;
            letter-spacing: -0.02em;
            background: linear-gradient(90deg, #fde68a, #5eead4);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }
        .muy-achieve span {
            display: block;
            margin-top: 0.35rem;
            font-size: 0.7rem;
            line-height: 1.35;
            color: rgba(226, 232, 240, 0.9);
        }
        .muy-hero__counting {
            margin: 0.85rem 0 0;
            font-size: 0.72rem;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: rgba(226, 232, 240, 0.7);
        }
        .muy-hero__counting em {
            font-style: normal;
            color: #fbbf24;
            font-weight: 700;
        }

        /* Right form panel */
        .muy-form-wrap {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1.5rem 3rem;
        }
        .muy-card {
            width: 100%;
            max-width: 400px;
            padding: 2.25rem 2rem 2rem;
            border-radius: 20px;
            background: rgba(255, 255, 255, 0.92);
            border: 1px solid rgba(255, 255, 255, 0.9);
            box-shadow:
                0 0 0 1px rgba(15, 23, 42, 0.04),
                0 24px 48px rgba(79, 70, 229, 0.12),
                0 12px 24px rgba(15, 23, 42, 0.06);
            backdrop-filter: blur(18px);
            -webkit-backdrop-filter: blur(18px);
            position: relative;
            overflow: hidden;
            box-sizing: border-box;
        }
        .muy-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #4f46e5, #7c3aed 45%, #0d9488);
        }
        .muy-card h1 {
            margin: 0;
            font-size: 1.5rem;
            font-weight: 700;
            letter-spacing: -0.025em;
            color: #0f172a;
        }
        .muy-card__lead {
            margin: 0.4rem 0 1.5rem;
            font-size: 0.9rem;
            color: #64748b;
        }
        .muy-card label {
            display: block;
            font-size: 0.8125rem;
            font-weight: 600;
            color: #334155;
            margin-bottom: 0.35rem;
        }
        .muy-card input[type="email"],
        .muy-card input[type="password"],
        .muy-card input[type="text"] {
            width: 100%;
            min-height: 2.75rem;
            padding: 0.6rem 0.85rem;
            margin-bottom: 1rem;
            border-radius: 10px;
            border: 1px solid #e2e8f0;
            background: #f8fafc;
            font-family: inherit;
            font-size: 1rem;
            box-sizing: border-box;
            transition: border-color 0.15s ease, background 0.15s ease, box-shadow 0.15s ease;
        }
        .muy-card input:hover {
            border-color: #cbd5e1;
            background: #fff;
        }
        .muy-card input:focus {
            outline: none;
            background: #fff;
            border-color: #818cf8;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.18);
        }
        .muy-card .app-auth-password-wrap { margin-bottom: 1rem; position: relative; }
        .muy-card .app-auth-password-wrap input { margin-bottom: 0; padding-right: 48px; }
        .muy-card .app-auth-password-toggle {
            position: absolute;
            right: 8px;
            top: 50%;
            transform: translateY(-50%);
            width: 36px;
            height: 36px;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            background: rgba(248, 250, 252, 0.95);
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #334155;
            transition: background 120ms ease, border-color 120ms ease, box-shadow 120ms ease;
        }
        .muy-card .app-auth-password-toggle:hover {
            background: #fff;
            border-color: #cbd5e1;
        }
        .muy-card .app-auth-password-toggle:focus-visible {
            outline: none;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.25);
            border-color: #6366f1;
        }
        .muy-card .app-auth-password-toggle svg { width: 18px; height: 18px; display: block; }
        .muy-card .app-auth-sr-only {
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            white-space: nowrap;
            border: 0;
        }
        .muy-remember {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.875rem;
            color: #475569;
            margin-bottom: 1.15rem;
        }
        .muy-remember label { margin: 0; font-weight: 500; color: #475569; }
        .muy-btn {
            display: block;
            width: 100%;
            min-height: 2.85rem;
            padding: 0.65rem 1rem;
            border: none;
            border-radius: 12px;
            font-family: inherit;
            font-size: 1rem;
            font-weight: 600;
            color: #fff;
            background: linear-gradient(135deg, #4f46e5, #0d9488);
            cursor: pointer;
            box-shadow: 0 10px 24px rgba(79, 70, 229, 0.28);
            transition: filter 0.15s, transform 0.15s, box-shadow 0.15s;
        }
        .muy-btn:hover {
            filter: brightness(1.05);
            transform: translateY(-1px);
            box-shadow: 0 14px 32px rgba(79, 70, 229, 0.35);
        }
        .muy-error {
            padding: 0.65rem 0.85rem;
            margin-bottom: 1.1rem;
            border-radius: 10px;
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #b91c1c;
            font-size: 0.875rem;
            line-height: 1.4;
        }
        .muy-card__foot {
            margin-top: 1.25rem;
            padding-top: 1rem;
            border-top: 1px solid #e2e8f0;
            font-size: 0.75rem;
            color: #94a3b8;
            text-align: center;
        }
    </style>
</head>
<body style="margin:0;">
<div class="muy-login">
    <aside class="muy-hero">
        <header class="muy-hero__top">
            <img class="muy-hero__logo" src="{{ asset('https://ukrbi.in/new/admin/muy.png') }}" alt="MUY logo">
            <div class="muy-hero__brand">
                <b>Mukhyamantri Udyamshala Yojana</b>
                <span>Government of Uttarakhand</span>
            </div>
        </header>

        <div class="muy-hero__center">
            <span class="muy-hero__eyebrow">Incubation Support Platform</span>
            <h2 class="muy-hero__title">
                Helping Uttarakhand's <em>founders &amp; entrepreneurs</em> turn ideas into real businesses.
            </h2>
            <p class="muy-hero__lead">
                A single space where incubatees, district hubs, and the state team work together — from application to mentorship to scale.
            </p>

            <div class="muy-pillars">
                <div class="muy-pillar">
                    <span class="muy-pillar__icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M4.5 16.5 12 9l7.5 7.5"/><path d="M4.5 12 12 4.5 19.5 12"/></svg>
                    </span>
                    <div class="muy-pillar__txt"><b>Nurture ideas</b><span>Guided path from CFA to a working venture</span></div>
                </div>
                <div class="muy-pillar">
                    <span class="muy-pillar__icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M12 14l9-5-9-5-9 5 9 5z"/><path d="M12 14v7"/><path d="M6 11v3a6 6 0 0 0 12 0v-3"/></svg>
                    </span>
                    <div class="muy-pillar__txt"><b>Mentor &amp; train</b><span>Financial, legal, marketing &amp; technical guidance</span></div>
                </div>
                <div class="muy-pillar">
                    <span class="muy-pillar__icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18"/><path d="M7 15l4-4 3 3 5-6"/></svg>
                    </span>
                    <div class="muy-pillar__txt"><b>Track growth</b><span>Targets, batches &amp; services tracked in real time</span></div>
                </div>
                <div class="muy-pillar">
                    <span class="muy-pillar__icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M3 12h18"/><path d="M12 3a14 14 0 0 1 0 18"/><path d="M12 3a14 14 0 0 0 0 18"/></svg>
                    </span>
                    <div class="muy-pillar__txt"><b>Connect ecosystem</b><span>District hubs, state team &amp; incubatees — one platform</span></div>
                </div>
            </div>
        </div>

        <footer class="muy-hero__foot">
            <p class="muy-hero__foot-title">Key achievements · so far</p>
            <div class="muy-achievements">
                <div class="muy-achieve">
                    <b>38,000+</b>
                    <span>Applications received</span>
                </div>
                <div class="muy-achieve">
                    <b>10,000+</b>
                    <span>Incubatees onboarded &amp; supported</span>
                </div>
                <div class="muy-achieve">
                    <b>13</b>
                    <span>Districts covered across Uttarakhand</span>
                </div>
                <div class="muy-achieve">
                    <b>2+</b>
                    <span>Regional incubation hubs</span>
                </div>
            </div>
            <p class="muy-hero__counting"><em>and counting…</em> the journey continues.</p>
        </footer>
    </aside>

    <section class="muy-form-wrap">
        <div class="muy-card">
            <h1>Welcome back</h1>
            <p class="muy-card__lead">Sign in to your MUY account.</p>

            @if ($errors->any())
                <div class="muy-error" role="alert">{{ $errors->first() }}</div>
            @endif

            <form method="post" action="{{ route('login') }}">
                @csrf
                <label for="email">Email</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required autocomplete="email" autofocus>

                <label for="password">Password</label>
                <div class="app-auth-password-wrap">
                    <input id="password" name="password" type="password" required autocomplete="current-password">
                    <button class="app-auth-password-toggle" type="button" data-toggle-password aria-label="Show password" title="Show password" aria-pressed="false">
                        <span class="app-auth-sr-only">Show password</span>
                        <svg data-icon-eye viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <path d="M2.2 12.1c2.3-4.8 6-7.1 9.8-7.1s7.5 2.3 9.8 7.1c.1.3.1.6 0 .8-2.3 4.8-6 7.1-9.8 7.1s-7.5-2.3-9.8-7.1a1 1 0 0 1 0-.8Z" stroke="currentColor" stroke-width="1.8" />
                            <path d="M12 16a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z" stroke="currentColor" stroke-width="1.8" />
                        </svg>
                        <svg data-icon-eye-off viewBox="0 0 24 24" fill="none" aria-hidden="true" style="display:none">
                            <path d="M9.9 4.6A10 10 0 0 1 12 4c3.8 0 7.5 2.3 9.8 7.1.1.3.1.6 0 .8a15.8 15.8 0 0 1-3.1 4.3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                            <path d="M6.1 6.1A15.8 15.8 0 0 0 2.2 12c-.1.3-.1.6 0 .8C4.5 17.6 8.2 20 12 20c1.2 0 2.4-.2 3.5-.6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                            <path d="M10.3 10.3a2.5 2.5 0 0 0 3.4 3.4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                            <path d="M3 3l18 18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                        </svg>
                    </button>
                </div>

                <div class="muy-remember">
                    <input id="remember" name="remember" type="checkbox" value="1">
                    <label for="remember">Remember me</label>
                </div>

                <button type="submit" class="muy-btn">Log in</button>
            </form>

            <p class="muy-card__foot">Mukhyamantri Udyamshala Yojana · Government of Uttarakhand</p>
        </div>
    </section>
</div>

<script>
    (() => {
        const btn = document.querySelector('[data-toggle-password]');
        const input = document.getElementById('password');
        if (!btn || !input) return;
        const eye = btn.querySelector('[data-icon-eye]');
        const eyeOff = btn.querySelector('[data-icon-eye-off]');
        const sr = btn.querySelector('.app-auth-sr-only');

        const sync = () => {
            const isPassword = input.type === 'password';
            const label = isPassword ? 'Show password' : 'Hide password';
            btn.setAttribute('aria-label', label);
            btn.setAttribute('title', label);
            btn.setAttribute('aria-pressed', String(!isPassword));
            if (sr) sr.textContent = label;
            if (eye && eyeOff) {
                eye.style.display = isPassword ? 'block' : 'none';
                eyeOff.style.display = isPassword ? 'none' : 'block';
            }
        };

        btn.addEventListener('click', () => {
            input.type = input.type === 'password' ? 'text' : 'password';
            input.focus({ preventScroll: true });
            sync();
        });

        sync();
    })();
</script>
</body>
</html>
