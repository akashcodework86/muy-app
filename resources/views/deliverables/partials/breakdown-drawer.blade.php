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
    .dlv-gender-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.2rem;
        padding: 0.15rem 0.45rem;
        border-radius: 999px;
        font-size: 0.7rem;
        font-weight: 800;
        letter-spacing: 0.04em;
        flex-shrink: 0;
    }
    .dlv-gender-badge--f { background: #fdf2f8; color: #be185d; border: 1px solid #fbcfe8; }
    .dlv-gender-badge--m { background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; }
    .dlv-participant-name { display: flex; align-items: center; gap: 0.4rem; font-weight: 600; }
    .dlv-session-line { padding: 0.12rem 0; border-bottom: 1px dashed #e2e8f0; }
    .dlv-session-line:last-child { border-bottom: none; padding-bottom: 0; }
    .dlv-type-chip {
        display: inline-block;
        padding: 0.12rem 0.4rem;
        border-radius: 6px;
        font-size: 0.65rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.03em;
        vertical-align: middle;
    }
    .dlv-type-chip--visit    { background: #ecfdf5; color: #065f46; }
    .dlv-type-chip--workshop { background: #eff6ff; color: #1e40af; }
    /* Pagination */
    .dlv-pagination {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.5rem;
        padding: 0.65rem 0.1rem 0.1rem;
        flex-wrap: wrap;
    }
    .dlv-pagination__info {
        font-size: 0.75rem;
        color: #64748b;
        font-weight: 600;
    }
    .dlv-pagination__controls { display: flex; gap: 0.35rem; align-items: center; }
    .dlv-page-btn {
        appearance: none;
        border: 1px solid #e2e8f0;
        background: #fff;
        color: #334155;
        font-size: 0.78rem;
        font-weight: 700;
        padding: 0.3rem 0.65rem;
        border-radius: 8px;
        cursor: pointer;
        transition: background 0.12s, border-color 0.12s;
    }
    .dlv-page-btn:hover:not(:disabled) { background: #f1f5f9; border-color: #cbd5e1; }
    .dlv-page-btn:disabled { opacity: 0.38; cursor: default; }
    .dlv-page-btn--active { background: #0f172a; color: #fff; border-color: #0f172a; }
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
    .dlv-action-btn--pdf {
        background: #fff7ed;
        color: #c2410c;
        border: 1px solid #fed7aa;
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
            <a href="#" class="dlv-action-btn dlv-action-btn--pdf" id="dlv-export-pdf">⬇ Download PDF</a>
            <a href="#" class="dlv-action-btn dlv-action-btn--ghost" id="dlv-export-csv">⬇ Download CSV</a>
        </div>
    </aside>
</div>

@push('scripts')
<script>
(() => {
    const overlay = document.getElementById('dlv-drawer-overlay');
    const body = document.getElementById('dlv-drawer-body');
    const exportXlsx = document.getElementById('dlv-export-xlsx');
    const exportPdf = document.getElementById('dlv-export-pdf');
    const exportCsv = document.getElementById('dlv-export-csv');
    const breakdownUrl = @json(route($breakdownRoute));
    const breakdownExportUrl = @json(route($breakdownExportRoute));
    const breakdownExportCsvUrl = @json(route($breakdownExportCsvRoute));
    const breakdownExportPdfUrl = @json(route($breakdownExportPdfRoute));
    const filterParams = @json($queryParams);
    let activeSerial = null;

    const fmt = (n) => new Intl.NumberFormat('en-IN').format(Number(n || 0));
    const fmtCurrency = (n) => {
        const value = Number(n || 0);
        return 'Rs ' + value.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    };
    const PAGE_SIZE_DEFAULT = 25;
    const PAGE_SIZE_PARTICIPANTS = 100;

    // Pagination state (reset per drawer open)
    let _allRecords = [];
    let _currentPage = 1;
    let _sourceType = '';

    function openDrawer(serial, name) {
        activeSerial = serial;
        document.getElementById('dlv-drawer-serial').textContent = 'S.N. ' + serial;
        document.getElementById('dlv-drawer-title').textContent = name;
        document.getElementById('dlv-drawer-scope').textContent = 'Loading…';
        document.getElementById('dlv-drawer-period').textContent = 'Loading…';
        document.getElementById('dlv-drawer-source').textContent = 'Loading…';
        body.innerHTML = '<div class="dlv-loading"><div class="dlv-spinner"></div><div>Loading breakdown…</div></div>';
        overlay.classList.add('is-open');
        overlay.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';

        const params = new URLSearchParams({ ...filterParams, serial });
        exportXlsx.href = breakdownExportUrl + '?' + params.toString();
        exportPdf.href = breakdownExportPdfUrl + '?' + params.toString();
        exportCsv.href = breakdownExportCsvUrl + '?' + params.toString();

        fetch(breakdownUrl + '?' + params.toString(), {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        })
            .then(async (res) => {
                const data = await res.json().catch(() => ({}));
                if (!res.ok) {
                    throw new Error(data.message || 'Failed to load breakdown');
                }
                return data;
            })
            .then(renderBreakdown)
            .catch((err) => {
                document.getElementById('dlv-drawer-scope').textContent = '—';
                document.getElementById('dlv-drawer-period').textContent = '—';
                document.getElementById('dlv-drawer-source').textContent = '—';
                body.innerHTML = '<div class="dlv-loading">' + (err.message || 'Could not load breakdown. Please try again.') + '</div>';
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

        // Store pagination state
        _allRecords = data.records || [];
        _currentPage = 1;
        _sourceType = data.source_type || '';

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

        const hasAmountSummary = data.applied_amount_total != null || data.sanctioned_amount_total != null;
        const amountCards = hasAmountSummary ? `
                <div class="dlv-stat-card">
                    <div class="dlv-stat-card__label">Applied Amount</div>
                    <div class="dlv-stat-card__value">${fmtCurrency(data.applied_amount_total || 0)}</div>
                </div>
                <div class="dlv-stat-card">
                    <div class="dlv-stat-card__label">Sanctioned Amount</div>
                    <div class="dlv-stat-card__value">${fmtCurrency(data.sanctioned_amount_total || 0)}</div>
                </div>
        ` : '';

        const serviceSection = (data.by_service || []).length ? `
            <div class="dlv-section">
                <h3 class="dlv-section__title">${data.source_type === 'market_linkage_incubatees' ? 'Online / offline split (incubatees)' : 'Service bifurcation'}</h3>
                <table class="dlv-table">
                    <thead><tr><th>Service</th><th>Count</th><th>Share</th><th>Applied Amount</th><th>Sanctioned Amount</th></tr></thead>
                    <tbody>${(data.by_service || []).map((row) => `<tr><td>${row.service}</td><td>${fmt(row.count)}</td><td>${row.share_pct}%</td><td>${fmtCurrency(row.applied_amount || 0)}</td><td>${fmtCurrency(row.sanctioned_amount || 0)}</td></tr>`).join('')}</tbody>
                </table>
            </div>
        ` : '';

        const recordsSectionTitle = buildRecordsSectionTitle(data.total);

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
                ${amountCards}
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
            <div class="dlv-section" id="dlv-records-section">
                <h3 class="dlv-section__title">${recordsSectionTitle}</h3>
                <div id="dlv-records-table-wrap"></div>
                <div id="dlv-pagination-wrap"></div>
            </div>
        `;

        renderRecordsPage();
    }

    function buildRecordsSectionTitle(total) {
        const isFP = _sourceType === 'field_work_participants' || _sourceType === 'field_visit_participants';
        const isWS = _sourceType === 'field_work_workshops' || _sourceType === 'field_visit_sessions';
        const isBst = _sourceType === 'bst_participants';
        const isPartners = _sourceType === 'market_linkage_unique_partners';
        const isMarketIncubatees = _sourceType === 'market_linkage_incubatees';
        if (isPartners) {
            return `Partner names <span style="font-weight:400;font-size:0.78rem;color:#0369a1;margin-left:0.4rem;">${fmt(total)} unique</span>`;
        }
        if (isMarketIncubatees) {
            return `Linked incubatees <span style="font-weight:400;font-size:0.78rem;color:#0369a1;margin-left:0.4rem;">${fmt(total)} incubatees</span>`;
        }
        if (isFP) {
            return `Female Participants <span style="font-weight:400;font-size:0.78rem;color:#be185d;margin-left:0.4rem;">${fmt(total)} entries</span>`;
        }
        if (isBst) {
            return `BST participations <span style="font-weight:400;font-size:0.78rem;color:#0369a1;margin-left:0.4rem;">${fmt(total)} counted</span>`;
        }
        if (isWS) return 'Activities';
        return 'Records';
    }

    function escapeHtml(text) {
        return String(text ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function renderRecordsPage() {
        const isFP = _sourceType === 'field_work_participants' || _sourceType === 'field_visit_participants';
        const isWS = _sourceType === 'field_work_workshops' || _sourceType === 'field_visit_sessions';
        const isBst = _sourceType === 'bst_participants';
        const isPartners = _sourceType === 'market_linkage_unique_partners';
        const isMarketIncubatees = _sourceType === 'market_linkage_incubatees';
        const isPitchDeckCombined = _sourceType === 'pitch_deck_combined';

        const isFPPage = _sourceType === 'field_work_participants' || _sourceType === 'field_visit_participants';
        const pageSize = (isFPPage || isBst) ? PAGE_SIZE_PARTICIPANTS : PAGE_SIZE_DEFAULT;

        const total = _allRecords.length;
        const totalPages = Math.max(1, Math.ceil(total / pageSize));
        _currentPage = Math.min(Math.max(1, _currentPage), totalPages);

        const start = (_currentPage - 1) * pageSize;
        const pageRecords = _allRecords.slice(start, start + pageSize);
        const globalOffset = start; // for continuous numbering across pages

        let tableHtml = '';

        if (isFP) {
            const rowsHtml = pageRecords.map((row, i) => {
                const sr = globalOffset + i + 1;
                const namePart = (row.applicant && row.applicant !== '—')
                    ? `<div class="dlv-participant-name"><span class="dlv-gender-badge dlv-gender-badge--f">♀ F</span><span>${row.applicant}</span></div>`
                    : `<span style="color:#94a3b8;font-style:italic;">Not recorded</span>`;
                return `<tr>
                    <td style="text-align:center;color:#94a3b8;font-weight:700;font-size:0.75rem;">${sr}</td>
                    <td>${namePart}</td>
                    <td>${row.district}</td>
                    <td style="font-size:0.78rem;color:#475569;">${row.service && row.service !== '—' ? row.service : '—'}</td>
                    <td style="color:#64748b;font-size:0.75rem;">${row.reference}</td>
                    <td>${row.date}</td>
                </tr>`;
            }).join('');
            tableHtml = `<table class="dlv-table">
                <thead><tr>
                    <th style="width:2rem;text-align:center;">#</th>
                    <th>Name &amp; Gender</th>
                    <th>District</th>
                    <th>Gram Panchayat / Mobile</th>
                    <th>Workshop Ref</th>
                    <th>Visit Date</th>
                </tr></thead>
                <tbody>${rowsHtml || '<tr><td colspan="6" style="color:#94a3b8;font-style:italic;padding:0.75rem 0.35rem;">No named participant records — only aggregate counts are available for this period.</td></tr>'}</tbody>
            </table>`;
        } else if (isWS) {
            const rowsHtml = pageRecords.map((row, i) => {
                const sr = globalOffset + i + 1;
                const isWkshp = row.service === 'Block workshop';
                const chip = isWkshp
                    ? `<span class="dlv-type-chip dlv-type-chip--workshop">Workshop</span>`
                    : `<span class="dlv-type-chip dlv-type-chip--visit">Field Visit</span>`;
                return `<tr>
                    <td style="text-align:center;color:#94a3b8;font-weight:700;font-size:0.75rem;">${sr}</td>
                    <td>${chip} <span style="font-size:0.78rem;color:#475569;">${row.reference}</span></td>
                    <td>${row.applicant}</td>
                    <td>${row.district}</td>
                    <td>${row.date}</td>
                </tr>`;
            }).join('');
            tableHtml = `<table class="dlv-table">
                <thead><tr>
                    <th style="width:2rem;text-align:center;">#</th>
                    <th>Type &amp; Ref</th>
                    <th>Area / Block</th>
                    <th>District</th>
                    <th>Date</th>
                </tr></thead>
                <tbody>${rowsHtml || '<tr><td colspan="5">No records found.</td></tr>'}</tbody>
            </table>`;
        } else if (isBst) {
            const rowsHtml = pageRecords.map((row, i) => {
                const sr = globalOffset + i + 1;
                const namePart = (row.applicant && row.applicant !== '—')
                    ? `<strong>${escapeHtml(row.applicant)}</strong>`
                    : `<span style="color:#94a3b8;font-style:italic;">Name not recorded</span>`;
                const sessions = Array.isArray(row.sessions) && row.sessions.length
                    ? row.sessions.map((label) => `<div class="dlv-session-line">${escapeHtml(label)}</div>`).join('')
                    : (row.service && row.service !== '—'
                        ? `<div class="dlv-session-line">${escapeHtml(row.service).replace(/\n/g, '<br>')}</div>`
                        : '<span style="color:#94a3b8;font-style:italic;">—</span>');
                return `<tr>
                    <td style="text-align:center;color:#94a3b8;font-weight:700;font-size:0.75rem;">${sr}</td>
                    <td>${namePart}</td>
                    <td>${escapeHtml(row.reference && row.reference !== '—' ? row.reference : '—')}</td>
                    <td>${escapeHtml(row.district)}</td>
                    <td style="font-size:0.78rem;color:#334155;line-height:1.45;">${sessions}</td>
                    <td style="font-size:0.75rem;color:#64748b;">${escapeHtml(row.hub)}</td>
                </tr>`;
            }).join('');
            tableHtml = `<table class="dlv-table">
                <thead><tr>
                    <th style="width:2rem;text-align:center;">#</th>
                    <th>Incubatee name</th>
                    <th>Application no.</th>
                    <th>District</th>
                    <th>BST session(s) attended</th>
                    <th>Hub</th>
                </tr></thead>
                <tbody>${rowsHtml || '<tr><td colspan="6" style="color:#94a3b8;font-style:italic;padding:0.75rem 0.35rem;">No unique incubatees in this period.</td></tr>'}</tbody>
            </table>`;
        } else if (isPartners) {
            const rowsHtml = pageRecords.map((row, i) => {
                const sr = globalOffset + i + 1;
                return `<tr>
                    <td style="text-align:center;color:#94a3b8;font-weight:700;font-size:0.75rem;">${sr}</td>
                    <td>${escapeHtml(row.service && row.service !== '—' ? row.service : '—')}</td>
                </tr>`;
            }).join('');
            tableHtml = `<table class="dlv-table">
                <thead><tr>
                    <th style="width:2rem;text-align:center;">#</th>
                    <th>Partner name</th>
                </tr></thead>
                <tbody>${rowsHtml || '<tr><td colspan="2">No partner names found.</td></tr>'}</tbody>
            </table>`;
        } else if (isMarketIncubatees) {
            const rowsHtml = pageRecords.map((row, i) => {
                const sr = globalOffset + i + 1;
                const mode = escapeHtml(row.linkage_mode || '—');
                const modeClass = String(row.linkage_mode || '').toLowerCase() === 'offline' ? 'dlv-type-chip--visit' : 'dlv-type-chip--workshop';
                return `<tr>
                    <td style="text-align:center;color:#94a3b8;font-weight:700;font-size:0.75rem;">${sr}</td>
                    <td>${escapeHtml(row.reference)}</td>
                    <td>${escapeHtml(row.applicant)}</td>
                    <td>${escapeHtml(row.district)}</td>
                    <td>${escapeHtml(row.service)}</td>
                    <td><span class="dlv-type-chip ${modeClass}">${mode}</span></td>
                    <td>${escapeHtml(row.date)}</td>
                </tr>`;
            }).join('');
            tableHtml = `<table class="dlv-table">
                <thead><tr>
                    <th style="width:2rem;text-align:center;">#</th>
                    <th>Application no.</th><th>Incubatee</th><th>District</th><th>Partner(s)</th><th>Mode</th><th>Latest linkage</th>
                </tr></thead>
                <tbody>${rowsHtml || '<tr><td colspan="7">No linked incubatees found.</td></tr>'}</tbody>
            </table>`;
        } else if (isPitchDeckCombined) {
            const rowsHtml = pageRecords.map((row, i) => {
                const sr = globalOffset + i + 1;
                return `<tr>
                    <td style="text-align:center;color:#94a3b8;font-weight:700;font-size:0.75rem;">${sr}</td>
                    <td>${escapeHtml(row.reference)}</td>
                    <td>${escapeHtml(row.applicant)}</td>
                    <td>${escapeHtml(row.district)}</td>
                    <td>${escapeHtml(row.service)}</td>
                    <td>${escapeHtml(row.filled_by && row.filled_by !== row.service ? row.filled_by : '—')}</td>
                    <td>${escapeHtml(row.date)}</td>
                </tr>`;
            }).join('');
            tableHtml = `<table class="dlv-table">
                <thead><tr>
                    <th style="width:2rem;text-align:center;">#</th>
                    <th>Reference</th><th>Incubatee</th><th>District</th><th>Source</th><th>Filled by</th><th>Date</th>
                </tr></thead>
                <tbody>${rowsHtml || '<tr><td colspan="7">No records found.</td></tr>'}</tbody>
            </table>`;
        } else {
            const rowsHtml = pageRecords.map((row, i) => {
                const sr = globalOffset + i + 1;
                return `<tr>
                    <td style="text-align:center;color:#94a3b8;font-weight:700;font-size:0.75rem;">${sr}</td>
                    <td>${row.reference}</td>
                    <td>${row.applicant}</td>
                    <td>${row.district}</td>
                    <td>${row.service}</td>
                    <td>${row.date}</td>
                </tr>`;
            }).join('');
            tableHtml = `<table class="dlv-table">
                <thead><tr>
                    <th style="width:2rem;text-align:center;">#</th>
                    <th>Reference</th><th>Applicant</th><th>District</th><th>Service</th><th>Date</th>
                </tr></thead>
                <tbody>${rowsHtml || '<tr><td colspan="6">No records found.</td></tr>'}</tbody>
            </table>`;
        }

        // ── Pagination controls ──────────────────────────────────────────────
        let paginationHtml = '';
        if (totalPages > 1) {
            const from = start + 1;
            const to = Math.min(start + pageSize, total);

            // Show up to 5 page number buttons around current page
            const pageButtons = [];
            const rangeStart = Math.max(1, _currentPage - 2);
            const rangeEnd = Math.min(totalPages, _currentPage + 2);
            if (rangeStart > 1) pageButtons.push(`<button class="dlv-page-btn" data-pg="1">1</button>`);
            if (rangeStart > 2) pageButtons.push(`<span style="color:#94a3b8;font-size:0.75rem;padding:0 0.1rem;">…</span>`);
            for (let p = rangeStart; p <= rangeEnd; p++) {
                pageButtons.push(`<button class="dlv-page-btn${p === _currentPage ? ' dlv-page-btn--active' : ''}" data-pg="${p}">${p}</button>`);
            }
            if (rangeEnd < totalPages - 1) pageButtons.push(`<span style="color:#94a3b8;font-size:0.75rem;padding:0 0.1rem;">…</span>`);
            if (rangeEnd < totalPages) pageButtons.push(`<button class="dlv-page-btn" data-pg="${totalPages}">${totalPages}</button>`);

            paginationHtml = `
                <div class="dlv-pagination">
                    <div class="dlv-pagination__info">${from}–${to} of ${fmt(total)}</div>
                    <div class="dlv-pagination__controls">
                        <button class="dlv-page-btn" id="dlv-prev-page" ${_currentPage <= 1 ? 'disabled' : ''}>&#8592; Prev</button>
                        ${pageButtons.join('')}
                        <button class="dlv-page-btn" id="dlv-next-page" ${_currentPage >= totalPages ? 'disabled' : ''}>Next &#8594;</button>
                    </div>
                </div>`;
        }

        document.getElementById('dlv-records-table-wrap').innerHTML = tableHtml;
        document.getElementById('dlv-pagination-wrap').innerHTML = paginationHtml;

        // Attach pagination handlers
        document.getElementById('dlv-prev-page')?.addEventListener('click', () => { _currentPage--; renderRecordsPage(); });
        document.getElementById('dlv-next-page')?.addEventListener('click', () => { _currentPage++; renderRecordsPage(); });
        document.querySelectorAll('#dlv-pagination-wrap .dlv-page-btn[data-pg]').forEach((btn) => {
            btn.addEventListener('click', () => { _currentPage = parseInt(btn.dataset.pg, 10); renderRecordsPage(); });
        });
    }

    document.querySelectorAll('[data-dlv-breakdown]').forEach((btn) => {
        btn.addEventListener('click', () => openDrawer(btn.dataset.serial, btn.dataset.name));
    });

    document.getElementById('dlv-drawer-close').addEventListener('click', closeDrawer);
    overlay.addEventListener('click', (e) => { if (e.target === overlay) closeDrawer(); });
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeDrawer(); });
})();
</script>
@endpush
