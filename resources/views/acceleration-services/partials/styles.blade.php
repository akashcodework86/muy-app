@once
@push('styles')
<style>
    .accel-shell { display:flex; flex-direction:column; gap:1.35rem; width:100%; max-width:100%; overflow:visible; }
    .accel-entry-layout {
        display:grid;
        grid-template-columns:minmax(0, 75%) minmax(0, 25%);
        gap:1rem;
        align-items:start;
        width:100%;
        overflow:visible;
    }
    .accel-form-wrap { width:100%; max-width:100%; min-width:0; }
    /* admin-main uses overflow-x:clip which breaks position:sticky — restore for this page */
    body:has(.accel-entry-layout) .admin-main {
        overflow: visible;
    }
    .accel-ticked-sidebar {
        min-width:0;
        position:sticky;
        top:5.5rem;
        z-index:40;
        align-self:start;
        height:fit-content;
        max-height:calc(100vh - 6.25rem);
    }
    .accel-ticked-sidebar__inner {
        background:#fff;
        border:1px solid #e2e8f0;
        border-radius:14px;
        padding:0.95rem 1rem;
        box-shadow:0 1px 2px rgba(15,23,42,0.04);
        max-height:calc(100vh - 6.25rem);
        overflow-x:hidden;
        overflow-y:auto;
    }
    .accel-ticked-sidebar__title {
        margin:0;
        font-size:0.95rem;
        font-weight:800;
        color:#0f172a;
    }
    .accel-ticked-sidebar__sub {
        margin:0.25rem 0 0.75rem;
        font-size:0.75rem;
        color:#64748b;
        line-height:1.4;
    }
    .accel-alert { border-radius:12px; padding:0.85rem 1rem; font-size:0.88rem; line-height:1.45; }
    .accel-alert--warning { background:#fffbeb; border:1px solid #fde68a; color:#92400e; }
    .accel-alert--success { background:#f0fdf4; border:1px solid #86efac; color:#166534; }
    .accel-alert--error { background:#fef2f2; border:1px solid #fca5a5; color:#991b1b; }
    .accel-alert--error ul { margin:0.35rem 0 0 1rem; }
    .accel-alert--info {
        background:linear-gradient(135deg, #eff6ff 0%, #f0fdfa 100%);
        border:1px solid #bfdbfe;
        border-left:4px solid #0d9488;
        color:#1e3a5f;
    }

    .accel-stats { display:grid; grid-template-columns:repeat(auto-fit, minmax(160px, 1fr)); gap:0.75rem; }
    .accel-stat {
        background:#fff;
        border:1px solid #e2e8f0;
        border-radius:14px;
        padding:0.95rem 1.05rem;
        box-shadow:0 1px 2px rgba(15,23,42,0.04);
        position:relative;
        overflow:hidden;
    }
    .accel-stat::before {
        content:'';
        position:absolute;
        top:0; left:0; right:0;
        height:3px;
        background:linear-gradient(90deg, #0d9488, #14b8a6);
        opacity:0.85;
    }
    .accel-stat__label { font-size:0.7rem; font-weight:700; text-transform:uppercase; letter-spacing:0.06em; color:#64748b; }
    .accel-stat__value { margin-top:0.35rem; font-size:1.5rem; font-weight:800; color:#0f172a; letter-spacing:-0.02em; }

    .accel-card {
        background:#fff;
        border:1px solid #e2e8f0;
        border-radius:16px;
        padding:1.35rem 1.45rem;
        box-shadow:0 1px 3px rgba(15,23,42,0.05), 0 4px 14px rgba(15,23,42,0.03);
    }
    .accel-card--form {
        border-color:#cbd5e1;
        border-top:3px solid #0d9488;
    }
    .accel-card__head { margin-bottom:1.15rem; }
    .accel-card__title { margin:0 0 0.35rem; font-size:1.05rem; font-weight:700; color:#0f172a; letter-spacing:-0.01em; }
    .accel-card__sub { margin:0; font-size:0.84rem; color:#64748b; line-height:1.5; }
    .accel-card__toolbar { display:flex; flex-wrap:wrap; justify-content:space-between; gap:0.65rem; align-items:center; margin-bottom:1rem; }

    .accel-badge { display:inline-block; font-size:0.68rem; font-weight:700; padding:0.15rem 0.45rem; border-radius:999px; }
    .accel-badge--init { background:#dcfce7; color:#166534; }
    .accel-badge--follow { background:#f1f5f9; color:#475569; }

    .accel-status { display:inline-flex; align-items:center; font-size:0.7rem; font-weight:800; padding:0.18rem 0.55rem; border-radius:999px; border:1px solid transparent; white-space:nowrap; }
    .accel-status--draft { background:#f1f5f9; color:#475569; border-color:#cbd5e1; }
    .accel-status--pending_review { background:#fffbeb; color:#92400e; border-color:#fcd34d; }
    .accel-status--pending_final { background:#eff6ff; color:#1d4ed8; border-color:#bfdbfe; }
    .accel-status--approved { background:#dcfce7; color:#166534; border-color:#86efac; }
    .accel-status--sent_back { background:#fef2f2; color:#b91c1c; border-color:#fecaca; }

    .accel-grid { display:grid; grid-template-columns:minmax(0,1fr); gap:1.15rem; align-items:start; }
    .accel-form-top { display:grid; grid-template-columns:1fr; gap:0.75rem; align-items:start; }

    .accel-field { display:flex; flex-direction:column; gap:0.4rem; margin-bottom:0.9rem; }
    .accel-field label { font-size:0.82rem; font-weight:700; color:#0f172a; }
    .accel-field .accel-required { color:#e11d48; }
    .accel-field input[type="text"],
    .accel-field input[type="date"],
    .accel-field input[type="number"],
    .accel-field input[type="url"],
    .accel-field input[type="email"],
    .accel-field input[type="tel"],
    .accel-field input[type="file"],
    .accel-field select,
    .accel-field textarea {
        width:100%;
        box-sizing:border-box;
        border:1px solid #cbd5e1;
        border-radius:10px;
        padding:0.62rem 0.75rem;
        font-size:0.88rem;
        font-family:inherit;
        background:#fff;
        color:#0f172a;
        transition:border-color 0.15s ease, box-shadow 0.15s ease;
    }
    .accel-field input:focus,
    .accel-field select:focus,
    .accel-field textarea:focus {
        outline:none;
        border-color:#14b8a6;
        box-shadow:0 0 0 3px rgba(20,184,166,0.18);
    }
    .accel-field textarea { min-height:4.25rem; resize:vertical; line-height:1.45; }
    .accel-field-hint { margin:0; font-size:0.76rem; color:#64748b; line-height:1.4; }
    .accel-input-affix {
        display:flex;
        align-items:stretch;
        width:100%;
        border:1px solid #cbd5e1;
        border-radius:10px;
        background:#fff;
        overflow:hidden;
        transition:border-color 0.15s ease, box-shadow 0.15s ease;
    }
    .accel-input-affix:focus-within {
        border-color:#14b8a6;
        box-shadow:0 0 0 3px rgba(20,184,166,0.18);
    }
    .accel-input-affix__prefix,
    .accel-input-affix__suffix {
        display:inline-flex;
        align-items:center;
        padding:0 0.7rem;
        background:#f1f5f9;
        color:#475569;
        font-size:0.82rem;
        font-weight:700;
        border-right:1px solid #e2e8f0;
        white-space:nowrap;
    }
    .accel-input-affix__suffix {
        border-right:none;
        border-left:1px solid #e2e8f0;
    }
    .accel-input-affix input {
        border:none !important;
        border-radius:0 !important;
        box-shadow:none !important;
        flex:1;
        min-width:0;
        padding:0.62rem 0.75rem;
    }
    .accel-check-grid {
        display:grid;
        grid-template-columns:repeat(auto-fill, minmax(180px, 1fr));
        gap:0.45rem 0.65rem;
        padding:0.65rem 0.75rem;
        border:1px solid #e2e8f0;
        border-radius:10px;
        background:#f8fafc;
    }
    .accel-check-grid label {
        display:flex;
        align-items:flex-start;
        gap:0.4rem;
        font-size:0.84rem;
        font-weight:500;
        color:#0f172a;
        cursor:pointer;
        line-height:1.35;
        margin:0;
    }
    .accel-check-grid input[type="checkbox"] {
        width:1rem;
        height:1rem;
        margin-top:0.12rem;
        accent-color:#0d9488;
        flex-shrink:0;
        cursor:pointer;
    }

    .accel-support-types {
        display:flex;
        flex-direction:column;
        gap:0.55rem;
        padding:0.65rem 0.75rem;
        border:1px solid #e2e8f0;
        border-radius:10px;
        background:#f8fafc;
    }
    .accel-support-type {
        display:grid;
        grid-template-columns:minmax(12rem, 34%) minmax(0, 1fr);
        gap:0.55rem 0.75rem;
        align-items:start;
        padding:0.45rem 0.55rem;
        border:1px solid transparent;
        border-radius:8px;
    }
    .accel-support-type.is-on {
        background:#fff;
        border-color:#99f6e4;
        box-shadow:0 1px 2px rgba(13,148,136,0.08);
    }
    .accel-support-type__tick {
        display:flex;
        align-items:flex-start;
        gap:0.4rem;
        font-size:0.84rem;
        font-weight:600;
        color:#0f172a;
        cursor:pointer;
        line-height:1.35;
        margin:0;
        padding-top:0.25rem;
    }
    .accel-support-type__tick input[type="checkbox"] {
        width:1rem;
        height:1rem;
        margin-top:0.12rem;
        accent-color:#0d9488;
        flex-shrink:0;
        cursor:pointer;
    }
    .accel-support-type__specify {
        min-width:0;
    }
    .accel-support-type__specify label {
        display:block;
        margin:0 0 0.25rem;
        font-size:0.74rem;
        font-weight:700;
        color:#0f766e;
    }
    .accel-support-type__specify textarea {
        width:100%;
        box-sizing:border-box;
        border:1px solid #cbd5e1;
        border-radius:8px;
        padding:0.45rem 0.55rem;
        font:inherit;
        font-size:0.84rem;
        background:#fff;
        resize:vertical;
        min-height:2.6rem;
    }
    .accel-support-type__specify.is-cond-hidden,
    .accel-support-type__specify[hidden] { display:none !important; }

    @media (max-width: 720px) {
        .accel-support-type { grid-template-columns:1fr; }
    }

    .accel-search-input {
        background:#fff url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='18' height='18' fill='none' stroke='%2394a3b8' stroke-width='2' stroke-linecap='round'%3E%3Ccircle cx='8' cy='8' r='5.5'/%3E%3Cpath d='M12.5 12.5L16 16'/%3E%3C/svg%3E") no-repeat 0.75rem center;
        padding-left:2.35rem !important;
    }

    .accel-block {
        margin:0 0 1rem;
        padding:1rem 1.05rem;
        border:1px solid #cbd5e1;
        border-radius:12px;
        background:#f8fafc;
    }
    .accel-block__title {
        margin:0 0 0.85rem;
        padding-bottom:0.55rem;
        border-bottom:1px solid #e2e8f0;
        font-size:0.88rem;
        font-weight:700;
        color:#0f172a;
    }
    .accel-section__items { display:flex; flex-direction:column; gap:0.55rem; }

    .accel-item {
        border:1px solid #e2e8f0;
        border-radius:10px;
        padding:0.7rem 0.8rem;
        background:#fff;
        transition:border-color 0.15s ease, background 0.15s ease, box-shadow 0.15s ease;
    }
    .accel-item:hover { border-color:#cbd5e1; }
    .accel-item.is-checked {
        border-color:#14b8a6;
        background:#f0fdfa;
        box-shadow:inset 3px 0 0 #0d9488;
    }
    .accel-item__head { display:flex; gap:0.6rem; align-items:flex-start; }
    .accel-item__head input[type="checkbox"] {
        width:1.05rem;
        height:1.05rem;
        margin-top:0.15rem;
        accent-color:#0d9488;
        flex-shrink:0;
        cursor:pointer;
    }
    .accel-item__head label {
        font-size:0.86rem;
        font-weight:600;
        color:#0f172a;
        cursor:pointer;
        line-height:1.4;
        flex:1;
    }
    .accel-item__extra {
        margin-top:0.65rem;
        padding:0.75rem 0.8rem;
        border:1px solid #99f6e4;
        border-radius:10px;
        background:#fff;
        display:none;
    }
    .accel-item.is-checked .accel-item__extra { display:block; }
    .accel-item__schema { display:flex; flex-direction:column; gap:0.15rem; }
    .accel-item__extra .accel-field { margin-bottom:0.65rem; }
    .accel-item__extra .accel-field:last-child { margin-bottom:0; }
    .accel-field.is-cond-hidden,
    .accel-field[hidden] { display:none !important; }
    .accel-radio-row { display:flex; flex-wrap:wrap; gap:0.65rem 1rem; }
    .accel-radio-row label { display:inline-flex; align-items:center; gap:0.3rem; font-size:0.84rem; font-weight:500; cursor:pointer; }

    .accel-media-preview { display:flex; flex-wrap:wrap; gap:0.5rem; margin-top:0.45rem; }
    .accel-media-preview:empty { display:none; }
    .accel-media-preview img { width:76px; height:76px; object-fit:cover; border-radius:10px; border:1px solid #e2e8f0; box-shadow:0 1px 2px rgba(15,23,42,0.06); }
    .accel-media-chip { font-size:0.74rem; background:#fff; border:1px solid #e2e8f0; border-radius:8px; padding:0.3rem 0.5rem; color:#475569; }

    .accel-picker {
        border:1px solid #e2e8f0;
        border-radius:14px;
        background:linear-gradient(180deg, #f8fafc 0%, #fff 100%);
        padding:0.85rem;
        margin-top:0.5rem;
    }
    .accel-picker__grid { display:grid; grid-template-columns:minmax(0,1.05fr) minmax(0,0.95fr); gap:0.85rem; min-height:280px; align-items:stretch; }
    .accel-picker__list { max-height:320px; }
    .accel-picker__col-head {
        margin:0 0 0.5rem;
        font-size:0.72rem;
        font-weight:700;
        color:#64748b;
        text-transform:uppercase;
        letter-spacing:0.06em;
    }
    .accel-picker__list {
        max-height:320px;
        overflow:auto;
        background:#fff;
        border:1px solid #e2e8f0;
        border-radius:12px;
        box-shadow:inset 0 1px 2px rgba(15,23,42,0.03);
    }
    .accel-picker__detail {
        background:#fff;
        border:1px solid #a5b4fc;
        border-radius:12px;
        padding:0.85rem 0.95rem;
        min-height:280px;
        box-shadow:0 2px 8px rgba(79,70,229,0.06);
    }
    .accel-picker__detail-title { margin:0 0 0.55rem; font-size:0.9rem; font-weight:700; color:#312e81; }
    .accel-picker__detail-empty { margin:0; font-size:0.8rem; color:#64748b; line-height:1.45; }
    .accel-ticked-list { display:flex; flex-direction:column; gap:0.4rem; }
    .accel-ticked-chip {
        display:flex;
        gap:0.5rem;
        align-items:flex-start;
        padding:0.5rem 0.6rem;
        border:1px solid #e2e8f0;
        border-radius:8px;
        background:#f8fafc;
        font-size:0.8rem;
        line-height:1.35;
    }
    .accel-ticked-chip__num {
        width:1.25rem;
        height:1.25rem;
        border-radius:999px;
        background:#0d9488;
        color:#fff;
        font-size:0.7rem;
        font-weight:800;
        display:inline-flex;
        align-items:center;
        justify-content:center;
        flex-shrink:0;
        margin-top:0.05rem;
    }
    .accel-ticked-chip__body { display:flex; flex-direction:column; gap:0.1rem; min-width:0; }
    .accel-ticked-chip__body strong { color:#0f172a; font-weight:700; }
    .accel-ticked-chip__section { color:#64748b; font-size:0.72rem; }
    .accel-picker__meta { display:grid; grid-template-columns:repeat(2, minmax(0,1fr)); gap:0.5rem 0.75rem; font-size:0.8rem; margin-bottom:0.75rem; }
    .accel-picker__meta dt { font-weight:700; color:#64748b; font-size:0.68rem; text-transform:uppercase; letter-spacing:0.04em; }
    .accel-picker__meta dd { margin:0.1rem 0 0; color:#0f172a; font-weight:500; }
    .accel-picker__history-title { margin:0.85rem 0 0.45rem; font-size:0.72rem; font-weight:700; letter-spacing:0.06em; text-transform:uppercase; color:#64748b; }
    .accel-picker__history { max-height:220px; overflow:auto; padding-right:0.15rem; }

    .accel-search-item {
        padding:0.65rem 0.75rem;
        border-bottom:1px solid #f1f5f9;
        cursor:pointer;
        font-size:0.82rem;
        transition:background 0.12s ease;
    }
    .accel-search-item:last-child { border-bottom:none; }
    .accel-search-item:hover { background:#f8fafc; }
    .accel-search-item.is-active { background:#eef2ff; border-left:3px solid #6366f1; padding-left:calc(0.75rem - 3px); }
    .accel-search-item.is-selected { background:#ecfdf5; border-left:3px solid #059669; padding-left:calc(0.75rem - 3px); }
    .accel-search-item__name { font-weight:700; color:#0f172a; line-height:1.35; }
    .accel-search-item__meta { margin-top:0.2rem; font-size:0.76rem; color:#64748b; line-height:1.35; }
    .accel-search-item__actions { display:flex; gap:0.4rem; margin-top:0.45rem; }

    .accel-side-entry {
        background:#f8fafc;
        border:1px solid #e2e8f0;
        border-radius:10px;
        padding:0.55rem 0.65rem;
        margin-bottom:0.45rem;
        font-size:0.78rem;
        line-height:1.4;
    }
    .accel-side-entry__date { font-weight:700; color:#0f172a; }
    .accel-side-entry__meta { color:#64748b; margin-top:0.2rem; font-size:0.74rem; }

    .accel-selected {
        margin-top:0.65rem;
        padding:0.65rem 0.8rem;
        background:linear-gradient(135deg, #ecfdf5, #f0fdfa);
        border:1px solid #6ee7b7;
        border-radius:10px;
        font-size:0.84rem;
        color:#065f46;
        line-height:1.45;
    }
    .accel-selected strong { color:#047857; }

    .accel-custom-row { margin-top:0.55rem; }
    .accel-custom-row input {
        width:100%;
        box-sizing:border-box;
        border:1px dashed #cbd5e1;
        border-radius:10px;
        padding:0.55rem 0.7rem;
        font-size:0.84rem;
        background:#fafafa;
        color:#475569;
        font-family:inherit;
    }
    .accel-custom-row input:focus {
        outline:none;
        border-color:#14b8a6;
        border-style:solid;
        background:#fff;
        box-shadow:0 0 0 3px rgba(20,184,166,0.15);
    }

    .accel-form-actions {
        margin-top:1.35rem;
        padding-top:1.1rem;
        border-top:1px solid #e2e8f0;
        display:flex;
        flex-wrap:wrap;
        gap:0.65rem;
        align-items:center;
    }

    .accel-btn {
        border:none;
        border-radius:10px;
        background:linear-gradient(180deg, #0d9488, #0f766e);
        color:#fff;
        padding:0.65rem 1.15rem;
        font-weight:700;
        cursor:pointer;
        font-size:0.88rem;
        text-decoration:none;
        display:inline-flex;
        align-items:center;
        justify-content:center;
        gap:0.35rem;
        font-family:inherit;
        box-shadow:0 1px 2px rgba(15,118,110,0.25);
        transition:transform 0.12s ease, box-shadow 0.12s ease, filter 0.12s ease;
    }
    .accel-btn:hover { filter:brightness(1.05); box-shadow:0 3px 10px rgba(15,118,110,0.28); }
    .accel-btn:active { transform:translateY(1px); }
    .accel-btn--secondary { background:#fff; color:#334155; border:1px solid #cbd5e1; box-shadow:0 1px 2px rgba(15,23,42,0.04); }
    .accel-btn--secondary:hover { background:#f8fafc; filter:none; }
    .accel-btn--xs { padding:0.32rem 0.62rem; font-size:0.74rem; border-radius:8px; box-shadow:none; }
    .accel-btn--ghost { background:#fff; color:#334155; border:1px solid #cbd5e1; box-shadow:none; }
    .accel-btn--ghost:hover { background:#f8fafc; border-color:#94a3b8; filter:none; }
    .accel-btn--add { background:linear-gradient(180deg, #10b981, #059669); border:none; }

    .accel-filter-form { display:grid; grid-template-columns:repeat(auto-fit,minmax(150px,1fr)); gap:0.75rem; margin-bottom:1rem; align-items:end; }
    .accel-filter-form .accel-field { margin:0; }

    .accel-table-wrap { overflow:auto; border:1px solid #e2e8f0; border-radius:12px; }
    .accel-table { width:100%; border-collapse:collapse; font-size:0.84rem; }
    .accel-table th, .accel-table td { text-align:left; padding:0.75rem 0.85rem; border-bottom:1px solid #f1f5f9; vertical-align:top; }
    .accel-table thead tr { background:#f8fafc; }
    .accel-table th { font-size:0.72rem; font-weight:700; text-transform:uppercase; letter-spacing:0.04em; color:#64748b; }
    .accel-table tbody tr:hover { background:#fafafa; }
    .accel-table tbody tr:last-child td { border-bottom:none; }
    .accel-table__empty { color:#64748b; text-align:center; padding:1.5rem !important; }
    .accel-table__num { color:#94a3b8; font-weight:700; font-variant-numeric:tabular-nums; width:3rem; }
    .accel-services-cell { min-width:14rem; max-width:22rem; }
    .accel-services-cell__empty { color:#94a3b8; }
    .accel-services-cell__names {
        margin:0;
        padding:0;
        list-style:none;
        display:flex;
        flex-direction:column;
        gap:0.2rem;
    }
    .accel-services-cell__names li {
        color:#0f172a;
        font-weight:600;
        font-size:0.82rem;
        line-height:1.35;
        padding-left:0.85rem;
        position:relative;
    }
    .accel-services-cell__names li::before {
        content:'';
        position:absolute;
        left:0;
        top:0.45em;
        width:0.35rem;
        height:0.35rem;
        border-radius:999px;
        background:#0d9488;
    }
    .accel-services-cell__cats {
        margin-top:0.4rem;
        font-size:0.72rem;
        color:#64748b;
        font-weight:600;
        line-height:1.35;
    }
    .accel-services-cell__total { color:#94a3b8; font-weight:500; }

    .accel-link { color:#0f766e; font-weight:700; text-decoration:none; }
    .accel-link:hover { text-decoration:underline; }
    .accel-autosave-status { font-size:0.78rem; font-weight:600; color:#0f766e; }
    .accel-media-field--order-proof.is-required-proof {
        border:1px solid #f59e0b;
        background:#fffbeb;
        border-radius:10px;
        padding:0.75rem 0.85rem;
        box-shadow:0 0 0 3px rgba(245,158,11,0.15);
    }
    .accel-media-field--order-proof.is-required-proof label { color:#92400e; }
    .accel-proof-note { color:#b45309; }
    .accel-existing-media { display:flex; flex-wrap:wrap; gap:0.4rem; margin-top:0.45rem; }
    .accel-form-actions { display:flex; flex-wrap:wrap; align-items:center; gap:0.75rem; }

    @media (max-width: 960px) {
        .accel-entry-layout { grid-template-columns:1fr; }
        .accel-ticked-sidebar {
            position:static;
            max-height:none;
            z-index:auto;
        }
        .accel-ticked-sidebar__inner { max-height:none; }
        .accel-picker__grid { grid-template-columns:1fr; }
        .accel-picker__detail { min-height:0; }
        .accel-picker__meta { grid-template-columns:1fr; }
        .accel-check-grid { grid-template-columns:1fr; }
    }
</style>
@endpush
@endonce
