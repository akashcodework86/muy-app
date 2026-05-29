@if ($showStaffFieldWorkNav ?? false)
    @if ($staffFieldWorkNavEmbedded ?? false)
        <div class="admin-topbar__dropdown-subgroup @if ($staffFieldWorkActive ?? false) is-active @endif">
            <span class="admin-topbar__dropdown-subtrigger">
                {!! $i('calendar') !!}<span>Field work</span>
            </span>
            <div class="admin-topbar__dropdown-subpanel admin-topbar__dropdown-subpanel--wide" role="menu">
                @include('partials.staff-field-work-nav-items')
            </div>
        </div>
    @else
    <details class="admin-topbar__details admin-topbar__details--field-work">
        <summary class="admin-topbar__link admin-topbar__dropdown-trigger @if ($staffFieldWorkActive ?? false) is-active @endif">
            {!! $i('calendar') !!}<span class="admin-topbar__link-text">Field work</span>
        </summary>
        <div class="admin-topbar__dropdown-panel admin-topbar__dropdown-panel--wide" role="menu">
            <p class="admin-topbar__dropdown-kicker" role="presentation">Field work</p>
            @include('partials.staff-field-work-nav-items')
        </div>
    </details>
    @endif
@endif
