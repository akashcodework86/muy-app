@push('styles')
<style>
    .dlv-ach-btn {
        appearance: none;
        border: none;
        background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%);
        color: #065f46;
        font: inherit;
        font-weight: 700;
        padding: 0.2rem 0.55rem;
        border-radius: 999px;
        cursor: pointer;
        transition: transform 0.15s ease, box-shadow 0.15s ease, background 0.15s ease;
        box-shadow: inset 0 0 0 1px rgba(6, 95, 70, 0.15);
    }
    .dlv-ach-btn:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 14px rgba(6, 95, 70, 0.18);
        background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
    }
    .dlv-ach-btn:focus-visible {
        outline: 2px solid #059669;
        outline-offset: 2px;
    }
    .dlv-ach-static {
        color: #334155;
        font-weight: 600;
    }
    .dlv-drawer-overlay {
        position: fixed;
        inset: 0;
        background: rgba(15, 23, 42, 0.45);
        backdrop-filter: blur(3px);
        z-index: 1200;
        opacity: 0;
        visibility: hidden;
        transition: opacity 0.25s ease, visibility 0.25s ease;
    }
    .dlv-drawer-overlay.is-open {
        opacity: 1;
        visibility: visible;
    }
    .dlv-drawer {
        position: fixed;
        top: 0;
        right: 0;
        width: min(720px, 100vw);
        height: 100vh;
        background: #f8fafc;
        box-shadow: -20px 0 60px rgba(15, 23, 42, 0.18);
        z-index: 1201;
        transform: translateX(100%);
        transition: transform 0.28s cubic-bezier(0.22, 1, 0.36, 1);
        display: flex;
        flex-direction: column;
    }
    .dlv-drawer-overlay.is-open .dlv-drawer {
        transform: translateX(0);
    }
    .dlv-drawer__head {
        padding: 1.15rem 1.25rem 1rem;
        background: linear-gradient(135deg, #9a3412 0%, #c2410c 55%, #ea580c 100%);
        color: #fff;
        flex-shrink: 0;
    }
    .dlv-drawer__head-top {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 0.75rem;
    }
    .dlv-drawer__serial {
        display: inline-block;
        font-size: 0.72rem;
        font-weight: 700;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        opacity: 0.85;
        margin-bottom: 0.25rem;
    }
    .dlv-drawer__title {
        margin: 0;
        font-size: 1.15rem;
        line-height: 1.35;
        font-weight: 700;
    }
    .dlv-drawer__close {
        appearance: none;
        border: none;
        background: rgba(255,255,255,0.16);
        color: #fff;
        width: 2rem;
        height: 2rem;
        border-radius: 999px;
        cursor: pointer;
        font-size: 1.1rem;
        line-height: 1;
    }
    .dlv-drawer__meta {
        margin-top: 0.65rem;
        display: flex;
        flex-wrap: wrap;
        gap: 0.45rem;
        font-size: 0.78rem;
    }
    .dlv-drawer__pill {
        background: rgba(255,255,255,0.14);
        border: 1px solid rgba(255,255,255,0.18);
        padding: 0.18rem 0.55rem;
        border-radius: 999px;
    }
    .dlv-drawer__body {
        flex: 1;
        overflow: auto;
        padding: 1rem 1.25rem 1.5rem;
    }
    .dlv-stat-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 0.75rem;
        margin-bottom: 1rem;
    }
    .dlv-stat-card {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        padding: 0.85rem 0.9rem;
        box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
    }
    .dlv-stat-card__label {
        font-size: 0.72rem;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        color: #64748b;
        font-weight: 700;
    }
    .dlv-stat-card__value {
        margin-top: 0.25rem;
        font-size: 1.35rem;
        font-weight: 800;
        color: #0f172a;
    }
    .dlv-stat-card--accent .dlv-stat-card__value { color: #059669; }
    .dlv-insights {
        display: grid;
        gap: 0.55rem;
        margin-bottom: 1rem;
    }
    .dlv-insight {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-left: 4px solid #6366f1;
        border-radius: 10px;
        padding: 0.65rem 0.8rem;
        display: flex;
        justify-content: space-between;
        gap: 0.75rem;
        align-items: center;
    }
    .dlv-insight--success { border-left-color: #10b981; }
    .dlv-insight--info { border-left-color: #0ea5e9; }
    .dlv-insight--muted { border-left-color: #94a3b8; }
    .dlv-insight__label {
        font-size: 0.78rem;
        color: #64748b;
        font-weight: 600;
    }
    .dlv-insight__value {
        font-size: 0.88rem;
        color: #0f172a;
        font-weight: 700;
        text-align: right;
    }
    .dlv-section {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        padding: 0.85rem 0.95rem;
        margin-bottom: 0.85rem;
    }
    .dlv-section__title {
        margin: 0 0 0.75rem;
        font-size: 0.92rem;
        font-weight: 800;
        color: #0f172a;
    }
    .dlv-bar-row {
        display: grid;
        grid-template-columns: minmax(0, 1fr) minmax(90px, 120px) 42px;
        gap: 0.55rem;
        align-items: center;
        margin-bottom: 0.55rem;
        font-size: 0.82rem;
    }
    .dlv-bar-row__label {
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        color: #334155;
        font-weight: 600;
    }
    .dlv-bar-row__track {
        height: 8px;
        background: #e2e8f0;
        border-radius: 999px;
        overflow: hidden;
    }
    .dlv-bar-row__fill {
        height: 100%;
        background: linear-gradient(90deg, #059669, #34d399);
        border-radius: 999px;
    }
    .dlv-bar-row__count {
        text-align: right;
        font-weight: 700;
        color: #065f46;
    }
    .dlv-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.8rem;
    }
    .dlv-table th,
    .dlv-table td {
        padding: 0.45rem 0.35rem;
        border-bottom: 1px solid #eef2f7;
        text-align: left;
        vertical-align: top;
    }
    .dlv-table th {
        font-size: 0.72rem;
        text-transform: uppercase;
        letter-spacing: 0.03em;
        color: #64748b;
    }
    .dlv-drawer__actions {
        display: flex;
        flex-wrap: wrap;
        gap: 0.55rem;
        padding: 0.85rem 1.25rem 1.1rem;
        border-top: 1px solid #e2e8f0;
        background: #fff;
        flex-shrink: 0;
    }
    .dlv-action-btn {
        appearance: none;
        border: none;
        border-radius: 10px;
        padding: 0.55rem 0.85rem;
        font-size: 0.84rem;
        font-weight: 700;
        cursor: pointer;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
    }
    .dlv-action-btn--primary {
        background: #065f46;
        color: #fff;
    }
    .dlv-action-btn--ghost {
        background: #f1f5f9;
        color: #334155;
    }
    .dlv-loading {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 0.75rem;
        min-height: 220px;
        color: #64748b;
    }
    .dlv-spinner {
        width: 34px;
        height: 34px;
        border: 3px solid #e2e8f0;
        border-top-color: #c2410c;
        border-radius: 50%;
        animation: dlv-spin 0.8s linear infinite;
    }
    @keyframes dlv-spin { to { transform: rotate(360deg); } }
    @media (max-width: 640px) {
        .dlv-stat-grid { grid-template-columns: 1fr; }
        .dlv-bar-row { grid-template-columns: 1fr; }
    }
</style>
@endpush

<div id="dlv-drawer-overlay" class="dlv-drawer-overlay" aria-hidden="true">
    <aside class="dlv-drawer" role="dialog" aria-modal="true" aria-labelledby="dlv-drawer-title">
        <div class="dlv-drawer__head">
            <div class="dlv-drawer__head-top">
                <div>
                    <div class="dlv-drawer__serial" id="dlv-drawer-serial">—</div>
                    <h2 class="dlv-drawer__title" id="dlv-drawer-title">Achievement breakdown</h2>
                </div>
                <button type="button" class="dlv-drawer__close" id="dlv-drawer-close" aria-label="Close">&times;</button>
            </div>
            <div class="dlv-drawer__meta">
                <span class="dlv-drawer__pill" id="dlv-drawer-scope">—</span>
                <span class="dlv-drawer__pill" id="dlv-drawer-period">—</span>
                <span class="dlv-drawer__pill" id="dlv-drawer-source">—</span>
            </div>
        </div>
        <div class="dlv-drawer__body" id="dlv-drawer-body">
            <div class="dlv-loading">
                <div class="dlv-spinner"></div>
                <div>Loading breakdown…</div>
            </div>
        </div>
        <div class="dlv-drawer__actions">
            <a href="#" class="dlv-action-btn dlv-action-btn--primary" id="dlv-export-xlsx">⬇ Download Excel</a>
            <button type="button" class="dlv-action-btn dlv-action-btn--ghost" id="dlv-export-csv">⬇ Download CSV</button>
        </div>
    </aside>
</div>

@push('scripts')
<script>
(() => {
    const overlay = document.getElementById('dlv-drawer-overlay');
    const body = document.getElementById('dlv-drawer-body');
    const exportXlsx = document.getElementById('dlv-export-xlsx');
    const exportCsv = document.getElementById('dlv-export-csv');
    const breakdownUrl = @json(route($breakdownRoute));
    const breakdownExportUrl = @json(route($breakdownExportRoute));
    const filterParams = @json($queryParams);
    let activeSerial = null;

    const fmt = (n) => new Intl.NumberFormat('en-IN').format(Number(n || 0));

    function openDrawer(serial, name) {
        activeSerial = serial;
        document.getElementById('dlv-drawer-serial').textContent = 'S.N. ' + serial;
        document.getElementById('dlv-drawer-title').textContent = name;
        body.innerHTML = '<div class="dlv-loading"><div class="dlv-spinner"></div><div>Loading breakdown…</div></div>';
        overlay.classList.add('is-open');
        overlay.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';

        const params = new URLSearchParams({ ...filterParams, serial });
        exportXlsx.href = breakdownExportUrl + '?' + params.toString();

        fetch(breakdownUrl + '?' + params.toString(), {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        })
            .then((res) => {
                if (!res.ok) throw new Error('Failed to load breakdown');
                return res.json();
            })
            .then(renderBreakdown)
            .catch(() => {
                body.innerHTML = '<div class="dlv-loading">Could not load breakdown. Please try again.</div>';
            });
    }

    function closeDrawer() {
        overlay.classList.remove('is-open');
        overlay.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
        activeSerial = null;
    }

    function renderBreakdown(data) {
        document.getElementById('dlv-drawer-scope').textContent = data.scope_label || '—';
        document.getElementById('dlv-drawer-period').textContent = data.period_label || '—';
        document.getElementById('dlv-drawer-source').textContent = data.source_type_label || '—';

        const maxDistrict = Math.max(...(data.by_district || []).map((r) => r.count), 1);
        const districtBars = (data.by_district || []).slice(0, 8).map((row) => `
            <div class="dlv-bar-row">
                <div class="dlv-bar-row__label" title="${row.district}">${row.district}</div>
                <div class="dlv-bar-row__track"><div class="dlv-bar-row__fill" style="width:${Math.round((row.count / maxDistrict) * 100)}%"></div></div>
                <div class="dlv-bar-row__count">${fmt(row.count)}</div>
            </div>
        `).join('');

        const insights = (data.insights || []).map((item) => `
            <div class="dlv-insight dlv-insight--${item.tone || 'primary'}">
                <div class="dlv-insight__label">${item.label}</div>
                <div class="dlv-insight__value">${item.value}</div>
            </div>
        `).join('');

        const monthRows = (data.by_month || []).map((row) => `
            <tr><td>${row.month}</td><td>${fmt(row.count)}</td><td>${row.share_pct}%</td></tr>
        `).join('');

        const serviceSection = (data.by_service || []).length ? `
            <div class="dlv-section">
                <h3 class="dlv-section__title">By service</h3>
                <table class="dlv-table">
                    <thead><tr><th>Service</th><th>Count</th><th>Share</th></tr></thead>
                    <tbody>${(data.by_service || []).map((row) => `<tr><td>${row.service}</td><td>${fmt(row.count)}</td><td>${row.share_pct}%</td></tr>`).join('')}</tbody>
                </table>
            </div>
        ` : '';

        const recordRows = (data.records || []).slice(0, 25).map((row) => `
            <tr>
                <td>${row.reference}</td>
                <td>${row.applicant}</td>
                <td>${row.district}</td>
                <td>${row.service}</td>
                <td>${row.date}</td>
            </tr>
        `).join('');

        body.innerHTML = `
            <div class="dlv-stat-grid">
                <div class="dlv-stat-card dlv-stat-card--accent">
                    <div class="dlv-stat-card__label">Achievement</div>
                    <div class="dlv-stat-card__value">${fmt(data.total)}</div>
                </div>
                <div class="dlv-stat-card">
                    <div class="dlv-stat-card__label">Target</div>
                    <div class="dlv-stat-card__value">${data.target != null ? fmt(data.target) : '—'}</div>
                </div>
                <div class="dlv-stat-card">
                    <div class="dlv-stat-card__label">Progress</div>
                    <div class="dlv-stat-card__value">${data.achievement_pct != null ? data.achievement_pct + '%' : '—'}</div>
                </div>
            </div>
            <div class="dlv-insights">${insights || '<div class="dlv-insight dlv-insight--muted"><div class="dlv-insight__label">Insights</div><div class="dlv-insight__value">No data in this period</div></div>'}</div>
            <div class="dlv-section">
                <h3 class="dlv-section__title">District split</h3>
                ${districtBars || '<div style="color:#64748b;font-size:0.85rem;">No district data.</div>'}
            </div>
            <div class="dlv-section">
                <h3 class="dlv-section__title">Monthly trend</h3>
                <table class="dlv-table">
                    <thead><tr><th>Month</th><th>Count</th><th>Share</th></tr></thead>
                    <tbody>${monthRows || '<tr><td colspan="3">No monthly data.</td></tr>'}</tbody>
                </table>
            </div>
            ${serviceSection}
            <div class="dlv-section">
                <h3 class="dlv-section__title">Recent records</h3>
                <table class="dlv-table">
                    <thead><tr><th>Reference</th><th>Applicant</th><th>District</th><th>Service</th><th>Date</th></tr></thead>
                    <tbody>${recordRows || '<tr><td colspan="5">No records found.</td></tr>'}</tbody>
                </table>
            </div>
        `;
    }

    function downloadCsv() {
        if (!activeSerial) return;
        const params = new URLSearchParams({ ...filterParams, serial: activeSerial });
        fetch(breakdownUrl + '?' + params.toString(), {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        })
            .then((res) => res.json())
            .then((data) => {
                const lines = [
                    ['Indicator', data.name || ''],
                    ['Achievement', data.total || 0],
                    [],
                    ['District', 'Hub', 'Count', 'Share %'],
                    ...(data.by_district || []).map((r) => [r.district, r.hub, r.count, r.share_pct]),
                    [],
                    ['Reference', 'Applicant', 'District', 'Service', 'Date'],
                    ...(data.records || []).map((r) => [r.reference, r.applicant, r.district, r.service, r.date]),
                ];
                const csv = lines.map((row) => row.map((cell) => `"${String(cell ?? '').replace(/"/g, '""')}"`).join(',')).join('\n');
                const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
                const link = document.createElement('a');
                link.href = URL.createObjectURL(blob);
                link.download = 'deliverables-breakdown-' + activeSerial.replace(/\./g, '-') + '.csv';
                link.click();
                URL.revokeObjectURL(link.href);
            });
    }

    document.querySelectorAll('[data-dlv-breakdown]').forEach((btn) => {
        btn.addEventListener('click', () => openDrawer(btn.dataset.serial, btn.dataset.name));
    });

    document.getElementById('dlv-drawer-close').addEventListener('click', closeDrawer);
    overlay.addEventListener('click', (e) => { if (e.target === overlay) closeDrawer(); });
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeDrawer(); });
    exportCsv.addEventListener('click', downloadCsv);
})();
</script>
@endpush
