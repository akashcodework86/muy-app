<?php

namespace Tests\Unit;

use App\Models\FiscalYear;
use App\Services\Deliverables\ProgramDeliverablesFilter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProgramDeliverablesFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_form_dates_derives_range_when_month_selected(): void
    {
        $fy = FiscalYear::query()->create([
            'code' => '2026-27',
            'name' => 'FY 2026-27',
            'starts_on' => '2026-04-02',
            'ends_on' => '2027-04-01',
            'is_active' => true,
        ]);

        $filter = new ProgramDeliverablesFilter($fy->id, null, 4, null, null, null);
        $dates = $filter->formDates($fy);

        $this->assertSame(2026, $dates['year']);
        $this->assertSame('2026-04-02', $dates['dateFrom']);
        $this->assertSame('2026-04-30', $dates['dateTo']);
    }

    public function test_with_derived_dates_fills_query_params(): void
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
}
