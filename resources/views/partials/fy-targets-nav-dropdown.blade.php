@if ($fyTargetsNavPrefix ?? null)
<details class="admin-topbar__details admin-topbar__details--fy-targets @if ($fyTargetsNavStaff ?? false) admin-topbar__details--fy-targets-staff @endif">
    <summary class="admin-topbar__link admin-topbar__dropdown-trigger @if ($fyTargetsGroupActive ?? false) is-active @endif" title="{{ $fyTargetsNavLabel ?? 'FY Targets' }}">
        {!! $i('targets') !!}<span class="admin-topbar__link-text">{{ $fyTargetsNavLabel ?? 'FY Targets' }}</span>
    </summary>
    <div class="admin-topbar__dropdown-panel admin-topbar__dropdown-panel--fy-targets" role="menu">
        <p class="admin-topbar__dropdown-kicker" role="presentation">Official monthly plan · Read-only</p>
        <a href="{{ route($fyTargetsNavPrefix.'.fy-targets.state') }}" class="admin-topbar__dropdown-item @if (($activeNav ?? '') === 'fy-targets-state') is-active @endif" role="menuitem">
            {!! $i('calendar') !!}<span>State target month wise</span>
        </a>
        <a href="{{ route($fyTargetsNavPrefix.'.fy-targets.district') }}" class="admin-topbar__dropdown-item @if (($activeNav ?? '') === 'fy-targets-district') is-active @endif" role="menuitem">
            {!! $i('calendar') !!}<span>District target month wise</span>
        </a>
        <a href="{{ route($fyTargetsNavPrefix.'.fy-targets.hub') }}" class="admin-topbar__dropdown-item @if (($activeNav ?? '') === 'fy-targets-hub') is-active @endif" role="menuitem">
            {!! $i('calendar') !!}<span>Hub target distribution</span>
        </a>
    </div>
</details>
@endif
