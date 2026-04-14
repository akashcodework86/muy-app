<style>
    :root {
        --text: #0f172a;
        --text-muted: #64748b;
        --shadow: 0 24px 60px rgba(99, 102, 241, 0.12);
        --radius: 24px;
        --border: #e2e8f0;
    }
    .glass-surface {
        backdrop-filter: blur(30px);
        -webkit-backdrop-filter: blur(30px);
        background: linear-gradient(135deg, rgba(255, 255, 255, 0.46) 0%, rgba(255, 255, 255, 0.28) 100%);
        border: 1px solid rgba(255, 255, 255, 0.1);
        box-shadow:
            0 8px 32px rgba(31, 38, 135, 0.08),
            inset 0 1px 0 rgba(255, 255, 255, 0.5);
    }
    .hub-dashboard-shell {
        position: relative;
        max-width: 100%;
        overflow-x: clip;
        box-sizing: border-box;
    }
    .hub-dashboard-shell::before {
        content: '';
        position: absolute;
        inset: 0 0 auto;
        height: 16rem;
        background:
            radial-gradient(circle at 10% 10%, rgba(251, 191, 36, 0.22), transparent 22%),
            radial-gradient(circle at 85% 10%, rgba(59, 130, 246, 0.18), transparent 20%),
            radial-gradient(circle at 55% 40%, rgba(236, 72, 153, 0.12), transparent 24%);
        pointer-events: none;
        z-index: 0;
    }
    .hub-dashboard-shell > * { position: relative; z-index: 1; }

    .hub-hero-three-col {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 0.85rem;
        margin-bottom: 1.25rem;
        align-items: stretch;
        width: 100%;
        min-width: 0;
        box-sizing: border-box;
    }
    @media (max-width: 1300px) {
        .hub-hero-three-col { grid-template-columns: 1fr; }
    }
    .hub-hero-col {
        border-radius: 16px;
        padding: 0.95rem 1rem;
        display: flex;
        flex-direction: column;
        min-width: 0;
        max-width: 100%;
    }
    .hub-hero-col--welcome {
        background: linear-gradient(145deg, rgba(255, 255, 255, 0.42) 0%, rgba(245, 243, 255, 0.2) 100%);
    }
    .hub-hero-col--metrics {
        background: linear-gradient(145deg, rgba(255, 255, 255, 0.42) 0%, rgba(240, 249, 255, 0.22) 100%);
    }
    .hub-hero-col__title {
        font-size: 0.6rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        color: #7c3aed;
        margin-bottom: 0.75rem;
        display: flex;
        align-items: center;
        gap: 0.35rem;
    }
    .hub-hero-col__title i { font-size: 0.65rem; }
    .hub-welcome-intro h2 {
        font-family: 'DM Sans', sans-serif;
        font-size: 1.15rem;
        font-weight: 800;
        color: #0f172a;
        margin: 0 0 0.5rem;
        line-height: 1.2;
    }
    .hub-welcome-intro p {
        font-size: 0.78rem;
        color: #64748b;
        line-height: 1.55;
        margin: 0 0 0.85rem;
    }
    .hub-welcome-intro strong { color: #0f172a; font-weight: 700; }
    .hub-welcome-meta-pills {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }
    .hub-welcome-meta-pill {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.5rem 0.65rem;
        background: rgba(255, 255, 255, 0.6);
        border: 1px solid rgba(167, 139, 250, 0.2);
        border-radius: 8px;
        font-size: 0.65rem;
        color: #475569;
    }
    .hub-welcome-meta-pill i { color: #7c3aed; font-size: 0.75rem; }
    .hub-welcome-meta-pill a.hub-batch-link {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        margin-left: auto;
        padding: 0.4rem 0.75rem;
        border-radius: 999px;
        background: linear-gradient(135deg, #059669, #14b8a6);
        color: #fff !important;
        font-weight: 700;
        text-decoration: none;
        font-size: 0.72rem;
    }
    .hub-welcome-meta-pill--row { flex-wrap: wrap; justify-content: space-between; }

    .hub-highlight-card__header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 0.35rem;
    }
    .hub-highlight-card__header .label {
        font-size: 0.6rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        color: #64748b;
    }
    .hub-highlight-card__header .label-time {
        font-size: 0.55rem;
        color: #94a3b8;
    }
    .hub-apps-highlight { margin-top: 0.35rem; }
    .hub-apps-highlight__main {
        text-align: center;
        padding: 1rem 0.75rem;
        background: linear-gradient(135deg, rgba(99, 102, 241, 0.08) 0%, rgba(139, 92, 246, 0.08) 100%);
        border-radius: 12px;
        margin-bottom: 0.65rem;
    }
    .hub-apps-highlight__number {
        font-family: 'DM Sans', sans-serif;
        font-size: 2.35rem;
        font-weight: 800;
        line-height: 1;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
        margin-bottom: 0.35rem;
    }
    .hub-apps-highlight__label {
        font-size: 0.65rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: #64748b;
    }
    .hub-progress-bar-wrap {
        background: rgba(226, 232, 240, 0.6);
        border-radius: 999px;
        height: 18px;
        overflow: hidden;
        position: relative;
        box-shadow: inset 0 1px 3px rgba(0, 0, 0, 0.05);
    }
    .hub-progress-bar-fill {
        height: 100%;
        background: linear-gradient(90deg, #667eea 0%, #764ba2 50%, #f093fb 100%);
        border-radius: 999px;
        transition: width 0.6s ease;
        display: flex;
        align-items: center;
        justify-content: flex-end;
        padding-right: 0.5rem;
        min-width: 2.2rem;
    }
    .hub-progress-bar-fill span {
        font-size: 0.58rem;
        font-weight: 800;
        color: #fff;
        text-shadow: 0 1px 2px rgba(0, 0, 0, 0.2);
    }
    .hub-progress-bar-meta {
        margin-top: 0.4rem;
        font-size: 0.6rem;
        color: #64748b;
        text-align: center;
        font-weight: 600;
    }

    .hub-stage-row {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 0.45rem;
        margin-top: 0.65rem;
    }
    .hub-stage-pill {
        border-radius: 12px;
        padding: 0.55rem 0.45rem;
        text-align: center;
        color: #fff;
        font-size: 0.58rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }
    .hub-stage-pill--seed { background: linear-gradient(135deg, #064e3b, #047857); }
    .hub-stage-pill--early { background: linear-gradient(135deg, #0c4a6e, #0369a1); }
    .hub-stage-pill--growth { background: linear-gradient(135deg, #3b0764, #6d28d9); }
    .hub-stage-pill .n { display: block; font-size: 1.25rem; font-weight: 800; margin-top: 0.2rem; letter-spacing: -0.02em; }

    .hub-target-strip {
        border-radius: 18px;
        padding: 1.25rem 1.35rem;
        margin-bottom: 1.25rem;
        background: linear-gradient(120deg, rgba(30, 27, 75, 0.92) 0%, rgba(49, 46, 129, 0.9) 50%, rgba(30, 58, 95, 0.92) 100%);
        color: #e0e7ff;
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        box-shadow: 0 12px 40px rgba(30, 27, 75, 0.35);
        border: 1px solid rgba(255, 255, 255, 0.12);
    }
    .hub-target-strip h3 { margin: 0; font-size: 1rem; font-weight: 600; opacity: 0.92; }
    .hub-target-strip .big { font-family: 'DM Sans', sans-serif; font-size: 2rem; font-weight: 700; margin: 0.25rem 0 0; }
    .hub-target-strip .meta { font-size: 0.85rem; opacity: 0.78; max-width: 26rem; line-height: 1.45; }
    .hub-target-strip .progress-wrap { width: min(100%, 320px); }
    .hub-target-strip .bar { height: 10px; background: rgba(255,255,255,0.15); border-radius: 999px; overflow: hidden; margin-top: 0.75rem; }
    .hub-target-strip .fill { height: 100%; border-radius: 999px; background: linear-gradient(90deg, #34d399, #6ee7b7); transition: width 0.6s ease; }

    .hub-charts-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 1.25rem;
        margin-bottom: 1.25rem;
    }
    @media (max-width: 1100px) { .hub-charts-grid { grid-template-columns: 1fr; } }
    .hub-chart-card {
        background: #fff;
        border-radius: 14px;
        border: 1px solid var(--border);
        box-shadow: 0 4px 24px rgba(15, 23, 42, 0.08);
        padding: 1.15rem 1.25rem 1.35rem;
    }
    .hub-chart-card h4 { margin: 0 0 0.15rem; font-size: 0.95rem; font-weight: 700; font-family: 'DM Sans', sans-serif; color: var(--text); }
    .hub-chart-card .hint { font-size: 0.78rem; color: var(--text-muted); margin-bottom: 0.75rem; }
    .hub-chart-card .canvas-wrap { position: relative; height: 260px; }
    .hub-chart-card.tall .canvas-wrap { height: 300px; }

    .hub-foot-note {
        background: rgba(255, 255, 255, 0.75);
        border: 1px solid var(--border);
        border-radius: 14px;
        padding: 1rem 1.25rem;
        font-size: 0.9rem;
        color: var(--text-muted);
        line-height: 1.5;
        box-shadow: 0 4px 24px rgba(15, 23, 42, 0.06);
    }
</style>
