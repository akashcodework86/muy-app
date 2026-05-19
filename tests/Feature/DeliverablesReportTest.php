<?php

namespace Tests\Feature;

use App\Models\Deliverable;
use App\Models\District;
use App\Models\DistrictDeliverableTarget;
use App\Models\FiscalYear;
use App\Models\Hub;
use App\Models\Service;
use App\Models\ServiceCase;
use App\Models\ServiceCategory;
use App\Models\User;
use App\Services\Deliverables\ProgramDeliverablesFilter;
use App\Services\Deliverables\ProgramDeliverablesScope;
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
            ->assertSee('Outreach and Mobilisation')
            ->assertSee('District');
    }

    public function test_staff_deliverables_page_is_scoped_to_district(): void
    {
        $hub = Hub::query()->create(['slug' => 'hub-a', 'name' => 'Hub A', 'sort_order' => 1]);
        $districtA = District::query()->create(['hub_id' => $hub->id, 'slug' => 'dist-a', 'name' => 'District A', 'sort_order' => 1]);
        $districtB = District::query()->create(['hub_id' => $hub->id, 'slug' => 'dist-b', 'name' => 'District B', 'sort_order' => 2]);

        $staff = User::factory()->create([
            'role' => 'district_staff',
            'is_active' => true,
            'hub_id' => $hub->id,
            'district_id' => $districtA->id,
        ]);

        $this->actingAs($staff)
            ->get(route('staff.deliverables.index'))
            ->assertOk()
            ->assertSee('Your district')
            ->assertDontSee('name="district_id"', false);
    }

    public function test_report_follows_official_mis_sequence(): void
    {
        $filter = new ProgramDeliverablesFilter(null, null, null, null, null, null);
        $scope = ProgramDeliverablesScope::forUser(User::factory()->make(['role' => 'state_admin']));

        $report = app(ProgramDeliverablesReportService::class)->build($filter, $scope);
        $serials = collect($report['rows'])->pluck('serial')->all();

        $this->assertSame('1', $serials[0]);
        $this->assertSame('1.1', $serials[1]);
        $this->assertContains('4.1.1', $serials);
        $this->assertContains('12.2', $serials);
    }

    public function test_cfa_count_uses_fiscal_year_id_not_created_at_window(): void
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

        $hub = Hub::query()->create(['slug' => 'cfa-hub', 'name' => 'Hub', 'sort_order' => 1]);
        $district = District::query()->create([
            'hub_id' => $hub->id,
            'slug' => 'cfa-district',
            'name' => 'District',
            'sort_order' => 1,
        ]);

        DB::table('cfa_submissions')->insert([
            [
                'district_id' => $district->id,
                'fiscal_year_id' => $fy->id,
                'applicant_name' => 'Tagged',
                'phone' => '9000000001',
                'payload' => json_encode([]),
                'created_at' => '2025-06-01',
                'updated_at' => now(),
            ],
            [
                'district_id' => $district->id,
                'fiscal_year_id' => null,
                'applicant_name' => 'Date only',
                'phone' => '9000000002',
                'payload' => json_encode([]),
                'created_at' => '2025-06-15',
                'updated_at' => now(),
            ],
        ]);

        $filter = new ProgramDeliverablesFilter($fy->id, null, null, null, null, null);
        $scope = ProgramDeliverablesScope::forUser(User::factory()->make(['role' => 'state_admin']));
        $report = app(ProgramDeliverablesReportService::class)->build($filter, $scope);
        $row = collect($report['rows'])->firstWhere('serial', '1.1');

        $this->assertNotNull($row);
        $this->assertSame(1, $row['achievement']);
    }

    public function test_business_registration_row_counts_approved_cases_in_district_scope(): void
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

        $hub = Hub::query()->create(['slug' => 'deliverables-hub', 'name' => 'Test Hub', 'sort_order' => 1]);
        $district = District::query()->create([
            'hub_id' => $hub->id,
            'slug' => 'deliverables-district',
            'name' => 'Test District',
            'sort_order' => 1,
        ]);
        $otherDistrict = District::query()->create([
            'hub_id' => $hub->id,
            'slug' => 'other-district',
            'name' => 'Other District',
            'sort_order' => 2,
        ]);

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

        DistrictDeliverableTarget::query()->create([
            'fiscal_year_id' => $fy->id,
            'district_id' => $district->id,
            'deliverable_id' => $deliverable->id,
            'target_total' => 50,
        ]);

        $cfaId = (int) DB::table('cfa_submissions')->insertGetId([
            'district_id' => $district->id,
            'applicant_name' => 'In scope',
            'phone' => '9999999991',
            'payload' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $cfaOtherId = (int) DB::table('cfa_submissions')->insertGetId([
            'district_id' => $otherDistrict->id,
            'applicant_name' => 'Out of scope',
            'phone' => '9999999992',
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

        ServiceCase::query()->create([
            'cfa_submission_id' => $cfaOtherId,
            'service_id' => $service->id,
            'status' => ServiceCase::STATUS_APPROVED,
            'reference_number' => 'SC-TEST-2',
            'approved_at' => '2025-06-01',
        ]);

        $filter = new ProgramDeliverablesFilter($fy->id, $district->id, null, null, null, null);
        $scope = new ProgramDeliverablesScope('district_staff', $hub->id, [$district->id], false);

        $report = app(ProgramDeliverablesReportService::class)->build($filter, $scope);
        $row = collect($report['rows'])->firstWhere('serial', '4.1.1');

        $this->assertNotNull($row);
        $this->assertSame(50, $row['target']);
        $this->assertSame(1, $row['achievement']);
    }
}
