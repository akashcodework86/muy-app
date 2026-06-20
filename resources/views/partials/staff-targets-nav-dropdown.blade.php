@if ($showStaffNav ?? false)
<details class="admin-topbar__details admin-topbar__details--staff-targets">
    <summary class="admin-topbar__link admin-topbar__dropdown-trigger @if ($staffTargetsGroupActive ?? false) is-active @endif" title="Targets">
        {!! $i('targets') !!}<span class="admin-topbar__link-text">Targets</span>
    </summary>
    <div class="admin-topbar__dropdown-panel admin-topbar__dropdown-panel--staff-targets" role="menu">
        <p class="admin-topbar__dropdown-kicker" role="presentation">District planning</p>
        <a href="{{ route('staff.monthly-targets') }}" class="admin-topbar__dropdown-item @if (($activeNav ?? '') === 'staff-targets') is-active @endif" role="menuitem">
            {!! $i('calendar') !!}<span>Monthly targets</span>
        </a>
        <p class="admin-topbar__dropdown-kicker admin-topbar__dropdown-kicker--sub" role="presentation">Official monthly plan · Read-only</p>
        <a href="{{ route('staff.fy-targets.state') }}" class="admin-topbar__dropdown-item @if (($activeNav ?? '') === 'fy-targets-state') is-active @endif" role="menuitem">
            {!! $i('calendar') !!}<span>State target month wise</span>
        </a>
        <a href="{{ route('staff.fy-targets.district') }}" class="admin-topbar__dropdown-item @if (($activeNav ?? '') === 'fy-targets-district') is-active @endif" role="menuitem">
            {!! $i('calendar') !!}<span>District target month wise</span>
        </a>
        <a href="{{ route('staff.fy-targets.hub') }}" class="admin-topbar__dropdown-item @if (($activeNav ?? '') === 'fy-targets-hub') is-active @endif" role="menuitem">
            {!! $i('calendar') !!}<span>Hub target distribution</span>
        </a>
    </div>
</details>
@endif
