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
            --accent: #d04a02;
            --accent2: #eb8c00;
            --radius: 12px;
            --shadow: 0 1px 2px rgba(15, 23, 42, 0.05), 0 8px 24px rgba(208, 74, 2, 0.08);
        }
        body.hub-batch-page { font-family: 'DM Sans', system-ui, sans-serif; }
        .hb-wrap { max-width: 1200px; margin: 0 auto; }
        .hb-hero {
            background: linear-gradient(135deg, #a63d02 0%, #d04a02 55%, #eb8c00 100%);
            color: #fff;
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
        .hb-stage-mix__box--growth { background: linear-gradient(180deg, #fdeee6, #fff); border-color: #f5c4a8; }
        .hb-stage-mix__note { font-size: 0.65rem; color: #92400e; margin: 0.5rem 0 0; line-height: 1.4; }
        .hb-pool-tools { display: flex; flex-wrap: wrap; gap: 0.45rem; align-items: center; padding: 0.6rem 0.8rem; border-bottom: 1px solid var(--border); background: #fcfcfd; }
        .hb-pool-tools .hb-btn { padding: 0.32rem 0.55rem; font-size: 0.72rem; border-radius: 8px; }
        .hb-pool-tools__note { margin-left: auto; font-size: 0.72rem; color: var(--muted); }
        .hb-pool-check { width: 16px; height: 16px; vertical-align: middle; }
        .hb-batch-link {
            background: none;
            border: none;
            padding: 0;
            color: #d04a02;
            font-weight: 700;
            text-align: left;
            cursor: pointer;
            text-decoration: underline;
            text-decoration-thickness: 1px;
            text-underline-offset: 2px;
        }
        .hb-batch-link:hover { color: #0f172a; }
        .hb-row-active td { background: #ecfeff !important; }
        .hb-detail-card {
            margin-top: 1rem;
            border: 1px solid #bae6fd;
            background: linear-gradient(180deg, #f0fdfa 0%, #ffffff 85%);
            border-radius: 12px;
            padding: 1rem;
            display: none;
        }
        .hb-detail-card.is-open { display: block; }
        .hb-detail-head { display: flex; justify-content: space-between; gap: 0.75rem; align-items: center; margin-bottom: 0.8rem; }
        .hb-detail-title { margin: 0; font-size: 1rem; color: #0f172a; }
        .hb-detail-sub { margin: 0.2rem 0 0; font-size: 0.78rem; color: var(--muted); }
        .hb-kpi-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 0.6rem; margin-bottom: 0.8rem; }
        .hb-kpi {
            border: 1px solid #dbeafe;
            border-radius: 10px;
            padding: 0.65rem 0.75rem;
            background: #fff;
            cursor: pointer;
            transition: border-color 0.15s ease, box-shadow 0.15s ease, transform 0.15s ease;
        }
        .hb-kpi:hover { border-color: #7dd3fc; transform: translateY(-1px); }
        .hb-kpi.is-active { border-color: #0ea5e9; box-shadow: 0 0 0 2px rgba(14, 165, 233, 0.12); }
        .hb-kpi .k { font-size: 0.62rem; text-transform: uppercase; letter-spacing: 0.04em; color: var(--muted); font-weight: 700; }
        .hb-kpi .v { font-size: 1.2rem; font-weight: 800; color: #0f172a; margin-top: 0.2rem; }
        .hb-filter-chips { display: flex; flex-wrap: wrap; gap: 0.4rem; margin: 0.1rem 0 0.7rem; }
        .hb-filter-chip {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.22rem 0.5rem;
            border-radius: 999px;
            border: 1px solid #67e8f9;
            background: #ecfeff;
            color: #0f172a;
            font-size: 0.74rem;
            font-weight: 600;
            cursor: pointer;
        }
        .hb-filter-chip:hover { background: #cffafe; }
        .hb-filter-chip .x { font-size: 0.85rem; line-height: 1; color: #d04a02; }
        .hb-detail-grid { display: grid; grid-template-columns: 1.2fr 1fr; gap: 0.75rem; }
        .hb-detail-box {
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 0.7rem;
            background: #fff;
        }
        .hb-detail-box h4 { margin: 0 0 0.55rem; font-size: 0.72rem; color: var(--muted); text-transform: uppercase; letter-spacing: 0.04em; }
        .hb-cat-list { margin: 0; padding: 0; list-style: none; max-height: 230px; overflow: auto; }
        .hb-cat-list li { font-size: 0.82rem; padding: 0.2rem 0; border-bottom: 1px dashed #e2e8f0; }
        .hb-cat-list li:last-child { border-bottom: none; }
        .hb-cat-btn {
            width: 100%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border: 1px solid transparent;
            background: transparent;
            border-radius: 8px;
            padding: 0.3rem 0.35rem;
            cursor: pointer;
            color: #0f172a;
            text-align: left;
        }
        .hb-cat-btn:hover { background: #f0f9ff; border-color: #bae6fd; }
        .hb-cat-btn.is-active { background: #ecfeff; border-color: #67e8f9; }
        .hb-member-link { color: #d04a02; font-weight: 600; text-decoration: underline; text-underline-offset: 2px; }
        .hb-member-link:hover { color: #0f172a; }
        .hb-member-table-wrap { max-height: min(52vh, 480px); overflow: auto; border: 1px solid #e2e8f0; border-radius: 8px; }
        .hb-portal-note { font-size: 0.72rem; color: var(--muted); margin: 0 0 0.45rem; line-height: 1.4; }
        .hb-member-table { width: 100%; border-collapse: collapse; font-size: 0.78rem; }
        .hb-member-table th, .hb-member-table td { padding: 0.45rem 0.5rem; border-bottom: 1px solid #f1f5f9; text-align: left; }
        .hb-member-table th { background: #f8fafc; color: var(--muted); font-size: 0.65rem; text-transform: uppercase; position: sticky; top: 0; }
        @media (max-width: 980px) {
            .hb-kpi-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .hb-detail-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body class="admin-app-body admin-app-body--dashboard admin-app-body--hub-premium hub-batch-page">
    @include('partials.admin-topbar')
    <main class="admin-main hb-wrap">
        @if (session('status'))
            <div class="hb-banner hb-banner--ok">{{ session('status') }}</div>
        @endif

        <div class="hb-hero">
            <h1>Batch &amp; CFA pool</h1>
            <p>Pick CFA only, reject / later, fixed batch size (N), keep draft open across days, then lock and upload Onboarding Letter within 7 days.</p>
            <div class="hb-stats">
                <div class="hb-stat"><div class="l">PDF pending</div><div class="v" id="statPending">{{ (int) $stats['pending_cdo'] }}</div></div>
                <div class="hb-stat"><div class="l">Overdue</div><div class="v" id="statOverdue" style="color:{{ $stats['overdue_cdo'] ? '#dc2626' : '#94a3b8' }}">{{ (int) $stats['overdue_cdo'] }}</div></div>
            </div>
        </div>

        <div id="blockedBanner" class="hb-banner hb-banner--warn" style="display:{{ $stats['blocked'] ? 'block' : 'none' }}">
            <strong>New batches paused:</strong> upload Onboarding Letter for overdue locked batches, or ask state admin to extend / waive.
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
                    <button type="button" class="hb-btn hb-btn--primary" id="btnEditCurrentBatch" style="width:100%;margin-bottom:0.5rem">Edit batch size / details</button>
                    <button type="button" class="hb-btn hb-btn--ghost" id="btnCancelDraft" style="width:100%">Cancel draft</button>
                </div>
                <div class="hb-card" style="margin-top:1rem">
                    <h2>Incubate later</h2>
                    <button type="button" class="hb-btn hb-btn--ghost" id="btnShowLater" style="width:100%">Show later list</button>
                </div>
            </div>
            <div>
                <div class="hb-card" style="margin-bottom:1rem">
                    <div style="display:grid;grid-template-columns:1fr 200px;gap:0.6rem;align-items:end">
                        <input type="search" id="inpSearch" class="hb-input" placeholder="Search application no., name, phone…">
                        <div>
                            <label style="font-size:0.72rem;color:var(--muted);display:block;margin-bottom:0.28rem">Fiscal year</label>
                            <select id="selFiscalYear" class="hb-select">
                                @foreach ($fiscalYears as $fy)
                                    <option value="{{ $fy->id }}" @selected((int) $selectedFiscalYearId === (int) $fy->id)>{{ $fy->code }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
                <div class="hb-card" style="padding:0;overflow:hidden">
                    <div style="padding:0.65rem 1rem;border-bottom:1px solid var(--border);background:#f8fafc;font-weight:700;font-size:0.9rem">CFA choice pool</div>
                    <div class="hb-pool-tools">
                        <button type="button" class="hb-btn hb-btn--ghost" id="btnSelectAllVisible">Select visible</button>
                        <button type="button" class="hb-btn hb-btn--ghost" id="btnClearSelection">Clear</button>
                        <button type="button" class="hb-btn hb-btn--ghost" id="btnAutoSelectMix">Auto-select for ideal mix</button>
                        <button type="button" class="hb-btn hb-btn--primary" id="btnAddSelected">Add selected</button>
                        <span class="hb-pool-tools__note" id="poolBulkStatus">0 selected</span>
                    </div>
                    <div class="hb-table-wrap">
                        <table class="hb-table">
                            <thead><tr><th style="width:46px">Pick</th><th>App</th><th>Name</th><th>Stage</th><th style="text-align:right">Actions</th></tr></thead>
                            <tbody id="poolBody">
                                <tr><td colspan="5" style="text-align:center;color:var(--muted);padding:2rem">Select a district.</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="hb-card" style="padding:0;overflow:hidden;margin-top:1rem">
                    <div style="padding:0.65rem 1rem;border-bottom:1px solid var(--border);background:#f8fafc;font-weight:700;font-size:0.9rem;display:flex;justify-content:space-between;align-items:center;gap:0.5rem;">
                        <span>Later list (current district)</span>
                        <span id="laterInlineCount" style="font-size:0.75rem;color:var(--muted);font-weight:600;">0</span>
                    </div>
                    <div class="hb-table-wrap" style="max-height:260px;">
                        <table class="hb-table">
                            <thead><tr><th>App</th><th>Name</th><th style="text-align:right">Actions</th></tr></thead>
                            <tbody id="laterInlineBody">
                                <tr><td colspan="3" style="text-align:center;color:var(--muted);padding:1rem">Select a district to view later list.</td></tr>
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
                            <th>Onboarding Letter</th>
                            <th style="text-align:right">Upload</th>
                            <th style="text-align:right">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="batchesBody"></tbody>
                </table>
            </div>
            <div class="hb-detail-card" id="batchDetailCard" aria-live="polite">
                <div class="hb-detail-head">
                    <div>
                        <h3 class="hb-detail-title" id="batchDetailTitle">Batch insight</h3>
                        <p class="hb-detail-sub" id="batchDetailMeta">Click any batch name to view KPI breakdown.</p>
                    </div>
                    <div style="display:flex;flex-wrap:wrap;gap:0.5rem;align-items:center">
                        <button type="button" class="hb-btn hb-btn--primary" id="btnProvisionIncubatees" style="display:none;padding:0.35rem 0.65rem;font-size:0.76rem" title="Creates incubatee users on the server (no SSH needed)">Create portal logins</button>
                        <button type="button" class="hb-btn hb-btn--ghost" id="btnCloseBatchDetail" style="padding:0.35rem 0.65rem;font-size:0.76rem">Close</button>
                    </div>
                </div>
                <div class="hb-kpi-grid">
                    <div class="hb-kpi" id="kpiCardMembers"><div class="k">Members (all)</div><div class="v" id="kpiMembers">0</div></div>
                    <div class="hb-kpi" id="kpiCardSeed"><div class="k">Seed</div><div class="v" id="kpiSeed">0</div></div>
                    <div class="hb-kpi" id="kpiCardEarly"><div class="k">Early</div><div class="v" id="kpiEarly">0</div></div>
                    <div class="hb-kpi" id="kpiCardGrowth"><div class="k">Growth</div><div class="v" id="kpiGrowth">0</div></div>
                </div>
                <div id="batchDetailFilters" class="hb-filter-chips" style="display:none"></div>
                <div class="hb-detail-grid">
                    <div class="hb-detail-box">
                        <h4>Member List</h4>
                        <p class="hb-portal-note" id="batchPortalLoginNote">Loading portal login hints…</p>
                        <div class="hb-member-table-wrap" id="batchDetailMembersWrap"></div>
                    </div>
                    <div class="hb-detail-box">
                        <h4>Business Category Mix</h4>
                        <ul class="hb-cat-list" id="batchCategoryMixList"></ul>
                        <h4 style="margin-top:0.85rem;">Applicant Category</h4>
                        <ul class="hb-cat-list" id="batchApplicantCategoryList"></ul>
                        <h4 style="margin-top:0.85rem;">SHG / Lakhpati Didi</h4>
                        <ul class="hb-cat-list" id="batchInclusionFlagsList"></ul>
                    </div>
                </div>
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
            <p style="margin:0;font-size:0.875rem;color:var(--muted)">After lock, incubatees stay in this batch. Upload Onboarding Letter within <strong>7 days</strong>.</p>
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

    <div class="hb-modal-bg" id="modalEditBatch">
        <div class="hb-modal">
            <h3 style="margin:0 0 0.75rem">Edit batch</h3>
            <label style="font-size:0.78rem;color:var(--muted)">Batch name</label>
            <input type="text" id="editBatchName" class="hb-input" maxlength="120" style="margin:0.35rem 0 0.75rem">
            <label style="font-size:0.78rem;color:var(--muted)">Target size (N)</label>
            <input type="number" id="editBatchTarget" class="hb-input" min="1" max="500" style="margin:0.35rem 0 0.75rem">
            <label style="font-size:0.78rem;color:var(--muted)">Onboarding date</label>
            <input type="date" id="editBatchDate" class="hb-input" style="margin:0.35rem 0 0.75rem">
            <p style="margin:0;font-size:0.72rem;color:var(--muted)">Note: draft and approved edit-unlocked batches can be edited.</p>
            <div style="display:flex;gap:0.75rem;margin-top:1.1rem">
                <button type="button" class="hb-btn hb-btn--ghost" id="editBatchCancel" style="flex:1">Cancel</button>
                <button type="button" class="hb-btn hb-btn--primary" id="editBatchSave" style="flex:1">Save</button>
            </div>
        </div>
    </div>

    <div class="hb-modal-bg" id="modalUnlockRequest">
        <div class="hb-modal">
            <h3 style="margin:0 0 0.75rem">Request locked-batch edit approval</h3>
            <label style="font-size:0.78rem;color:var(--muted)">Reason</label>
            <textarea id="unlockReqReason" class="hb-input" rows="3" style="margin:0.35rem 0 0.75rem;resize:vertical;"></textarea>
            <label style="font-size:0.78rem;color:var(--muted)">Expected changes summary</label>
            <textarea id="unlockReqExpected" class="hb-input" rows="4" style="margin:0.35rem 0 0.75rem;resize:vertical;"></textarea>
            <p style="margin:0;font-size:0.72rem;color:var(--muted)">State admin approval is required. Multiple requests are allowed.</p>
            <div style="display:flex;gap:0.75rem;margin-top:1.1rem">
                <button type="button" class="hb-btn hb-btn--ghost" id="unlockReqCancel" style="flex:1">Cancel</button>
                <button type="button" class="hb-btn hb-btn--primary" id="unlockReqSubmit" style="flex:1">Submit request</button>
            </div>
        </div>
    </div>

    <script>
    (function () {
        const CSRF = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        const API_URL = @json(route('hub.batches.api'));
        const UPLOAD_URL = @json(route('hub.batches.upload-cdo'));
        const LETTER_VIEW_URL_TEMPLATE = @json(route('hub.batches.onboarding-letter', ['batch' => '__BATCH_ID__']));
        let currentDistrictId = 0;
        let currentBatchId = 0;
        let blocked = @json($stats['blocked']);
        let searchTimer = null;
        let selectedFiscalYearId = parseInt(@json($selectedFiscalYearId), 10) || 0;
        let mixState = { seed: 0, early: 0, growth: 0, unknown: 0, count: 0, targetN: 0 };
        let latestPoolRows = [];
        let bulkAddBusy = false;
        let latestBatches = [];
        let editingBatchId = 0;
        let unlockRequestBatchId = 0;
        let openedBatchDetailId = 0;
        let batchDetailData = null;
        let detailFilters = { stage: '', category: '' };

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

        function normalizeStageKey(stage) {
            const s = String(stage || '').trim().toLowerCase();
            if (s === 'seed') return 'seed';
            if (s === 'early') return 'early';
            if (s === 'growth') return 'growth';
            return 'unknown';
        }

        function selectedPoolIds() {
            return Array.from(document.querySelectorAll('.pool-pick:checked'))
                .map(x => parseInt(x.value, 10))
                .filter(Number.isFinite);
        }

        function refreshBulkStatus(label) {
            const el = document.getElementById('poolBulkStatus');
            if (!el) return;
            if (label) {
                el.textContent = label;
                return;
            }
            el.textContent = selectedPoolIds().length + ' selected';
        }

        function markSelectedPoolIds(ids) {
            const set = new Set(ids);
            document.querySelectorAll('.pool-pick').forEach(cb => {
                cb.checked = set.has(parseInt(cb.value, 10));
            });
            refreshBulkStatus();
        }

        async function addSelectedToDraft() {
            if (bulkAddBusy) return;
            if (!currentBatchId) { alert('Create or resume a draft batch first.'); return; }
            const ids = selectedPoolIds();
            if (!ids.length) { alert('Select applicants first.'); return; }
            bulkAddBusy = true;
            const btn = document.getElementById('btnAddSelected');
            const oldText = btn.textContent;
            btn.disabled = true;
            refreshBulkStatus('Adding 0 / ' + ids.length + ' ...');
            let ok = 0;
            let firstErr = '';
            for (let i = 0; i < ids.length; i++) {
                try {
                    await api('add_to_draft', { batch_id: currentBatchId, cfa_submission_id: ids[i] });
                    ok++;
                } catch (e) {
                    firstErr = firstErr || String(e.message || 'Add failed');
                    break;
                }
                if (i % 10 === 0 || i === ids.length - 1) {
                    refreshBulkStatus('Adding ' + (i + 1) + ' / ' + ids.length + ' ...');
                }
            }
            await loadDraft();
            await loadPool();
            btn.disabled = false;
            btn.textContent = oldText;
            bulkAddBusy = false;
            if (firstErr) {
                alert('Added ' + ok + ' applicant(s). Stopped: ' + firstErr);
            } else {
                alert('Added ' + ok + ' applicant(s) to draft.');
            }
            refreshBulkStatus();
        }

        function autoSelectByIdealMix() {
            if (!currentBatchId) { alert('Create or resume a draft batch first.'); return; }
            const remainingSlots = Math.max(0, (mixState.targetN || 0) - (mixState.count || 0));
            if (!remainingSlots) { alert('Draft is already full.'); return; }
            const ideal = idealMixFromN(mixState.targetN || 0);
            const want = {
                seed: Math.max(0, ideal.seed - mixState.seed),
                early: Math.max(0, ideal.early - mixState.early),
                growth: Math.max(0, ideal.growth - mixState.growth),
            };
            const byStage = { seed: [], early: [], growth: [], unknown: [] };
            latestPoolRows.forEach(r => {
                const key = byStage[r.stage_key] ? r.stage_key : 'unknown';
                byStage[key].push(r.id);
            });
            const selected = [];
            ['seed', 'early', 'growth'].forEach(stage => {
                const take = Math.min(want[stage], byStage[stage].length);
                for (let i = 0; i < take; i++) selected.push(byStage[stage][i]);
            });
            if (selected.length < remainingSlots) {
                const picked = new Set(selected);
                for (const row of latestPoolRows) {
                    if (picked.has(row.id)) continue;
                    selected.push(row.id);
                    picked.add(row.id);
                    if (selected.length >= remainingSlots) break;
                }
            }
            markSelectedPoolIds(selected.slice(0, remainingSlots));
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

        function letterViewUrl(batchId) {
            return LETTER_VIEW_URL_TEMPLATE.replace('__BATCH_ID__', String(batchId || ''));
        }

        async function loadPool() {
            const tbody = document.getElementById('poolBody');
            const q = document.getElementById('inpSearch').value.trim();
            latestPoolRows = [];
            if (!currentDistrictId) {
                tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;color:var(--muted);padding:2rem">Select a district.</td></tr>';
                refreshBulkStatus();
                return;
            }
            tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;padding:1.5rem">Loading…</td></tr>';
            try {
                const data = await api('pool_list', { district_id: currentDistrictId, q, fiscal_year_id: selectedFiscalYearId });
                const rows = data.candidates || [];
                latestPoolRows = rows.map(r => ({ id: parseInt(r.id, 10), stage_key: normalizeStageKey(r.stage) })).filter(r => Number.isFinite(r.id));
                if (!rows.length) {
                    tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;color:var(--muted);padding:2rem">No eligible CFA in pool.</td></tr>';
                    refreshBulkStatus();
                    return;
                }
                tbody.innerHTML = rows.map(c => `
                    <tr>
                        <td><input class="hb-pool-check pool-pick" type="checkbox" value="${c.id}" ${blocked || !currentBatchId ? 'disabled' : ''}></td>
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
                tbody.querySelectorAll('.pool-pick').forEach(cb => cb.addEventListener('change', () => refreshBulkStatus()));
                refreshBulkStatus();
            } catch (e) {
                tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;color:#dc2626;padding:1rem">' + esc(e.message) + '</td></tr>';
                refreshBulkStatus();
            }
        }

        async function setChoice(cfaId, state) {
            if (!currentDistrictId) return;
            try {
                await api('set_choice', { cfa_submission_id: cfaId, district_id: currentDistrictId, state });
                loadPool();
                loadLaterInline();
            } catch (e) { alert(e.message); }
        }

        async function restoreLater(cfaId, districtId) {
            try {
                await api('restore_later', { cfa_submission_id: cfaId, district_id: districtId });
                await loadLaterInline();
                await loadPool();
            } catch (e) {
                alert(e.message);
            }
        }

        async function restoreLaterAndAdd(cfaId, districtId) {
            if (!currentBatchId) {
                alert('Create or resume a draft batch first.');
                return;
            }
            try {
                await api('restore_later', { cfa_submission_id: cfaId, district_id: districtId });
                await api('add_to_draft', { batch_id: currentBatchId, cfa_submission_id: cfaId });
                await loadDraft();
                await loadLaterInline();
                await loadPool();
            } catch (e) {
                alert(e.message);
            }
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
                loadLaterInline();
            } catch (e) { console.error(e); }
        }

        async function loadBatches() {
            const tbody = document.getElementById('batchesBody');
            try {
                const data = await api('batches_list', {});
                const rows = data.batches || [];
                latestBatches = rows;
                tbody.innerHTML = rows.length ? rows.map(b => {
                    let cdo = '—';
                    if (b.status === 'locked' && b.locked_at) {
                        if (b.has_cdo_pdf) {
                            cdo = '<span style="color:var(--accent);font-weight:600">Uploaded</span>'
                                + ` <a href="${letterViewUrl(b.id)}" target="_blank" rel="noopener noreferrer" style="margin-left:0.4rem;font-size:0.76rem;font-weight:700;color:#d04a02;text-decoration:underline;">View</a>`;
                        }
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
                    const unlockBadge = (b.status === 'locked' && b.edit_unlocked)
                        ? '<span style="margin-left:0.35rem;background:#dcfce7;color:#166534;padding:0.15rem 0.5rem;border-radius:999px;font-size:0.72rem;font-weight:700">Edit unlocked</span>'
                        : '';
                    const reqBadge = (b.status === 'locked' && !b.edit_unlocked && (parseInt(b.pending_unlock_requests || 0, 10) || 0) > 0)
                        ? '<span style="margin-left:0.35rem;background:#eef2ff;color:#3730a3;padding:0.15rem 0.5rem;border-radius:999px;font-size:0.72rem;font-weight:700">Requests: ' + (parseInt(b.pending_unlock_requests || 0, 10) || 0) + '</span>'
                        : '';
                    let actions = '<span style="color:#94a3b8">—</span>';
                    if (b.status === 'draft') {
                        actions = `<button type="button" class="link-mini batch-continue" data-id="${b.id}">Continue</button>
                           <button type="button" class="link-mini batch-edit" data-id="${b.id}">Edit</button>
                           <button type="button" class="link-mini batch-delete" style="color:#dc2626" data-id="${b.id}">Delete</button>`;
                    } else if (b.status === 'locked' && b.edit_unlocked) {
                        actions = `<button type="button" class="link-mini batch-continue" data-id="${b.id}">Edit</button>
                           <button type="button" class="link-mini batch-relock" style="color:#d04a02" data-id="${b.id}">Re-lock</button>`;
                    } else if (b.status === 'locked') {
                        const reqCount = parseInt(b.pending_unlock_requests || 0, 10) || 0;
                        actions = `<button type="button" class="link-mini batch-request-unlock" data-id="${b.id}">Request unlock</button>
                           <span style="font-size:0.7rem;color:#64748b;margin-left:0.25rem;">Pending: ${reqCount}</span>`;
                    }
                    return `<tr data-batch-row="${b.id}">
                        <td><button type="button" class="hb-batch-link batch-open" data-id="${b.id}" title="View batch KPI details">${esc(b.name)}</button></td>
                        <td>${esc(b.district_name)}</td>
                        <td>${st}${unlockBadge}${reqBadge}</td>
                        <td>${b.member_count}</td>
                        <td>${cdo}</td>
                        <td style="text-align:right">${up}</td>
                        <td style="text-align:right;white-space:nowrap">${actions}</td>
                    </tr>`;
                }).join('') : '<tr><td colspan="7" style="text-align:center;color:var(--muted);padding:1.5rem">No batches yet.</td></tr>';
                tbody.querySelectorAll('.batch-open').forEach(btn => btn.addEventListener('click', () => openBatchDetail(parseInt(btn.dataset.id, 10))));
                tbody.querySelectorAll('.batch-continue').forEach(btn => btn.addEventListener('click', () => continueDraft(parseInt(btn.dataset.id, 10))));
                tbody.querySelectorAll('.batch-edit').forEach(btn => btn.addEventListener('click', () => openEditBatch(parseInt(btn.dataset.id, 10))));
                tbody.querySelectorAll('.batch-delete').forEach(btn => btn.addEventListener('click', () => deleteBatchRow(parseInt(btn.dataset.id, 10))));
                tbody.querySelectorAll('.batch-request-unlock').forEach(btn => btn.addEventListener('click', () => openUnlockRequest(parseInt(btn.dataset.id, 10))));
                tbody.querySelectorAll('.batch-relock').forEach(btn => btn.addEventListener('click', () => relockBatch(parseInt(btn.dataset.id, 10))));
                if (openedBatchDetailId) {
                    highlightBatchRow(openedBatchDetailId);
                }
            } catch (e) {
                tbody.innerHTML = '<tr><td colspan="7" style="color:#dc2626">' + esc(e.message) + '</td></tr>';
            }
        }

        async function loadLaterInline() {
            const body = document.getElementById('laterInlineBody');
            const countEl = document.getElementById('laterInlineCount');
            if (!currentDistrictId) {
                body.innerHTML = '<tr><td colspan="3" style="text-align:center;color:var(--muted);padding:1rem">Select a district to view later list.</td></tr>';
                if (countEl) countEl.textContent = '0';
                return;
            }
            body.innerHTML = '<tr><td colspan="3" style="text-align:center;color:var(--muted);padding:1rem">Loading...</td></tr>';
            try {
                const data = await api('later_list', { district_id: currentDistrictId });
                const rows = data.rows || [];
                if (countEl) countEl.textContent = String(rows.length);
                if (!rows.length) {
                    body.innerHTML = '<tr><td colspan="3" style="text-align:center;color:var(--muted);padding:1rem">No later entries for this district.</td></tr>';
                    return;
                }
                body.innerHTML = rows.map(r => `
                    <tr>
                        <td style="font-family:monospace;font-size:0.75rem">${esc(r.application_no)}</td>
                        <td>${esc(r.applicant_name)}</td>
                        <td style="text-align:right;white-space:nowrap;">
                            <button type="button" class="link-mini later-restore" data-app="${r.application_id}" data-dist="${r.district_id}">Restore</button>
                            <button type="button" class="hb-btn hb-btn--primary later-add" data-app="${r.application_id}" data-dist="${r.district_id}" style="padding:0.2rem 0.45rem;font-size:0.72rem;${currentBatchId ? '' : 'opacity:.45;'}" ${currentBatchId ? '' : 'disabled'}>Add</button>
                        </td>
                    </tr>
                `).join('');
                body.querySelectorAll('.later-restore').forEach(btn => btn.addEventListener('click', () => {
                    restoreLater(parseInt(btn.dataset.app, 10), parseInt(btn.dataset.dist, 10));
                }));
                body.querySelectorAll('.later-add').forEach(btn => btn.addEventListener('click', () => {
                    restoreLaterAndAdd(parseInt(btn.dataset.app, 10), parseInt(btn.dataset.dist, 10));
                }));
            } catch (e) {
                body.innerHTML = '<tr><td colspan="3" style="text-align:center;color:#dc2626;padding:1rem">' + esc(e.message) + '</td></tr>';
                if (countEl) countEl.textContent = '0';
            }
        }

        function highlightBatchRow(batchId) {
            document.querySelectorAll('[data-batch-row]').forEach(row => {
                const id = parseInt(row.getAttribute('data-batch-row'), 10) || 0;
                row.classList.toggle('hb-row-active', id === batchId);
            });
        }

        function closeBatchDetail() {
            openedBatchDetailId = 0;
            batchDetailData = null;
            detailFilters = { stage: '', category: '' };
            document.getElementById('batchDetailCard').classList.remove('is-open');
            document.getElementById('batchDetailMeta').textContent = 'Click any batch name to view KPI breakdown.';
            highlightBatchRow(0);
        }

        function categoryMixFromMembers(members) {
            const out = {};
            members.forEach(m => {
                const cat = String(m.business_category || 'Not specified');
                out[cat] = (out[cat] || 0) + 1;
            });
            return Object.entries(out).sort((a, b) => b[1] - a[1]);
        }

        function stageMixFromMembers(members) {
            const out = { seed: 0, early: 0, growth: 0 };
            members.forEach(m => {
                const key = String(m.stage_key || '').toLowerCase();
                if (Object.prototype.hasOwnProperty.call(out, key)) out[key]++;
            });
            return out;
        }

        function applicantCategoryMixFromMembers(members) {
            const out = {};
            members.forEach(m => {
                const key = String(m.applicant_category || 'Not specified');
                out[key] = (out[key] || 0) + 1;
            });
            return Object.entries(out).sort((a, b) => b[1] - a[1]);
        }

        function inclusionFlagSummaryFromMembers(members) {
            const shg = { yes: 0, no: 0, na: 0 };
            const lakhpati = { yes: 0, no: 0, na: 0 };
            function bucket(v) {
                const s = String(v || '').trim().toLowerCase();
                if (s === 'yes') return 'yes';
                if (s === 'no') return 'no';
                return 'na';
            }
            members.forEach(m => {
                shg[bucket(m.member_of_shg)]++;
                lakhpati[bucket(m.lakhpati_didi)]++;
            });
            return { shg, lakhpati };
        }

        function applyDetailFilters(members) {
            return members.filter(m => {
                const okStage = !detailFilters.stage || String(m.stage_key || '').toLowerCase() === detailFilters.stage;
                const okCat = !detailFilters.category || String(m.business_category || '') === detailFilters.category;
                return okStage && okCat;
            });
        }

        function updateDetailFilterUI() {
            document.getElementById('kpiCardMembers').classList.toggle('is-active', !detailFilters.stage);
            document.getElementById('kpiCardSeed').classList.toggle('is-active', detailFilters.stage === 'seed');
            document.getElementById('kpiCardEarly').classList.toggle('is-active', detailFilters.stage === 'early');
            document.getElementById('kpiCardGrowth').classList.toggle('is-active', detailFilters.stage === 'growth');
            document.querySelectorAll('.hb-cat-btn').forEach(btn => {
                btn.classList.toggle('is-active', String(btn.dataset.category || '') === detailFilters.category);
            });
            const chipWrap = document.getElementById('batchDetailFilters');
            const chips = [];
            if (detailFilters.stage) {
                chips.push(`<button type="button" class="hb-filter-chip" data-clear="stage"><span>Stage: ${esc(String(detailFilters.stage).toUpperCase())}</span><span class="x">&times;</span></button>`);
            }
            if (detailFilters.category) {
                chips.push(`<button type="button" class="hb-filter-chip" data-clear="category"><span>Category: ${esc(String(detailFilters.category))}</span><span class="x">&times;</span></button>`);
            }
            chipWrap.innerHTML = chips.join('');
            chipWrap.style.display = chips.length ? 'flex' : 'none';
            chipWrap.querySelectorAll('[data-clear]').forEach(btn => btn.addEventListener('click', () => {
                const what = String(btn.dataset.clear || '');
                if (what === 'stage') detailFilters.stage = '';
                if (what === 'category') detailFilters.category = '';
                if (batchDetailData) renderBatchDetail(batchDetailData, true);
            }));
        }

        function renderBatchDetail(detail, preserveFilters = false) {
            if (!preserveFilters) {
                detailFilters = { stage: '', category: '' };
            }
            batchDetailData = detail;
            const batch = detail.batch || {};
            const summary = detail.summary || {};
            const allMembers = Array.isArray(detail.members) ? detail.members : [];
            const filteredMembers = applyDetailFilters(allMembers);
            const stageMix = stageMixFromMembers(filteredMembers);
            const categoryEntries = categoryMixFromMembers(filteredMembers);
            const applicantCategoryEntries = applicantCategoryMixFromMembers(filteredMembers);
            const inclusion = inclusionFlagSummaryFromMembers(filteredMembers);
            document.getElementById('batchDetailTitle').textContent = (batch.name || 'Batch insight') + ' — KPI breakdown';
            const filterParts = [];
            if (detailFilters.stage) filterParts.push('Stage: ' + detailFilters.stage.toUpperCase());
            if (detailFilters.category) filterParts.push('Category: ' + detailFilters.category);
            const filterText = filterParts.length ? ' | Filtered by ' + filterParts.join(' + ') : '';
            document.getElementById('batchDetailMeta').textContent = (batch.district_name || 'District') + ' | ' + String((batch.status || '').toUpperCase()) + ' | Target ' + (batch.target_size || 0) + filterText;
            document.getElementById('kpiMembers').textContent = String(filteredMembers.length);
            document.getElementById('kpiSeed').textContent = String(stageMix.seed);
            document.getElementById('kpiEarly').textContent = String(stageMix.early);
            document.getElementById('kpiGrowth').textContent = String(stageMix.growth);

            const defaultPwd = (batch.incubatee_default_password && String(batch.incubatee_default_password).trim())
                ? String(batch.incubatee_default_password)
                : '';
            const noteEl = document.getElementById('batchPortalLoginNote');
            if (noteEl) {
                const bid = batch.id != null ? String(batch.id) : '';
                if (!defaultPwd) {
                    noteEl.textContent = 'Set INCUBATEE_DEFAULT_PASSWORD in .env so the default password can be shown after accounts exist.';
                } else {
                    noteEl.innerHTML = 'Click <strong>Create portal logins</strong> (top right). A message will show <strong>Created</strong> and <strong>skipped</strong> counts — that confirms how many portal accounts were made. The list then reloads; <strong>Password</strong> appears only for rows where an account exists. Until then login will not work. (Advanced: <code style="font-size:0.7rem">php artisan incubatees:provision-users --batch-id=' + esc(bid) + '</code> does the same on the server.)';
                }
            }

            const membersWrap = document.getElementById('batchDetailMembersWrap');
            membersWrap.innerHTML = filteredMembers.length
                ? `<table class="hb-member-table">
                    <thead><tr><th>App no.</th><th>Name</th><th>Username</th><th>Password</th><th>Stage</th><th>Category</th></tr></thead>
                    <tbody>${filteredMembers.map(m => {
                        const ready = !!m.incubatee_account_ready;
                        let pwdCell;
                        if (!ready) {
                            pwdCell = '<span style="color:#b45309;font-weight:600">Pending</span>';
                        } else if (defaultPwd) {
                            pwdCell = esc(defaultPwd);
                        } else {
                            pwdCell = '<span style="color:#64748b">—</span>';
                        }
                        const userCell = (m.portal_username || '')
                            ? `<div style="word-break:break-all">${esc(m.portal_username)}</div>${ready ? '' : '<div style="font-size:0.62rem;color:#b45309;margin-top:0.15rem">Account not created yet</div>'}`
                            : '—';
                        return `
                        <tr>
                            <td style="font-family:monospace">${m.profile_url ? `<a class="hb-member-link" href="${esc(m.profile_url)}" target="_blank" rel="noopener noreferrer">${esc(m.application_no)}</a>` : esc(m.application_no)}</td>
                            <td>${m.profile_url ? `<a class="hb-member-link" href="${esc(m.profile_url)}" target="_blank" rel="noopener noreferrer">${esc(m.applicant_name)}</a>` : esc(m.applicant_name)}</td>
                            <td style="font-family:ui-monospace,monospace;font-size:0.72rem">${userCell}</td>
                            <td style="font-family:ui-monospace,monospace;font-size:0.72rem">${pwdCell}</td>
                            <td>${esc(m.stage_label || m.stage_key || 'UNKNOWN')}</td>
                            <td>${esc(m.business_category || 'Not specified')}</td>
                        </tr>`;
                    }).join('')}
                    </tbody>
                </table>`
                : '<p style="margin:0;color:var(--muted);font-size:0.82rem">No members matched current filter.</p>';

            const catList = document.getElementById('batchCategoryMixList');
            const applicantCategoryList = document.getElementById('batchApplicantCategoryList');
            const inclusionList = document.getElementById('batchInclusionFlagsList');
            catList.innerHTML = categoryEntries.length
                ? categoryEntries.map(([name, count]) => `<li><button type="button" class="hb-cat-btn" data-category="${esc(name)}"><span>${esc(name)}</span><strong>${count}</strong></button></li>`).join('')
                : '<li><span style="color:var(--muted)">No category data</span><strong>0</strong></li>';
            catList.querySelectorAll('.hb-cat-btn').forEach(btn => btn.addEventListener('click', () => {
                const selected = String(btn.dataset.category || '');
                detailFilters.category = detailFilters.category === selected ? '' : selected;
                renderBatchDetail(batchDetailData, true);
            }));
            applicantCategoryList.innerHTML = applicantCategoryEntries.length
                ? applicantCategoryEntries.map(([name, count]) => `<li><span>${esc(name)}</span><strong>${count}</strong></li>`).join('')
                : '<li><span style="color:var(--muted)">No applicant category data</span><strong>0</strong></li>';
            inclusionList.innerHTML = [
                `<li><span>Member of SHG/CBO — Yes</span><strong>${inclusion.shg.yes}</strong></li>`,
                `<li><span>Member of SHG/CBO — No</span><strong>${inclusion.shg.no}</strong></li>`,
                `<li><span>Member of SHG/CBO — N/A</span><strong>${inclusion.shg.na}</strong></li>`,
                `<li><span>Lakhpati Didi — Yes</span><strong>${inclusion.lakhpati.yes}</strong></li>`,
                `<li><span>Lakhpati Didi — No</span><strong>${inclusion.lakhpati.no}</strong></li>`,
                `<li><span>Lakhpati Didi — N/A</span><strong>${inclusion.lakhpati.na}</strong></li>`
            ].join('');
            updateDetailFilterUI();
            const btnProv = document.getElementById('btnProvisionIncubatees');
            if (btnProv) {
                const locked = String(batch.status || '').toLowerCase() === 'locked';
                btnProv.style.display = locked ? 'inline-flex' : 'none';
                btnProv.dataset.batchId = String(batch.id || '');
            }
            document.getElementById('batchDetailCard').classList.add('is-open');
        }

        async function openBatchDetail(batchId) {
            if (!batchId) return;
            openedBatchDetailId = batchId;
            highlightBatchRow(batchId);
            const membersWrap = document.getElementById('batchDetailMembersWrap');
            const catList = document.getElementById('batchCategoryMixList');
            const applicantCategoryList = document.getElementById('batchApplicantCategoryList');
            const inclusionList = document.getElementById('batchInclusionFlagsList');
            document.getElementById('batchDetailTitle').textContent = 'Loading batch insight...';
            document.getElementById('batchDetailMeta').textContent = 'Please wait while KPI data is loading.';
            membersWrap.innerHTML = '<p style="margin:0;color:var(--muted);font-size:0.82rem">Loading members...</p>';
            catList.innerHTML = '<li><span style="color:var(--muted)">Loading...</span><strong>...</strong></li>';
            applicantCategoryList.innerHTML = '<li><span style="color:var(--muted)">Loading...</span><strong>...</strong></li>';
            inclusionList.innerHTML = '<li><span style="color:var(--muted)">Loading...</span><strong>...</strong></li>';
            document.getElementById('batchDetailCard').classList.add('is-open');
            try {
                const data = await api('batch_detail', { batch_id: batchId });
                renderBatchDetail(data);
            } catch (e) {
                document.getElementById('batchDetailTitle').textContent = 'Batch insight';
                document.getElementById('batchDetailMeta').textContent = e.message || 'Could not load batch detail.';
                membersWrap.innerHTML = '<p style="margin:0;color:#dc2626;font-size:0.82rem">Failed to load members.</p>';
                catList.innerHTML = '<li><span style="color:#dc2626">Failed</span><strong>!</strong></li>';
                applicantCategoryList.innerHTML = '<li><span style="color:#dc2626">Failed</span><strong>!</strong></li>';
                inclusionList.innerHTML = '<li><span style="color:#dc2626">Failed</span><strong>!</strong></li>';
            }
        }

        async function continueDraft(batchId) {
            const b = latestBatches.find(x => parseInt(x.id, 10) === batchId);
            if (!b || (b.status !== 'draft' && !(b.status === 'locked' && b.edit_unlocked))) return;
            currentDistrictId = parseInt(b.district_id, 10) || 0;
            currentBatchId = parseInt(b.id, 10) || 0;
            const districtSel = document.getElementById('selDistrict');
            districtSel.value = String(currentDistrictId || '');
            document.getElementById('draftCreateWrap').style.display = currentDistrictId ? 'block' : 'none';
            await loadDraft();
            await loadPool();
            await loadLaterInline();
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        function openEditBatch(batchId) {
            const b = latestBatches.find(x => parseInt(x.id, 10) === batchId);
            if (!b || (b.status !== 'draft' && !(b.status === 'locked' && b.edit_unlocked))) return;
            editingBatchId = batchId;
            document.getElementById('editBatchName').value = b.name || '';
            document.getElementById('editBatchTarget').value = b.target_size || '';
            document.getElementById('editBatchDate').value = b.onboarding_date || '';
            document.getElementById('modalEditBatch').classList.add('is-open');
        }

        function closeEditBatch() {
            editingBatchId = 0;
            document.getElementById('modalEditBatch').classList.remove('is-open');
        }

        async function saveEditBatch() {
            if (!editingBatchId) return;
            try {
                await api('edit_batch', {
                    batch_id: editingBatchId,
                    name: document.getElementById('editBatchName').value.trim(),
                    target_size: parseInt(document.getElementById('editBatchTarget').value, 10),
                    onboarding_date: document.getElementById('editBatchDate').value,
                });
                closeEditBatch();
                await loadBatches();
                if (currentBatchId === editingBatchId) await loadDraft();
                alert('Batch updated.');
            } catch (e) { alert(e.message); }
        }

        async function deleteBatchRow(batchId) {
            const b = latestBatches.find(x => parseInt(x.id, 10) === batchId);
            if (!b || (b.status !== 'draft' && !(b.status === 'locked' && b.edit_unlocked))) {
                alert('This batch is not editable.');
                return;
            }
            if (!confirm('Delete draft "' + (b.name || 'batch') + '"? This cannot be undone.')) return;
            try {
                await api('delete_batch', { batch_id: batchId });
                if (currentBatchId === batchId) {
                    currentBatchId = 0;
                    document.getElementById('activeDraftCard').style.display = 'none';
                }
                await loadBatches();
                await loadPool();
                await loadLaterInline();
                alert('Draft batch deleted.');
            } catch (e) { alert(e.message); }
        }

        function openUnlockRequest(batchId) {
            const b = latestBatches.find(x => parseInt(x.id, 10) === batchId);
            if (!b || b.status !== 'locked') return;
            unlockRequestBatchId = batchId;
            document.getElementById('unlockReqReason').value = '';
            document.getElementById('unlockReqExpected').value = '';
            document.getElementById('modalUnlockRequest').classList.add('is-open');
        }

        function closeUnlockRequest() {
            unlockRequestBatchId = 0;
            document.getElementById('modalUnlockRequest').classList.remove('is-open');
        }

        async function submitUnlockRequest() {
            if (!unlockRequestBatchId) return;
            const reason = document.getElementById('unlockReqReason').value.trim();
            const expected = document.getElementById('unlockReqExpected').value.trim();
            if (!reason || !expected) {
                alert('Please fill reason and expected changes.');
                return;
            }
            try {
                await api('request_unlock', {
                    batch_id: unlockRequestBatchId,
                    reason,
                    expected_changes: expected,
                });
                closeUnlockRequest();
                await loadBatches();
                alert('Unlock request sent to state admin.');
            } catch (e) {
                alert(e.message);
            }
        }

        async function relockBatch(batchId) {
            if (!confirm('Re-lock this batch now? Editing will stop immediately.')) return;
            try {
                await api('relock_batch', { batch_id: batchId });
                await loadBatches();
                if (currentBatchId === batchId) {
                    currentBatchId = 0;
                    document.getElementById('activeDraftCard').style.display = 'none';
                }
                alert('Batch re-locked.');
            } catch (e) {
                alert(e.message);
            }
        }

        document.getElementById('selDistrict').addEventListener('change', async (e) => {
            currentDistrictId = parseInt(e.target.value, 10) || 0;
            document.getElementById('draftCreateWrap').style.display = currentDistrictId ? 'block' : 'none';
            currentBatchId = 0;
            document.getElementById('activeDraftCard').style.display = 'none';
            if (currentDistrictId) await tryResumeDraft();
            else {
                loadPool();
                loadLaterInline();
            }
        });

        document.getElementById('inpSearch').addEventListener('input', () => {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(loadPool, 280);
        });
        document.getElementById('selFiscalYear').addEventListener('change', (e) => {
            selectedFiscalYearId = parseInt(e.target.value, 10) || 0;
            loadPool();
        });

        document.getElementById('btnCreateDraft').addEventListener('click', async () => {
            if (blocked) { alert('Blocked until overdue Onboarding Letters are resolved.'); return; }
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
                loadLaterInline();
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
                loadLaterInline();
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
                loadLaterInline();
                loadBatches();
                alert('Batch locked. Upload Onboarding Letter within 7 days.');
            } catch (e) { alert(e.message); }
        });
        document.getElementById('btnLock').addEventListener('click', openLockFlow);
        document.getElementById('modalRatioGapBack').addEventListener('click', closeRatioGap);
        document.getElementById('modalRatioGapContinue').addEventListener('click', () => {
            closeRatioGap();
            document.getElementById('modalLock').classList.add('is-open');
        });

        document.getElementById('btnRefreshBatches').addEventListener('click', loadBatches);
        document.getElementById('btnSelectAllVisible').addEventListener('click', () => {
            const ids = latestPoolRows.map(r => r.id);
            markSelectedPoolIds(ids);
        });
        document.getElementById('btnClearSelection').addEventListener('click', () => markSelectedPoolIds([]));
        document.getElementById('btnAutoSelectMix').addEventListener('click', autoSelectByIdealMix);
        document.getElementById('btnAddSelected').addEventListener('click', addSelectedToDraft);
        document.getElementById('btnEditCurrentBatch').addEventListener('click', () => {
            if (!currentBatchId) {
                alert('Select or continue a batch first.');
                return;
            }
            openEditBatch(currentBatchId);
        });
        document.getElementById('editBatchCancel').addEventListener('click', closeEditBatch);
        document.getElementById('editBatchSave').addEventListener('click', saveEditBatch);
        document.getElementById('btnCloseBatchDetail').addEventListener('click', closeBatchDetail);
        document.getElementById('unlockReqCancel').addEventListener('click', closeUnlockRequest);
        document.getElementById('unlockReqSubmit').addEventListener('click', submitUnlockRequest);
        document.getElementById('btnProvisionIncubatees')?.addEventListener('click', async () => {
            const btn = document.getElementById('btnProvisionIncubatees');
            const bid = parseInt(btn?.dataset.batchId || '0', 10);
            if (!bid) return;
            if (!confirm('Create incubatee portal accounts for everyone in this locked batch? Large batches may take a minute.')) return;
            btn.disabled = true;
            try {
                const res = await api('provision_incubatees', { batch_id: bid });
                const c = res.created ?? 0;
                const s = res.skipped ?? 0;
                alert('Portal accounts: ' + c + ' created, ' + s + ' skipped (already had account or conflict). The member list will refresh now.');
                await openBatchDetail(bid);
            } catch (e) {
                alert(e.message || 'Failed');
            } finally {
                btn.disabled = false;
            }
        });
        document.getElementById('kpiCardMembers').addEventListener('click', () => {
            detailFilters.stage = '';
            if (batchDetailData) renderBatchDetail(batchDetailData, true);
        });
        document.getElementById('kpiCardSeed').addEventListener('click', () => {
            detailFilters.stage = detailFilters.stage === 'seed' ? '' : 'seed';
            if (batchDetailData) renderBatchDetail(batchDetailData, true);
        });
        document.getElementById('kpiCardEarly').addEventListener('click', () => {
            detailFilters.stage = detailFilters.stage === 'early' ? '' : 'early';
            if (batchDetailData) renderBatchDetail(batchDetailData, true);
        });
        document.getElementById('kpiCardGrowth').addEventListener('click', () => {
            detailFilters.stage = detailFilters.stage === 'growth' ? '' : 'growth';
            if (batchDetailData) renderBatchDetail(batchDetailData, true);
        });

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
                        loadLaterInline();
                    } catch (e) { alert(e.message); }
                }));
                document.getElementById('modalLater').classList.add('is-open');
            } catch (e) { alert(e.message); }
        });
        document.getElementById('modalLaterClose').addEventListener('click', () => document.getElementById('modalLater').classList.remove('is-open'));

        loadBatches();
    })();
    </script>
    @include('partials.app-footer')
</body>
</html>
