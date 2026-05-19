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
            ->assertSee('Deliverables')
            ->assertSee('Outreach and Mobilisation');
    }

    public function test_report_follows_official_mis_sequence(): void
    {
        $report = app(ProgramDeliverablesReportService::class)->build(null);
        $serials = collect($report['rows'])->pluck('serial')->all();

        $this->assertSame('1', $serials[0]);
        $this->assertSame('1.1', $serials[1]);
        $this->assertContains('4.1.1', $serials);
        $this->assertContains('12.2', $serials);

        $idx4 = array_search('4', $serials, true);
        $idx5 = array_search('5', $serials, true);
        $this->assertNotFalse($idx4);
        $this->assertNotFalse($idx5);
        $this->assertLessThan($idx5, $idx4);
    }

    public function test_business_registration_row_counts_approved_cases(): void
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

        $child = ServiceCategory::query()->create([
            'slug' => 'bf_services',
            'name' => 'Services',
            'sort_order' => 0,
        ]);

        $deliverable = Deliverable::query()->create([
            'sort_order' => 6,
            'code' => 'business_registration',
            'name' => 'Business Registration',
            'mis_entry_label' => 'Business Registration',
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

        $row = $rows->firstWhere('serial', '4.1.1');
        $this->assertNotNull($row);
        $this->assertSame('Business Registration', $row['name']);
        $this->assertSame(100, $row['target']);
        $this->assertSame(1, $row['achievement']);
    }
}
