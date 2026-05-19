<?php

namespace Tests\Feature;

use App\Models\Deliverable;
use App\Models\District;
use App\Models\FiscalYear;
use App\Models\Hub;
use App\Models\Service;
use App\Models\ServiceCase;
use App\Models\ServiceCategory;
use App\Models\StateDeliverableTarget;
use App\Models\User;
use App\Services\ProgramDeliverablesReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DeliverablesReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_deliverables_page_renders_for_admin(): void
    {
        $admin = User::factory()->create(['role' => 'state_admin', 'is_active' => true]);

        $this->actingAs($admin)
            ->get(route('admin.deliverables.index'))
            ->assertOk()
            ->assertSee('Deliverables');
    }

    public function test_report_builds_category_and_service_rows_with_targets_and_achievements(): void
    {
        $fy = FiscalYear::query()->firstOrCreate(
            ['code' => '2025-26'],
            [
                'name' => 'FY 2025-26',
                'starts_on' => '2025-04-01',
                'ends_on' => '2026-03-31',
                'is_active' => true,
            ]
        );

        $root = ServiceCategory::query()->create([
            'slug' => 'test_root',
            'name' => 'Business Formalisation',
            'sort_order' => 1,
            'target_mode' => ServiceCategory::TARGET_MODE_SERVICE,
        ]);

        $child = ServiceCategory::query()->create([
            'parent_id' => $root->id,
            'slug' => 'test_root_services',
            'name' => 'Services',
            'sort_order' => 0,
        ]);

        $deliverable = Deliverable::query()->create([
            'sort_order' => 50,
            'code' => 'test_udyam',
            'name' => 'Udyam',
            'mis_entry_label' => 'Udyam',
            'is_active' => true,
        ]);

        $service = Service::query()->create([
            'service_category_id' => $child->id,
            'deliverable_id' => $deliverable->id,
            'code' => 'udyam_test',
            'name' => 'Udyam Registration',
            'sort_order' => 1,
            'is_active' => true,
            'reporting_tier' => Service::REPORTING_KEY,
        ]);

        StateDeliverableTarget::query()->create([
            'fiscal_year_id' => $fy->id,
            'deliverable_id' => $deliverable->id,
            'target_total' => 100,
        ]);

        $hub = Hub::query()->create(['slug' => 'deliverables-hub', 'name' => 'Test Hub', 'sort_order' => 1]);
        $district = District::query()->create([
            'hub_id' => $hub->id,
            'slug' => 'deliverables-district',
            'name' => 'Test District',
            'sort_order' => 1,
        ]);

        $cfaId = (int) DB::table('cfa_submissions')->insertGetId([
            'district_id' => $district->id,
            'applicant_name' => 'Test Applicant',
            'phone' => '9999999999',
            'payload' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        ServiceCase::query()->create([
            'cfa_submission_id' => $cfaId,
            'service_id' => $service->id,
            'status' => ServiceCase::STATUS_APPROVED,
            'reference_number' => 'SC-TEST-1',
            'approved_at' => '2025-06-01',
        ]);

        $report = app(ProgramDeliverablesReportService::class)->build($fy->id);
        $rows = collect($report['rows']);

        $this->assertTrue($rows->contains(fn ($r) => $r['row_type'] === 'category' && $r['serial'] === '1'));
        $this->assertTrue($rows->contains(fn ($r) => $r['row_type'] === 'service' && $r['name'] === 'Udyam Registration' && $r['target'] === 100 && $r['achievement'] === 1));
    }
}
