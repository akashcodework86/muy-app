{{-- Shared dashboard layout: unified strip, tabs, panels (state / hub / staff). --}}
.admin-app-body--dash-unified .admin-main {
    padding: 0 clamp(0.75rem, 2vw, 1.35rem) 1.25rem;
}
.sad-unified-strip {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 0.55rem 1rem;
    margin: 0 calc(-1 * clamp(0.75rem, 2vw, 1.35rem)) 0.65rem;
    padding: 0.55rem clamp(0.75rem, 2vw, 1.35rem);
}
.sad-unified-strip__left {
    display: flex;
    flex-wrap: wrap;
    align-items: baseline;
    gap: 0.35rem 0.65rem;
    min-width: 0;
}
.sad-unified-strip__title {
    margin: 0;
    font-size: 1.05rem;
    font-weight: 800;
    letter-spacing: -0.02em;
    line-height: 1.2;
    white-space: nowrap;
}
.sad-unified-strip__sub {
    margin: 0;
    font-size: 0.72rem;
    line-height: 1.35;
    opacity: 0.88;
}
.sad-unified-strip__sub strong { font-weight: 700; }
.sad-unified-strip__meta {
    display: flex;
    flex-wrap: wrap;
    gap: 0.35rem;
    justify-content: flex-end;
    align-items: center;
}
.sad-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.38rem 0.65rem;
    border-radius: 999px;
    background: #f8fafc;
    border: 1px solid var(--sad-border, #e8ecf1);
    font-size: 0.72rem;
    font-weight: 600;
    color: #334155;
    white-space: nowrap;
}
.sad-badge--live::before {
    content: '';
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: #4ade80;
    box-shadow: 0 0 0 3px rgba(74, 222, 128, 0.35);
}
.sad {
    font-family: 'DM Sans', system-ui, sans-serif;
    color: var(--sad-text, #37474f);
    max-width: 100%;
}
.sad-nav {
    display: flex;
    flex-wrap: wrap;
    gap: 0.35rem;
    margin-bottom: 0.6rem;
    padding: 0.35rem;
    background: var(--sad-surface, #fff);
    border: 1px solid var(--sad-border, #e8ecf1);
    border-radius: 12px;
    position: sticky;
    top: 0.5rem;
    z-index: 20;
    box-shadow: 0 8px 24px rgba(15, 23, 42, 0.06);
}
.sad-nav__btn {
    flex: 1;
    min-width: 7rem;
    border: none;
    background: transparent;
    font-family: inherit;
    font-size: 0.78rem;
    font-weight: 700;
    color: var(--sad-muted, #78909c);
    padding: 0.5rem 0.65rem;
    border-radius: 8px;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.4rem;
    transition: background 0.15s, color 0.15s;
}
.sad-nav__btn:hover { background: #f1f5f9; color: var(--sad-text, #37474f); }
.sad-nav__btn.is-active {
    background: var(--sad-brand-grad, linear-gradient(135deg, #00897b, #26a69a));
    color: #fff;
    box-shadow: var(--sad-nav-shadow, 0 4px 14px rgba(38, 166, 154, 0.22));
}
.sad-panel { display: none; animation: sadPanelFade 0.25s ease; }
.sad-panel.is-active { display: block; }
@keyframes sadPanelFade {
    from { opacity: 0; transform: translateY(4px); }
    to { opacity: 1; transform: translateY(0); }
}
.sad-card {
    background: var(--sad-surface, #fff);
    border: 1px solid var(--sad-border, #e8ecf1);
    border-radius: var(--sad-radius, 16px);
    padding: 0.85rem 0.95rem;
    box-shadow: var(--sad-shadow, 0 2px 12px rgba(55, 71, 79, 0.06));
}
.sad-card__head {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 0.35rem;
    margin-bottom: 0.35rem;
}
.sad-card__title {
    margin: 0;
    font-size: 0.88rem;
    font-weight: 800;
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
}
.sad-card__title i { color: var(--sad-brand, #26a69a); font-size: 0.85rem; }
.sad-card__hint {
    margin: 0 0 0.5rem;
    font-size: 0.72rem;
    color: var(--sad-muted, #78909c);
    line-height: 1.4;
}
.sad-card__tag {
    font-size: 0.62rem;
    font-weight: 700;
    padding: 0.2rem 0.45rem;
    border-radius: 999px;
    background: var(--sad-brand-light, #e0f2f1);
    color: var(--sad-brand-deep, #00897b);
}
.sad-signals {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 0.45rem;
}
@media (max-width: 900px) { .sad-signals { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
.sad-signal {
    background: var(--sad-surface, #fff);
    border: 1px solid var(--sad-border, #e8ecf1);
    border-radius: 12px;
    padding: 0.55rem 0.65rem;
    font-size: 0.68rem;
    color: var(--sad-muted, #78909c);
}
.sad-signal strong {
    display: block;
    font-size: 1rem;
    color: var(--sad-text, #37474f);
    margin-top: 0.15rem;
}
.admin-app-body--state-theme-revamp .dashboard-shell::before { display: none; }
.admin-app-body--state-theme-revamp.admin-app-body--dash-unified { background: var(--sad-page-bg, #f0f4f8) !important; }
