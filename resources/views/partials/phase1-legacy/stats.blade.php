@php
    $scope = $scopeCounts ?? ['total' => 0, 'onboarded' => 0, 'non_onboarded' => 0];
    $listed = method_exists($rows ?? null, 'total') ? (int) $rows->total() : 0;
    $fyOb = $fyOnboarding ?? null;
@endphp

<div class="p1l-stats">
    <div class="p1l-stat">
        <div class="p1l-stat__label">{{ $totalLabel ?? 'In scope' }}</div>
        <div class="p1l-stat__value">{{ number_format($scope['total']) }}</div>
        <div class="p1l-stat__hint">{{ $totalHint ?? 'Before onboard filter' }}</div>
    </div>
    <div class="p1l-stat p1l-stat--onboard-yes @if ($fyOb) p1l-stat--onboard-fy @endif">
        @if ($fyOb)
            <div class="p1l-stat__label">Onboarded (FY {{ $fyOb['fiscal_year_code'] ?? '—' }})</div>
            <div class="p1l-stat__value">{{ number_format((int) ($fyOb['total'] ?? 0)) }}</div>
            <ul class="p1l-stat__breakdown">
                <li><span class="p1l-stat__phase">Phase 1</span> {{ number_format((int) ($fyOb['phase1'] ?? 0)) }}</li>
                <li><span class="p1l-stat__phase">Phase 2</span> {{ number_format((int) ($fyOb['phase2'] ?? 0)) }}</li>
                <li><span class="p1l-stat__phase">Phase 3</span> {{ number_format((int) ($fyOb['phase3'] ?? 0)) }}</li>
            </ul>
            <div class="p1l-stat__hint">
                Locked hub batches · <code>onboarding_date</code> in FY
                @if ((int) ($scope['onboarded'] ?? 0) > 0)
                    · {{ number_format((int) $scope['onboarded']) }} in list scope
                @endif
            </div>
        @else
            <div class="p1l-stat__label">Onboarded</div>
            <div class="p1l-stat__value">{{ number_format($scope['onboarded']) }}</div>
            <div class="p1l-stat__hint"><code>onboard</code> = yes</div>
        @endif
    </div>
    <div class="p1l-stat p1l-stat--onboard-no">
        <div class="p1l-stat__label">Non onboarded</div>
        <div class="p1l-stat__value">{{ number_format($scope['non_onboarded']) }}</div>
        <div class="p1l-stat__hint">Empty or not yes</div>
    </div>
    <div class="p1l-stat">
        <div class="p1l-stat__label">This list</div>
        <div class="p1l-stat__value">{{ number_format($listed) }}</div>
        <div class="p1l-stat__hint">
            @if (\App\Services\LegacyPhase1\LegacyPhase1ListQuery::hasActiveFilters(request(), $countDistrictParam ?? true))
                After all filters
            @else
                Matches scope total
            @endif
        </div>
    </div>
</div>
