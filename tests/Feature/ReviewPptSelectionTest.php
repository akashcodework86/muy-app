<?php

namespace Tests\Feature;

use App\Models\FiscalYear;
use App\Services\ReviewPpt\ReviewPptSelection;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Tests\TestCase;

class ReviewPptSelectionTest extends TestCase
{
    public function createApplication()
    {
        $app = require dirname(__DIR__, 2).'/bootstrap/app.php';
        $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

        return $app;
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_custom_selected_period_and_single_district(): void
    {
        Carbon::setTestNow('2026-09-15 12:00:00');
        $fy = new FiscalYear(['code' => '2026-27', 'starts_on' => '2026-04-02', 'ends_on' => '2027-04-01']);
        $selection = ReviewPptSelection::fromRequest(Request::create('/review', 'GET', [
            'period_kind' => 'custom', 'date_from' => '2026-08-21', 'date_to' => '2026-09-14',
            'count_mode' => 'period', 'district_scope' => 'almora',
        ]), $fy);

        $this->assertSame('2026-08-21', $selection->achievementFrom->toDateString());
        $this->assertSame('2026-09-14', $selection->periodTo->toDateString());
        $this->assertSame([5, 6], [$selection->targetFromMonth, $selection->targetToMonth]);
        $this->assertSame(['almora'], $selection->districtSlugs);
    }

    public function test_month_fy_cumulative_uses_fy_start_and_targets_through_month(): void
    {
        Carbon::setTestNow('2026-09-15 12:00:00');
        $fy = new FiscalYear(['code' => '2026-27', 'starts_on' => '2026-04-02', 'ends_on' => '2027-04-01']);
        $selection = ReviewPptSelection::fromRequest(Request::create('/review', 'GET', [
            'period_kind' => 'month', 'report_month' => '2026-08',
            'count_mode' => 'cumulative', 'district_scope' => 'kumaon',
        ]), $fy);

        $this->assertSame('2026-04-02', $selection->achievementFrom->toDateString());
        $this->assertSame('2026-08-31', $selection->periodTo->toDateString());
        $this->assertSame([1, 5], [$selection->targetFromMonth, $selection->targetToMonth]);
        $this->assertCount(6, $selection->districtSlugs);
    }
}
