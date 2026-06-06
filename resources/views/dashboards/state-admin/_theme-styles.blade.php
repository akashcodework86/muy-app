{{-- Revamp v6: EduLearn-inspired — light canvas, soft teal brand, pastel KPI accents. --}}
.admin-app-body--state-theme-revamp,
.admin-app-body--state-theme-revamp .sad {
    --sad-text: #37474f;
    --sad-muted: #78909c;
    --sad-border: #e8ecf1;
    --sad-surface: #ffffff;
    --sad-brand: #26a69a;
    --sad-brand-deep: #00897b;
    --sad-brand-light: #e0f2f1;
    --sad-accent: #ff8a65;
    --sad-accent-soft: #fff3e0;
    --sad-green: #66bb6a;
    --sad-green-deep: #43a047;
    --sad-teal: #26a69a;
    --sad-sky: #42a5f5;
    --sad-navy: #263238;
    --sad-coral: #f06292;
    --sad-saffron: #ffca28;
    --sad-gold: #ffb300;
    --sad-radius: 16px;
    --sad-shadow: 0 2px 12px rgba(55, 71, 79, 0.06), 0 8px 24px rgba(38, 166, 154, 0.06);
    --sad-brand-grad: linear-gradient(135deg, #00897b 0%, #26a69a 50%, #4db6ac 100%);
    --sad-chart-primary: #26a69a;
    --sad-chart-secondary: #42a5f5;
    --sad-chart-fill: rgba(38, 166, 154, 0.12);
    --sad-page-bg: #f0f4f8;
    --sad-nav-shadow: 0 4px 14px rgba(38, 166, 154, 0.22);
    --sad-header-grad: #ffffff;
}
.admin-app-body--state-theme-revamp.admin-app-body--state-premium {
    background: var(--sad-page-bg) !important;
}

/* Light topbar — clean like reference dashboard */
.admin-app-body--dash-unified.admin-app-body--state-theme-revamp.admin-app-body--dashboard .admin-topbar,
.admin-app-body--state-theme-revamp.admin-app-body--dashboard .admin-topbar {
    background: #ffffff !important;
    border-bottom: 1px solid #e8ecf1 !important;
    box-shadow: 0 1px 8px rgba(55, 71, 79, 0.05) !important;
}
.admin-app-body--dash-unified.admin-app-body--state-theme-revamp .sad-unified-strip {
    background: linear-gradient(120deg, #00897b 0%, #26a69a 45%, #4db6ac 100%);
    color: #fff;
    border-bottom: none;
    box-shadow: 0 8px 24px rgba(38, 166, 154, 0.22);
}
.admin-app-body--state-theme-revamp.admin-app-body--dashboard .admin-brand__name {
    background: none !important;
    -webkit-text-fill-color: #263238 !important;
    color: #263238 !important;
}
.admin-app-body--state-theme-revamp.admin-app-body--dashboard .admin-brand__sub {
    background: #e0f2f1 !important;
    border-color: #b2dfdb !important;
    color: #00695c !important;
}
.admin-app-body--state-theme-revamp.admin-app-body--dashboard .admin-topbar__nav--state-admin,
.admin-app-body--state-theme-revamp.admin-app-body--dashboard .admin-topbar__nav--hub-admin {
    border-top-color: #e8ecf1 !important;
}
.admin-app-body--state-theme-revamp.admin-app-body--dashboard .admin-topbar__link {
    color: #546e7a !important;
}
.admin-app-body--state-theme-revamp.admin-app-body--dashboard .admin-topbar__link:hover {
    color: #26a69a !important;
    background: #e0f2f1 !important;
    border-color: #b2dfdb !important;
}
.admin-app-body--state-theme-revamp.admin-app-body--dashboard .admin-topbar__link.is-active,
.admin-app-body--state-theme-revamp.admin-app-body--dashboard .admin-topbar__link.is-active.admin-topbar__dropdown-trigger {
    color: #1565c0 !important;
    background: #e3f2fd !important;
    border-color: #90caf9 !important;
    box-shadow: none !important;
}
.admin-app-body--state-theme-revamp.admin-app-body--dashboard .admin-topbar__notif-summary {
    border-color: #e8ecf1 !important;
    background: #f5f7fa !important;
    color: #26a69a !important;
}
.admin-app-body--state-theme-revamp.admin-app-body--dashboard .admin-topbar__link-ico {
    color: #607d8b !important;
}
.admin-app-body--state-theme-revamp.admin-app-body--dashboard .admin-topbar__link:hover .admin-topbar__link-ico {
    color: #26a69a !important;
}
.admin-app-body--state-theme-revamp.admin-app-body--dashboard .admin-topbar__link.is-active .admin-topbar__link-ico {
    color: #1976d2 !important;
}

/* Hub admin revamp — light header; override orange hub shell (white links on white rail) */
.admin-app-body--state-theme-revamp.admin-app-body--hub-admin .admin-topbar--hub {
    background: #ffffff !important;
    border-bottom: 1px solid #e8ecf1 !important;
    box-shadow: 0 1px 8px rgba(55, 71, 79, 0.05) !important;
}
.admin-app-body--state-theme-revamp.admin-app-body--hub-admin .admin-topbar--hub::before,
.admin-app-body--state-theme-revamp.admin-app-body--hub-admin .admin-topbar--hub::after {
    display: none !important;
}
.admin-app-body--state-theme-revamp.admin-app-body--hub-admin .admin-topbar--hub .admin-topbar__nav-rail {
    background: #eef2f6 !important;
    border: 1px solid #dde3ea !important;
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.85) !important;
}
.admin-app-body--state-theme-revamp.admin-app-body--hub-admin .admin-topbar--hub .admin-topbar__nav--hub-admin .admin-topbar__link {
    color: #455a64 !important;
}
.admin-app-body--state-theme-revamp.admin-app-body--hub-admin .admin-topbar--hub .admin-topbar__link-ico {
    color: #607d8b !important;
}
.admin-app-body--state-theme-revamp.admin-app-body--hub-admin .admin-topbar--hub .admin-topbar__link:hover {
    color: #00897b !important;
    background: #e0f2f1 !important;
    border-color: #b2dfdb !important;
}
.admin-app-body--state-theme-revamp.admin-app-body--hub-admin .admin-topbar--hub .admin-topbar__link:hover .admin-topbar__link-ico {
    color: #26a69a !important;
}
.admin-app-body--state-theme-revamp.admin-app-body--hub-admin .admin-topbar--hub .admin-topbar__link.is-active,
.admin-app-body--state-theme-revamp.admin-app-body--hub-admin .admin-topbar--hub .admin-topbar__link.is-active.admin-topbar__dropdown-trigger {
    color: #1565c0 !important;
    background: #e3f2fd !important;
    border-color: #90caf9 !important;
    box-shadow: none !important;
}
.admin-app-body--state-theme-revamp.admin-app-body--hub-admin .admin-topbar--hub .admin-topbar__link.is-active .admin-topbar__link-ico {
    color: #1976d2 !important;
}
.admin-app-body--state-theme-revamp.admin-app-body--hub-admin .admin-brand--hub .admin-brand__logo-wrap {
    background: #fff !important;
    border-color: #e8ecf1 !important;
    box-shadow: 0 2px 10px rgba(55, 71, 79, 0.1) !important;
}
.admin-app-body--state-theme-revamp.admin-app-body--hub-admin .admin-brand--hub .admin-brand__eyebrow {
    color: #78909c !important;
}
.admin-app-body--state-theme-revamp.admin-app-body--hub-admin .admin-brand--hub .admin-brand__name {
    color: #263238 !important;
    -webkit-text-fill-color: #263238 !important;
    background: none !important;
}
.admin-app-body--state-theme-revamp.admin-app-body--hub-admin .admin-brand--hub .admin-brand__hub {
    color: #546e7a !important;
}
.admin-app-body--state-theme-revamp.admin-app-body--hub-admin .admin-topbar--hub .admin-topbar__notif-summary,
.admin-app-body--state-theme-revamp.admin-app-body--hub-admin .admin-topbar--hub .admin-topbar__settings {
    border-color: #e8ecf1 !important;
    background: #f5f7fa !important;
    color: #26a69a !important;
}
.admin-app-body--state-theme-revamp.admin-app-body--hub-admin .admin-topbar--hub .admin-topbar__theme-toggle {
    border-color: #e8ecf1 !important;
    background: #fff !important;
    color: #00897b !important;
}
.admin-app-body--state-theme-revamp.admin-app-body--hub-admin .admin-topbar--hub .admin-topbar__theme-toggle:hover {
    background: #e0f2f1 !important;
    border-color: #80cbc4 !important;
    color: #00695c !important;
}

/* District staff revamp — scroll rail + readable links */
.admin-app-body--state-theme-revamp.admin-app-body--staff-premium .admin-topbar--staff {
    background: #ffffff !important;
    border-bottom: 1px solid #e8ecf1 !important;
    box-shadow: 0 1px 8px rgba(55, 71, 79, 0.05) !important;
}
.admin-app-body--state-theme-revamp.admin-app-body--staff-premium .admin-topbar--staff::before,
.admin-app-body--state-theme-revamp.admin-app-body--staff-premium .admin-topbar--staff::after {
    display: none !important;
}
.admin-app-body--state-theme-revamp.admin-app-body--staff-premium .admin-topbar__nav-rail--staff {
    background: #eef2f6 !important;
    border-color: #dde3ea !important;
}
.admin-app-body--state-theme-revamp.admin-app-body--staff-premium .admin-topbar__nav-rail--staff::before {
    background: linear-gradient(90deg, #eef2f6 30%, rgba(238, 242, 246, 0)) !important;
}
.admin-app-body--state-theme-revamp.admin-app-body--staff-premium .admin-topbar__nav-rail--staff::after {
    background: linear-gradient(270deg, #eef2f6 30%, rgba(238, 242, 246, 0)) !important;
}
.admin-app-body--state-theme-revamp.admin-app-body--staff-premium .admin-topbar__nav--staff .admin-topbar__link {
    color: #455a64 !important;
}
.admin-app-body--state-theme-revamp.admin-app-body--staff-premium .admin-topbar__nav--staff .admin-topbar__link-ico {
    color: #607d8b !important;
}
.admin-app-body--state-theme-revamp.admin-app-body--staff-premium .admin-topbar__nav--staff .admin-topbar__link:hover {
    color: #00897b !important;
    background: #e0f2f1 !important;
}
.admin-app-body--state-theme-revamp.admin-app-body--staff-premium .admin-topbar__nav--staff .admin-topbar__link.is-active,
.admin-app-body--state-theme-revamp.admin-app-body--staff-premium .admin-topbar__nav--staff .admin-topbar__link.is-active.admin-topbar__dropdown-trigger {
    color: #1565c0 !important;
    background: #e3f2fd !important;
    box-shadow: none !important;
}
.admin-app-body--state-theme-revamp.admin-app-body--staff-premium .admin-topbar__nav--staff .admin-topbar__link.is-active .admin-topbar__link-ico {
    color: #1976d2 !important;
}
.admin-app-body--state-theme-revamp.admin-app-body--staff-premium .admin-brand--staff .admin-brand__logo-wrap {
    border-color: #e8ecf1 !important;
}

.admin-app-body--dash-unified.admin-app-body--state-theme-revamp .sad-unified-strip__title {
    color: #fff;
}
.admin-app-body--dash-unified.admin-app-body--state-theme-revamp .sad-unified-strip__sub {
    color: rgba(255, 255, 255, 0.88);
}
.admin-app-body--dash-unified.admin-app-body--state-theme-revamp .sad-unified-strip__sub strong {
    color: #fff9c4;
}
.admin-app-body--dash-unified.admin-app-body--state-theme-revamp .sad-badge {
    background: rgba(255, 255, 255, 0.18);
    border: 1px solid rgba(255, 255, 255, 0.28);
    color: #fff;
}
.admin-app-body--dash-unified.admin-app-body--state-theme-revamp .sad-theme-toggle {
    background: #fff;
    border-color: rgba(255, 255, 255, 0.5);
    color: #00897b;
    font-weight: 800;
}
.admin-app-body--dash-unified.admin-app-body--state-theme-revamp .sad-theme-toggle:hover {
    background: #e0f7fa;
    color: #00695c;
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

/* KPI — white cards + pastel icon blocks (reference style) */
.admin-app-body--state-theme-revamp .sad-kpi {
    border-radius: 16px;
    padding: 0.8rem 0.9rem;
    border: 1px solid #e8ecf1;
    background: #fff;
    transition: transform 0.18s ease, box-shadow 0.18s ease;
    position: relative;
    overflow: hidden;
    box-shadow: 0 2px 10px rgba(55, 71, 79, 0.04);
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
    box-shadow: 0 12px 28px rgba(55, 71, 79, 0.09);
}
.admin-app-body--state-theme-revamp .sad-kpi__value {
    font-size: 1.32rem;
    font-weight: 800;
    letter-spacing: -0.03em;
    color: var(--sad-navy);
}
.admin-app-body--state-theme-revamp .sad-kpi__icon {
    width: 2.25rem;
    height: 2.25rem;
    border-radius: 12px;
    font-size: 0.9rem;
    box-shadow: none;
}
.admin-app-body--state-theme-revamp .sad-kpi--tone-blue { background: #fff; }
.admin-app-body--state-theme-revamp .sad-kpi--tone-blue::before { background: linear-gradient(90deg, #26a69a, #4db6ac); }
.admin-app-body--state-theme-revamp .sad-kpi--tone-indigo { background: #fff; }
.admin-app-body--state-theme-revamp .sad-kpi--tone-indigo::before { background: linear-gradient(90deg, #ff8a65, #ffab91); }
.admin-app-body--state-theme-revamp .sad-kpi--tone-amber { background: #fff; }
.admin-app-body--state-theme-revamp .sad-kpi--tone-amber::before { background: linear-gradient(90deg, #ffb300, #ffca28); }
.admin-app-body--state-theme-revamp .sad-kpi--tone-violet { background: #fff; }
.admin-app-body--state-theme-revamp .sad-kpi--tone-violet::before { background: linear-gradient(90deg, #f06292, #f48fb1); }
.admin-app-body--state-theme-revamp .sad-kpi--tone-cyan { background: #fff; }
.admin-app-body--state-theme-revamp .sad-kpi--tone-cyan::before { background: linear-gradient(90deg, #42a5f5, #90caf9); }
.admin-app-body--state-theme-revamp .sad-kpi--tone-slate { background: #fff; }
.admin-app-body--state-theme-revamp .sad-kpi--tone-slate::before { background: linear-gradient(90deg, #78909c, #b0bec5); }
.admin-app-body--state-theme-revamp .sad-kpi--tone-teal { background: #fff; }
.admin-app-body--state-theme-revamp .sad-kpi--tone-teal::before { background: linear-gradient(90deg, #66bb6a, #a5d6a7); }
.admin-app-body--state-theme-revamp .sad-kpi--tone-emerald { background: #fff; }
.admin-app-body--state-theme-revamp .sad-kpi--tone-emerald::before { background: linear-gradient(90deg, #ab47bc, #ce93d8); }

.admin-app-body--state-theme-revamp .sad-kpi__icon--green { background: #e0f2f1; color: #00897b; }
.admin-app-body--state-theme-revamp .sad-kpi__icon--teal { background: #fff3e0; color: #ef6c00; }
.admin-app-body--state-theme-revamp .sad-kpi__icon--sky { background: #fff8e1; color: #f9a825; }
.admin-app-body--state-theme-revamp .sad-kpi__icon--amber { background: #fce4ec; color: #d81b60; }
.admin-app-body--state-theme-revamp .sad-kpi__icon--rose { background: #e3f2fd; color: #1e88e5; }
.admin-app-body--state-theme-revamp .sad-kpi__icon--indigo { background: #eceff1; color: #546e7a; }
.admin-app-body--state-theme-revamp .sad-kpi__icon--teal2 { background: #e8f5e9; color: #43a047; }

/* Nav + cards */
.admin-app-body--state-theme-revamp .sad-nav {
    background: #fff;
    border: 1px solid #e8ecf1;
    border-radius: 999px;
    padding: 0.32rem;
    box-shadow: 0 2px 12px rgba(55, 71, 79, 0.05);
}
.admin-app-body--state-theme-revamp .sad-nav__btn.is-active {
    background: linear-gradient(135deg, #26a69a, #4db6ac);
    color: #fff;
    box-shadow: var(--sad-nav-shadow);
}
.admin-app-body--state-theme-revamp .sad-card {
    border-radius: 16px;
    border-color: #e8ecf1;
    box-shadow: 0 2px 12px rgba(55, 71, 79, 0.05);
    background: #fff;
}
.admin-app-body--state-theme-revamp .sad-card__tag {
    background: #e0f2f1;
    color: #00695c;
    border: 1px solid #b2dfdb;
}
.admin-app-body--state-theme-revamp .sad-card__title i {
    color: #26a69a;
}
.admin-app-body--state-theme-revamp .sad-progress-fill--sky {
    background: linear-gradient(90deg, #26a69a, #42a5f5);
}
.admin-app-body--state-theme-revamp .sad-district-card.is-top {
    border-color: #80cbc4;
    background: linear-gradient(180deg, #e0f2f1 0%, #fff 100%);
}
.admin-app-body--state-theme-revamp .sad-alert--info {
    background: #e3f2fd;
    border-color: #90caf9;
    color: #1565c0;
}
.admin-app-body--state-theme-revamp .sad-savings-tile--blue {
    border-color: #90caf9;
    background: #e3f2fd;
}
.admin-app-body--state-theme-revamp .sad-savings-tile--blue .sad-savings-tile__lbl {
    color: #1565c0 !important;
}
.admin-app-body--state-theme-revamp .sad-savings-tile--blue .sad-savings-tile__val {
    color: #0d47a1 !important;
}
.admin-app-body--state-theme-revamp .sad-kpi__icon--sky {
    background: #fff8e1 !important;
    color: #f9a825 !important;
}
.admin-app-body--state-theme-revamp .sad-alert--ok {
    background: #e8f5e9;
    border-color: #a5d6a7;
    color: #2e7d32;
}
.admin-app-body--state-theme-revamp .sad-alert--warn {
    background: #fff8e1;
    border-color: #ffe082;
    color: #f57f17;
}

/* ---- Shell pages (all state-admin routes via layouts.admin) ---- */
.admin-app-body--state-theme-revamp.admin-app-body--dashboard .admin-topbar__inner {
    padding: 0.6rem 1.15rem;
}
.admin-app-body--state-theme-revamp.admin-app-body--dashboard .admin-brand::before {
    display: none;
}
.admin-app-body--state-theme-revamp.admin-app-body--dashboard .admin-brand:hover {
    background: transparent;
    transform: none;
}
.admin-app-body--state-theme-revamp.admin-app-body--dashboard .admin-brand__img {
    box-shadow: 0 2px 10px rgba(55, 71, 79, 0.12);
}
.admin-app-body--state-theme-revamp.admin-app-body--dashboard .admin-topbar__link:hover::before {
    opacity: 0;
    animation: none;
}
.admin-app-body--state-theme-revamp.admin-app-body--dashboard .admin-topbar__link.is-active:not(.admin-topbar__dropdown-trigger)::after {
    display: none;
}
.admin-app-body--state-theme-revamp.admin-app-body--dashboard .admin-topbar__notif-summary:hover {
    transform: none;
    border-color: #b2dfdb;
    box-shadow: 0 4px 12px rgba(38, 166, 154, 0.12);
}
.admin-app-body--state-theme-revamp.admin-app-body--dashboard .admin-topbar__details--notifications[open] .admin-topbar__notif-summary {
    color: #00897b;
    background: #fff;
    border-color: #b2dfdb;
}
.admin-app-body--state-theme-revamp.admin-app-body--dashboard .admin-topbar__profile {
    background: #fff;
    border-color: #e8ecf1;
}
.admin-app-body--state-theme-revamp.admin-app-body--dashboard .admin-topbar__profile-summary:hover .admin-topbar__profile {
    transform: none;
    border-color: #b2dfdb;
    box-shadow: 0 4px 14px rgba(55, 71, 79, 0.08);
}
.admin-app-body--state-theme-revamp.admin-app-body--dashboard .admin-topbar__details--profile[open] .admin-topbar__profile {
    background: #fff;
    border-color: #b2dfdb;
    box-shadow: 0 4px 14px rgba(55, 71, 79, 0.1);
}
.admin-app-body--state-theme-revamp.admin-app-body--dashboard .admin-topbar__user {
    color: #37474f;
}
.admin-app-body--state-theme-revamp.admin-app-body--dashboard .admin-topbar__user-role {
    color: #78909c;
}
.admin-app-body--state-theme-revamp.admin-app-body--dashboard .admin-topbar__avatar {
    background: linear-gradient(135deg, #26a69a, #4db6ac);
    box-shadow: 0 0 0 2px #fff, 0 0 0 3px rgba(38, 166, 154, 0.25);
}
.admin-app-body--state-theme-revamp.admin-app-body--dashboard .admin-topbar__settings {
    background: #f5f7fa;
    border-color: #e8ecf1;
    color: #37474f;
}
.admin-app-body--state-theme-revamp.admin-app-body--dashboard .admin-topbar__settings:hover,
.admin-app-body--state-theme-revamp.admin-app-body--dashboard .admin-topbar__settings.is-active {
    background: #e0f2f1;
    color: #00897b;
    border-color: #b2dfdb;
}
.admin-app-body--state-theme-revamp.admin-app-body--dashboard .admin-topbar__hamburger {
    border-color: #e8ecf1;
    background: #f5f7fa;
    color: #37474f;
}
.admin-app-body--state-theme-revamp .admin-page-head h1 {
    color: #263238;
    font-weight: 800;
}
.admin-app-body--state-theme-revamp .admin-page-head .admin-page-meta {
    color: #78909c;
}
.admin-app-body--state-theme-revamp .admin-page-head .pill {
    background: #e0f2f1;
    color: #00695c;
    border: 1px solid #b2dfdb;
}
.admin-app-body--state-theme-revamp .admin-main .banner:not(.banner--warning) {
    background: #e8f5e9;
    border-color: #a5d6a7;
    color: #2e7d32;
}

/* Phase 1/2/3 list pages (p1l-* partials) */
.admin-app-body--state-theme-revamp .p1l-page {
    --p1l-indigo: #26a69a;
}
.admin-app-body--state-theme-revamp .p1l-hero {
    border-color: #e8ecf1;
    background: linear-gradient(135deg, #e0f2f1 0%, #f0f4f8 55%, #fff 100%);
}
.admin-app-body--state-theme-revamp .p1l-badge--fy {
    background: #00897b;
    color: #e0f2f1;
}
.admin-app-body--state-theme-revamp .p1l-badge--district {
    border-color: #b2dfdb;
    color: #00695c;
}
.admin-app-body--state-theme-revamp .p1l-stat--geo {
    border-color: #b2dfdb;
    background: linear-gradient(180deg, #e0f2f1 0%, #fff 100%);
}
.admin-app-body--state-theme-revamp .p1l-stat--geo .p1l-stat__value {
    color: #00897b;
}
.admin-app-body--state-theme-revamp .p1l-appno {
    color: #00897b;
}
.admin-app-body--state-theme-revamp .p1l-input:focus,
.admin-app-body--state-theme-revamp .p1l-select:focus {
    border-color: #26a69a;
    box-shadow: 0 0 0 3px rgba(38, 166, 154, 0.15);
}
.admin-app-body--state-theme-revamp .p1l-btn--primary {
    background: linear-gradient(135deg, #00897b, #26a69a);
}
.admin-app-body--state-theme-revamp .p1l-btn--primary:hover {
    background: linear-gradient(135deg, #00695c, #00897b);
}
.admin-app-body--state-theme-revamp .p1l-pagination .page-item.active .page-link {
    background: #26a69a;
    border-color: #26a69a;
}
.admin-app-body--state-theme-revamp .p1l-pill--region-kumaon {
    background: #e0f2f1;
    color: #00695c;
}

.admin-app-body--state-theme-revamp.admin-app-body--dashboard .admin-topbar__theme-toggle {
    margin-right: 0.35rem;
    font-size: 0.68rem;
    padding: 0.32rem 0.55rem;
}
.admin-app-body--state-theme-revamp.admin-app-body--dashboard .admin-topbar__theme-toggle:hover {
    background: #e0f2f1;
    border-color: #80cbc4;
    color: #00695c;
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
