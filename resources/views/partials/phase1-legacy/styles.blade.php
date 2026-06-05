<style>
    .p1l-page { --p1l-indigo: #4f46e5; --p1l-slate-50: #f8fafc; font-family: inherit; }
    .p1l-banner {
        margin-bottom: 1rem;
        padding: 0.75rem 1rem;
        border-radius: 0.75rem;
        border: 1px solid #fde68a;
        background: #fffbeb;
        color: #92400e;
        font-size: 0.875rem;
        line-height: 1.45;
    }
    .p1l-banner code { font-size: 0.8em; background: rgba(255,255,255,0.6); padding: 0.1em 0.35em; border-radius: 4px; }
    .p1l-alert { margin-bottom: 1rem; padding: 0.75rem 1rem; border-radius: 0.75rem; font-size: 0.875rem; }
    .p1l-alert--warn { border: 1px solid #fdba74; background: #fff7ed; color: #9a3412; }
    .p1l-alert--err { border: 1px solid #fecaca; background: #fef2f2; color: #b91c1c; }
    .p1l-hero {
        display: flex; flex-wrap: wrap; align-items: flex-start; justify-content: space-between;
        gap: 1rem; margin-bottom: 1rem; padding: 1.1rem 1.25rem;
        border-radius: 1rem; border: 1px solid #e2e8f0; background: linear-gradient(135deg, #eef2ff 0%, #f8fafc 55%, #fff 100%);
    }
    .p1l-hero__title { margin: 0; font-size: 1.15rem; font-weight: 800; color: #0f172a; }
    .p1l-hero__sub { margin: 0.35rem 0 0; font-size: 0.875rem; color: #64748b; max-width: 42rem; line-height: 1.5; }
    .p1l-hero__badges { display: flex; flex-wrap: wrap; gap: 0.4rem; align-items: center; }
    .p1l-badge {
        display: inline-flex; align-items: center; padding: 0.25rem 0.65rem;
        border-radius: 999px; font-size: 0.72rem; font-weight: 700; letter-spacing: 0.02em;
    }
    .p1l-badge--fy { background: #312e81; color: #e0e7ff; }
    .p1l-badge--district { background: #fff; border: 1px solid #c7d2fe; color: #3730a3; }
    .p1l-badge--hub { background: #ecfdf5; border: 1px solid #a7f3d0; color: #047857; }
    .p1l-stats {
        display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
        gap: 0.65rem; margin-bottom: 1rem;
    }
    .p1l-stat {
        padding: 0.75rem 0.9rem; border-radius: 0.75rem; border: 1px solid #e2e8f0;
        background: #fff; box-shadow: 0 1px 2px rgba(15,23,42,0.04);
    }
    .p1l-stat__label { font-size: 0.68rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; color: #94a3b8; }
    .p1l-stat__value { margin-top: 0.2rem; font-size: 1.35rem; font-weight: 800; color: #0f172a; line-height: 1.1; }
    .p1l-stat__hint { margin-top: 0.15rem; font-size: 0.72rem; color: #64748b; }
    .p1l-stat--onboard-yes { border-color: #a7f3d0; background: linear-gradient(180deg, #ecfdf5 0%, #fff 100%); }
    .p1l-stat--onboard-yes .p1l-stat__value { color: #047857; }
    .p1l-stat--onboard-no { border-color: #e2e8f0; background: linear-gradient(180deg, #f8fafc 0%, #fff 100%); }
    .p1l-stat--onboard-no .p1l-stat__value { color: #475569; }
    .p1l-stat--geo { border-color: #c7d2fe; background: linear-gradient(180deg, #eef2ff 0%, #fff 100%); }
    .p1l-stat--geo .p1l-stat__value { color: #4338ca; }
    .p1l-stat--onboard-fy { grid-column: span 1; min-width: 11rem; }
    @media (min-width: 720px) {
        .p1l-stat--onboard-fy { grid-column: span 2; }
    }
    .p1l-stat__breakdown {
        margin: 0.45rem 0 0; padding: 0; list-style: none;
        display: flex; flex-wrap: wrap; gap: 0.35rem 0.75rem;
        font-size: 0.78rem; font-weight: 600; color: #047857;
    }
    .p1l-stat__phase {
        display: inline-block; font-size: 0.65rem; font-weight: 700;
        text-transform: uppercase; letter-spacing: 0.04em; color: #64748b;
        margin-right: 0.2rem;
    }
    .p1l-filters {
        margin-bottom: 1rem; padding: 1rem; border-radius: 1rem;
        border: 1px solid #e2e8f0; background: #fff; box-shadow: 0 1px 2px rgba(15,23,42,0.04);
    }
    .p1l-filters__row { display: flex; flex-wrap: wrap; align-items: flex-end; gap: 0.75rem; }
    .p1l-filters__grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(11.5rem, 1fr));
        gap: 0.75rem 0.85rem;
        align-items: end;
    }
    .p1l-field--wide { grid-column: 1 / -1; }
    @media (min-width: 900px) {
        .p1l-field--wide { grid-column: span 2; }
    }
    .p1l-filters__actions {
        grid-column: 1 / -1;
        display: flex; flex-wrap: wrap; gap: 0.5rem; padding-top: 0.15rem;
    }
    .p1l-field { display: flex; flex-direction: column; gap: 0.3rem; min-width: 10rem; }
    .p1l-field--grow { flex: 1; min-width: 12rem; }
    .p1l-label { font-size: 0.7rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b; }
    .p1l-input, .p1l-select {
        padding: 0.5rem 0.65rem; border: 1px solid #cbd5e1; border-radius: 0.5rem;
        font-size: 0.875rem; color: #334155; background: #fff; width: 100%;
    }
    .p1l-input:focus, .p1l-select:focus { outline: none; border-color: var(--p1l-indigo); box-shadow: 0 0 0 3px rgba(79,70,229,0.15); }
    .p1l-btn {
        padding: 0.5rem 1rem; border-radius: 0.5rem; font-size: 0.875rem; font-weight: 600;
        cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; border: none;
    }
    .p1l-btn--primary { background: var(--p1l-indigo); color: #fff; }
    .p1l-btn--primary:hover { background: #4338ca; }
    .p1l-btn--ghost { background: #fff; color: #475569; border: 1px solid #cbd5e1; }
    .p1l-btn--ghost:hover { background: #f8fafc; }
    .p1l-toolbar {
        display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between;
        gap: 0.5rem; margin-bottom: 0.65rem; font-size: 0.82rem; color: #64748b;
    }
    .p1l-toolbar strong { color: #0f172a; }
    .p1l-table-wrap {
        overflow-x: auto; border-radius: 1rem; border: 1px solid #e2e8f0;
        background: #fff; box-shadow: 0 1px 3px rgba(15,23,42,0.06);
    }
    .p1l-table { width: 100%; border-collapse: collapse; font-size: 0.84rem; }
    .p1l-table thead tr { background: #f8fafc; text-align: left; }
    .p1l-table th {
        padding: 0.6rem 0.75rem; border-bottom: 1px solid #e2e8f0;
        font-size: 0.68rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; color: #64748b;
        white-space: nowrap;
    }
    .p1l-table td { padding: 0.55rem 0.75rem; border-bottom: 1px solid #f1f5f9; color: #334155; vertical-align: top; }
    .p1l-table tbody tr:hover { background: #f8fafc; }
    .p1l-table tbody tr:last-child td { border-bottom: none; }
    .p1l-sr {
        font-variant-numeric: tabular-nums; font-weight: 700; color: #94a3b8;
        white-space: nowrap; min-width: 3.5rem; text-align: right;
    }
    .p1l-appno { font-weight: 700; color: #3730a3; white-space: nowrap; }
    .p1l-name { font-weight: 600; color: #0f172a; }
    .p1l-muted { font-size: 0.72rem; color: #94a3b8; }
    .p1l-pill {
        display: inline-block; padding: 0.12rem 0.45rem; border-radius: 999px;
        font-size: 0.72rem; font-weight: 600; background: #f1f5f9; color: #475569;
    }
    .p1l-pill--region-garhwal { background: #ecfdf5; color: #047857; }
    .p1l-pill--region-kumaon { background: #eff6ff; color: #1d4ed8; }
    .p1l-pill--onboard-yes { background: #d1fae5; color: #065f46; font-weight: 700; }
    .p1l-pill--onboard-no { background: #f1f5f9; color: #64748b; font-weight: 600; }
    .p1l-empty { padding: 2.5rem 1rem; text-align: center; color: #94a3b8; }
    .p1l-pagination { margin-top: 1rem; }
    .p1l-pagination nav { display: flex; justify-content: center; }
    .p1l-pagination .pagination { display: flex; gap: 0.25rem; list-style: none; padding: 0; margin: 0; flex-wrap: wrap; justify-content: center; }
    .p1l-pagination .page-link {
        padding: 0.4rem 0.7rem; border: 1px solid #e2e8f0; border-radius: 0.5rem;
        font-size: 0.82rem; color: #334155; text-decoration: none; background: #fff;
    }
    .p1l-pagination .page-item.active .page-link { background: var(--p1l-indigo); border-color: var(--p1l-indigo); color: #fff; }
    .p1l-pagination .page-item.disabled .page-link { opacity: 0.45; pointer-events: none; }
</style>
