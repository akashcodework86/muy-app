@extends('layouts.admin')

@section('title', 'State monthly targets')
@section('heading', 'State monthly targets — selected services')

@section('content')
    <p style="font-size:0.88rem; color:#52525b; margin:0 0 1rem; max-width:56rem; line-height:1.55;">
        Month-wise state plan for selected MIS services grouped by deliverable category (main category → sub-services).
        Includes <strong>6.1–6.2</strong>, <strong>10.1–10.6</strong>, <strong>11.1</strong>, <strong>12.1–12.2</strong>, and other state-level indicators.
        Set <strong>M1–M12</strong> per service — paste a row of 12 numbers or edit cells directly; row total should match the <strong>state FY target</strong> when final.
        Annual totals are set on <a href="{{ route('admin.targets.state', ['fiscal_year_id' => $fiscalYearId]) }}">State targets</a>.
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

    <form method="get" action="{{ route('admin.targets.state-monthly') }}" style="margin-bottom:1rem; display:flex; flex-wrap:wrap; gap:0.65rem; align-items:flex-end; padding:0.85rem 1rem; background:#fff; border:1px solid #e4e4e7; border-radius:10px;">
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
        <p style="color:#64748b;">No configured services found.</p>
    @else
        <div style="background:linear-gradient(135deg,#fff7ed,#fef3c7); border:1px solid #fdba74; border-radius:10px; padding:0.85rem 1rem; margin-bottom:1rem; font-size:0.88rem; display:flex; flex-wrap:wrap; gap:1rem;">
            <div><strong>Services:</strong> {{ count($grid) }}</div>
            <div><strong>Monthly plan total (all rows):</strong> <span id="summary-grand">{{ number_format((int) ($columnTotals['grand'] ?? 0)) }}</span></div>
        </div>

        <form method="post" action="{{ route('admin.targets.state-monthly.update') }}" id="monthly-grid-form">
            @csrf
            <input type="hidden" name="fiscal_year_id" value="{{ $fiscalYearId }}">

            <div style="overflow-x:auto; background:#fff; border:1px solid #d4d4d8; border-radius:10px;">
                <table style="border-collapse:collapse; font-size:0.8rem; min-width:max-content; width:100%;" id="monthly-targets-table">
                    <thead>
                        <tr>
                            <th style="padding:0.5rem 0.65rem; background:#ea580c; color:#fff; border:1px solid #c2410c; text-align:left; min-width:3.5rem;">S.N.</th>
                            <th style="padding:0.5rem 0.65rem; background:#ea580c; color:#fff; border:1px solid #c2410c; text-align:left; min-width:16rem;">Indicator</th>
                            <th style="padding:0.5rem 0.65rem; background:#ea580c; color:#fff; border:1px solid #c2410c; text-align:left; min-width:14rem;">Paste M1–M12</th>
                            @foreach ($monthLabels as $m => $label)
                                <th style="padding:0.45rem 0.35rem; background:#ffedd5; border:1px solid #fdba74; text-align:center; min-width:3.25rem;">{{ $label }}</th>
                            @endforeach
                            <th style="padding:0.5rem 0.65rem; background:#ffedd5; border:1px solid #fdba74; text-align:center; min-width:5rem;">Total Target</th>
                            <th style="padding:0.5rem 0.65rem; background:#fef3c7; border:1px solid #fcd34d; text-align:center; min-width:5rem;">State FY</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php $lastCategory = null; @endphp
                        @foreach ($grid as $row)
                            @php
                                $d = $row['deliverable'];
                                $categoryKey = ($row['category_serial'] ?? '').'|'.($row['category_name'] ?? '');
                            @endphp
                            @if ($categoryKey !== '|' && $categoryKey !== $lastCategory)
                                @php $lastCategory = $categoryKey; @endphp
                                <tr class="js-category-row">
                                    <td style="padding:0.45rem 0.65rem; border:1px solid #e7d5b8; background:#f5e6c8; font-weight:700; color:#78350f;">
                                        {{ $row['category_serial'] }}
                                    </td>
                                    <td colspan="16" style="padding:0.45rem 0.65rem; border:1px solid #e7d5b8; background:#f5e6c8; font-weight:700; color:#78350f;">
                                        {{ $row['category_name'] }}
                                    </td>
                                </tr>
                            @endif
                            <tr class="js-data-row" data-annual="{{ (int) $row['state_annual'] }}">
                                <td style="padding:0.4rem 0.65rem; border:1px solid #e4e4e7; font-weight:600;">{{ $row['serial'] }}</td>
                                <td style="padding:0.4rem 0.65rem; border:1px solid #e4e4e7; font-weight:500; max-width:18rem;">{{ $d->name }}</td>
                                <td style="padding:0.35rem 0.5rem; border:1px solid #e4e4e7; min-width:14rem;">
                                    <label style="display:block; font-size:0.68rem; font-weight:600; color:#475569; margin-bottom:0.2rem;">Paste M1–M12 targets (tab, space, or comma separated)</label>
                                    <input type="text"
                                        class="js-row-paste-input"
                                        placeholder="87  105  140  70  35  35  70  53  28  32  27  18"
                                        autocomplete="off"
                                        title="Paste M1–M12 targets (tab, space, or comma separated)"
                                        style="width:100%; min-width:11rem; padding:0.3rem 0.4rem; border:1px solid #d4d4d8; border-radius:4px; font-size:0.75rem; font-family:ui-monospace, monospace;">
                                    <div style="display:flex; align-items:center; gap:0.35rem; margin-top:0.25rem;">
                                        <button type="button" class="js-row-paste-apply" style="background:#1d4ed8; color:#fff; border:none; padding:0.28rem 0.45rem; border-radius:4px; font-size:0.7rem; font-weight:600; cursor:pointer;">Apply</button>
                                        <span class="js-row-paste-status" style="font-size:0.68rem; color:#64748b;"></span>
                                    </div>
                                </td>
                                @foreach (range(1, 12) as $m)
                                    <td style="padding:0.25rem; border:1px solid #e4e4e7; text-align:center;">
                                        <input type="number" min="0" step="1"
                                            name="deliverables[{{ $d->id }}][{{ $m }}]"
                                            value="{{ old('deliverables.'.$d->id.'.'.$m, $row['months'][$m] ?? 0) }}"
                                            class="js-month-cell"
                                            style="width:3rem; padding:0.25rem; border:1px solid #d4d4d8; border-radius:4px; text-align:center; font-size:0.78rem;">
                                    </td>
                                @endforeach
                                <td style="padding:0.4rem; border:1px solid #e4e4e7; text-align:center; font-weight:700;" class="js-row-total">{{ number_format($row['row_total']) }}</td>
                                <td style="padding:0.4rem; border:1px solid #e4e4e7; text-align:center; color:#64748b;">
                                    @if ($row['state_annual'] > 0)
                                        {{ number_format($row['state_annual']) }}
                                    @else
                                        <span style="color:#b45309;">Not set</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr style="background:#ffedd5; font-weight:700;">
                            <td colspan="3" style="padding:0.45rem 0.65rem; border:1px solid #fdba74;">Total</td>
                            @foreach (range(1, 12) as $m)
                                <td style="padding:0.4rem; border:1px solid #fdba74; text-align:center;" class="js-col-total" data-month="{{ $m }}">{{ number_format((int) ($columnTotals[$m] ?? 0)) }}</td>
                            @endforeach
                            <td style="padding:0.4rem; border:1px solid #fdba74; text-align:center;" id="footer-grand">{{ number_format((int) ($columnTotals['grand'] ?? 0)) }}</td>
                            <td style="border:1px solid #fdba74;"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <p style="margin-top:1rem;">
                <button type="submit" style="background:#0f766e; color:#fff; border:none; padding:0.6rem 1.1rem; border-radius:8px; font-weight:700; cursor:pointer;">
                    Save state monthly targets
                </button>
                <a href="{{ route('admin.targets.state', ['fiscal_year_id' => $fiscalYearId]) }}" style="margin-left:0.75rem; font-size:0.88rem;">State targets (annual)</a>
            </p>
        </form>
    @endif
