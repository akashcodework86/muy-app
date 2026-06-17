@extends('layouts.admin')

@section('title', 'State target month wise (official)')
@section('heading', 'State target month wise — official plan')

@section('content')
    <p style="font-size:0.88rem; color:#52525b; margin:0 0 1rem; max-width:58rem; line-height:1.55;">
        Official state monthly plan (same layout as <strong>State Target Month Wise</strong> Excel).
        Cells show your <strong>last saved</strong> targets. Use <strong>Put targets automatically</strong> to refill from the Excel plan, edit if needed, then <strong>Update targets</strong> to save.
        For district-split services, totals are cross-checked against
        <a href="{{ route('admin.targets.official-district-monthly', ['fiscal_year_id' => $fiscalYearId]) }}" style="color:#1d4ed8; font-weight:600;">District target month wise</a>.
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

    <form method="get" action="{{ route('admin.targets.official-state-monthly') }}" style="margin-bottom:1rem; display:flex; flex-wrap:wrap; gap:0.65rem; align-items:flex-end; padding:0.85rem 1rem; background:#fff; border:1px solid #e4e4e7; border-radius:10px;">
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

    @if ($grid === [])
        <p style="color:#64748b;">No official rows configured.</p>
    @else
        <form method="post" action="{{ route('admin.targets.official-state-monthly.apply') }}" id="official-state-form">
            @csrf
            <input type="hidden" name="fiscal_year_id" value="{{ $fiscalYearId }}">
            <input type="hidden" name="targets_payload" id="targets_payload" value="">

            <div style="background:linear-gradient(135deg,#fff7ed,#fef3c7); border:1px solid #fdba74; border-radius:10px; padding:0.85rem 1rem; margin-bottom:1rem; font-size:0.88rem; display:flex; flex-wrap:wrap; gap:1rem; align-items:center;">
                <div><strong>Official plan total:</strong> <span id="grand-official">{{ number_format((int) ($columnTotals['grand_official'] ?? 0)) }}</span></div>
                <div><strong>Form total:</strong> <span id="grand-form">0</span></div>
                <div><strong>Saved in DB:</strong> {{ number_format((int) ($columnTotals['grand_saved'] ?? 0)) }}</div>
                <button type="button" id="btn-auto-fill" style="background:#0369a1; color:#fff; border:none; padding:0.55rem 1rem; border-radius:8px; font-weight:700; cursor:pointer;">
                    Put targets automatically
                </button>
                <button type="submit" style="background:#b45309; color:#fff; border:none; padding:0.55rem 1rem; border-radius:8px; font-weight:700; cursor:pointer;"
                    onclick="return confirm('Save these state monthly targets to the database?');">
                    Update targets
                </button>
            </div>

            <div style="overflow-x:auto; background:#fff; border:1px solid #d4d4d8; border-radius:10px;">
                <table style="border-collapse:collapse; font-size:0.8rem; min-width:max-content; width:100%;" id="state-target-grid">
                    <thead>
                        <tr>
                            <th style="padding:0.5rem 0.65rem; background:#9a3412; color:#fff; border:1px solid #7c2d12; text-align:left;">S.N.</th>
                            <th style="padding:0.5rem 0.65rem; background:#9a3412; color:#fff; border:1px solid #7c2d12; text-align:left; min-width:16rem;">Indicator</th>
                            <th style="padding:0.5rem 0.65rem; background:#9a3412; color:#fff; border:1px solid #7c2d12; text-align:center;">Official total</th>
                            <th style="padding:0.5rem 0.65rem; background:#9a3412; color:#fff; border:1px solid #7c2d12; text-align:center;">Type</th>
                            @foreach ($monthLabels as $m => $label)
                                <th style="padding:0.45rem 0.35rem; background:#ffedd5; border:1px solid #fdba74; text-align:center; min-width:3.25rem;">{{ $label }}</th>
                            @endforeach
                            <th style="padding:0.5rem 0.65rem; background:#ffedd5; border:1px solid #fdba74; text-align:center;">Row total</th>
                            <th style="padding:0.5rem 0.65rem; background:#fef3c7; border:1px solid #fcd34d; text-align:center;">Saved</th>
                            <th style="padding:0.5rem 0.65rem; background:#dbeafe; border:1px solid #93c5fd; text-align:center;">District allocated</th>
                            <th style="padding:0.5rem 0.65rem; background:#dbeafe; border:1px solid #93c5fd; text-align:center;">Alignment</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($grid as $row)
                            @php $type = $row['row_type'] ?? ''; @endphp
                            @if (in_array($type, ['category', 'subcategory'], true))
                                <tr style="background:#ffedd5; font-weight:700;">
                                    <td style="padding:0.45rem 0.65rem; border:1px solid #fdba74;">{{ $row['serial'] ?? '' }}</td>
                                    <td colspan="18" style="padding:0.45rem 0.65rem; border:1px solid #fdba74;">{{ $row['name'] ?? '' }}</td>
                                </tr>
                            @elseif ($type === 'leaf')
                                @php
                                    $deliverableId = (int) ($row['deliverable']->id ?? 0);
                                    $mapped = (bool) ($row['mapped'] ?? false);
                                    $hasDistrictSplit = (bool) ($row['has_district_split'] ?? false);
                                    $verifyDistrict = (array) ($row['verify_district'] ?? []);
                                    $districtAllocatedTotal = (int) ($row['district_allocated_total'] ?? 0);
                                @endphp
                                <tr class="leaf-row" @if (! $mapped) style="background:#fef2f2;" @endif
                                    @if ($mapped) data-leaf="1" @endif>
                                    <td style="padding:0.4rem 0.65rem; border:1px solid #e4e4e7;">{{ $row['sn'] ?? '' }}</td>
                                    <td style="padding:0.4rem 0.65rem; border:1px solid #e4e4e7; max-width:20rem;">
                                        <div style="font-weight:600;">{{ $row['serial'] }} — {{ $row['name'] }}</div>
                                        @if (! $mapped)
                                            <div style="font-size:0.72rem; color:#b91c1c;">{{ $row['map_error'] ?? 'Not mapped' }}</div>
                                        @endif
                                    </td>
                                    <td style="padding:0.4rem; border:1px solid #e4e4e7; text-align:center; font-weight:600;">{{ number_format((int) ($row['official_total'] ?? 0)) }}</td>
                                    <td style="padding:0.4rem; border:1px solid #e4e4e7; text-align:center; color:#64748b; font-size:0.75rem;">{{ $row['indicator_type'] ?? '' }}</td>
                                    @if ($mapped && $deliverableId > 0)
                                        @foreach (range(1, 12) as $m)
                                            <td style="padding:0.2rem; border:1px solid #e4e4e7; text-align:center;">
                                                <input type="number" min="0" step="1"
                                                    value="{{ (int) ($row['saved_months'][$m] ?? 0) }}"
                                                    data-official="{{ (int) ($row['official_months'][$m] ?? 0) }}"
                                                    data-deliverable-id="{{ $deliverableId }}"
                                                    data-month="{{ $m }}"
                                                    class="month-input"
                                                    style="width:3.25rem; padding:0.25rem; text-align:center; border:1px solid #d4d4d8; border-radius:4px;">
                                            </td>
                                        @endforeach
                                        <td class="row-total" style="padding:0.4rem; border:1px solid #e4e4e7; text-align:center; font-weight:700;">0</td>
                                        <td style="padding:0.4rem; border:1px solid #e4e4e7; text-align:center;">
                                            @if (($row['saved_total'] ?? 0) > 0)
                                                <span style="color:#047857;">{{ number_format((int) $row['saved_total']) }}</span>
                                            @else
                                                <span style="color:#94a3b8;">—</span>
                                            @endif
                                        </td>
                                        <td style="padding:0.4rem; border:1px solid #e4e4e7; text-align:center;">
                                            @if ($hasDistrictSplit)
                                                @if ($districtAllocatedTotal > 0)
                                                    <span style="font-weight:600;">{{ number_format($districtAllocatedTotal) }}</span>
                                                @else
                                                    <span style="color:#94a3b8;">—</span>
                                                @endif
                                            @else
                                                <span style="color:#94a3b8; font-size:0.72rem;">N/A</span>
                                            @endif
                                        </td>
                                        <td style="padding:0.4rem; border:1px solid #e4e4e7; text-align:center;">
                                            @if ($hasDistrictSplit && $verifyDistrict !== [])
                                                <span style="display:inline-block; padding:0.15rem 0.45rem; border-radius:999px; font-size:0.72rem; font-weight:700; color:{{ $verifyDistrict['color'] ?? '#64748b' }}; background:{{ $verifyDistrict['bg'] ?? '#f1f5f9' }};">{{ $verifyDistrict['label'] ?? '—' }}</span>
                                            @else
                                                <span style="color:#94a3b8;">—</span>
                                            @endif
                                        </td>
                                    @else
                                        @foreach (range(1, 12) as $m)
                                            <td style="padding:0.35rem; border:1px solid #e4e4e7; text-align:center; color:#94a3b8;">—</td>
                                        @endforeach
                                        <td style="padding:0.4rem; border:1px solid #e4e4e7; text-align:center;">—</td>
                                        <td style="padding:0.4rem; border:1px solid #e4e4e7; text-align:center;">—</td>
                                        <td style="padding:0.4rem; border:1px solid #e4e4e7; text-align:center;">—</td>
                                        <td style="padding:0.4rem; border:1px solid #e4e4e7; text-align:center;">—</td>
                                    @endif
                                </tr>
                            @endif
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr style="background:#ffedd5; font-weight:700;">
                            <td colspan="4" style="padding:0.45rem 0.65rem; border:1px solid #fdba74;">Column totals</td>
                            @foreach (range(1, 12) as $m)
                                <td class="col-total" data-month="{{ $m }}" style="padding:0.4rem; border:1px solid #fdba74; text-align:center;">0</td>
                            @endforeach
                            <td id="footer-grand" style="padding:0.4rem; border:1px solid #fdba74; text-align:center;">0</td>
                            <td style="border:1px solid #fdba74;"></td>
                            <td style="border:1px solid #fdba74;"></td>
                            <td style="border:1px solid #fdba74;"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </form>
    @endif

    @push('scripts')
    <script>
        (function () {
            const form = document.getElementById('official-state-form');
            if (!form) return;

            function recalc() {
                const colTotals = {};
                for (let m = 1; m <= 12; m++) colTotals[m] = 0;
                let grand = 0;

                form.querySelectorAll('tr[data-leaf="1"]').forEach(function (row) {
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
                    const cell = form.querySelector('.col-total[data-month="' + m + '"]');
                    if (cell) cell.textContent = (colTotals[m] || 0).toLocaleString();
                }
                const footer = document.getElementById('footer-grand');
                const grandForm = document.getElementById('grand-form');
                if (footer) footer.textContent = grand.toLocaleString();
                if (grandForm) grandForm.textContent = grand.toLocaleString();
            }

            form.querySelectorAll('.month-input').forEach(function (input) {
                input.addEventListener('input', recalc);
            });

            document.getElementById('btn-auto-fill')?.addEventListener('click', function () {
                form.querySelectorAll('.month-input').forEach(function (input) {
                    input.value = input.getAttribute('data-official') || '0';
                });
                recalc();
            });

            form.addEventListener('submit', function () {
                const targets = {};
                form.querySelectorAll('.month-input[data-deliverable-id]').forEach(function (input) {
                    const deliverableId = input.getAttribute('data-deliverable-id');
                    const month = input.getAttribute('data-month');
                    if (!deliverableId || !month) {
                        return;
                    }
                    if (!targets[deliverableId]) {
                        targets[deliverableId] = {};
                    }
                    targets[deliverableId][month] = parseInt(input.value, 10) || 0;
                });
                const payloadEl = document.getElementById('targets_payload');
                if (payloadEl) {
                    payloadEl.value = JSON.stringify(targets);
                }
            });

            recalc();
        })();
    </script>
    @endpush
@endsection
