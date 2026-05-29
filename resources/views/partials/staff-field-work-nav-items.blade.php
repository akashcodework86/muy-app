@php
    $fieldWorkReportGroupLabel = ($isFieldCoordinator ?? false) ? 'Field report' : 'Block level workshop';
    $fieldWorkReportSubmitLabel = ($isFieldCoordinator ?? false) ? 'Submit field report' : 'Submit block level workshop';
    $fieldWorkReportViewLabel = ($isFieldCoordinator ?? false) ? 'View field reports' : 'View block level workshops';
@endphp
<div class="admin-topbar__dropdown-subgroup @if (in_array($activeNav, ['staff-attendance', 'staff-attendance-view'], true)) is-active @endif">
    <span class="admin-topbar__dropdown-subtrigger">
        {!! $i('doc') !!}<span>{{ $fieldWorkReportGroupLabel }}</span>
    </span>
    <div class="admin-topbar__dropdown-subpanel" role="menu">
        @if ($isFieldCoordinator ?? false)
        <a href="{{ route('staff.attendance.index') }}" class="admin-topbar__dropdown-item @if ($activeNav === 'staff-attendance') is-active @endif" role="menuitem">
            {!! $i('doc') !!}<span>{{ $fieldWorkReportSubmitLabel }}</span>
        </a>
        <a href="{{ route('staff.attendance.view') }}" class="admin-topbar__dropdown-item @if ($activeNav === 'staff-attendance-view') is-active @endif" role="menuitem">
            {!! $i('bars') !!}<span>{{ $fieldWorkReportViewLabel }}</span>
        </a>
        @else
        <a href="{{ route('staff.workshops.index') }}" class="admin-topbar__dropdown-item @if ($activeNav === 'staff-attendance') is-active @endif" role="menuitem">
            {!! $i('doc') !!}<span>{{ $fieldWorkReportSubmitLabel }}</span>
        </a>
        <a href="{{ route('staff.workshops.view') }}" class="admin-topbar__dropdown-item @if ($activeNav === 'staff-attendance-view') is-active @endif" role="menuitem">
            {!! $i('bars') !!}<span>{{ $fieldWorkReportViewLabel }}</span>
        </a>
        @endif
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
