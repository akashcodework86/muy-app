@extends('layouts.admin')

@section('title', 'District target month wise (official)')
@section('heading', 'District target month wise — official plan')

@section('content')
    <p style="font-size:0.88rem; color:#52525b; margin:0 0 1rem; max-width:58rem; line-height:1.55;">
        Official district / hub monthly plan (same layout as <strong>District Target Month Wise</strong> Excel).
        Cells show your <strong>last saved</strong> targets. Use <strong>Put targets automatically</strong> to refill from the Excel plan, edit if needed, then <strong>Update targets</strong> to save.
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
                    <li>{{ is_array($error) ? implode('; ', $error) : $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="get" action="{{ route('admin.targets.official-district-monthly') }}" style="margin-bottom:1rem; display:flex; flex-wrap:wrap; gap:0.65rem; align-items:flex-end; padding:0.85rem 1rem; background:#fff; border:1px solid #e4e4e7; border-radius:10px;">
        <div>
            <label for="fy" style="display:block; font-size:0.78rem; font-weight:600; margin-bottom:0.25rem;">Fiscal year</label>
            <select id="fy" name="fiscal_year_id" style="padding:0.45rem 0.55rem; border-radius:8px; border:1px solid #d4d4d8; min-width:11rem;">
                @foreach ($fiscalYears as $fy)
                    <option value="{{ $fy->id }}" @selected((int) $fiscalYearId === (int) $fy->id)>{{ $fy->name }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" style="background:#18181b; color:#fff; border:none; padding:0.5rem 0.85rem; border-radius:8px; font-weight:600; cursor:pointer;">Load</button>
    </form>

    <form method="post" action="{{ route('admin.targets.official-district-monthly.apply') }}" id="official-district-form">
        @csrf
        <input type="hidden" name="fiscal_year_id" value="{{ $fiscalYearId }}">
        <input type="hidden" name="district_payload" id="district_payload" value="">

        <div style="background:linear-gradient(135deg,#eff6ff,#f0fdf4); border:1px solid #93c5fd; border-radius:10px; padding:0.85rem 1rem; margin-bottom:1.25rem; font-size:0.88rem; display:flex; flex-wrap:wrap; gap:1rem; align-items:center;">
            <div><strong>Services / blocks:</strong> {{ count($districtBlocks) }}</div>
            <div><strong>State-only rows:</strong> {{ count($stateOnlyRows) }}</div>
            <button type="button" id="btn-auto-fill" style="background:#0369a1; color:#fff; border:none; padding:0.55rem 1rem; border-radius:8px; font-weight:700; cursor:pointer;">
                Put targets automatically
            </button>
            <button type="submit" style="background:#1d4ed8; color:#fff; border:none; padding:0.55rem 1rem; border-radius:8px; font-weight:700; cursor:pointer;"
                onclick="return confirm('Save these district monthly targets to the database?');">
                Update targets
            </button>
        </div>

        @foreach ($districtBlocks as $block)
            @php
                $deliverableId = (int) ($block['deliverable']->id ?? 0);
                $mapped = (bool) ($block['mapped'] ?? false);
            @endphp
            <div class="district-block" style="margin-bottom:1.5rem; background:#fff; border:1px solid #e4e4e7; border-radius:10px; overflow:hidden;"
                @if ($mapped && $deliverableId > 0) data-block="1" @endif>
                <div style="padding:0.65rem 0.9rem; background:#9a3412; color:#fff; font-weight:700; font-size:0.9rem;">
                    @if (! empty($block['excel_sn']))
                        {{ $block['excel_sn'] }}.
                    @endif
                    {{ $block['mis_serial'] ? $block['mis_serial'].' — ' : '' }}{{ $block['name'] }}
                    <span style="font-weight:400; opacity:0.85; margin-left:0.5rem;">
                        Official state total: {{ number_format((int) ($block['official_state_total'] ?? 0)) }}
                        · Form total: <span class="block-grand-total">0</span>
                    </span>
                    @if (! $mapped)
                        <span style="display:block; font-size:0.75rem; color:#fecaca; margin-top:0.25rem;">{{ $block['map_error'] ?? 'Not mapped' }}</span>
                    @endif
                </div>
                <div style="overflow-x:auto;">
                    <table class="block-table" style="border-collapse:collapse; font-size:0.78rem; min-width:max-content; width:100%;">
                        <thead>
                            <tr>
                                <th style="padding:0.45rem 0.65rem; background:#ffedd5; border:1px solid #fdba74; text-align:left; min-width:8rem;">District</th>
                                @foreach ($monthLabels as $m => $label)
                                    <th style="padding:0.4rem 0.35rem; background:#ffedd5; border:1px solid #fdba74; text-align:center;">{{ $label }}</th>
                                @endforeach
                                <th style="padding:0.45rem 0.65rem; background:#ffedd5; border:1px solid #fdba74; text-align:center;">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($block['district_rows'] ?? [] as $dRow)
                                @php $districtId = (int) $dRow['district']->id; @endphp
                                <tr class="data-row">
                                    <td style="padding:0.4rem 0.65rem; border:1px solid #e4e4e7; font-weight:600;">{{ $dRow['district']->name }}</td>
                                    @if ($mapped && $deliverableId > 0)
                                        @foreach (range(1, 12) as $m)
                                            <td style="padding:0.15rem; border:1px solid #e4e4e7; text-align:center;">
                                                <input type="number" min="0" step="1"
                                                    value="{{ (int) ($dRow['saved_months'][$m] ?? 0) }}"
                                                    data-official="{{ (int) ($dRow['official_months'][$m] ?? 0) }}"
                                                    data-scope="district"
                                                    data-deliverable-id="{{ $deliverableId }}"
                                                    data-district-id="{{ $districtId }}"
                                                    data-month="{{ $m }}"
                                                    class="month-input"
                                                    style="width:2.75rem; padding:0.2rem; text-align:center; border:1px solid #d4d4d8; border-radius:4px; font-size:0.75rem;">
                                            </td>
                                        @endforeach
                                        <td class="row-total" style="padding:0.4rem; border:1px solid #e4e4e7; text-align:center; font-weight:700;">0</td>
                                    @else
                                        @foreach (range(1, 12) as $m)
                                            <td style="padding:0.35rem; border:1px solid #e4e4e7; text-align:center;">{{ number_format((int) ($dRow['official_months'][$m] ?? 0)) }}</td>
                                        @endforeach
                                        <td style="padding:0.4rem; border:1px solid #e4e4e7; text-align:center; font-weight:700;">{{ number_format((int) ($dRow['official_total'] ?? 0)) }}</td>
                                    @endif
                                </tr>
                            @endforeach
                            @foreach ($block['hub_rows'] ?? [] as $hRow)
                                @php $hubId = (int) $hRow['hub']->id; @endphp
                                <tr class="data-row">
                                    <td style="padding:0.4rem 0.65rem; border:1px solid #e4e4e7; font-weight:600;">{{ $hRow['hub']->name }} (Hub)</td>
                                    @if ($mapped && $deliverableId > 0)
                                        @foreach (range(1, 12) as $m)
                                            <td style="padding:0.15rem; border:1px solid #e4e4e7; text-align:center;">
                                                <input type="number" min="0" step="1"
                                                    value="{{ (int) ($hRow['saved_months'][$m] ?? 0) }}"
                                                    data-official="{{ (int) ($hRow['official_months'][$m] ?? 0) }}"
                                                    data-scope="hub"
                                                    data-deliverable-id="{{ $deliverableId }}"
                                                    data-hub-id="{{ $hubId }}"
                                                    data-month="{{ $m }}"
                                                    class="month-input"
                                                    style="width:2.75rem; padding:0.2rem; text-align:center; border:1px solid #d4d4d8; border-radius:4px; font-size:0.75rem;">
                                            </td>
                                        @endforeach
                                        <td class="row-total" style="padding:0.4rem; border:1px solid #e4e4e7; text-align:center; font-weight:700;">0</td>
                                    @else
                                        @foreach (range(1, 12) as $m)
                                            <td style="padding:0.35rem; border:1px solid #e4e4e7; text-align:center;">{{ number_format((int) ($hRow['official_months'][$m] ?? 0)) }}</td>
                                        @endforeach
                                        <td style="padding:0.4rem; border:1px solid #e4e4e7; text-align:center; font-weight:700;">{{ number_format((int) ($hRow['official_total'] ?? 0)) }}</td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                        @if ($mapped && $deliverableId > 0)
                            <tfoot>
                                <tr style="background:#ffedd5; font-weight:700;">
                                    <td style="padding:0.4rem 0.65rem; border:1px solid #fdba74;">Service total</td>
                                    @foreach (range(1, 12) as $m)
                                        <td class="col-total" data-month="{{ $m }}" style="padding:0.35rem; border:1px solid #fdba74; text-align:center;">0</td>
                                    @endforeach
                                    <td class="footer-grand" style="padding:0.4rem; border:1px solid #fdba74; text-align:center;">0</td>
                                </tr>
                            </tfoot>
                        @endif
                    </table>
                </div>
            </div>
        @endforeach

        @if ($stateOnlyRows !== [])
            <div style="margin-bottom:1rem; background:#fff; border:1px solid #e4e4e7; border-radius:10px; overflow:hidden;">
                <div style="padding:0.65rem 0.9rem; background:#1e3a8a; color:#fff; font-weight:700; font-size:0.9rem;">
                    State-level monthly targets (no district split)
                </div>
                <div style="overflow-x:auto;">
                    <table style="border-collapse:collapse; font-size:0.78rem; min-width:max-content; width:100%;" id="state-only-table">
                        <thead>
                            <tr>
                                <th style="padding:0.45rem 0.65rem; background:#dbeafe; border:1px solid #93c5fd; text-align:left;">S.N.</th>
                                <th style="padding:0.45rem 0.65rem; background:#dbeafe; border:1px solid #93c5fd; text-align:left; min-width:16rem;">Indicator</th>
                                <th style="padding:0.45rem 0.65rem; background:#dbeafe; border:1px solid #93c5fd; text-align:center;">Level</th>
                                @foreach ($monthLabels as $m => $label)
                                    <th style="padding:0.4rem 0.35rem; background:#dbeafe; border:1px solid #93c5fd; text-align:center;">{{ $label }}</th>
                                @endforeach
                                <th style="padding:0.45rem 0.65rem; background:#dbeafe; border:1px solid #93c5fd; text-align:center;">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($stateOnlyRows as $row)
                                @php
                                    $deliverableId = (int) ($row['deliverable']->id ?? 0);
                                    $mapped = (bool) ($row['mapped'] ?? false);
                                @endphp
                                <tr @if (! $mapped) style="background:#fef2f2;" @endif
                                    @if ($mapped && $deliverableId > 0) class="state-only-row" data-leaf="1" @endif>
                                    <td style="padding:0.4rem 0.65rem; border:1px solid #e4e4e7;">{{ $row['excel_sn'] ?? '' }}</td>
                                    <td style="padding:0.4rem 0.65rem; border:1px solid #e4e4e7;">
                                        <div style="font-weight:600;">{{ $row['mis_serial'] }} — {{ $row['name'] }}</div>
                                        @if (! $mapped)
                                            <div style="font-size:0.72rem; color:#b91c1c;">{{ $row['map_error'] ?? '' }}</div>
                                        @endif
                                    </td>
                                    <td style="padding:0.4rem; border:1px solid #e4e4e7; text-align:center; font-size:0.72rem; color:#64748b;">{{ $row['level'] ?? '' }}</td>
                                    @if ($mapped && $deliverableId > 0)
                                        @foreach (range(1, 12) as $m)
                                            <td style="padding:0.15rem; border:1px solid #e4e4e7; text-align:center;">
                                                <input type="number" min="0" step="1"
                                                    value="{{ (int) ($row['saved_months'][$m] ?? 0) }}"
                                                    data-official="{{ (int) ($row['official_months'][$m] ?? 0) }}"
                                                    data-scope="state_only"
                                                    data-deliverable-id="{{ $deliverableId }}"
                                                    data-month="{{ $m }}"
                                                    class="month-input"
                                                    style="width:2.75rem; padding:0.2rem; text-align:center; border:1px solid #d4d4d8; border-radius:4px; font-size:0.75rem;">
                                            </td>
                                        @endforeach
                                        <td class="row-total" style="padding:0.4rem; border:1px solid #e4e4e7; text-align:center; font-weight:700;">0</td>
                                    @else
                                        @foreach (range(1, 12) as $m)
                                            <td style="padding:0.35rem; border:1px solid #e4e4e7; text-align:center;">{{ number_format((int) ($row['official_months'][$m] ?? 0)) }}</td>
                                        @endforeach
                                        <td style="padding:0.4rem; border:1px solid #e4e4e7; text-align:center; font-weight:700;">{{ number_format((int) ($row['official_total'] ?? 0)) }}</td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </form>

    @push('scripts')
    <script>
        (function () {
            const form = document.getElementById('official-district-form');
            if (!form) return;

            function recalcBlock(block) {
                const colTotals = {};
                for (let m = 1; m <= 12; m++) colTotals[m] = 0;
                let grand = 0;

                block.querySelectorAll('.data-row, tr[data-leaf="1"]').forEach(function (row) {
                    let rowSum = 0;
                    row.querySelectorAll('.month-input').forEach(function (input) {
                        const val = parseInt(input.value, 10) || 0;
                        rowSum += val;
                        const m = parseInt(input.getAttribute('data-month') || '', 10);
                        if (m >= 1 && m <= 12) {
                            colTotals[m] = (colTotals[m] || 0) + val;
                        }
                    });
                    const totalCell = row.querySelector('.row-total');
                    if (totalCell) totalCell.textContent = rowSum.toLocaleString();
                    grand += rowSum;
                });

                for (let m = 1; m <= 12; m++) {
                    const cell = block.querySelector('.col-total[data-month="' + m + '"]');
                    if (cell) cell.textContent = (colTotals[m] || 0).toLocaleString();
                }
                const footer = block.querySelector('.footer-grand');
                if (footer) footer.textContent = grand.toLocaleString();
                const headerTotal = block.querySelector('.block-grand-total');
                if (headerTotal) headerTotal.textContent = grand.toLocaleString();
            }

            function recalcAll() {
                form.querySelectorAll('[data-block="1"]').forEach(recalcBlock);
                const stateOnlyTable = document.getElementById('state-only-table');
                if (stateOnlyTable) recalcBlock(stateOnlyTable);
            }

            form.querySelectorAll('.month-input').forEach(function (input) {
                input.addEventListener('input', recalcAll);
            });

            document.getElementById('btn-auto-fill')?.addEventListener('click', function () {
                form.querySelectorAll('.month-input').forEach(function (input) {
                    input.value = input.getAttribute('data-official') || '0';
                });
                recalcAll();
            });

            form.addEventListener('submit', function () {
                const blocks = {};
                const stateOnly = {};

                form.querySelectorAll('.month-input[data-deliverable-id]').forEach(function (input) {
                    const scope = input.getAttribute('data-scope') || '';
                    const deliverableId = input.getAttribute('data-deliverable-id');
                    const month = input.getAttribute('data-month');
                    const value = parseInt(input.value, 10) || 0;

                    if (!deliverableId || !month) {
                        return;
                    }

                    if (scope === 'state_only') {
                        if (!stateOnly[deliverableId]) {
                            stateOnly[deliverableId] = {};
                        }
                        stateOnly[deliverableId][month] = value;

                        return;
                    }

                    if (!blocks[deliverableId]) {
                        blocks[deliverableId] = { districts: {}, hubs: {} };
                    }

                    if (scope === 'district') {
                        const districtId = input.getAttribute('data-district-id');
                        if (!districtId) {
                            return;
                        }
                        if (!blocks[deliverableId].districts[districtId]) {
                            blocks[deliverableId].districts[districtId] = {};
                        }
                        blocks[deliverableId].districts[districtId][month] = value;
                    } else if (scope === 'hub') {
                        const hubId = input.getAttribute('data-hub-id');
                        if (!hubId) {
                            return;
                        }
                        if (!blocks[deliverableId].hubs[hubId]) {
                            blocks[deliverableId].hubs[hubId] = {};
                        }
                        blocks[deliverableId].hubs[hubId][month] = value;
                    }
                });

                const payloadEl = document.getElementById('district_payload');
                if (payloadEl) {
                    payloadEl.value = JSON.stringify({ blocks: blocks, state_only: stateOnly });
                }
            });

            recalcAll();
        })();
    </script>
    @endpush
@endsection
