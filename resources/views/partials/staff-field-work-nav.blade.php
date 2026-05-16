@if ($showStaffFieldWorkNav ?? false)
<details class="admin-topbar__details admin-topbar__details--field-work">
    <summary class="admin-topbar__link admin-topbar__dropdown-trigger @if ($staffFieldWorkActive ?? false) is-active @endif">
        {!! $i('calendar') !!}<span class="admin-topbar__link-text">Field work</span>
    </summary>
    <div class="admin-topbar__dropdown-panel admin-topbar__dropdown-panel--wide" role="menu">
        <p class="admin-topbar__dropdown-kicker" role="presentation">Field work</p>

        <div class="admin-topbar__dropdown-subgroup @if (in_array($activeNav, ['staff-attendance', 'staff-attendance-view'], true)) is-active @endif">
            <span class="admin-topbar__dropdown-subtrigger">
                {!! $i('doc') !!}<span>Field report</span>
            </span>
            <div class="admin-topbar__dropdown-subpanel" role="menu">
                @if ($isFieldCoordinator)
                    <a href="{{ route('staff.attendance.index') }}" class="admin-topbar__dropdown-item @if ($activeNav === 'staff-attendance') is-active @endif" role="menuitem">
                        {!! $i('doc') !!}<span>Submit field report</span>
                    </a>
                @endif
                <a href="{{ route('staff.attendance.view') }}" class="admin-topbar__dropdown-item @if ($activeNav === 'staff-attendance-view') is-active @endif" role="menuitem">
                    {!! $i('bars') !!}<span>View field reports</span>
                </a>
            </div>
        </div>

        @if ($staffNavTrainingPackage)
        <div class="admin-topbar__dropdown-subgroup @if (in_array($activeNav, ['staff-training-packages-submit', 'staff-training-packages-dashboard'], true)) is-active @endif">
            <span class="admin-topbar__dropdown-subtrigger">
                {!! $i('calendar') !!}<span>Training package</span>
            </span>
            <div class="admin-topbar__dropdown-subpanel" role="menu">
                <a href="{{ route('staff.training-packages.create') }}" class="admin-topbar__dropdown-item @if ($activeNav === 'staff-training-packages-submit') is-active @endif" role="menuitem">
                    {!! $i('doc') !!}<span>Submit report</span>
                </a>
                <a href="{{ route('staff.training-packages.dashboard') }}" class="admin-topbar__dropdown-item @if ($activeNav === 'staff-training-packages-dashboard') is-active @endif" role="menuitem">
                    {!! $i('bars') !!}<span>View dashboard</span>
                </a>
            </div>
        </div>
        @endif

        @if ($staffNavTechnicalTraining)
        <div class="admin-topbar__dropdown-subgroup @if (in_array($activeNav, ['staff-technical-trainings-submit', 'staff-technical-trainings-dashboard'], true)) is-active @endif">
            <span class="admin-topbar__dropdown-subtrigger">
                {!! $i('calendar') !!}<span>Technical training</span>
            </span>
            <div class="admin-topbar__dropdown-subpanel" role="menu">
                <a href="{{ route('staff.technical-trainings.create') }}" class="admin-topbar__dropdown-item @if ($activeNav === 'staff-technical-trainings-submit') is-active @endif" role="menuitem">
                    {!! $i('doc') !!}<span>Submit report</span>
                </a>
                <a href="{{ route('staff.technical-trainings.dashboard') }}" class="admin-topbar__dropdown-item @if ($activeNav === 'staff-technical-trainings-dashboard') is-active @endif" role="menuitem">
                    {!! $i('bars') !!}<span>View dashboard</span>
                </a>
            </div>
        </div>
        @endif

        @if ($staffNavEapEdp)
        <div class="admin-topbar__dropdown-subgroup @if (in_array($activeNav, ['staff-eap-edp-sessions-submit', 'staff-eap-edp-sessions-dashboard'], true)) is-active @endif">
            <span class="admin-topbar__dropdown-subtrigger">
                {!! $i('calendar') !!}<span>EAP / EDP</span>
            </span>
            <div class="admin-topbar__dropdown-subpanel" role="menu">
                <a href="{{ route('staff.eap-edp-sessions.create') }}" class="admin-topbar__dropdown-item @if ($activeNav === 'staff-eap-edp-sessions-submit') is-active @endif" role="menuitem">
                    {!! $i('doc') !!}<span>Submit report</span>
                </a>
                <a href="{{ route('staff.eap-edp-sessions.dashboard') }}" class="admin-topbar__dropdown-item @if ($activeNav === 'staff-eap-edp-sessions-dashboard') is-active @endif" role="menuitem">
                    {!! $i('bars') !!}<span>View dashboard</span>
                </a>
            </div>
        </div>
        @endif

        @if ($staffNavDistrictWorkshop)
        <div class="admin-topbar__dropdown-subgroup @if (in_array($activeNav, ['staff-district-workshop-sessions-submit', 'staff-district-workshop-sessions-dashboard'], true)) is-active @endif">
            <span class="admin-topbar__dropdown-subtrigger">
                {!! $i('calendar') !!}<span>District workshop</span>
            </span>
            <div class="admin-topbar__dropdown-subpanel" role="menu">
                <a href="{{ route('staff.district-workshop-sessions.create') }}" class="admin-topbar__dropdown-item @if ($activeNav === 'staff-district-workshop-sessions-submit') is-active @endif" role="menuitem">
                    {!! $i('doc') !!}<span>Submit report</span>
                </a>
                <a href="{{ route('staff.district-workshop-sessions.dashboard') }}" class="admin-topbar__dropdown-item @if ($activeNav === 'staff-district-workshop-sessions-dashboard') is-active @endif" role="menuitem">
                    {!! $i('bars') !!}<span>View dashboard</span>
                </a>
            </div>
        </div>
        @endif
    </div>
</details>
@endif
