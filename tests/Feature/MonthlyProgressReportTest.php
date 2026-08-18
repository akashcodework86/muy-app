<?php

namespace Tests\Feature;

use App\Models\FiscalYear;
use App\Models\User;
use App\Services\Deliverables\ProgramDeliverablesAchievementBreakdownService;
use App\Services\MediaGalleryService;
use App\Services\ProgramDeliverablesReportService;
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
            ->assertSee('Generate a formatted MPR in one step')
            ->assertSee('Download MPR (Word)')
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
        $fiscalYear = FiscalYear::query()->create([
            'code' => '2026-27',
            'name' => 'FY 2026-27',
            'starts_on' => '2026-04-01',
            'ends_on' => '2027-03-31',
            'is_active' => true,
        ]);
        $rows = [
            ['row_type' => 'pillar', 'serial' => '1', 'name' => 'Mobilization and Outreach'],
            ['row_type' => 'indicator', 'serial' => '1.1', 'name' => 'Call for Application', 'indicator_type' => 'Key Indicator', 'target' => 100, 'achievement' => 90, 'achievement_pct' => 90, 'cumul_target' => 400, 'cumul_achievement' => 350, 'cumul_achievement_pct' => 88],
            ['row_type' => 'indicator', 'serial' => '2.1', 'name' => 'Incubatees Onboarded', 'indicator_type' => 'Key Indicator', 'target' => 50, 'achievement' => 45, 'achievement_pct' => 90, 'cumul_target' => 200, 'cumul_achievement' => 180, 'cumul_achievement_pct' => 90],
        ];

        $reportService = Mockery::mock(ProgramDeliverablesReportService::class);
        $reportService->shouldReceive('build')->once()->andReturn([
            'fiscalYear' => $fiscalYear,
            'rows' => $rows,
            'show_cumulative_columns' => true,
        ]);
        $this->app->instance(ProgramDeliverablesReportService::class, $reportService);

        $breakdown = Mockery::mock(ProgramDeliverablesAchievementBreakdownService::class);
        $breakdown->shouldReceive('build')->twice()->andReturn(['by_district' => []]);
        $this->app->instance(ProgramDeliverablesAchievementBreakdownService::class, $breakdown);

        $gallery = Mockery::mock(MediaGalleryService::class);
        $gallery->shouldReceive('monthlyReportHighlights')->once()->andReturn([]);
        $this->app->instance(MediaGalleryService::class, $gallery);

        $response = $this->actingAs($admin)
            ->get(route('admin.mpr.download', ['report_month' => '2026-07']));

        $response->assertOk();
        $this->assertTrue($response->headers->has('content-disposition'));
        $disposition = (string) $response->headers->get('content-disposition');
        $this->assertStringContainsString('MUY-MPR-July-2026', $disposition);
        $this->assertStringContainsString('.docx', $disposition);

        $contentType = strtolower((string) $response->headers->get('content-type'));
        $this->assertStringContainsString('wordprocessingml.document', $contentType);
    }

    public function test_future_month_is_rejected(): void
    {
        $admin = User::factory()->create(['role' => 'state_admin', 'is_active' => true]);

        $this->actingAs($admin)
            ->from(route('admin.mpr.index'))
            ->get(route('admin.mpr.download', ['report_month' => now()->addMonths(2)->format('Y-m')]))
            ->assertRedirect(route('admin.mpr.index'))
            ->assertSessionHasErrors('report_month');
    }
}
