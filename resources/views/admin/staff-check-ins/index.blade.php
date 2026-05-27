@extends('layouts.admin')

@section('title', 'Staff daily attendance')
@section('heading', 'Staff daily attendance')

@push('styles')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="">
<style>
    .sdca { font-family: 'DM Sans', sans-serif; display: flex; flex-direction: column; gap: 1.25rem; padding-bottom: 2rem; }
    .sdca-head { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 0.75rem; }
    .sdca-chips { display: flex; flex-wrap: wrap; gap: 0.6rem; }
    .sdca-chip {
        background: #fff; border: 1px solid #e2e8f0; border-radius: 12px;
        padding: 0.65rem 1rem; min-width: 130px;
        box-shadow: 0 2px 10px rgba(15,23,42,0.04);
    }
    .sdca-chip span { display: block; font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.06em; color: #64748b; font-weight: 700; }
    .sdca-chip strong { font-size: 1.35rem; color: #0f172a; }
    .sdca-chip--present strong { color: #15803d; }
    .sdca-chip--absent strong { color: #b91c1c; }
    .sdca-chip--rate strong { color: #4f46e5; }
    .sdca-filter {
        background: #fff; border: 1px solid #e2e8f0; border-radius: 14px;
        padding: 1rem 1.15rem;
    }
    .sdca-filter-row { display: flex; flex-wrap: wrap; gap: 0.55rem; align-items: flex-end; }
    .sdca-field { display: flex; flex-direction: column; gap: 0.25rem; min-width: 140px; }
    .sdca-field label { font-size: 0.72rem; font-weight: 600; color: #475569; }
    .sdca-field input, .sdca-field select {
        padding: 0.5rem 0.6rem; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 0.86rem;
    }
    .sdca-btn {
        padding: 0.52rem 1rem; border: none; border-radius: 8px;
        background: #4f46e5; color: #fff; font-weight: 700; font-size: 0.85rem; cursor: pointer;
        text-decoration: none; display: inline-flex; align-items: center; gap: 0.35rem;
    }
    .sdca-btn--ghost {
        background: transparent; color: #4f46e5; border: 1px solid #4f46e5;
    }
    .sdca-btn--excel {
        background: #059669; color: #fff;
    }
    .sdca-btn--excel:hover { background: #047857; color: #fff; }
    .sdca-btn--sm {
        padding: 0.35rem 0.65rem;
        font-size: 0.78rem;
    }
    .sdca-table-wrap {
        overflow-x: auto; background: #fff; border: 1px solid #e2e8f0; border-radius: 14px;
    }
    .sdca-table { width: 100%; border-collapse: collapse; font-size: 0.86rem; }
    .sdca-table th, .sdca-table td { padding: 0.65rem 0.85rem; text-align: left; border-bottom: 1px solid #f1f5f9; }
    .sdca-table th { background: #f8fafc; font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b; }
    .sdca-table th.sn, .sdca-table td.sn { width: 3rem; text-align: center; color: #64748b; font-weight: 700; }
    .sdca-table tr.row--absent { background: #fef2f2; }
    .sdca-table tr.row--present { background: #f0fdf4; }
    .sdca-badge {
        display: inline-block; padding: 0.2rem 0.5rem; border-radius: 999px; font-size: 0.72rem; font-weight: 700;
    }
    .sdca-badge--present { background: #dcfce7; color: #166534; }
    .sdca-badge--absent { background: #fee2e2; color: #991b1b; }
    .sdca-role { font-size: 0.75rem; color: #64748b; }

    .sdca-modal[hidden] { display: none; }
    .sdca-modal {
        position: fixed; inset: 0; z-index: 9999;
        display: flex; align-items: center; justify-content: center; padding: 1rem;
    }
    .sdca-modal__backdrop { position: absolute; inset: 0; background: rgba(15,23,42,0.5); backdrop-filter: blur(3px); }
    .sdca-modal__panel {
        position: relative; width: min(100%, 520px);
        background: #fff; border-radius: 18px; padding: 1.25rem;
        box-shadow: 0 24px 50px rgba(15,23,42,0.2);
        max-height: 90vh; overflow-y: auto;
    }
    .sdca-modal__close {
        position: absolute; top: 0.6rem; right: 0.75rem;
        width: 2rem; height: 2rem; border: none; border-radius: 999px;
        background: #f1f5f9; font-size: 1.25rem; cursor: pointer; line-height: 1;
    }
    .sdca-modal__title { margin: 0 0 0.35rem; font-size: 1.1rem; }
    .sdca-modal__meta { margin: 0 0 0.75rem; font-size: 0.85rem; color: #64748b; }
    #sdca-detail-map { height: 280px; border-radius: 12px; border: 1px solid #e2e8f0; }
    .sdca-modal__coords {
        margin-top: 0.65rem; font-family: ui-monospace, monospace;
        font-size: 0.82rem; color: #334155;
    }
    .sdca-modal__link { display: inline-block; margin-top: 0.5rem; color: #0d9488; font-weight: 700; font-size: 0.85rem; }
</style>
@endpush

@section('content')
@php
    $presentPct = $summary['total'] > 0 ? round(($summary['present'] / $summary['total']) * 100, 1) : 0;
    $exportQuery = http_build_query(array_filter([
        'date' => $date->toDateString(),
        'role' => $roleFilter ?: null,
        'hub_id' => $hubId > 0 ? $hubId : null,
        'district_id' => $districtId > 0 ? $districtId : null,
        'status' => $statusFilter ?: null,
    ]));
@endphp
<div class="sdca">
    <div class="sdca-head">
        <div class="sdca-chips">
            <div class="sdca-chip">
                <span>Total staff</span>
                <strong>{{ number_format($summary['total']) }}</strong>
            </div>
            <div class="sdca-chip sdca-chip--present">
                <span>Present</span>
                <strong>{{ number_format($summary['present']) }}</strong>
            </div>
            <div class="sdca-chip sdca-chip--absent">
                <span>Absent</span>
                <strong>{{ number_format($summary['absent']) }}</strong>
            </div>
            <div class="sdca-chip sdca-chip--rate">
                <span>Attendance rate</span>
                <strong>{{ $presentPct }}%</strong>
            </div>
            <div class="sdca-chip">
                <span>Date</span>
                <strong style="font-size:1rem;">{{ $date->format('d M Y') }}</strong>
            </div>
        </div>
        <a href="{{ route('admin.staff-check-ins.export') }}?{{ $exportQuery }}" class="sdca-btn sdca-btn--excel">
            ⬇ Export Excel
        </a>
    </div>

    <form method="get" class="sdca-filter">
        <div class="sdca-filter-row">
            <div class="sdca-field">
                <label for="date">Date</label>
                <input type="date" name="date" id="date" value="{{ $date->toDateString() }}">
            </div>
            <div class="sdca-field">
                <label for="role">Role</label>
                <select name="role" id="role">
                    <option value="">All roles</option>
                    @foreach ($roleOptions as $value => $label)
                        <option value="{{ $value }}" @selected($roleFilter === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="sdca-field">
                <label for="hub_id">Hub</label>
                <select name="hub_id" id="hub_id">
                    <option value="">All hubs</option>
                    @foreach ($hubs as $hub)
                        <option value="{{ $hub->id }}" @selected($hubId === (int) $hub->id)>{{ $hub->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="sdca-field">
                <label for="district_id">District</label>
                <select name="district_id" id="district_id">
                    <option value="">All districts</option>
                    @foreach ($districts as $district)
                        <option value="{{ $district->id }}" @selected($districtId === (int) $district->id)>{{ $district->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="sdca-field">
                <label for="status">Status</label>
                <select name="status" id="status">
                    <option value="">All</option>
                    <option value="present" @selected($statusFilter === 'present')>Present only</option>
                    <option value="absent" @selected($statusFilter === 'absent')>Absent only</option>
                </select>
            </div>
            <button type="submit" class="sdca-btn">Apply</button>
            <a href="{{ route('admin.staff-check-ins.index') }}" class="sdca-btn sdca-btn--ghost">Reset</a>
        </div>
    </form>

    <div class="sdca-table-wrap">
        <table class="sdca-table">
            <thead>
                <tr>
                    <th class="sn">#</th>
                    <th>Staff</th>
                    <th>Role / designation</th>
                    <th>Hub / district</th>
                    <th>Status</th>
                    <th>Check-in time</th>
                    <th>View location</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($summary['rows'] as $index => $row)
                    @php
                        $user = $row['user'];
                        $checkIn = $row['check_in'];
                    @endphp
                    <tr class="{{ $row['present'] ? 'row--present' : 'row--absent' }}">
                        <td class="sn">{{ $index + 1 }}</td>
                        <td>
                            <strong>{{ $user->name }}</strong><br>
                            <span class="sdca-role">{{ $user->email }}</span>
                        </td>
                        <td>
                            {{ $roleOptions[$user->role] ?? $user->role }}<br>
                            <span class="sdca-role">{{ $user->designationRecord?->name ?? '—' }}</span>
                        </td>
                        <td>
                            {{ $user->hub?->name ?? '—' }}<br>
                            <span class="sdca-role">{{ $user->district?->name ?? '—' }}</span>
                        </td>
                        <td>
                            @if ($row['present'])
                                <span class="sdca-badge sdca-badge--present">Present</span>
                            @else
                                <span class="sdca-badge sdca-badge--absent">Absent</span>
                            @endif
                        </td>
                        <td>
                            @if ($checkIn)
                                {{ $checkIn->marked_at->timezone(config('app.timezone'))->format('g:i A') }}
                            @else
                                —
                            @endif
                        </td>
                        <td>
                            @if ($checkIn)
                                <button type="button" class="sdca-btn sdca-btn--sm sdca-view-detail"
                                    data-name="{{ e($user->name) }}"
                                    data-role="{{ e($roleOptions[$user->role] ?? $user->role) }}"
                                    data-designation="{{ e($user->designationRecord?->name ?? '—') }}"
                                    data-hub="{{ e($user->hub?->name ?? '—') }}"
                                    data-district="{{ e($user->district?->name ?? '—') }}"
                                    data-time="{{ $checkIn->marked_at->timezone(config('app.timezone'))->format('g:i A, d M Y') }}"
                                    data-lat="{{ $checkIn->latitude }}"
                                    data-lng="{{ $checkIn->longitude }}"
                                    data-accuracy="{{ $checkIn->accuracy_m ?? '' }}"
                                    data-maps="{{ $checkIn->googleMapsUrl() }}">
                                    View location
                                </button>
                            @else
                                <span class="sdca-role">No check-in</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" style="text-align:center;color:#64748b;padding:2rem;">No staff match these filters.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="sdca-modal" id="sdca-detail-modal" role="dialog" aria-modal="true" hidden>
    <div class="sdca-modal__backdrop" data-sdca-close></div>
    <div class="sdca-modal__panel">
        <button type="button" class="sdca-modal__close" data-sdca-close aria-label="Close">&times;</button>
        <h2 class="sdca-modal__title" id="sdca-detail-name">—</h2>
        <p class="sdca-modal__meta" id="sdca-detail-meta"></p>
        <div id="sdca-detail-map"></div>
        <p class="sdca-modal__coords" id="sdca-detail-coords"></p>
        <a href="#" id="sdca-detail-maps-link" class="sdca-modal__link" target="_blank" rel="noopener">Open in Google Maps →</a>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
<script>
(function () {
    const modal = document.getElementById('sdca-detail-modal');
    let detailMap = null;
    let detailMarker = null;

    function closeModal() {
        modal.hidden = true;
        document.body.style.overflow = '';
        if (detailMap) {
            detailMap.remove();
            detailMap = null;
            detailMarker = null;
        }
    }

    function openModal(btn) {
        document.getElementById('sdca-detail-name').textContent = btn.dataset.name;
        document.getElementById('sdca-detail-meta').textContent =
            (btn.dataset.role || '') + ' · ' + (btn.dataset.designation || '') +
            ' · ' + (btn.dataset.hub || '') + ' / ' + (btn.dataset.district || '') +
            ' · ' + (btn.dataset.time || '');
        const lat = parseFloat(btn.dataset.lat);
        const lng = parseFloat(btn.dataset.lng);
        const acc = btn.dataset.accuracy;
        document.getElementById('sdca-detail-coords').textContent =
            'Lat ' + lat.toFixed(6) + ', Lng ' + lng.toFixed(6) +
            (acc ? ' · ±' + parseFloat(acc).toFixed(0) + ' m' : '');
        const mapsLink = document.getElementById('sdca-detail-maps-link');
        mapsLink.href = btn.dataset.maps || '#';

        modal.hidden = false;
        document.body.style.overflow = 'hidden';

        requestAnimationFrame(function () {
            if (detailMap) detailMap.remove();
            detailMap = L.map('sdca-detail-map').setView([lat, lng], 15);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; OpenStreetMap'
            }).addTo(detailMap);
            detailMarker = L.marker([lat, lng]).addTo(detailMap)
                .bindPopup(btn.dataset.name).openPopup();
            setTimeout(function () { detailMap.invalidateSize(); }, 150);
        });
    }

    document.querySelectorAll('.sdca-view-detail').forEach(function (btn) {
        btn.addEventListener('click', function () { openModal(btn); });
    });
    modal.querySelectorAll('[data-sdca-close]').forEach(function (el) {
        el.addEventListener('click', closeModal);
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && !modal.hidden) closeModal();
    });
})();
</script>
@endpush
