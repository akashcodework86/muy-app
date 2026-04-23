@php
    $logoUrl = asset('https://ukrbi.in/new/admin/muy.png');
    $u = auth()->user();
    $showAdminNav = $u && $u->role === 'state_admin';
    $showStateStaffNav = $u && $u->role === 'state_staff';
    $showHubNav = $u && $u->role === 'hub_admin';
    $showStaffNav = $u && $u->role === 'district_staff';
    $staffServiceModuleOn = $showStaffNav && app(\App\Services\AppSettingsService::class)->isEnabled('service_module.enabled');
    $showIncubateeNav = $u && $u->role === 'incubatee';
    $brandSub = match ($u->role ?? '') {
        'district_staff' => 'District staff',
        'hub_admin' => 'Hub admin',
        'state_staff' => 'State staff (SPOC)',
        'incubatee' => 'Incubatee',
        default => 'State admin',
    };
    $initials = collect(preg_split('/\s+/', trim((string) ($u->name ?? 'MUY'))) ?: [])
        ->filter()
        ->map(fn ($part) => mb_substr($part, 0, 1))
        ->take(2)
        ->implode('');
    if ($initials === '') {
        $initials = 'MU';
    }
    $r = request()->route()?->getName() ?? '';
    $activeNav = match (true) {
        $r === 'dashboard' => 'dashboard',
        str_starts_with($r, 'admin.cfa') => 'cfa',
        str_starts_with($r, 'admin.phase1-cfa') => 'phase1-cfa',
        str_starts_with($r, 'admin.phase2-cfa') => 'phase2-cfa',
        str_starts_with($r, 'admin.targets.state') => 'state',
        str_starts_with($r, 'admin.targets.district') => 'district',
        str_starts_with($r, 'admin.staff') => 'staff',
        str_starts_with($r, 'admin.state-staff') => 'state-staff',
        str_starts_with($r, 'admin.service-spocs') => 'service-spocs',
        str_starts_with($r, 'admin.team-performance') => 'team-performance',
        str_starts_with($r, 'admin.designations') => 'designations',
        str_starts_with($r, 'admin.audit') => 'audit',
        str_starts_with($r, 'admin.hub-batch-compliance') => 'hub-batch-compliance',
        str_starts_with($r, 'admin.service-catalog') => 'service-catalog',
        str_starts_with($r, 'admin.service-module-settings') => 'service-module-settings',
        str_starts_with($r, 'admin.batches') => 'admin-batches',
        str_starts_with($r, 'hub.batches') => 'hub-batches',
        $r === 'staff.monthly-targets' => 'staff-targets',
        str_starts_with($r, 'staff.services') => 'staff-services',
        str_starts_with($r, 'staff.applications') => 'staff-apps',
        str_starts_with($r, 'staff.phase1-data') => 'staff-phase1-data',
        str_starts_with($r, 'staff.phase2-data') => 'staff-phase2-data',
        str_starts_with($r, 'staff.batches') => 'staff-batches',
        str_starts_with($r, 'account.') => 'account',
        str_starts_with($r, 'incubatee.') => 'incubatee',
        str_starts_with($r, 'notifications.') => 'notifications',
        default => '',
    };
    $targetsStaffActive = in_array($activeNav, ['state', 'district', 'staff', 'state-staff', 'service-spocs', 'team-performance'], true);
    $cfaGroupActive = in_array($activeNav, ['cfa', 'phase1-cfa', 'phase2-cfa'], true);
    $opsGroupActive = in_array($activeNav, ['service-catalog', 'designations', 'hub-batch-compliance', 'admin-batches', 'service-module-settings'], true);

    // Inline SVG icon set (heroicons-style, uses currentColor so it respects active/hover states).
    $ico = [
        'dashboard'   => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg>',
        'cfa'         => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="6" y="4" width="12" height="17" rx="2"/><rect x="9" y="2" width="6" height="4" rx="1"/><path d="M9 11h6M9 14h6M9 17h4"/></svg>',
        'targets'     => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="5"/><circle cx="12" cy="12" r="1.3" fill="currentColor"/></svg>',
        'catalog'     => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><path d="M17.5 14v7M14 17.5h7"/></svg>',
        'more'        => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="5" cy="12" r="1.6" fill="currentColor" stroke="none"/><circle cx="12" cy="12" r="1.6" fill="currentColor" stroke="none"/><circle cx="19" cy="12" r="1.6" fill="currentColor" stroke="none"/></svg>',
        'batches'     => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3 3 7.5 12 12l9-4.5L12 3Z"/><path d="M3 12l9 4.5L21 12"/><path d="M3 16.5 12 21l9-4.5"/></svg>',
        'calendar'    => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18"/></svg>',
        'inbox'       => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 13l3-8h12l3 8"/><path d="M3 13v5a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-5"/><path d="M3 13h5l2 2h4l2-2h5"/></svg>',
        'pie'         => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12a9 9 0 1 1-9-9v9h9Z"/><path d="M14 3.5A9 9 0 0 1 21 10h-7V3.5Z"/></svg>',
        'mentor'      => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="9" cy="8" r="3.5"/><path d="M3 20a6 6 0 0 1 12 0"/><path d="M19 8v5M16.5 10.5h5"/></svg>',
        'book'        => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 5a2 2 0 0 1 2-2h13v16H6a2 2 0 0 0-2 2V5Z"/><path d="M6 3v16"/></svg>',
        'doc'         => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8l-5-5Z"/><path d="M14 3v5h5"/><path d="M8 12h8M8 16h6"/></svg>',
        'database'    => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><ellipse cx="12" cy="5" rx="8" ry="3"/><path d="M4 5v14c0 1.7 3.6 3 8 3s8-1.3 8-3V5"/><path d="M4 12c0 1.7 3.6 3 8 3s8-1.3 8-3"/></svg>',
        'flag'        => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 21V4"/><path d="M4 4h12l-2 4 2 4H4"/></svg>',
        'pin'         => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 21s-7-7.5-7-12a7 7 0 0 1 14 0c0 4.5-7 12-7 12Z"/><circle cx="12" cy="9" r="2.5"/></svg>',
        'users'       => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="8" cy="8" r="3.3"/><path d="M2 20a6 6 0 0 1 12 0"/><circle cx="17" cy="9" r="2.8"/><path d="M15 20a4 4 0 0 1 7 0"/></svg>',
        'bars'        => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 21h18"/><rect x="6" y="12" width="3" height="7"/><rect x="11" y="8" width="3" height="11"/><rect x="16" y="4" width="3" height="15"/></svg>',
        'badge'       => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="4" y="4" width="16" height="16" rx="2"/><circle cx="12" cy="10" r="2.5"/><path d="M8 17a4 4 0 0 1 8 0"/></svg>',
        'download'    => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8l-5-5Z"/><path d="M14 3v5h5"/><path d="M12 11v6M9 14l3 3 3-3"/></svg>',
        'cog'         => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-2.8 1.2V21a2 2 0 0 1-4 0v-.1a1.7 1.7 0 0 0-2.8-1.2l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1A1.7 1.7 0 0 0 4.6 15H3a2 2 0 0 1 0-4h.1A1.7 1.7 0 0 0 4.6 9a1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1A1.7 1.7 0 0 0 9 4.6V3a2 2 0 0 1 4 0v.1A1.7 1.7 0 0 0 15 4.6a1.7 1.7 0 0 0 1.8.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 0 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1Z"/></svg>',
        'shield'      => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3 4 6v6c0 5 4 8 8 9 4-1 8-4 8-9V6l-8-3Z"/><path d="m9 12 2 2 4-4"/></svg>',
        'logout'      => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><path d="M10 17l5-5-5-5"/><path d="M15 12H3"/></svg>',
    ];
    // Helper to render an icon as a labelled wrapper span (keeps markup short below).
    $i = fn ($key) => '<span class="admin-topbar__link-ico" aria-hidden="true">'.($ico[$key] ?? '').'</span>';
