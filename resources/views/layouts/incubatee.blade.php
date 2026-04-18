<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#5b21b6">
    <title>@yield('title', 'My hub') — {{ config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Sora:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --ink: #0f172a;
            --muted: #64748b;
            --card: rgba(255, 255, 255, 0.72);
            --card-border: rgba(255, 255, 255, 0.55);
            --shadow: 0 25px 50px -12px rgba(15, 23, 42, 0.35);
            --glow-cyan: rgba(34, 211, 238, 0.45);
            --glow-fuchsia: rgba(232, 121, 249, 0.4);
            --accent: #7c3aed;
            --accent2: #ec4899;
            --accent3: #06b6d4;
            --success: #059669;
            --warning: #d97706;
        }
        * { box-sizing: border-box; }
        body.incubatee-app {
            margin: 0;
            min-height: 100vh;
            font-family: 'Sora', system-ui, sans-serif;
            color: var(--ink);
            background:
                radial-gradient(1200px 800px at 10% -10%, rgba(124, 58, 237, 0.35), transparent 55%),
                radial-gradient(900px 600px at 90% 0%, rgba(236, 72, 153, 0.3), transparent 50%),
                radial-gradient(800px 500px at 50% 100%, rgba(6, 182, 212, 0.25), transparent 45%),
                linear-gradient(165deg, #1e1b4b 0%, #312e81 35%, #4c1d95 65%, #831843 100%);
            background-attachment: fixed;
        }
        .incubatee-shell {
            max-width: 1120px;
            margin: 0 auto;
            padding: 1.25rem 1.25rem 3rem;
        }
        .incubatee-nav {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding: 0.85rem 1.15rem;
            background: var(--card);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid var(--card-border);
            border-radius: 18px;
            box-shadow: var(--shadow);
            margin-bottom: 1.5rem;
        }
        .incubatee-brand {
            display: flex;
            align-items: center;
            gap: 0.65rem;
            text-decoration: none;
            color: inherit;
        }
        .incubatee-brand__mark {
            width: 42px;
            height: 42px;
            border-radius: 14px;
            background: linear-gradient(135deg, #a855f7, #ec4899, #22d3ee);
            display: grid;
            place-items: center;
            font-size: 1.15rem;
            box-shadow: 0 8px 24px rgba(124, 58, 237, 0.35);
        }
        .incubatee-brand__text {
            font-family: 'Outfit', sans-serif;
            font-weight: 800;
            font-size: 1.05rem;
            letter-spacing: -0.02em;
            background: linear-gradient(90deg, #4c1d95, #db2777, #0891b2);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }
        .incubatee-nav__actions {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 0.5rem;
        }
        .incubatee-nav__actions a,
        .incubatee-nav__actions button {
            font-family: inherit;
            font-size: 0.875rem;
            font-weight: 600;
            padding: 0.45rem 0.85rem;
            border-radius: 10px;
            text-decoration: none;
            border: none;
            cursor: pointer;
            transition: transform 0.15s ease, box-shadow 0.15s ease;
        }
        .incubatee-nav__actions a[href*="settings"] {
            background: rgba(255, 255, 255, 0.9);
            color: #4c1d95;
            border: 1px solid rgba(124, 58, 237, 0.2);
        }
        .incubatee-nav__actions form button {
            background: linear-gradient(135deg, #f43f5e, #ec4899);
            color: #fff;
            box-shadow: 0 8px 20px rgba(236, 72, 153, 0.35);
        }
        .incubatee-nav__actions a:hover,
        .incubatee-nav__actions button:hover {
            transform: translateY(-1px);
        }
        .incubatee-user-pill {
            font-size: 0.8rem;
            color: var(--muted);
            padding: 0.35rem 0.65rem;
            background: rgba(255, 255, 255, 0.5);
            border-radius: 999px;
        }
        .incubatee-flash {
            padding: 0.75rem 1rem;
            border-radius: 12px;
            margin-bottom: 1rem;
            font-size: 0.9rem;
            background: rgba(255, 255, 255, 0.85);
            border: 1px solid rgba(16, 185, 129, 0.35);
            color: #065f46;
        }
    </style>
    @stack('styles')
</head>
<body class="incubatee-app">
    <div class="incubatee-shell">
        <header class="incubatee-nav">
            <a href="{{ route('incubatee.dashboard') }}" class="incubatee-brand">
                <span class="incubatee-brand__mark" aria-hidden="true">✦</span>
                <span class="incubatee-brand__text">MUY · Incubatee hub</span>
            </a>
            <span class="incubatee-user-pill">{{ auth()->user()->name }}</span>
            <div class="incubatee-nav__actions">
                <a href="{{ route('account.settings.edit') }}">Account settings</a>
                <form method="post" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit">Log out</button>
                </form>
            </div>
        </header>

        @if (session('status'))
            <div class="incubatee-flash" role="status">{{ session('status') }}</div>
        @endif

        @yield('content')
    </div>
    @stack('scripts')
</body>
</html>
