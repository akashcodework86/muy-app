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
        .app-auth-body--mis {
            background:
                radial-gradient(ellipse 90% 55% at 50% -15%, rgba(99, 102, 241, 0.18), transparent 55%),
                radial-gradient(ellipse 70% 50% at 100% 0%, rgba(45, 212, 191, 0.14), transparent 50%),
                radial-gradient(ellipse 60% 45% at 0% 100%, rgba(129, 140, 248, 0.12), transparent 45%),
                linear-gradient(160deg, #f0f9ff 0%, #f5f3ff 38%, #ecfeff 72%, #f8fafc 100%);
        }
        .app-auth-wrap--mis {
            padding: 1.75rem 1.25rem;
        }
        .app-auth-card--mis {
            position: relative;
            max-width: 400px;
            padding: 2.25rem 2rem 2rem;
            border-radius: 20px;
            border: 1px solid rgba(255, 255, 255, 0.9);
            box-shadow:
                0 0 0 1px rgba(15, 23, 42, 0.04),
                0 24px 48px rgba(79, 70, 229, 0.1),
                0 12px 24px rgba(15, 23, 42, 0.06);
            overflow: hidden;
        }
        .app-auth-card--mis::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #4f46e5, #7c3aed 45%, #0d9488);
        }
        .app-auth-brand--mis {
            text-align: center;
            margin-bottom: 1.35rem;
        }
        .app-auth-brand--mis .app-auth-logo-ring {
            display: inline-flex;
            padding: 0.35rem;
            margin-bottom: 1rem;
            border-radius: 18px;
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.95), rgba(238, 242, 255, 0.9));
            box-shadow:
                0 0 0 1px rgba(99, 102, 241, 0.12),
                0 12px 28px rgba(99, 102, 241, 0.15);
        }
        .app-auth-brand--mis .app-auth-logo {
            display: block;
            width: 88px;
            height: 88px;
            margin: 0;
            object-fit: cover;
            border-radius: 14px;
            border: 1px solid rgba(226, 232, 240, 0.9);
            box-shadow: 0 4px 14px rgba(15, 23, 42, 0.08);
            background: #fff;
        }
        .app-auth-brand--mis .app-auth-kicker {
            margin: 0 0 0.4rem;
            font-size: 0.68rem;
            font-weight: 700;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: #6366f1;
        }
        .app-auth-brand--mis h1 {
            margin: 0;
            font-size: 1.65rem;
            font-weight: 700;
            letter-spacing: -0.03em;
            color: #0f172a;
            line-height: 1.2;
        }
        .app-auth-brand--mis .app-auth-subtitle {
            margin: 0.55rem 0 0;
            font-size: 0.875rem;
            font-weight: 500;
            color: #64748b;
            letter-spacing: 0.02em;
        }
        .app-auth-card--mis .app-auth-lead {
            text-align: center;
            margin: 0 0 1.35rem;
            font-size: 0.9rem;
            color: #475569;
        }
        .app-auth-card--mis label {
            font-size: 0.8125rem;
            color: #334155;
        }
        .app-auth-card--mis input[type="email"],
        .app-auth-card--mis input[type="password"],
        .app-auth-card--mis input[type="text"] {
            min-height: 2.75rem;
            padding: 0.6rem 0.85rem;
            margin-bottom: 1rem;
            border-radius: 10px;
            border: 1px solid #e2e8f0;
            background: #f8fafc;
            transition: border-color 0.15s ease, background 0.15s ease, box-shadow 0.15s ease;
        }
        .app-auth-card--mis input:hover {
            border-color: #cbd5e1;
            background: #fff;
        }
        .app-auth-card--mis input:focus {
            background: #fff;
            border-color: #818cf8;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.18);
        }
        .app-auth-card--mis .app-auth-password-wrap {
            margin-bottom: 1rem;
        }
        .app-auth-card--mis .app-auth-password-wrap input {
            margin-bottom: 0;
        }
        .app-auth-card--mis .app-auth-password-toggle {
            border-radius: 8px;
            border-color: #e2e8f0;
            background: rgba(248, 250, 252, 0.95);
        }
        .app-auth-card--mis .app-auth-remember {
            margin-bottom: 1.15rem;
        }
        .app-auth-card--mis .app-auth-btn-primary {
            min-height: 2.85rem;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 600;
            box-shadow: 0 10px 24px rgba(79, 70, 229, 0.28);
        }
        .app-auth-card--mis .app-auth-btn-primary:hover {
            box-shadow: 0 14px 32px rgba(79, 70, 229, 0.35);
        }
        .app-auth-error--mis {
            padding: 0.65rem 0.85rem;
            margin-bottom: 1.1rem;
            border-radius: 10px;
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #b91c1c;
            font-size: 0.875rem;
            line-height: 1.4;
        }
        .app-auth-password-wrap {
            position: relative;
        }
        .app-auth-password-wrap input[type="password"],
        .app-auth-password-wrap input[type="text"] {
            padding-right: 48px;
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
            border: 1px solid rgba(0, 0, 0, 0.1);
            background: rgba(255, 255, 255, 0.65);
            cursor: pointer;
            font: inherit;
            color: rgba(0, 0, 0, 0.7);
            transition: background 120ms ease, border-color 120ms ease, box-shadow 120ms ease, color 120ms ease;
        }
        .app-auth-password-toggle:hover {
            background: rgba(255, 255, 255, 0.95);
            border-color: rgba(0, 0, 0, 0.18);
            color: rgba(0, 0, 0, 0.85);
        }
        .app-auth-password-toggle:focus-visible {
            outline: none;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.25);
            border-color: rgba(99, 102, 241, 0.55);
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
<body class="app-auth-body app-auth-body--mis">
    <div class="app-auth-wrap app-auth-wrap--mis">
        <div class="app-auth-card app-auth-card--mis">
            <div class="app-auth-brand app-auth-brand--mis">
                <div class="app-auth-logo-ring">
                    <img class="app-auth-logo" src="{{ asset('https://ukrbi.in/new/admin/muy.png') }}" alt="Mukhyamantri Udyamshala Yojana">
                </div>
                <p class="app-auth-kicker">Mukhyamantri Udyamshala Yojana</p>
                <h1>Welcome to MIS</h1>
                <p class="app-auth-subtitle">MUY • Incubation Support Platform</p>
            </div>
            <p class="app-auth-lead">Sign in to your account.</p>

            @if ($errors->any())
                <div class="app-auth-error app-auth-error--mis" role="alert">{{ $errors->first() }}</div>
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
