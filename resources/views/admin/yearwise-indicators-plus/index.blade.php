@extends('layouts.admin')

@section('title', 'Year-wise indicators')
@section('heading', 'Year-wise indicators')

@push('styles')
<style>
.yi-page{max-width:none;width:100%;margin:0;padding:0 0 2.5rem;box-sizing:border-box}
.yi-flash{display:flex;align-items:center;gap:.5rem;background:#ecfdf5;border:1px solid #a7f3d0;color:#047857;border-radius:.75rem;padding:.7rem 1rem;margin-bottom:1rem;font-size:.9rem;font-weight:600}
.yi-card{background:#fff;border:1px solid #e2e8f0;border-radius:1rem;box-shadow:0 1px 3px rgba(0,0,0,.05);overflow:hidden}
.yi-card__head{display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap;padding:1.1rem 1.35rem;border-bottom:1px solid #e2e8f0;background:#f8fafc}
.yi-card__title{font-size:1.15rem;font-weight:800;color:#1e293b}
.yi-actions{display:flex;gap:.45rem;flex-wrap:wrap}
.yi-btn{display:inline-flex;align-items:center;gap:.35rem;padding:.5rem .85rem;border-radius:.5rem;border:1px solid #d4d4d8;background:#fff;color:#334155;font-size:.85rem;font-weight:700;text-decoration:none;cursor:pointer}
.yi-btn:hover{background:#f8fafc}
.yi-btn--primary{background:#059669;border-color:#059669;color:#fff}
.yi-btn--primary:hover{background:#047857;color:#fff}
.yi-btn--muted{background:#f1f5f9;color:#475569}
.yi-body{padding:1.15rem 1.35rem}
.yi-filters{border:1px dashed #cbd5e1;border-radius:.75rem;padding:1rem 1.15rem;margin-bottom:1.15rem;background:#fafafa}
.yi-filters__title{font-size:.75rem;font-weight:700;color:#059669;text-transform:uppercase;letter-spacing:.04em;margin-bottom:.65rem}
.yi-filters__grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:.75rem;align-items:end}
.yi-filters__field label{display:block;font-size:.75rem;font-weight:600;color:#475569;margin-bottom:.3rem}
.yi-filters__field select{width:100%;padding:.5rem .6rem;border:1px solid #d4d4d8;border-radius:.5rem;font-size:.9rem;background:#fff}
.yi-filters__actions{display:flex;gap:.4rem;flex-wrap:wrap}
.yi-badge{display:inline-flex;background:#ecfdf5;color:#047857;border:1px solid #a7f3d0;border-radius:999px;padding:.15rem .55rem;font-size:.72rem;font-weight:700}
.yi-table-wrap{overflow:auto;width:100%;border-radius:.6rem;border:1px solid #c2703f}
.yi-deck{margin-top:2.25rem}
.yi-deck__title{font-size:1.15rem;font-weight:800;color:#1e293b;margin:0 0 1rem}
.yi-deck-table-wrap{overflow:auto;margin-bottom:1.75rem;border-radius:.6rem;border:1px solid #c2703f}
.yi-deck-table{width:100%;min-width:1100px;border-collapse:collapse;font-size:.95rem;background:#fff}
.yi-deck-table caption{text-align:left;font-size:1rem;font-weight:800;color:#c2410c;padding:.65rem .25rem;caption-side:top}
.yi-deck-table th{background:#c2410c;color:#fff;text-align:center;padding:.7rem .55rem;font-size:.88rem;font-weight:700;border:1px solid #7c2d12;vertical-align:middle}
.yi-deck-table th:first-child{text-align:left;padding-left:.9rem;min-width:160px;position:sticky;left:0;z-index:2;background:#c2410c}
.yi-deck-table th.is-ext .yi-ext-tag{display:block;font-size:.68rem;font-weight:700;color:#ffedd5;text-transform:none;margin-top:.12rem}
.yi-deck-table th.is-phase{background:#9a3412;border-bottom:2px solid #7c2d12;letter-spacing:.02em;font-size:.9rem;padding:.75rem .55rem}
.yi-deck-table th.is-phase-total{background:#7c2d12;color:#ffedd5;font-size:.82rem;min-width:110px}
.yi-deck-table th.is-till{background:#9a3412;color:#ffedd5;font-size:.82rem;min-width:120px;border:2px solid #7c2d12}
.yi-deck-table th.is-grand-h{background:#7c2d12}
.yi-deck-table td{padding:.65rem .55rem;text-align:center;border:1px solid #d69b78;color:#1e293b;font-variant-numeric:tabular-nums;font-size:.95rem;vertical-align:middle}
.yi-deck-table td:first-child{text-align:left;font-weight:700;color:#1e293b;padding-left:.9rem;position:sticky;left:0;z-index:1;background:#fff}
.yi-deck-table tbody tr:nth-child(even) td:first-child{background:#fff7ed}
.yi-deck-table tbody tr:nth-child(even){background:#fff7ed}
.yi-deck-table tr.is-other td{color:#94a3b8;font-style:italic}
.yi-deck-table tr.is-grand td{font-weight:800;background:#fde3cf;border-top:2px solid #7c2d12}
.yi-deck-table tr.is-grand td:first-child{background:#fde3cf}
.yi-deck-table td.is-total{font-weight:800;background:#fff1e6;border-left:2px solid #c2703f;font-size:1rem}
.yi-deck-table td.is-till{font-weight:800;background:#fdece0;color:#9a3412;border-left:2px solid #c2703f;border-right:2px solid #c2703f;font-size:1rem}
.yi-deck-table tr.is-grand td.is-till{background:#f6c9a3}
.yi-deck-table td.is-jit-cell{font-weight:700}

/* Phase highlight outline only (same orange palette) */
.yi-deck-table th.is-phase{border-left:3px solid #431407;border-right:3px solid #431407}
.yi-deck-table th.ph-start{border-left:3px solid #431407}
.yi-deck-table th.is-phase-total{border-right:3px solid #431407}
.yi-deck-table td.ph-start{border-left:3px solid #c2410c}
.yi-deck-table td.is-phase-total{font-weight:800;background:#fdece0;color:#9a3412;border-left:2px solid #c2703f;border-right:3px solid #c2410c;font-size:1rem}
.yi-deck-table tbody tr:nth-child(even) td.is-phase-total{background:#f6c9a3}
.yi-deck-table tr.is-grand td.is-phase-total{background:#f6c9a3}

.yi-cell-calc{display:block;margin-top:.35rem;font-size:.72rem;line-height:1.45;font-weight:600;color:#9a3412;text-align:left}
.yi-cell-calc__line{display:flex;justify-content:space-between;gap:.55rem}
.yi-cell-calc__line.is-sum{margin-top:.2rem;padding-top:.2rem;border-top:1px solid #fdba74;font-weight:800}
.yi-deck-table a.yi-count-link{color:inherit;text-decoration:underline;text-underline-offset:2px;font-weight:inherit}
.yi-deck-table a.yi-count-link:hover{color:#9a3412}
</style>
@endpush

@section('content')
@php
    $yiFy = $yi_fy ?? null;
    $yiDistrictId = $yi_district_id ?? null;
    $yearwise = $yearwise ?? ['rows' => [], 'totals' => [], 'years' => [], 'note' => '', 'generated_at' => '', 'extras' => []];
    $yiQuery = array_filter([
        'yi_fy' => $yiFy,
        'yi_district_id' => $yiDistrictId ?: null,
    ], fn ($v) => $v !== null && $v !== '');
    $yiRows = $yearwise['rows'] ?? [];
    $yiTotals = $yearwise['totals'] ?? [];
    $yiYears = array_values(array_map(fn ($r) => (string) $r['year'], $yiRows));
    $yiMetrics = [
        ['key' => 'cfa', 'label' => 'CFA'],
        ['key' => 'onboarding', 'label' => 'Onboarding', 'jit_ld_fy' => '2023-24'],
        ['key' => 'udyam', 'label' => 'Udyam registration'],
        ['key' => 'artisan_card', 'label' => 'Artisan card'],
        ['key' => 'fssai', 'label' => 'FSSAI'],
        ['key' => 'gst', 'label' => 'GST'],
        ['key' => 'market_linkage', 'label' => 'Market linkage'],
        ['key' => 'convergence', 'label' => 'Convergence'],
    ];
    $yiByYear = [];
    foreach ($yiRows as $yiRow) {
        $yiByYear[(string) $yiRow['year']] = $yiRow;
    }
    $yiPhaseDefs = [
        ['key' => 'pilot', 'label' => 'Pilot Phase (2021-22 to 2022-23)', 'years' => ['2021-22', '2022-23']],
        ['key' => 'phase1', 'label' => 'Phase 1 (2023-24)', 'years' => ['2023-24']],
        ['key' => 'extension', 'label' => 'Extension period (2024-25)', 'years' => ['2024-25']],
        ['key' => 'phase2', 'label' => 'Current Phase 2 (2025-26 + 2026-27)', 'years' => ['2025-26', '2026-27']],
    ];
    $yiPhaseGroups = [];
    foreach ($yiPhaseDefs as $group) {
        $matchYears = array_values(array_intersect($group['years'], $yiYears));
        if ($matchYears !== []) {
            $group['years'] = $matchYears;
            $yiPhaseGroups[] = $group;
        }
    }
    $yiRecordsDistrict = is_string($yearwise['district_filter'] ?? null) ? (string) $yearwise['district_filter'] : null;
    $yiCountUrl = static function (
        string $metric,
        string $scope,
        ?string $year = null,
        ?string $phase = null,
        ?string $district = null,
    ): string {
        $params = array_filter([
            'metric' => $metric,
            'scope' => $scope,
            'year' => $year,
            'phase' => $phase,
            'district' => ($district !== null && $district !== '' && $district !== 'Grand Total' && $district !== 'Other / Unmapped')
                ? $district
                : null,
        ], static fn ($v) => $v !== null && $v !== '');

        return route('admin.yearwise-indicators-plus.records', $params);
    };
@endphp
<div class="yi-page">
    @if (session('flash_success'))
        <div class="yi-flash">{{ session('flash_success') }}</div>
    @endif

    <div class="yi-card">
        <div class="yi-card__head">
            <div>
                <div class="yi-card__title">Year-wise indicators</div>
            </div>
            <div class="yi-actions">
                <form method="POST" action="{{ route('admin.yearwise-indicators-plus.refresh') }}" style="display:inline;">
                    @csrf
                    @foreach ($yiQuery as $qk => $qv)
                        <input type="hidden" name="{{ $qk }}" value="{{ $qv }}">
                    @endforeach
                    <button type="submit" class="yi-btn">Refresh</button>
                </form>
                <a href="{{ route('admin.yearwise-indicators-plus.export', $yiQuery) }}" class="yi-btn yi-btn--primary">Export CSV</a>
            </div>
        </div>

        <div class="yi-body">
            <form method="get" action="{{ route('admin.yearwise-indicators-plus.index') }}" class="yi-filters">
                <div class="yi-filters__title">Filters — Year-wise indicators</div>
                <div class="yi-filters__grid">
                    <div class="yi-filters__field">
                        <label for="yi_district_id">District</label>
                        <select name="yi_district_id" id="yi_district_id">
                            <option value="">All districts</option>
                            @foreach ($districts as $d)
                                <option value="{{ $d->id }}" @selected((int) $yiDistrictId === (int) $d->id)>{{ $d->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="yi-filters__field">
                        <label for="yi_fy">Financial year</label>
                        <select name="yi_fy" id="yi_fy">
                            <option value="">All years (2021–22 to 2026–27)</option>
                            @foreach ($yearwise['years'] ?? [] as $fyOpt)
                                <option value="{{ $fyOpt }}" @selected($yiFy === $fyOpt)>{{ $fyOpt }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="yi-filters__actions">
                        <button type="submit" class="yi-btn yi-btn--primary">Apply</button>
                        <a href="{{ route('admin.yearwise-indicators-plus.index') }}" class="yi-btn yi-btn--muted">Reset</a>
                    </div>
                </div>
            </form>

            <div class="yi-table-wrap">
                <table class="yi-deck-table">
                    <thead>
                        <tr>
                            <th rowspan="2">Indicator</th>
                            @foreach ($yiPhaseGroups as $group)
                                <th colspan="{{ count($group['years']) + 1 }}" class="is-phase ph-{{ $group['key'] }}">
                                    {{ $group['label'] }}
                                </th>
                                @if ($group['key'] === 'phase1')
                                    <th rowspan="2" class="is-till">Till Phase Total<br><span style="font-size:.68rem;font-weight:600;opacity:.9;">(Pilot + Phase 1)</span></th>
                                @endif
                            @endforeach
                            <th rowspan="2" class="is-grand-h">Grand Total</th>
                        </tr>
                        <tr>
                            @foreach ($yiPhaseGroups as $group)
                                @foreach ($group['years'] as $yi => $fyCol)
                                    <th class="ph-{{ $group['key'] }}{{ $yi === 0 ? ' ph-start' : '' }}">
                                        {{ $fyCol }}
                                        @if ($fyCol === '2024-25')
                                            <span class="yi-ext-tag">Ext.</span>
                                        @endif
                                    </th>
                                @endforeach
                                <th class="is-phase-total ph-{{ $group['key'] }}">Phase Total</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @if ($yiYears === [])
                            <tr>
                                <td colspan="2" style="text-align:center;color:#64748b;">No data for the selected filters.</td>
                            </tr>
                        @else
                            @foreach ($yiMetrics as $metric)
                                @php $runningTill = 0; @endphp
                                <tr>
                                    <td>{{ $metric['label'] }}</td>
                                    @foreach ($yiPhaseGroups as $group)
                                        @php $phaseSum = 0; @endphp
                                        @foreach ($group['years'] as $yi => $fyCol)
                                            @php
                                                $cellVal = (int) ($yiByYear[$fyCol][$metric['key']] ?? 0);
                                                $phaseSum += $cellVal;
                                            @endphp
                                            <td class="ph-{{ $group['key'] }}{{ $yi === 0 ? ' ph-start' : '' }}">
                                                <a class="yi-count-link" href="{{ $yiCountUrl($metric['key'], 'year', $fyCol, null, $yiRecordsDistrict) }}">{{ number_format($cellVal) }}</a>
                                            </td>
                                        @endforeach
                                        @php $runningTill += $phaseSum; @endphp
                                        <td class="is-phase-total ph-{{ $group['key'] }}">
                                            <a class="yi-count-link" href="{{ $yiCountUrl($metric['key'], 'phase', null, $group['key'], $yiRecordsDistrict) }}">{{ number_format($phaseSum) }}</a>
                                            @if (($metric['key'] ?? '') === 'onboarding' && ($group['key'] ?? '') === 'phase1' && $onboardingBreakdown)
                                                <div class="yi-cell-calc">
                                                    <div class="yi-cell-calc__line"><span>Verified</span><span>{{ number_format($onboardingBreakdown['onboarded_proper']) }}</span></div>
                                                    <div class="yi-cell-calc__line"><span>+ JIT</span><span>{{ number_format($onboardingBreakdown['jit_rows']) }}</span></div>
                                                    <div class="yi-cell-calc__line"><span>+ Lakhpati Didi</span><span>{{ number_format($onboardingBreakdown['ld_rows']) }}</span></div>
                                                    <div class="yi-cell-calc__line is-sum"><span>= Phase Total</span><span>{{ number_format($onboardingBreakdown['total']) }}</span></div>
                                                </div>
                                            @endif
                                        </td>
                                        @if ($group['key'] === 'phase1')
                                            <td class="is-till">
                                                <a class="yi-count-link" href="{{ $yiCountUrl($metric['key'], 'till', null, null, $yiRecordsDistrict) }}">{{ number_format($runningTill) }}</a>
                                            </td>
                                        @endif
                                    @endforeach
                                    <td class="is-total">
                                        <a class="yi-count-link" href="{{ $yiCountUrl($metric['key'], 'grand', null, null, $yiRecordsDistrict) }}">{{ number_format((int) ($yiTotals[$metric['key']] ?? 0)) }}</a>
                                    </td>
                                </tr>
                            @endforeach
                        @endif
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @php
        $dm = $districtMatrix ?? ['years' => [], 'metrics' => [], 'phase_groups' => [], 'tables' => []];
        $dmPhaseGroups = $dm['phase_groups'] ?? [];
        $metricLabels = [
            'cfa' => 'CFA',
            'onboarding' => 'Onboarding',
            'udyam' => 'Udyam registration',
            'artisan_card' => 'Artisan card',
            'fssai' => 'FSSAI',
            'gst' => 'GST',
            'market_linkage' => 'Market linkage',
            'convergence' => 'Convergence',
        ];
    @endphp

    @if (!empty($dm['tables']))
        <div class="yi-deck">
            <div class="yi-deck__title">District-wise breakdown</div>

            @foreach ($metricLabels as $metricKey => $metricLabel)
                @continue(empty($dm['tables'][$metricKey]))
                <div class="yi-deck-table-wrap">
                    <table class="yi-deck-table">
                        <caption>{{ $metricLabel }}</caption>
                        <thead>
                            <tr>
                                <th rowspan="2">District Name</th>
                                @foreach ($dmPhaseGroups as $group)
                                    <th colspan="{{ count($group['years']) + 1 }}" class="is-phase ph-{{ $group['key'] }}">
                                        {{ $group['label'] }}
                                    </th>
                                    @if ($group['key'] === 'phase1')
                                        <th rowspan="2" class="is-till">Till Phase Total<br><span style="font-size:.68rem;font-weight:600;opacity:.9;">(Pilot + Phase 1)</span></th>
                                    @endif
                                @endforeach
                                <th rowspan="2" class="is-grand-h">Grand Total</th>
                            </tr>
                            <tr>
                                @foreach ($dmPhaseGroups as $group)
                                    @foreach ($group['years'] as $yi => $fyCol)
                                        <th class="ph-{{ $group['key'] }}{{ $yi === 0 ? ' ph-start' : '' }}">{{ $fyCol }}</th>
                                    @endforeach
                                    <th class="is-phase-total ph-{{ $group['key'] }}">Phase Total</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($dm['tables'][$metricKey] as $dmRow)
                                @php
                                    $rowClass = $dmRow['district'] === 'Grand Total'
                                        ? 'is-grand'
                                        : ($dmRow['district'] === 'Other / Unmapped' ? 'is-other' : '');
                                    $runningTill = 0;
                                @endphp
                                <tr @class([$rowClass])>
                                    <td>{{ $dmRow['district'] }}</td>
                                    @foreach ($dmPhaseGroups as $group)
                                        @foreach ($group['years'] as $yi => $fyCol)
                                            @php $dmCell = (int) ($dmRow[$fyCol] ?? 0); @endphp
                                            <td class="ph-{{ $group['key'] }}{{ $yi === 0 ? ' ph-start' : '' }}">
                                                <a class="yi-count-link" href="{{ $yiCountUrl($metricKey, 'year', $fyCol, null, (string) $dmRow['district']) }}">{{ number_format($dmCell) }}</a>
                                            </td>
                                        @endforeach
                                        @php
                                            $phaseSum = (int) ($dmRow['phase_'.$group['key']] ?? 0);
                                            $runningTill += $phaseSum;
                                        @endphp
                                        <td class="is-phase-total ph-{{ $group['key'] }}">
                                            <a class="yi-count-link" href="{{ $yiCountUrl($metricKey, 'phase', null, $group['key'], (string) $dmRow['district']) }}">{{ number_format($phaseSum) }}</a>
                                        </td>
                                        @if ($group['key'] === 'phase1')
                                            <td class="is-till">
                                                <a class="yi-count-link" href="{{ $yiCountUrl($metricKey, 'till', null, null, (string) $dmRow['district']) }}">{{ number_format($runningTill) }}</a>
                                            </td>
                                        @endif
                                    @endforeach
                                    <td class="is-total">
                                        <a class="yi-count-link" href="{{ $yiCountUrl($metricKey, 'grand', null, null, (string) $dmRow['district']) }}">{{ number_format((int) ($dmRow['total'] ?? 0)) }}</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endforeach
        </div>
    @endif
</div>
@endsection
