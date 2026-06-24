<style>
    .fma-wrap { display: grid; gap: 0.9rem; }
    .fma-alert-ok {
        background: #dcfce7; color: #166534; border: 1px solid #bbf7d0;
        padding: 0.55rem 0.75rem; border-radius: 8px; font-size: 0.88rem;
    }
    .fma-tabs { display: flex; flex-wrap: wrap; gap: 0.45rem; }
    .fma-tab {
        text-decoration: none; border: 1px solid #e4e4e7; background: #fff; color: #3f3f46;
        padding: 0.42rem 0.78rem; border-radius: 999px; font-size: 0.81rem; font-weight: 700;
    }
    .fma-tab.is-active { border-color: #4f46e5; background: #eef2ff; color: #3730a3; }
    .fma-kpis {
        display: grid; gap: 0.65rem; grid-template-columns: repeat(auto-fit, minmax(10rem, 1fr));
    }
    .fma-kpi {
        border: 1px solid #e5e7eb; background: #fff; border-radius: 10px; padding: 0.55rem 0.7rem;
    }
    .fma-kpi-label { font-size: 0.7rem; color: #64748b; text-transform: uppercase; font-weight: 700; letter-spacing: 0.06em; }
    .fma-kpi-value { font-size: 1.2rem; font-weight: 800; color: #0f172a; margin-top: 0.12rem; }
    .fma-table-card { border: 1px solid #e5e7eb; border-radius: 12px; background: #fff; overflow: hidden; box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04); }
    .fma-toolbar { padding: 0.75rem 0.85rem; border-bottom: 1px solid #f1f5f9; background: linear-gradient(180deg, #fafafa 0%, #f8fafc 100%); }
    .fma-search {
        width: 100%; max-width: 26rem; border: 1px solid #d1d5db; border-radius: 9px;
        padding: 0.42rem 0.65rem; font-size: 0.86rem; background: #fff;
    }
    .fma-filter-grid { display: grid; gap: 0.45rem; grid-template-columns: repeat(auto-fit, minmax(11rem, 1fr)); margin-bottom: 0.5rem; }
    .fma-filter-actions { display: flex; gap: 0.45rem; flex-wrap: wrap; align-items: center; }
    .fma-table-wrap { overflow-x: auto; max-height: 72vh; }
    .fma-table { width: 100%; border-collapse: collapse; min-width: 1080px; font-size: 0.84rem; }
    .fma-table th {
        position: sticky; top: 0; z-index: 2;
        text-align: left; font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.05em;
        color: #64748b; background: #f8fafc; padding: 0.62rem 0.7rem; border-bottom: 1px solid #e2e8f0;
        white-space: nowrap;
    }
    .fma-table td { padding: 0.62rem 0.7rem; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
    .fma-table tr.fma-row--pending_approval td { background: #fffbeb; }
    .fma-table tr.fma-row--sent_back td { background: #fff7ed; }
    .fma-table tr.fma-row--approved td { background: #ecfdf5; }
    .fma-table tr.fma-row--rejected td { background: #fef2f2; }
    .fma-table tr:hover td { filter: brightness(0.98); }
    .fma-remark { max-width: 14rem; font-size: 0.78rem; color: #475569; word-break: break-word; white-space: normal; }
    .fma-sr { width: 2.8rem; text-align: center; color: #64748b; font-weight: 700; }
    .fma-serial { font-weight: 800; color: #3730a3; }
    .fma-status {
        display: inline-flex; border-radius: 999px; padding: 0.15rem 0.55rem; font-size: 0.75rem; font-weight: 700;
    }
    .fma-status--pending_approval { background: #fff7ed; color: #9a3412; }
    .fma-status--sent_back { background: #fee2e2; color: #b91c1c; }
    .fma-status--approved { background: #dcfce7; color: #166534; }
    .fma-status--rejected { background: #fce7f3; color: #9d174d; }
    .fma-btn {
        border: 1px solid #d1d5db; background: #fff; color: #111827; border-radius: 8px;
        padding: 0.3rem 0.55rem; font-size: 0.76rem; font-weight: 700; text-decoration: none; cursor: pointer;
    }
    .fma-btn--primary { border-color: #4f46e5; background: #eef2ff; color: #3730a3; }
    .fma-review-layout { display: grid; gap: 1rem; grid-template-columns: minmax(0, 1fr) minmax(260px, 320px); align-items: start; }
    @media (max-width: 960px) { .fma-review-layout { grid-template-columns: 1fr; } }
    .fma-review-banner {
        border-radius: 10px; padding: 0.75rem 1rem; font-size: 0.88rem; margin-bottom: 0.25rem;
    }
    .fma-review-banner--pending { background: #fffbeb; border: 1px solid #fde68a; color: #92400e; }
    .fma-review-banner--sent_back { background: #fff7ed; border: 1px solid #fed7aa; color: #9a3412; }
    .fma-review-banner--rejected { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; }
    .fma-review-banner--approved { background: #ecfdf5; border: 1px solid #86efac; color: #166534; }
    .fma-action-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1rem; position: sticky; top: 1rem; }
    .fma-action-card h3 { margin: 0 0 0.75rem; font-size: 0.92rem; font-weight: 800; color: #0f172a; }
    .fma-action-form { display: grid; gap: 0.65rem; margin-bottom: 0.85rem; padding-bottom: 0.85rem; border-bottom: 1px solid #f1f5f9; }
    .fma-action-form:last-child { border-bottom: none; margin-bottom: 0; padding-bottom: 0; }
    .fma-action-form label { display: block; font-size: 0.78rem; font-weight: 700; margin-bottom: 0.3rem; color: #334155; }
    .fma-action-form textarea { width: 100%; box-sizing: border-box; border: 1px solid #d1d5db; border-radius: 8px; padding: 0.45rem 0.55rem; font-size: 0.82rem; min-height: 4.5rem; }
    .fma-btn--approve { background: #166534; color: #fff; border-color: #166534; width: 100%; padding: 0.5rem; }
    .fma-btn--sendback { background: #9a3412; color: #fff; border-color: #9a3412; width: 100%; padding: 0.5rem; }
    .fma-btn--reject { background: #991b1b; color: #fff; border-color: #991b1b; width: 100%; padding: 0.5rem; }
    .fma-detail-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 1.2rem 1.35rem; }
    .fma-detail-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 0.75rem; }
    .fma-detail-label { display: block; font-size: 0.72rem; font-weight: 700; color: #64748b; text-transform: uppercase; margin-bottom: 0.2rem; }
    .fma-detail-value { font-size: 0.9rem; font-weight: 700; color: #0f172a; line-height: 1.45; }
    .fma-detail-full { grid-column: 1 / -1; }
</style>
