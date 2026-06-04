@php
    $logoUrl = asset('https://ukrbi.in/new/admin/muy.png');
    $u = auth()->user();
    $showAdminNav = $u && $u->role === 'state_admin';
    $showStateStaffNav = $u && $u->role === 'state_staff';
    $showHubNav = $u && $u->role === 'hub_admin';
    $showStaffNav = $u && $u->role === 'district_staff';
    $showStaffDailyCheckInNav = $u && \App\Support\StaffDailyCheckInAccess::isRequired($u);
    $isFieldCoordinator = $showStaffNav && (
        str_contains(strtolower((string) ($u->designationRecord?->name ?? '')), 'field coordinator')
        || str_contains(strtolower((string) ($u->designationRecord?->name ?? '')), 'field co-ordinator')
    );
    $appSettings = app(\App\Services\AppSettingsService::class);
    $staffServiceModuleOn = $showStaffNav && $appSettings->isEnabled('service_module.enabled');
    $staffNavTrainingPackage = $appSettings->isEnabled('staff_nav.training_package.visible');
    $staffNavTechnicalTraining = $appSettings->isEnabled('staff_nav.technical_training.visible');
    $staffNavEapEdp = $appSettings->isEnabled('staff_nav.eap_edp_session.visible');
    $staffNavDistrictWorkshop = $appSettings->isEnabled('staff_nav.district_workshop.visible');
    $canSubmitSocialMediaPost = $u && \App\Support\SocialMediaPostAccess::canSubmit($u);
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
    $hasAdminCfaIndex = \Illuminate\Support\Facades\Route::has('admin.cfa.index');
    $r = request()->route()?->getName() ?? '';
    $activeNav = match (true) {
        $r === 'dashboard' => 'dashboard',
        str_starts_with($r, 'admin.data-centre') => 'data-centre',
        str_starts_with($r, 'admin.cfa') => 'cfa',
        str_starts_with($r, 'admin.phase1-cfa') => 'phase1-cfa',
        str_starts_with($r, 'admin.phase2-cfa') => 'phase2-cfa',
        str_starts_with($r, 'admin.onboarded') => 'onboarded',
        str_starts_with($r, 'hub.onboarded') => 'onboarded',
        str_starts_with($r, 'staff.onboarded') => 'onboarded',
        str_starts_with($r, 'spoc.state-tasks') => 'state-tasks-spoc',
        str_starts_with($r, 'spoc.onboarded') => 'onboarded',
        str_starts_with($r, 'admin.phase3-services') => 'phase3-services',
        str_starts_with($r, 'admin.deliverables') => 'deliverables',
        str_starts_with($r, 'hub.deliverables') => 'deliverables',
        str_starts_with($r, 'staff.deliverables') => 'deliverables',
        str_starts_with($r, 'spoc.deliverables') => 'deliverables',
        str_starts_with($r, 'admin.targets.state') => 'state',
        str_starts_with($r, 'admin.targets.district') => 'district',
        str_starts_with($r, 'admin.targets.allocate-by-service') => 'targets-allocate',
        str_starts_with($r, 'admin.training-package-month-plans') => 'training-package-month-plans',
        str_starts_with($r, 'admin.staff') => 'staff',
        str_starts_with($r, 'admin.state-staff') => 'state-staff',
        str_starts_with($r, 'admin.service-spocs') => 'service-spocs',
        str_starts_with($r, 'admin.pending-actions') => 'pending-actions',
        str_starts_with($r, 'admin.state-tasks') => 'state-tasks',
        str_starts_with($r, 'spoc.') => 'spoc-queue',
        str_starts_with($r, 'admin.team-performance') => 'team-performance',
        str_starts_with($r, 'team.') => 'team-directory',
        str_starts_with($r, 'admin.attendance') => 'attendance',
        str_starts_with($r, 'admin.staff-check-ins') => 'staff-daily-check-ins',
        str_starts_with($r, 'staff-daily-check-in') => 'staff-daily-check-in',
        str_starts_with($r, 'admin.field-coordinator-reports') => 'field-coordinator-report',
        str_starts_with($r, 'hub.field-coordinator-reports') => 'field-coordinator-report',
        str_starts_with($r, 'staff.field-coordinator-reports') => 'field-coordinator-report',
        str_starts_with($r, 'spoc.field-coordinator-reports') => 'field-coordinator-report',
        str_starts_with($r, 'admin.designations') => 'designations',
        str_starts_with($r, 'admin.audit') => 'audit',
        str_starts_with($r, 'admin.ops.cache-clear') => 'ops-cache-clear',
        str_starts_with($r, 'admin.hub-batch-compliance') => 'hub-batch-compliance',
        str_starts_with($r, 'admin.documents') => 'admin-documents',
        str_starts_with($r, 'library.documents') => 'documents',
        str_starts_with($r, 'admin.service-catalog') => 'service-catalog',
        str_starts_with($r, 'admin.service-module-settings') => 'service-module-settings',
        str_starts_with($r, 'admin.staff-phase3-attendance-nav') => 'staff-phase3-attendance-nav',
        str_starts_with($r, 'admin.batches') => 'admin-batches',
        str_starts_with($r, 'hub.batches') => 'hub-batches',
        str_starts_with($r, 'hub.applications') => 'hub-applications',
        str_starts_with($r, 'hub.staff-performance') => 'hub-staff-performance',
        str_starts_with($r, 'hub.pending-actions') => 'hub-pending-actions',
        str_starts_with($r, 'hub.onboarding-insight') => 'hub-onboarding-insight',
        $r === 'staff.monthly-targets' => 'staff-targets',
        str_starts_with($r, 'staff.services') => 'staff-services',
        str_starts_with($r, 'staff.applications') => 'staff-apps',
        str_starts_with($r, 'staff.phase1-data') => 'staff-phase1-data',
        str_starts_with($r, 'staff.phase2-data') => 'staff-phase2-data',
        str_starts_with($r, 'staff.batches') => 'staff-batches',
        $r === 'staff.attendance.index' => 'staff-attendance',
        $r === 'staff.attendance.view'  => 'staff-attendance-view',
        str_starts_with($r, 'staff.training-packages.create') => 'staff-training-packages-submit',
        str_starts_with($r, 'staff.training-packages.edit') => 'staff-training-packages-submit',
        str_starts_with($r, 'staff.training-packages.dashboard') => 'staff-training-packages-dashboard',
        str_starts_with($r, 'staff.training-packages.show') => 'staff-training-packages-dashboard',
        str_starts_with($r, 'spoc.training-packages.dashboard') => 'staff-training-packages-dashboard',
        str_starts_with($r, 'spoc.training-packages.show') => 'staff-training-packages-dashboard',
        str_starts_with($r, 'admin.training-packages.dashboard') => 'staff-training-packages-dashboard',
        str_starts_with($r, 'admin.training-packages.show') => 'staff-training-packages-dashboard',
        str_starts_with($r, 'staff.technical-trainings.create') => 'staff-technical-trainings-submit',
        str_starts_with($r, 'staff.technical-trainings.edit') => 'staff-technical-trainings-submit',
        str_starts_with($r, 'staff.technical-trainings.dashboard') => 'staff-technical-trainings-dashboard',
        str_starts_with($r, 'staff.technical-trainings.show') => 'staff-technical-trainings-dashboard',
        str_starts_with($r, 'spoc.technical-trainings.dashboard') => 'staff-technical-trainings-dashboard',
        str_starts_with($r, 'spoc.technical-trainings.show') => 'staff-technical-trainings-dashboard',
        str_starts_with($r, 'admin.technical-trainings.dashboard') => 'staff-technical-trainings-dashboard',
        str_starts_with($r, 'admin.technical-trainings.show') => 'staff-technical-trainings-dashboard',
        str_starts_with($r, 'staff.eap-edp-sessions.create') => 'staff-eap-edp-sessions-submit',
        str_starts_with($r, 'staff.eap-edp-sessions.edit') => 'staff-eap-edp-sessions-submit',
        str_starts_with($r, 'staff.eap-edp-sessions.dashboard') => 'staff-eap-edp-sessions-dashboard',
        str_starts_with($r, 'staff.eap-edp-sessions.show') => 'staff-eap-edp-sessions-dashboard',
        str_starts_with($r, 'spoc.eap-edp-sessions.dashboard') => 'staff-eap-edp-sessions-dashboard',
        str_starts_with($r, 'spoc.eap-edp-sessions.show') => 'staff-eap-edp-sessions-dashboard',
        str_starts_with($r, 'admin.eap-edp-sessions.dashboard') => 'staff-eap-edp-sessions-dashboard',
        str_starts_with($r, 'admin.eap-edp-sessions.show') => 'staff-eap-edp-sessions-dashboard',
        str_starts_with($r, 'staff.district-workshop-sessions.create') => 'staff-district-workshop-sessions-submit',
        str_starts_with($r, 'staff.district-workshop-sessions.edit') => 'staff-district-workshop-sessions-submit',
        str_starts_with($r, 'staff.district-workshop-sessions.dashboard') => 'staff-district-workshop-sessions-dashboard',
        str_starts_with($r, 'staff.district-workshop-sessions.show') => 'staff-district-workshop-sessions-dashboard',
        str_starts_with($r, 'spoc.district-workshop-sessions.dashboard') => 'staff-district-workshop-sessions-dashboard',
        str_starts_with($r, 'spoc.district-workshop-sessions.show') => 'staff-district-workshop-sessions-dashboard',
        str_starts_with($r, 'admin.district-workshop-sessions.dashboard') => 'staff-district-workshop-sessions-dashboard',
        str_starts_with($r, 'admin.district-workshop-sessions.show') => 'staff-district-workshop-sessions-dashboard',
        str_starts_with($r, 'admin.block-workshops.') => 'block-workshops-dashboard',
        str_starts_with($r, 'spoc.social-media-posts.create') => 'social-media-posts-submit',
        str_starts_with($r, 'spoc.social-media-posts.index') => 'social-media-posts-submit',
        str_starts_with($r, 'spoc.social-media-posts.store') => 'social-media-posts-submit',
        str_starts_with($r, 'spoc.social-media-posts.dashboard') => 'social-media-posts-dashboard',
        str_starts_with($r, 'spoc.social-media-posts.show') => 'social-media-posts-dashboard',
        str_starts_with($r, 'admin.social-media-posts.dashboard') => 'social-media-posts-dashboard',
        str_starts_with($r, 'admin.social-media-posts.show') => 'social-media-posts-dashboard',
        str_starts_with($r, 'staff.market-linkages.create') => 'market-linkages-submit',
        str_starts_with($r, 'staff.market-linkages.store') => 'market-linkages-submit',
        str_starts_with($r, 'staff.market-linkages.dashboard') => 'market-linkages-dashboard',
        str_starts_with($r, 'staff.market-linkages.show') => 'market-linkages-dashboard',
        str_starts_with($r, 'admin.market-linkages.dashboard') => 'market-linkage-dashboard',
        str_starts_with($r, 'admin.market-linkages.show') => 'market-linkage-dashboard',
        str_starts_with($r, 'account.') => 'account',
        str_starts_with($r, 'incubatee.documents') => 'documents',
        str_starts_with($r, 'incubatee.') => 'incubatee',
        str_starts_with($r, 'notifications.') => 'notifications',
        default => '',
    };
    $targetsStaffActive = in_array($activeNav, ['deliverables', 'state', 'district', 'targets-allocate', 'training-package-month-plans', 'staff', 'state-staff', 'service-spocs', 'pending-actions', 'state-tasks', 'team-performance', 'team-directory', 'attendance', 'staff-daily-check-ins', 'field-coordinator-report'], true);
    $cfaGroupActive = in_array($activeNav, ['cfa', 'phase1-cfa', 'phase2-cfa', 'phase3-services'], true);
    $serviceGroupActive = in_array($activeNav, ['service-catalog', 'phase3-services', 'staff-training-packages-dashboard', 'staff-technical-trainings-dashboard', 'staff-eap-edp-sessions-dashboard', 'staff-district-workshop-sessions-dashboard', 'block-workshops-dashboard', 'social-media-posts-dashboard', 'market-linkage-dashboard'], true);
    $opsGroupActive = in_array($activeNav, ['designations', 'hub-batch-compliance', 'admin-batches', 'service-module-settings', 'staff-phase3-attendance-nav', 'admin-documents', 'data-centre'], true);
    $staffFieldWorkNavKeys = [
        'staff-attendance', 'staff-attendance-view',
        'staff-training-packages-submit', 'staff-training-packages-dashboard',
        'staff-technical-trainings-submit', 'staff-technical-trainings-dashboard',
        'staff-eap-edp-sessions-submit', 'staff-eap-edp-sessions-dashboard',
        'staff-district-workshop-sessions-submit', 'staff-district-workshop-sessions-dashboard',
    ];
    $staffFieldWorkActive = in_array($activeNav, $staffFieldWorkNavKeys, true);
    $showStaffFieldWorkNav = $showStaffNav
        || $staffNavTrainingPackage
        || $staffNavTechnicalTraining
        || $staffNavEapEdp
        || $staffNavDistrictWorkshop;
    $staffCfaGroupActive = in_array($activeNav, ['staff-apps', 'staff-phase1-data', 'staff-phase2-data', 'onboarded', 'staff-batches'], true);
    $staffTargetsGroupActive = in_array($activeNav, ['staff-targets', 'deliverables', 'field-coordinator-report'], true);
    $staffServiceGroupActive = in_array($activeNav, ['staff-services', 'market-linkages-submit', 'market-linkages-dashboard'], true)
        || $staffFieldWorkActive;

    $hubCfaGroupActive = in_array($activeNav, ['hub-applications', 'hub-batches', 'onboarded', 'hub-onboarding-insight'], true);
    $hubPerformanceGroupActive = in_array($activeNav, ['deliverables', 'hub-staff-performance', 'field-coordinator-report', 'hub-pending-actions'], true);
    $hubMoreGroupActive = in_array($activeNav, ['staff-daily-check-in', 'documents'], true);
    $hubDisplayName = $showHubNav ? trim((string) ($u->hub?->name ?? 'Hub')) : '';

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
<header class="admin-topbar @if ($showHubNav) admin-topbar--hub @endif">
    <div class="admin-topbar__inner @if ($showHubNav) admin-topbar__inner--hub @endif">
        <a href="{{ route('dashboard') }}" class="admin-brand @if ($showHubNav) admin-brand--hub @endif" title="Mukhyamantri Udyamshala Yojana">
            <span class="admin-brand__logo-wrap">
                <img src="{{ $logoUrl }}" alt="MUY Logo" class="admin-brand__img">
            </span>
            <span class="admin-brand__text">
                @if ($showHubNav)
                    <span class="admin-brand__eyebrow">Hub command centre</span>
                    <span class="admin-brand__name">MUY</span>
                    <span class="admin-brand__hub" title="{{ $hubDisplayName }}">{{ $hubDisplayName }}</span>
                @else
                    <span class="admin-brand__name">Mukhyamantri Udyamshala Yojana</span>
                    <span class="admin-brand__sub">{{ $brandSub }}</span>
                @endif
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
                    @if ($hasAdminCfaIndex)
                        <a href="{{ route('admin.cfa.index') }}" class="admin-topbar__dropdown-item @if ($activeNav === 'cfa') is-active @endif" role="menuitem">
                            {!! $i('doc') !!}<span>CFA applications</span>
                        </a>
                    @endif
                    <a href="{{ route('admin.phase1-cfa.index') }}" class="admin-topbar__dropdown-item @if ($activeNav === 'phase1-cfa') is-active @endif" role="menuitem">
                        {!! $i('database') !!}<span>CFA (FY 2024-25 Data)</span>
                    </a>
                    <a href="{{ route('admin.phase2-cfa.index') }}" class="admin-topbar__dropdown-item @if ($activeNav === 'phase2-cfa') is-active @endif" role="menuitem">
                        {!! $i('database') !!}<span>CFA (FY 2025-26 Data)</span>
                    </a>
                    <a href="{{ route('admin.onboarded.index') }}" class="admin-topbar__dropdown-item @if ($activeNav === 'onboarded') is-active @endif" role="menuitem">
                        {!! $i('batches') !!}<span>Onboarded</span>
                    </a>
                    <a href="{{ route('admin.phase3-services.index') }}" class="admin-topbar__dropdown-item @if ($activeNav === 'phase3-services') is-active @endif" role="menuitem">
                        {!! $i('bars') !!}<span>Phase 3 Service Cases</span>
                    </a>
                </div>
            </details>

            <details class="admin-topbar__details">
                <summary class="admin-topbar__link admin-topbar__dropdown-trigger @if ($targetsStaffActive) is-active @endif">
                    {!! $i('targets') !!}<span class="admin-topbar__link-text">Targets &amp; team</span>
                </summary>
                <div class="admin-topbar__dropdown-panel" role="menu">
                    <p class="admin-topbar__dropdown-kicker" role="presentation">Planning &amp; performance</p>
                    <a href="{{ route('admin.deliverables.index') }}" class="admin-topbar__dropdown-item @if ($activeNav === 'deliverables') is-active @endif" role="menuitem">
                        {!! $i('catalog') !!}<span>Deliverables</span>
                    </a>
                    <a href="{{ route('admin.targets.state') }}" class="admin-topbar__dropdown-item @if ($activeNav === 'state') is-active @endif" role="menuitem">
                        {!! $i('flag') !!}<span>State targets</span>
                    </a>
                    <a href="{{ route('admin.targets.district') }}" class="admin-topbar__dropdown-item @if ($activeNav === 'district') is-active @endif" role="menuitem">
                        {!! $i('pin') !!}<span>District targets</span>
                    </a>
                    <a href="{{ route('admin.targets.allocate-by-service') }}" class="admin-topbar__dropdown-item @if ($activeNav === 'targets-allocate') is-active @endif" role="menuitem">
                        {!! $i('users') !!}<span>Allocate by service</span>
                    </a>
                    <a href="{{ route('admin.training-package-month-plans.index') }}" class="admin-topbar__dropdown-item @if ($activeNav === 'training-package-month-plans') is-active @endif" role="menuitem">
                        {!! $i('calendar') !!}<span>Training package session targets</span>
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
                    <a href="{{ route('admin.pending-actions.index') }}" class="admin-topbar__dropdown-item @if ($activeNav === 'pending-actions') is-active @endif" role="menuitem">
                        {!! $i('inbox') !!}<span>Pending actions</span>
                    </a>
                    <a href="{{ route('admin.state-tasks.index') }}" class="admin-topbar__dropdown-item @if ($activeNav === 'state-tasks') is-active @endif" role="menuitem">
                        {!! $i('doc') !!}<span>State tasks</span>
                    </a>
                    <a href="{{ route('admin.team-performance.index') }}" class="admin-topbar__dropdown-item @if ($activeNav === 'team-performance') is-active @endif" role="menuitem">
                        {!! $i('bars') !!}<span>Team performance</span>
                    </a>
                    <a href="{{ route('team.index') }}" class="admin-topbar__dropdown-item @if ($activeNav === 'team-directory') is-active @endif" role="menuitem">
                        {!! $i('users') !!}<span>Team directory</span>
                    </a>
                    <a href="{{ route('admin.attendance.index') }}" class="admin-topbar__dropdown-item @if ($activeNav === 'attendance') is-active @endif" role="menuitem">
                        {!! $i('calendar') !!}<span>Field reports</span>
                    </a>
                    <a href="{{ route('admin.staff-check-ins.index') }}" class="admin-topbar__dropdown-item @if ($activeNav === 'staff-daily-check-ins') is-active @endif" role="menuitem">
                        {!! $i('pin') !!}<span>Staff daily attendance</span>
                    </a>
                    <a href="{{ route('admin.field-coordinator-reports.index') }}" class="admin-topbar__dropdown-item @if ($activeNav === 'field-coordinator-report') is-active @endif" role="menuitem">
                        {!! $i('bars') !!}<span>Field coordinator report</span>
                    </a>
                </div>
            </details>

            <details class="admin-topbar__details">
                <summary class="admin-topbar__link admin-topbar__dropdown-trigger @if ($serviceGroupActive) is-active @endif">
                    {!! $i('catalog') !!}<span class="admin-topbar__link-text">Service</span>
                </summary>
                <div class="admin-topbar__dropdown-panel" role="menu">
                    <p class="admin-topbar__dropdown-kicker" role="presentation">Service module</p>
                    <a href="{{ route('admin.service-catalog.index') }}" class="admin-topbar__dropdown-item @if ($activeNav === 'service-catalog') is-active @endif" role="menuitem">
                        {!! $i('catalog') !!}<span>Service catalog</span>
                    </a>
                    <a href="{{ route('admin.phase3-services.index') }}" class="admin-topbar__dropdown-item @if ($activeNav === 'phase3-services') is-active @endif" role="menuitem">
                        {!! $i('bars') !!}<span>All services</span>
                    </a>
                        <div class="admin-topbar__dropdown-subgroup @if (in_array($activeNav, ['staff-training-packages-dashboard', 'staff-technical-trainings-dashboard', 'staff-eap-edp-sessions-dashboard', 'staff-district-workshop-sessions-dashboard', 'block-workshops-dashboard'], true)) is-active @endif">
                        <span class="admin-topbar__dropdown-subtrigger">
                            {!! $i('calendar') !!}<span>Training and Capacity Building</span>
                        </span>
                        <div class="admin-topbar__dropdown-subpanel" role="menu">
                            <a href="{{ route('admin.training-packages.dashboard') }}" class="admin-topbar__dropdown-item @if ($activeNav === 'staff-training-packages-dashboard') is-active @endif" role="menuitem">
                                {!! $i('bars') !!}<span>Training Package Attendance</span>
                            </a>
                            <a href="{{ route('admin.technical-trainings.dashboard') }}" class="admin-topbar__dropdown-item @if ($activeNav === 'staff-technical-trainings-dashboard') is-active @endif" role="menuitem">
                                {!! $i('bars') !!}<span>Technical training to incubatees</span>
                            </a>
                            <a href="{{ route('admin.eap-edp-sessions.dashboard') }}" class="admin-topbar__dropdown-item @if ($activeNav === 'staff-eap-edp-sessions-dashboard') is-active @endif" role="menuitem">
                                {!! $i('bars') !!}<span>EAP / EDP sessions</span>
                            </a>
                            <a href="{{ route('admin.district-workshop-sessions.dashboard') }}" class="admin-topbar__dropdown-item @if ($activeNav === 'staff-district-workshop-sessions-dashboard') is-active @endif" role="menuitem">
                                {!! $i('bars') !!}<span>District level workshop</span>
                            </a>
                            <a href="{{ route('admin.block-workshops.index') }}" class="admin-topbar__dropdown-item @if ($activeNav === 'block-workshops-dashboard') is-active @endif" role="menuitem">
                                {!! $i('users') !!}<span>Block level workshop</span>
                            </a>
                        </div>
                    </div>
                    <div class="admin-topbar__dropdown-subgroup @if ($activeNav === 'social-media-posts-dashboard') is-active @endif">
                        <span class="admin-topbar__dropdown-subtrigger">
                            {!! $i('book') !!}<span>Branding, Communication &amp; Knowledge Management</span>
                        </span>
                        <div class="admin-topbar__dropdown-subpanel" role="menu">
                            <a href="{{ route('admin.social-media-posts.dashboard') }}" class="admin-topbar__dropdown-item @if ($activeNav === 'social-media-posts-dashboard') is-active @endif" role="menuitem">
                                {!! $i('bars') !!}<span>Social Media Post</span>
                            </a>
                        </div>
                    </div>
                    <div class="admin-topbar__dropdown-subgroup @if ($activeNav === 'market-linkage-dashboard') is-active @endif">
                        <span class="admin-topbar__dropdown-subtrigger">
                            {!! $i('pin') !!}<span>Forward Linkages</span>
                        </span>
                        <div class="admin-topbar__dropdown-subpanel" role="menu">
                            <a href="{{ route('admin.market-linkages.dashboard') }}" class="admin-topbar__dropdown-item @if ($activeNav === 'market-linkage-dashboard') is-active @endif" role="menuitem">
                                {!! $i('bars') !!}<span>Market Linkage</span>
                            </a>
                        </div>
                    </div>
                </div>
            </details>
            <a href="{{ route('library.documents.index') }}" class="admin-topbar__link @if (in_array($activeNav, ['documents', 'admin-documents'], true)) is-active @endif">
                {!! $i('book') !!}<span class="admin-topbar__link-text">Documents</span>
            </a>

            <details class="admin-topbar__details">
                <summary class="admin-topbar__link admin-topbar__dropdown-trigger @if ($opsGroupActive && $activeNav !== 'service-catalog') is-active @endif">
                    {!! $i('more') !!}<span class="admin-topbar__link-text">More</span>
                </summary>
                <div class="admin-topbar__dropdown-panel" role="menu">
                    <p class="admin-topbar__dropdown-kicker" role="presentation">Analytics &amp; Reports</p>
                    <a href="{{ route('admin.data-centre.index') }}" class="admin-topbar__dropdown-item @if ($activeNav === 'data-centre') is-active @endif" role="menuitem">
                        {!! $i('bars') !!}<span>Program Data Centre</span>
                    </a>
                    <p class="admin-topbar__dropdown-kicker" role="presentation">Operations</p>
                    <a href="{{ route('admin.batches.index') }}" class="admin-topbar__dropdown-item @if ($activeNav === 'admin-batches') is-active @endif" role="menuitem">
                        {!! $i('batches') !!}<span>Batches</span>
                    </a>
                    <a href="{{ route('admin.designations.index') }}" class="admin-topbar__dropdown-item @if ($activeNav === 'designations') is-active @endif" role="menuitem">
                        {!! $i('badge') !!}<span>Designations</span>
                    </a>
                    <a href="{{ route('admin.hub-batch-compliance.index') }}" class="admin-topbar__dropdown-item @if ($activeNav === 'hub-batch-compliance') is-active @endif" role="menuitem">
                        {!! $i('download') !!}<span>Batch Onboarding Letter</span>
                    </a>
                    <a href="{{ route('admin.hub-batch-compliance.requests') }}" class="admin-topbar__dropdown-item @if ($activeNav === 'hub-batch-compliance') is-active @endif" role="menuitem">
                        {!! $i('inbox') !!}<span>Unlock requests</span>
                    </a>
                    <a href="{{ route('admin.documents.index') }}" class="admin-topbar__dropdown-item @if ($activeNav === 'admin-documents') is-active @endif" role="menuitem">
                        {!! $i('book') !!}<span>Document repository</span>
                    </a>
                    <a href="{{ route('admin.service-module-settings.edit') }}" class="admin-topbar__dropdown-item @if ($activeNav === 'service-module-settings') is-active @endif" role="menuitem">
                        {!! $i('cog') !!}<span>Service module settings</span>
                    </a>
                    <a href="{{ route('admin.staff-phase3-attendance-nav.edit') }}" class="admin-topbar__dropdown-item @if ($activeNav === 'staff-phase3-attendance-nav') is-active @endif" role="menuitem">
                        {!! $i('bars') !!}<span>Staff training menus</span>
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
            @if ($showStaffDailyCheckInNav)
            <a href="{{ route('staff-daily-check-in.index') }}" class="admin-topbar__link @if ($activeNav === 'staff-daily-check-in') is-active @endif">
                {!! $i('pin') !!}<span class="admin-topbar__link-text">Daily attendance</span>
            </a>
            @endif
            <a href="{{ route('spoc.onboarded.index') }}" class="admin-topbar__link @if ($activeNav === 'onboarded') is-active @endif">
                {!! $i('batches') !!}<span class="admin-topbar__link-text">Onboarded</span>
            </a>
            <a href="{{ route('spoc.service-cases.index') }}" class="admin-topbar__link @if ($activeNav === 'spoc-queue') is-active @endif">
                {!! $i('inbox') !!}<span class="admin-topbar__link-text">Approval queue</span>
            </a>
            <a href="{{ route('spoc.state-tasks.index') }}" class="admin-topbar__link @if ($activeNav === 'state-tasks-spoc') is-active @endif">
                {!! $i('doc') !!}<span class="admin-topbar__link-text">My tasks</span>
            </a>
            <a href="{{ route('spoc.deliverables.index') }}" class="admin-topbar__link @if ($activeNav === 'deliverables') is-active @endif">
                {!! $i('bars') !!}<span class="admin-topbar__link-text">Deliverables</span>
            </a>
            <a href="{{ route('spoc.field-coordinator-reports.index') }}" class="admin-topbar__link @if ($activeNav === 'field-coordinator-report') is-active @endif">
                {!! $i('calendar') !!}<span class="admin-topbar__link-text">Field coordinator report</span>
            </a>
            @if ($canSubmitSocialMediaPost)
            <a href="{{ route('spoc.social-media-posts.index') }}" class="admin-topbar__link @if (in_array($activeNav, ['social-media-posts-submit', 'social-media-posts-dashboard'], true)) is-active @endif">
                {!! $i('doc') !!}<span class="admin-topbar__link-text">Social media</span>
            </a>
            @endif
            <a href="{{ route('library.documents.index') }}" class="admin-topbar__link @if ($activeNav === 'documents') is-active @endif">
                {!! $i('book') !!}<span class="admin-topbar__link-text">Documents</span>
            </a>
        </nav>
        @endif

        @if ($showHubNav)
        <div class="admin-topbar__nav-rail">
        <nav class="admin-topbar__nav admin-topbar__nav--hub-admin" aria-label="Hub">
            <a href="{{ route('dashboard') }}" class="admin-topbar__link @if ($activeNav === 'dashboard') is-active @endif">
                {!! $i('dashboard') !!}<span class="admin-topbar__link-text">Dashboard</span>
            </a>

            <details class="admin-topbar__details">
                <summary class="admin-topbar__link admin-topbar__dropdown-trigger @if ($hubCfaGroupActive) is-active @endif">
                    {!! $i('cfa') !!}<span class="admin-topbar__link-text">CFA</span>
                </summary>
                <div class="admin-topbar__dropdown-panel" role="menu">
                    <p class="admin-topbar__dropdown-kicker" role="presentation">Applications &amp; onboarding</p>
                    <a href="{{ route('hub.applications.index') }}" class="admin-topbar__dropdown-item @if ($activeNav === 'hub-applications') is-active @endif" role="menuitem">
                        {!! $i('inbox') !!}<span>CFA applications</span>
                    </a>
                    <a href="{{ route('hub.batches.index') }}" class="admin-topbar__dropdown-item @if ($activeNav === 'hub-batches') is-active @endif" role="menuitem">
                        {!! $i('batches') !!}<span>Batch manager</span>
                    </a>
                    <a href="{{ route('hub.onboarded.index') }}" class="admin-topbar__dropdown-item @if ($activeNav === 'onboarded') is-active @endif" role="menuitem">
                        {!! $i('badge') !!}<span>Onboarded incubatees</span>
                    </a>
                    <a href="{{ route('hub.onboarding-insight.index') }}" class="admin-topbar__dropdown-item @if ($activeNav === 'hub-onboarding-insight') is-active @endif" role="menuitem">
                        {!! $i('pie') !!}<span>Onboarding insight</span>
                    </a>
                </div>
            </details>

            <details class="admin-topbar__details">
                <summary class="admin-topbar__link admin-topbar__dropdown-trigger @if ($hubPerformanceGroupActive) is-active @endif">
                    {!! $i('bars') !!}<span class="admin-topbar__link-text">Performance</span>
                </summary>
                <div class="admin-topbar__dropdown-panel" role="menu">
                    <p class="admin-topbar__dropdown-kicker" role="presentation">Hub oversight</p>
                    <a href="{{ route('hub.deliverables.index') }}" class="admin-topbar__dropdown-item @if ($activeNav === 'deliverables') is-active @endif" role="menuitem">
                        {!! $i('pie') !!}<span>Deliverables</span>
                    </a>
                    <a href="{{ route('hub.staff-performance.index') }}" class="admin-topbar__dropdown-item @if ($activeNav === 'hub-staff-performance') is-active @endif" role="menuitem">
                        {!! $i('users') !!}<span>Staff performance</span>
                    </a>
                    <a href="{{ route('hub.field-coordinator-reports.index') }}" class="admin-topbar__dropdown-item @if ($activeNav === 'field-coordinator-report') is-active @endif" role="menuitem">
                        {!! $i('calendar') !!}<span>Field coordinator report</span>
                    </a>
                    <a href="{{ route('hub.pending-actions.index') }}" class="admin-topbar__dropdown-item @if ($activeNav === 'hub-pending-actions') is-active @endif" role="menuitem">
                        {!! $i('inbox') !!}<span>Pending actions</span>
                    </a>
                </div>
            </details>

            <details class="admin-topbar__details">
                <summary class="admin-topbar__link admin-topbar__dropdown-trigger @if ($hubMoreGroupActive) is-active @endif">
                    {!! $i('more') !!}<span class="admin-topbar__link-text">More</span>
                </summary>
                <div class="admin-topbar__dropdown-panel" role="menu">
                    @if ($showStaffDailyCheckInNav)
                    <a href="{{ route('staff-daily-check-in.index') }}" class="admin-topbar__dropdown-item @if ($activeNav === 'staff-daily-check-in') is-active @endif" role="menuitem">
                        {!! $i('pin') !!}<span>Daily attendance</span>
                    </a>
                    @endif
                    <a href="{{ route('library.documents.index') }}" class="admin-topbar__dropdown-item @if ($activeNav === 'documents') is-active @endif" role="menuitem">
                        {!! $i('book') !!}<span>Documents</span>
                    </a>
                </div>
            </details>
        </nav>
        </div>
        @endif

        @if ($showStaffNav)
        <nav class="admin-topbar__nav admin-topbar__nav--state-admin" aria-label="Staff">
            <a href="{{ route('dashboard') }}" class="admin-topbar__link @if ($activeNav === 'dashboard') is-active @endif">
                {!! $i('dashboard') !!}<span class="admin-topbar__link-text">Dashboard</span>
            </a>
            @if ($showStaffDailyCheckInNav)
            <a href="{{ route('staff-daily-check-in.index') }}" class="admin-topbar__link @if ($activeNav === 'staff-daily-check-in') is-active @endif">
                {!! $i('pin') !!}<span class="admin-topbar__link-text">Daily attendance</span>
            </a>
            @endif

            <details class="admin-topbar__details">
                <summary class="admin-topbar__link admin-topbar__dropdown-trigger @if ($staffCfaGroupActive) is-active @endif">
                    {!! $i('cfa') !!}<span class="admin-topbar__link-text">CFA</span>
                </summary>
                <div class="admin-topbar__dropdown-panel" role="menu">
                    <p class="admin-topbar__dropdown-kicker" role="presentation">Applications &amp; data</p>
                    <a href="{{ route('staff.applications') }}" class="admin-topbar__dropdown-item @if ($activeNav === 'staff-apps') is-active @endif" role="menuitem">
                        {!! $i('inbox') !!}<span>Applications</span>
                    </a>
                    <a href="{{ route('staff.phase1-data') }}" class="admin-topbar__dropdown-item @if ($activeNav === 'staff-phase1-data') is-active @endif" role="menuitem">
                        {!! $i('database') !!}<span>CFA (FY 2024-25 Data)</span>
                    </a>
                    <a href="{{ route('staff.phase2-data') }}" class="admin-topbar__dropdown-item @if ($activeNav === 'staff-phase2-data') is-active @endif" role="menuitem">
                        {!! $i('pie') !!}<span>FY 2025-26 Data</span>
                    </a>
                    <a href="{{ route('staff.onboarded.index') }}" class="admin-topbar__dropdown-item @if ($activeNav === 'onboarded') is-active @endif" role="menuitem">
                        {!! $i('batches') !!}<span>Onboarded</span>
                    </a>
                    <a href="{{ route('staff.batches.index') }}" class="admin-topbar__dropdown-item @if ($activeNav === 'staff-batches') is-active @endif" role="menuitem">
                        {!! $i('batches') !!}<span>Batches</span>
                    </a>
                </div>
            </details>

            <details class="admin-topbar__details">
                <summary class="admin-topbar__link admin-topbar__dropdown-trigger @if ($staffTargetsGroupActive) is-active @endif">
                    {!! $i('targets') !!}<span class="admin-topbar__link-text">Targets &amp; progress</span>
                </summary>
                <div class="admin-topbar__dropdown-panel" role="menu">
                    <p class="admin-topbar__dropdown-kicker" role="presentation">Planning &amp; reporting</p>
                    <a href="{{ route('staff.monthly-targets') }}" class="admin-topbar__dropdown-item @if ($activeNav === 'staff-targets') is-active @endif" role="menuitem">
                        {!! $i('calendar') !!}<span>Monthly targets</span>
                    </a>
                    <a href="{{ route('staff.deliverables.index') }}" class="admin-topbar__dropdown-item @if ($activeNav === 'deliverables') is-active @endif" role="menuitem">
                        {!! $i('bars') !!}<span>Deliverables</span>
                    </a>
                    <a href="{{ route('staff.field-coordinator-reports.index') }}" class="admin-topbar__dropdown-item @if ($activeNav === 'field-coordinator-report') is-active @endif" role="menuitem">
                        {!! $i('calendar') !!}<span>Field coordinator report</span>
                    </a>
                </div>
            </details>

            <details class="admin-topbar__details">
                <summary class="admin-topbar__link admin-topbar__dropdown-trigger @if ($staffServiceGroupActive) is-active @endif">
                    {!! $i('catalog') !!}<span class="admin-topbar__link-text">Service</span>
                </summary>
                <div class="admin-topbar__dropdown-panel admin-topbar__dropdown-panel--wide" role="menu">
                    <p class="admin-topbar__dropdown-kicker" role="presentation">Service module</p>
                    @if ($staffServiceModuleOn)
                    <a href="{{ route('staff.services.index') }}" class="admin-topbar__dropdown-item @if ($activeNav === 'staff-services') is-active @endif" role="menuitem">
                        {!! $i('doc') !!}<span>Services</span>
                    </a>
                    @endif
                    <a href="{{ route('staff.market-linkages.dashboard') }}" class="admin-topbar__dropdown-item @if (in_array($activeNav, ['market-linkages-submit', 'market-linkages-dashboard'], true)) is-active @endif" role="menuitem">
                        {!! $i('pin') !!}<span>Market linkage</span>
                    </a>
                </div>
            </details>
            @include('partials.staff-field-work-nav')

            <a href="{{ route('library.documents.index') }}" class="admin-topbar__link @if ($activeNav === 'documents') is-active @endif">
                {!! $i('book') !!}<span class="admin-topbar__link-text">Documents</span>
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
            <a href="{{ route('incubatee.documents.index') }}" class="admin-topbar__link @if ($activeNav === 'documents') is-active @endif">
                {!! $i('doc') !!}<span class="admin-topbar__link-text">Documents</span>
            </a>
        </nav>
        @endif

        <button class="admin-topbar__hamburger" id="muyMobileMenuToggle" aria-label="Open navigation menu" aria-expanded="false">
            <span class="admin-topbar__hamburger-icon" aria-hidden="true">
                <span></span>
                <span></span>
                <span></span>
            </span>
        </button>

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
                        @if (($dbUnreadNotificationCount ?? 0) > 0)
                            <form method="post" action="{{ route('notifications.read-all') }}" class="admin-topbar__notif-markall">
                                @csrf
                                <button type="submit">Mark all read</button>
                            </form>
                        @endif
                    </div>
                    @forelse ($notificationsPreview ?? [] as $n)
                        <a href="{{ $n['link'] ?? route('notifications.index') }}" class="admin-topbar__notif-item @if(!empty($n['is_unread'])) is-unread @endif" role="menuitem">
                            <span class="admin-topbar__notif-item-title">
                                {{ $n['title'] ?? 'Notification' }}
                                @if (!empty($n['is_reminder']))
                                    <span style="font-size:0.66rem;color:#b45309;margin-left:0.25rem;">Reminder</span>
                                @endif
                            </span>
                            <span class="admin-topbar__notif-item-body">{{ \Illuminate\Support\Str::limit($n['body'] ?? '', 120) }}</span>
                            <span class="admin-topbar__notif-item-time">{{ $n['time_human'] ?? '' }}</span>
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
                                <span class="admin-topbar__user-role">
                                    @if ($showHubNav)
                                        Hub admin
                                    @else
                                        {{ str_replace('_', ' ', $u->role ?? 'user') }}
                                    @endif
                                </span>
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

        /* Click anywhere outside the topbar closes all open dropdowns and mobile menu */
        document.addEventListener('click', function (e) {
            if (!e.target.closest('.admin-topbar')) {
                allDetails.forEach(function (d) {
                    if (d.open) d.removeAttribute('open');
                });
                closeMobileMenu();
            }
        });
    }

    function closeMobileMenu() {
        var hamburger = document.getElementById('muyMobileMenuToggle');
        if (hamburger && hamburger.getAttribute('aria-expanded') === 'true') {
            hamburger.setAttribute('aria-expanded', 'false');
            hamburger.setAttribute('aria-label', 'Open navigation menu');
            document.querySelectorAll('.admin-topbar__nav').forEach(function (nav) {
                nav.classList.remove('is-open');
            });
        }
    }

    function initMobileMenu() {
        var hamburger = document.getElementById('muyMobileMenuToggle');
        if (!hamburger) return;
        hamburger.addEventListener('click', function () {
            var isOpen = this.getAttribute('aria-expanded') === 'true';
            this.setAttribute('aria-expanded', String(!isOpen));
            this.setAttribute('aria-label', isOpen ? 'Open navigation menu' : 'Close navigation menu');
            document.querySelectorAll('.admin-topbar__nav').forEach(function (nav) {
                nav.classList.toggle('is-open', !isOpen);
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            initTopbarDropdowns();
            initMobileMenu();
        });
    } else {
        initTopbarDropdowns();
        initMobileMenu();
    }
}());
</script>
