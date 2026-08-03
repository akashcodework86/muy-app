<?php

namespace Tests\Feature;

use App\Models\FiscalYear;
use App\Services\Deliverables\ProgramDeliverablesFilter;
use Tests\TestCase;

class DeliverablesPrintViewTest extends TestCase
{
    public function test_print_page_combines_period_and_cumulative_metrics_without_level_column(): void
    {
        $html = $this->renderPrintPage(true);

        $this->assertStringContainsString('Monthly progress report for the month of - July 2026', $html);
        $this->assertStringContainsString('Monthly / selected-period and cumulative progress', $html);
        $this->assertStringContainsString('1,820', $html);
        $this->assertStringContainsString('2,413', $html);
        $this->assertStringContainsString('133%', $html);
        $this->assertStringContainsString('17,010', $html);
        $this->assertStringContainsString('21,407', $html);
        $this->assertStringContainsString('126%', $html);
        $this->assertStringNotContainsString('Spoke/ Hub/ State', $html);
        $this->assertStringContainsString('Page 1 of 4', $html);
        $this->assertSame(9, preg_match_all('/<th(?:\s|>)/', $html));
    }

    public function test_print_page_omits_cumulative_columns_when_report_has_no_cumulative_data(): void
    {
        $html = $this->renderPrintPage(false);

        $this->assertStringContainsString('2,413', $html);
        $this->assertStringNotContainsString('21,407', $html);
        $this->assertSame(6, preg_match_all('/<th(?:\s|>)/', $html));
    }

    private function renderPrintPage(bool $showCumulativeColumns): string
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
            'printSectionTitle' => 'Monthly / selected-period and cumulative progress',
            'printPageNumber' => 1,
            'printTotalPages' => 4,
            'showCumulativeColumns' => $showCumulativeColumns,
            'cumulativeThroughLabel' => 'till Jul 2026',
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
