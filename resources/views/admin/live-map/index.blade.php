@extends('layouts.admin')

@section('title', 'Live map')
@section('heading', 'Uttarakhand live map')

@push('styles')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="">
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css" crossorigin="">
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css" crossorigin="">
<style>
    .slm-page {
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
        min-height: calc(100vh - 7.5rem);
        font-family: 'DM Sans', system-ui, sans-serif;
    }
    .slm-toolbar {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 0.65rem 1rem;
        padding: 0.75rem 1rem;
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        box-shadow: 0 2px 12px rgba(15, 23, 42, 0.05);
    }
    .slm-toolbar__group { display: flex; flex-wrap: wrap; align-items: center; gap: 0.5rem; }
    .slm-toolbar__label { font-size: 0.72rem; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; }
    .slm-toolbar input[type="date"] {
        padding: 0.45rem 0.55rem; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 0.85rem;
    }
    .slm-btn {
        padding: 0.45rem 0.85rem; border-radius: 8px; border: none; background: #0d9488; color: #fff;
        font-weight: 700; font-size: 0.82rem; cursor: pointer; font-family: inherit;
    }
    .slm-btn--ghost { background: #fff; color: #0d9488; border: 1px solid #99f6e4; }
    .slm-chip {
        display: inline-flex; align-items: center; gap: 0.35rem;
        padding: 0.35rem 0.65rem; border-radius: 999px; background: #f8fafc; border: 1px solid #e2e8f0;
        font-size: 0.78rem; font-weight: 600; color: #334155;
    }
    .slm-chip strong { color: #0f172a; }
    .slm-legend {
        display: flex; flex-wrap: wrap; gap: 0.45rem 0.85rem; font-size: 0.72rem; color: #475569;
    }
    .slm-legend span { display: inline-flex; align-items: center; gap: 0.3rem; }
    .slm-legend i {
        width: 0.65rem; height: 0.65rem; border-radius: 999px; display: inline-block; border: 1px solid rgba(15,23,42,0.15);
    }
    .slm-layout {
        flex: 1;
        display: grid;
        grid-template-columns: minmax(0, 1fr) minmax(280px, 360px);
        gap: 0.75rem;
        min-height: 560px;
    }
    .slm-map-shell {
        position: relative;
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        overflow: hidden;
        box-shadow: 0 4px 20px rgba(15, 23, 42, 0.06);
        min-height: 560px;
    }
    #slm-map { width: 100%; height: 100%; min-height: 560px; background: #f8fafc; }
    .slm-status {
        position: absolute; left: 0.75rem; bottom: 0.75rem; z-index: 500;
        padding: 0.4rem 0.65rem; border-radius: 8px; background: rgba(255,255,255,0.92);
        border: 1px solid #e2e8f0; font-size: 0.72rem; color: #475569; font-weight: 600;
        box-shadow: 0 2px 8px rgba(15,23,42,0.08);
    }
    .slm-side {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        box-shadow: 0 4px 20px rgba(15, 23, 42, 0.06);
        display: flex;
        flex-direction: column;
        min-height: 560px;
        max-height: calc(100vh - 11rem);
        overflow: hidden;
    }
    .slm-side__head {
        padding: 1rem 1.1rem 0.75rem;
        border-bottom: 1px solid #f1f5f9;
        background: linear-gradient(180deg, #f8fafc 0%, #fff 100%);
    }
    .slm-side__eyebrow {
        font-size: 0.68rem;
        font-weight: 700;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        color: #64748b;
        margin: 0 0 0.25rem;
    }
    .slm-side__title {
        margin: 0;
        font-size: 1.35rem;
        font-weight: 800;
        letter-spacing: -0.02em;
        color: #0f172a;
        line-height: 1.15;
    }
    .slm-side__sub {
        margin: 0.35rem 0 0;
        font-size: 0.78rem;
        color: #64748b;
        line-height: 1.45;
    }
    .slm-side__body {
        padding: 0.85rem 1.1rem 1.1rem;
        overflow-y: auto;
        flex: 1;
    }
    .slm-kpis {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 0.55rem;
        margin-bottom: 0.85rem;
    }
    .slm-kpi {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 0.65rem 0.75rem;
    }
    .slm-kpi span {
        display: block;
        font-size: 0.68rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: #64748b;
        margin-bottom: 0.15rem;
    }
    .slm-kpi strong {
        font-size: 1.25rem;
        font-weight: 800;
        color: #0f172a;
        line-height: 1.1;
    }
    .slm-kpi small {
        display: block;
        margin-top: 0.15rem;
        font-size: 0.68rem;
        color: #94a3b8;
        font-weight: 600;
    }
    .slm-section {
        margin-bottom: 0.85rem;
    }
    .slm-section__label {
        font-size: 0.68rem;
        font-weight: 800;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        color: #64748b;
        margin: 0 0 0.45rem;
    }
    .slm-bar-row {
        display: grid;
        grid-template-columns: 4.5rem 1fr auto;
        gap: 0.45rem;
        align-items: center;
        margin-bottom: 0.35rem;
        font-size: 0.76rem;
    }
    .slm-bar-row span:first-child { color: #475569; font-weight: 600; }
    .slm-bar-row strong { color: #0f172a; font-size: 0.78rem; min-width: 2rem; text-align: right; }
    .slm-bar {
        height: 0.55rem;
        background: #e2e8f0;
        border-radius: 999px;
        overflow: hidden;
    }
    .slm-bar > i {
        display: block;
        height: 100%;
        border-radius: 999px;
        background: linear-gradient(90deg, #14b8a6, #0d9488);
    }
    .slm-list { list-style: none; margin: 0; padding: 0; }
    .slm-list li {
        display: flex;
        justify-content: space-between;
        gap: 0.65rem;
        align-items: baseline;
        padding: 0.38rem 0;
        border-bottom: 1px solid #f1f5f9;
        font-size: 0.78rem;
    }
    .slm-list li:last-child { border-bottom: none; }
    .slm-list__name { color: #334155; font-weight: 600; min-width: 0; }
    .slm-list__meta { color: #94a3b8; font-size: 0.68rem; display: block; margin-top: 0.1rem; }
    .slm-list__val { color: #0d9488; font-weight: 800; white-space: nowrap; }
    .slm-empty {
        font-size: 0.8rem;
        color: #94a3b8;
        padding: 0.75rem 0;
        line-height: 1.45;
    }
    .slm-side.is-district .slm-side__head {
        background: linear-gradient(180deg, #ecfdf5 0%, #fff 100%);
        border-bottom-color: #ccfbf1;
    }
    .slm-side.is-staff .slm-side__head {
        background: linear-gradient(180deg, #eff6ff 0%, #fff 100%);
        border-bottom-color: #bfdbfe;
    }
    .slm-side.is-staff .slm-side__title { color: #1e40af; }
    .slm-side__maps-link {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        margin-top: 0.65rem;
        font-size: 0.8rem;
        font-weight: 700;
        color: #0d9488;
        text-decoration: none;
    }
    .slm-side__maps-link:hover { text-decoration: underline; }
    .slm-popup { font-size: 0.8rem; line-height: 1.45; }
    .slm-popup strong { display: block; font-size: 0.9rem; margin-bottom: 0.15rem; }
    .slm-popup a { color: #0d9488; font-weight: 700; }
    @media (max-width: 960px) {
        .slm-layout { grid-template-columns: 1fr; }
        .slm-side { max-height: none; min-height: auto; }
        .slm-map-shell, #slm-map { min-height: 420px; }
    }
</style>
@endpush

@section('content')
<div class="slm-page">
    <div class="slm-toolbar">
        <div class="slm-toolbar__group">
            <span class="slm-toolbar__label">Date</span>
            <input type="date" id="slm-date" value="{{ now()->toDateString() }}">
            <button type="button" class="slm-btn" id="slm-apply">Apply</button>
            <button type="button" class="slm-btn slm-btn--ghost" id="slm-today">Today</button>
        </div>
        <div class="slm-toolbar__group">
            <span class="slm-chip">FY: <strong>{{ $activeFy?->name ?? '—' }}</strong></span>
            <span class="slm-chip" id="slm-staff-count">Staff on map: <strong>—</strong></span>
            <span class="slm-chip" id="slm-updated">Updated: <strong>—</strong></span>
        </div>
        <label class="slm-chip" style="cursor:pointer;">
            <input type="checkbox" id="slm-auto" checked style="margin:0;"> Auto-refresh (60s)
        </label>
        <div class="slm-legend" aria-label="Staff pin legend">
            <span><i style="background:#0d9488;"></i> District staff</span>
            <span><i style="background:#2563eb;"></i> Hub admin</span>
            <span><i style="background:#7c3aed;"></i> State staff</span>
            <span><i style="background:#ea580c;"></i> Field coordinator</span>
        </div>
    </div>

    <div class="slm-layout">
        <div class="slm-map-shell">
            <div id="slm-map" role="application" aria-label="Uttarakhand district live map"></div>
            <div class="slm-status" id="slm-status">Loading map…</div>
        </div>

        <aside class="slm-side" id="slm-side" aria-live="polite">
            <div class="slm-side__head">
                <p class="slm-side__eyebrow" id="slm-side-eyebrow">Uttarakhand overview</p>
                <h2 class="slm-side__title" id="slm-side-title">Hover a district</h2>
                <p class="slm-side__sub" id="slm-side-sub">Move mouse on the map — district or staff pin — details update here instantly.</p>
            </div>
            <div class="slm-side__body" id="slm-side-body">
                <div class="slm-empty">State-wide summary loads after map data is ready.</div>
            </div>
        </aside>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
<script src="https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js" crossorigin=""></script>
<script>
(function () {
    const geoJsonUrl = @json($geoJsonUrl);
    const dataUrl = @json($dataUrl);
    const dateInput = document.getElementById('slm-date');
    const statusEl = document.getElementById('slm-status');
    const staffCountEl = document.getElementById('slm-staff-count');
    const updatedEl = document.getElementById('slm-updated');
    const autoRefreshEl = document.getElementById('slm-auto');
    const sideEl = document.getElementById('slm-side');
    const sideEyebrowEl = document.getElementById('slm-side-eyebrow');
    const sideTitleEl = document.getElementById('slm-side-title');
    const sideSubEl = document.getElementById('slm-side-sub');
    const sideBodyEl = document.getElementById('slm-side-body');

    const map = L.map('slm-map', { scrollWheelZoom: true, zoomControl: true }).setView([30.0668, 79.0193], 8);
    L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap &copy; CARTO'
    }).addTo(map);

    let districtLayer = null;
    let bubbleLayer = null;
    let markerCluster = L.markerClusterGroup({
        maxClusterRadius: 42,
        spiderfyOnMaxZoom: true,
        showCoverageOnHover: false,
        zoomToBoundsOnClick: false
    });
    map.addLayer(markerCluster);

    const districtMetrics = {};
    const districtLayerByName = {};
    let livePayload = null;
    let staffPins = [];
    let hoveredDistrictName = null;
    let hoveredPinId = null;
    let refreshTimer = null;
    let moveRaf = null;

    const fmt = (n) => Number(n || 0).toLocaleString();
    const pct = (part, total) => total > 0 ? Math.round((part / total) * 100) : 0;

    const pinColor = (pin) => {
        if (pin.is_field_coordinator) return '#ea580c';
        if (pin.role === 'hub_admin') return '#2563eb';
        if (pin.role === 'state_staff') return '#7c3aed';
        return '#0d9488';
    };

    const districtFill = (name, active) => {
        const m = districtMetrics[name];
        if (!m) return active ? '#99f6e4' : '#e2e8f0';
        const activity = (m.cfa_today || 0) + (m.services_today || 0);
        const staff = m.staff_present || 0;
        if (active) return '#2dd4bf';
        if (staff > 0 && activity > 0) return '#5eead4';
        if (staff > 0) return '#99f6e4';
        if (activity > 0) return '#fde68a';
        if ((m.cfa_fy || 0) + (m.services_fy || 0) > 0) return '#f1f5f9';
        return '#e2e8f0';
    };

    const barRow = (label, value, max, color) => {
        const width = max > 0 ? Math.max(4, Math.round((value / max) * 100)) : 0;
        return `<div class="slm-bar-row">
            <span>${label}</span>
            <div class="slm-bar"><i style="width:${width}%;background:${color};"></i></div>
            <strong>${fmt(value)}</strong>
        </div>`;
    };

    const renderOverviewPanel = () => {
        hoveredDistrictName = null;
        hoveredPinId = null;
        resetDistrictHighlight();
        const s = livePayload?.summary || {};
        sideEl.classList.remove('is-district', 'is-staff');
        sideEyebrowEl.textContent = 'Uttarakhand overview';
        sideTitleEl.textContent = 'All districts';
        sideSubEl.textContent = (livePayload?.date_label || 'Today') + ' · ' + (livePayload?.fiscal_year?.name || 'FY') + ' · hover a district for detail';
        sideBodyEl.innerHTML = `
            <div class="slm-kpis">
                <div class="slm-kpi"><span>CFA today</span><strong>${fmt(s.cfa_today_state)}</strong><small>State total</small></div>
                <div class="slm-kpi"><span>CFA FY</span><strong>${fmt(s.cfa_fy_state)}</strong><small>State total</small></div>
                <div class="slm-kpi"><span>Services today</span><strong>${fmt(s.services_today_state)}</strong><small>Approved</small></div>
                <div class="slm-kpi"><span>Services FY</span><strong>${fmt(s.services_fy_state)}</strong><small>Approved</small></div>
            </div>
            <div class="slm-section">
                <p class="slm-section__label">Field activity</p>
                ${barRow('Staff on map', s.staff_on_map || 0, Math.max(s.staff_on_map || 0, 1), 'linear-gradient(90deg,#14b8a6,#0d9488)')}
                ${barRow('Districts active', s.districts_with_check_ins || 0, s.district_count || 13, 'linear-gradient(90deg,#60a5fa,#2563eb)')}
            </div>
            <p class="slm-empty">Hover any district boundary to open its breakdown here.</p>
        `;
    };

            <p class="slm-empty">Move mouse over a district or staff pin on the map.</p>
        `;
    };

    const renderStaffPanel = (pin) => {
        if (!pin) return;
        hoveredPinId = pin.user_id;
        sideEl.classList.remove('is-district');
        sideEl.classList.add('is-staff');
        sideEyebrowEl.textContent = pin.district + ' · ' + pin.hub;
        sideTitleEl.textContent = pin.name;
        const acc = pin.accuracy_m ? ' · GPS ±' + Math.round(pin.accuracy_m) + 'm' : '';
        sideSubEl.textContent = pin.role_label + ' · ' + pin.designation + ' · check-in ' + pin.marked_at + acc;

        const dm = districtMetrics[pin.district] || null;
        const districtBlock = dm ? `
            <div class="slm-section" style="margin-top:0.85rem;padding-top:0.75rem;border-top:1px solid #f1f5f9;">
                <p class="slm-section__label">${dm.name} district (today)</p>
                <div class="slm-kpis" style="margin-bottom:0;">
                    <div class="slm-kpi"><span>CFA</span><strong>${fmt(dm.cfa_today)}</strong><small>FY ${fmt(dm.cfa_fy)}</small></div>
                    <div class="slm-kpi"><span>Services</span><strong>${fmt(dm.services_today)}</strong><small>FY ${fmt(dm.services_fy)}</small></div>
                </div>
            </div>
        ` : '';

        sideBodyEl.innerHTML = `
            <div class="slm-kpis">
                <div class="slm-kpi"><span>Role</span><strong style="font-size:0.95rem;">${pin.role_label}</strong></div>
                <div class="slm-kpi"><span>Check-in</span><strong style="font-size:0.95rem;">${pin.marked_at}</strong></div>
            </div>
            <p class="slm-empty" style="padding-top:0;">Assigned: <strong>${pin.district}</strong> · ${pin.hub}</p>
            <a class="slm-side__maps-link" href="${pin.maps_url}" target="_blank" rel="noopener">Open location in Google Maps →</a>
            ${districtBlock}
        `;

        if (pin.district && districtMetrics[pin.district]) {
            highlightDistrictByName(pin.district);
        }
    };

    const renderDistrictPanel = (m) => {
        if (!m) return;
        hoveredPinId = null;
        hoveredDistrictName = m.name;
        sideEl.classList.remove('is-staff');
        sideEl.classList.add('is-district');
        highlightDistrictByName(m.name);
        sideEyebrowEl.textContent = m.hub || 'District';
        sideTitleEl.textContent = m.name;
        sideSubEl.textContent = `${m.cfa_fy_share_pct}% of state CFA FY · ${m.staff_present} staff checked in today`;

        const attendancePct = pct(m.staff_present, m.staff_total);
        const activityMax = Math.max(m.cfa_today, m.services_today, m.cfa_fy, m.services_fy, 1);
        const staffList = (m.staff_today || []).map((s) => `
            <li>
                <div class="slm-list__name">${s.name}<span class="slm-list__meta">${s.role_label}${s.is_field_coordinator ? ' · FC' : ''}</span></div>
                <span class="slm-list__val">${s.marked_at}</span>
            </li>
        `).join('');

        sideBodyEl.innerHTML = `
            <div class="slm-kpis">
                <div class="slm-kpi"><span>CFA today</span><strong>${fmt(m.cfa_today)}</strong><small>FY: ${fmt(m.cfa_fy)}</small></div>
                <div class="slm-kpi"><span>Services today</span><strong>${fmt(m.services_today)}</strong><small>FY: ${fmt(m.services_fy)}</small></div>
                <div class="slm-kpi"><span>Staff today</span><strong>${fmt(m.staff_present)}</strong><small>Of ${fmt(m.staff_total)} active</small></div>
                <div class="slm-kpi"><span>Activity FY</span><strong>${fmt(m.activity_fy)}</strong><small>CFA + services</small></div>
            </div>
            <div class="slm-section">
                <p class="slm-section__label">Today vs FY</p>
                ${barRow('CFA today', m.cfa_today, activityMax, 'linear-gradient(90deg,#14b8a6,#0d9488)')}
                ${barRow('CFA FY', m.cfa_fy, activityMax, 'linear-gradient(90deg,#5eead4,#14b8a6)')}
                ${barRow('Services today', m.services_today, activityMax, 'linear-gradient(90deg,#fb923c,#ea580c)')}
                ${barRow('Services FY', m.services_fy, activityMax, 'linear-gradient(90deg,#fdba74,#fb923c)')}
            </div>
            <div class="slm-section">
                <p class="slm-section__label">Attendance today</p>
                ${barRow('Present', m.staff_present, Math.max(m.staff_total, 1), 'linear-gradient(90deg,#4ade80,#16a34a)')}
                <p class="slm-empty" style="padding-top:0.25rem;">${attendancePct}% of assigned district staff checked in.</p>
            </div>
            <div class="slm-section">
                <p class="slm-section__label">Staff checked in</p>
                ${staffList ? `<ul class="slm-list">${staffList}</ul>` : '<p class="slm-empty">No check-ins from this district yet today.</p>'}
            </div>
        `;
    };

    const resetDistrictHighlight = () => {
        Object.values(districtLayerByName).forEach((layer) => {
            if (districtLayer) districtLayer.resetStyle(layer);
        });
    };

    const highlightDistrictByName = (name) => {
        if (!name || !districtLayerByName[name]) return;
        resetDistrictHighlight();
        const layer = districtLayerByName[name];
        layer.setStyle({
            fillColor: districtFill(name, true),
            weight: 2.5,
            color: '#0d9488',
            fillOpacity: 0.92
        });
        layer.bringToFront();
    };

    const pointInRing = (lat, lng, ring) => {
        let inside = false;
        for (let i = 0, j = ring.length - 1; i < ring.length; j = i++) {
            const yi = ring[i].lat;
            const xi = ring[i].lng;
            const yj = ring[j].lat;
            const xj = ring[j].lng;
            const intersect = ((yi > lat) !== (yj > lat))
                && (lng < (xj - xi) * (lat - yi) / ((yj - yi) || 1e-12) + xi);
            if (intersect) inside = !inside;
        }
        return inside;
    };

    const pointInLayer = (latlng, layer) => {
        if (!layer.getBounds().contains(latlng)) return false;
        const geom = layer.getLatLngs();
        const testRing = (ring) => pointInRing(latlng.lat, latlng.lng, ring);
        const testRings = (rings) => {
            if (!rings || !rings.length) return false;
            if (rings[0] && typeof rings[0].lat === 'number') return testRing(rings);
            if (Array.isArray(rings[0]) && rings[0][0] && typeof rings[0][0].lat === 'number') return testRing(rings[0]);
            return false;
        };
        if (layer instanceof L.MultiPolygon) {
            return geom.some((poly) => testRings(poly));
        }
        return testRings(geom);
    };

    const districtNameAt = (latlng) => {
        for (const name of Object.keys(districtLayerByName)) {
            if (pointInLayer(latlng, districtLayerByName[name])) return name;
        }
        return null;
    };

    const pinHoverRadiusM = () => {
        const z = map.getZoom();
        if (z >= 11) return 1500;
        if (z >= 10) return 3000;
        if (z >= 9) return 6000;
        return 11000;
    };

    const nearestPinAt = (latlng) => {
        let best = null;
        let bestDist = Infinity;
        const maxDist = pinHoverRadiusM();
        staffPins.forEach((pin) => {
            const d = map.distance(latlng, L.latLng(pin.lat, pin.lng));
            if (d <= maxDist && d < bestDist) {
                bestDist = d;
                best = pin;
            }
        });
        return best;
    };

    const handleMapHover = (latlng) => {
        const pin = nearestPinAt(latlng);
        if (pin) {
            if (hoveredPinId !== pin.user_id) renderStaffPanel(pin);
            return;
        }
        const districtName = districtNameAt(latlng);
        if (districtName && districtMetrics[districtName]) {
            if (hoveredDistrictName !== districtName || hoveredPinId !== null) {
                renderDistrictPanel(districtMetrics[districtName]);
            }
            return;
        }
        if (hoveredDistrictName !== null || hoveredPinId !== null) {
            renderOverviewPanel();
        }
    };

    const styleDistrict = (feature) => {
        const name = feature?.properties?.district || '';
        return {
            fillColor: districtFill(name, false),
            weight: 1.25,
            opacity: 1,
            color: '#94a3b8',
            fillOpacity: 0.78
        };
    };

    const bindDistrictLayer = (geojson) => {
        if (districtLayer) map.removeLayer(districtLayer);
        if (bubbleLayer) map.removeLayer(bubbleLayer);
        Object.keys(districtLayerByName).forEach((k) => delete districtLayerByName[k]);

        bubbleLayer = L.layerGroup([], { interactive: false });
        districtLayer = L.geoJSON(geojson, {
            interactive: false,
            style: styleDistrict,
            onEachFeature: (feature, layer) => {
                const name = feature?.properties?.district || '';
                if (name) districtLayerByName[name] = layer;

                try {
                    const center = layer.getBounds().getCenter();
                    const m = districtMetrics[name];
                    const score = m ? (m.cfa_today + m.services_today + m.staff_present) : 0;
                    if (score > 0) {
                        const radius = Math.min(28, 8 + Math.sqrt(score) * 4);
                        L.circleMarker(center, {
                            radius,
                            fillColor: '#14b8a6',
                            color: '#0f766e',
                            weight: 1,
                            fillOpacity: 0.28,
                            interactive: false
                        }).addTo(bubbleLayer);
                    }
                } catch (e) { /* ignore */ }
            }
        }).addTo(map);
        bubbleLayer.addTo(map);

        try {
            map.fitBounds(districtLayer.getBounds(), { padding: [24, 24] });
        } catch (e) { /* ignore */ }
    };

    const renderPins = (pins) => {
        staffPins = pins || [];
        markerCluster.clearLayers();
        staffPins.forEach((pin) => {
            const marker = L.circleMarker([pin.lat, pin.lng], {
                radius: 8,
                fillColor: pinColor(pin),
                color: '#fff',
                weight: 2,
                fillOpacity: 0.95
            });
            marker.on('mouseover', () => renderStaffPanel(pin));
            markerCluster.addLayer(marker);
        });
    };

    const applyMetrics = (payload) => {
        livePayload = payload;
        Object.keys(districtMetrics).forEach((k) => delete districtMetrics[k]);
        (payload.districts || []).forEach((d) => { districtMetrics[d.name] = d; });

        if (districtLayer) {
            districtLayer.setStyle(styleDistrict);
            if (bubbleLayer) {
                map.removeLayer(bubbleLayer);
                bubbleLayer = L.layerGroup([], { interactive: false });
                Object.keys(districtLayerByName).forEach((name) => {
                    const layer = districtLayerByName[name];
                    try {
                        const center = layer.getBounds().getCenter();
                        const m = districtMetrics[name];
                        const score = m ? (m.cfa_today + m.services_today + m.staff_present) : 0;
                        if (score > 0) {
                            const radius = Math.min(28, 8 + Math.sqrt(score) * 4);
                            L.circleMarker(center, {
                                radius,
                                fillColor: '#14b8a6',
                                color: '#0f766e',
                                weight: 1,
                                fillOpacity: 0.28,
                                interactive: false
                            }).addTo(bubbleLayer);
                        }
                    } catch (e) { /* ignore */ }
                });
                bubbleLayer.addTo(map);
            }
        }

        renderPins(payload.staff_pins || []);

        if (hoveredPinId !== null) {
            const pin = staffPins.find((p) => p.user_id === hoveredPinId);
            if (pin) renderStaffPanel(pin);
            else if (hoveredDistrictName && districtMetrics[hoveredDistrictName]) {
                renderDistrictPanel(districtMetrics[hoveredDistrictName]);
            } else {
                renderOverviewPanel();
            }
        } else if (hoveredDistrictName && districtMetrics[hoveredDistrictName]) {
            renderDistrictPanel(districtMetrics[hoveredDistrictName]);
        } else {
            renderOverviewPanel();
        }

        staffCountEl.innerHTML = 'Staff on map: <strong>' + (payload.summary?.staff_on_map ?? 0) + '</strong>';
        const updated = payload.updated_at ? new Date(payload.updated_at) : new Date();
        updatedEl.innerHTML = 'Updated: <strong>' + updated.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) + '</strong>';
        statusEl.textContent = payload.date_label + ' · move mouse on map → side panel updates';
    };

    const loadData = async () => {
        statusEl.textContent = 'Refreshing…';
        const date = dateInput.value || '';
        try {
            const res = await fetch(dataUrl + '?date=' + encodeURIComponent(date), {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            });
            if (!res.ok) throw new Error('HTTP ' + res.status);
            applyMetrics(await res.json());
        } catch (err) {
            statusEl.textContent = 'Could not load live data.';
            console.error(err);
        }
    };

    const scheduleRefresh = () => {
        if (refreshTimer) clearInterval(refreshTimer);
        if (autoRefreshEl.checked) refreshTimer = setInterval(loadData, 60000);
    };

    document.getElementById('slm-apply').addEventListener('click', () => { loadData(); scheduleRefresh(); });
    document.getElementById('slm-today').addEventListener('click', () => {
        dateInput.value = new Date().toISOString().slice(0, 10);
        loadData();
        scheduleRefresh();
    });
    autoRefreshEl.addEventListener('change', scheduleRefresh);

    map.on('mousemove', (e) => {
        if (moveRaf) cancelAnimationFrame(moveRaf);
        moveRaf = requestAnimationFrame(() => handleMapHover(e.latlng));
    });

    (async function init() {
        try {
            const geoRes = await fetch(geoJsonUrl);
            bindDistrictLayer(await geoRes.json());
            await loadData();
            scheduleRefresh();
        } catch (err) {
            statusEl.textContent = 'Failed to load map boundaries.';
            console.error(err);
        }
    })();
})();
</script>
@endpush
