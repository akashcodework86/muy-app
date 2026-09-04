@include('stakeholder-consultation-workshops.partials.list-styles')
<style>
    .mr-shell { display:flex; flex-direction:column; gap:1.15rem; }
    .mr-back {
        display:inline-flex; align-items:center; gap:0.4rem;
        color:#4338ca; font-weight:700; font-size:0.84rem; text-decoration:none;
    }
    .mr-back:hover { text-decoration:underline; }

    .mr-hero {
        position:relative; overflow:hidden;
        border-radius:18px; padding:1.35rem 1.45rem 1.4rem;
        color:#fff;
        background:
            radial-gradient(circle at 92% 0%, rgba(253,224,71,.32), transparent 46%),
            radial-gradient(circle at 8% 110%, rgba(94,234,212,.28), transparent 50%),
            linear-gradient(135deg, #3730a3 0%, #6366f1 48%, #0f766e 100%);
        box-shadow: 0 16px 36px rgba(49,46,129,.18);
    }
    .mr-hero__kicker {
        display:inline-flex; align-items:center; gap:.45rem;
        padding:.28rem .7rem; border-radius:999px;
        background:rgba(255,255,255,.14); border:1px solid rgba(255,255,255,.2);
        color:#fef3c7; font-size:.68rem; font-weight:700; letter-spacing:.1em; text-transform:uppercase;
    }
    .mr-hero__title { margin:.7rem 0 .35rem; font-size:1.45rem; font-weight:800; letter-spacing:-.02em; line-height:1.15; }
    .mr-hero__sub { margin:0; color:rgba(226,232,240,.92); font-size:.88rem; line-height:1.5; }
    .mr-hero--split { display:flex; flex-wrap:wrap; justify-content:space-between; align-items:flex-end; gap:1rem; }
    .mr-hero__copy { max-width:46rem; }
    .mr-hero__actions { display:flex; flex-wrap:wrap; gap:.5rem; align-items:center; }
    .mr-btn--on-dark {
        background:#fff; color:#3730a3; font-weight:800;
        box-shadow:0 8px 18px rgba(15,23,42,.18);
    }
    .mr-btn--on-dark:hover { background:#eef2ff; }
    a.ldm-list-stat {
        text-decoration:none; color:inherit; position:relative; overflow:hidden;
        transition:box-shadow .15s ease, border-color .15s ease;
    }
    a.ldm-list-stat::before {
        content:''; position:absolute; top:0; left:0; right:0; height:3px; background:#c7d2fe;
    }
    a.ldm-list-stat:hover { border-color:#c7d2fe; box-shadow:0 8px 18px rgba(79,70,229,.12); }
    a.ldm-list-stat.is-on { border-color:#818cf8; box-shadow:0 0 0 2px rgba(99,102,241,.25); }
    a.ldm-list-stat.is-on::before { background:#4f46e5; }
    .ldm-list-stat__hint { display:block; margin-top:.2rem; font-size:.7rem; color:#94a3b8; font-weight:600; }
    .mr-table-bar {
        display:flex; flex-wrap:wrap; justify-content:space-between; align-items:center;
        gap:.65rem; margin:0 0 .85rem;
    }
    .mr-table-bar__meta { font-size:.82rem; color:#64748b; font-weight:600; }
    .mr-filter-actions { display:flex; flex-wrap:wrap; gap:.5rem; align-items:center; }
    .mr-hero__chips { display:flex; flex-wrap:wrap; gap:.45rem; margin-top:.9rem; }
    .mr-chip {
        display:inline-flex; align-items:center; gap:.35rem;
        padding:.28rem .65rem; border-radius:999px;
        background:rgba(15,23,42,.22); border:1px solid rgba(255,255,255,.16);
        font-size:.75rem; font-weight:600; color:#f8fafc;
    }

    .mr-badge {
        display:inline-flex; align-items:center; padding:.18rem .58rem;
        border-radius:999px; font-size:.68rem; font-weight:800; letter-spacing:.04em; text-transform:uppercase;
    }
    .mr-badge--pending { background:#fef3c7; color:#92400e; }
    .mr-badge--scheduled { background:#e0e7ff; color:#3730a3; }
    .mr-badge--done { background:#dcfce7; color:#166534; }
    .mr-badge--cancelled { background:#fee2e2; color:#991b1b; }
    .mr-badge--on-dark { background:rgba(255,255,255,.2); color:#fff; border:1px solid rgba(255,255,255,.28); }

    .mr-grid { display:grid; grid-template-columns:minmax(0,1.15fr) minmax(0,.85fr); gap:1rem; }
    @media (max-width: 900px) { .mr-grid { grid-template-columns:1fr; } }

    .mr-card {
        background:#fff; border:1px solid #e2e8f0; border-radius:16px;
        padding:1.15rem 1.25rem; box-shadow:0 8px 24px rgba(15,23,42,.04);
    }
    .mr-card__h {
        margin:0 0 .85rem; font-size:.72rem; font-weight:800; letter-spacing:.08em;
        text-transform:uppercase; color:#64748b;
    }
    .mr-facts { display:grid; grid-template-columns:repeat(auto-fit,minmax(150px,1fr)); gap:.75rem .9rem; }
    .mr-fact__l { display:block; font-size:.68rem; font-weight:700; color:#94a3b8; text-transform:uppercase; letter-spacing:.04em; }
    .mr-fact__v { display:block; margin-top:.2rem; font-size:.92rem; font-weight:700; color:#0f172a; line-height:1.35; }
    .mr-message {
        margin:.95rem 0 0; padding:.85rem .95rem; border-radius:12px;
        background:#f8fafc; border:1px solid #e2e8f0; color:#334155;
        font-size:.9rem; line-height:1.55; white-space:pre-wrap;
    }

    .mr-steps { display:flex; gap:.45rem; flex-wrap:wrap; margin:0 0 1rem; }
    .mr-step {
        flex:1 1 6.5rem; min-width:6rem; padding:.45rem .55rem; border-radius:10px;
        background:#f8fafc; border:1px solid #e2e8f0; text-align:center;
        font-size:.68rem; font-weight:800; letter-spacing:.04em; text-transform:uppercase; color:#94a3b8;
    }
    .mr-step.is-on { background:#eef2ff; border-color:#c7d2fe; color:#3730a3; }
    .mr-step.is-done { background:#ecfdf5; border-color:#a7f3d0; color:#047857; }
    .mr-step.is-stop { background:#fef2f2; border-color:#fecaca; color:#b91c1c; }

    .mr-people { list-style:none; margin:0; padding:0; display:flex; flex-direction:column; gap:.4rem; }
    .mr-people li {
        display:flex; align-items:center; justify-content:space-between; gap:.5rem;
        padding:.5rem .65rem; border-radius:10px; background:#f8fafc; border:1px solid #eef2f7;
        font-size:.85rem; font-weight:600; color:#0f172a;
    }
    .mr-people__meta { font-size:.72rem; font-weight:600; color:#64748b; }

    .mr-actions { display:flex; flex-wrap:wrap; gap:.55rem; margin-top:1rem; }
    .mr-btn {
        display:inline-flex; align-items:center; justify-content:center; gap:.35rem;
        border:none; border-radius:10px; padding:.58rem 1rem; font-weight:700; font-size:.86rem;
        text-decoration:none; cursor:pointer; font-family:inherit;
    }
    .mr-btn--primary { background:#4f46e5; color:#fff; }
    .mr-btn--primary:hover { background:#4338ca; }
    .mr-btn--ghost { background:#fff; color:#334155; border:1px solid #cbd5e1; }
    .mr-btn--success { background:#047857; color:#fff; }

    .mr-pick { display:flex; flex-direction:column; gap:.4rem; margin:.35rem 0 .85rem; max-height:16rem; overflow:auto; }
    .mr-pick label {
        display:flex; align-items:center; gap:.55rem; padding:.55rem .65rem;
        border:1px solid #e2e8f0; border-radius:10px; font-size:.85rem; cursor:pointer; background:#fff;
    }
    .mr-pick label:hover { border-color:#c7d2fe; background:#eef2ff; }
    .mr-pick__name { font-weight:700; color:#0f172a; }
    .mr-pick__meta { color:#64748b; font-size:.75rem; }

    .mr-file {
        display:block; padding:.85rem; border:1.5px dashed #c7d2fe; border-radius:12px;
        background:#eef2ff; color:#3730a3; font-size:.84rem;
    }
    .mr-hint { margin:.4rem 0 0; font-size:.75rem; color:#64748b; line-height:1.45; }

    .ldm-list-table a.mr-link {
        display:inline-flex; padding:.28rem .6rem; border-radius:8px;
        background:#eef2ff; color:#3730a3; font-weight:700; text-decoration:none; font-size:.75rem;
    }
    .ldm-list-table a.mr-link:hover { background:#e0e7ff; }
    .mr-name { font-weight:700; color:#0f172a; }
    .mr-muted { color:#64748b; font-size:.75rem; }
    .mr-proof-thumb {
        display:inline-block; margin-top:.4rem;
        width:48px; height:48px; border-radius:8px; overflow:hidden;
        border:1px solid #c7d2fe; background:#eef2ff; vertical-align:middle;
    }
    .mr-proof-thumb img { width:100%; height:100%; object-fit:cover; display:block; }
    .mr-proof-thumb--lg { width:148px; height:148px; margin-top:.7rem; }
    .mr-proof-caption { margin:.4rem 0 0; font-size:.72rem; color:#64748b; font-weight:600; }
</style>
