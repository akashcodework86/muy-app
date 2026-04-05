<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Log in — {{ config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700&display=swap" rel="stylesheet">
    @include('partials.admin-shell-styles')
</head>
<body class="app-auth-body">
    <div class="app-auth-wrap">
        <div class="app-auth-card">
            <h1>{{ config('app.name') }}</h1>
            <p class="app-auth-lead">Sign in to your account.</p>

            @if ($errors->any())
                <div class="app-auth-error">{{ $errors->first() }}</div>
            @endif

            <form method="post" action="{{ route('login') }}">
                @csrf
                <label for="email">Email</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required autocomplete="email" autofocus>

                <label for="password">Password</label>
                <input id="password" name="password" type="password" required autocomplete="current-password">

                <div class="app-auth-remember">
                    <input id="remember" name="remember" type="checkbox" value="1">
                    <label for="remember">Remember me</label>
                </div>

                <button type="submit" class="app-auth-btn-primary">Log in</button>
            </form>

            <p class="app-auth-hint">Demo: <strong>stateadmin@local.test</strong> (state) · <strong>hubadmin@local.test</strong> (hub) · <strong>staff.almora@local.test</strong> (district staff) — password <strong>password</strong> (<code>migrate:fresh --seed</code>).</p>
        </div>
    </div>
</body>
</html>
