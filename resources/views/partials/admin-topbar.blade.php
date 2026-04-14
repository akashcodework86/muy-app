@php
    $logoUrl = asset('https://ukrbi.in/new/admin/muy.jpg');
    $u = auth()->user();
    $showAdminNav = $u && $u->role === 'state_admin';
    $showHubNav = $u && $u->role === 'hub_admin';
    $showStaffNav = $u && $u->role === 'district_staff';
    $brandSub = match ($u->role ?? '') {
        'district_staff' => 'District staff',
        'hub_admin' => 'Hub admin',
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
        str_starts_with($r, 'admin.phase2-cfa') => 'phase2-cfa',
        str_starts_with($r, 'admin.targets.state') => 'state',
        str_starts_with($r, 'admin.targets.district') => 'district',
        str_starts_with($r, 'admin.staff') => 'staff',
        str_starts_with($r, 'admin.designations') => 'designations',
        str_starts_with($r, 'admin.audit') => 'audit',
        str_starts_with($r, 'admin.hub-batch-compliance') => 'hub-batch-compliance',
        str_starts_with($r, 'hub.batches') => 'hub-batches',
        $r === 'staff.monthly-targets' => 'staff-targets',
        $r === 'staff.applications' => 'staff-apps',
        str_starts_with($r, 'staff.phase2-data') => 'staff-phase2-data',
        str_starts_with($r, 'account.') => 'account',
        default => '',
    };
    $targetsStaffActive = in_array($activeNav, ['state', 'district', 'staff'], true);
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
            <a href="{{ route('dashboard') }}" class="admin-topbar__link @if ($activeNav === 'dashboard') is-active @endif">Dashboard</a>
            <a href="{{ route('admin.cfa.index') }}" class="admin-topbar__link @if ($activeNav === 'cfa') is-active @endif">CFA applications</a>
            <a href="{{ route('admin.phase2-cfa.index') }}" class="admin-topbar__link @if ($activeNav === 'phase2-cfa') is-active @endif">CFA (FY 2025-26 Data)</a>
            <details class="admin-topbar__details">
                <summary class="admin-topbar__link admin-topbar__dropdown-trigger @if ($targetsStaffActive) is-active @endif">
                    Targets &amp; staff
                </summary>
                <div class="admin-topbar__dropdown-panel" role="menu">
                    <p class="admin-topbar__dropdown-kicker" role="presentation">{{ strtoupper(str_replace('_', ' ', $u->role ?? '')) }}</p>
                    <a href="{{ route('admin.targets.state') }}" class="admin-topbar__dropdown-item @if ($activeNav === 'state') is-active @endif" role="menuitem">State targets</a>
                    <a href="{{ route('admin.targets.district') }}" class="admin-topbar__dropdown-item @if ($activeNav === 'district') is-active @endif" role="menuitem">District targets</a>
                    <a href="{{ route('admin.staff.index') }}" class="admin-topbar__dropdown-item @if ($activeNav === 'staff') is-active @endif" role="menuitem">Staff</a>
                </div>
            </details>
            <a href="{{ route('admin.designations.index') }}" class="admin-topbar__link @if ($activeNav === 'designations') is-active @endif">Designations</a>
            <a href="{{ route('admin.hub-batch-compliance.index') }}" class="admin-topbar__link @if ($activeNav === 'hub-batch-compliance') is-active @endif">Batch CDO PDF</a>
        </nav>
        @endif

        @if ($showHubNav)
        <nav class="admin-topbar__nav" aria-label="Hub">
            <a href="{{ route('dashboard') }}" class="admin-topbar__link @if ($activeNav === 'dashboard') is-active @endif">Dashboard</a>
            <a href="{{ route('hub.batches.index') }}" class="admin-topbar__link @if ($activeNav === 'hub-batches') is-active @endif">Batches</a>
        </nav>
        @endif

        @if ($showStaffNav)
        <nav class="admin-topbar__nav" aria-label="Staff">
            <a href="{{ route('dashboard') }}" class="admin-topbar__link @if ($activeNav === 'dashboard') is-active @endif">Dashboard</a>
            <a href="{{ route('staff.monthly-targets') }}" class="admin-topbar__link @if ($activeNav === 'staff-targets') is-active @endif">Monthly targets</a>
            <a href="{{ route('staff.applications') }}" class="admin-topbar__link @if ($activeNav === 'staff-apps') is-active @endif">Applications</a>
            <a href="{{ route('staff.phase2-data') }}" class="admin-topbar__link @if ($activeNav === 'staff-phase2-data') is-active @endif">FY 2025-26 Data</a>
        </nav>
        @endif

        <div class="admin-topbar__right">
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
                    <a href="{{ route('account.settings.edit') }}" class="admin-topbar__dropdown-item @if (request()->routeIs('account.settings.*')) is-active @endif" role="menuitem">Settings</a>
                    @if ($showAdminNav)
                        <a href="{{ route('admin.audit.index') }}" class="admin-topbar__dropdown-item @if ($activeNav === 'audit') is-active @endif" role="menuitem">Audit log</a>
                    @endif
                    <form method="post" action="{{ route('logout') }}" class="admin-topbar__dropdown-logout">
                        @csrf
                        <button type="submit" class="admin-topbar__dropdown-item admin-topbar__dropdown-item--button" role="menuitem">Log out</button>
                    </form>
                </div>
            </details>
        </div>
    </div>
</header>
