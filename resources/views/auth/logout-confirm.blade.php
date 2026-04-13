<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Log out — {{ config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700&display=swap" rel="stylesheet">
    @include('partials.admin-shell-styles')
    <style>
        .app-auth-body { min-height: 100vh; margin: 0; display: flex; align-items: center; justify-content: center; padding: 1.5rem; box-sizing: border-box; }
        .app-auth-wrap { width: 100%; max-width: 420px; }
        .app-auth-card { background: #fff; border-radius: 16px; padding: 1.75rem 1.5rem; box-shadow: 0 4px 24px rgba(15, 23, 42, 0.08); border: 1px solid #e2e8f0; }
        .app-auth-lead { margin: 0 0 1.25rem; font-size: 0.95rem; color: #475569; line-height: 1.5; }
        .app-auth-actions { display: flex; flex-wrap: wrap; gap: 0.65rem; }
        .app-auth-btn-primary {
            flex: 1 1 auto; min-width: 8rem; padding: 0.65rem 1rem; border-radius: 10px; border: none;
            background: linear-gradient(135deg, #4f46e5, #6366f1); color: #fff; font-weight: 700; font-size: 0.9rem; cursor: pointer; font-family: inherit;
        }
        .app-auth-btn-ghost {
            flex: 1 1 auto; min-width: 8rem; padding: 0.65rem 1rem; border-radius: 10px;
            border: 1px solid #e2e8f0; background: #f8fafc; color: #334155; font-weight: 600; font-size: 0.9rem; cursor: pointer; font-family: inherit; text-decoration: none; text-align: center; box-sizing: border-box;
        }
    </style>
</head>
<body class="app-auth-body">
    <div class="app-auth-wrap">
        <div class="app-auth-card">
            <p class="app-auth-lead">You are about to log out of MIS. Use the button below to confirm.</p>
            <div class="app-auth-actions">
                <a href="{{ route('dashboard') }}" class="app-auth-btn-ghost">Cancel</a>
                <form method="post" action="{{ route('logout') }}" style="flex:1 1 auto;min-width:8rem;margin:0;">
                    @csrf
                    <button type="submit" class="app-auth-btn-primary" style="width:100%;">Log out</button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
