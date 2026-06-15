@php
    $stageColorMap = ['EARLY' => '#ff9500', 'SEED' => '#d97706', 'GROWTH' => '#26a69a'];
    $stageLabelMap = ['EARLY' => 'Early', 'SEED' => 'Seed', 'GROWTH' => 'Growth'];
    $stageTotals = $stageTotals ?? [];
    $stagePct = $stagePct ?? [];
@endphp
<div class="cg-hero-stage-rows">
    @foreach (['EARLY', 'SEED', 'GROWTH'] as $sk)
        @php
            $skCount = (int) ($stageTotals[$sk] ?? 0);
            $skPct = (int) ($stagePct[$sk] ?? 0);
            $skColor = $stageColorMap[$sk];
        @endphp
        <div class="cg-hero-stage-row">
            <span class="cg-hero-stage-row__label">{{ $stageLabelMap[$sk] }}</span>
            <div class="cg-hero-stage-row__track">
                <div class="cg-hero-stage-row__fill" style="background:{{ $skColor }};width:{{ $skPct }}%;"></div>
            </div>
            <span class="cg-hero-stage-row__count">{{ number_format($skCount) }}</span>
            <span class="cg-hero-stage-row__pct">{{ $skPct }}%</span>
        </div>
    @endforeach
</div>
