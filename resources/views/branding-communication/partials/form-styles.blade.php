<style>
    .bc-shell { display:flex; flex-direction:column; gap:1.25rem; }
    .bc-alert { border-radius:12px; padding:0.85rem 1rem; font-size:0.88rem; }
    .bc-alert--warning { background:#fffbeb; border:1px solid #fde68a; color:#92400e; }
    .bc-alert--success { background:#f0fdf4; border:1px solid #86efac; color:#166534; }
    .bc-alert--error { background:#fef2f2; border:1px solid #fca5a5; color:#991b1b; }
    .bc-alert--error ul { margin:0.35rem 0 0 1rem; }
    .bc-card { background:#fff; border:1px solid #e2e8f0; border-radius:14px; padding:1.25rem 1.35rem; max-width:56rem; }
    .bc-card__title { margin:0 0 1rem; font-size:1rem; font-weight:700; color:#0f172a; }
    .bc-grid { display:grid; grid-template-columns:repeat(2, minmax(0, 1fr)); gap:1rem 1.1rem; }
    .bc-field { display:flex; flex-direction:column; gap:0.4rem; min-width:0; }
    .bc-field--full { grid-column:1 / -1; }
    .bc-field label { font-size:0.82rem; font-weight:700; color:#0f172a; }
    .bc-req { color:#b91c1c; }
    .bc-field input, .bc-field select, .bc-field textarea {
        width:100%; box-sizing:border-box; border:1px solid #cbd5e1; border-radius:8px; padding:0.58rem 0.7rem; font-size:0.88rem;
    }
    .bc-field textarea { min-height:4.5rem; resize:vertical; }
    .bc-readonly { background:#f8fafc; color:#64748b; }
    .bc-hint { margin:0.2rem 0 0; color:#64748b; font-size:0.78rem; }
    .bc-actions { margin-top:1.1rem; display:flex; flex-wrap:wrap; gap:0.65rem; }
    .bc-submit { border:none; border-radius:8px; background:#7c3aed; color:#fff; padding:0.62rem 1rem; font-weight:700; cursor:pointer; }
    .bc-link { color:#7c3aed; font-weight:700; text-decoration:none; }
    .bc-search { width:100%; box-sizing:border-box; border:1px solid #cbd5e1; border-radius:8px; padding:0.58rem 0.7rem; font-size:0.88rem; }
    .bc-picker { display:grid; grid-template-columns:minmax(0,1fr) minmax(0,1fr); gap:0.85rem; margin-top:0.45rem; }
    .bc-picker__col { border:1px solid #e2e8f0; border-radius:12px; background:#f8fafc; display:flex; flex-direction:column; min-width:0; }
    .bc-picker__head { display:flex; justify-content:space-between; padding:0.55rem 0.75rem; border-bottom:1px solid #e2e8f0; font-size:0.76rem; font-weight:800; color:#475569; text-transform:uppercase; }
    .bc-picker__count { font-size:0.72rem; color:#7c3aed; background:#f3e8ff; border-radius:999px; padding:0.12rem 0.5rem; text-transform:none; }
    .bc-picker__body { padding:0.35rem; }
    .bc-picker__body--results, .bc-picker__body--detail { max-height:20rem; overflow:auto; }
    .bc-picker__empty { padding:1rem 0.75rem; font-size:0.84rem; color:#64748b; }
    .bc-result { display:block; width:100%; text-align:left; border:1px solid transparent; background:#fff; cursor:pointer; padding:0.62rem 0.68rem; border-radius:10px; margin-bottom:0.35rem; }
    .bc-result:hover, .bc-result.is-hover { border-color:#ddd6fe; background:#f5f3ff; }
    .bc-result.is-selected { border-color:#7c3aed; background:#ede9fe; }
    .bc-result__name { font-size:0.86rem; font-weight:700; color:#0f172a; }
    .bc-result__meta { margin-top:0.28rem; font-size:0.76rem; color:#64748b; }
    .bc-pill { display:inline-flex; padding:0.12rem 0.45rem; border-radius:999px; font-size:0.68rem; font-weight:800; background:#f3e8ff; color:#6d28d9; margin-right:0.25rem; }
    .bc-pill--ok { background:#dcfce7; color:#166534; }
    .bc-pill--muted { background:#f1f5f9; color:#475569; }
    .bc-detail { padding:0.65rem 0.75rem; }
    .bc-detail__title { margin:0 0 0.65rem; font-size:0.95rem; font-weight:800; }
    .bc-detail__grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:0.55rem; }
    .bc-detail__label { font-size:0.72rem; font-weight:700; color:#64748b; text-transform:uppercase; }
    .bc-detail__value { margin-top:0.15rem; font-size:0.84rem; }
    .bc-table-wrap { overflow:auto; border:1px solid #e2e8f0; border-radius:14px; background:#fff; }
    .bc-table { width:100%; border-collapse:collapse; font-size:0.84rem; }
    .bc-table th, .bc-table td { padding:0.7rem 0.75rem; border-bottom:1px solid #e2e8f0; text-align:left; vertical-align:top; }
    .bc-table thead tr { background:#f8fafc; }
    .bc-table .bc-serial { width:2.5rem; color:#64748b; font-weight:700; }
    .bc-filters { display:grid; grid-template-columns:repeat(auto-fit, minmax(150px, 1fr)); gap:0.85rem; align-items:end; margin-bottom:1rem; }
    .bc-btn { border:none; border-radius:8px; background:#7c3aed; color:#fff; padding:0.58rem 0.9rem; font-weight:700; text-decoration:none; display:inline-flex; font-size:0.88rem; }
    .bc-btn--secondary { background:#fff; color:#334155; border:1px solid #cbd5e1; }
    .bc-media-preview { display:flex; flex-wrap:wrap; gap:0.5rem; margin-top:0.5rem; }
    .bc-media-thumb { width:72px; height:72px; object-fit:cover; border-radius:8px; border:1px solid #e2e8f0; }
    @media (max-width:720px) { .bc-grid, .bc-picker, .bc-detail__grid { grid-template-columns:1fr; } }
</style>
