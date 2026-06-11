<style>
    .tp-shell { display:flex; flex-direction:column; gap:1.25rem; }
    .tp-alert { border-radius:12px; padding:0.85rem 1rem; font-size:0.88rem; }
    .tp-alert--error { background:#fef2f2; border:1px solid #fca5a5; color:#991b1b; }
    .tp-alert--error ul { margin:0.35rem 0 0 1rem; }
    .tp-card { background:#fff; border:1px solid #e2e8f0; border-radius:14px; padding:1.25rem 1.35rem; }
    .tp-card__title { margin:0 0 1.15rem; font-size:1rem; font-weight:700; color:#0f172a; }
    .tp-section { margin-top:1.35rem; }
    .tp-section__label { margin:0 0 0.65rem; font-size:0.72rem; font-weight:700; letter-spacing:0.08em; text-transform:uppercase; color:#64748b; }
    .tp-grid { display:grid; grid-template-columns:repeat(2, minmax(0, 1fr)); gap:1rem 1.1rem; align-items:start; }
    .tp-field { display:flex; flex-direction:column; gap:0.4rem; min-width:0; }
    .tp-field--full { grid-column:1 / -1; }
    .tp-field label { font-size:0.82rem; font-weight:700; color:#0f172a; }
    .tp-field input, .tp-field select, .tp-field textarea { width:100%; box-sizing:border-box; border:1px solid #cbd5e1; border-radius:8px; padding:0.58rem 0.7rem; font-size:0.88rem; background:#fff; }
    .tp-field textarea { min-height:5.5rem; resize:vertical; }
    .tp-readonly { background:#f8fafc; color:#64748b; }
    .tp-field-hint { margin:0.2rem 0 0; color:#64748b; font-size:0.78rem; }
    .tp-actions { margin-top:1.25rem; display:flex; flex-wrap:wrap; gap:0.65rem; }
    .tp-submit { border:none; border-radius:8px; background:#4f46e5; color:#fff; padding:0.62rem 1rem; font-weight:700; cursor:pointer; }
    .tp-link { color:#4f46e5; font-weight:700; text-decoration:none; font-size:0.88rem; }
    .tp-req { color:#e11d48; }
</style>