@endsection

@if (! empty($grid))
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

                function parsePastedMonths(text) {
                    const parts = String(text || '')
                        .trim()
                        .split(/[\s,\t]+/)
                        .filter(function (part) { return part !== ''; });
                    if (parts.length === 0) {
                        return { ok: false, message: 'Paste 12 numbers for M1–M12.' };
                    }
                    if (parts.length !== 12) {
                        return { ok: false, message: 'Need exactly 12 values (got ' + parts.length + ').' };
                    }
                    const months = {};
                    let annual = 0;
                    for (let i = 0; i < 12; i++) {
                        const value = parseInt(parts[i], 10);
                        if (!Number.isFinite(value) || value < 0) {
                            return { ok: false, message: 'Invalid number at position ' + (i + 1) + '.' };
                        }
                        months[i + 1] = value;
                        annual += value;
                    }
                    return { ok: true, months: months, annual: annual };
                }

                function setRowPasteStatus(tr, message, tone) {
                    const statusEl = tr.querySelector('.js-row-paste-status');
                    if (!statusEl) return;
                    statusEl.textContent = message;
                    statusEl.style.color = tone === 'error' ? '#b91c1c' : (tone === 'ok' ? '#047857' : '#64748b');
                }

                function applyMonthsObjectToRow(tr, months) {
                    tr.querySelectorAll('.js-month-cell').forEach(function (cell, idx) {
                        cell.value = months[idx + 1] ?? 0;
                    });
                    recalc();
                }

                function applyRowPastedMonths(tr) {
                    const pasteInput = tr.querySelector('.js-row-paste-input');
                    if (!pasteInput) return;
                    const parsed = parsePastedMonths(pasteInput.value);
                    if (!parsed.ok) {
                        setRowPasteStatus(tr, parsed.message, 'error');
                        return;
                    }
                    applyMonthsObjectToRow(tr, parsed.months);
                    setRowPasteStatus(tr, 'Applied — ' + fmt(parsed.annual), 'ok');
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

                table.querySelectorAll('.js-data-row').forEach(function (tr) {
                    const applyBtn = tr.querySelector('.js-row-paste-apply');
                    const pasteInput = tr.querySelector('.js-row-paste-input');
                    if (applyBtn) {
                        applyBtn.addEventListener('click', function () {
                            applyRowPastedMonths(tr);
                        });
                    }
                    if (pasteInput) {
                        pasteInput.addEventListener('keydown', function (event) {
                            if (event.key === 'Enter') {
                                event.preventDefault();
                                applyRowPastedMonths(tr);
                            }
                        });
                    }
                });

                recalc();
            })();
        </script>
    @endpush
@endif
