@extends('layouts.admin')

@section('title', 'Monthly targets by district / hub')
@section('heading', 'Monthly targets — Hub & Spoke / Hub only')

@section('content')
    @php
        use App\Services\DistrictHubMonthlyTargetsService;
        $isDistrictTab = $tab === DistrictHubMonthlyTargetsService::SCOPE_DISTRICT;
    @endphp

    <p style="font-size:0.88rem; color:#52525b; margin:0 0 1rem; max-width:56rem; line-height:1.55;">
        Monthly targets for MIS indicators only:
        <strong>1.3</strong>, <strong>1.3.1</strong>, <strong>3.1</strong>, <strong>3.2</strong>, <strong>3.3</strong>, <strong>3.3.1</strong> (Hub &amp; Spoke — districts)
        and <strong>3.4</strong> (Hub only — Kumaon / Garhwal).
        Set <strong>M1–M12</strong> per row; row total should match the district FY or state FY when final.
    </p>

    @if (session('status'))
        <p style="background:#f0fdf4; border:1px solid #86efac; color:#166534; padding:0.75rem; border-radius:8px; font-size:0.88rem; margin-bottom:1rem;">
            {{ session('status') }}
        </p>
    @endif

    @if ($errors->any())
        <div style="background:#fef2f2; border:1px solid #fca5a5; color:#991b1b; padding:0.75rem; border-radius:8px; font-size:0.88rem; margin-bottom:1rem;">
            <ul style="margin:0.35rem 0 0 1.1rem; padding:0;">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div style="display:flex; gap:0.5rem; margin-bottom:1rem; flex-wrap:wrap;">
        <a href="{{ route('admin.targets.district-hub-monthly', ['fiscal_year_id' => $fiscalYearId, 'deliverable_id' => $deliverableId, 'tab' => DistrictHubMonthlyTargetsService::SCOPE_DISTRICT]) }}"
           style="padding:0.5rem 1rem; border-radius:8px; font-weight:600; font-size:0.88rem; text-decoration:none; {{ $isDistrictTab ? 'background:#9a3412; color:#fff;' : 'background:#fff; color:#334155; border:1px solid #d4d4d8;' }}">
            Hub &amp; Spoke (districts)
        </a>
        <a href="{{ route('admin.targets.district-hub-monthly', ['fiscal_year_id' => $fiscalYearId, 'deliverable_id' => $deliverableId, 'tab' => DistrictHubMonthlyTargetsService::SCOPE_HUB]) }}"
           style="padding:0.5rem 1rem; border-radius:8px; font-weight:600; font-size:0.88rem; text-decoration:none; {{ ! $isDistrictTab ? 'background:#9a3412; color:#fff;' : 'background:#fff; color:#334155; border:1px solid #d4d4d8;' }}">
            Hub only
        </a>
    </div>

    <form method="get" action="{{ route('admin.targets.district-hub-monthly') }}" style="margin-bottom:1rem; display:flex; flex-wrap:wrap; gap:0.65rem; align-items:flex-end; padding:0.85rem 1rem; background:#fff; border:1px solid #e4e4e7; border-radius:10px;">
        <input type="hidden" name="tab" value="{{ $tab }}">
        <div>
            <label for="fy" style="display:block; font-size:0.78rem; font-weight:600; margin-bottom:0.25rem;">Fiscal year</label>
            <select id="fy" name="fiscal_year_id" style="padding:0.45rem 0.55rem; border-radius:8px; border:1px solid #d4d4d8; min-width:11rem;">
                @foreach ($fiscalYears as $fy)
                    <option value="{{ $fy->id }}" @selected((int) $fiscalYearId === (int) $fy->id)>{{ $fy->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="deliverable_id" style="display:block; font-size:0.78rem; font-weight:600; margin-bottom:0.25rem;">Service / deliverable</label>
            <select id="deliverable_id" name="deliverable_id" style="padding:0.45rem 0.55rem; border-radius:8px; border:1px solid #d4d4d8; min-width:20rem;">
                @forelse ($scopeDeliverables as $d)
                    <option value="{{ $d->id }}" @selected((int) $deliverableId === (int) $d->id)>
                        {{ $d->mis_serial ? $d->mis_serial.' — ' : '' }}{{ $d->name }}
                    </option>
                @empty
                    <option value="">— No deliverables for this tab —</option>
                @endforelse
            </select>
        </div>
        <button type="submit" style="background:#18181b; color:#fff; border:none; padding:0.5rem 0.85rem; border-radius:8px; font-weight:600; cursor:pointer;">Load</button>
    </form>

    @if ($isDistrictTab && ($districtPresets ?? []) !== [])
        <div style="background:#fff; border:1px solid #e4e4e7; border-radius:10px; padding:0.85rem 1rem; margin-bottom:1rem;">
            <div style="font-size:0.78rem; font-weight:700; color:#57534e; margin-bottom:0.45rem; text-transform:uppercase; letter-spacing:0.04em;">
                Load official allocation (one click)
            </div>
            <p style="font-size:0.82rem; color:#64748b; margin:0 0 0.65rem; line-height:1.45;">
                Applies the approved Hub &amp; Spoke monthly split for the selected fiscal year. Overwrites district annual targets, state total, and M1–M12 values for that indicator.
            </p>
            <div style="display:flex; flex-wrap:wrap; gap:0.45rem;">
                @foreach ($districtPresets as $preset)
                    <form method="post" action="{{ route('admin.targets.district-hub-monthly.preset') }}" style="margin:0;"
                          onsubmit="return confirm('Load official allocation for {{ $preset['serial'] }} — {{ $preset['label'] }}? This will overwrite existing monthly targets for the selected fiscal year.');">
                        @csrf
                        <input type="hidden" name="fiscal_year_id" value="{{ $fiscalYearId }}">
                        <input type="hidden" name="deliverable_code" value="{{ $preset['code'] }}">
                        <button type="submit"
                                style="background:{{ ($deliverable && strtolower((string) $deliverable->code) === $preset['code']) ? '#c2410c' : '#fff' }}; color:{{ ($deliverable && strtolower((string) $deliverable->code) === $preset['code']) ? '#fff' : '#9a3412' }}; border:1px solid #fdba74; padding:0.4rem 0.7rem; border-radius:8px; font-weight:600; font-size:0.8rem; cursor:pointer;">
                            {{ $preset['serial'] }} — {{ $preset['label'] }}
                        </button>
                    </form>
                @endforeach
            </div>
        </div>
    @endif

    @if ($pending !== [])
        <div style="background:#fff7ed; border:1px solid #fed7aa; border-radius:10px; padding:0.75rem 0.9rem; margin-bottom:1rem;">
            <div style="font-size:0.78rem; font-weight:700; color:#c2410c; margin-bottom:0.45rem; text-transform:uppercase; letter-spacing:0.04em;">Pending monthly plans ({{ count($pending) }})</div>
            <div style="display:flex; flex-wrap:wrap; gap:0.4rem;">
                @foreach ($pending as $item)
                    <a href="{{ route('admin.targets.district-hub-monthly', ['fiscal_year_id' => $fiscalYearId, 'deliverable_id' => $item['deliverable']->id, 'tab' => $tab]) }}"
                       style="font-size:0.8rem; padding:0.3rem 0.55rem; border-radius:999px; text-decoration:none; background:#fff; border:1px solid {{ $item['status'] === 'needs_annual' ? '#fcd34d' : '#fdba74' }}; color:#9a3412;">
                        {{ $item['deliverable']->name }}
                        <span style="color:#64748b;">· {{ $item['status_label'] }}</span>
                    </a>
                @endforeach
            </div>
        </div>
    @endif

    @if (! $deliverable)
        <p style="color:#64748b;">Select a fiscal year and deliverable.</p>
    @elseif ($deliverable)
        @if ($isDistrictTab && $referenceAnnual <= 0)
            <p style="background:#fffbeb; border:1px solid #fcd34d; color:#92400e; padding:0.85rem; border-radius:8px; font-size:0.9rem; margin-bottom:1rem;">
                No district annual target yet for <strong>{{ $deliverable->name }}</strong>.
                You can still save a monthly plan below, or set annual totals on
                <a href="{{ route('admin.targets.district', ['fiscal_year_id' => $fiscalYearId, 'deliverable_id' => $deliverable->id]) }}">District targets</a>
                / <a href="{{ route('admin.targets.state', ['fiscal_year_id' => $fiscalYearId]) }}">State targets</a> first.
            </p>
        @elseif (! $isDistrictTab && $stateAnnual <= 0)
            <p style="background:#fffbeb; border:1px solid #fcd34d; color:#92400e; padding:0.85rem; border-radius:8px; font-size:0.9rem; margin-bottom:1rem;">
                No state annual target yet for <strong>{{ $deliverable->name }}</strong>.
                You can still save hub monthly plan below, or set it on
                <a href="{{ route('admin.targets.state', ['fiscal_year_id' => $fiscalYearId]) }}">State targets</a> first.
            </p>
        @endif

        <div style="background:linear-gradient(135deg,#fff7ed,#fef3c7); border:1px solid #fdba74; border-radius:10px; padding:0.85rem 1rem; margin-bottom:1rem; font-size:0.88rem; display:flex; flex-wrap:wrap; gap:1rem;">
            <div><strong>Deliverable:</strong> {{ $deliverable->name }}</div>
            @if ($isDistrictTab)
                <div><strong>District annual total:</strong> {{ number_format($referenceAnnual) }}</div>
                <div><strong>Monthly plan total:</strong> <span id="summary-grand">{{ number_format((int) ($columnTotals['grand'] ?? 0)) }}</span></div>
            @else
                <div><strong>State annual:</strong> {{ number_format($stateAnnual) }}</div>
                <div><strong>Hub monthly total:</strong> <span id="summary-grand">{{ number_format((int) ($columnTotals['grand'] ?? 0)) }}</span></div>
            @endif
        </div>

        @if ($isDistrictTab)
            <form method="post" action="{{ route('admin.targets.district-hub-monthly.district') }}" id="monthly-grid-form">
                @csrf
                <input type="hidden" name="fiscal_year_id" value="{{ $fiscalYearId }}">
                <input type="hidden" name="deliverable_id" value="{{ $deliverableId }}">
        @else
            <form method="post" action="{{ route('admin.targets.district-hub-monthly.hub') }}" id="monthly-grid-form">
                @csrf
                <input type="hidden" name="fiscal_year_id" value="{{ $fiscalYearId }}">
                <input type="hidden" name="deliverable_id" value="{{ $deliverableId }}">
        @endif

        <div style="overflow-x:auto; background:#fff; border:1px solid #d4d4d8; border-radius:10px;">
            <table style="border-collapse:collapse; font-size:0.8rem; min-width:max-content; width:100%;" id="monthly-targets-table">
                <thead>
                    <tr>
                        <th style="padding:0.5rem 0.65rem; background:#ea580c; color:#fff; border:1px solid #c2410c; text-align:left; min-width:{{ $isDistrictTab ? '8rem' : '10rem' }};">
                            {{ $isDistrictTab ? 'District' : 'Hub' }}
                        </th>
                        @foreach ($monthLabels as $m => $label)
                            <th style="padding:0.45rem 0.35rem; background:#ffedd5; border:1px solid #fdba74; text-align:center; min-width:3.25rem;">{{ $label }}</th>
                        @endforeach
                        <th style="padding:0.5rem 0.65rem; background:#ffedd5; border:1px solid #fdba74; text-align:center; min-width:5rem;">Total Target</th>
                        @if ($isDistrictTab)
                            <th style="padding:0.5rem 0.65rem; background:#fef3c7; border:1px solid #fcd34d; text-align:center; min-width:5rem;">District FY</th>
                        @endif
                        <th style="padding:0.5rem 0.65rem; background:#fef3c7; border:1px solid #fcd34d; text-align:center; min-width:6rem; writing-mode:vertical-rl; transform:rotate(180deg);">
                            {{ $isDistrictTab ? 'Hub & Spoke Teams' : 'Hub level' }}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    @if ($isDistrictTab)
                        @php $lastHub = null; @endphp
                        @foreach ($grid as $row)
                            @if ($lastHub !== $row['hub_name'])
                                @php $lastHub = $row['hub_name']; @endphp
                                <tr style="background:#fafafa;">
                                    <td colspan="{{ 12 + 3 + ($isDistrictTab ? 1 : 0) }}" style="padding:0.35rem 0.65rem; font-weight:700; color:#57534e; border-bottom:1px solid #e7e5e4; font-size:0.75rem;">
                                        {{ $row['hub_name'] }}
                                    </td>
                                </tr>
                            @endif
                            <tr class="js-data-row" data-annual="{{ (int) $row['annual'] }}">
                                <td style="padding:0.4rem 0.65rem; border:1px solid #e4e4e7; font-weight:500;">{{ $row['district']->name }}</td>
                                @foreach (range(1, 12) as $m)
                                    <td style="padding:0.25rem; border:1px solid #e4e4e7; text-align:center;">
                                        <input type="number" min="0" step="1"
                                            name="districts[{{ $row['district']->id }}][{{ $m }}]"
                                            value="{{ old('districts.'.$row['district']->id.'.'.$m, $row['months'][$m] ?? 0) }}"
                                            class="js-month-cell"
                                            style="width:3rem; padding:0.25rem; border:1px solid #d4d4d8; border-radius:4px; text-align:center; font-size:0.78rem;">
                                    </td>
                                @endforeach
                                <td style="padding:0.4rem; border:1px solid #e4e4e7; text-align:center; font-weight:700;" class="js-row-total">{{ number_format($row['row_total']) }}</td>
                                <td style="padding:0.4rem; border:1px solid #e4e4e7; text-align:center; color:#64748b;">{{ number_format($row['annual']) }}</td>
                                <td style="border:1px solid #e4e4e7;"></td>
                            </tr>
                        @endforeach
                    @else
                        @foreach ($grid as $row)
                            <tr class="js-data-row" data-annual="{{ (int) $stateAnnual }}">
                                <td style="padding:0.4rem 0.65rem; border:1px solid #e4e4e7; font-weight:600;">{{ $row['hub']->name }}</td>
                                @foreach (range(1, 12) as $m)
                                    <td style="padding:0.25rem; border:1px solid #e4e4e7; text-align:center;">
                                        <input type="number" min="0" step="1"
                                            name="hubs[{{ $row['hub']->id }}][{{ $m }}]"
                                            value="{{ old('hubs.'.$row['hub']->id.'.'.$m, $row['months'][$m] ?? 0) }}"
                                            class="js-month-cell"
                                            style="width:3rem; padding:0.25rem; border:1px solid #d4d4d8; border-radius:4px; text-align:center; font-size:0.78rem;">
                                    </td>
                                @endforeach
                                <td style="padding:0.4rem; border:1px solid #e4e4e7; text-align:center; font-weight:700;" class="js-row-total">{{ number_format($row['row_total']) }}</td>
                                <td style="border:1px solid #e4e4e7;"></td>
                            </tr>
                        @endforeach
                    @endif
                    <tr style="background:#ffedd5; font-weight:700;">
                        <td style="padding:0.45rem 0.65rem; border:1px solid #fdba74;">Total</td>
                        @foreach (range(1, 12) as $m)
                            <td style="padding:0.4rem; border:1px solid #fdba74; text-align:center;" class="js-col-total" data-month="{{ $m }}">{{ number_format((int) ($columnTotals[$m] ?? 0)) }}</td>
                        @endforeach
                        <td style="padding:0.4rem; border:1px solid #fdba74; text-align:center;" id="footer-grand">{{ number_format((int) ($columnTotals['grand'] ?? 0)) }}</td>
                        @if ($isDistrictTab)
                            <td style="padding:0.4rem; border:1px solid #fdba74; text-align:center;">{{ number_format($referenceAnnual) }}</td>
                        @endif
                        <td style="border:1px solid #fdba74;"></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <p style="margin-top:1rem; display:flex; flex-wrap:wrap; gap:0.5rem; align-items:center;">
            <button type="submit" style="background:#18181b; color:#fff; border:none; padding:0.55rem 1.1rem; border-radius:8px; font-weight:600; cursor:pointer;">
                Save monthly targets
            </button>
            @if ($isDistrictTab && ($districtPresets ?? []) !== [] && $deliverable)
                @php
                    $currentPreset = collect($districtPresets)->firstWhere('code', strtolower((string) $deliverable->code));
                @endphp
                @if ($currentPreset)
                    <form method="post" action="{{ route('admin.targets.district-hub-monthly.preset') }}" style="margin:0;"
                          onsubmit="return confirm('Load official allocation for this indicator? Existing values will be replaced.');">
                        @csrf
                        <input type="hidden" name="fiscal_year_id" value="{{ $fiscalYearId }}">
                        <input type="hidden" name="deliverable_code" value="{{ $currentPreset['code'] }}">
                        <button type="submit" style="background:#ea580c; color:#fff; border:none; padding:0.55rem 1.1rem; border-radius:8px; font-weight:600; cursor:pointer;">
                            Load official allocation
                        </button>
                    </form>
                @endif
            @endif
        </p>
        </form>
    @endif
@endsection

@if ($deliverable ?? false)
    @push('scripts')
        <script>
            (function () {
                const form = document.getElementById('monthly-grid-form');
                const table = document.getElementById('monthly-targets-table');
                if (!form || !table) return;

                const summaryGrand = document.getElementById('summary-grand');
                const footerGrand = document.getElementById('footer-grand');
                const colTotalCells = table.querySelectorAll('.js-col-total');

                function parseVal(el) {
                    const raw = String(el.value).trim();
                    const v = raw === '' ? 0 : parseInt(raw, 10);
                    return Number.isFinite(v) && v >= 0 ? v : 0;
                }

                function fmt(n) {
                    return Number(n).toLocaleString('en-IN');
                }

                function recalc() {
                    const colTotals = {};
                    for (let m = 1; m <= 12; m++) colTotals[m] = 0;
                    let grand = 0;

                    table.querySelectorAll('.js-data-row').forEach(function (row) {
                        let rowSum = 0;
                        row.querySelectorAll('.js-month-cell').forEach(function (cell, idx) {
                            const v = parseVal(cell);
                            rowSum += v;
                            const month = idx + 1;
                            colTotals[month] = (colTotals[month] || 0) + v;
                        });
                        const totalCell = row.querySelector('.js-row-total');
                        if (totalCell) totalCell.textContent = fmt(rowSum);
                        grand += rowSum;

                        const annual = parseInt(row.getAttribute('data-annual'), 10) || 0;
                        if (annual > 0 && rowSum !== annual) {
                            row.style.background = rowSum > annual ? '#fef2f2' : '#fffbeb';
                        } else if (annual > 0 && rowSum === annual) {
                            row.style.background = '#f0fdf4';
                        } else {
                            row.style.background = '';
                        }
                    });

                    colTotalCells.forEach(function (cell) {
                        const m = parseInt(cell.getAttribute('data-month'), 10);
                        cell.textContent = fmt(colTotals[m] || 0);
                    });

                    if (summaryGrand) summaryGrand.textContent = fmt(grand);
                    if (footerGrand) footerGrand.textContent = fmt(grand);
                }

                table.querySelectorAll('.js-month-cell').forEach(function (el) {
                    el.addEventListener('input', recalc);
                    el.addEventListener('change', recalc);
                });
                recalc();
            })();
        </script>
    @endpush
@endif
