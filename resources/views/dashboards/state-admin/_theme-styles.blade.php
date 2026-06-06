{{-- Revamp v2: light alpine — white header, emerald accents. Legacy: orange (revert via Classic theme). --}}
.admin-app-body--state-theme-revamp,
.admin-app-body--state-theme-revamp .sad {
    --sad-text: #1c1917;
    --sad-muted: #78716c;
    --sad-border: #e7e5e4;
    --sad-surface: #ffffff;
    --sad-brand: #059669;
    --sad-brand-deep: #047857;
    --sad-brand-light: #ecfdf5;
    --sad-accent: #0d9488;
    --sad-accent-soft: #f0fdfa;
    --sad-green: #16a34a;
    --sad-green-deep: #15803d;
    --sad-teal: #0d9488;
    --sad-sky: #57534e;
    --sad-navy: #292524;
    --sad-coral: #d97706;
    --sad-saffron: #f59e0b;
    --sad-gold: #ca8a04;
    --sad-radius: 14px;
    --sad-shadow: 0 1px 3px rgba(28, 25, 23, 0.04), 0 6px 20px rgba(5, 150, 105, 0.06);
    --sad-brand-grad: linear-gradient(135deg, #047857 0%, #059669 50%, #0d9488 100%);
    --sad-chart-primary: #059669;
    --sad-chart-secondary: #0d9488;
    --sad-chart-fill: rgba(5, 150, 105, 0.16);
    --sad-page-bg: #fafaf9;
    --sad-nav-shadow: 0 2px 8px rgba(5, 150, 105, 0.22);
}
.admin-app-body--state-theme-revamp.admin-app-body--state-premium {
    background: var(--sad-page-bg) !important;
}

/* Light topbar — no dark blue */
.admin-app-body--state-theme-revamp.admin-app-body--dashboard .admin-topbar {
    background: #ffffff !important;
    border-bottom: 1px solid #e7e5e4 !important;
    box-shadow: 0 1px 0 rgba(28, 25, 23, 0.04), 0 4px 16px rgba(28, 25, 23, 0.03) !important;
}
.admin-app-body--state-theme-revamp.admin-app-body--dashboard .admin-brand__name {
    background: none !important;
    -webkit-text-fill-color: #1c1917 !important;
    color: #1c1917 !important;
}
.admin-app-body--state-theme-revamp.admin-app-body--dashboard .admin-brand__sub {
    background: #ecfdf5 !important;
    border-color: #a7f3d0 !important;
    color: #047857 !important;
}
.admin-app-body--state-theme-revamp.admin-app-body--dashboard .admin-topbar__nav--state-admin,
.admin-app-body--state-theme-revamp.admin-app-body--dashboard .admin-topbar__nav--hub-admin {
    border-top-color: #f5f5f4 !important;
}
.admin-app-body--state-theme-revamp.admin-app-body--dashboard .admin-topbar__link {
    color: #57534e !important;
}
.admin-app-body--state-theme-revamp.admin-app-body--dashboard .admin-topbar__link:hover {
    color: #047857 !important;
    background: #f5f5f4 !important;
    border-color: #e7e5e4 !important;
}
.admin-app-body--state-theme-revamp.admin-app-body--dashboard .admin-topbar__link.is-active,
.admin-app-body--state-theme-revamp.admin-app-body--dashboard .admin-topbar__link.is-active.admin-topbar__dropdown-trigger {
    color: #047857 !important;
    background: #ecfdf5 !important;
    border-color: #a7f3d0 !important;
    box-shadow: none !important;
}
.admin-app-body--state-theme-revamp.admin-app-body--dashboard .admin-topbar__notif-summary {
    border-color: #e7e5e4 !important;
    background: #fafaf9 !important;
    color: #57534e !important;
}
.admin-app-body--state-theme-revamp.admin-app-body--dashboard .admin-topbar__user-trigger,
.admin-app-body--state-theme-revamp.admin-app-body--dashboard .admin-topbar__icon-btn {
    color: #57534e !important;
    border-color: #e7e5e4 !important;
}

.admin-app-body--state-theme-revamp .sad-masthead {
    border: 1px solid #d1fae5;
    border-left: 4px solid var(--sad-brand);
    border-top: none;
    background: linear-gradient(118deg, #ffffff 0%, #f0fdf4 42%, #ffffff 100%);
    box-shadow: var(--sad-shadow);
    padding: 1.2rem 1.35rem;
}
.admin-app-body--state-theme-revamp .sad-masthead__eyebrow {
    color: var(--sad-brand-deep);
    background: #ecfdf5;
    padding: 0.2rem 0.55rem;
    border-radius: 6px;
    border: 1px solid #a7f3d0;
}
.admin-app-body--state-theme-revamp .sad-kpi {
    border-radius: 14px;
    padding: 0.7rem 0.8rem;
    background: linear-gradient(180deg, #ffffff 0%, #fafaf9 100%);
    transition: transform 0.15s ease, box-shadow 0.15s ease, border-color 0.15s ease;
}
.admin-app-body--state-theme-revamp .sad-kpi:hover {
    border-color: #a7f3d0;
    box-shadow: 0 8px 24px rgba(5, 150, 105, 0.1);
    transform: translateY(-1px);
}
.admin-app-body--state-theme-revamp .sad-kpi__value {
    font-size: 1.28rem;
    color: var(--sad-navy);
}
.admin-app-body--state-theme-revamp .sad-kpi__icon {
    width: 2rem;
    height: 2rem;
    border-radius: 10px;
    font-size: 0.82rem;
}
.admin-app-body--state-theme-revamp .sad-nav {
    background: #ffffff;
    border: 1px solid #e7e5e4;
    border-radius: 999px;
    padding: 0.3rem;
    box-shadow: 0 1px 3px rgba(28, 25, 23, 0.04);
}
.admin-app-body--state-theme-revamp .sad-nav__btn {
    border-radius: 999px;
    font-size: 0.76rem;
}
.admin-app-body--state-theme-revamp .sad-nav__btn.is-active {
    background: var(--sad-brand);
    color: #fff;
    box-shadow: var(--sad-nav-shadow);
}
.admin-app-body--state-theme-revamp .sad-card {
    border-radius: 14px;
    border-color: #e7e5e4;
    box-shadow: 0 1px 3px rgba(28, 25, 23, 0.03);
}
.admin-app-body--state-theme-revamp .sad-card__tag {
    background: #ecfdf5;
    color: #047857;
    border: 1px solid #a7f3d0;
}
.admin-app-body--state-theme-revamp .sad-progress-fill--sky {
    background: linear-gradient(90deg, #059669, #0d9488);
}
.admin-app-body--state-theme-revamp .sad-district-card.is-top {
    border-color: #6ee7b7;
    background: linear-gradient(180deg, #ecfdf5 0%, #fff 100%);
}
.admin-app-body--state-theme-revamp .sad-alert--info {
    background: #f0fdfa;
    border-color: #99f6e4;
    color: #0f766e;
}
.admin-app-body--state-theme-revamp .sad-theme-toggle {
    background: #fffbeb;
    border-color: #fde68a;
    color: #b45309;
}
.admin-app-body--state-theme-revamp .sad-theme-toggle:hover {
    border-color: #f59e0b;
    color: #92400e;
}
.admin-app-body--state-theme-revamp .sad-kpi__icon--teal {
    background: #ecfdf5;
    color: #047857;
}
.admin-app-body--state-theme-revamp .sad-kpi__icon--sky {
    background: #f0fdfa;
    color: #0f766e;
}
.admin-app-body--state-theme-revamp .sad-kpi__icon--indigo {
    background: #f5f3ff;
    color: #6d28d9;
}
.admin-app-body--state-theme-revamp .sad-kpi__icon--teal2 {
    background: #ecfdf5;
    color: #059669;
}

.admin-app-body--state-theme-legacy,
.admin-app-body--state-theme-legacy .sad {
    --sad-text: #0f172a;
    --sad-muted: #64748b;
    --sad-border: #e2e8f0;
    --sad-surface: #ffffff;
    --sad-brand: #d04a02;
    --sad-brand-deep: #a63d02;
    --sad-brand-light: #fdeee6;
    --sad-accent: #eb8c00;
    --sad-accent-soft: #fff4e6;
    --sad-green: #22c55e;
    --sad-green-deep: #16a34a;
    --sad-teal: #d04a02;
    --sad-sky: #464646;
    --sad-navy: #2d2d2d;
    --sad-coral: #eb8c00;
    --sad-saffron: #ffb600;
    --sad-gold: #b45309;
    --sad-radius: 12px;
    --sad-shadow: 0 1px 2px rgba(15, 23, 42, 0.05), 0 8px 24px rgba(208, 74, 2, 0.08);
    --sad-brand-grad: linear-gradient(135deg, #a63d02 0%, #d04a02 55%, #eb8c00 100%);
    --sad-chart-primary: #d04a02;
    --sad-chart-secondary: #eb8c00;
    --sad-chart-fill: rgba(208, 74, 2, 0.24);
    --sad-nav-shadow: 0 2px 8px rgba(208, 74, 2, 0.28);
}
.admin-app-body--state-theme-legacy.admin-app-body--state-premium {
    background: #f7f5f2 !important;
}

.sad-theme-toggle {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.38rem 0.65rem;
    border-radius: 999px;
    background: var(--sad-brand-light);
    border: 1px solid var(--sad-border);
    font-size: 0.72rem;
    font-weight: 700;
    color: var(--sad-brand-deep);
    text-decoration: none;
    white-space: nowrap;
    transition: border-color 0.15s, color 0.15s;
}
.sad-theme-toggle:hover {
    border-color: var(--sad-brand);
    color: var(--sad-brand);
}
.sad-insights-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 0.65rem;
    margin-top: 0.65rem;
}
.sad-insights-grid--2 {
    grid-template-columns: repeat(2, minmax(0, 1fr));
}
@media (max-width: 1100px) {
    .sad-insights-grid,
    .sad-insights-grid--2 { grid-template-columns: 1fr; }
}
.sad-chart-box--donut { height: 210px; position: relative; }
.sad-chart-box--md { height: 230px; position: relative; }
.sad-kpi__icon--indigo { background: #eef2ff; color: #4338ca; }
.sad-kpi__icon--teal2 { background: #ecfdf5; color: #0f766e; }
