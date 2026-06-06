<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Admin') — {{ config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700&display=swap" rel="stylesheet">
    @include('partials.admin-shell-styles')
    @if(\App\Support\StateAdminTheme::appliesToRole(auth()->user()?->role))
        <style>@include('dashboards.state-admin._theme-styles') @include('dashboards.state-admin._sad-layout-styles')</style>
    @endif
    @stack('styles')
</head>
<body class="admin-app-body @if(in_array(auth()->user()?->role, ['state_admin', 'hub_admin', 'district_staff'], true)) admin-app-body--dashboard @endif @if(auth()->user()?->role === 'state_admin') admin-app-body--state-premium admin-app-body--state-theme-{{ $stateAdminTheme ?? 'revamp' }} @endif @if(auth()->user()?->role === 'hub_admin') admin-app-body--hub-premium admin-app-body--hub-admin admin-app-body--state-theme-{{ $stateAdminTheme ?? 'revamp' }} @endif @if(auth()->user()?->role === 'district_staff') admin-app-body--staff-premium admin-app-body--state-theme-{{ $stateAdminTheme ?? 'revamp' }} @endif @yield('body_class')">
    @include('partials.admin-topbar')
    <main class="admin-main">
        <div class="admin-page-head">
            <h1>@yield('heading')</h1>
            <p class="admin-page-meta">{{ auth()->user()->name }} · <span class="pill">{{ auth()->user()->role ?? '—' }}</span></p>
        </div>
        @if (session('status'))
            <div class="banner">{{ session('status') }}</div>
        @endif
        @include('partials.flash-profile-photo-reminder')
        @include('partials.staff-daily-check-in-reminder')
        @if ($errors->any())
            <div class="error-banner">{{ $errors->first() }}</div>
        @endif
        @yield('content')
    </main>
    @include('partials.app-footer')
    @stack('scripts')
</body>
</html>
