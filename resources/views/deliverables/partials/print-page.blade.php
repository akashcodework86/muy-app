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
            @foreach (range(1, $showCumulativeColumns ? 6 : 3) as $printMetricColumn)
                <col class="dlv-print-col-metric">
            @endforeach
        </colgroup>
        <thead>
            <tr>
                <th>S.N.</th>
                <th class="dlv-print-text-left">Indicator</th>
                <th>Type of Indicator</th>
                <th>Target<span class="dlv-print-th-subtitle">(period)</span></th>
                <th>Achievement<span class="dlv-print-th-subtitle">(period)</span></th>
                <th>Achievement (%)<span class="dlv-print-th-subtitle">(period)</span></th>
                @if ($showCumulativeColumns)
                    <th class="dlv-print-cumulative-th">Target<span class="dlv-print-th-subtitle">({{ $cumulativeThroughLabel }})</span></th>
                    <th class="dlv-print-cumulative-th">Achievement<span class="dlv-print-th-subtitle">({{ $cumulativeThroughLabel }})</span></th>
                    <th class="dlv-print-cumulative-th">Achievement (%)<span class="dlv-print-th-subtitle">({{ $cumulativeThroughLabel }})</span></th>
                @endif
            </tr>
        </thead>
        <tbody>
            @forelse ($printPageRows as $printRow)
                @php
                    $printIsHeading = in_array($printRow['row_type'], ['pillar', 'subcategory'], true);
                    $printPercentage = $printRow['achievement_pct'] ?? null;
                    $printTone = $printRow['performance_tone'] ?? 'critical';
                    $printCumulativePercentage = $printRow['cumul_achievement_pct'] ?? null;
                    $printCumulativeTone = $printRow['cumul_performance_tone'] ?? 'critical';
                @endphp
                <tr @class(['dlv-print-section-row' => $printIsHeading])>
                    <td>{{ $printRow['serial'] }}</td>
                    <td class="dlv-print-text-left">{{ $printRow['name'] }}</td>
                    <td>{{ $printIsHeading ? '' : ($printRow['indicator_type'] ?: '—') }}</td>
                    <td>
                        @if (! $printIsHeading && ($printRow['target_label'] ?? null))
                            <span class="dlv-print-target-label">{{ $printRow['target_label'] }}</span>
                        @elseif (! $printIsHeading && ($printRow['target'] ?? null) !== null)
                            {{ number_format($printRow['target']) }}
                        @endif
                    </td>
                    <td>
                        @if (! $printIsHeading && ($printRow['achievement'] ?? null) !== null)
                            <strong>{{ number_format((int) $printRow['achievement']) }}</strong>
                        @endif
                    </td>
                    <td>
                        @if (! $printIsHeading && $printPercentage !== null)
                            <span class="dlv-pct-badge dlv-pct-badge--{{ $printTone }}">{{ $printPercentage }}%</span>
                        @endif
                    </td>
                    @if ($showCumulativeColumns)
                        <td class="dlv-print-cumulative-cell">
                            @if (! $printIsHeading && ($printRow['cumul_target_label'] ?? null))
                                <span class="dlv-print-target-label">{{ $printRow['cumul_target_label'] }}</span>
                            @elseif (! $printIsHeading && ($printRow['cumul_target'] ?? null) !== null)
                                {{ number_format($printRow['cumul_target']) }}
                            @endif
                        </td>
                        <td class="dlv-print-cumulative-cell">
                            @if (! $printIsHeading && ($printRow['cumul_achievement'] ?? null) !== null)
                                <strong>{{ number_format((int) $printRow['cumul_achievement']) }}</strong>
                            @endif
                        </td>
                        <td class="dlv-print-cumulative-cell">
                            @if (! $printIsHeading && $printCumulativePercentage !== null)
                                <span class="dlv-pct-badge dlv-pct-badge--{{ $printCumulativeTone }}">{{ $printCumulativePercentage }}%</span>
                            @endif
                        </td>
                    @endif
                </tr>
            @empty
                <tr>
                    <td colspan="{{ $showCumulativeColumns ? 9 : 6 }}" class="dlv-print-empty">No data for this scope.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <footer class="dlv-print-footer">
        <span>{{ $printSectionTitle }}</span>
        <span>Page {{ $printPageNumber }} of {{ $printTotalPages }}</span>
    </footer>
</article>
