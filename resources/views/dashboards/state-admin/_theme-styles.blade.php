{{-- Revamp v5: Warm earth — slate header, forest green brand, amber accent, settled KPI spectrum. --}}
.admin-app-body--state-theme-revamp,
.admin-app-body--state-theme-revamp .sad {
    --sad-text: #1c1917;
    --sad-muted: #78716c;
    --sad-border: #e7e5e4;
    --sad-surface: #ffffff;
    --sad-brand: #166534;
    --sad-brand-deep: #14532d;
    --sad-brand-light: #f0fdf4;
    --sad-accent: #c2410c;
    --sad-accent-soft: #fff7ed;
    --sad-green: #16a34a;
    --sad-green-deep: #15803d;
    --sad-teal: #b45309;
    --sad-sky: #57534e;
    --sad-navy: #292524;
    --sad-coral: #be185d;
    --sad-saffron: #d97706;
    --sad-gold: #a16207;
    --sad-radius: 14px;
    --sad-shadow: 0 2px 8px rgba(28, 25, 23, 0.05), 0 8px 22px rgba(22, 101, 52, 0.07);
    --sad-brand-grad: linear-gradient(135deg, #14532d 0%, #166534 45%, #b45309 100%);
    --sad-chart-primary: #166534;
    --sad-chart-secondary: #c2410c;
    --sad-chart-fill: rgba(22, 101, 52, 0.14);
    --sad-page-bg: #fafaf9;
    --sad-nav-shadow: 0 3px 12px rgba(22, 101, 52, 0.22);
    --sad-header-grad: linear-gradient(135deg, #292524 0%, #3f3f46 42%, #52525b 100%);
}
.admin-app-body--state-theme-revamp.admin-app-body--state-premium {
    background:
        radial-gradient(ellipse 70% 45% at 0% 0%, rgba(22, 101, 52, 0.05), transparent 55%),
        radial-gradient(ellipse 55% 40% at 100% 0%, rgba(194, 65, 12, 0.04), transparent 50%),
        var(--sad-page-bg) !important;
}

/* Unified header — warm slate, not blue */
.admin-app-body--dash-unified.admin-app-body--state-theme-revamp.admin-app-body--dashboard .admin-topbar,
.admin-app-body--state-theme-revamp.admin-app-body--dashboard .admin-topbar {
    background: var(--sad-header-grad) !important;
    border-bottom: none !important;
    box-shadow: none !important;
}
.admin-app-body--dash-unified.admin-app-body--state-theme-revamp .sad-unified-strip {
    background: linear-gradient(135deg, #3f3f46 0%, #52525b 55%, #57534e 100%);
    color: #fff;
    border-bottom: 2px solid #b45309;
    box-shadow: 0 6px 18px rgba(28, 25, 23, 0.12);
}
.admin-app-body--state-theme-revamp.admin-app-body--dashboard .admin-brand__name {
    background: none !important;
    -webkit-text-fill-color: #fafaf9 !important;
    color: #fafaf9 !important;
}
.admin-app-body--state-theme-revamp.admin-app-body--dashboard .admin-brand__sub {
    background: rgba(180, 83, 9, 0.28) !important;
    border-color: rgba(251, 191, 36, 0.35) !important;
    color: #fef3c7 !important;
}
.admin-app-body--state-theme-revamp.admin-app-body--dashboard .admin-topbar__nav--state-admin,
.admin-app-body--state-theme-revamp.admin-app-body--dashboard .admin-topbar__nav--hub-admin {
    border-top-color: rgba(255, 255, 255, 0.1) !important;
}
.admin-app-body--state-theme-revamp.admin-app-body--dashboard .admin-topbar__link {
    color: rgba(250, 250, 249, 0.9) !important;
}
.admin-app-body--state-theme-revamp.admin-app-body--dashboard .admin-topbar__link:hover {
    color: #fff !important;
    background: rgba(255, 255, 255, 0.1) !important;
    border-color: rgba(255, 255, 255, 0.15) !important;
}
.admin-app-body--state-theme-revamp.admin-app-body--dashboard .admin-topbar__link.is-active,
.admin-app-body--state-theme-revamp.admin-app-body--dashboard .admin-topbar__link.is-active.admin-topbar__dropdown-trigger {
    color: #14532d !important;
    background: #fef3c7 !important;
    border-color: #fcd34d !important;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.12) !important;
}
.admin-app-body--state-theme-revamp.admin-app-body--dashboard .admin-topbar__notif-summary {
    border-color: #e7e5e4 !important;
    background: #fafaf9 !important;
    color: #166534 !important;
}

.admin-app-body--dash-unified.admin-app-body--state-theme-revamp .sad-unified-strip__title {
    color: #fafaf9;
}
.admin-app-body--dash-unified.admin-app-body--state-theme-revamp .sad-unified-strip__sub {
    color: rgba(250, 250, 249, 0.78);
}
.admin-app-body--dash-unified.admin-app-body--state-theme-revamp .sad-unified-strip__sub strong {
    color: #fcd34d;
}
.admin-app-body--dash-unified.admin-app-body--state-theme-revamp .sad-badge {
    background: rgba(255, 255, 255, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.18);
    color: #f5f5f4;
}
.admin-app-body--dash-unified.admin-app-body--state-theme-revamp .sad-theme-toggle {
    background: #fffbeb;
    border-color: #fde68a;
    color: #b45309;
    font-weight: 800;
}
.admin-app-body--dash-unified.admin-app-body--state-theme-revamp .sad-theme-toggle:hover {
    background: #fef3c7;
    color: #92400e;
}

/* Legacy unified strip */
.admin-app-body--dash-unified.admin-app-body--state-theme-legacy.admin-app-body--dashboard .admin-topbar {
    border-bottom: none !important;
    box-shadow: none !important;
}
.admin-app-body--dash-unified.admin-app-body--state-theme-legacy .sad-unified-strip {
    background: linear-gradient(135deg, #a63d02 0%, #d04a02 55%, #eb8c00 100%);
    color: #fff;
    border-bottom: 1px solid rgba(255, 255, 255, 0.18);
    box-shadow: 0 6px 20px rgba(208, 74, 2, 0.2);
}
.admin-app-body--dash-unified.admin-app-body--state-theme-legacy .sad-unified-strip__title,
.admin-app-body--dash-unified.admin-app-body--state-theme-legacy .sad-unified-strip__sub {
    color: rgba(255, 255, 255, 0.92);
}
.admin-app-body--dash-unified.admin-app-body--state-theme-legacy .sad-badge {
    background: rgba(255, 255, 255, 0.14);
    border-color: rgba(255, 255, 255, 0.28);
    color: #fff;
}
.admin-app-body--dash-unified.admin-app-body--state-theme-legacy .sad-theme-toggle {
    background: #fff;
    color: #a63d02;
}

/* KPI — settled multi-tone (no blue overload) */
.admin-app-body--state-theme-revamp .sad-kpi {
    border-radius: 14px;
    padding: 0.75rem 0.85rem;
    border: 1px solid #f5f5f4;
    transition: transform 0.18s ease, box-shadow 0.18s ease;
    position: relative;
    overflow: hidden;
}
.admin-app-body--state-theme-revamp .sad-kpi::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
}
.admin-app-body--state-theme-revamp .sad-kpi:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 24px rgba(28, 25, 23, 0.08);
}
.admin-app-body--state-theme-revamp .sad-kpi__value {
    font-size: 1.32rem;
    font-weight: 800;
    letter-spacing: -0.03em;
    color: var(--sad-navy);
}
.admin-app-body--state-theme-revamp .sad-kpi__icon {
    width: 2.1rem;
    height: 2.1rem;
    border-radius: 11px;
    font-size: 0.85rem;
    box-shadow: 0 2px 6px rgba(28, 25, 23, 0.06);
}
.admin-app-body--state-theme-revamp .sad-kpi--tone-blue { background: linear-gradient(160deg, #f0fdf4 0%, #fff 100%); }
.admin-app-body--state-theme-revamp .sad-kpi--tone-blue::before { background: linear-gradient(90deg, #166534, #4ade80); }
.admin-app-body--state-theme-revamp .sad-kpi--tone-indigo { background: linear-gradient(160deg, #fff7ed 0%, #fff 100%); }
.admin-app-body--state-theme-revamp .sad-kpi--tone-indigo::before { background: linear-gradient(90deg, #c2410c, #fb923c); }
.admin-app-body--state-theme-revamp .sad-kpi--tone-amber { background: linear-gradient(160deg, #fffbeb 0%, #fff 100%); }
.admin-app-body--state-theme-revamp .sad-kpi--tone-amber::before { background: linear-gradient(90deg, #b45309, #fbbf24); }
.admin-app-body--state-theme-revamp .sad-kpi--tone-violet { background: linear-gradient(160deg, #fdf2f8 0%, #fff 100%); }
.admin-app-body--state-theme-revamp .sad-kpi--tone-violet::before { background: linear-gradient(90deg, #9d174d, #f472b6); }
.admin-app-body--state-theme-revamp .sad-kpi--tone-cyan { background: linear-gradient(160deg, #fef2f2 0%, #fff 100%); }
.admin-app-body--state-theme-revamp .sad-kpi--tone-cyan::before { background: linear-gradient(90deg, #b91c1c, #f87171); }
.admin-app-body--state-theme-revamp .sad-kpi--tone-slate { background: linear-gradient(160deg, #fafaf9 0%, #fff 100%); }
.admin-app-body--state-theme-revamp .sad-kpi--tone-slate::before { background: linear-gradient(90deg, #57534e, #a8a29e); }
.admin-app-body--state-theme-revamp .sad-kpi--tone-teal { background: linear-gradient(160deg, #fefce8 0%, #fff 100%); }
.admin-app-body--state-theme-revamp .sad-kpi--tone-teal::before { background: linear-gradient(90deg, #a16207, #facc15); }
.admin-app-body--state-theme-revamp .sad-kpi--tone-emerald { background: linear-gradient(160deg, #ecfdf5 0%, #fff 100%); }
.admin-app-body--state-theme-revamp .sad-kpi--tone-emerald::before { background: linear-gradient(90deg, #15803d, #86efac); }

.admin-app-body--state-theme-revamp .sad-kpi__icon--green { background: #dcfce7; color: #166534; }
.admin-app-body--state-theme-revamp .sad-kpi__icon--teal { background: #ffedd5; color: #c2410c; }
.admin-app-body--state-theme-revamp .sad-kpi__icon--sky { background: #fef3c7; color: #b45309; }
.admin-app-body--state-theme-revamp .sad-kpi__icon--amber { background: #fce7f3; color: #9d174d; }
.admin-app-body--state-theme-revamp .sad-kpi__icon--rose { background: #fee2e2; color: #b91c1c; }
.admin-app-body--state-theme-revamp .sad-kpi__icon--indigo { background: #f5f5f4; color: #57534e; }
.admin-app-body--state-theme-revamp .sad-kpi__icon--teal2 { background: #fef9c3; color: #a16207; }

/* Nav + cards */
.admin-app-body--state-theme-revamp .sad-nav {
    background: #fff;
    border: 1px solid #e7e5e4;
    border-radius: 999px;
    padding: 0.32rem;
    box-shadow: 0 2px 12px rgba(28, 25, 23, 0.05);
}
.admin-app-body--state-theme-revamp .sad-nav__btn.is-active {
    background: var(--sad-brand-grad);
    color: #fff;
    box-shadow: var(--sad-nav-shadow);
}
.admin-app-body--state-theme-revamp .sad-card {
    border-radius: 14px;
    border-color: #e7e5e4;
    box-shadow: 0 2px 8px rgba(28, 25, 23, 0.04);
    background: #fff;
}
.admin-app-body--state-theme-revamp .sad-card__tag {
    background: #f0fdf4;
    color: #14532d;
    border: 1px solid #bbf7d0;
}
.admin-app-body--state-theme-revamp .sad-card__title i {
    color: #166534;
}
.admin-app-body--state-theme-revamp .sad-progress-fill--sky {
    background: linear-gradient(90deg, #166534, #c2410c);
}
.admin-app-body--state-theme-revamp .sad-district-card.is-top {
    border-color: #fcd34d;
    background: linear-gradient(180deg, #fffbeb 0%, #fff 100%);
}
.admin-app-body--state-theme-revamp .sad-alert--info {
    background: #fff7ed;
    border-color: #fdba74;
    color: #9a3412;
}
.admin-app-body--state-theme-revamp .sad-savings-tile--blue {
    border-color: #fde68a;
    background: #fffbeb;
}
.admin-app-body--state-theme-revamp .sad-savings-tile--blue .sad-savings-tile__lbl {
    color: #92400e !important;
}
.admin-app-body--state-theme-revamp .sad-savings-tile--blue .sad-savings-tile__val {
    color: #78350f !important;
}
.admin-app-body--state-theme-revamp .sad-kpi__icon--sky {
    background: #fef3c7 !important;
    color: #b45309 !important;
}
.admin-app-body--state-theme-revamp .sad-alert--ok {
    background: #ecfdf5;
    border-color: #6ee7b7;
    color: #047857;
}
.admin-app-body--state-theme-revamp .sad-alert--warn {
    background: #fffbeb;
    border-color: #fcd34d;
    color: #92400e;
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
    transition: border-color 0.15s, color 0.15s, background 0.15s;
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
.admin-app-body--state-theme-legacy .sad-kpi__icon--indigo { background: #eef2ff; color: #4338ca; }
.admin-app-body--state-theme-legacy .sad-kpi__icon--teal2 { background: #ecfeff; color: #0891b2; }
