<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700&display=swap" rel="stylesheet">
    @include('partials.admin-shell-styles')
    </head>
<body class="app-auth-body">
    <div class="app-auth-wrap">
        <div class="app-auth-card app-auth-card--wide" style="text-align:center;">
            <h1 style="margin-bottom:0.5rem;">{{ config('app.name') }}</h1>
            <p class="app-auth-lead" style="margin-bottom:1.5rem;">State MIS targets, staff, and CFA applications.</p>
                    @auth
                <a href="{{ url('/dashboard') }}" class="app-auth-btn-primary">Go to dashboard</a>
            @elseif (Route::has('login'))
                <a href="{{ route('login') }}" class="app-auth-btn-primary">Log in</a>
            @endif
        </div>
    </div>
    </body>
</html>
