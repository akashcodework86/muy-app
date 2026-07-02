@php
    use App\Support\ReapIncubateeTargets;

    $progress = $reapTargetsProgress ?? null;
    $interactive = (bool) ($reapTargetsInteractive ?? true);
    $compact = (bool) ($reapTargetsCompact ?? false);

    if (! is_array($progress) || empty($progress['buckets'])) {
        return;
    }

    $districtName = (string) ($progress['district']['name'] ?? 'District');
    $fyName = (string) ($progress['fiscal_year']['name'] ?? 'FY');
    $totals = (array) ($progress['totals'] ?? []);
    $weakest = (string) ($progress['weakest_bucket'] ?? '');
@endphp
<div
    class="reap-targets-panel"
    data-reap-targets-panel="1"
    @if ($interactive) data-reap-targets-interactive="1" @endif
    style="margin:0 0 0.75rem;padding:0.6rem 0.65rem;border:1px solid #fdba74;border-radius:8px;background:#fff;border-left:4px solid #ea580c;"
>
    <div style="display:flex;justify-content:space-between;gap:0.5rem;align-items:flex-start;flex-wrap:wrap;margin-bottom:0.45rem;">
        <div>
            <div style="font-size:0.78rem;font-weight:700;color:#9a3412;letter-spacing:0.02em;">
                District REAP targets · MIS 8.2
            </div>
            <div style="font-size:0.72rem;color:#7c2d12;margin-top:0.1rem;">
                {{ $districtName }} · {{ $fyName }}
            </div>
        </div>
        <div style="font-size:0.72rem;color:#7c2d12;text-align:right;">
            Total <strong>{{ (int) ($totals['approved'] ?? 0) }}/{{ (int) ($totals['target'] ?? 0) }}</strong>
            @if (($totals['remaining'] ?? 0) > 0)
                <span style="display:block;color:#9a3412;">{{ (int) $totals['remaining'] }} remaining</span>
            @endif
        </div>
    </div>

    <table style="width:100%;border-collapse:collapse;font-size:{{ $compact ? '0.72rem' : '0.76rem' }};">
        <thead>
            <tr>
                <th style="text-align:left;padding:0.25rem 0.35rem;color:#7c2d12;font-weight:600;"></th>
                <th style="text-align:center;padding:0.25rem 0.35rem;color:#7c2d12;font-weight:600;">1 Lakh</th>
                <th style="text-align:center;padding:0.25rem 0.35rem;color:#7c2d12;font-weight:600;">3 Lakh</th>
            </tr>
        </thead>
        <tbody>
            @foreach (['Farm' => [ReapIncubateeTargets::BUCKET_FARM_1_LAKH, ReapIncubateeTargets::BUCKET_FARM_3_LAKH], 'Non-farm' => [ReapIncubateeTargets::BUCKET_NON_FARM_1_LAKH, ReapIncubateeTargets::BUCKET_NON_FARM_3_LAKH]] as $rowLabel => $bucketPair)
                <tr>
                    <td style="padding:0.3rem 0.35rem;font-weight:600;color:#7c2d12;white-space:nowrap;">{{ $rowLabel }}</td>
                    @foreach ($bucketPair as $bucketKey)
                        @php
                            $cell = (array) ($progress['buckets'][$bucketKey] ?? []);
                            $approved = (int) ($cell['approved'] ?? 0);
                            $target = (int) ($cell['target'] ?? 0);
                            $pct = $target > 0 ? min(100, (int) round(($approved / $target) * 100)) : 0;
                            $isWeakest = $bucketKey === $weakest && $target > 0 && $approved < $target;
                            $isComplete = $target > 0 && $approved >= $target;
                            $bg = $isComplete ? '#dcfce7' : ($isWeakest ? '#ffedd5' : '#fff7ed');
                            $border = $isComplete ? '#86efac' : ($isWeakest ? '#fdba74' : '#fed7aa');
                        @endphp
                        <td
                            style="padding:0.2rem;text-align:center;"
                            data-reap-target-cell="1"
                            data-reap-sector="{{ $rowLabel === 'Farm' ? 'farm' : 'non_farm' }}"
                            data-reap-amount="{{ str_contains($bucketKey, '1_lakh') ? '1_lakh' : '3_lakh' }}"
                        >
                            <div
                                class="reap-target-cell"
                                style="padding:0.28rem 0.35rem;border:1px solid {{ $border }};border-radius:6px;background:{{ $bg }};font-weight:700;color:#7c2d12;"
                            >
                                {{ $approved }}/{{ $target }}
                            </div>
                            @if ($target > 0)
                                <div style="margin-top:0.15rem;height:4px;border-radius:999px;background:#fde68a;overflow:hidden;">
                                    <div style="width:{{ $pct }}%;height:100%;background:{{ $isComplete ? '#16a34a' : '#ea580c' }};"></div>
                                </div>
                            @endif
                        </td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>

    <p
        class="reap-targets-selection-hint"
        data-reap-targets-hint="1"
        style="display:none;margin:0.45rem 0 0;font-size:0.72rem;color:#9a3412;"
    ></p>
</div>
