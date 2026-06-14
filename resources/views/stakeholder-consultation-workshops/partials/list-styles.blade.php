<style>
    .scw-list-shell, .ldm-list-shell { display:flex; flex-direction:column; gap:1.25rem; }
    .scw-list-alert, .ldm-list-alert { border-radius:12px; padding:0.85rem 1rem; font-size:0.88rem; }
    .scw-list-alert--warning, .ldm-list-alert--warning { background:#fffbeb; border:1px solid #fde68a; color:#92400e; }
    .scw-list-alert--success, .ldm-list-alert--success { background:#f0fdf4; border:1px solid #86efac; color:#166534; }
    .scw-list-hero, .ldm-list-hero { display:flex; flex-wrap:wrap; justify-content:space-between; gap:1rem; padding:1.1rem 1.25rem; border-radius:16px; background:linear-gradient(135deg,#eef2ff 0%,#f8fafc 55%,#ecfdf5 100%); border:1px solid #e2e8f0; }
    .scw-list-hero__title, .ldm-list-hero__title { margin:0; font-size:1rem; font-weight:800; }
    .scw-list-hero__sub, .ldm-list-hero__sub { margin:0.35rem 0 0; font-size:0.84rem; color:#64748b; }
    .scw-list-stat-grid, .ldm-list-stat-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(160px,1fr)); gap:0.85rem; }
    .scw-list-stat, .ldm-list-stat { background:#fff; border:1px solid #e2e8f0; border-radius:14px; padding:0.95rem 1.05rem; }
    .scw-list-stat__label, .ldm-list-stat__label { font-size:0.72rem; color:#64748b; text-transform:uppercase; font-weight:700; }
    .scw-list-stat__value, .ldm-list-stat__value { margin-top:0.35rem; font-size:1.35rem; font-weight:800; }
    .scw-list-card, .ldm-list-card { background:#fff; border:1px solid #e2e8f0; border-radius:14px; padding:1.2rem 1.3rem; }
    .scw-list-filters, .ldm-list-filters { display:grid; grid-template-columns:repeat(auto-fit,minmax(150px,1fr)); gap:0.85rem; align-items:end; margin-bottom:1rem; }
    .scw-list-filter-field label, .ldm-list-filter-field label { display:block; font-size:0.78rem; font-weight:700; margin-bottom:0.35rem; }
    .scw-list-filter-field input, .scw-list-filter-field select, .ldm-list-filter-field input, .ldm-list-filter-field select { width:100%; box-sizing:border-box; border:1px solid #cbd5e1; border-radius:8px; padding:0.55rem 0.65rem; }
    .scw-list-btn, .ldm-list-btn { display:inline-flex; border:none; border-radius:8px; background:#4f46e5; color:#fff; padding:0.58rem 0.95rem; font-weight:700; text-decoration:none; font-size:0.88rem; cursor:pointer; }
    .scw-list-btn--secondary, .ldm-list-btn--secondary { background:#fff; color:#334155; border:1px solid #cbd5e1; }
    .scw-list-table-wrap, .ldm-list-table-wrap { overflow:auto; }
    .scw-list-table, .ldm-list-table { width:100%; border-collapse:collapse; font-size:0.84rem; min-width:860px; }
    .scw-list-table th, .ldm-list-table th { text-align:left; padding:0.72rem 0.75rem; border-bottom:1px solid #e2e8f0; font-size:0.68rem; text-transform:uppercase; color:#64748b; }
    .scw-list-table td, .ldm-list-table td { padding:0.72rem 0.75rem; border-bottom:1px solid #e2e8f0; vertical-align:top; }
    .scw-list-title, .ldm-list-title { font-weight:800; color:#0f172a; }
    .scw-list-empty, .ldm-list-empty { text-align:center; color:#64748b; padding:1.25rem; }
</style>
