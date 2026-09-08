@extends('layouts.admin')

@section('title', 'Market linkage partners')
@section('heading', 'Market linkage partners')

@section('content')
    @php
        $query = request()->except('page');
    @endphp

    @push('styles')
    <style>
        .ml-shell { display: flex; flex-direction: column; gap: 1rem; }
        .ml-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 0.65rem; }
        .ml-stat {
            background: linear-gradient(180deg, #fff 0%, #f8fafc 100%);
            border: 1px solid #e2e8f0; border-radius: 14px; padding: 0.9rem 1rem; position: relative; overflow: hidden;
        }
        .ml-stat::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px; background: var(--ml-accent, #4f46e5); }
        .ml-stat--partners { --ml-accent: #4f46e5; }
        .ml-stat--incubatees { --ml-accent: #059669; }
        .ml-stat--records { --ml-accent: #0ea5e9; }
        .ml-stat--p1 { --ml-accent: #ca8a04; }
        .ml-stat--p2 { --ml-accent: #db2777; }
        .ml-stat--p3 { --ml-accent: #7c3aed; }
        .ml-stat__label { font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b; }
        .ml-stat__value { margin-top: 0.35rem; font-size: 1.45rem; font-weight: 800; color: #0f172a; font-variant-numeric: tabular-nums; }
        .ml-stat__hint { margin-top: 0.2rem; font-size: 0.74rem; color: #94a3b8; }
        .ml-toolbar { display: flex; flex-wrap: wrap; gap: 0.5rem; align-items: center; justify-content: space-between; }
        .ml-btn { text-decoration: none; display: inline-flex; align-items: center; gap: 0.35rem; padding: 0.48rem 0.95rem; border-radius: 8px; font-size: 0.85rem; font-weight: 700; border: none; cursor: pointer; font-family: inherit; }
        .ml-btn--export { background: #065f46; color: #fff; }
        .ml-btn--ghost { background: #fff; color: #334155; border: 1px solid #cbd5e1; }
        .ml-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 0.9rem 1rem; }
        .ml-filters { display: grid; grid-template-columns: minmax(14rem, 2fr) repeat(auto-fit, minmax(140px, 1fr)) auto; gap: 0.55rem; align-items: end; }
        .ml-filters label { display: block; font-size: 0.78rem; font-weight: 700; margin-bottom: 0.2rem; color: #334155; }
        .ml-filters input, .ml-filters select { width: 100%; padding: 0.45rem 0.55rem; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 0.88rem; }
        .ml-table-wrap { background: #fff; border: 1px solid #e2e8f0; border-radius: 14px; overflow: auto; }
        .ml-table { width: 100%; border-collapse: collapse; font-size: 0.84rem; }
        .ml-table thead tr { background: #f8fafc; }
        .ml-table th, .ml-table td { padding: 0.65rem 0.75rem; border-bottom: 1px solid #e2e8f0; vertical-align: top; text-align: left; }
        .ml-table tbody tr:hover { background: #fafafa; }
        .ml-name { font-weight: 800; color: #312e81; text-decoration: none; }
        .ml-name:hover { text-decoration: underline; }
        .ml-meta { color: #64748b; font-size: 0.76rem; margin-top: 0.15rem; }
        .ml-badge { display: inline-flex; padding: 0.12rem 0.45rem; border-radius: 999px; font-size: 0.72rem; font-weight: 700; background: #eef2ff; color: #3730a3; }
        .ml-badge--p1 { background: #fef3c7; color: #92400e; }
        .ml-badge--p2 { background: #fce7f3; color: #9d174d; }
        .ml-badge--p3 { background: #ede9fe; color: #5b21b6; }
        .ml-empty { padding: 1.4rem; text-align: center; color: #64748b; }
        .ml-pager { padding: 0.75rem; }
    </style>
    @endpush

    <div class="ml-shell">
        <div class="ml-stats">
            <div class="ml-stat ml-stat--partners">
                <div class="ml-stat__label">Partners</div>
                <div class="ml-stat__value">{{ number_format((int) $stats['partners']) }}</div>
                <div class="ml-stat__hint">Unique names (merged variants)</div>
            </div>
            <div class="ml-stat ml-stat--incubatees">
                <div class="ml-stat__label">Incubatees</div>
                <div class="ml-stat__value">{{ number_format((int) $stats['incubatees']) }}</div>
                <div class="ml-stat__hint">Linked across selected filters</div>
            </div>
            <div class="ml-stat ml-stat--records">
                <div class="ml-stat__label">Linkages</div>
                <div class="ml-stat__value">{{ number_format((int) $stats['links']) }}</div>
                <div class="ml-stat__hint">All partner–incubatee rows</div>
            </div>
            <div class="ml-stat ml-stat--p1">
                <div class="ml-stat__label">Phase 1</div>
                <div class="ml-stat__value">{{ number_format((int) $stats['phase1']) }}</div>
            </div>
            <div class="ml-stat ml-stat--p2">
                <div class="ml-stat__label">Phase 2</div>
                <div class="ml-stat__value">{{ number_format((int) $stats['phase2']) }}</div>
            </div>
            <div class="ml-stat ml-stat--p3">
                <div class="ml-stat__label">Phase 3</div>
                <div class="ml-stat__value">{{ number_format((int) $stats['phase3']) }}</div>
            </div>
        </div>

        <div class="ml-toolbar">
            <p style="margin:0;font-size:0.82rem;color:#64748b;max-width:42rem;">
                Search any partner or incubatee. Click a partner to see every linked incubatee from Phase 1, Phase 2 and Phase 3.
            </p>
            <div style="display:flex;gap:0.5rem;flex-wrap:wrap;">
                <a href="{{ route($routes['dashboard']) }}" class="ml-btn ml-btn--ghost">Incubatee dashboard</a>
                <a href="{{ route($routes['export'], $query) }}" class="ml-btn ml-btn--export">⬇ Export Excel</a>
            </div>
        </div>

        <div class="ml-card">
            <form method="get" action="{{ route($routes['index']) }}" class="ml-filters">
                <div>
                    <label for="q">Find partner or incubatee</label>
                    <input id="q" type="search" name="q" value="{{ $filters['q'] }}" placeholder="Amazon, incubatee name, application no…">
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
                    <button type="submit" class="ml-btn ml-btn--export" style="background:#4f46e5;">Search</button>
                    <button type="submit" class="ml-btn ml-btn--export" formaction="{{ route($routes['export']) }}">⬇ Export Excel</button>
                    <a href="{{ route($routes['index']) }}" class="ml-btn ml-btn--ghost">Clear filters</a>
                </div>
            </form>
        </div>

        <div class="ml-table-wrap">
            <table class="ml-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Partner</th>
                        <th>Incubatees</th>
                        <th>Linkages</th>
                        <th>Online / Offline</th>
                        <th>Years</th>
                        <th>Phase</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($partners as $i => $row)
                        <tr>
                            <td>{{ $partners->firstItem() + $i }}</td>
                            <td>
                                <a class="ml-name" href="{{ route($routes['show'], array_merge($query, ['partner' => $row['key_param']])) }}">{{ $row['name'] }}</a>
                                @if ($row['districts'] !== [])
                                    <div class="ml-meta">{{ implode(', ', array_slice($row['districts'], 0, 4)) }}{{ count($row['districts']) > 4 ? ' +'.(count($row['districts']) - 4) : '' }}</div>
                                @endif
                            </td>
                            <td><strong>{{ number_format((int) $row['incubatee_count']) }}</strong></td>
                            <td>{{ number_format((int) $row['link_count']) }}</td>
                            <td>{{ (int) $row['online'] }} / {{ (int) $row['offline'] }}</td>
                            <td>{{ $row['years'] !== [] ? implode(', ', $row['years']) : '—' }}</td>
                            <td>
                                @foreach ($row['phases'] as $phaseLabel)
                                    @php $cls = str_contains($phaseLabel, '1') ? 'ml-badge--p1' : (str_contains($phaseLabel, '2') ? 'ml-badge--p2' : 'ml-badge--p3'); @endphp
                                    <span class="ml-badge {{ $cls }}">{{ $phaseLabel }}</span>
                                @endforeach
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="ml-empty">No partners match these filters.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
            @if ($partners->hasPages())
                <div class="ml-pager">{{ $partners->links() }}</div>
            @endif
        </div>
    </div>
@endsection
