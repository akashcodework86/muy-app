@extends('layouts.admin')

@section('title', 'Team performance')
@section('heading', 'Team performance')

@push('styles')
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">
<style>
    :root {
        --tp-indigo: #4f46e5;
        --tp-violet: #7c3aed;
        --tp-teal: #0d9488;
        --tp-emerald: #10b981;
        --tp-text: #0f172a;
        --tp-muted: #64748b;
        --tp-ink: #334155;
    }
    .tp-shell {
        display: flex;
        flex-direction: column;
        gap: 1rem;
        padding-bottom: 3rem;
        font-family: 'DM Sans', sans-serif;
    }

    .tp-intro {
        background: linear-gradient(135deg, rgba(79, 70, 229, 0.08), rgba(124, 58, 237, 0.06) 45%, rgba(13, 148, 136, 0.05));
        border: 1px solid rgba(99, 102, 241, 0.18);
        border-radius: 18px;
        padding: 1rem 1.3rem;
        display: flex;
        flex-wrap: wrap;
        gap: 1rem;
        align-items: center;
        justify-content: space-between;
    }
    .tp-intro h2 { margin: 0 0 0.2rem; font-size: 1.05rem; font-weight: 800; color: var(--tp-text); }
    .tp-intro p { margin: 0; font-size: 0.82rem; color: var(--tp-muted); max-width: 46rem; }
    .tp-intro__fy {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        background: #fff;
        border: 1px solid rgba(148, 163, 184, 0.4);
        border-radius: 999px;
        padding: 0.35rem 0.85rem;
        font-size: 0.8rem;
        font-weight: 600;
        color: var(--tp-text);
    }
    .tp-intro__fy i { color: var(--tp-indigo); }
    .tp-intro__fy select { border: none; background: transparent; font: inherit; color: inherit; outline: none; cursor: pointer; }

    .tp-stat-row {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 0.75rem;
    }
    .tp-stat {
        background: #fff;
        border: 1px solid rgba(226, 232, 240, 0.9);
        border-radius: 14px;
        padding: 0.8rem 1rem;
        box-shadow: 0 6px 18px rgba(15, 23, 42, 0.04);
    }
    .tp-stat__label { font-size: 0.7rem; color: var(--tp-muted); text-transform: uppercase; letter-spacing: 0.08em; font-weight: 700; }
    .tp-stat__value { font-size: 1.4rem; font-weight: 800; color: var(--tp-text); margin-top: 0.15rem; }
    .tp-stat__sub { font-size: 0.7rem; color: var(--tp-muted); margin-top: 0.1rem; }

    /* ---- Canvas ---- */
    .tp-canvas {
        position: relative;
        background: linear-gradient(135deg, #e8fbf7 0%, #f0fdfa 45%, #ecfeff 100%);
        border: 1px solid rgba(13, 148, 136, 0.18);
        border-radius: 18px;
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.6), 0 4px 16px rgba(15, 23, 42, 0.04);
        padding: 1.25rem;
        overflow: auto;
        max-height: 80vh;
    }
    .tp-canvas::-webkit-scrollbar { height: 10px; width: 10px; }
    .tp-canvas::-webkit-scrollbar-thumb { background: rgba(13, 148, 136, 0.25); border-radius: 999px; }

    .tp-legend {
        display: flex;
        flex-wrap: wrap;
        gap: 0.65rem;
        margin-bottom: 0.85rem;
        font-size: 0.72rem;
        color: var(--tp-muted);
    }
    .tp-legend span {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        padding: 0.2rem 0.55rem;
        background: rgba(255, 255, 255, 0.6);
        border: 1px solid rgba(148, 163, 184, 0.3);
        border-radius: 999px;
    }
    .tp-legend i { font-size: 0.62rem; }
    .tp-legend .dot-state { color: var(--tp-indigo); }
    .tp-legend .dot-hub { color: #8b5cf6; }
    .tp-legend .dot-district { color: var(--tp-emerald); }
    .tp-legend .dot-staff { color: var(--tp-teal); }

    .tp-world {
        position: relative;
        min-height: 420px;
    }
    .tp-connectors {
        position: absolute;
        inset: 0;
        width: 100%;
        height: 100%;
        pointer-events: none;
        overflow: visible;
    }

    /* --- Card --- */
    .tp-card {
        position: absolute;
        width: 230px;
        min-height: 78px;
        background: #fff;
        border-radius: 10px;
        box-shadow: 0 4px 12px rgba(15, 23, 42, 0.08);
        padding: 0.7rem 0.9rem;
        cursor: pointer;
        transition: box-shadow 0.2s ease, transform 0.2s ease, border-color 0.2s ease;
        overflow: hidden;
        border: 1px solid transparent;
        box-sizing: border-box;
    }
    .tp-card:hover {
        box-shadow: 0 14px 30px rgba(15, 23, 42, 0.14);
        transform: translateY(-1px);
    }
    .tp-card.is-leaf { cursor: default; }
    .tp-card.is-leaf:hover { transform: none; }
    .tp-card__kicker {
        font-size: 0.62rem;
        letter-spacing: 0.1em;
        text-transform: uppercase;
        font-weight: 700;
        color: var(--tp-muted);
        margin-bottom: 0.15rem;
    }
    .tp-card__title {
        font-size: 0.92rem;
        font-weight: 700;
        color: var(--tp-text);
        line-height: 1.28;
        padding-right: 3.8rem;
        margin: 0;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .tp-card__sub {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        margin-top: 0.5rem;
        font-size: 0.78rem;
        color: var(--tp-muted);
    }
    .tp-card__sub-name { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; min-width: 0; flex: 1; }

    /* Corner badge */
    .tp-card__corner {
        position: absolute;
        top: 0;
        right: 0;
        width: 0;
        height: 0;
        border-style: solid;
        border-width: 0 82px 52px 0;
    }
    .tp-card.is-state .tp-card__corner { border-color: transparent var(--tp-indigo) transparent transparent; }
    .tp-card.is-hub .tp-card__corner { border-color: transparent #8b5cf6 transparent transparent; }
    .tp-card.is-district .tp-card__corner { border-color: transparent var(--tp-emerald) transparent transparent; }
    .tp-card.is-staff .tp-card__corner { border-color: transparent var(--tp-teal) transparent transparent; }
    .tp-card.is-inactive .tp-card__corner { border-color: transparent #94a3b8 transparent transparent; }
    .tp-card__corner-text {
        position: absolute;
        top: 0.38rem;
        right: 0.55rem;
        font-size: 0.75rem;
        font-weight: 800;
        color: #fff;
        letter-spacing: -0.01em;
    }
    .tp-card__corner-sub {
        position: absolute;
        top: 1.45rem;
        right: 0.6rem;
        font-size: 0.54rem;
        font-weight: 700;
        color: rgba(255, 255, 255, 0.88);
        text-transform: uppercase;
        letter-spacing: 0.08em;
    }

    /* avatar */
    .tp-avatar {
        width: 1.65rem;
        height: 1.65rem;
        border-radius: 50%;
        background: linear-gradient(135deg, #0d9488, #2dd4bf);
        color: #fff;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 0.68rem;
        font-weight: 700;
        overflow: hidden;
        flex-shrink: 0;
        box-shadow: 0 2px 6px rgba(13, 148, 136, 0.3);
    }
    .tp-card.is-state .tp-avatar { background: linear-gradient(135deg, #4f46e5, #7c3aed); box-shadow: 0 2px 6px rgba(79, 70, 229, 0.35); }
    .tp-card.is-hub .tp-avatar { background: linear-gradient(135deg, #8b5cf6, #a78bfa); box-shadow: 0 2px 6px rgba(139, 92, 246, 0.3); }
    .tp-card.is-district .tp-avatar { background: linear-gradient(135deg, #10b981, #34d399); box-shadow: 0 2px 6px rgba(16, 185, 129, 0.3); }
    .tp-card.is-inactive .tp-avatar { background: #94a3b8; box-shadow: none; }
    .tp-avatar img { width: 100%; height: 100%; object-fit: cover; }

    /* 3-dot menu (decorative for now) */
    .tp-card__dots {
        position: absolute;
        right: 0.55rem;
        bottom: 0.5rem;
        color: #cbd5e1;
        font-size: 0.85rem;
        letter-spacing: 0.15em;
    }

    /* Toggle button between levels */
    .tp-toggle {
        position: absolute;
        width: 26px;
        height: 26px;
        border-radius: 50%;
        background: #fff;
        color: #334155;
        border: 1px solid rgba(148, 163, 184, 0.5);
        box-shadow: 0 2px 6px rgba(15, 23, 42, 0.08);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.95rem;
        font-weight: 700;
        cursor: pointer;
        z-index: 5;
        padding: 0;
        line-height: 1;
        transition: background 0.15s ease, color 0.15s ease, transform 0.15s ease;
    }
    .tp-toggle:hover { background: var(--tp-teal); color: #fff; border-color: var(--tp-teal); transform: scale(1.08); }
    .tp-toggle__count {
        position: absolute;
        top: 100%;
        left: 50%;
        transform: translate(-50%, 3px);
        font-size: 0.68rem;
        color: #475569;
        font-weight: 700;
        white-space: nowrap;
        background: rgba(255, 255, 255, 0.75);
        padding: 0 0.3rem;
        border-radius: 4px;
    }

    /* Staff leaf cards are slightly smaller */
    .tp-card.is-staff { min-height: 72px; }
    .tp-card.is-staff .tp-card__title { font-size: 0.86rem; }

    .tp-soon {
        margin-top: 0.75rem;
        padding: 0.55rem 0.8rem;
        background: rgba(79, 70, 229, 0.06);
        border: 1px dashed rgba(79, 70, 229, 0.35);
        border-radius: 10px;
        font-size: 0.72rem;
        color: #3730a3;
        text-align: center;
    }
    .tp-soon i { margin-right: 0.35rem; }

    .tp-empty-canvas {
        padding: 2rem;
        text-align: center;
        color: var(--tp-muted);
        font-size: 0.9rem;
    }

    @media (max-width: 720px) {
        .tp-intro { padding: 0.85rem; }
        .tp-canvas { padding: 0.85rem; max-height: 75vh; }
    }
</style>
@endpush

@section('content')
@php
    $nf = new class {
        public function format($n) {
            $n = (int) $n;
            if ($n < 0) return '-'.$this->format(-$n);
            if ($n < 1000) return (string) $n;
            $s = (string) $n;
            $last3 = substr($s, -3);
            $rest = substr($s, 0, -3);
            $rest = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $rest);
            return $rest.','.$last3;
        }
    };
@endphp

<div class="tp-shell">
    <section class="tp-intro">
        <div>
            <h2><i class="fa-solid fa-sitemap" style="color:var(--tp-indigo);"></i> Team performance — org view</h2>
            <p>State on the left. Click the <strong>+</strong> button next to any card to expand its children. Works top-down: State → Hubs → Districts → Staff. Other service deliverables will plug into this tree as they go live.</p>
        </div>
        <form method="get" action="{{ route('admin.team-performance.index') }}" class="tp-intro__fy">
            <i class="fa-solid fa-calendar-days"></i>
            <select name="fy" onchange="this.form.submit()">
                @foreach ($fiscalYears as $fy)
                    <option value="{{ $fy->id }}" @selected($activeFy && $fy->id === $activeFy->id)>{{ $fy->name }}</option>
                @endforeach
            </select>
        </form>
    </section>

    <section class="tp-stat-row">
        <div class="tp-stat">
            <div class="tp-stat__label">Total CFAs</div>
            <div class="tp-stat__value">{{ $nf->format($stateTotal) }}</div>
            <div class="tp-stat__sub">FY {{ $activeFy?->name ?? '—' }}</div>
        </div>
        <div class="tp-stat">
            <div class="tp-stat__label">Hubs</div>
            <div class="tp-stat__value">{{ $totalHubs }}</div>
            <div class="tp-stat__sub">Operating across state</div>
        </div>
        <div class="tp-stat">
            <div class="tp-stat__label">Districts</div>
            <div class="tp-stat__value">{{ $totalDistricts }}</div>
            <div class="tp-stat__sub">Under all hubs</div>
        </div>
        <div class="tp-stat">
            <div class="tp-stat__label">District staff</div>
            <div class="tp-stat__value">{{ $nf->format($totalStaff) }}</div>
            <div class="tp-stat__sub">Referral mapped</div>
        </div>
    </section>

    <section class="tp-canvas" id="tpCanvas">
        <div class="tp-legend">
            <span><i class="fa-solid fa-circle dot-state"></i>State</span>
            <span><i class="fa-solid fa-circle dot-hub"></i>Hub</span>
            <span><i class="fa-solid fa-circle dot-district"></i>District</span>
            <span><i class="fa-solid fa-circle dot-staff"></i>Staff</span>
            <span><i class="fa-solid fa-circle-info" style="color:#d97706;"></i>Corner badge = share of parent</span>
        </div>

        @if (empty($hubs))
            <div class="tp-empty-canvas">
                <i class="fa-solid fa-circle-info" style="color:var(--tp-muted);"></i>
                No hubs configured yet.
            </div>
        @else
            <div class="tp-world" id="tpWorld">
                <svg class="tp-connectors" id="tpConnectors" xmlns="http://www.w3.org/2000/svg"></svg>
            </div>

            <p class="tp-soon">
                <i class="fa-solid fa-hourglass-half"></i>
                Service delivery columns (per staff, per service) will appear to the right of staff once service cases go live.
            </p>
        @endif
    </section>
</div>

<script id="tpTreeData" type="application/json">@json([
    'state' => [
        'name' => 'Uttarakhand',
        'sub' => 'State HQ',
        'count' => $stateTotal,
        'pct' => 100,
    ],
    'hubs' => $hubs,
])</script>
@endsection

@push('scripts')
<script>
(function () {
    const raw = document.getElementById('tpTreeData');
    if (!raw) return;
    const data = JSON.parse(raw.textContent || raw.innerText || '{}');
    if (!data || !data.hubs || !data.hubs.length) return;

    const world = document.getElementById('tpWorld');
    const svg = document.getElementById('tpConnectors');
    const canvas = document.getElementById('tpCanvas');
    if (!world || !svg || !canvas) return;

    // ----- Layout config -----
    const CFG = {
        cardW: 230,
        cardH: 78,
        cardHStaff: 72,
        colGap: 72,      // horizontal gap between columns
        rowGap: 14,      // vertical gap between sibling cards
        padTop: 24,
        padLeft: 24,
        paren: 18,       // gap on either side for connector logic
    };

    // ----- Build tree model with ids -----
    // { id, kind, name, sub, count, pct, pctLabel, avatarUrl, initials, children, collapsed, _layout }
    function initials(name) {
        if (!name) return '?';
        return String(name).trim().split(/\s+/).filter(Boolean).slice(0, 2).map(p => p[0]).join('').toUpperCase();
    }

    // Root = state
    const root = {
        id: 'state',
        kind: 'state',
        name: data.state.name || 'State',
        sub: data.state.sub || 'State HQ',
        count: data.state.count || 0,
        pct: 100,
        pctLabel: 'of total',
        initials: initials(data.state.name),
        collapsed: false,  // state expanded by default
        children: [],
    };

    data.hubs.forEach((h) => {
        const hub = {
            id: 'hub-' + h.id,
            kind: 'hub',
            name: h.name,
            sub: (h.district_count || 0) + ' districts · ' + (h.staff_count || 0) + ' staff',
            count: h.cfa_count || 0,
            pct: Math.round((h.pct_of_state || 0) * 10) / 10,
            pctLabel: 'of state',
            initials: initials(h.name),
            collapsed: true,  // hubs start collapsed
            children: [],
        };

        (h.districts || []).forEach((d) => {
            const district = {
                id: 'district-' + d.id,
                kind: 'district',
                name: d.name,
                sub: (d.staff ? d.staff.length : 0) + ' staff' + (d.unassigned_count > 0 ? ' · ' + d.unassigned_count + ' walk-in' : ''),
                count: d.cfa_count || 0,
                pct: Math.round((d.pct_of_hub || 0) * 10) / 10,
                pctLabel: 'of hub',
                initials: initials(d.name),
                collapsed: true,
                children: [],
                unassigned: d.unassigned_count || 0,
                districtTotal: d.cfa_count || 0,
            };

            (d.staff || []).forEach((s) => {
                district.children.push({
                    id: 'staff-' + s.id,
                    kind: 'staff',
                    name: s.name,
                    sub: s.email || s.phone || '—',
                    count: s.cfa_count || 0,
                    pct: s.pct_of_district || 0,
                    pctLabel: 'of district',
                    initials: initials(s.name),
                    avatarUrl: s.avatar_url || null,
                    isInactive: !s.is_active,
                    isLeaf: true,
                    children: [],
                });
            });

            // If district has walk-in/unassigned CFAs, add a synthetic "Walk-in" leaf
            if (d.unassigned_count > 0) {
                district.children.push({
                    id: 'unassigned-' + d.id,
                    kind: 'staff',
                    name: 'Walk-in / direct',
                    sub: 'No referral mapped',
                    count: d.unassigned_count,
                    pct: (d.cfa_count > 0 ? Math.round((d.unassigned_count / d.cfa_count) * 1000) / 10 : 0),
                    pctLabel: 'of district',
                    initials: 'WI',
                    isSynthetic: true,
                    isLeaf: true,
                    children: [],
                });
            }

            hub.children.push(district);
        });

        root.children.push(hub);
    });

    // ----- Tree layout (post-order + pre-order) -----
    function effectiveH(node) {
        return node.kind === 'staff' ? CFG.cardHStaff : CFG.cardH;
    }

    function visibleChildren(node) {
        if (node.collapsed) return [];
        return node.children || [];
    }

    function computeSubtreeH(node) {
        const kids = visibleChildren(node);
        if (kids.length === 0) return effectiveH(node);
        let h = 0;
        kids.forEach((c, i) => {
            h += computeSubtreeH(c);
            if (i > 0) h += CFG.rowGap;
        });
        return Math.max(h, effectiveH(node));
    }

    function placeNode(node, depth, subtreeTop) {
        const subtreeH = computeSubtreeH(node);
        node._layout = {
            depth,
            x: CFG.padLeft + depth * (CFG.cardW + CFG.colGap),
            y: subtreeTop + (subtreeH - effectiveH(node)) / 2,
            w: CFG.cardW,
            h: effectiveH(node),
            subtreeH,
            subtreeTop,
        };

        const kids = visibleChildren(node);
        let cursor = subtreeTop;
        kids.forEach((c) => {
            const ch = computeSubtreeH(c);
            placeNode(c, depth + 1, cursor);
            cursor += ch + CFG.rowGap;
        });
    }

    function flatten(node, acc) {
        acc.push(node);
        (visibleChildren(node)).forEach((c) => flatten(c, acc));
        return acc;
    }

    // ----- Render -----
    function render() {
        placeNode(root, 0, CFG.padTop);

        const nodes = flatten(root, []);
        const maxX = Math.max(...nodes.map(n => n._layout.x + n._layout.w));
        const maxY = Math.max(...nodes.map(n => n._layout.y + n._layout.h));
        const worldW = maxX + CFG.padLeft + 40; // breathing room for toggle
        const worldH = maxY + CFG.padTop;

        world.style.width = worldW + 'px';
        world.style.height = worldH + 'px';
        svg.setAttribute('width', worldW);
        svg.setAttribute('height', worldH);
        svg.setAttribute('viewBox', '0 0 ' + worldW + ' ' + worldH);

        // Clear existing
        world.querySelectorAll('.tp-card, .tp-toggle').forEach(el => el.remove());
        while (svg.firstChild) svg.removeChild(svg.firstChild);

        // --- Draw connectors (behind cards) ---
        nodes.forEach((parent) => {
            const kids = visibleChildren(parent);
            if (!kids.length) return;
            const pl = parent._layout;
            const startX = pl.x + pl.w;
            const startY = pl.y + pl.h / 2;
            kids.forEach((child) => {
                const cl = child._layout;
                const endX = cl.x;
                const endY = cl.y + cl.h / 2;
                const mid = (startX + endX) / 2;
                const d = 'M ' + startX + ' ' + startY +
                          ' C ' + mid + ' ' + startY +
                          ', ' + mid + ' ' + endY +
                          ', ' + endX + ' ' + endY;
                const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
                path.setAttribute('d', d);
                path.setAttribute('fill', 'none');
                path.setAttribute('stroke', connectorColor(parent.kind));
                path.setAttribute('stroke-width', '2');
                path.setAttribute('stroke-linecap', 'round');
                path.setAttribute('opacity', '0.55');
                svg.appendChild(path);
            });
        });

        // --- Draw cards ---
        nodes.forEach((node) => {
            const el = buildCard(node);
            world.appendChild(el);
        });

        // --- Draw toggle buttons (between parent and its children's column) ---
        nodes.forEach((node) => {
            if (!node.children || !node.children.length) return;
            const pl = node._layout;
            const toggle = document.createElement('button');
            toggle.type = 'button';
            toggle.className = 'tp-toggle';
            toggle.setAttribute('aria-label', node.collapsed ? 'Expand' : 'Collapse');
            toggle.innerHTML = node.collapsed ? '+' : '−';

            // Position: just to the right of parent card, midpoint
            const tx = pl.x + pl.w + (CFG.colGap / 2) - 13; // 26/2 = 13
            const ty = pl.y + pl.h / 2 - 13;
            toggle.style.left = tx + 'px';
            toggle.style.top = ty + 'px';

            // Badge with count
            const count = document.createElement('span');
            count.className = 'tp-toggle__count';
            count.textContent = node.children.length;
            toggle.appendChild(count);

            toggle.addEventListener('click', (e) => {
                e.stopPropagation();
                node.collapsed = !node.collapsed;
                render();
            });
            world.appendChild(toggle);
        });
    }

    function connectorColor(parentKind) {
        switch (parentKind) {
            case 'state': return '#8b5cf6';
            case 'hub': return '#10b981';
            case 'district': return '#0d9488';
            default: return '#94a3b8';
        }
    }

    function buildCard(node) {
        const el = document.createElement('div');
        el.className = 'tp-card is-' + node.kind + (node.isInactive ? ' is-inactive' : '') + (node.isLeaf ? ' is-leaf' : '');
        el.style.left = node._layout.x + 'px';
        el.style.top = node._layout.y + 'px';
        el.style.width = node._layout.w + 'px';
        el.style.height = node._layout.h + 'px';

        const corner = document.createElement('div');
        corner.className = 'tp-card__corner';
        el.appendChild(corner);

        const cornerText = document.createElement('div');
        cornerText.className = 'tp-card__corner-text';
        // For state: show pct as 100%, for others show pct; for staff show count
        if (node.kind === 'staff') {
            cornerText.textContent = formatNum(node.count);
        } else {
            cornerText.textContent = formatPct(node.pct);
        }
        el.appendChild(cornerText);

        // Kicker row: kind label + count (for non-staff)
        const kicker = document.createElement('div');
        kicker.className = 'tp-card__kicker';
        if (node.kind === 'state') kicker.textContent = 'State · ' + formatNum(node.count) + ' CFAs';
        else if (node.kind === 'hub') kicker.textContent = 'Hub · ' + formatNum(node.count) + ' CFAs';
        else if (node.kind === 'district') kicker.textContent = 'District · ' + formatNum(node.count) + ' CFAs';
        else kicker.textContent = node.isSynthetic ? 'Unassigned' : 'Staff';
        el.appendChild(kicker);

        const title = document.createElement('div');
        title.className = 'tp-card__title';
        title.textContent = node.name;
        title.title = node.name;
        el.appendChild(title);

        const subRow = document.createElement('div');
        subRow.className = 'tp-card__sub';

        const avatar = document.createElement('span');
        avatar.className = 'tp-avatar';
        if (node.avatarUrl) {
            const img = document.createElement('img');
            img.src = node.avatarUrl;
            img.alt = '';
            avatar.appendChild(img);
        } else {
            avatar.textContent = node.initials || '?';
        }
        subRow.appendChild(avatar);

        const subName = document.createElement('span');
        subName.className = 'tp-card__sub-name';
        subName.textContent = node.sub || '';
        subName.title = node.sub || '';
        subRow.appendChild(subName);

        el.appendChild(subRow);

        const dots = document.createElement('div');
        dots.className = 'tp-card__dots';
        dots.textContent = '⋮';
        el.appendChild(dots);

        // Click card also toggles (desktop shortcut); but don't conflict if leaf
        if (!node.isLeaf && node.children && node.children.length) {
            el.addEventListener('click', () => {
                node.collapsed = !node.collapsed;
                render();
            });
        }

        return el;
    }

    function formatNum(n) {
        n = Number(n || 0);
        if (n < 1000) return String(n);
        const s = String(n);
        const last3 = s.slice(-3);
        let rest = s.slice(0, -3);
        rest = rest.replace(/\B(?=(\d{2})+(?!\d))/g, ',');
        return rest + ',' + last3;
    }
    function formatPct(p) {
        p = Number(p || 0);
        if (p === 0) return '0%';
        if (p < 1) return p.toFixed(1).replace(/\.0$/, '') + '%';
        return Math.round(p) + '%';
    }

    render();

    // Re-render on resize for responsive canvas — positions don't change but svg viewBox refresh helps.
    let resizeTimer;
    window.addEventListener('resize', () => {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(render, 120);
    });
})();
</script>
@endpush
