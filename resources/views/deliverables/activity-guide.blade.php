@extends('layouts.admin')

@section('title', 'Activity guide')
@section('heading', 'Deliverables setup guide')

@section('content')
    @php
        $queryParams = $filter->queryParams();
        $summary = $guideSummary ?? [];
    @endphp

    <div style="display:flex;flex-wrap:wrap;gap:0.65rem;align-items:center;margin-bottom:0.85rem;">
        <a href="{{ route($indexRoute, $queryParams) }}" style="text-decoration:none;color:#a63d02;font-weight:700;font-size:0.88rem;">← Back to deliverables table</a>
        <span style="color:#94a3b8;font-size:0.82rem;">{{ $scopeLabel }} · {{ $periodLabel }}</span>
    </div>

    <p style="margin:0 0 0.85rem;font-size:0.86rem;color:#475569;max-width:56rem;line-height:1.5;">
        MIS readiness check for the full fiscal year — not monthly progress. For each indicator: are FY targets set and aligned
        (state → district → staff), is the input/logging module in place, and is achievement auto-counted in the app?
        The <strong>Done</strong> column shows current achievement so you can verify numbers appear correctly.
    </p>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(9rem,1fr));gap:0.5rem;margin-bottom:0.85rem;">
        @foreach ([
            ['label' => 'Indicators', 'val' => $summary['total'] ?? 0, 'bg' => '#f8fafc', 'color' => '#0f172a'],
            ['label' => 'Fully configured', 'val' => $summary['ready'] ?? 0, 'bg' => '#ecfdf5', 'color' => '#15803d'],
            ['label' => 'Target gaps', 'val' => $summary['target_gap'] ?? 0, 'bg' => '#fffbeb', 'color' => '#b45309'],
            ['label' => 'Input not wired', 'val' => $summary['input_missing'] ?? 0, 'bg' => '#fef2f2', 'color' => '#b91c1c'],
            ['label' => 'Not in app', 'val' => $summary['tracking_na'] ?? 0, 'bg' => '#f4f4f5', 'color' => '#52525b'],
        ] as $tile)
            <div style="background:{{ $tile['bg'] }};border:1px solid #e4e4e7;border-radius:10px;padding:0.55rem 0.65rem;">
                <div style="font-size:0.62rem;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;color:#64748b;">{{ $tile['label'] }}</div>
                <div style="font-size:1.35rem;font-weight:800;color:{{ $tile['color'] }};margin-top:0.15rem;">{{ number_format((int) $tile['val']) }}</div>
            </div>
        @endforeach
    </div>

    <form method="get" action="{{ route($guideRoute) }}" style="display:flex;flex-wrap:wrap;gap:0.55rem;align-items:flex-end;margin-bottom:0.85rem;background:#fff;border:1px solid #e4e4e7;border-radius:10px;padding:0.65rem 0.8rem;">
        <div style="display:flex;flex-direction:column;gap:0.2rem;">
            <label for="fiscal_year_id" style="font-size:0.72rem;font-weight:600;color:#475569;">Fiscal year</label>
            <select name="fiscal_year_id" id="fiscal_year_id" style="padding:0.4rem 0.5rem;border:1px solid #d4d4d8;border-radius:8px;min-width:9rem;">
                @foreach ($fiscalYears as $fy)
                    <option value="{{ $fy->id }}" @selected((int) $fiscalYearId === (int) $fy->id)>{{ $fy->name }}</option>
                @endforeach
            </select>
        </div>
        @if ($canPickDistrict)
            <div style="display:flex;flex-direction:column;gap:0.2rem;">
                <label for="district_id" style="font-size:0.72rem;font-weight:600;color:#475569;">District</label>
                <select name="district_id" id="district_id" style="padding:0.4rem 0.5rem;border:1px solid #d4d4d8;border-radius:8px;min-width:10rem;">
                    <option value="">All in scope</option>
                    @foreach ($districts as $d)
                        <option value="{{ $d->id }}" @selected((int) ($filter->districtId ?? 0) === (int) $d->id)>{{ $d->name }}</option>
                    @endforeach
                </select>
            </div>
        @endif
        <div style="display:flex;flex-direction:column;gap:0.2rem;">
            <label for="dag_status" style="font-size:0.72rem;font-weight:600;color:#475569;">Show</label>
            <select name="status" id="dag_status" style="padding:0.4rem 0.5rem;border:1px solid #d4d4d8;border-radius:8px;min-width:10rem;">
                @foreach ([
                    '' => 'All indicators',
                    'ready' => 'Fully configured',
                    'target_gap' => 'Target setup gaps',
                    'input_missing' => 'Input not wired',
                    'tracking_na' => 'Not configured in app',
                ] as $val => $lbl)
                    <option value="{{ $val }}" @selected(request('status') === (string) $val)>{{ $lbl }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" style="background:#d04a02;color:#fff;border:none;padding:0.42rem 0.85rem;border-radius:8px;font-weight:700;cursor:pointer;">Apply</button>
    </form>

    @php
        $statusFilter = request('status');
        $filteredRows = collect($guideRows ?? [])->when($statusFilter, fn ($c) => $c->where('overall_status', $statusFilter))->values();
        $statusStyles = [
            'ready' => ['bg' => '#ecfdf5', 'color' => '#15803d'],
            'target_gap' => ['bg' => '#fffbeb', 'color' => '#b45309'],
            'input_missing' => ['bg' => '#fef2f2', 'color' => '#b91c1c'],
            'tracking_na' => ['bg' => '#f4f4f5', 'color' => '#52525b'],
        ];
        $currentPillar = null;
    @endphp

    <div style="overflow-x:auto;">
        <table style="width:100%;border-collapse:collapse;font-size:0.82rem;background:#fff;">
            <thead>
                <tr>
                    <th style="padding:0.5rem;border:1px solid #1c1917;background:#9a3412;color:#fff;text-align:center;min-width:3rem;">S.N.</th>
                    <th style="padding:0.5rem 0.6rem;border:1px solid #1c1917;background:#9a3412;color:#fff;text-align:left;min-width:13rem;">Indicator</th>
                    <th style="padding:0.5rem;border:1px solid #1c1917;background:#9a3412;color:#fff;text-align:left;min-width:10rem;">Log in module</th>
                    <th style="padding:0.5rem;border:1px solid #1c1917;background:#9a3412;color:#fff;text-align:left;min-width:9rem;">Target cascade</th>
                    <th style="padding:0.5rem;border:1px solid #1c1917;background:#9a3412;color:#fff;text-align:center;min-width:5rem;">Input</th>
                    <th style="padding:0.5rem;border:1px solid #1c1917;background:#9a3412;color:#fff;text-align:center;min-width:5.5rem;">Tracking</th>
                    <th style="padding:0.5rem;border:1px solid #1c1917;background:#9a3412;color:#fff;text-align:center;">Done</th>
                    <th style="padding:0.5rem;border:1px solid #1c1917;background:#9a3412;color:#fff;text-align:center;min-width:8rem;">Setup status</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($filteredRows as $row)
                    @if ($currentPillar !== $row['pillar'])
                        @php $currentPillar = $row['pillar']; @endphp
                        <tr style="background:#ffedd5;font-weight:700;">
                            <td colspan="8" style="padding:0.45rem 0.6rem;border:1px solid #d4d4d8;">{{ $currentPillar }}</td>
                        </tr>
                    @endif
                    @php $st = $statusStyles[$row['overall_status']] ?? ['bg' => '#fff', 'color' => '#334155']; @endphp
                    <tr>
                        <td style="padding:0.42rem;border:1px solid #e4e4e7;text-align:center;font-weight:700;color:#a63d02;">{{ $row['serial'] }}</td>
                        <td style="padding:0.42rem 0.55rem;border:1px solid #e4e4e7;">
                            <div style="font-weight:700;">{{ $row['name'] }}</div>
                            <div style="font-size:0.68rem;color:#64748b;margin-top:0.15rem;">{{ $row['level'] }} · {{ $row['indicator_type'] ?: '—' }}</div>
                        </td>
                        <td style="padding:0.42rem 0.55rem;border:1px solid #e4e4e7;">
                            @if (!empty($row['logging_route']))
                                <a href="{{ route($row['logging_route']) }}" style="color:#a63d02;font-weight:700;text-decoration:none;">{{ $row['logging_module'] }}</a>
                            @else
                                <span style="font-weight:600;">{{ $row['logging_module'] }}</span>
                            @endif
                            @if (!empty($row['logging_note']))
                                <div style="font-size:0.66rem;color:#64748b;margin-top:0.2rem;line-height:1.35;">{{ $row['logging_note'] }}</div>
                            @endif
                        </td>
                        <td style="padding:0.42rem 0.55rem;border:1px solid #e4e4e7;font-size:0.72rem;line-height:1.45;">
                            <div>{{ $row['target_state_label'] }}</div>
                            <div>{{ $row['target_district_label'] }}</div>
                            <div>{{ $row['target_staff_label'] }}</div>
                            @if (!empty($row['target_note']))
                                <div style="color:#64748b;margin-top:0.2rem;">{{ $row['target_note'] }}</div>
                            @endif
                        </td>
                        <td style="padding:0.42rem;border:1px solid #e4e4e7;text-align:center;font-size:0.72rem;font-weight:600;">{{ $row['input_status_label'] }}</td>
                        <td style="padding:0.42rem;border:1px solid #e4e4e7;text-align:center;font-size:0.72rem;font-weight:600;">{{ $row['achievement_status_label'] }}</td>
                        <td style="padding:0.42rem;border:1px solid #e4e4e7;text-align:center;">
                            @if ($row['drilldown'])
                                <button type="button" class="dlv-ach-btn" data-dlv-breakdown data-serial="{{ $row['serial'] }}" data-name="{{ $row['name'] }}" title="View breakdown">{{ number_format((int) $row['achievement']) }}</button>
                            @else
                                <span class="dlv-ach-static">{{ number_format((int) $row['achievement']) }}</span>
                            @endif
                        </td>
                        <td style="padding:0.42rem;border:1px solid #e4e4e7;text-align:center;">
                            <span style="display:inline-block;padding:0.18rem 0.45rem;border-radius:999px;font-size:0.66rem;font-weight:700;background:{{ $st['bg'] }};color:{{ $st['color'] }};">{{ $row['overall_status_label'] }}</span>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" style="padding:1.25rem;text-align:center;color:#94a3b8;">No indicators match this filter.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @include('deliverables.partials.breakdown-drawer')
@endsection
