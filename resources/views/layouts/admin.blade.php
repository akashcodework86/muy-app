<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Admin') — {{ config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700&family=Noto+Sans+Devanagari:wght@400;500;600;700&display=swap" rel="stylesheet">
    @include('partials.admin-shell-styles')
    @if(\App\Support\StateAdminTheme::appliesToRole(auth()->user()?->role))
        <style>@include('dashboards.state-admin._theme-styles') @include('dashboards.state-admin._sad-layout-styles')</style>
    @endif
    @if(app()->getLocale() === 'hi')
        <style>body.admin-app-body { font-family: 'Noto Sans Devanagari', 'DM Sans', sans-serif; }</style>
    @endif
    @stack('styles')
</head>
<body class="admin-app-body @if(in_array(auth()->user()?->role, ['state_admin', 'hub_admin', 'district_staff', 'incubatee'], true)) admin-app-body--dashboard @endif @if(auth()->user()?->role === 'state_admin') admin-app-body--state-premium admin-app-body--state-theme-{{ $stateAdminTheme ?? 'revamp' }} @endif @if(auth()->user()?->role === 'hub_admin') admin-app-body--hub-premium admin-app-body--hub-admin admin-app-body--state-theme-{{ $stateAdminTheme ?? 'revamp' }} @endif @if(auth()->user()?->role === 'district_staff') admin-app-body--staff-premium admin-app-body--state-theme-{{ $stateAdminTheme ?? 'revamp' }} @endif @if(auth()->user()?->role === 'incubatee') admin-app-body--incubatee admin-app-body--state-premium admin-app-body--state-theme-{{ $stateAdminTheme ?? 'revamp' }} @endif @yield('body_class')">
    @include('partials.admin-topbar')
    <main class="admin-main">
        <div class="admin-page-head">
            <h1>@yield('heading')</h1>
            @hasSection('page_meta')
                @yield('page_meta')
            @else
                <p class="admin-page-meta">{{ auth()->user()->name }} · <span class="pill">{{ auth()->user()->role ?? '—' }}</span></p>
            @endif
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
    @if(auth()->user()?->role === 'state_admin' && !request()->routeIs('admin.mis-assistant.*'))
        <a href="{{ route('admin.mis-assistant.index') }}" aria-label="Open MIS Data Assistant" title="Ask MIS Data Assistant" style="position:fixed;right:22px;bottom:22px;z-index:80;display:inline-flex;align-items:center;gap:.5rem;padding:.72rem .95rem;border-radius:999px;background:linear-gradient(135deg,#4338ca,#0f766e);color:#fff;text-decoration:none;font-weight:800;font-size:.78rem;box-shadow:0 10px 28px rgba(30,41,59,.25)">
            <span aria-hidden="true" style="font-size:1rem">✦</span> Ask MIS
        </a>
    @endif
    @include('partials.app-footer')
    @stack('scripts')
</body>
</html>
