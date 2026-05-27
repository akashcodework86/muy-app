@extends('layouts.admin')

@section('title', 'Daily attendance')
@section('heading', 'Daily attendance')

@push('styles')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="">
<style>
    .sdci-page { font-family: 'DM Sans', sans-serif; padding-bottom: 2.5rem; }
    .sdci-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1.25rem;
        align-items: start;
    }
    @media (max-width: 960px) {
        .sdci-grid { grid-template-columns: 1fr; }
    }
    .sdci-panel {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 18px;
        padding: 1.35rem 1.5rem;
        box-shadow: 0 8px 28px rgba(15, 23, 42, 0.06);
    }
    .sdci-panel--map {
        position: sticky;
        top: 1rem;
    }
    .sdci-kicker {
        font-size: 0.72rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        color: #6366f1;
        margin: 0 0 0.35rem;
    }
    .sdci-panel h2 {
        margin: 0 0 0.75rem;
        font-size: 1.15rem;
        color: #0f172a;
    }
    .sdci-stats {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
        margin-bottom: 1.25rem;
    }
    .sdci-stat {
        flex: 1;
        min-width: 120px;
        padding: 0.65rem 0.85rem;
        border-radius: 12px;
        background: linear-gradient(135deg, #eef2ff, #f0fdfa);
        border: 1px solid #c7d2fe;
    }
    .sdci-stat span { display: block; font-size: 0.68rem; color: #64748b; font-weight: 700; text-transform: uppercase; }
    .sdci-stat strong { font-size: 1.2rem; color: #0f172a; }
    .sdci-steps {
        list-style: none;
        margin: 0 0 1.25rem;
        padding: 0;
        display: flex;
        flex-direction: column;
        gap: 0.65rem;
    }
    .sdci-step {
        display: flex;
        gap: 0.75rem;
        align-items: flex-start;
        padding: 0.75rem;
        border-radius: 12px;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        transition: border-color 0.2s, background 0.2s;
    }
    .sdci-step.is-done {
        background: #ecfdf5;
        border-color: #6ee7b7;
    }
    .sdci-step.is-active {
        background: #eef2ff;
        border-color: #a5b4fc;
        box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.12);
    }
    .sdci-step__num {
        flex-shrink: 0;
        width: 1.75rem;
        height: 1.75rem;
        border-radius: 999px;
        background: #4f46e5;
        color: #fff;
        font-weight: 800;
        font-size: 0.8rem;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .sdci-step.is-done .sdci-step__num { background: #059669; }
    .sdci-step__body strong { display: block; font-size: 0.88rem; color: #0f172a; margin-bottom: 0.15rem; }
    .sdci-step__body span { font-size: 0.8rem; color: #64748b; line-height: 1.45; }
    .sdci-highlight {
        padding: 1rem;
        border-radius: 12px;
        margin-bottom: 1rem;
        font-size: 0.88rem;
        line-height: 1.5;
    }
    .sdci-highlight--ok {
        background: #ecfdf5;
        border: 1px solid #6ee7b7;
        color: #065f46;
    }
    .sdci-highlight--pending {
        background: #fff7ed;
        border: 1px solid #fdba74;
        color: #9a3412;
    }
    .sdci-coords {
        font-family: ui-monospace, monospace;
        font-size: 0.82rem;
        margin-top: 0.5rem;
        color: #334155;
    }
    .sdci-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.4rem;
        padding: 0.75rem 1.35rem;
        border: none;
        border-radius: 12px;
        background: linear-gradient(135deg, #4f46e5, #6366f1);
        color: #fff;
        font-weight: 700;
        font-size: 0.92rem;
        cursor: pointer;
        font-family: inherit;
        box-shadow: 0 8px 22px rgba(79, 70, 229, 0.3);
        transition: transform 0.2s, box-shadow 0.2s;
        width: 100%;
    }
    .sdci-btn:disabled {
        opacity: 0.5;
        cursor: not-allowed;
        transform: none;
    }
    .sdci-btn:not(:disabled):hover {
        transform: translateY(-1px);
        box-shadow: 0 12px 28px rgba(79, 70, 229, 0.35);
    }
    .sdci-btn.is-loading::after {
        content: '';
        width: 1rem;
        height: 1rem;
        border: 2px solid rgba(255,255,255,0.4);
        border-top-color: #fff;
        border-radius: 50%;
        animation: sdci-spin 0.7s linear infinite;
        margin-left: 0.35rem;
    }
    @keyframes sdci-spin { to { transform: rotate(360deg); } }
    .sdci-error { margin-top: 0.75rem; color: #b91c1c; font-size: 0.85rem; }
    .sdci-map-link { color: #0d9488; font-weight: 700; font-size: 0.85rem; }
    #sdci-map {
        height: 320px;
        border-radius: 14px;
        border: 1px solid #e2e8f0;
        background: #f1f5f9;
    }
    .sdci-map-meta {
        margin-top: 0.65rem;
        font-size: 0.8rem;
        color: #64748b;
        line-height: 1.45;
    }
    .sdci-history {
        margin-top: 1.5rem;
    }
    .sdci-history h2 { margin-bottom: 0.75rem; }
    .sdci-history-table-wrap {
        overflow-x: auto;
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        background: #fff;
    }
    .sdci-history-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.86rem;
    }
    .sdci-history-table th,
    .sdci-history-table td {
        padding: 0.65rem 0.85rem;
        text-align: left;
        border-bottom: 1px solid #f1f5f9;
    }
    .sdci-history-table th {
        background: #f8fafc;
        font-size: 0.72rem;
        text-transform: uppercase;
        color: #64748b;
    }
    .sdci-history-map-btn {
        padding: 0.25rem 0.55rem;
        border-radius: 6px;
        background: #ecfeff;
        color: #0f766e;
        border: 1px solid #99f6e4;
        font-size: 0.75rem;
        font-weight: 700;
        cursor: pointer;
        font-family: inherit;
    }
</style>
@endpush

@section('content')
<div class="sdci-page">
    <div class="sdci-stats">
        <div class="sdci-stat">
            <span>Days recorded</span>
            <strong>{{ number_format($stats['total_days']) }}</strong>
        </div>
        <div class="sdci-stat">
            <span>This month</span>
            <strong>{{ number_format($stats['this_month']) }}</strong>
        </div>
        <div class="sdci-stat">
            <span>Today</span>
            <strong>{{ $todayCheckIn ? 'Present' : 'Pending' }}</strong>
        </div>
    </div>

    <div class="sdci-grid">
        <div class="sdci-panel">
            <p class="sdci-kicker">Check-in</p>
            <h2>Mark today’s attendance</h2>

            <ol class="sdci-steps" id="sdci-steps">
                <li class="sdci-step is-active" data-step="1">
                    <span class="sdci-step__num">1</span>
                    <div class="sdci-step__body">
                        <strong>Allow location access</strong>
                        <span>When prompted, choose “Allow” so we can capture your GPS coordinates.</span>
                    </div>
                </li>
                <li class="sdci-step" data-step="2">
                    <span class="sdci-step__num">2</span>
                    <div class="sdci-step__body">
                        <strong>Confirm on the map</strong>
                        <span>Your pin appears on the live map on the right when location is detected.</span>
                    </div>
                </li>
                <li class="sdci-step" data-step="3">
                    <span class="sdci-step__num">3</span>
                    <div class="sdci-step__body">
                        <strong>Submit attendance</strong>
                        <span>Tap the button below once — only one check-in per day is allowed.</span>
                    </div>
                </li>
            </ol>

            @if ($todayCheckIn)
                <div class="sdci-highlight sdci-highlight--ok">
                    <strong>✓ Attendance marked for today</strong><br>
                    Time: {{ $todayCheckIn->marked_at->timezone(config('app.timezone'))->format('g:i A, d M Y') }}
                    <p class="sdci-coords">
                        {{ number_format((float) $todayCheckIn->latitude, 6) }},
                        {{ number_format((float) $todayCheckIn->longitude, 6) }}
                        @if ($todayCheckIn->accuracy_m)
                            · accuracy ±{{ number_format((float) $todayCheckIn->accuracy_m, 0) }} m
                        @endif
                    </p>
                    <a href="{{ $todayCheckIn->googleMapsUrl() }}" class="sdci-map-link" target="_blank" rel="noopener">Open in Google Maps →</a>
                </div>
            @else
                <div class="sdci-highlight sdci-highlight--pending">
                    <strong>Not marked yet today</strong><br>
                    @if ($showReminder)
                        Reminder is active after 9:00 AM until you check in.
                    @else
                        You can mark attendance any time before midnight.
                    @endif
                </div>

                <form method="post" action="{{ route('staff-daily-check-in.store') }}" id="sdci-form">
                    @csrf
                    <input type="hidden" name="latitude" id="sdci-lat">
                    <input type="hidden" name="longitude" id="sdci-lng">
                    <input type="hidden" name="accuracy_m" id="sdci-acc">
                    <button type="submit" class="sdci-btn is-loading" id="sdci-submit" disabled>
                        Detecting your location…
                    </button>
                    <p class="sdci-error" id="sdci-error" hidden></p>
                </form>
            @endif
        </div>

        <div class="sdci-panel sdci-panel--map">
            <p class="sdci-kicker">Live location</p>
            <h2>Your coordinates</h2>
            <div id="sdci-map" role="img" aria-label="Map showing your check-in location"></div>
            <p class="sdci-map-meta" id="sdci-map-meta">
                @if ($todayCheckIn)
                    Showing today’s marked location.
                @else
                    Map updates when GPS is detected.
                @endif
            </p>
        </div>
    </div>

    <section class="sdci-history" id="history">
        <p class="sdci-kicker">Your records</p>
        <h2>Attendance history</h2>
        <div class="sdci-history-table-wrap">
            <table class="sdci-history-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Coordinates</th>
                        <th>Accuracy</th>
                        <th>Map</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($history as $i => $record)
                        <tr>
                            <td>{{ $i + 1 }}</td>
                            <td>{{ $record->check_in_date->format('d M Y') }}</td>
                            <td>{{ $record->marked_at->timezone(config('app.timezone'))->format('g:i A') }}</td>
                            <td class="sdci-coords" style="margin:0;">
                                {{ number_format((float) $record->latitude, 5) }}, {{ number_format((float) $record->longitude, 5) }}
                            </td>
                            <td>{{ $record->accuracy_m ? '±'.number_format((float) $record->accuracy_m, 0).' m' : '—' }}</td>
                            <td>
                                <button type="button" class="sdci-history-map-btn"
                                    data-lat="{{ $record->latitude }}"
                                    data-lng="{{ $record->longitude }}"
                                    data-label="{{ $record->check_in_date->format('d M Y') }}">
                                    View on map
                                </button>
                                <a href="{{ $record->googleMapsUrl() }}" target="_blank" rel="noopener" class="sdci-map-link" style="margin-left:0.35rem;">↗</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" style="text-align:center;color:#64748b;padding:1.5rem;">No attendance history yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
@endsection

@push('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
<script>
(function () {
    const defaultLat = {{ $todayCheckIn ? (float) $todayCheckIn->latitude : '28.6139' }};
    const defaultLng = {{ $todayCheckIn ? (float) $todayCheckIn->longitude : '77.2090' }};
    const hasToday = {{ $todayCheckIn ? 'true' : 'false' }};
    const zoom = hasToday ? 15 : 5;

    const map = L.map('sdci-map', { scrollWheelZoom: true }).setView([defaultLat, defaultLng], zoom);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap'
    }).addTo(map);

    let marker = null;
    function setMarker(lat, lng, label) {
        if (marker) map.removeLayer(marker);
        marker = L.marker([lat, lng]).addTo(map);
        if (label) marker.bindPopup(label).openPopup();
        map.setView([lat, lng], 15);
        document.getElementById('sdci-map-meta').textContent =
            'Lat ' + lat.toFixed(6) + ', Lng ' + lng.toFixed(6);
    }

    if (hasToday) {
        setMarker(defaultLat, defaultLng, 'Today\'s check-in');
        document.querySelectorAll('.sdci-step').forEach(function (s) { s.classList.add('is-done'); s.classList.remove('is-active'); });
    }

    document.querySelectorAll('.sdci-history-map-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const lat = parseFloat(btn.dataset.lat);
            const lng = parseFloat(btn.dataset.lng);
            setMarker(lat, lng, btn.dataset.label || 'Check-in');
            document.getElementById('sdci-map').scrollIntoView({ behavior: 'smooth', block: 'center' });
        });
    });

    @if (! $todayCheckIn)
    const latInput = document.getElementById('sdci-lat');
    const lngInput = document.getElementById('sdci-lng');
    const accInput = document.getElementById('sdci-acc');
    const submitBtn = document.getElementById('sdci-submit');
    const errorEl = document.getElementById('sdci-error');
    const steps = document.querySelectorAll('.sdci-step');

    function setStep(n) {
        steps.forEach(function (s) {
            const sn = parseInt(s.dataset.step, 10);
            s.classList.toggle('is-active', sn === n);
            s.classList.toggle('is-done', sn < n);
        });
    }

    function showError(msg) {
        errorEl.hidden = false;
        errorEl.textContent = msg;
        submitBtn.disabled = true;
        submitBtn.classList.remove('is-loading');
        submitBtn.textContent = 'Location required — refresh & allow GPS';
    }

    if (!navigator.geolocation) {
        showError('Your browser does not support GPS. Use a phone or modern browser.');
    } else {
        navigator.geolocation.getCurrentPosition(
            function (pos) {
                const lat = pos.coords.latitude;
                const lng = pos.coords.longitude;
                latInput.value = lat;
                lngInput.value = lng;
                if (pos.coords.accuracy) accInput.value = pos.coords.accuracy;
                setMarker(lat, lng, 'Your current location');
                setStep(3);
                submitBtn.disabled = false;
                submitBtn.classList.remove('is-loading');
                submitBtn.textContent = 'Mark attendance with current location';
                errorEl.hidden = true;
            },
            function (err) {
                let msg = 'Could not get location. Enable GPS and allow permission, then refresh.';
                if (err.code === 1) msg = 'Location denied. Allow location for this site in browser settings.';
                showError(msg);
                setStep(1);
            },
            { enableHighAccuracy: true, timeout: 25000, maximumAge: 0 }
        );
        setStep(1);
    }
    @endif

    setTimeout(function () { map.invalidateSize(); }, 200);
})();
</script>
@endpush
