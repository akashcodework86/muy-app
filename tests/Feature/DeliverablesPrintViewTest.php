<?php

namespace Tests\Feature;

use App\Models\FiscalYear;
use App\Services\Deliverables\ProgramDeliverablesFilter;
use Tests\TestCase;

class DeliverablesPrintViewTest extends TestCase
{
    public function test_print_page_renders_period_metrics_in_readable_seven_column_table(): void
    {
        $html = $this->renderPrintPage('period');

        $this->assertStringContainsString('Monthly progress report for the month of - July 2026', $html);
        $this->assertStringContainsString('Monthly / selected-period progress', $html);
        $this->assertStringContainsString('1,820', $html);
        $this->assertStringContainsString('2,413', $html);
        $this->assertStringContainsString('133%', $html);
        $this->assertStringContainsString('Page 1 of 4', $html);
        $this->assertSame(7, preg_match_all('/<th(?:\s|>)/', $html));
    }

    public function test_print_page_renders_cumulative_metrics_separately(): void
    {
        $html = $this->renderPrintPage('cumulative');

        $this->assertStringContainsString('Cumulative progress through till Jul 2026', $html);
        $this->assertStringContainsString('17,010', $html);
        $this->assertStringContainsString('21,407', $html);
        $this->assertStringContainsString('126%', $html);
        $this->assertStringNotContainsString('2,413', $html);
    }

    private function renderPrintPage(string $metric): string
    {
        $filter = new ProgramDeliverablesFilter(
            fiscalYearId: 1,
            districtId: null,
            month: 7,
            year: 2026,
            dateFrom: '2026-07-01',
            dateTo: '2026-07-31',
        );

        $fiscalYear = new FiscalYear(['name' => 'FY 2026-27']);

        return view('deliverables.partials.print-page', [
            'printMetric' => $metric,
            'printSectionTitle' => $metric === 'period'
                ? 'Monthly / selected-period progress'
                : 'Cumulative progress through till Jul 2026',
            'printPageNumber' => 1,
            'printTotalPages' => 4,
            'printPageRows' => [[
                'row_type' => 'indicator',
                'serial' => '1.1',
                'name' => 'Call for Application',
                'indicator_type' => 'Key Indicator',
                'level' => 'Spoke & Hub',
                'target' => 1820,
                'target_label' => null,
                'achievement' => 2413,
                'achievement_pct' => 133,
                'performance_tone' => 'good',
                'cumul_target' => 17010,
                'cumul_target_label' => null,
                'cumul_achievement' => 21407,
                'cumul_achievement_pct' => 126,
                'cumul_performance_tone' => 'good',
            ]],
            'monthlyProgressMonthLabel' => 'July 2026',
            'fiscalYear' => $fiscalYear,
            'screenshotScopeLabel' => 'All districts (state)',
            'screenshotPeriodLabel' => '01 Jul 2026 - 31 Jul 2026',
            'filter' => $filter,
        ])->render();
    }
}
