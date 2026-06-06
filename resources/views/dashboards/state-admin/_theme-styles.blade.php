{{-- Revamp v3: Executive Pro — indigo/violet brand, rich KPI tones. Legacy = orange. --}}
.admin-app-body--state-theme-revamp,
.admin-app-body--state-theme-revamp .sad {
    --sad-text: #0f172a;
    --sad-muted: #64748b;
    --sad-border: #e2e8f0;
    --sad-surface: #ffffff;
    --sad-brand: #4f46e5;
    --sad-brand-deep: #3730a3;
    --sad-brand-light: #eef2ff;
    --sad-accent: #0284c7;
    --sad-accent-soft: #e0f2fe;
    --sad-green: #10b981;
    --sad-green-deep: #059669;
    --sad-teal: #0891b2;
    --sad-sky: #2563eb;
    --sad-navy: #1e1b4b;
    --sad-coral: #7c3aed;
    --sad-saffron: #f59e0b;
    --sad-gold: #d97706;
    --sad-radius: 14px;
    --sad-shadow: 0 4px 6px -1px rgba(15, 23, 42, 0.06), 0 12px 28px -4px rgba(79, 70, 229, 0.14);
    --sad-brand-grad: linear-gradient(135deg, #312e81 0%, #4f46e5 45%, #6366f1 100%);
    --sad-chart-primary: #4f46e5;
    --sad-chart-secondary: #0891b2;
    --sad-chart-fill: rgba(79, 70, 229, 0.18);
    --sad-page-bg: #f1f5f9;
    --sad-nav-shadow: 0 4px 14px rgba(79, 70, 229, 0.35);
}
.admin-app-body--state-theme-revamp.admin-app-body--state-premium {
    background:
        radial-gradient(ellipse 80% 50% at 10% -10%, rgba(99, 102, 241, 0.12), transparent 55%),
        radial-gradient(ellipse 60% 40% at 95% 0%, rgba(14, 165, 233, 0.1), transparent 50%),
        var(--sad-page-bg) !important;
}

/* Single header: topbar + unified strip (no double card) */
.admin-app-body--dash-unified.admin-app-body--state-theme-revamp.admin-app-body--dashboard .admin-topbar {
    background: linear-gradient(135deg, #1e1b4b 0%, #312e81 38%, #4338ca 72%, #4f46e5 100%) !important;
    border-bottom: none !important;
    box-shadow: none !important;
}
.admin-app-body--dash-unified.admin-app-body--state-theme-revamp .sad-unified-strip {
    background: linear-gradient(135deg, #312e81 0%, #4338ca 48%, #4f46e5 100%);
    color: #fff;
    border-bottom: 1px solid rgba(255, 255, 255, 0.14);
    box-shadow: 0 8px 24px rgba(30, 27, 75, 0.22);
}
.admin-app-body--state-theme-revamp.admin-app-body--dashboard .admin-topbar {
    background: linear-gradient(135deg, #1e1b4b 0%, #312e81 38%, #4338ca 72%, #4f46e5 100%) !important;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1) !important;
    box-shadow: 0 4px 24px rgba(30, 27, 75, 0.35) !important;
}
.admin-app-body--state-theme-revamp.admin-app-body--dashboard .admin-brand__name {
    background: none !important;
    -webkit-text-fill-color: #fff !important;
    color: #fff !important;
}
.admin-app-body--state-theme-revamp.admin-app-body--dashboard .admin-brand__sub {
    background: rgba(255, 255, 255, 0.14) !important;
    border-color: rgba(255, 255, 255, 0.28) !important;
    color: #e0e7ff !important;
}
.admin-app-body--state-theme-revamp.admin-app-body--dashboard .admin-topbar__nav--state-admin,
.admin-app-body--state-theme-revamp.admin-app-body--dashboard .admin-topbar__nav--hub-admin {
    border-top-color: rgba(255, 255, 255, 0.12) !important;
}
.admin-app-body--state-theme-revamp.admin-app-body--dashboard .admin-topbar__link {
    color: rgba(255, 255, 255, 0.88) !important;
}
.admin-app-body--state-theme-revamp.admin-app-body--dashboard .admin-topbar__link:hover {
    color: #fff !important;
    background: rgba(255, 255, 255, 0.14) !important;
    border-color: rgba(255, 255, 255, 0.2) !important;
}
.admin-app-body--state-theme-revamp.admin-app-body--dashboard .admin-topbar__link.is-active,
.admin-app-body--state-theme-revamp.admin-app-body--dashboard .admin-topbar__link.is-active.admin-topbar__dropdown-trigger {
    color: #3730a3 !important;
    background: #fff !important;
    border-color: transparent !important;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.15) !important;
}
.admin-app-body--state-theme-revamp.admin-app-body--dashboard .admin-topbar__notif-summary {
    border-color: rgba(255, 255, 255, 0.35) !important;
    background: rgba(255, 255, 255, 0.95) !important;
    color: #4f46e5 !important;
}

.admin-app-body--dash-unified.admin-app-body--state-theme-revamp .sad-unified-strip__title {
    color: #fff;
}
.admin-app-body--dash-unified.admin-app-body--state-theme-revamp .sad-unified-strip__sub {
    color: rgba(255, 255, 255, 0.82);
}
.admin-app-body--dash-unified.admin-app-body--state-theme-revamp .sad-unified-strip__sub strong {
    color: #c7d2fe;
}
.admin-app-body--dash-unified.admin-app-body--state-theme-revamp .sad-badge {
    background: rgba(255, 255, 255, 0.12);
    border: 1px solid rgba(255, 255, 255, 0.22);
    color: #f8fafc;
    backdrop-filter: blur(4px);
}
.admin-app-body--dash-unified.admin-app-body--state-theme-revamp .sad-theme-toggle {
    background: rgba(255, 255, 255, 0.95);
    border-color: rgba(255, 255, 255, 0.5);
    color: #4338ca;
    font-weight: 800;
}
.admin-app-body--dash-unified.admin-app-body--state-theme-revamp .sad-theme-toggle:hover {
    background: #fff;
    color: #3730a3;
}

/* Legacy: unified strip matches orange topbar */
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

/* KPI tone cards */
.admin-app-body--state-theme-revamp .sad-kpi {
    border-radius: 14px;
    padding: 0.75rem 0.85rem;
    border: 1px solid transparent;
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
    border-radius: 14px 14px 0 0;
}
.admin-app-body--state-theme-revamp .sad-kpi:hover {
    transform: translateY(-2px);
    box-shadow: 0 12px 28px rgba(15, 23, 42, 0.1);
}
.admin-app-body--state-theme-revamp .sad-kpi__value {
    font-size: 1.32rem;
    font-weight: 800;
    letter-spacing: -0.03em;
}
.admin-app-body--state-theme-revamp .sad-kpi__icon {
    width: 2.1rem;
    height: 2.1rem;
    border-radius: 11px;
    font-size: 0.85rem;
    box-shadow: 0 2px 8px rgba(15, 23, 42, 0.08);
}
.admin-app-body--state-theme-revamp .sad-kpi--tone-blue { background: linear-gradient(160deg, #eff6ff 0%, #fff 100%); }
.admin-app-body--state-theme-revamp .sad-kpi--tone-blue::before { background: linear-gradient(90deg, #2563eb, #60a5fa); }
.admin-app-body--state-theme-revamp .sad-kpi--tone-indigo { background: linear-gradient(160deg, #eef2ff 0%, #fff 100%); }
.admin-app-body--state-theme-revamp .sad-kpi--tone-indigo::before { background: linear-gradient(90deg, #4f46e5, #818cf8); }
.admin-app-body--state-theme-revamp .sad-kpi--tone-amber { background: linear-gradient(160deg, #fffbeb 0%, #fff 100%); }
.admin-app-body--state-theme-revamp .sad-kpi--tone-amber::before { background: linear-gradient(90deg, #d97706, #fbbf24); }
.admin-app-body--state-theme-revamp .sad-kpi--tone-violet { background: linear-gradient(160deg, #f5f3ff 0%, #fff 100%); }
.admin-app-body--state-theme-revamp .sad-kpi--tone-violet::before { background: linear-gradient(90deg, #7c3aed, #a78bfa); }
.admin-app-body--state-theme-revamp .sad-kpi--tone-cyan { background: linear-gradient(160deg, #ecfeff 0%, #fff 100%); }
.admin-app-body--state-theme-revamp .sad-kpi--tone-cyan::before { background: linear-gradient(90deg, #0891b2, #22d3ee); }
.admin-app-body--state-theme-revamp .sad-kpi--tone-slate { background: linear-gradient(160deg, #f8fafc 0%, #fff 100%); }
.admin-app-body--state-theme-revamp .sad-kpi--tone-slate::before { background: linear-gradient(90deg, #475569, #94a3b8); }
.admin-app-body--state-theme-revamp .sad-kpi--tone-teal { background: linear-gradient(160deg, #f0fdfa 0%, #fff 100%); }
.admin-app-body--state-theme-revamp .sad-kpi--tone-teal::before { background: linear-gradient(90deg, #0d9488, #2dd4bf); }
.admin-app-body--state-theme-revamp .sad-kpi--tone-emerald { background: linear-gradient(160deg, #ecfdf5 0%, #fff 100%); }
.admin-app-body--state-theme-revamp .sad-kpi--tone-emerald::before { background: linear-gradient(90deg, #059669, #34d399); }

.admin-app-body--state-theme-revamp .sad-kpi__icon--green { background: #dbeafe; color: #1d4ed8; }
.admin-app-body--state-theme-revamp .sad-kpi__icon--teal { background: #e0e7ff; color: #4338ca; }
.admin-app-body--state-theme-revamp .sad-kpi__icon--sky { background: #fef3c7; color: #b45309; }
.admin-app-body--state-theme-revamp .sad-kpi__icon--amber { background: #ede9fe; color: #6d28d9; }
.admin-app-body--state-theme-revamp .sad-kpi__icon--rose { background: #cffafe; color: #0e7490; }
.admin-app-body--state-theme-revamp .sad-kpi__icon--indigo { background: #f1f5f9; color: #334155; }
.admin-app-body--state-theme-revamp .sad-kpi__icon--teal2 { background: #ccfbf1; color: #0f766e; }

/* Nav + cards */
.admin-app-body--state-theme-revamp .sad-nav {
    background: rgba(255, 255, 255, 0.92);
    border: 1px solid #e2e8f0;
    border-radius: 999px;
    padding: 0.32rem;
    box-shadow: 0 4px 20px rgba(15, 23, 42, 0.06);
    backdrop-filter: blur(8px);
}
.admin-app-body--state-theme-revamp .sad-nav__btn {
    border-radius: 999px;
}
.admin-app-body--state-theme-revamp .sad-nav__btn.is-active {
    background: var(--sad-brand-grad);
    color: #fff;
    box-shadow: var(--sad-nav-shadow);
}
.admin-app-body--state-theme-revamp .sad-card {
    border-radius: 14px;
    border-color: #e2e8f0;
    box-shadow: 0 2px 8px rgba(15, 23, 42, 0.04);
    background: rgba(255, 255, 255, 0.97);
}
.admin-app-body--state-theme-revamp .sad-card__tag {
    background: #eef2ff;
    color: #4338ca;
    border: 1px solid #c7d2fe;
}
.admin-app-body--state-theme-revamp .sad-card__title i {
    color: #4f46e5;
}
.admin-app-body--state-theme-revamp .sad-progress-fill--sky {
    background: linear-gradient(90deg, #4f46e5, #0891b2);
}
.admin-app-body--state-theme-revamp .sad-district-card.is-top {
    border-color: #a5b4fc;
    background: linear-gradient(180deg, #eef2ff 0%, #fff 100%);
}
.admin-app-body--state-theme-revamp .sad-alert--info {
    background: #eef2ff;
    border-color: #a5b4fc;
    color: #4338ca;
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
.sad-kpi__icon--indigo { background: #eef2ff; color: #4338ca; }
.sad-kpi__icon--teal2 { background: #ecfeff; color: #0891b2; }
