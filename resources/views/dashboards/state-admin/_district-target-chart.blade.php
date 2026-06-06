@php
    $dt = $insights['districtTargetComparison'] ?? ['labels' => [], 'achieved' => [], 'targets' => [], 'periods' => [], 'default_key' => 'fy'];
    $dtPeriods = $dt['periods'] ?? [];
    if ($dtPeriods === [] && ($dt['labels'] ?? []) !== []) {
        $dtPeriods = [[
            'key' => 'fy',
            'label' => 'Full FY',
            'subtitle' => 'Cumulative CFA achieved vs annual district target',
            'achieved' => $dt['achieved'] ?? [],
            'targets' => $dt['targets'] ?? [],
        ]];
    }
    $dtDefaultKey = (string) ($dt['default_key'] ?? 'fy');
    $dtDefaultPeriod = collect($dtPeriods)->firstWhere('key', $dtDefaultKey) ?? ($dtPeriods[0] ?? null);
@endphp
<div class="sad-card sad-district-target-card">
    <div class="sad-card__head">
        <h2 class="sad-card__title"><i class="fa-solid fa-bullseye" aria-hidden="true"></i> District CFA vs target</h2>
    </div>
    @if ($dtPeriods !== [])
        <div class="sad-dt-tabs" role="tablist" aria-label="Target comparison period">
            @foreach ($dtPeriods as $period)
                @php
                    $pKey = (string) ($period['key'] ?? '');
                    $isActive = $pKey === $dtDefaultKey;
                @endphp
                <button
                    type="button"
                    class="sad-dt-tab @if ($pKey === 'fy') sad-dt-tab--fy @endif @if ($isActive) is-active @endif"
                    role="tab"
                    aria-selected="{{ $isActive ? 'true' : 'false' }}"
                    data-sad-dt-tab="{{ $pKey }}"
                >{{ $period['label'] ?? $pKey }}</button>
            @endforeach
        </div>
        <p class="sad-dt-hint" data-sad-dt-hint>{{ $dtDefaultPeriod['subtitle'] ?? 'Achieved vs district CFA target' }}</p>
    @else
        <p class="sad-card__hint">No district target data for this scope</p>
    @endif
    <div class="sad-chart-box sad-chart-box--tall">
        <canvas id="chartDistrictTarget" aria-label="District CFA achieved vs target"></canvas>
    </div>
</div>
