<?php

namespace Tests\Unit;

use App\Models\FiscalYear;
use App\Services\Deliverables\ProgramDeliverablesFilter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProgramDeliverablesFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_month_filter_uses_first_to_last_day_of_calendar_month(): void
    {
        $fy = FiscalYear::query()->create([
            'code' => '2026-27',
            'name' => 'FY 2026-27',
            'starts_on' => '2026-04-02',
            'ends_on' => '2027-04-01',
            'is_active' => true,
        ]);

        $filter = new ProgramDeliverablesFilter($fy->id, null, 4, 2026, null, null);
        $dates = $filter->formDates($fy);

        $this->assertSame(2026, $dates['year']);
        $this->assertSame('2026-04-01', $dates['dateFrom']);
        $this->assertSame('2026-04-30', $dates['dateTo']);
    }

    public function test_with_derived_dates_fills_query_params_for_month(): void
    {
        $fy = FiscalYear::query()->create([
            'code' => '2026-27',
            'name' => 'FY 2026-27',
            'starts_on' => '2026-04-01',
            'ends_on' => '2027-03-31',
            'is_active' => true,
        ]);

        $filter = new ProgramDeliverablesFilter($fy->id, null, 5, null, null, null);
        $derived = $filter->withDerivedDates($fy);

        $this->assertSame(2026, $derived->year);
        $this->assertSame('2026-05-01', $derived->dateFrom);
        $this->assertSame('2026-05-31', $derived->dateTo);
        $this->assertSame([
            'fiscal_year_id' => $fy->id,
            'month' => 5,
            'year' => 2026,
            'date_from' => '2026-05-01',
            'date_to' => '2026-05-31',
        ], $derived->queryParams());
    }

    public function test_quarter_filter_uses_three_full_calendar_months(): void
    {
        $fy = FiscalYear::query()->create([
            'code' => '2026-27',
            'name' => 'FY 2026-27',
            'starts_on' => '2026-04-02',
            'ends_on' => '2027-04-01',
            'is_active' => true,
        ]);

        $filter = new ProgramDeliverablesFilter($fy->id, null, null, null, null, null, 1);
        $derived = $filter->withDerivedDates($fy);

        $this->assertSame('2026-04-01', $derived->dateFrom);
        $this->assertSame('2026-06-30', $derived->dateTo);
        $this->assertSame([1, 2, 3], $fy->fiscalMonthNumbersForQuarter(1));
    }

    public function test_legacy_date_range_snaps_to_whole_months(): void
    {
        $fy = FiscalYear::query()->create([
            'code' => '2026-27',
            'name' => 'FY 2026-27',
            'starts_on' => '2026-04-02',
            'ends_on' => '2027-04-01',
            'is_active' => true,
        ]);

        $filter = new ProgramDeliverablesFilter($fy->id, null, null, null, '2026-04-15', '2026-06-20');
        [$from, $to] = $filter->resolvePeriod($fy);

        $this->assertSame('2026-04-01', $from?->toDateString());
        $this->assertSame('2026-06-30', $to?->toDateString());
    }
}
