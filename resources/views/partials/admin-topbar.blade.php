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
        default => '',
    };
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
        <nav class="admin-topbar__nav" aria-label="Main">
            <a href="{{ route('dashboard') }}" class="admin-topbar__link @if ($activeNav === 'dashboard') is-active @endif">Dashboard</a>
            <a href="{{ route('admin.cfa.index') }}" class="admin-topbar__link @if ($activeNav === 'cfa') is-active @endif">CFA applications</a>
            <a href="{{ route('admin.phase2-cfa.index') }}" class="admin-topbar__link @if ($activeNav === 'phase2-cfa') is-active @endif">CFA (FY 25-26)</a>
            <a href="{{ route('admin.targets.state') }}" class="admin-topbar__link @if ($activeNav === 'state') is-active @endif">State targets</a>
            <a href="{{ route('admin.targets.district') }}" class="admin-topbar__link @if ($activeNav === 'district') is-active @endif">District targets</a>
            <a href="{{ route('admin.staff.index') }}" class="admin-topbar__link @if ($activeNav === 'staff') is-active @endif">Staff</a>
            <a href="{{ route('admin.designations.index') }}" class="admin-topbar__link @if ($activeNav === 'designations') is-active @endif">Designations</a>
            <a href="{{ route('admin.audit.index') }}" class="admin-topbar__link @if ($activeNav === 'audit') is-active @endif">Audit log</a>
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
            <a href="{{ route('staff.phase2-data') }}" class="admin-topbar__link @if ($activeNav === 'staff-phase2-data') is-active @endif">FY 25-26</a>
        </nav>
        @endif

        <div class="admin-topbar__right">
            <span class="admin-topbar__user" title="{{ auth()->user()->email }}">{{ auth()->user()->name }}</span>
            <form method="post" action="{{ route('logout') }}" class="admin-topbar__logout">
                @csrf
                <button type="submit">Log out</button>
            </form>
        </div>
    </div>
</header>
