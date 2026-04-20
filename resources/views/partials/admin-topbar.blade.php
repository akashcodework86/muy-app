@php
    $logoUrl = asset('https://ukrbi.in/new/admin/muy.png');
    $u = auth()->user();
    $showAdminNav = $u && $u->role === 'state_admin';
    $showHubNav = $u && $u->role === 'hub_admin';
    $showStaffNav = $u && $u->role === 'district_staff';
    $showIncubateeNav = $u && $u->role === 'incubatee';
    $brandSub = match ($u->role ?? '') {
        'district_staff' => 'District staff',
        'hub_admin' => 'Hub admin',
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
        str_starts_with($r, 'admin.phase2-cfa') => 'phase2-cfa',
        str_starts_with($r, 'admin.targets.state') => 'state',
        str_starts_with($r, 'admin.targets.district') => 'district',
        str_starts_with($r, 'admin.staff') => 'staff',
        str_starts_with($r, 'admin.team-performance') => 'team-performance',
        str_starts_with($r, 'admin.designations') => 'designations',
        str_starts_with($r, 'admin.audit') => 'audit',
        str_starts_with($r, 'admin.hub-batch-compliance') => 'hub-batch-compliance',
        str_starts_with($r, 'admin.service-catalog') => 'service-catalog',
        str_starts_with($r, 'hub.batches') => 'hub-batches',
        $r === 'staff.monthly-targets' => 'staff-targets',
        $r === 'staff.applications' => 'staff-apps',
        str_starts_with($r, 'staff.phase2-data') => 'staff-phase2-data',
        str_starts_with($r, 'account.') => 'account',
        str_starts_with($r, 'incubatee.') => 'incubatee',
        str_starts_with($r, 'notifications.') => 'notifications',
        default => '',
    };
    $targetsStaffActive = in_array($activeNav, ['state', 'district', 'staff', 'team-performance'], true);
    $cfaGroupActive = in_array($activeNav, ['cfa', 'phase2-cfa'], true);
    $opsGroupActive = in_array($activeNav, ['service-catalog', 'designations', 'hub-batch-compliance'], true);
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

            <details class="admin-topbar__details">
                <summary class="admin-topbar__link admin-topbar__dropdown-trigger @if ($cfaGroupActive) is-active @endif">
                    CFA
                </summary>
                <div class="admin-topbar__dropdown-panel" role="menu">
                    <p class="admin-topbar__dropdown-kicker" role="presentation">Applications</p>
                    <a href="{{ route('admin.cfa.index') }}" class="admin-topbar__dropdown-item @if ($activeNav === 'cfa') is-active @endif" role="menuitem">CFA applications</a>
                    <a href="{{ route('admin.phase2-cfa.index') }}" class="admin-topbar__dropdown-item @if ($activeNav === 'phase2-cfa') is-active @endif" role="menuitem">CFA (FY 2025-26 Data)</a>
                </div>
            </details>

            <details class="admin-topbar__details">
                <summary class="admin-topbar__link admin-topbar__dropdown-trigger @if ($targetsStaffActive) is-active @endif">
                    Targets &amp; team
                </summary>
                <div class="admin-topbar__dropdown-panel" role="menu">
                    <p class="admin-topbar__dropdown-kicker" role="presentation">Planning &amp; performance</p>
                    <a href="{{ route('admin.targets.state') }}" class="admin-topbar__dropdown-item @if ($activeNav === 'state') is-active @endif" role="menuitem">State targets</a>
                    <a href="{{ route('admin.targets.district') }}" class="admin-topbar__dropdown-item @if ($activeNav === 'district') is-active @endif" role="menuitem">District targets</a>
                    <a href="{{ route('admin.staff.index') }}" class="admin-topbar__dropdown-item @if ($activeNav === 'staff') is-active @endif" role="menuitem">Staff</a>
                    <a href="{{ route('admin.team-performance.index') }}" class="admin-topbar__dropdown-item @if ($activeNav === 'team-performance') is-active @endif" role="menuitem">Team performance</a>
                </div>
            </details>

            <a href="{{ route('admin.service-catalog.index') }}" class="admin-topbar__link @if ($activeNav === 'service-catalog') is-active @endif">Service catalog</a>

            <details class="admin-topbar__details">
                <summary class="admin-topbar__link admin-topbar__dropdown-trigger @if ($opsGroupActive && $activeNav !== 'service-catalog') is-active @endif">
                    More
                </summary>
                <div class="admin-topbar__dropdown-panel" role="menu">
                    <p class="admin-topbar__dropdown-kicker" role="presentation">Operations</p>
                    <a href="{{ route('admin.designations.index') }}" class="admin-topbar__dropdown-item @if ($activeNav === 'designations') is-active @endif" role="menuitem">Designations</a>
                    <a href="{{ route('admin.hub-batch-compliance.index') }}" class="admin-topbar__dropdown-item @if ($activeNav === 'hub-batch-compliance') is-active @endif" role="menuitem">Batch CDO PDF</a>
                </div>
            </details>
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

        @if ($showIncubateeNav)
        <nav class="admin-topbar__nav" aria-label="Incubatee">
            <a href="{{ route('incubatee.dashboard') }}" class="admin-topbar__link @if ($activeNav === 'incubatee' && request()->routeIs('incubatee.dashboard')) is-active @endif">Dashboard</a>
            <a href="{{ route('incubatee.mentorship.index') }}" class="admin-topbar__link @if (request()->routeIs('incubatee.mentorship.*')) is-active @endif">Request mentorship</a>
            <a href="{{ route('incubatee.udmita-kosh') }}" class="admin-topbar__link @if (request()->routeIs('incubatee.udmita-kosh')) is-active @endif">Udmita Kosh</a>
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
