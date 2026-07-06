<style>
    .satt-grid-card {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        overflow: hidden;
        box-shadow: 0 4px 18px rgba(15,23,42,0.05);
        margin-top: 1.5rem;
    }
    .satt-grid-card__head {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
        padding: 0.9rem 1.15rem;
        background: linear-gradient(90deg, #f8fafc, #f1f5f9);
        border-bottom: 1px solid #e2e8f0;
    }
    .satt-grid-card__title { margin: 0; font-size: 0.95rem; font-weight: 800; color: #0f172a; }
    .satt-legend { display: flex; flex-wrap: wrap; gap: 0.85rem; font-size: 0.78rem; color: #64748b; font-weight: 600; }
    .satt-legend__item { display: inline-flex; align-items: center; gap: 0.3rem; }
    .satt-grid-wrap { overflow: auto; max-height: 70vh; min-height: 120px; }
    .satt-grid { border-collapse: separate; border-spacing: 0; width: max-content; min-width: 100%; font-size: 0.82rem; }
    .satt-grid th, .satt-grid td { border-bottom: 1px solid #f1f5f9; border-right: 1px solid #f1f5f9; text-align: center; padding: 0; }
    .satt-grid thead th {
        position: sticky; top: 0; z-index: 3;
        background: linear-gradient(180deg, #0f766e, #0d9488);
        color: #fff; font-size: 0.72rem; font-weight: 700; padding: 0.55rem 0.2rem; min-width: 30px;
    }
    .satt-grid thead th.is-today { background: linear-gradient(180deg, #b45309, #d97706); }
    .satt-grid thead th.is-weekend { background: linear-gradient(180deg, #475569, #64748b); }
    .satt-grid__cell { width: 32px; height: 36px; display: flex; align-items: center; justify-content: center; margin: 0 auto; }
    .satt-grid td.is-weekend { background: #f8fafc; }
    .satt-grid td.is-today { background: #fffbeb; }
    .satt-grid tbody td.satt-grid__sunday { background: #f1f5f9 !important; vertical-align: middle; min-width: 34px; }
    .satt-grid__sunday-label {
        writing-mode: vertical-rl; transform: rotate(180deg); font-size: 0.65rem; font-weight: 700;
        color: #94a3b8; letter-spacing: 0.12em; text-transform: uppercase; display: inline-flex;
        align-items: center; justify-content: center; padding: 0.5rem 0; white-space: nowrap;
    }
    .satt-mark {
        display: inline-flex; align-items: center; justify-content: center;
        width: 22px; height: 22px; border-radius: 6px; font-size: 0.75rem; font-weight: 800;
    }
    .satt-mark--present { background: linear-gradient(135deg, #dcfce7, #bbf7d0); color: #15803d; }
    .satt-mark--absent {
        background: linear-gradient(135deg, #fee2e2, #fecaca); color: #b91c1c;
        border: none; cursor: pointer;
    }
    .satt-mark--future { color: #cbd5e1; font-weight: 400; }
    .satt-grid thead th.satt-grid__col-total,
    .satt-grid tbody td.satt-grid__col-total {
        position: sticky; z-index: 2; min-width: 58px; padding: 0.45rem 0.5rem;
        text-align: center; vertical-align: middle; background: #f8fafc;
    }
    .satt-grid thead th.satt-grid__col-present { right: 58px; border-left: 2px solid #e2e8f0; background: linear-gradient(180deg, #15803d, #16a34a); color: #fff; }
    .satt-grid thead th.satt-grid__col-absent { right: 0; background: linear-gradient(180deg, #b91c1c, #dc2626); color: #fff; }
    .satt-grid tbody td.satt-grid__col-present { right: 58px; border-left: 2px solid #e2e8f0; }
    .satt-grid tbody td.satt-grid__col-absent { right: 0; }
    .satt-grid__total-val {
        display: inline-flex; align-items: center; justify-content: center; min-width: 2rem;
        padding: 0.2rem 0.5rem; border-radius: 8px; font-size: 0.85rem; font-weight: 800;
    }
    .satt-grid__total-val--p { background: #dcfce7; color: #15803d; }
    .satt-grid__total-val--a { background: #fee2e2; color: #b91c1c; }
    .satt-grid-foot {
        padding: 0.85rem 1.15rem; border-top: 1px solid #e2e8f0;
        background: linear-gradient(180deg, #fafbfc, #f8fafc); font-size: 0.82rem; color: #475569;
    }
    .satt-sun-chips { display: flex; flex-wrap: wrap; gap: 0.4rem; margin-top: 0.45rem; }
    .satt-sun-chip {
        padding: 0.22rem 0.55rem; border-radius: 999px; background: #f1f5f9;
        border: 1px solid #e2e8f0; font-size: 0.76rem; font-weight: 600; color: #64748b;
    }
    .satt-empty { text-align: center; color: #64748b; padding: 2rem 1rem; }
</style>
