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
        .app-auth-brand h1 {
            margin: 0;
        }
        .app-auth-brand h2 {
            margin: 10px 0 0;
            font-size: 16px;
            line-height: 1.25;
            font-weight: 700;
        }
        .app-auth-brand .app-auth-subtitle {
            margin: 6px 0 0;
            opacity: 0.85;
            font-weight: 500;
        }
        .app-auth-logo {
            display: block;
            width: 96px;
            height: 96px;
            margin: 0 auto 12px;
            object-fit: cover;
            border-radius: 14px;
            border: 1px solid rgba(0, 0, 0, 0.08);
            box-shadow: 0 10px 24px rgba(15, 23, 42, 0.12);
            background: #fff;
        }
        .app-auth-password-wrap {
            position: relative;
            margin-bottom: 1rem;
        }
        .app-auth-password-wrap input[type="password"],
        .app-auth-password-wrap input[type="text"] {
            padding-right: 48px;
            margin-bottom: 0;
        }
        .app-auth-password-toggle {
            position: absolute;
            right: 8px;
            top: 50%;
            transform: translateY(-50%);
            width: 36px;
            height: 36px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
            border: 1px solid rgba(0, 0, 0, 0.10);
            background: rgba(255, 255, 255, 0.65);
            cursor: pointer;
            font: inherit;
            color: rgba(0, 0, 0, 0.70);
            transition: background 120ms ease, border-color 120ms ease, box-shadow 120ms ease, color 120ms ease;
        }
        .app-auth-password-toggle:hover {
            background: rgba(255, 255, 255, 0.95);
            border-color: rgba(0, 0, 0, 0.18);
            color: rgba(0, 0, 0, 0.85);
        }
        .app-auth-password-toggle:focus-visible {
            outline: none;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.25);
            border-color: rgba(59, 130, 246, 0.55);
        }
        .app-auth-password-toggle svg {
            width: 18px;
            height: 18px;
            display: block;
        }
        .app-auth-sr-only {
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
    </style>
</head>
<body class="app-auth-body">
    <div class="app-auth-wrap">
        <div class="app-auth-card">
            <div class="app-auth-brand">
                <img class="app-auth-logo" src="{{ asset('images/muy.jpg') }}" alt="Mukhyamantri Udyamshala Yojana">
                <h1>Welcome to MIS</h1>
                <h2>(Mukhyamantri Udyamshala Yojana)</h2>
                <p class="app-auth-subtitle">RBI • Incubation Support Platform</p>
            </div>
            <p class="app-auth-lead">Sign in to your account.</p>

            @if ($errors->any())
                <div class="app-auth-error">{{ $errors->first() }}</div>
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

                <div class="app-auth-remember">
                    <input id="remember" name="remember" type="checkbox" value="1">
                    <label for="remember">Remember me</label>
                </div>

                <button type="submit" class="app-auth-btn-primary">Log in</button>
            </form>

            <!-- <p class="app-auth-hint">Demo: <strong>stateadmin@local.test</strong> (state) · <strong>hubadmin@local.test</strong> (hub) · <strong>staff.almora@local.test</strong> (district staff) — password <strong>password</strong> (<code>migrate:fresh --seed</code>).</p> -->
        </div>
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
