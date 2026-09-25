<?php

namespace Tests\Unit\Support;

use App\Models\FiscalYear;
use App\Support\FyPaceChartBuilder;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FyPaceChartBuilderTest extends TestCase
{
    use RefreshDatabase;

    public function test_build_includes_daily_series_from_trends(): void
    {
        FiscalYear::query()->create([
            'code' => 'FY2026-27',
            'name' => 'FY 2026-27',
            'starts_on' => '2026-04-01',
            'ends_on' => '2027-03-31',
            'is_active' => true,
            'is_phase3_default' => true,
        ]);

        $fy = FiscalYear::query()->where('code', 'FY2026-27')->first();
        $builder = app(FyPaceChartBuilder::class);
        $floor = Carbon::create(2026, 4, 1)->startOfDay();

        $chart = $builder->build(
            $fy,
            $floor,
            (int) $fy->id,
            1000,
            100,
            ['labels' => ['01 Apr', '02 Apr'], 'values' => [3, 5]],
            ['labels' => ['01 Apr', '02 Apr'], 'values' => [1, 0]],
        );

        $this->assertSame(['01 Apr', '02 Apr'], $chart['daily']['labels']);
        $this->assertSame([3, 5], $chart['daily']['cfa']);
        $this->assertSame([1, 0], $chart['daily']['onboarding']);
        $this->assertNotEmpty($chart['labels']);
        $this->assertSame(1000, $chart['cfa_target']);
    }
}
