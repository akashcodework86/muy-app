@php
    $deliverableId = (int) ($block['deliverable']->id ?? 0);
    $mapped = (bool) ($block['mapped'] ?? false);
    $verifySaved = (array) ($block['verify_saved'] ?? []);
    $stateSavedTotal = (int) ($block['state_saved_total'] ?? 0);
    $stateSavedMonths = (array) ($block['state_saved_months'] ?? []);
    $hubRows = $block['hub_rows'] ?? [];
@endphp
<div class="district-block" style="margin-bottom:1.5rem; background:#fff; border:1px solid #e4e4e7; border-radius:10px; overflow:hidden;"
    id="district-block-{{ (int) ($block['block_index'] ?? 0) }}"
    data-block="1"
    data-block-label="{{ ($block['mis_serial'] ?? '') !== '' ? $block['mis_serial'].' — ' : '' }}{{ $block['name'] ?? '' }}"
    data-block-serial="{{ (string) ($block['mis_serial'] ?? '') }}"
    data-block-name="{{ (string) ($block['name'] ?? '') }}"
    data-state-total="{{ $stateSavedTotal }}"
    @foreach (range(1, 12) as $m)
        data-state-month-{{ $m }}="{{ (int) ($stateSavedMonths[$m] ?? 0) }}"
    @endforeach
>
    <div style="padding:0.65rem 0.9rem; background:#9a3412; color:#fff; font-weight:700; font-size:0.9rem;">
        @if (! empty($block['excel_sn']))
            {{ $block['excel_sn'] }}.
        @endif
        {{ $block['mis_serial'] ? $block['mis_serial'].' — ' : '' }}{{ $block['name'] }}
        <span style="font-weight:400; opacity:0.85; margin-left:0.5rem;">
            State target (saved): <span class="block-state-total">{{ number_format($stateSavedTotal) }}</span>
            · District allocation: <span class="block-grand-total">0</span>
            · <span class="block-verify-status" style="display:inline-block; padding:0.1rem 0.45rem; border-radius:999px; font-size:0.72rem; font-weight:700; color:{{ $verifySaved['color'] ?? '#64748b' }}; background:{{ $verifySaved['bg'] ?? '#f1f5f9' }};">{{ $verifySaved['label'] ?? '—' }}</span>
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
                        @foreach (range(1, 12) as $m)
                            <td style="padding:0.15rem; border:1px solid #e4e4e7; text-align:center;">
                                <input type="number" min="0" step="1"
                                    value="{{ (int) ($dRow['saved_months'][$m] ?? 0) }}"
                                    data-official="{{ (int) ($dRow['official_months'][$m] ?? 0) }}"
                                    data-scope="district"
                                    @if ($mapped && $deliverableId > 0)
                                        data-deliverable-id="{{ $deliverableId }}"
                                    @else
                                        data-mis-serial="{{ (string) ($block['mis_serial'] ?? '') }}"
                                        data-indicator-name="{{ (string) ($block['name'] ?? '') }}"
                                    @endif
                                    data-district-id="{{ $districtId }}"
                                    data-month="{{ $m }}"
                                    class="month-input"
                                    style="width:2.75rem; padding:0.2rem; text-align:center; border:1px solid #d4d4d8; border-radius:4px; font-size:0.75rem;">
                            </td>
                        @endforeach
                        <td class="row-total" style="padding:0.4rem; border:1px solid #e4e4e7; text-align:center; font-weight:700;">0</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr style="background:#ffedd5; font-weight:700;">
                    <td style="padding:0.4rem 0.65rem; border:1px solid #fdba74;">District allocation</td>
                    @foreach (range(1, 12) as $m)
                        <td class="col-total" data-month="{{ $m }}" style="padding:0.35rem; border:1px solid #fdba74; text-align:center;">0</td>
                    @endforeach
                    <td class="footer-grand" style="padding:0.4rem; border:1px solid #fdba74; text-align:center;">0</td>
                </tr>
                <tr style="background:#dbeafe; font-weight:600;">
                    <td style="padding:0.4rem 0.65rem; border:1px solid #93c5fd;">State target (saved)</td>
                    @foreach (range(1, 12) as $m)
                        <td class="state-month-total" data-month="{{ $m }}" style="padding:0.35rem; border:1px solid #93c5fd; text-align:center;">{{ number_format((int) ($stateSavedMonths[$m] ?? 0)) }}</td>
                    @endforeach
                    <td class="state-grand-total" style="padding:0.4rem; border:1px solid #93c5fd; text-align:center;">{{ number_format($stateSavedTotal) }}</td>
                </tr>
            </tfoot>
        </table>
    </div>

    @if ($hubRows !== [])
        <details class="hub-target-section">
            <summary>
                Hub target distribution
                <span class="hub-target-section__count">({{ count($hubRows) }} hub{{ count($hubRows) === 1 ? '' : 's' }})</span>
            </summary>
            <div class="hub-target-section__body">
                <table class="block-table" style="border-collapse:collapse; font-size:0.78rem; min-width:max-content; width:100%;">
                    <thead>
                        <tr>
                            <th style="padding:0.45rem 0.65rem; background:#dbeafe; border:1px solid #93c5fd; text-align:left; min-width:8rem;">Hub</th>
                            @foreach ($monthLabels as $m => $label)
                                <th style="padding:0.4rem 0.35rem; background:#dbeafe; border:1px solid #93c5fd; text-align:center;">{{ $label }}</th>
                            @endforeach
                            <th style="padding:0.45rem 0.65rem; background:#dbeafe; border:1px solid #93c5fd; text-align:center;">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($hubRows as $hRow)
                            @php $hubId = (int) $hRow['hub']->id; @endphp
                            <tr class="data-row">
                                <td style="padding:0.4rem 0.65rem; border:1px solid #e4e4e7; font-weight:600;">{{ $hRow['hub']->name }}</td>
                                @foreach (range(1, 12) as $m)
                                    <td style="padding:0.15rem; border:1px solid #e4e4e7; text-align:center;">
                                        <input type="number" min="0" step="1"
                                            value="{{ (int) ($hRow['saved_months'][$m] ?? 0) }}"
                                            data-official="{{ (int) ($hRow['official_months'][$m] ?? 0) }}"
                                            data-scope="hub"
                                            @if ($mapped && $deliverableId > 0)
                                                data-deliverable-id="{{ $deliverableId }}"
                                            @else
                                                data-mis-serial="{{ (string) ($block['mis_serial'] ?? '') }}"
                                                data-indicator-name="{{ (string) ($block['name'] ?? '') }}"
                                            @endif
                                            data-hub-id="{{ $hubId }}"
                                            data-month="{{ $m }}"
                                            class="month-input"
                                            style="width:2.75rem; padding:0.2rem; text-align:center; border:1px solid #d4d4d8; border-radius:4px; font-size:0.75rem;">
                                    </td>
                                @endforeach
                                <td class="row-total" style="padding:0.4rem; border:1px solid #e4e4e7; text-align:center; font-weight:700;">0</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </details>
    @endif
</div>