@endphp
<header class="admin-topbar">
    <div class="admin-topbar__inner">
        <a href="{{ route('dashboard') }}" class="admin-brand" title="Mukhyamantri Udyamshala Yojana">
            <img src="{{ $logoUrl }}" alt="MUY Logo" class="admin-brand__img">
            <span class="admin-brand__text">
                <span class="admin-brand__name">Mukhyamantri Udyamshala Yojana</span>
                <span class="admin-brand__sub">{{ $brandSub }}</span>
            </span>
        </a>

        @if ($showAdminNav)
        <nav class="admin-topbar__nav admin-topbar__nav--state-admin" aria-label="Main">
            <a href="{{ route('dashboard') }}" class="admin-topbar__link @if ($activeNav === 'dashboard') is-active @endif">
                {!! $i('dashboard') !!}<span class="admin-topbar__link-text">Dashboard</span>
            </a>

            <details class="admin-topbar__details">
                <summary class="admin-topbar__link admin-topbar__dropdown-trigger @if ($cfaGroupActive) is-active @endif">
                    {!! $i('cfa') !!}<span class="admin-topbar__link-text">CFA</span>
                </summary>
                <div class="admin-topbar__dropdown-panel" role="menu">
                    <p class="admin-topbar__dropdown-kicker" role="presentation">Applications</p>
                    <a href="{{ route('admin.cfa.index') }}" class="admin-topbar__dropdown-item @if ($activeNav === 'cfa') is-active @endif" role="menuitem">
                        {!! $i('doc') !!}<span>CFA applications</span>
                    </a>
                    <a href="{{ route('admin.phase1-cfa.index') }}" class="admin-topbar__dropdown-item @if ($activeNav === 'phase1-cfa') is-active @endif" role="menuitem">
                        {!! $i('database') !!}<span>CFA (FY 2024-25 Data)</span>
                    </a>
                    <a href="{{ route('admin.phase2-cfa.index') }}" class="admin-topbar__dropdown-item @if ($activeNav === 'phase2-cfa') is-active @endif" role="menuitem">
                        {!! $i('database') !!}<span>CFA (FY 2025-26 Data)</span>
                    </a>
                </div>
            </details>

            <details class="admin-topbar__details">
                <summary class="admin-topbar__link admin-topbar__dropdown-trigger @if ($targetsStaffActive) is-active @endif">
                    {!! $i('targets') !!}<span class="admin-topbar__link-text">Targets &amp; team</span>
                </summary>
                <div class="admin-topbar__dropdown-panel" role="menu">
                    <p class="admin-topbar__dropdown-kicker" role="presentation">Planning &amp; performance</p>
                    <a href="{{ route('admin.targets.state') }}" class="admin-topbar__dropdown-item @if ($activeNav === 'state') is-active @endif" role="menuitem">
                        {!! $i('flag') !!}<span>State targets</span>
                    </a>
                    <a href="{{ route('admin.targets.district') }}" class="admin-topbar__dropdown-item @if ($activeNav === 'district') is-active @endif" role="menuitem">
                        {!! $i('pin') !!}<span>District targets</span>
                    </a>
                    <a href="{{ route('admin.staff.index') }}" class="admin-topbar__dropdown-item @if ($activeNav === 'staff') is-active @endif" role="menuitem">
                        {!! $i('users') !!}<span>District staff</span>
                    </a>
                    <a href="{{ route('admin.state-staff.index') }}" class="admin-topbar__dropdown-item @if ($activeNav === 'state-staff') is-active @endif" role="menuitem">
                        {!! $i('shield') !!}<span>State staff (SPOC)</span>
                    </a>
                    <a href="{{ route('admin.service-spocs.index') }}" class="admin-topbar__dropdown-item @if ($activeNav === 'service-spocs') is-active @endif" role="menuitem">
                        {!! $i('pin') !!}<span>Service SPOCs (map)</span>
                    </a>
                    <a href="{{ route('admin.team-performance.index') }}" class="admin-topbar__dropdown-item @if ($activeNav === 'team-performance') is-active @endif" role="menuitem">
                        {!! $i('bars') !!}<span>Team performance</span>
                    </a>
                </div>
            </details>

            <a href="{{ route('admin.service-catalog.index') }}" class="admin-topbar__link @if ($activeNav === 'service-catalog') is-active @endif">
                {!! $i('catalog') !!}<span class="admin-topbar__link-text">Service catalog</span>
            </a>

            <details class="admin-topbar__details">
                <summary class="admin-topbar__link admin-topbar__dropdown-trigger @if ($opsGroupActive && $activeNav !== 'service-catalog') is-active @endif">
                    {!! $i('more') !!}<span class="admin-topbar__link-text">More</span>
                </summary>
                <div class="admin-topbar__dropdown-panel" role="menu">
                    <p class="admin-topbar__dropdown-kicker" role="presentation">Operations</p>
                    <a href="{{ route('admin.batches.index') }}" class="admin-topbar__dropdown-item @if ($activeNav === 'admin-batches') is-active @endif" role="menuitem">
                        {!! $i('batches') !!}<span>Batches</span>
                    </a>
                    <a href="{{ route('admin.designations.index') }}" class="admin-topbar__dropdown-item @if ($activeNav === 'designations') is-active @endif" role="menuitem">
                        {!! $i('badge') !!}<span>Designations</span>
                    </a>
                    <a href="{{ route('admin.hub-batch-compliance.index') }}" class="admin-topbar__dropdown-item @if ($activeNav === 'hub-batch-compliance') is-active @endif" role="menuitem">
                        {!! $i('download') !!}<span>Batch CDO PDF</span>
                    </a>
                    <a href="{{ route('admin.service-module-settings.edit') }}" class="admin-topbar__dropdown-item @if ($activeNav === 'service-module-settings') is-active @endif" role="menuitem">
                        {!! $i('cog') !!}<span>Service module settings</span>
                    </a>
                </div>
            </details>
        </nav>
        @endif

        @if ($showStateStaffNav)
        <nav class="admin-topbar__nav" aria-label="State staff">
            <a href="{{ route('dashboard') }}" class="admin-topbar__link @if ($activeNav === 'dashboard') is-active @endif">
                {!! $i('dashboard') !!}<span class="admin-topbar__link-text">Dashboard</span>
            </a>
        </nav>
        @endif

        @if ($showHubNav)
        <nav class="admin-topbar__nav" aria-label="Hub">
            <a href="{{ route('dashboard') }}" class="admin-topbar__link @if ($activeNav === 'dashboard') is-active @endif">
                {!! $i('dashboard') !!}<span class="admin-topbar__link-text">Dashboard</span>
            </a>
            <a href="{{ route('hub.batches.index') }}" class="admin-topbar__link @if ($activeNav === 'hub-batches') is-active @endif">
                {!! $i('batches') !!}<span class="admin-topbar__link-text">Batches</span>
            </a>
        </nav>
        @endif

        @if ($showStaffNav)
        <nav class="admin-topbar__nav" aria-label="Staff">
            <a href="{{ route('dashboard') }}" class="admin-topbar__link @if ($activeNav === 'dashboard') is-active @endif">
                {!! $i('dashboard') !!}<span class="admin-topbar__link-text">Dashboard</span>
            </a>
            <a href="{{ route('staff.monthly-targets') }}" class="admin-topbar__link @if ($activeNav === 'staff-targets') is-active @endif">
                {!! $i('calendar') !!}<span class="admin-topbar__link-text">Monthly targets</span>
            </a>
            <a href="{{ route('staff.applications') }}" class="admin-topbar__link @if ($activeNav === 'staff-apps') is-active @endif">
                {!! $i('inbox') !!}<span class="admin-topbar__link-text">Applications</span>
            </a>
            @if ($staffServiceModuleOn)
            <a href="{{ route('staff.services.index') }}" class="admin-topbar__link @if ($activeNav === 'staff-services') is-active @endif">
                {!! $i('doc') !!}<span class="admin-topbar__link-text">Services</span>
            </a>
            @endif
            <a href="{{ route('staff.batches.index') }}" class="admin-topbar__link @if ($activeNav === 'staff-batches') is-active @endif">
                {!! $i('batches') !!}<span class="admin-topbar__link-text">Batches</span>
            </a>
            <a href="{{ route('staff.phase2-data') }}" class="admin-topbar__link @if ($activeNav === 'staff-phase2-data') is-active @endif">
                {!! $i('pie') !!}<span class="admin-topbar__link-text">FY 2025-26 Data</span>
            </a>
            <a href="{{ route('staff.phase1-data') }}" class="admin-topbar__link @if ($activeNav === 'staff-phase1-data') is-active @endif">
                {!! $i('database') !!}<span class="admin-topbar__link-text">CFA (FY 2024-25 Data)</span>
            </a>
        </nav>
        @endif

        @if ($showIncubateeNav)
        <nav class="admin-topbar__nav" aria-label="Incubatee">
            <a href="{{ route('incubatee.dashboard') }}" class="admin-topbar__link @if ($activeNav === 'incubatee' && request()->routeIs('incubatee.dashboard')) is-active @endif">
                {!! $i('dashboard') !!}<span class="admin-topbar__link-text">Dashboard</span>
            </a>
            <a href="{{ route('incubatee.mentorship.index') }}" class="admin-topbar__link @if (request()->routeIs('incubatee.mentorship.*')) is-active @endif">
                {!! $i('mentor') !!}<span class="admin-topbar__link-text">Request mentorship</span>
            </a>
            <a href="{{ route('incubatee.udmita-kosh') }}" class="admin-topbar__link @if (request()->routeIs('incubatee.udmita-kosh')) is-active @endif">
                {!! $i('book') !!}<span class="admin-topbar__link-text">Udmita Kosh</span>
            </a>
        </nav>
        @endif

        <div class="admin-topbar__right">
            @include('partials.live-ops-drawer')

            @if (!empty($showNotificationBell))
            <details class="admin-topbar__details admin-topbar__details--notifications">
                <summary class="admin-topbar__notif-summary" aria-label="Notifications">
                    <span class="admin-topbar__notif-icon" aria-hidden="true">
                        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                        </svg>
                    </span>
                    @if (($unreadNotificationCount ?? 0) > 0)
                        <span class="admin-topbar__notif-badge">{{ $unreadNotificationCount > 99 ? '99+' : $unreadNotificationCount }}</span>
                    @endif
                </summary>
                <div class="admin-topbar__dropdown-panel admin-topbar__dropdown-panel--notifications" role="menu">
                    <div class="admin-topbar__notif-head">
                        <span>Notifications</span>
                        @if (($unreadNotificationCount ?? 0) > 0)
                            <form method="post" action="{{ route('notifications.read-all') }}" class="admin-topbar__notif-markall">
                                @csrf
                                <button type="submit">Mark all read</button>
                            </form>
                        @endif
                    </div>
                    @forelse ($notificationsPreview ?? [] as $n)
                        @php
                            $d = $n->data ?? [];
                            $isUnread = $n->read_at === null;
                        @endphp
                        <a href="{{ route('notifications.open', $n->id) }}" class="admin-topbar__notif-item @if($isUnread) is-unread @endif" role="menuitem">
                            <span class="admin-topbar__notif-item-title">{{ $d['title'] ?? 'Notification' }}</span>
                            <span class="admin-topbar__notif-item-body">{{ \Illuminate\Support\Str::limit($d['body'] ?? '', 120) }}</span>
                            <span class="admin-topbar__notif-item-time">{{ $n->created_at?->timezone(config('app.timezone'))->diffForHumans() }}</span>
                        </a>
                    @empty
                        <p class="admin-topbar__notif-empty">No notifications yet.</p>
                    @endforelse
                    <a href="{{ route('notifications.index') }}" class="admin-topbar__notif-footer">View all</a>
                </div>
            </details>
            @endif

            <details class="admin-topbar__details admin-topbar__details--profile">
                <summary class="admin-topbar__profile-summary" aria-label="Account menu">
                    <div class="admin-topbar__profile" title="{{ $u->email }}">
                        @if ($u->avatar_path)
                            <img src="{{ $u->avatarUrl() }}" alt="" class="admin-topbar__avatar admin-topbar__avatar--photo" width="35" height="35">
                        @else
                            <span class="admin-topbar__avatar">{{ strtoupper($initials) }}</span>
                        @endif
                        <span class="admin-topbar__user-wrap">
                            <span class="admin-topbar__user">{{ $u->name }}</span>
                            @if (! $showAdminNav)
                                <span class="admin-topbar__user-role">{{ str_replace('_', ' ', $u->role ?? 'user') }}</span>
                            @endif
                        </span>
                    </div>
                </summary>
                <div class="admin-topbar__dropdown-panel admin-topbar__dropdown-panel--profile" role="menu">
                    <p class="admin-topbar__dropdown-profile-name">{{ $u->name }}</p>
                    <p class="admin-topbar__dropdown-profile-email">{{ $u->email }}</p>
                    <hr class="admin-topbar__dropdown-hr" aria-hidden="true">
                    <a href="{{ route('account.settings.edit') }}" class="admin-topbar__dropdown-item @if (request()->routeIs('account.settings.*')) is-active @endif" role="menuitem">
                        {!! $i('cog') !!}<span>Settings</span>
                    </a>
                    @if ($showAdminNav)
                        <a href="{{ route('admin.audit.index') }}" class="admin-topbar__dropdown-item @if ($activeNav === 'audit') is-active @endif" role="menuitem">
                            {!! $i('shield') !!}<span>Audit log</span>
                        </a>
                    @endif
                    <form method="post" action="{{ route('logout') }}" class="admin-topbar__dropdown-logout">
                        @csrf
                        <button type="submit" class="admin-topbar__dropdown-item admin-topbar__dropdown-item--button" role="menuitem">
                            {!! $i('logout') !!}<span>Log out</span>
                        </button>
                    </form>
                </div>
            </details>
        </div>
    </div>
</header>
<script>
(function () {
    function initTopbarDropdowns() {
        var allDetails = Array.prototype.slice.call(
            document.querySelectorAll('.admin-topbar .admin-topbar__details')
        );
        if (!allDetails.length) return;

        /* When any <details> opens, close every other one */
        allDetails.forEach(function (det) {
            var summary = det.querySelector('summary');
            if (!summary) return;
            summary.addEventListener('click', function () {
                if (!det.open) {
                    /* About to open — close siblings first */
                    allDetails.forEach(function (other) {
                        if (other !== det && other.open) {
                            other.removeAttribute('open');
                        }
                    });
                }
            });
        });

        /* Click anywhere outside the topbar closes all open dropdowns */
        document.addEventListener('click', function (e) {
            if (!e.target.closest('.admin-topbar')) {
                allDetails.forEach(function (d) {
                    if (d.open) d.removeAttribute('open');
                });
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initTopbarDropdowns);
    } else {
        initTopbarDropdowns();
    }
}());
</script>
