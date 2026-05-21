<?php

namespace Tests\Feature;

use App\Models\Deliverable;
use App\Models\District;
use App\Models\DistrictDeliverableTarget;
use App\Models\StaffMonthlyTarget;
use App\Models\FiscalYear;
use App\Models\Hub;
use App\Models\OnboardingBatch;
use App\Models\OnboardingBatchCfa;
use App\Models\Service;
use App\Models\ServiceCase;
use App\Models\FieldCoordinatorAttendanceReport;
use App\Models\ServiceCategory;
use App\Models\StateDeliverableTarget;
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
            ->assertSee('Call for Application')
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
            ->assertSee('Deliverables')
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
        $this->assertContains('2', $serials);
        $this->assertContains('4', $serials);
        $this->assertContains('4.1', $serials);
        $this->assertContains('4.1.1', $serials);
        $this->assertContains('4.2.4', $serials);
        $this->assertContains('12.2', $serials);

        $heading = collect($report['rows'])->firstWhere('serial', '4');
        $this->assertSame('pillar', $heading['row_type']);
        $this->assertNull($heading['target']);
        $this->assertNull($heading['achievement']);
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

    public function test_field_work_maps_to_mis_rows_1_3_and_1_3_1(): void
    {
        $fy = FiscalYear::query()->firstOrCreate(
            ['code' => '2026-27'],
            [
                'name' => 'FY 2026-27',
                'starts_on' => '2026-04-01',
                'ends_on' => '2027-03-31',
                'is_active' => true,
            ]
        );

        $hub = Hub::query()->create(['slug' => 'fw-hub', 'name' => 'Hub', 'sort_order' => 1]);
        $district = District::query()->create([
            'hub_id' => $hub->id,
            'slug' => 'fw-district',
            'name' => 'District',
            'sort_order' => 1,
        ]);

        $coordinator = User::factory()->create([
            'role' => 'district_staff',
            'district_id' => $district->id,
            'hub_id' => $hub->id,
        ]);

        FieldCoordinatorAttendanceReport::query()->create([
            'field_coordinator_user_id' => $coordinator->id,
            'field_coordinator_name' => 'Test FC',
            'district_id' => $district->id,
            'visit_date' => '2026-06-10',
            'entry_date' => '2026-06-10',
            'participants_total' => 40,
            'participants_male_count' => 20,
            'participants_female_count' => 20,
        ]);

        FieldCoordinatorAttendanceReport::query()->create([
            'field_coordinator_user_id' => $coordinator->id,
            'field_coordinator_name' => 'Test FC',
            'district_id' => $district->id,
            'visit_date' => '2026-06-12',
            'entry_date' => '2026-06-12',
            'participants_total' => 25,
            'participants_male_count' => 10,
            'participants_female_count' => 15,
        ]);

        $filter = new ProgramDeliverablesFilter($fy->id, $district->id, null, null, null, null);
        $scope = new ProgramDeliverablesScope('district_staff', $hub->id, [$district->id], false);
        $report = app(ProgramDeliverablesReportService::class)->build($filter, $scope);

        $workshops = collect($report['rows'])->firstWhere('serial', '1.3');
        $participants = collect($report['rows'])->firstWhere('serial', '1.3.1');

        $this->assertNotNull($workshops);
        $this->assertNotNull($participants);
        $this->assertSame(2, $workshops['achievement']);
        $this->assertSame(65, $participants['achievement']);
    }

    public function test_district_staff_sees_district_target_via_svc_deliverable_mapping(): void
    {
        $fy = FiscalYear::query()->firstOrCreate(
            ['code' => '2026-27'],
            [
                'name' => 'FY 2026-27',
                'starts_on' => '2026-04-01',
                'ends_on' => '2027-03-31',
                'is_active' => true,
            ]
        );

        $hub = Hub::query()->create(['slug' => 'staff-gst-hub', 'name' => 'Hub', 'sort_order' => 1]);
        $district = District::query()->create([
            'hub_id' => $hub->id,
            'slug' => 'staff-gst-district',
            'name' => 'Staff GST District',
            'sort_order' => 1,
        ]);

        $svcGst = Deliverable::query()->create([
            'sort_order' => 18,
            'code' => 'svc_gst',
            'name' => 'GST',
            'mis_entry_label' => 'GST',
            'is_active' => true,
        ]);

        DistrictDeliverableTarget::query()->create([
            'fiscal_year_id' => $fy->id,
            'district_id' => $district->id,
            'deliverable_id' => $svcGst->id,
            'target_total' => 42,
        ]);

        $staff = User::factory()->create([
            'role' => 'district_staff',
            'hub_id' => $hub->id,
            'district_id' => $district->id,
            'is_active' => true,
        ]);

        $filter = new ProgramDeliverablesFilter($fy->id, null, null, null, null, null);
        $scope = ProgramDeliverablesScope::forUser($staff);
        $report = app(ProgramDeliverablesReportService::class)->build($filter, $scope);
        $row = collect($report['rows'])->firstWhere('serial', '4.2.4');

        $this->assertNotNull($row);
        $this->assertSame(42, $row['target']);
    }

    public function test_district_staff_falls_back_to_staff_monthly_targets_when_no_district_row(): void
    {
        $fy = FiscalYear::query()->firstOrCreate(
            ['code' => '2026-27'],
            [
                'name' => 'FY 2026-27',
                'starts_on' => '2026-04-01',
                'ends_on' => '2027-03-31',
                'is_active' => true,
            ]
        );

        $hub = Hub::query()->create(['slug' => 'staff-fssai-hub', 'name' => 'Hub', 'sort_order' => 1]);
        $district = District::query()->create([
            'hub_id' => $hub->id,
            'slug' => 'staff-fssai-district',
            'name' => 'Staff FSSAI District',
            'sort_order' => 1,
        ]);

        $svcFssai = Deliverable::query()->create([
            'sort_order' => 17,
            'code' => 'svc_fssai',
            'name' => 'FSSAI',
            'mis_entry_label' => 'FSSAI',
            'is_active' => true,
        ]);

        $staff = User::factory()->create([
            'role' => 'district_staff',
            'hub_id' => $hub->id,
            'district_id' => $district->id,
            'is_active' => true,
        ]);

        foreach (range(1, 12) as $month) {
            StaffMonthlyTarget::query()->create([
                'fiscal_year_id' => $fy->id,
                'user_id' => $staff->id,
                'deliverable_id' => $svcFssai->id,
                'month_number' => $month,
                'target_count' => 2,
            ]);
        }

        $filter = new ProgramDeliverablesFilter($fy->id, null, null, null, null, null);
        $scope = ProgramDeliverablesScope::forUser($staff);
        $report = app(ProgramDeliverablesReportService::class)->build($filter, $scope);
        $row = collect($report['rows'])->firstWhere('serial', '4.2.2');

        $this->assertNotNull($row);
        $this->assertSame(24, $row['target']);
    }

    public function test_state_target_resolves_for_gst_via_svc_deliverable_row(): void
    {
        $fy = FiscalYear::query()->firstOrCreate(
            ['code' => '2026-27'],
            [
                'name' => 'FY 2026-27',
                'starts_on' => '2026-04-01',
                'ends_on' => '2027-03-31',
                'is_active' => true,
            ]
        );

        $child = ServiceCategory::query()->create(['slug' => 'legal', 'name' => 'Legal', 'sort_order' => 1]);
        Deliverable::query()->create([
            'sort_order' => 8,
            'code' => 'gst',
            'name' => 'GST Registration',
            'mis_entry_label' => 'GST',
            'is_active' => true,
        ]);
        $svcGst = Deliverable::query()->create([
            'sort_order' => 18,
            'code' => 'svc_gst',
            'name' => 'GST',
            'mis_entry_label' => 'GST',
            'is_active' => true,
        ]);

        Service::query()->create([
            'service_category_id' => $child->id,
            'deliverable_id' => $svcGst->id,
            'code' => 'gst',
            'name' => 'GST',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        StateDeliverableTarget::query()->create([
            'fiscal_year_id' => $fy->id,
            'deliverable_id' => $svcGst->id,
            'target_total' => 350,
        ]);

        $filter = new ProgramDeliverablesFilter($fy->id, null, null, null, null, null);
        $scope = ProgramDeliverablesScope::forUser(User::factory()->make(['role' => 'state_admin']));
        $report = app(ProgramDeliverablesReportService::class)->build($filter, $scope);
        $row = collect($report['rows'])->firstWhere('serial', '4.2.4');

        $this->assertNotNull($row);
        $this->assertSame(350, $row['target']);
    }

    public function test_state_admin_sees_district_target_when_district_filter_applied(): void
    {
        $fy = FiscalYear::query()->firstOrCreate(
            ['code' => '2026-27'],
            [
                'name' => 'FY 2026-27',
                'starts_on' => '2026-04-01',
                'ends_on' => '2027-03-31',
                'is_active' => true,
            ]
        );

        $hub = Hub::query()->create(['slug' => 'gst-hub', 'name' => 'Hub', 'sort_order' => 1]);
        $district = District::query()->create([
            'hub_id' => $hub->id,
            'slug' => 'gst-district',
            'name' => 'GST District',
            'sort_order' => 1,
        ]);

        $svcGst = Deliverable::query()->create([
            'sort_order' => 18,
            'code' => 'svc_gst',
            'name' => 'GST',
            'mis_entry_label' => 'GST',
            'is_active' => true,
        ]);

        StateDeliverableTarget::query()->create([
            'fiscal_year_id' => $fy->id,
            'deliverable_id' => $svcGst->id,
            'target_total' => 350,
        ]);

        DistrictDeliverableTarget::query()->create([
            'fiscal_year_id' => $fy->id,
            'district_id' => $district->id,
            'deliverable_id' => $svcGst->id,
            'target_total' => 42,
        ]);

        $scope = ProgramDeliverablesScope::forUser(User::factory()->make(['role' => 'state_admin']));

        $statewideReport = app(ProgramDeliverablesReportService::class)->build(
            new ProgramDeliverablesFilter($fy->id, null, null, null, null, null),
            $scope,
        );
        $statewideRow = collect($statewideReport['rows'])->firstWhere('serial', '4.2.4');
        $this->assertNotNull($statewideRow);
        $this->assertSame(350, $statewideRow['target']);

        $districtReport = app(ProgramDeliverablesReportService::class)->build(
            new ProgramDeliverablesFilter($fy->id, $district->id, null, null, null, null),
            $scope,
        );
        $districtRow = collect($districtReport['rows'])->firstWhere('serial', '4.2.4');

        $this->assertNotNull($districtRow);
        $this->assertSame(42, $districtRow['target']);
    }

    public function test_state_target_resolves_via_service_catalog_for_pitch_deck(): void
    {
        $fy = FiscalYear::query()->firstOrCreate(
            ['code' => '2026-27'],
            [
                'name' => 'FY 2026-27',
                'starts_on' => '2026-04-01',
                'ends_on' => '2027-03-31',
                'is_active' => true,
            ]
        );

        $child = ServiceCategory::query()->create(['slug' => 'inc', 'name' => 'Inc', 'sort_order' => 1]);
        $mis = Deliverable::query()->create([
            'sort_order' => 18,
            'code' => 'pitch_deck_prep',
            'name' => 'Pitch deck MIS',
            'mis_entry_label' => 'Pitch',
            'is_active' => true,
        ]);
        $svcDeliverable = Deliverable::query()->create([
            'sort_order' => 55,
            'code' => 'svc_pitch_deck',
            'name' => 'Pitch Decks',
            'mis_entry_label' => 'Pitch Decks',
            'is_active' => true,
        ]);

        Service::query()->create([
            'service_category_id' => $child->id,
            'deliverable_id' => $svcDeliverable->id,
            'code' => 'pitch_deck',
            'name' => 'Pitch Deck',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        StateDeliverableTarget::query()->create([
            'fiscal_year_id' => $fy->id,
            'deliverable_id' => $svcDeliverable->id,
            'target_total' => 200,
        ]);

        $filter = new ProgramDeliverablesFilter($fy->id, null, null, null, null, null);
        $scope = ProgramDeliverablesScope::forUser(User::factory()->make(['role' => 'state_admin']));
        $report = app(ProgramDeliverablesReportService::class)->build($filter, $scope);
        $row = collect($report['rows'])->firstWhere('serial', '8.3');

        $this->assertNotNull($row);
        $this->assertSame(200, $row['target']);
    }

    public function test_state_target_resolves_from_synced_svc_deliverable_code(): void
    {
        $fy = FiscalYear::query()->firstOrCreate(
            ['code' => '2026-27'],
            [
                'name' => 'FY 2026-27',
                'starts_on' => '2026-04-01',
                'ends_on' => '2027-03-31',
                'is_active' => true,
            ]
        );

        $mis = Deliverable::query()->create([
            'sort_order' => 10,
            'code' => 'artisan_card',
            'name' => 'Artisan Card',
            'mis_entry_label' => 'Artisan Card',
            'is_active' => true,
        ]);

        $svc = Deliverable::query()->create([
            'sort_order' => 50,
            'code' => 'svc_artisan_card',
            'name' => 'Artisan Card Service',
            'mis_entry_label' => 'Artisan Card Service',
            'is_active' => true,
        ]);

        StateDeliverableTarget::query()->create([
            'fiscal_year_id' => $fy->id,
            'deliverable_id' => $svc->id,
            'target_total' => 260,
        ]);

        $filter = new ProgramDeliverablesFilter($fy->id, null, null, null, null, null);
        $scope = ProgramDeliverablesScope::forUser(User::factory()->make(['role' => 'state_admin']));
        $report = app(ProgramDeliverablesReportService::class)->build($filter, $scope);
        $row = collect($report['rows'])->firstWhere('serial', '4.2.1');

        $this->assertNotNull($row);
        $this->assertSame(260, $row['target']);
    }

    public function test_business_registration_row_counts_approved_cases_in_district_scope(): void
    {
        $fy = FiscalYear::query()->firstOrCreate(
            ['code' => '2026-27'],
            [
                'name' => 'FY 2026-27',
                'starts_on' => '2026-04-01',
                'ends_on' => '2027-03-31',
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
            'code' => 'udyam_registration',
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
            'approved_at' => '2026-06-01',
        ]);

        ServiceCase::query()->create([
            'cfa_submission_id' => $cfaOtherId,
            'service_id' => $service->id,
            'status' => ServiceCase::STATUS_APPROVED,
            'reference_number' => 'SC-TEST-2',
            'approved_at' => '2026-06-01',
        ]);

        $filter = new ProgramDeliverablesFilter($fy->id, $district->id, null, null, null, null);
        $scope = new ProgramDeliverablesScope('district_staff', $hub->id, [$district->id], false);

        $report = app(ProgramDeliverablesReportService::class)->build($filter, $scope);
        $row = collect($report['rows'])->firstWhere('serial', '4.1.1');

        $this->assertNotNull($row);
        $this->assertSame(50, $row['target']);
        $this->assertSame(1, $row['achievement']);
    }

    public function test_fssai_achievement_counts_via_svc_deliverable_and_service_alias(): void
    {
        $fy = FiscalYear::query()->firstOrCreate(
            ['code' => '2026-27'],
            [
                'name' => 'FY 2026-27',
                'starts_on' => '2026-04-01',
                'ends_on' => '2027-03-31',
                'is_active' => true,
            ]
        );

        $hub = Hub::query()->create(['slug' => 'fssai-hub', 'name' => 'Hub', 'sort_order' => 1]);
        $district = District::query()->create([
            'hub_id' => $hub->id,
            'slug' => 'fssai-district',
            'name' => 'FSSAI District',
            'sort_order' => 1,
        ]);

        $child = ServiceCategory::query()->create(['slug' => 'legal', 'name' => 'Legal', 'sort_order' => 1]);
        $misFssai = Deliverable::query()->create([
            'sort_order' => 7,
            'code' => 'fssai',
            'name' => 'FSSAI',
            'mis_entry_label' => 'FSSAI',
            'is_active' => true,
        ]);
        $svcFssai = Deliverable::query()->create([
            'sort_order' => 57,
            'code' => 'svc_fssai',
            'name' => 'FSSAI Service',
            'mis_entry_label' => 'FSSAI',
            'is_active' => true,
        ]);

        $service = Service::query()->create([
            'service_category_id' => $child->id,
            'deliverable_id' => $svcFssai->id,
            'code' => 'fssai_registration',
            'name' => 'FSSAI Registration',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        StateDeliverableTarget::query()->create([
            'fiscal_year_id' => $fy->id,
            'deliverable_id' => $svcFssai->id,
            'target_total' => 750,
        ]);

        $cfaId = (int) DB::table('cfa_submissions')->insertGetId([
            'district_id' => $district->id,
            'applicant_name' => 'FSSAI Applicant',
            'phone' => '9999999901',
            'payload' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        ServiceCase::query()->create([
            'cfa_submission_id' => $cfaId,
            'service_id' => $service->id,
            'status' => ServiceCase::STATUS_APPROVED,
            'reference_number' => 'SC-FSSAI-1',
            'approved_at' => '2026-06-15',
        ]);

        ServiceCase::query()->create([
            'cfa_submission_id' => $cfaId,
            'service_id' => $service->id,
            'status' => ServiceCase::STATUS_APPROVED,
            'reference_number' => 'SC-FSSAI-2',
            'approved_at' => '2026-07-01',
        ]);

        $filter = new ProgramDeliverablesFilter($fy->id, null, null, null, null, null);
        $scope = ProgramDeliverablesScope::forUser(User::factory()->make(['role' => 'state_admin']));
        $report = app(ProgramDeliverablesReportService::class)->build($filter, $scope);
        $row = collect($report['rows'])->firstWhere('serial', '4.2.2');

        $this->assertNotNull($row);
        $this->assertSame(750, $row['target']);
        $this->assertSame(2, $row['achievement']);
    }

    public function test_fssai_achievement_matches_registration_renewal_service_name_without_deliverable_link(): void
    {
        $fy = FiscalYear::query()->firstOrCreate(
            ['code' => '2026-27'],
            [
                'name' => 'FY 2026-27',
                'starts_on' => '2026-04-01',
                'ends_on' => '2027-03-31',
                'is_active' => true,
            ]
        );

        $hub = Hub::query()->create(['slug' => 'fssai-renewal-hub', 'name' => 'Hub', 'sort_order' => 1]);
        $district = District::query()->create([
            'hub_id' => $hub->id,
            'slug' => 'fssai-renewal-district',
            'name' => 'FSSAI Renewal District',
            'sort_order' => 1,
        ]);

        $child = ServiceCategory::query()->create(['slug' => 'legal-renewal', 'name' => 'Legal', 'sort_order' => 1]);
        Deliverable::query()->create([
            'sort_order' => 7,
            'code' => 'fssai',
            'name' => 'FSSAI registration / renewal',
            'mis_entry_label' => 'FSSAI',
            'is_active' => true,
        ]);
        $svcFssai = Deliverable::query()->create([
            'sort_order' => 57,
            'code' => 'svc_fssai',
            'name' => 'FSSAI Service',
            'mis_entry_label' => 'FSSAI',
            'is_active' => true,
        ]);

        $service = Service::query()->create([
            'service_category_id' => $child->id,
            'deliverable_id' => null,
            'code' => 'fssai_registration_renewal',
            'name' => 'FSSAI Registration/Renewal',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        StateDeliverableTarget::query()->create([
            'fiscal_year_id' => $fy->id,
            'deliverable_id' => $svcFssai->id,
            'target_total' => 750,
        ]);

        $cfaId = (int) DB::table('cfa_submissions')->insertGetId([
            'district_id' => $district->id,
            'applicant_name' => 'FSSAI Renewal Applicant',
            'phone' => '9999999902',
            'payload' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        ServiceCase::query()->create([
            'cfa_submission_id' => $cfaId,
            'service_id' => $service->id,
            'status' => ServiceCase::STATUS_APPROVED,
            'reference_number' => 'SC-FSSAI-RENEW-1',
            'submitted_at' => '2026-05-21 11:02:00',
        ]);

        $filter = new ProgramDeliverablesFilter($fy->id, null, null, null, null, null);
        $scope = ProgramDeliverablesScope::forUser(User::factory()->make(['role' => 'state_admin']));
        $report = app(ProgramDeliverablesReportService::class)->build($filter, $scope);
        $row = collect($report['rows'])->firstWhere('serial', '4.2.2');

        $this->assertNotNull($row);
        $this->assertSame(750, $row['target']);
        $this->assertSame(1, $row['achievement']);
    }

    public function test_bmc_achievement_counts_via_svc_deliverable_and_service_name(): void
    {
        $fy = FiscalYear::query()->firstOrCreate(
            ['code' => '2026-27'],
            [
                'name' => 'FY 2026-27',
                'starts_on' => '2026-04-01',
                'ends_on' => '2027-03-31',
                'is_active' => true,
            ]
        );

        $hub = Hub::query()->create(['slug' => 'bmc-hub', 'name' => 'Hub', 'sort_order' => 1]);
        $district = District::query()->create([
            'hub_id' => $hub->id,
            'slug' => 'bmc-district',
            'name' => 'BMC District',
            'sort_order' => 1,
        ]);

        $child = ServiceCategory::query()->create(['slug' => 'business_model_canvas_services', 'name' => 'Services', 'sort_order' => 1]);
        Deliverable::query()->create([
            'sort_order' => 5,
            'code' => 'bmc',
            'name' => 'Business Model Canvas',
            'mis_entry_label' => 'Business Model Canvas',
            'is_active' => true,
        ]);
        $svcBmc = Deliverable::query()->create([
            'sort_order' => 55,
            'code' => 'svc_bmc_canvas',
            'name' => 'Business Model Canvas',
            'mis_entry_label' => 'Business Model Canvas',
            'is_active' => true,
        ]);

        $service = Service::query()->create([
            'service_category_id' => $child->id,
            'deliverable_id' => $svcBmc->id,
            'code' => 'business_model_canvas',
            'name' => 'Business Model Canvas',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        StateDeliverableTarget::query()->create([
            'fiscal_year_id' => $fy->id,
            'deliverable_id' => $svcBmc->id,
            'target_total' => 6000,
        ]);

        $cfaId = (int) DB::table('cfa_submissions')->insertGetId([
            'district_id' => $district->id,
            'applicant_name' => 'BMC Applicant',
            'phone' => '9999999903',
            'payload' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        ServiceCase::query()->create([
            'cfa_submission_id' => $cfaId,
            'service_id' => $service->id,
            'status' => ServiceCase::STATUS_APPROVED,
            'reference_number' => 'SC-BMC-1',
            'submitted_at' => '2026-05-10 09:00:00',
        ]);

        ServiceCase::query()->create([
            'cfa_submission_id' => $cfaId,
            'service_id' => $service->id,
            'status' => ServiceCase::STATUS_APPROVED,
            'reference_number' => 'SC-BMC-2',
            'submitted_at' => '2026-05-12 14:30:00',
        ]);

        $filter = new ProgramDeliverablesFilter($fy->id, null, null, null, null, null);
        $scope = ProgramDeliverablesScope::forUser(User::factory()->make(['role' => 'state_admin']));
        $report = app(ProgramDeliverablesReportService::class)->build($filter, $scope);
        $row = collect($report['rows'])->firstWhere('serial', '9.1');

        $this->assertNotNull($row);
        $this->assertSame(6000, $row['target']);
        $this->assertSame(2, $row['achievement']);
    }

    public function test_deliverables_breakdown_returns_json_for_bmc(): void
    {
        $fy = FiscalYear::query()->firstOrCreate(
            ['code' => '2026-27'],
            [
                'name' => 'FY 2026-27',
                'starts_on' => '2026-04-01',
                'ends_on' => '2027-03-31',
                'is_active' => true,
            ]
        );

        $hub = Hub::query()->create(['slug' => 'bmc-breakdown-hub', 'name' => 'Hub', 'sort_order' => 1]);
        $district = District::query()->create([
            'hub_id' => $hub->id,
            'slug' => 'bmc-breakdown-district',
            'name' => 'BMC Breakdown District',
            'sort_order' => 1,
        ]);

        $child = ServiceCategory::query()->create(['slug' => 'bmc_breakdown_services', 'name' => 'Services', 'sort_order' => 1]);
        $svcBmc = Deliverable::query()->create([
            'sort_order' => 55,
            'code' => 'svc_bmc_canvas',
            'name' => 'Business Model Canvas',
            'mis_entry_label' => 'Business Model Canvas',
            'is_active' => true,
        ]);

        $service = Service::query()->create([
            'service_category_id' => $child->id,
            'deliverable_id' => $svcBmc->id,
            'code' => 'bmc_canvas',
            'name' => 'Business Model Canvas',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $cfaId = (int) DB::table('cfa_submissions')->insertGetId([
            'district_id' => $district->id,
            'applicant_name' => 'BMC Breakdown Applicant',
            'phone' => '9999999904',
            'payload' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        ServiceCase::query()->create([
            'cfa_submission_id' => $cfaId,
            'service_id' => $service->id,
            'status' => ServiceCase::STATUS_APPROVED,
            'reference_number' => 'SC-BMC-BD-1',
            'submitted_at' => '2026-05-10 09:00:00',
        ]);

        $admin = User::factory()->create(['role' => 'state_admin', 'is_active' => true]);

        $this->actingAs($admin)
            ->getJson(route('admin.deliverables.breakdown', [
                'fiscal_year_id' => $fy->id,
                'serial' => '9.1',
            ]))
            ->assertOk()
            ->assertJsonPath('serial', '9.1')
            ->assertJsonPath('name', 'Business Model Canvas')
            ->assertJsonPath('total', 1)
            ->assertJsonStructure([
                'by_district',
                'by_month',
                'insights',
                'records',
                'period_label',
                'scope_label',
            ]);
    }

    public function test_deliverables_breakdown_returns_json_for_onboarding(): void
    {
        $fy = FiscalYear::query()->firstOrCreate(
            ['code' => '2026-27'],
            [
                'name' => 'FY 2026-27',
                'starts_on' => '2026-04-01',
                'ends_on' => '2027-03-31',
                'is_active' => true,
            ]
        );

        $hub = Hub::query()->create(['slug' => 'onb-breakdown-hub', 'name' => 'Hub', 'sort_order' => 1]);
        $district = District::query()->create([
            'hub_id' => $hub->id,
            'slug' => 'onb-breakdown-district',
            'name' => 'Onboarding District',
            'sort_order' => 1,
        ]);

        $cfaId = (int) DB::table('cfa_submissions')->insertGetId([
            'district_id' => $district->id,
            'applicant_name' => 'Onboarded Applicant',
            'phone' => '9999999905',
            'payload' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $batch = OnboardingBatch::query()->create([
            'hub_id' => $hub->id,
            'district_id' => $district->id,
            'name' => 'Batch Alpha',
            'target_size' => 20,
            'status' => 'locked',
            'locked_at' => '2026-05-15 10:00:00',
        ]);

        OnboardingBatchCfa::query()->create([
            'onboarding_batch_id' => $batch->id,
            'cfa_submission_id' => $cfaId,
        ]);

        $admin = User::factory()->create(['role' => 'state_admin', 'is_active' => true]);

        $this->actingAs($admin)
            ->getJson(route('admin.deliverables.breakdown', [
                'fiscal_year_id' => $fy->id,
                'serial' => '2.1',
            ]))
            ->assertOk()
            ->assertJsonPath('serial', '2.1')
            ->assertJsonPath('name', 'Incubatees Onboarded')
            ->assertJsonPath('total', 1)
            ->assertJsonPath('source_type_label', 'Onboarded incubatees');
    }

    public function test_deliverables_breakdown_returns_json_for_field_work(): void
    {
        $fy = FiscalYear::query()->firstOrCreate(
            ['code' => '2026-27'],
            [
                'name' => 'FY 2026-27',
                'starts_on' => '2026-04-01',
                'ends_on' => '2027-03-31',
                'is_active' => true,
            ]
        );

        $hub = Hub::query()->create(['slug' => 'fw-breakdown-hub', 'name' => 'Hub', 'sort_order' => 1]);
        $district = District::query()->create([
            'hub_id' => $hub->id,
            'slug' => 'fw-breakdown-district',
            'name' => 'Field District',
            'sort_order' => 1,
        ]);

        $coordinator = User::factory()->create([
            'role' => 'district_staff',
            'district_id' => $district->id,
            'hub_id' => $hub->id,
        ]);

        FieldCoordinatorAttendanceReport::query()->create([
            'field_coordinator_user_id' => $coordinator->id,
            'field_coordinator_name' => 'FC One',
            'district_id' => $district->id,
            'visit_date' => '2026-06-10',
            'entry_date' => '2026-06-10',
            'area' => 'Block A',
            'participants_total' => 10,
        ]);

        $admin = User::factory()->create(['role' => 'state_admin', 'is_active' => true]);

        $this->actingAs($admin)
            ->getJson(route('admin.deliverables.breakdown', [
                'fiscal_year_id' => $fy->id,
                'serial' => '1.3',
            ]))
            ->assertOk()
            ->assertJsonPath('serial', '1.3')
            ->assertJsonPath('total', 1)
            ->assertJsonPath('source_type_label', 'Field work visits');
    }

    public function test_deliverables_breakdown_export_returns_xlsx_for_field_work(): void
    {
        $fy = FiscalYear::query()->firstOrCreate(
            ['code' => '2026-27'],
            [
                'name' => 'FY 2026-27',
                'starts_on' => '2026-04-01',
                'ends_on' => '2027-03-31',
                'is_active' => true,
            ]
        );

        $hub = Hub::query()->create(['slug' => 'fw-export-hub', 'name' => 'Hub', 'sort_order' => 1]);
        $district = District::query()->create([
            'hub_id' => $hub->id,
            'slug' => 'fw-export-district',
            'name' => 'Field Export District',
            'sort_order' => 1,
        ]);

        $coordinator = User::factory()->create([
            'role' => 'district_staff',
            'district_id' => $district->id,
            'hub_id' => $hub->id,
        ]);

        FieldCoordinatorAttendanceReport::query()->create([
            'field_coordinator_user_id' => $coordinator->id,
            'field_coordinator_name' => 'FC Export',
            'district_id' => $district->id,
            'visit_date' => '2026-06-10',
            'entry_date' => '2026-06-10',
            'area' => 'Block A',
            'participants_total' => 10,
        ]);

        $admin = User::factory()->create(['role' => 'state_admin', 'is_active' => true]);

        $this->actingAs($admin)
            ->get(route('admin.deliverables.breakdown.export', [
                'fiscal_year_id' => $fy->id,
                'serial' => '1.3',
            ]))
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $this->actingAs($admin)
            ->get(route('admin.deliverables.breakdown.export.csv', [
                'fiscal_year_id' => $fy->id,
                'serial' => '1.3',
            ]))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    public function test_deliverables_breakdown_export_returns_xlsx_for_cfa(): void
    {
        $fy = FiscalYear::query()->firstOrCreate(
            ['code' => '2026-27'],
            [
                'name' => 'FY 2026-27',
                'starts_on' => '2026-04-01',
                'ends_on' => '2027-03-31',
                'is_active' => true,
            ]
        );

        $hub = Hub::query()->create(['slug' => 'cfa-export-hub', 'name' => 'Hub', 'sort_order' => 1]);
        $district = District::query()->create([
            'hub_id' => $hub->id,
            'slug' => 'cfa-export-district',
            'name' => 'CFA Export District',
            'sort_order' => 1,
        ]);

        DB::table('cfa_submissions')->insert([
            'district_id' => $district->id,
            'fiscal_year_id' => $fy->id,
            'application_no' => 'CFA-001',
            'applicant_name' => 'CFA Export Applicant',
            'phone' => '9999999910',
            'payload' => json_encode([]),
            'created_at' => '2026-05-01',
            'updated_at' => now(),
        ]);

        $admin = User::factory()->create(['role' => 'state_admin', 'is_active' => true]);

        $this->actingAs($admin)
            ->get(route('admin.deliverables.breakdown.export', [
                'fiscal_year_id' => $fy->id,
                'serial' => '1.1',
            ]))
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_deliverables_breakdown_export_pdf_for_cfa(): void
    {
        if (! class_exists(\Dompdf\Dompdf::class)) {
            $this->markTestSkipped('Dompdf is not installed.');
        }

        $fy = FiscalYear::query()->firstOrCreate(
            ['code' => '2026-27'],
            [
                'name' => 'FY 2026-27',
                'starts_on' => '2026-04-01',
                'ends_on' => '2027-03-31',
                'is_active' => true,
            ]
        );

        $hub = Hub::query()->create(['slug' => 'cfa-pdf-hub', 'name' => 'Hub', 'sort_order' => 1]);
        $district = District::query()->create([
            'hub_id' => $hub->id,
            'slug' => 'cfa-pdf-district',
            'name' => 'CFA PDF District',
            'sort_order' => 1,
        ]);

        DB::table('cfa_submissions')->insert([
            'district_id' => $district->id,
            'fiscal_year_id' => $fy->id,
            'application_no' => 'CFA-PDF-1',
            'applicant_name' => 'CFA PDF Applicant',
            'phone' => '9999999911',
            'payload' => json_encode([]),
            'created_at' => '2026-05-01',
            'updated_at' => now(),
        ]);

        $admin = User::factory()->create(['role' => 'state_admin', 'is_active' => true]);

        $this->actingAs($admin)
            ->get(route('admin.deliverables.breakdown.export.pdf', [
                'fiscal_year_id' => $fy->id,
                'serial' => '1.1',
            ]))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_social_media_post_achievement_counts_logged_posts(): void
    {
        $fy = FiscalYear::query()->firstOrCreate(
            ['code' => '2026-27'],
            [
                'name' => 'FY 2026-27',
                'starts_on' => '2026-04-01',
                'ends_on' => '2027-03-31',
                'is_active' => true,
            ]
        );

        $user = User::factory()->create([
            'role' => 'state_staff',
            'name' => 'Sanjna Mishra',
            'is_active' => true,
        ]);

        DB::table('social_media_posts')->insert([
            [
                'submitted_by_user_id' => $user->id,
                'submitted_by_name' => $user->name,
                'posted_on' => '2026-05-10',
                'post_url' => 'https://www.instagram.com/p/in-fy-1/',
                'description' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'submitted_by_user_id' => $user->id,
                'submitted_by_name' => $user->name,
                'posted_on' => '2026-06-01',
                'post_url' => 'https://www.instagram.com/p/in-fy-2/',
                'description' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'submitted_by_user_id' => $user->id,
                'submitted_by_name' => $user->name,
                'posted_on' => '2025-12-01',
                'post_url' => 'https://www.instagram.com/p/outside-fy/',
                'description' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $filter = new ProgramDeliverablesFilter($fy->id, null, null, null, null, null);
        $scope = ProgramDeliverablesScope::forUser(User::factory()->make(['role' => 'state_staff']));
        $report = app(ProgramDeliverablesReportService::class)->build($filter, $scope);

        $row = collect($report['rows'])->firstWhere('name', 'Social Media Post');
        $this->assertNotNull($row);
        $this->assertSame(2, $row['achievement']);
    }

    public function test_market_linkage_deliverable_achievements_are_scoped_by_district(): void
    {
        $fy = FiscalYear::query()->firstOrCreate(
            ['code' => '2026-27'],
            [
                'name' => 'FY 2026-27',
                'starts_on' => '2026-04-01',
                'ends_on' => '2027-03-31',
                'is_active' => true,
            ]
        );

        $hub = Hub::query()->create(['slug' => 'ml-del-hub', 'name' => 'ML Hub', 'sort_order' => 1]);
        $districtA = District::query()->create(['hub_id' => $hub->id, 'slug' => 'ml-dist-a', 'name' => 'ML District A', 'sort_order' => 1]);
        $districtB = District::query()->create(['hub_id' => $hub->id, 'slug' => 'ml-dist-b', 'name' => 'ML District B', 'sort_order' => 2]);

        $staffA = User::factory()->create([
            'role' => 'district_staff',
            'hub_id' => $hub->id,
            'district_id' => $districtA->id,
            'is_active' => true,
        ]);
        $staffB = User::factory()->create([
            'role' => 'district_staff',
            'hub_id' => $hub->id,
            'district_id' => $districtB->id,
            'is_active' => true,
        ]);

        $cfaA = (int) DB::table('cfa_submissions')->insertGetId([
            'district_id' => $districtA->id,
            'application_no' => 'ML-A-001',
            'applicant_name' => 'Incubatee A',
            'phone' => '9000000001',
            'payload' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $cfaB = (int) DB::table('cfa_submissions')->insertGetId([
            'district_id' => $districtB->id,
            'application_no' => 'ML-B-001',
            'applicant_name' => 'Incubatee B',
            'phone' => '9000000002',
            'payload' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $subA = (int) DB::table('market_linkage_submissions')->insertGetId([
            'submitted_by_user_id' => $staffA->id,
            'submitted_by_name' => $staffA->name,
            'district_id' => $districtA->id,
            'district_name' => $districtA->name,
            'cfa_submission_id' => $cfaA,
            'incubatee_name' => 'Incubatee A',
            'application_no' => 'ML-A-001',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $subB = (int) DB::table('market_linkage_submissions')->insertGetId([
            'submitted_by_user_id' => $staffB->id,
            'submitted_by_name' => $staffB->name,
            'district_id' => $districtB->id,
            'district_name' => $districtB->name,
            'cfa_submission_id' => $cfaB,
            'incubatee_name' => 'Incubatee B',
            'application_no' => 'ML-B-001',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('market_linkage_partners')->insert([
            [
                'market_linkage_submission_id' => $subA,
                'partner_name' => 'Amazon India',
                'linkage_mode' => 'online',
                'linkage_date' => '2026-05-10',
                'sort_order' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'market_linkage_submission_id' => $subA,
                'partner_name' => 'Local Mandi',
                'linkage_mode' => 'offline',
                'linkage_date' => '2026-05-12',
                'sort_order' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'market_linkage_submission_id' => $subB,
                'partner_name' => 'amazon india',
                'linkage_mode' => 'online',
                'linkage_date' => '2026-05-15',
                'sort_order' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'market_linkage_submission_id' => $subB,
                'partner_name' => 'Flipkart',
                'linkage_mode' => 'offline',
                'linkage_date' => '2026-05-16',
                'sort_order' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $filter = new ProgramDeliverablesFilter($fy->id, null, null, null, null, null);

        $stateReport = app(ProgramDeliverablesReportService::class)->build(
            $filter,
            ProgramDeliverablesScope::forUser(User::factory()->make(['role' => 'state_admin']))
        );
        $partnersState = collect($stateReport['rows'])->firstWhere('name', 'No of Partners outreach');
        $incubateesState = collect($stateReport['rows'])->firstWhere('name', 'Incubatees linked to online/offline Market');
        $this->assertNotNull($partnersState);
        $this->assertNotNull($incubateesState);
        $this->assertSame(3, $partnersState['achievement']);
        $this->assertSame(2, $incubateesState['achievement']);

        $districtReport = app(ProgramDeliverablesReportService::class)->build(
            $filter,
            ProgramDeliverablesScope::forUser($staffA)
        );
        $partnersDistrict = collect($districtReport['rows'])->firstWhere('name', 'No of Partners outreach');
        $incubateesDistrict = collect($districtReport['rows'])->firstWhere('name', 'Incubatees linked to online/offline Market');
        $this->assertNotNull($partnersDistrict);
        $this->assertNotNull($incubateesDistrict);
        $this->assertSame(2, $partnersDistrict['achievement']);
        $this->assertSame(1, $incubateesDistrict['achievement']);
    }
}
