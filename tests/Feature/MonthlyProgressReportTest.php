<?php

namespace Tests\Feature;

use App\Models\FiscalYear;
use App\Models\User;
use App\Services\Reports\ProgressReportContext;
use App\Services\Reports\ProgressReportDataService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class MonthlyProgressReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_state_admin_can_open_mpr_generator_page(): void
    {
        $admin = User::factory()->create(['role' => 'state_admin', 'is_active' => true]);

        $this->actingAs($admin)
            ->get(route('admin.mpr.index'))
            ->assertOk()
            ->assertSee('Generate MPR or QPR from MIS')
            ->assertSee('Download Word report')
            ->assertSee('[TEAM:')
            ->assertSee(route('admin.mpr.index'), false);
    }

    public function test_non_state_admin_cannot_open_mpr_generator_page(): void
    {
        $staff = User::factory()->create(['role' => 'district_staff', 'is_active' => true]);

        $this->actingAs($staff)
            ->get(route('admin.mpr.index'))
            ->assertForbidden();
    }

    public function test_state_admin_can_download_a_word_mpr_for_one_month(): void
    {
        $admin = User::factory()->create(['role' => 'state_admin', 'is_active' => true]);
        FiscalYear::query()->create([
            'code' => '2026-27',
            'name' => 'FY 2026-27',
            'starts_on' => '2026-04-01',
            'ends_on' => '2027-03-31',
            'is_active' => true,
        ]);

        $context = new ProgressReportContext(
            reportType: 'mpr',
            periodFrom: Carbon::parse('2026-07-01'),
            periodTo: Carbon::parse('2026-07-31'),
            periodLabel: 'July 2026',
            reportKindLabel: 'Monthly Progress Report',
            filePrefix: 'MUY-MPR',
            headerMonth: Carbon::parse('2026-07-01'),
            fiscalYearLabel: 'FY 2026-27',
            rows: [
                ['row_type' => 'indicator', 'serial' => '1.1', 'name' => 'Call for Application', 'indicator_type' => 'Key Indicator', 'target' => 100, 'achievement' => 90, 'achievement_pct' => 90],
            ],
            districtRows: [],
            photos: [],
            photosBySection: [],
            breakdowns: [],
            teamRoster: [],
        );

        $reportData = Mockery::mock(ProgressReportDataService::class);
        $reportData->shouldReceive('buildMonthly')->once()->andReturn($context);
        $this->app->instance(ProgressReportDataService::class, $reportData);

        $response = $this->actingAs($admin)
            ->get(route('admin.mpr.download', [
                'report_type' => 'mpr',
                'report_month' => '2026-07',
            ]));

        $response->assertOk();
        $this->assertTrue($response->headers->has('content-disposition'));
        $disposition = (string) $response->headers->get('content-disposition');
        $this->assertStringContainsString('MUY-MPR-July-2026', $disposition);

        $contentType = strtolower((string) $response->headers->get('content-type'));
        $this->assertTrue(
            str_contains($contentType, 'wordprocessingml.document')
            || str_contains($contentType, 'msword'),
            'Expected a Word document download.',
        );
    }

    public function test_state_admin_can_download_a_word_qpr_for_one_quarter(): void
    {
        $admin = User::factory()->create(['role' => 'state_admin', 'is_active' => true]);
        $fiscalYear = FiscalYear::query()->create([
            'code' => '2026-27',
            'name' => 'FY 2026-27',
            'starts_on' => '2026-04-01',
            'ends_on' => '2027-03-31',
            'is_active' => true,
        ]);

        $context = new ProgressReportContext(
            reportType: 'qpr',
            periodFrom: Carbon::parse('2026-04-01'),
            periodTo: Carbon::parse('2026-06-30'),
            periodLabel: 'Apr–Jun 2026',
            reportKindLabel: 'Quarterly Progress Report',
            filePrefix: 'MUY-QPR',
            headerMonth: Carbon::parse('2026-06-01'),
            fiscalYearLabel: 'FY 2026-27',
            rows: [],
            districtRows: [],
            photos: [],
            photosBySection: [],
            breakdowns: [],
            teamRoster: [],
        );

        $reportData = Mockery::mock(ProgressReportDataService::class);
        $reportData->shouldReceive('buildQuarterly')->once()->withArgs(function ($user, $fy, $quarter) use ($admin, $fiscalYear): bool {
            return $user->is($admin) && $fy->is($fiscalYear) && $quarter === 1;
        })->andReturn($context);
        $this->app->instance(ProgressReportDataService::class, $reportData);

        $response = $this->actingAs($admin)
            ->get(route('admin.mpr.download', [
                'report_type' => 'qpr',
                'report_month' => '2026-06',
                'report_quarter' => 1,
                'fiscal_year_id' => $fiscalYear->id,
            ]));

        $response->assertOk();
        $disposition = (string) $response->headers->get('content-disposition');
        $this->assertStringContainsString('MUY-QPR', $disposition);
    }

    public function test_future_month_is_rejected(): void
    {
        $admin = User::factory()->create(['role' => 'state_admin', 'is_active' => true]);

        $this->actingAs($admin)
            ->from(route('admin.mpr.index'))
            ->get(route('admin.mpr.download', [
                'report_type' => 'mpr',
                'report_month' => now()->addMonths(2)->format('Y-m'),
            ]))
            ->assertRedirect(route('admin.mpr.index'))
            ->assertSessionHasErrors('report_month');
    }
}
