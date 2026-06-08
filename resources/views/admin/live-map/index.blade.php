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
    .slm-map-shell {
        flex: 1; min-height: 520px; position: relative;
        border: 1px solid #e2e8f0; border-radius: 14px; overflow: hidden;
        box-shadow: 0 4px 20px rgba(15, 23, 42, 0.06);
    }
    #slm-map { width: 100%; height: 100%; min-height: 520px; background: #eef2f6; }
    .slm-status {
        position: absolute; left: 0.75rem; bottom: 0.75rem; z-index: 500;
        padding: 0.4rem 0.65rem; border-radius: 8px; background: rgba(255,255,255,0.92);
        border: 1px solid #e2e8f0; font-size: 0.72rem; color: #475569; font-weight: 600;
        box-shadow: 0 2px 8px rgba(15,23,42,0.08);
    }
    .slm-tooltip { font-size: 0.78rem; line-height: 1.45; min-width: 11rem; }
    .slm-tooltip h3 { margin: 0 0 0.35rem; font-size: 0.88rem; color: #0f172a; }
    .slm-tooltip dl { margin: 0; display: grid; grid-template-columns: auto 1fr; gap: 0.15rem 0.65rem; }
    .slm-tooltip dt { color: #64748b; font-weight: 600; }
    .slm-tooltip dd { margin: 0; font-weight: 700; color: #0f172a; text-align: right; }
    .slm-popup { font-size: 0.8rem; line-height: 1.45; }
    .slm-popup strong { display: block; font-size: 0.9rem; margin-bottom: 0.15rem; }
    .slm-popup a { color: #0d9488; font-weight: 700; }
    @media (max-width: 768px) {
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

    <div class="slm-map-shell">
        <div id="slm-map" role="application" aria-label="Uttarakhand district live map"></div>
        <div class="slm-status" id="slm-status">Loading map…</div>
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

    const map = L.map('slm-map', { scrollWheelZoom: true }).setView([30.0668, 79.0193], 8);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 18,
        attribution: '&copy; OpenStreetMap'
    }).addTo(map);

    let districtLayer = null;
    let markerCluster = L.markerClusterGroup({ maxClusterRadius: 42, spiderfyOnMaxZoom: true });
    map.addLayer(markerCluster);

    const districtMetrics = {};
    let refreshTimer = null;

    const pinColor = (pin) => {
        if (pin.is_field_coordinator) return '#ea580c';
        if (pin.role === 'hub_admin') return '#2563eb';
        if (pin.role === 'state_staff') return '#7c3aed';
        return '#0d9488';
    };

    const districtFill = (name) => {
        const m = districtMetrics[name];
        if (!m) return '#e2e8f0';
        const activity = (m.cfa_today || 0) + (m.services_today || 0);
        const staff = m.staff_present || 0;
        if (staff > 0 && activity > 0) return '#5eead4';
        if (staff > 0) return '#99f6e4';
        if (activity > 0) return '#fde68a';
        if ((m.cfa_fy || 0) + (m.services_fy || 0) > 0) return '#f1f5f9';
        return '#e2e8f0';
    };

    const tooltipHtml = (m) => {
        if (!m) return '<div class="slm-tooltip">No data</div>';
        return `<div class="slm-tooltip">
            <h3>${m.name}</h3>
            <dl>
                <dt>Staff today</dt><dd>${m.staff_present} / ${m.staff_total}</dd>
                <dt>CFA today</dt><dd>${Number(m.cfa_today).toLocaleString()}</dd>
                <dt>CFA FY</dt><dd>${Number(m.cfa_fy).toLocaleString()}</dd>
                <dt>Services today</dt><dd>${Number(m.services_today).toLocaleString()}</dd>
                <dt>Services FY</dt><dd>${Number(m.services_fy).toLocaleString()}</dd>
            </dl>
        </div>`;
    };

    const styleDistrict = (feature) => {
        const name = feature?.properties?.district || '';
        return {
            fillColor: districtFill(name),
            weight: 1.5,
            opacity: 1,
            color: '#64748b',
            fillOpacity: 0.72
        };
    };

    const bindDistrictLayer = (geojson) => {
        if (districtLayer) {
            map.removeLayer(districtLayer);
        }
        districtLayer = L.geoJSON(geojson, {
            style: styleDistrict,
            onEachFeature: (feature, layer) => {
                const name = feature?.properties?.district || '';
                layer.bindTooltip(() => tooltipHtml(districtMetrics[name]), {
                    sticky: true,
                    direction: 'top',
                    className: 'slm-tooltip-wrap'
                });
                layer.on('mouseover', () => layer.setStyle({ weight: 2.5, color: '#0d9488', fillOpacity: 0.88 }));
                layer.on('mouseout', () => districtLayer.resetStyle(layer));
            }
        }).addTo(map);
        try {
            map.fitBounds(districtLayer.getBounds(), { padding: [24, 24] });
        } catch (e) { /* ignore */ }
    };

    const renderPins = (pins) => {
        markerCluster.clearLayers();
        pins.forEach((pin) => {
            const marker = L.circleMarker([pin.lat, pin.lng], {
                radius: 8,
                fillColor: pinColor(pin),
                color: '#fff',
                weight: 2,
                fillOpacity: 0.95
            });
            const acc = pin.accuracy_m ? ` · ±${Math.round(pin.accuracy_m)} m` : '';
            marker.bindPopup(`<div class="slm-popup">
                <strong>${pin.name}</strong>
                ${pin.role_label} · ${pin.designation}<br>
                ${pin.hub} / ${pin.district}<br>
                Check-in: ${pin.marked_at}${acc}<br>
                <a href="${pin.maps_url}" target="_blank" rel="noopener">Open in Google Maps</a>
            </div>`);
            markerCluster.addLayer(marker);
        });
    };

    const applyMetrics = (payload) => {
        Object.keys(districtMetrics).forEach((k) => delete districtMetrics[k]);
        (payload.districts || []).forEach((d) => { districtMetrics[d.name] = d; });
        if (districtLayer) {
            districtLayer.setStyle(styleDistrict);
        }
        renderPins(payload.staff_pins || []);
        staffCountEl.innerHTML = 'Staff on map: <strong>' + (payload.summary?.staff_on_map ?? 0) + '</strong>';
        const updated = payload.updated_at ? new Date(payload.updated_at) : new Date();
        updatedEl.innerHTML = 'Updated: <strong>' + updated.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) + '</strong>';
        statusEl.textContent = payload.date_label + ' · ' + (payload.fiscal_year?.name || 'FY') + ' · hover district for CFA & services';
    };

    const loadData = async () => {
        statusEl.textContent = 'Refreshing…';
        const date = dateInput.value || '';
        try {
            const res = await fetch(dataUrl + '?date=' + encodeURIComponent(date), {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            });
            if (!res.ok) throw new Error('HTTP ' + res.status);
            const payload = await res.json();
            applyMetrics(payload);
        } catch (err) {
            statusEl.textContent = 'Could not load live data. Retry or check connection.';
            console.error(err);
        }
    };

    const scheduleRefresh = () => {
        if (refreshTimer) clearInterval(refreshTimer);
        if (autoRefreshEl.checked) {
            refreshTimer = setInterval(loadData, 60000);
        }
    };

    document.getElementById('slm-apply').addEventListener('click', () => { loadData(); scheduleRefresh(); });
    document.getElementById('slm-today').addEventListener('click', () => {
        dateInput.value = new Date().toISOString().slice(0, 10);
        loadData();
        scheduleRefresh();
    });
    autoRefreshEl.addEventListener('change', scheduleRefresh);

    (async function init() {
        try {
            const geoRes = await fetch(geoJsonUrl);
            const geojson = await geoRes.json();
            bindDistrictLayer(geojson);
            await loadData();
            scheduleRefresh();
        } catch (err) {
            statusEl.textContent = 'Failed to load Uttarakhand district boundaries.';
            console.error(err);
        }
    })();
})();
</script>
@endpush
