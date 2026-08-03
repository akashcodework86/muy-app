@php
    $isCumulativePrintSection = $printMetric === 'cumulative';
    $printTargetKey = $isCumulativePrintSection ? 'cumul_target' : 'target';
    $printTargetLabelKey = $isCumulativePrintSection ? 'cumul_target_label' : 'target_label';
    $printAchievementKey = $isCumulativePrintSection ? 'cumul_achievement' : 'achievement';
    $printPercentageKey = $isCumulativePrintSection ? 'cumul_achievement_pct' : 'achievement_pct';
    $printToneKey = $isCumulativePrintSection ? 'cumul_performance_tone' : 'performance_tone';
@endphp

<article class="dlv-print-page">
    <header class="dlv-print-header">
        <img src="{{ asset('images/muy.jpg') }}" alt="MUY Logo" class="dlv-print-header__logo">
        <div class="dlv-print-header__content">
            <div class="dlv-print-header__scheme">Mukhyamantri Udyamshala Yojana</div>
            <h1>Monthly progress report for the month of - {{ $monthlyProgressMonthLabel }}</h1>
        </div>
    </header>

    <section class="dlv-print-summary">
        <span><strong>Fiscal year:</strong> {{ $fiscalYear?->name ?? '—' }}</span>
        <span><strong>Scope:</strong> {{ $screenshotScopeLabel }}</span>
        <span><strong>Period:</strong> {{ $screenshotPeriodLabel }}</span>
        <span><strong>Indicator type:</strong> {{ $filter->indicatorType ?: 'All types' }}</span>
        <span><strong>Level:</strong> {{ $filter->level ?: 'All levels' }}</span>
        <span><strong>Generated:</strong> {{ now()->format('d M Y, H:i') }}</span>
    </section>

    <h2 class="dlv-print-section-title">{{ $printSectionTitle }}</h2>

    <table class="dlv-print-table">
        <colgroup>
            <col class="dlv-print-col-serial">
            <col class="dlv-print-col-indicator">
            <col class="dlv-print-col-type">
            <col class="dlv-print-col-level">
            <col class="dlv-print-col-metric">
            <col class="dlv-print-col-metric">
            <col class="dlv-print-col-metric">
        </colgroup>
        <thead>
            <tr>
                <th>S.N.</th>
                <th class="dlv-print-text-left">Indicator</th>
                <th>Type of Indicator</th>
                <th>Spoke/ Hub/ State</th>
                <th>Target</th>
                <th>Achievement</th>
                <th>Achievement (%)</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($printPageRows as $printRow)
                @php
                    $printIsHeading = in_array($printRow['row_type'], ['pillar', 'subcategory'], true);
                    $printPercentage = $printRow[$printPercentageKey] ?? null;
                    $printTone = $printRow[$printToneKey] ?? 'critical';
                @endphp
                <tr @class(['dlv-print-section-row' => $printIsHeading])>
                    <td>{{ $printRow['serial'] }}</td>
                    <td class="dlv-print-text-left">{{ $printRow['name'] }}</td>
                    <td>{{ $printIsHeading ? '' : ($printRow['indicator_type'] ?: '—') }}</td>
                    <td>{{ $printIsHeading ? '' : ($printRow['level'] ?: '—') }}</td>
                    <td>
                        @if (! $printIsHeading && ($printRow[$printTargetLabelKey] ?? null))
                            <span class="dlv-print-target-label">{{ $printRow[$printTargetLabelKey] }}</span>
                        @elseif (! $printIsHeading && ($printRow[$printTargetKey] ?? null) !== null)
                            {{ number_format($printRow[$printTargetKey]) }}
                        @endif
                    </td>
                    <td>
                        @if (! $printIsHeading && ($printRow[$printAchievementKey] ?? null) !== null)
                            <strong>{{ number_format((int) $printRow[$printAchievementKey]) }}</strong>
                        @endif
                    </td>
                    <td>
                        @if (! $printIsHeading && $printPercentage !== null)
                            <span class="dlv-pct-badge dlv-pct-badge--{{ $printTone }}">{{ $printPercentage }}%</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="dlv-print-empty">No data for this scope.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <footer class="dlv-print-footer">
        <span>{{ $printSectionTitle }}</span>
        <span>Page {{ $printPageNumber }} of {{ $printTotalPages }}</span>
    </footer>
</article>
