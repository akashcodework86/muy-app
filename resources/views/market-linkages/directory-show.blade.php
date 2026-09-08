@extends('layouts.admin')

@section('title', $partner['name'].' — linked incubatees')
@section('heading', $partner['name'])

@section('content')
    @php
        $query = request()->except(['page', 'partner']);
    @endphp

    @push('styles')
    <style>
        .ml-shell { display: flex; flex-direction: column; gap: 1rem; }
        .ml-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 0.65rem; }
        .ml-stat {
            background: #fff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 0.85rem 1rem;
        }
        .ml-stat__label { font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b; }
        .ml-stat__value { margin-top: 0.3rem; font-size: 1.4rem; font-weight: 800; color: #0f172a; }
        .ml-toolbar { display: flex; flex-wrap: wrap; gap: 0.5rem; align-items: center; justify-content: space-between; }
        .ml-btn { text-decoration: none; display: inline-flex; align-items: center; gap: 0.35rem; padding: 0.48rem 0.95rem; border-radius: 8px; font-size: 0.85rem; font-weight: 700; }
        .ml-btn--ghost { background: #fff; color: #334155; border: 1px solid #cbd5e1; }
        .ml-btn--export { background: #065f46; color: #fff; }
        .ml-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 0.9rem 1rem; }
        .ml-filters { display: grid; grid-template-columns: minmax(12rem, 1.6fr) repeat(auto-fit, minmax(130px, 1fr)) auto; gap: 0.55rem; align-items: end; }
        .ml-filters label { display: block; font-size: 0.78rem; font-weight: 700; margin-bottom: 0.2rem; color: #334155; }
        .ml-filters input, .ml-filters select { width: 100%; padding: 0.45rem 0.55rem; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 0.88rem; }
        .ml-table-wrap { background: #fff; border: 1px solid #e2e8f0; border-radius: 14px; overflow: auto; }
        .ml-table { width: 100%; border-collapse: collapse; font-size: 0.84rem; }
        .ml-table thead tr { background: #f8fafc; }
        .ml-table th, .ml-table td { padding: 0.6rem 0.75rem; border-bottom: 1px solid #e2e8f0; vertical-align: top; text-align: left; }
        .ml-table tbody tr:hover { background: #fafafa; }
        .ml-badge { display: inline-flex; padding: 0.12rem 0.45rem; border-radius: 999px; font-size: 0.72rem; font-weight: 700; }
        .ml-badge--p1 { background: #fef3c7; color: #92400e; }
        .ml-badge--p2 { background: #fce7f3; color: #9d174d; }
        .ml-badge--p3 { background: #ede9fe; color: #5b21b6; }
        .ml-mode { font-size: 0.76rem; font-weight: 700; padding: 0.12rem 0.4rem; border-radius: 6px; background: #eef2ff; color: #3730a3; }
        .ml-mode--offline { background: #fff7ed; color: #9a3412; }
        .ml-onboard { font-size: 0.76rem; font-weight: 700; padding: 0.12rem 0.45rem; border-radius: 999px; }
        .ml-onboard--yes { background: #ecfdf5; color: #166534; border: 1px solid #bbf7d0; }
        .ml-onboard--no { background: #fff7ed; color: #9a3412; border: 1px solid #fed7aa; }
        .ml-link { color: #4f46e5; font-weight: 700; text-decoration: none; word-break: break-all; }
        .ml-link:hover { text-decoration: underline; }
        .ml-empty { padding: 1.4rem; text-align: center; color: #64748b; }
    </style>
    @endpush

    <div class="ml-shell">
        <div class="ml-toolbar">
            <p style="margin:0;font-size:0.86rem;color:#475569;">
                Every incubatee linked to this partner across Phase 1, Phase 2 and Phase 3.
            </p>
            <div style="display:flex;gap:0.5rem;flex-wrap:wrap;">
                <a href="{{ route($routes['index'], $query) }}" class="ml-btn ml-btn--ghost">← All partners</a>
                <a href="{{ route($routes['export'], array_merge($query, ['partner' => $partner['key_param']])) }}" class="ml-btn ml-btn--export">⬇ Export Excel</a>
            </div>
        </div>

        <div class="ml-stats">
            <div class="ml-stat">
                <div class="ml-stat__label">Incubatees</div>
                <div class="ml-stat__value">{{ number_format((int) $partner['incubatee_count']) }}</div>
            </div>
            <div class="ml-stat">
                <div class="ml-stat__label">Linkages</div>
                <div class="ml-stat__value">{{ number_format((int) $partner['link_count']) }}</div>
            </div>
            <div class="ml-stat">
                <div class="ml-stat__label">Online / Offline</div>
                <div class="ml-stat__value">{{ (int) $partner['online'] }} / {{ (int) $partner['offline'] }}</div>
            </div>
            <div class="ml-stat">
                <div class="ml-stat__label">Years</div>
                <div class="ml-stat__value" style="font-size:1rem;line-height:1.35;">{{ $partner['years'] !== [] ? implode(', ', $partner['years']) : '—' }}</div>
            </div>
        </div>

        <div class="ml-card">
            <form method="get" action="{{ route($routes['show'], $partner['key_param']) }}" class="ml-filters">
                <input type="hidden" name="partner" value="{{ $partner['key_param'] }}">
                <div>
                    <label for="q">Find incubatee</label>
                    <input id="q" type="search" name="q" value="{{ $filters['q'] }}" placeholder="Name, application no, district…">
                </div>
                <div>
                    <label for="fy">Year</label>
                    <select id="fy" name="fy">
                        <option value="">All years</option>
                        @foreach ($years as $fy)
                            <option value="{{ $fy }}" @selected($filters['fy'] === $fy)>{{ $fy }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="phase">Phase</label>
                    <select id="phase" name="phase">
                        <option value="">All phases</option>
                        <option value="1" @selected($filters['phase'] === '1')>Phase 1</option>
                        <option value="2" @selected($filters['phase'] === '2')>Phase 2</option>
                        <option value="3" @selected($filters['phase'] === '3')>Phase 3</option>
                    </select>
                </div>
                <div>
                    <label for="mode">Mode</label>
                    <select id="mode" name="mode">
                        <option value="">All</option>
                        <option value="online" @selected($filters['mode'] === 'online')>Online</option>
                        <option value="offline" @selected($filters['mode'] === 'offline')>Offline</option>
                    </select>
                </div>
                <div>
                    <label for="onboard">Onboarding</label>
                    <select id="onboard" name="onboard">
                        <option value="">All</option>
                        <option value="onboarded" @selected(($filters['onboard'] ?? '') === 'onboarded')>Onboarded</option>
                        <option value="not_onboarded" @selected(($filters['onboard'] ?? '') === 'not_onboarded')>Not onboarded</option>
                    </select>
                </div>
                @if ($showDistrictScope && $districts->isNotEmpty())
                    <div>
                        <label for="district_id">District</label>
                        <select id="district_id" name="district_id">
                            <option value="">All districts</option>
                            @foreach ($districts as $district)
                                <option value="{{ $district->id }}" @selected((int) $filters['district_id'] === (int) $district->id)>{{ $district->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
                <div style="display:flex;gap:0.4rem;flex-wrap:wrap;align-items:center;">
                    <button type="submit" class="ml-btn ml-btn--export" style="background:#4f46e5;">Filter</button>
                    <button type="submit" class="ml-btn ml-btn--export" formaction="{{ route($routes['export']) }}">⬇ Export Excel</button>
                    <a href="{{ route($routes['show'], $partner['key_param']) }}" class="ml-btn ml-btn--ghost">Clear filters</a>
                </div>
            </form>
        </div>

        <div class="ml-table-wrap">
            <table class="ml-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Incubatee</th>
                        <th>Application no</th>
                        <th>District</th>
                        <th>Phone</th>
                        <th>Onboarding</th>
                        <th>Mode</th>
                        <th>Date</th>
                        <th>FY</th>
                        <th>Phase</th>
                        <th>Link</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($links as $i => $link)
                        <tr>
                            <td>{{ $i + 1 }}</td>
                            <td>
                                <strong>{{ $link['incubatee_name'] }}</strong>
                                @if ($link['block'] !== '')
                                    <div style="font-size:0.76rem;color:#64748b;">{{ $link['block'] }}</div>
                                @endif
                            </td>
                            <td>{{ $link['application_no'] !== '' ? $link['application_no'] : '—' }}</td>
                            <td>{{ $link['district'] !== '' ? $link['district'] : '—' }}</td>
                            <td>{{ $link['phone'] !== '' ? $link['phone'] : '—' }}</td>
                            <td>
                                @if (($link['onboard_status'] ?? '') === 'Onboarded')
                                    <span class="ml-onboard ml-onboard--yes">Onboarded</span>
                                @else
                                    <span class="ml-onboard ml-onboard--no">Not onboarded</span>
                                @endif
                            </td>
                            <td>
                                @if ($link['mode'] === 'offline')
                                    <span class="ml-mode ml-mode--offline">Offline</span>
                                @elseif ($link['mode'] === 'online')
                                    <span class="ml-mode">Online</span>
                                @else
                                    —
                                @endif
                            </td>
                            <td>{{ $link['date'] !== '' ? \Illuminate\Support\Carbon::parse($link['date'])->format('d M Y') : '—' }}</td>
                            <td>{{ $link['fy'] !== '' ? $link['fy'] : '—' }}</td>
                            <td>
                                @php $cls = ((int) $link['phase'] === 1) ? 'ml-badge--p1' : (((int) $link['phase'] === 2) ? 'ml-badge--p2' : 'ml-badge--p3'); @endphp
                                <span class="ml-badge {{ $cls }}">{{ $link['phase_label'] }}</span>
                            </td>
                            <td>
                                @if ($link['link_href'])
                                    <a class="ml-link" href="{{ $link['link_href'] }}" target="_blank" rel="noopener">{{ $link['link_url'] }}</a>
                                @elseif ($link['link_url'] !== '')
                                    {{ $link['link_url'] }}
                                @else
                                    —
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="11" class="ml-empty">No incubatees for this partner with the current filters.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
