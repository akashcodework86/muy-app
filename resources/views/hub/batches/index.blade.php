<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Hub batches — {{ $hub->name }} — {{ config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700&display=swap" rel="stylesheet">
    @include('partials.admin-shell-styles')
    <style>
        :root {
            --text: #0f172a;
            --muted: #64748b;
            --border: #e2e8f0;
            --accent: #059669;
            --accent2: #0d9488;
            --radius: 14px;
            --shadow: 0 4px 24px rgba(15, 23, 42, 0.08);
        }
        body.hub-batch-page { font-family: 'DM Sans', system-ui, sans-serif; }
        .hb-wrap { max-width: 1200px; margin: 0 auto; }
        .hb-hero {
            background: linear-gradient(135deg, #064e3b 0%, #059669 50%, #14b8a6 100%);
            color: #ecfdf5;
            border-radius: var(--radius);
            padding: 1.5rem 1.75rem;
            margin-bottom: 1.25rem;
            box-shadow: var(--shadow);
        }
        .hb-hero h1 { margin: 0; font-size: 1.5rem; font-weight: 700; }
        .hb-hero p { margin: 0.5rem 0 0; opacity: 0.92; font-size: 0.9rem; max-width: 40rem; }
        .hb-stats { display: flex; flex-wrap: wrap; gap: 0.75rem; margin-top: 1rem; }
        .hb-stat {
            background: rgba(255,255,255,0.95);
            color: var(--text);
            border-radius: 10px;
            padding: 0.65rem 1rem;
            min-width: 6rem;
        }
        .hb-stat .l { font-size: 0.65rem; text-transform: uppercase; font-weight: 600; color: var(--muted); }
        .hb-stat .v { font-size: 1.35rem; font-weight: 700; }
        .hb-grid { display: grid; grid-template-columns: 1fr; gap: 1.25rem; }
        @media (min-width: 960px) { .hb-grid { grid-template-columns: 320px 1fr; } }
        .hb-card {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 1.15rem 1.25rem;
            box-shadow: var(--shadow);
        }
        .hb-card h2 { margin: 0 0 0.75rem; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.06em; color: var(--muted); }
        .hb-input, .hb-select {
            width: 100%; border: 1px solid var(--border); border-radius: 10px; padding: 0.55rem 0.75rem; font-size: 0.9rem;
        }
        .hb-btn {
            display: inline-flex; align-items: center; justify-content: center; gap: 0.35rem;
            border: none; border-radius: 10px; padding: 0.65rem 1rem; font-weight: 600; font-size: 0.9rem; cursor: pointer;
        }
        .hb-btn--primary { background: var(--accent); color: #fff; }
        .hb-btn--dark { background: #0f172a; color: #fff; }
        .hb-btn--ghost { background: #f8fafc; color: var(--text); border: 1px solid var(--border); }
        .hb-btn:disabled { opacity: 0.45; cursor: not-allowed; }
        .hb-table-wrap { overflow: auto; max-height: min(70vh, 520px); border: 1px solid var(--border); border-radius: 10px; }
        table.hb-table { width: 100%; border-collapse: collapse; font-size: 0.875rem; }
        table.hb-table th { text-align: left; padding: 0.65rem 0.75rem; background: #f8fafc; font-size: 0.7rem; text-transform: uppercase; color: var(--muted); position: sticky; top: 0; }
        table.hb-table td { padding: 0.55rem 0.75rem; border-top: 1px solid #f1f5f9; }
        table.hb-table tr:hover td { background: #f0fdf4; }
        .hb-banner { border-radius: 10px; padding: 0.85rem 1rem; margin-bottom: 1rem; font-size: 0.875rem; }
        .hb-banner--warn { background: #fff7ed; border: 1px solid #fed7aa; color: #9a3412; }
        .hb-banner--ok { background: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46; }
        .hb-modal-bg { display: none; position: fixed; inset: 0; background: rgba(15,23,42,0.45); z-index: 50; align-items: center; justify-content: center; padding: 1rem; }
        .hb-modal-bg.is-open { display: flex; }
        .hb-modal { background: #fff; border-radius: var(--radius); padding: 1.25rem; max-width: 420px; width: 100%; box-shadow: 0 25px 50px rgba(0,0,0,0.2); }
        .link-mini { font-size: 0.75rem; font-weight: 600; color: var(--accent); background: none; border: none; cursor: pointer; }
        .hb-stage-mix { display: grid; grid-template-columns: repeat(3, 1fr); gap: 0.5rem; margin-top: 0.35rem; }
        .hb-stage-mix__box {
            border-radius: 10px; padding: 0.5rem 0.45rem; text-align: center;
            border: 1px solid var(--border); font-size: 0.72rem; line-height: 1.35;
        }
        .hb-stage-mix__box .lbl { font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; font-size: 0.6rem; color: var(--muted); }
        .hb-stage-mix__box .cnt { font-size: 1.35rem; font-weight: 800; font-family: 'DM Sans', sans-serif; margin: 0.15rem 0; }
        .hb-stage-mix__box .ideal { font-size: 0.62rem; color: var(--muted); }
        .hb-stage-mix__box--seed { background: linear-gradient(180deg, #fffbeb, #fff); border-color: #fcd34d; }
        .hb-stage-mix__box--early { background: linear-gradient(180deg, #eff6ff, #fff); border-color: #93c5fd; }
        .hb-stage-mix__box--growth { background: linear-gradient(180deg, #f5f3ff, #fff); border-color: #c4b5fd; }
        .hb-stage-mix__note { font-size: 0.65rem; color: #92400e; margin: 0.5rem 0 0; line-height: 1.4; }
    </style>
</head>
<body class="admin-app-body hub-batch-page">
    @include('partials.admin-topbar')
    <main class="admin-main hb-wrap">
        @if (session('status'))
            <div class="hb-banner hb-banner--ok">{{ session('status') }}</div>
        @endif

        <div class="hb-hero">
            <h1>Batch &amp; CFA pool</h1>
            <p>Pick CFA only, reject / later, fixed batch size (N), lock, then CDO signed PDF within 7 days.</p>
            <div class="hb-stats">
                <div class="hb-stat"><div class="l">PDF pending</div><div class="v" id="statPending">{{ (int) $stats['pending_cdo'] }}</div></div>
                <div class="hb-stat"><div class="l">Overdue</div><div class="v" id="statOverdue" style="color:{{ $stats['overdue_cdo'] ? '#dc2626' : '#94a3b8' }}">{{ (int) $stats['overdue_cdo'] }}</div></div>
            </div>
        </div>

        <div id="blockedBanner" class="hb-banner hb-banner--warn" style="display:{{ $stats['blocked'] ? 'block' : 'none' }}">
            <strong>New batches paused:</strong> upload CDO PDF for overdue locked batches, or ask state admin to extend / waive.
        </div>

        <div class="hb-grid">
            <div>
                <div class="hb-card">
                    <h2>District</h2>
                    <select id="selDistrict" class="hb-select">
                        <option value="">Choose district…</option>
                        @foreach ($districts as $d)
                            <option value="{{ $d->id }}">{{ $d->name }}</option>
                        @endforeach
                    </select>
                    <div id="draftCreateWrap" style="display:none;margin-top:1rem;padding-top:1rem;border-top:1px solid var(--border)">
                        <label class="hb-muted" style="font-size:0.8rem">Target size (N)</label>
                        <input type="number" id="inpTarget" class="hb-input" min="1" max="500" value="10" style="margin:0.35rem 0 0.75rem">
                        <label class="hb-muted" style="font-size:0.8rem">Onboarding date</label>
                        <input type="date" id="inpDate" class="hb-input" value="{{ now()->toDateString() }}" style="margin:0.35rem 0 0.75rem">
                        <button type="button" class="hb-btn hb-btn--primary" id="btnCreateDraft" style="width:100%">Create draft (auto name)</button>
                        <p style="font-size:0.72rem;color:var(--muted);margin:0.5rem 0 0">Name: <code>District-batchN-Mon-Year</code></p>
                    </div>
                </div>
                <div class="hb-card" id="activeDraftCard" style="display:none;margin-top:1rem">
                    <h2>Current draft</h2>
                    <p id="draftName" style="font-weight:700;color:var(--accent);margin:0"></p>
                    <span id="draftCountBadge" style="float:right;font-size:0.75rem;background:#d1fae5;padding:0.2rem 0.5rem;border-radius:999px;font-weight:700"></span>
                    <div id="hubStageMixWrap" style="clear:both;margin-top:0.75rem;padding-top:0.75rem;border-top:1px solid var(--border)">
                        <p style="font-size:0.7rem;color:var(--muted);margin:0 0 0.35rem;line-height:1.4">Onboarding mix (contract): <strong>10% Growth</strong> · <strong>60% Early</strong> · <strong>30% Seed</strong> — counts update as you add applicants.</p>
                        <div class="hb-stage-mix" id="hubStageMix" aria-live="polite">
                            <div class="hb-stage-mix__box hb-stage-mix__box--seed">
                                <div class="lbl">Seed</div>
                                <div class="cnt" id="cntSeed">0</div>
                                <div class="ideal">Ideal <span id="idealSeed">0</span></div>
                            </div>
                            <div class="hb-stage-mix__box hb-stage-mix__box--early">
                                <div class="lbl">Early</div>
                                <div class="cnt" id="cntEarly">0</div>
                                <div class="ideal">Ideal <span id="idealEarly">0</span></div>
                            </div>
                            <div class="hb-stage-mix__box hb-stage-mix__box--growth">
                                <div class="lbl">Growth</div>
                                <div class="cnt" id="cntGrowth">0</div>
                                <div class="ideal">Ideal <span id="idealGrowth">0</span></div>
                            </div>
                        </div>
                        <p class="hb-stage-mix__note" id="stageUnknownNote" style="display:none">Some rows have no Seed/Early/Growth stage in the form — they are not counted in the three boxes.</p>
                    </div>
                    <ul id="draftMembers" style="list-style:none;padding:0;margin:0.75rem 0;max-height:200px;overflow:auto;font-size:0.875rem"></ul>
                    <button type="button" class="hb-btn hb-btn--dark" id="btnLock" style="width:100%;margin-bottom:0.5rem">Lock batch</button>
                    <button type="button" class="hb-btn hb-btn--ghost" id="btnCancelDraft" style="width:100%">Cancel draft</button>
                </div>
                <div class="hb-card" style="margin-top:1rem">
                    <h2>Incubate later</h2>
                    <button type="button" class="hb-btn hb-btn--ghost" id="btnShowLater" style="width:100%">Show later list</button>
                </div>
            </div>
            <div>
                <div class="hb-card" style="margin-bottom:1rem">
                    <input type="search" id="inpSearch" class="hb-input" placeholder="Search application no., name, phone…">
                </div>
                <div class="hb-card" style="padding:0;overflow:hidden">
                    <div style="padding:0.65rem 1rem;border-bottom:1px solid var(--border);background:#f8fafc;font-weight:700;font-size:0.9rem">CFA choice pool</div>
                    <div class="hb-table-wrap">
                        <table class="hb-table">
                            <thead><tr><th>App</th><th>Name</th><th>Stage</th><th style="text-align:right">Actions</th></tr></thead>
                            <tbody id="poolBody">
                                <tr><td colspan="4" style="text-align:center;color:var(--muted);padding:2rem">Select a district.</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="hb-card" style="margin-top:1.5rem">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0.75rem">
                <h2 style="margin:0">All batches (this hub)</h2>
                <button type="button" class="link-mini" id="btnRefreshBatches">Refresh</button>
            </div>
            <div class="hb-table-wrap" style="max-height:none">
                <table class="hb-table">
                    <thead>
                        <tr>
                            <th>Batch</th>
                            <th>District</th>
                            <th>Status</th>
                            <th>Members</th>
                            <th>CDO PDF</th>
                            <th style="text-align:right">Upload</th>
                        </tr>
                    </thead>
                    <tbody id="batchesBody"></tbody>
                </table>
            </div>
        </div>
    </main>

    <div class="hb-modal-bg" id="modalRatioGap">
        <div class="hb-modal">
            <h3 style="margin:0 0 0.5rem">Onboarding mix (contract)</h3>
            <p id="ratioGapText" style="margin:0;font-size:0.875rem;color:var(--muted);line-height:1.5"></p>
            <div style="display:flex;gap:0.75rem;margin-top:1.25rem">
                <button type="button" class="hb-btn hb-btn--ghost" id="modalRatioGapBack" style="flex:1">Back</button>
                <button type="button" class="hb-btn hb-btn--primary" id="modalRatioGapContinue" style="flex:1">Continue to lock</button>
            </div>
        </div>
    </div>

    <div class="hb-modal-bg" id="modalLock">
        <div class="hb-modal">
            <h3 style="margin:0 0 0.5rem">Lock this batch?</h3>
            <p style="margin:0;font-size:0.875rem;color:var(--muted)">After lock, incubatees stay in this batch. Upload CDO signed PDF within <strong>7 days</strong>.</p>
            <div style="display:flex;gap:0.75rem;margin-top:1.25rem">
                <button type="button" class="hb-btn hb-btn--ghost" id="modalLockCancel" style="flex:1">Cancel</button>
                <button type="button" class="hb-btn hb-btn--primary" id="modalLockOk" style="flex:1">Yes, lock</button>
            </div>
        </div>
    </div>

    <div class="hb-modal-bg" id="modalLater">
        <div class="hb-modal" style="max-height:85vh;display:flex;flex-direction:column">
            <div style="display:flex;justify-content:space-between;align-items:center">
                <h3 style="margin:0">Later — restore</h3>
                <button type="button" id="modalLaterClose" style="border:none;background:none;font-size:1.25rem;cursor:pointer">&times;</button>
            </div>
            <div id="laterListBody" style="overflow:auto;margin-top:0.75rem;font-size:0.875rem"></div>
        </div>
    </div>

    <script>
    (function () {
        const CSRF = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        const API_URL = @json(route('hub.batches.api'));
        const UPLOAD_URL = @json(route('hub.batches.upload-cdo'));
        let currentDistrictId = 0;
        let currentBatchId = 0;
        let blocked = @json($stats['blocked']);
        let searchTimer = null;
        let mixState = { seed: 0, early: 0, growth: 0, unknown: 0, count: 0, targetN: 0 };

        function idealMixFromN(N) {
            N = Math.max(0, parseInt(N, 10) || 0);
            if (!N) return { seed: 0, early: 0, growth: 0 };
            const growth = Math.round(0.1 * N);
            const early = Math.round(0.6 * N);
            const seed = N - growth - early;
            return { seed, early, growth };
        }

        function ratioGapApplies() {
            const { seed, early, growth, unknown, count, targetN } = mixState;
            const ideal = idealMixFromN(targetN);
            if (unknown > 0) return true;
            if (!targetN || count === 0) return false;
            if (count !== targetN) return false;
            return seed !== ideal.seed || early !== ideal.early || growth !== ideal.growth;
        }

        function buildRatioGapMessage() {
            const { seed, early, growth, unknown, count, targetN } = mixState;
            const ideal = idealMixFromN(targetN);
            const parts = [];
            parts.push('The contract onboarding mix for a full batch of <strong>' + esc(targetN) + '</strong> is <strong>10% Growth</strong>, <strong>60% Early</strong>, <strong>30% Seed</strong> (ideal: Seed ' + ideal.seed + ', Early ' + ideal.early + ', Growth ' + ideal.growth + ').');
            if (unknown > 0) {
                parts.push('<br><br><strong>' + unknown + '</strong> applicant(s) could not be classified as Seed, Early, or Growth from the form data.');
            }
            if (count === targetN) {
                parts.push('<br><br>Classified in this batch: Seed <strong>' + seed + '</strong>, Early <strong>' + early + '</strong>, Growth <strong>' + growth + '</strong>.');
            }
            parts.push('<br><br>This batch does not fully match that contract mix. You can still lock. Please try to align the next batch closer to the split.');
            document.getElementById('ratioGapText').innerHTML = parts.join('');
        }

        function openLockFlow() {
            if (!currentBatchId) return;
            if (ratioGapApplies()) {
                buildRatioGapMessage();
                document.getElementById('modalRatioGap').classList.add('is-open');
            } else {
                document.getElementById('modalLock').classList.add('is-open');
            }
        }

        function closeRatioGap() {
            document.getElementById('modalRatioGap').classList.remove('is-open');
        }

        async function api(action, body = {}) {
            const r = await fetch(API_URL, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': CSRF,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify(Object.assign({ action }, body))
            });
            const data = await r.json().catch(() => ({}));
            if (!r.ok) throw new Error(data.error || data.message || 'Request failed');
            return data;
        }

        function esc(s) {
            const d = document.createElement('div');
            d.textContent = s == null ? '' : String(s);
            return d.innerHTML;
        }

        async function loadPool() {
            const tbody = document.getElementById('poolBody');
            const q = document.getElementById('inpSearch').value.trim();
            if (!currentDistrictId) {
                tbody.innerHTML = '<tr><td colspan="4" style="text-align:center;color:var(--muted);padding:2rem">Select a district.</td></tr>';
                return;
            }
            tbody.innerHTML = '<tr><td colspan="4" style="text-align:center;padding:1.5rem">Loading…</td></tr>';
            try {
                const data = await api('pool_list', { district_id: currentDistrictId, q });
                const rows = data.candidates || [];
                if (!rows.length) {
                    tbody.innerHTML = '<tr><td colspan="4" style="text-align:center;color:var(--muted);padding:2rem">No eligible CFA in pool.</td></tr>';
                    return;
                }
                tbody.innerHTML = rows.map(c => `
                    <tr>
                        <td style="font-family:monospace;font-size:0.75rem">${esc(c.application_no)}</td>
                        <td>${esc(c.applicant_name)}</td>
                        <td><span style="background:#f1f5f9;padding:0.15rem 0.45rem;border-radius:6px;font-size:0.75rem">${esc(c.stage)}</span></td>
                        <td style="text-align:right;white-space:nowrap">
                            <button type="button" class="add-btn hb-btn hb-btn--primary" style="padding:0.25rem 0.5rem;font-size:0.75rem" data-id="${c.id}" ${blocked || !currentBatchId ? 'disabled' : ''}>Add</button>
                            <button type="button" class="link-mini" data-later="${c.id}">Later</button>
                            <button type="button" class="link-mini" style="color:#dc2626" data-reject="${c.id}">Reject</button>
                        </td>
                    </tr>`).join('');
                tbody.querySelectorAll('.add-btn').forEach(btn => btn.addEventListener('click', () => addToDraft(parseInt(btn.dataset.id, 10))));
                tbody.querySelectorAll('[data-later]').forEach(btn => btn.addEventListener('click', () => setChoice(parseInt(btn.dataset.later, 10), 'later')));
                tbody.querySelectorAll('[data-reject]').forEach(btn => btn.addEventListener('click', () => setChoice(parseInt(btn.dataset.reject, 10), 'reject')));
            } catch (e) {
                tbody.innerHTML = '<tr><td colspan="4" style="text-align:center;color:#dc2626;padding:1rem">' + esc(e.message) + '</td></tr>';
            }
        }

        async function setChoice(cfaId, state) {
            if (!currentDistrictId) return;
            try {
                await api('set_choice', { cfa_submission_id: cfaId, district_id: currentDistrictId, state });
                loadPool();
            } catch (e) { alert(e.message); }
        }

        async function addToDraft(cfaId) {
            if (!currentBatchId) return;
            try {
                const data = await api('add_to_draft', { batch_id: currentBatchId, cfa_submission_id: cfaId });
                await loadDraft();
                loadPool();
                if (data.ready_lock) openLockFlow();
            } catch (e) { alert(e.message); }
        }

        async function loadDraft() {
            const card = document.getElementById('activeDraftCard');
            if (!currentBatchId) { card.style.display = 'none'; return; }
            try {
                const data = await api('draft_members', { batch_id: currentBatchId });
                const b = data.batch;
                const ts = parseInt(b.target_size, 10) || 0;
                document.getElementById('draftName').textContent = b.name;
                document.getElementById('draftCountBadge').textContent = (data.count || 0) + ' / ' + ts;
                const ul = document.getElementById('draftMembers');
                const mem = data.members || [];
                let seed = 0, early = 0, growth = 0, unknown = 0;
                mem.forEach(m => {
                    const k = String(m.stage_key || 'unknown').toLowerCase();
                    if (k === 'seed') seed++;
                    else if (k === 'early') early++;
                    else if (k === 'growth') growth++;
                    else unknown++;
                });
                mixState = { seed, early, growth, unknown, count: mem.length, targetN: ts };
                document.getElementById('cntSeed').textContent = String(seed);
                document.getElementById('cntEarly').textContent = String(early);
                document.getElementById('cntGrowth').textContent = String(growth);
                const ideal = idealMixFromN(ts);
                document.getElementById('idealSeed').textContent = String(ideal.seed);
                document.getElementById('idealEarly').textContent = String(ideal.early);
                document.getElementById('idealGrowth').textContent = String(ideal.growth);
                const unkNote = document.getElementById('stageUnknownNote');
                if (unkNote) unkNote.style.display = unknown > 0 ? 'block' : 'none';
                ul.innerHTML = mem.length ? mem.map(m => `<li style="display:flex;justify-content:space-between;border-bottom:1px solid #f1f5f9;padding:0.35rem 0">
                    <span>${esc(m.applicant_name)} <span style="opacity:0.6;font-size:0.75rem">${esc(m.application_no)}</span></span>
                    ${blocked ? '' : '<button type="button" class="link-mini rm" data-rm="' + m.id + '">Remove</button>'}
                </li>`).join('') : '<li style="color:var(--muted);font-style:italic">No incubatees yet.</li>';
                ul.querySelectorAll('.rm').forEach(btn => btn.addEventListener('click', async () => {
                    try {
                        await api('remove_from_draft', { batch_id: currentBatchId, cfa_submission_id: parseInt(btn.dataset.rm, 10) });
                        loadDraft(); loadPool();
                    } catch (e) { alert(e.message); }
                }));
                card.style.display = 'block';
            } catch (e) {
                currentBatchId = 0;
                card.style.display = 'none';
            }
            document.querySelectorAll('.add-btn').forEach(x => { x.disabled = blocked || !currentBatchId; });
        }

        async function tryResumeDraft() {
            if (!currentDistrictId) return;
            try {
                const data = await api('batches_list', {});
                const drafts = (data.batches || []).filter(x => x.status === 'draft' && parseInt(x.district_id, 10) === currentDistrictId);
                currentBatchId = drafts.length ? parseInt(drafts[0].id, 10) : 0;
                await loadDraft();
                loadPool();
            } catch (e) { console.error(e); }
        }

        async function loadBatches() {
            const tbody = document.getElementById('batchesBody');
            try {
                const data = await api('batches_list', {});
                const rows = data.batches || [];
                tbody.innerHTML = rows.length ? rows.map(b => {
                    let cdo = '—';
                    if (b.status === 'locked' && b.locked_at) {
                        if (b.has_cdo_pdf) cdo = '<span style="color:var(--accent);font-weight:600">Uploaded</span>';
                        else if (b.cdo_overdue) cdo = '<span style="color:#dc2626;font-weight:700">Overdue</span>';
                        else if (b.cdo_pending) cdo = '<span style="color:#d97706;font-weight:600">Pending</span>';
                    }
                    const up = (b.status === 'locked' && b.locked_at && !b.has_cdo_pdf)
                        ? `<form action="${UPLOAD_URL}" method="post" enctype="multipart/form-data" style="display:flex;gap:0.35rem;justify-content:flex-end;align-items:center">
                            <input type="hidden" name="_token" value="${CSRF}">
                            <input type="hidden" name="onboarding_batch_id" value="${b.id}">
                            <input type="file" name="file" accept=".pdf" required style="font-size:0.7rem;max-width:140px">
                            <button class="hb-btn hb-btn--primary" style="padding:0.25rem 0.5rem;font-size:0.75rem">PDF</button>
                           </form>`
                        : '';
                    const st = b.status === 'draft'
                        ? '<span style="background:#fef3c7;color:#92400e;padding:0.15rem 0.5rem;border-radius:999px;font-size:0.72rem;font-weight:700">Draft</span>'
                        : '<span style="background:#d1fae5;color:#065f46;padding:0.15rem 0.5rem;border-radius:999px;font-size:0.72rem;font-weight:700">Locked</span>';
                    return `<tr>
                        <td style="font-weight:600">${esc(b.name)}</td>
                        <td>${esc(b.district_name)}</td>
                        <td>${st}</td>
                        <td>${b.member_count}</td>
                        <td>${cdo}</td>
                        <td style="text-align:right">${up}</td>
                    </tr>`;
                }).join('') : '<tr><td colspan="6" style="text-align:center;color:var(--muted);padding:1.5rem">No batches yet.</td></tr>';
            } catch (e) {
                tbody.innerHTML = '<tr><td colspan="6" style="color:#dc2626">' + esc(e.message) + '</td></tr>';
            }
        }

        document.getElementById('selDistrict').addEventListener('change', async (e) => {
            currentDistrictId = parseInt(e.target.value, 10) || 0;
            document.getElementById('draftCreateWrap').style.display = currentDistrictId ? 'block' : 'none';
            currentBatchId = 0;
            document.getElementById('activeDraftCard').style.display = 'none';
            if (currentDistrictId) await tryResumeDraft();
            else loadPool();
        });

        document.getElementById('inpSearch').addEventListener('input', () => {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(loadPool, 280);
        });

        document.getElementById('btnCreateDraft').addEventListener('click', async () => {
            if (blocked) { alert('Blocked until overdue CDO PDFs are resolved.'); return; }
            if (!currentDistrictId) return;
            try {
                const data = await api('create_draft', {
                    district_id: currentDistrictId,
                    target_size: parseInt(document.getElementById('inpTarget').value, 10),
                    onboarding_date: document.getElementById('inpDate').value
                });
                currentBatchId = data.batch_id;
                await loadDraft();
                loadPool();
                loadBatches();
            } catch (e) { alert(e.message); }
        });

        document.getElementById('btnCancelDraft').addEventListener('click', async () => {
            if (!currentBatchId || !confirm('Cancel this draft?')) return;
            try {
                await api('cancel_draft', { batch_id: currentBatchId });
                currentBatchId = 0;
                document.getElementById('activeDraftCard').style.display = 'none';
                loadPool();
                loadBatches();
            } catch (e) { alert(e.message); }
        });

        function closeLock() { document.getElementById('modalLock').classList.remove('is-open'); }
        document.getElementById('modalLockCancel').addEventListener('click', closeLock);
        document.getElementById('modalLockOk').addEventListener('click', async () => {
            try {
                await api('lock_batch', { batch_id: currentBatchId, confirm: true });
                closeLock();
                closeRatioGap();
                currentBatchId = 0;
                document.getElementById('activeDraftCard').style.display = 'none';
                loadPool();
                loadBatches();
                alert('Batch locked. Upload CDO signed PDF within 7 days.');
            } catch (e) { alert(e.message); }
        });
        document.getElementById('btnLock').addEventListener('click', openLockFlow);
        document.getElementById('modalRatioGapBack').addEventListener('click', closeRatioGap);
        document.getElementById('modalRatioGapContinue').addEventListener('click', () => {
            closeRatioGap();
            document.getElementById('modalLock').classList.add('is-open');
        });

        document.getElementById('btnRefreshBatches').addEventListener('click', loadBatches);

        document.getElementById('btnShowLater').addEventListener('click', async () => {
            try {
                const data = await api('later_list', {});
                const body = document.getElementById('laterListBody');
                const rows = data.rows || [];
                body.innerHTML = rows.length ? rows.map(r => `
                    <div style="display:flex;justify-content:space-between;align-items:center;padding:0.5rem;border:1px solid var(--border);border-radius:8px;margin-bottom:0.35rem">
                        <div><strong>${esc(r.applicant_name)}</strong><br><span style="font-size:0.75rem;opacity:0.7">${esc(r.application_no)}</span></div>
                        <button type="button" class="link-mini restore-later" data-app="${r.application_id}" data-dist="${r.district_id}">Restore</button>
                    </div>`).join('') : '<p style="color:var(--muted)">No entries.</p>';
                body.querySelectorAll('.restore-later').forEach(btn => btn.addEventListener('click', async () => {
                    try {
                        await api('restore_later', { cfa_submission_id: parseInt(btn.dataset.app, 10), district_id: parseInt(btn.dataset.dist, 10) });
                        btn.closest('div').remove();
                        loadPool();
                    } catch (e) { alert(e.message); }
                }));
                document.getElementById('modalLater').classList.add('is-open');
            } catch (e) { alert(e.message); }
        });
        document.getElementById('modalLaterClose').addEventListener('click', () => document.getElementById('modalLater').classList.remove('is-open'));

        loadBatches();
    })();
    </script>
</body>
</html>
