@php
    $isLegacy = ($dashboardTheme ?? 'revamp') === 'legacy';
@endphp
{{-- Revamp: institutional slate/navy. Legacy: original orange (revert via ?theme=legacy). --}}
.admin-app-body--state-theme-revamp,
.admin-app-body--state-theme-revamp .sad {
    --sad-text: #0f172a;
    --sad-muted: #64748b;
    --sad-border: #e2e8f0;
    --sad-surface: #ffffff;
    --sad-brand: #1e40af;
    --sad-brand-deep: #1e3a8a;
    --sad-brand-light: #eff6ff;
    --sad-accent: #0f766e;
    --sad-accent-soft: #ecfdf5;
    --sad-green: #16a34a;
    --sad-green-deep: #15803d;
    --sad-teal: #0f766e;
    --sad-sky: #334155;
    --sad-navy: #0f172a;
    --sad-coral: #0369a1;
    --sad-saffron: #0891b2;
    --sad-gold: #ca8a04;
    --sad-radius: 12px;
    --sad-shadow: 0 1px 2px rgba(15, 23, 42, 0.05), 0 8px 24px rgba(30, 64, 175, 0.08);
    --sad-brand-grad: linear-gradient(135deg, #1e3a8a 0%, #1e40af 55%, #0f766e 100%);
    --sad-chart-primary: #1e40af;
    --sad-chart-secondary: #0f766e;
    --sad-chart-fill: rgba(30, 64, 175, 0.18);
}
.admin-app-body--state-theme-revamp.admin-app-body--state-premium {
    background: #f1f5f9 !important;
}
.admin-app-body--state-theme-revamp .admin-topbar {
    background: linear-gradient(135deg, #0f172a 0%, #1e293b 48%, #1e3a8a 100%) !important;
    border-bottom-color: rgba(255, 255, 255, 0.08) !important;
}
.admin-app-body--state-theme-revamp .admin-topbar__brand-sub,
.admin-app-body--state-theme-revamp .admin-topbar__nav-link.is-active {
    color: #e0e7ff !important;
}
.admin-app-body--state-theme-revamp .sad-masthead {
    border-top-color: var(--sad-brand);
    background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
}
.admin-app-body--state-theme-revamp .sad-kpi:hover {
    border-color: #bfdbfe;
    box-shadow: 0 4px 16px rgba(30, 64, 175, 0.1);
}
.admin-app-body--state-theme-revamp .sad-progress-fill--sky {
    background: linear-gradient(90deg, #1e40af, #0f766e);
}
.admin-app-body--state-theme-revamp .sad-district-card.is-top {
    border-color: #93c5fd;
    background: linear-gradient(180deg, #eff6ff 0%, #fff 100%);
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
}
.sad-theme-toggle:hover {
    border-color: var(--sad-brand);
    color: var(--sad-brand);
}
.sad-insights-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 0.55rem;
    margin-top: 0.55rem;
}
.sad-insights-grid--2 {
    grid-template-columns: repeat(2, minmax(0, 1fr));
}
@media (max-width: 1100px) {
    .sad-insights-grid,
    .sad-insights-grid--2 { grid-template-columns: 1fr; }
}
.sad-chart-box--donut { height: 200px; position: relative; }
.sad-chart-box--md { height: 220px; position: relative; }
.sad-kpi__icon--indigo { background: #eef2ff; color: #4338ca; }
.sad-kpi__icon--teal2 { background: #ecfdf5; color: #0f766e; }
