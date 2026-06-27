<?php

namespace Tests\Feature;

use App\Models\Deliverable;
use App\Models\District;
use App\Models\DistrictDeliverableTarget;
use App\Models\FieldCoordinatorAttendanceReport;
use App\Models\FiscalYear;
use App\Models\Hub;
use App\Models\OnboardingBatch;
use App\Models\OnboardingBatchCfa;
use App\Models\ProgramDeliverableRowMetadata;
use App\Models\Service;
use App\Models\ServiceCase;
use App\Models\ServiceCategory;
use App\Models\StaffMonthlyTarget;
use App\Models\StateDeliverableTarget;
use App\Models\StateMonthlyTarget;
use App\Models\TrainingPackage;
use App\Models\TrainingPackageMonthSession;
use App\Models\User;
use App\Services\AppSettingsService;
use App\Services\Deliverables\ProgramDeliverableRowMetadataService;
use App\Services\Deliverables\ProgramDeliverablesAchievementBreakdownService;
use App\Services\Deliverables\ProgramDeliverablesActivityGuideService;
use App\Services\Deliverables\ProgramDeliverablesFilter;
use App\Services\Deliverables\ProgramDeliverablesScope;
use App\Services\LegacyApplicationServiceCaseSupport;
use App\Services\ProgramDeliverablesReportService;
use App\Services\StateMonthlyTargetIndicatorBootstrapService;
use Dompdf\Dompdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
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
        // MIS 1.3.1 counts female participants only (20 + 15).
        $this->assertSame(35, $participants['achievement']);
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

        $guide = app(ProgramDeliverablesActivityGuideService::class)
            ->build($report['rows'], 'state_admin', $fy->id);
        $guideRow = collect($guide['rows'])->firstWhere('serial', '4.2.4');
        $this->assertNotNull($guideRow);
        $this->assertStringContainsString('State ✓', (string) $guideRow['target_state_label']);
        $this->assertStringContainsString('350', (string) $guideRow['target_state_label']);
    }

    public function test_activity_guide_links_gst_when_only_svc_deliverable_row_exists(): void
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

        $child = ServiceCategory::query()->create(['slug' => 'legal-svc-only', 'name' => 'Legal', 'sort_order' => 1]);
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

        $guide = app(ProgramDeliverablesActivityGuideService::class)
            ->build($report['rows'], 'state_admin', $fy->id);
        $guideRow = collect($guide['rows'])->firstWhere('serial', '4.2.4');

        $this->assertNotNull($guideRow);
        $this->assertStringContainsString('State ✓', (string) $guideRow['target_state_label']);
        $this->assertStringContainsString('350', (string) $guideRow['target_state_label']);
        $this->assertStringNotContainsString('No deliverable row linked', (string) $guideRow['target_note']);
    }

    public function test_gst_achievement_counts_legacy_linked_service_case_in_district_scope(): void
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

        $hub = Hub::query()->create(['slug' => 'gst-legacy-hub', 'name' => 'Hub', 'sort_order' => 1]);
        $district = District::query()->create([
            'hub_id' => $hub->id,
            'slug' => 'gst-legacy-district',
            'name' => 'GST Legacy District',
            'sort_order' => 1,
        ]);

        $child = ServiceCategory::query()->create(['slug' => 'legal-gst-legacy', 'name' => 'Legal', 'sort_order' => 1]);
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

        $service = Service::query()->create([
            'service_category_id' => $child->id,
            'deliverable_id' => $svcGst->id,
            'code' => 'gst',
            'name' => 'GST',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $cfaId = (int) DB::table('cfa_submissions')->insertGetId([
            'district_id' => $district->id,
            'applicant_name' => 'Sunita Khulbey',
            'application_no' => '4090103M',
            'phone' => '9999999902',
            'payload' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        ServiceCase::query()->create([
            'cfa_submission_id' => $cfaId,
            'service_id' => $service->id,
            'status' => ServiceCase::STATUS_APPROVED,
            'approved_at' => '2026-05-09 00:17:23',
        ]);

        ServiceCase::query()->create([
            'cfa_submission_id' => null,
            'legacy_application_id' => 1416,
            'service_id' => $service->id,
            'status' => ServiceCase::STATUS_APPROVED,
            'approved_at' => '2026-05-09 00:17:52',
        ]);

        $this->partialMock(LegacyApplicationServiceCaseSupport::class, function ($mock): void {
            $mock->shouldReceive('legacyApplicationIdsForLaravelDistrictIds')
                ->andReturn([1416]);
            $mock->shouldReceive('applicantSnapshotsByLegacyApplicationIds')
                ->andReturn([
                    1416 => [
                        'name' => 'Paras singhal',
                        'application_no' => 'RBI1749712514',
                        'phone' => '',
                        'gender' => '',
                        'village' => '',
                        'block_name' => '',
                    ],
                ]);
            $mock->shouldReceive('incubateePreview')
                ->with(1416)
                ->andReturn([
                    'applicant_name' => 'Paras singhal',
                    'application_no' => 'RBI1749712514',
                    'district' => 'GST Legacy District',
                ]);
        });

        $filter = new ProgramDeliverablesFilter($fy->id, $district->id, null, null, null, null);
        $scope = ProgramDeliverablesScope::forUser(User::factory()->make(['role' => 'state_admin']));
        $report = app(ProgramDeliverablesReportService::class)->build($filter, $scope);
        $row = collect($report['rows'])->firstWhere('serial', '4.2.4');

        $this->assertNotNull($row);
        $this->assertSame(2, $row['achievement']);

        $breakdown = app(ProgramDeliverablesAchievementBreakdownService::class)->build($filter, $scope, '4.2.4');
        $applicants = collect($breakdown['records'] ?? [])->pluck('applicant')->all();
        $this->assertContains('Sunita Khulbey', $applicants);
        $this->assertContains('Paras singhal', $applicants);
    }

    public function test_district_month_filter_uses_staff_monthly_targets_when_fy_starts_mid_month(): void
    {
        $fy = FiscalYear::query()->updateOrCreate(
            ['code' => '2026-27'],
            [
                'name' => 'FY 2026-27',
                'starts_on' => '2026-04-02',
                'ends_on' => '2027-04-01',
                'is_active' => true,
            ]
        );

        $hub = Hub::query()->create(['slug' => 'cfa-month-hub', 'name' => 'Hub', 'sort_order' => 1]);
        $district = District::query()->create([
            'hub_id' => $hub->id,
            'slug' => 'cfa-month-district',
            'name' => 'CFA Month District',
            'sort_order' => 1,
        ]);

        $cfa = Deliverable::query()->firstOrCreate(
            ['code' => 'cfa'],
            [
                'sort_order' => 3,
                'name' => 'Call for Application',
                'mis_entry_label' => 'Call for Application',
                'is_active' => true,
            ]
        );

        DistrictDeliverableTarget::query()->create([
            'fiscal_year_id' => $fy->id,
            'district_id' => $district->id,
            'deliverable_id' => $cfa->id,
            'target_total' => 2100,
        ]);

        $staff = User::factory()->create([
            'role' => 'district_staff',
            'hub_id' => $hub->id,
            'district_id' => $district->id,
            'is_active' => true,
        ]);

        foreach ([1 => 70, 2 => 105] as $month => $count) {
            StaffMonthlyTarget::query()->create([
                'fiscal_year_id' => $fy->id,
                'user_id' => $staff->id,
                'deliverable_id' => $cfa->id,
                'month_number' => $month,
                'target_count' => $count,
            ]);
        }

        $scope = ProgramDeliverablesScope::forUser(User::factory()->make(['role' => 'state_admin']));

        $aprilReport = app(ProgramDeliverablesReportService::class)->build(
            new ProgramDeliverablesFilter($fy->id, $district->id, 4, 2026, null, null),
            $scope,
        );
        $aprilRow = collect($aprilReport['rows'])->firstWhere('serial', '1.1');
        $this->assertNotNull($aprilRow);
        $this->assertSame(70, $aprilRow['target']);

        $mayReport = app(ProgramDeliverablesReportService::class)->build(
            new ProgramDeliverablesFilter($fy->id, $district->id, 5, 2026, null, null),
            $scope,
        );
        $mayRow = collect($mayReport['rows'])->firstWhere('serial', '1.1');
        $this->assertNotNull($mayRow);
        $this->assertSame(105, $mayRow['target']);
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

    public function test_state_monthly_targets_reflect_on_deliverables_report_without_annual_row(): void
    {
        app(StateMonthlyTargetIndicatorBootstrapService::class)->ensureDeliverables();

        $fy = FiscalYear::query()->firstOrCreate(
            ['code' => '2026-27'],
            [
                'name' => 'FY 2026-27',
                'starts_on' => '2026-04-01',
                'ends_on' => '2027-03-31',
                'is_active' => true,
            ]
        );

        $deliverable = Deliverable::query()->where('code', 'technical_training_sessions')->firstOrFail();

        foreach (range(1, 12) as $month) {
            StateMonthlyTarget::query()->create([
                'fiscal_year_id' => $fy->id,
                'deliverable_id' => $deliverable->id,
                'month_number' => $month,
                'target_count' => 1,
            ]);
        }

        $filter = new ProgramDeliverablesFilter($fy->id, null, null, null, null, null);
        $scope = ProgramDeliverablesScope::forUser(User::factory()->make(['role' => 'state_admin']));
        $report = app(ProgramDeliverablesReportService::class)->build($filter, $scope);
        $row = collect($report['rows'])->firstWhere('serial', '3.3');

        $this->assertNotNull($row);
        $this->assertSame(12, $row['target']);
    }

    public function test_state_monthly_targets_use_exact_month_on_deliverables_report_when_month_filtered(): void
    {
        app(StateMonthlyTargetIndicatorBootstrapService::class)->ensureDeliverables();

        $fy = FiscalYear::query()->firstOrCreate(
            ['code' => '2026-27'],
            [
                'name' => 'FY 2026-27',
                'starts_on' => '2026-04-01',
                'ends_on' => '2027-03-31',
                'is_active' => true,
            ]
        );

        $deliverable = Deliverable::query()->where('code', 'social_media')->firstOrFail();

        StateDeliverableTarget::query()->updateOrCreate(
            ['fiscal_year_id' => $fy->id, 'deliverable_id' => $deliverable->id],
            ['target_total' => 120],
        );

        foreach (range(1, 12) as $month) {
            StateMonthlyTarget::query()->updateOrCreate(
                [
                    'fiscal_year_id' => $fy->id,
                    'deliverable_id' => $deliverable->id,
                    'month_number' => $month,
                ],
                ['target_count' => $month === 1 ? 5 : ($month === 2 ? 7 : 0)],
            );
        }

        $scope = ProgramDeliverablesScope::forUser(User::factory()->make(['role' => 'state_admin']));

        $aprilFilter = new ProgramDeliverablesFilter($fy->id, null, 4, 2026, null, null);
        $aprilReport = app(ProgramDeliverablesReportService::class)->build($aprilFilter, $scope);
        $aprilRow = collect($aprilReport['rows'])->firstWhere('serial', '10.1');
        $this->assertNotNull($aprilRow);
        $this->assertSame(5, $aprilRow['target']);

        $mayFilter = new ProgramDeliverablesFilter($fy->id, null, 5, 2026, null, null);
        $mayReport = app(ProgramDeliverablesReportService::class)->build($mayFilter, $scope);
        $mayRow = collect($mayReport['rows'])->firstWhere('serial', '10.1');
        $this->assertNotNull($mayRow);
        $this->assertSame(7, $mayRow['target']);
    }

    public function test_state_monthly_partner_outreach_uses_exact_month_on_deliverables_report(): void
    {
        app(StateMonthlyTargetIndicatorBootstrapService::class)->ensureDeliverables();

        $fy = FiscalYear::query()->firstOrCreate(
            ['code' => '2026-27'],
            [
                'name' => 'FY 2026-27',
                'starts_on' => '2026-04-01',
                'ends_on' => '2027-03-31',
                'is_active' => true,
            ]
        );

        $deliverable = Deliverable::query()->where('code', 'partners_outreach')->firstOrFail();

        StateMonthlyTarget::query()->updateOrCreate(
            [
                'fiscal_year_id' => $fy->id,
                'deliverable_id' => $deliverable->id,
                'month_number' => 1,
            ],
            ['target_count' => 11],
        );

        $filter = new ProgramDeliverablesFilter($fy->id, null, 4, 2026, null, null);
        $scope = ProgramDeliverablesScope::forUser(User::factory()->make(['role' => 'state_admin']));
        $report = app(ProgramDeliverablesReportService::class)->build($filter, $scope);
        $row = collect($report['rows'])->firstWhere('serial', '6.1');

        $this->assertNotNull($row);
        $this->assertSame(11, $row['target']);
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

    public function test_specialized_mentorship_achievement_counts_approved_service_cases(): void
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

        $hub = Hub::query()->create(['slug' => 'mentorship-hub', 'name' => 'Hub', 'sort_order' => 1]);
        $district = District::query()->create([
            'hub_id' => $hub->id,
            'slug' => 'mentorship-district',
            'name' => 'Mentorship District',
            'sort_order' => 1,
        ]);

        $child = ServiceCategory::query()->create(['slug' => 'mentorship_services', 'name' => 'Services', 'sort_order' => 1]);
        $svcDeliverable = Deliverable::query()->create([
            'sort_order' => 56,
            'code' => 'svc_specialized_mentorship_support',
            'name' => 'Specialized Mentorship Support',
            'mis_entry_label' => 'Specialized Mentorship Support',
            'is_active' => true,
        ]);

        $service = Service::query()->create([
            'service_category_id' => $child->id,
            'deliverable_id' => $svcDeliverable->id,
            'code' => 'specialized_mentorship_support',
            'name' => 'Specialized Mentorship Support',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        StateDeliverableTarget::query()->create([
            'fiscal_year_id' => $fy->id,
            'deliverable_id' => $svcDeliverable->id,
            'target_total' => 650,
        ]);

        $cfaId = (int) DB::table('cfa_submissions')->insertGetId([
            'district_id' => $district->id,
            'applicant_name' => 'Mentorship Applicant',
            'phone' => '9999999904',
            'payload' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        ServiceCase::query()->create([
            'cfa_submission_id' => $cfaId,
            'service_id' => $service->id,
            'status' => ServiceCase::STATUS_APPROVED,
            'reference_number' => 'SC-MENTOR-1',
            'submitted_at' => '2026-05-10 09:00:00',
        ]);

        ServiceCase::query()->create([
            'cfa_submission_id' => $cfaId,
            'service_id' => $service->id,
            'status' => ServiceCase::STATUS_PENDING_APPROVAL,
            'reference_number' => 'SC-MENTOR-PENDING',
            'submitted_at' => '2026-05-11 09:00:00',
        ]);

        $filter = new ProgramDeliverablesFilter($fy->id, null, null, null, null, null);
        $scope = ProgramDeliverablesScope::forUser(User::factory()->make(['role' => 'state_admin']));
        $report = app(ProgramDeliverablesReportService::class)->build($filter, $scope);
        $row = collect($report['rows'])->firstWhere('serial', '5.1');

        $this->assertNotNull($row);
        $this->assertSame(650, $row['target']);
        $this->assertSame(1, $row['achievement']);
    }

    public function test_other_support_services_achievement_counts_approved_service_cases(): void
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

        $hub = Hub::query()->create(['slug' => 'other-support-hub', 'name' => 'Hub', 'sort_order' => 1]);
        $district = District::query()->create([
            'hub_id' => $hub->id,
            'slug' => 'other-support-district',
            'name' => 'Other Support District',
            'sort_order' => 1,
        ]);

        $child = ServiceCategory::query()->create(['slug' => 'other_support_services', 'name' => 'Services', 'sort_order' => 1]);
        $svcDeliverable = Deliverable::query()->create([
            'sort_order' => 57,
            'code' => 'svc_other_support_services_labelling',
            'name' => 'Other Support Services - Labelling, Packaging, Logo Designing etc.',
            'mis_entry_label' => 'Other Support Services - Labelling, Packaging, Logo Designing etc.',
            'is_active' => true,
        ]);

        $service = Service::query()->create([
            'service_category_id' => $child->id,
            'deliverable_id' => $svcDeliverable->id,
            'code' => 'other_support_services_labelling_packaging_logo_designing_etc',
            'name' => 'Other Support Services - Labelling, Packaging, Logo Designing etc.',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $cfaId = (int) DB::table('cfa_submissions')->insertGetId([
            'district_id' => $district->id,
            'applicant_name' => 'Other Support Applicant',
            'phone' => '9999999905',
            'payload' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        ServiceCase::query()->create([
            'cfa_submission_id' => $cfaId,
            'service_id' => $service->id,
            'status' => ServiceCase::STATUS_APPROVED,
            'reference_number' => 'SC-OTHER-1',
            'submitted_at' => '2026-06-01 10:00:00',
        ]);

        ServiceCase::query()->create([
            'cfa_submission_id' => $cfaId,
            'service_id' => $service->id,
            'status' => ServiceCase::STATUS_COMPLETED,
            'reference_number' => 'SC-OTHER-2',
            'submitted_at' => '2026-06-02 10:00:00',
        ]);

        $filter = new ProgramDeliverablesFilter($fy->id, null, null, null, null, null);
        $scope = ProgramDeliverablesScope::forUser(User::factory()->make(['role' => 'state_admin']));
        $report = app(ProgramDeliverablesReportService::class)->build($filter, $scope);
        $row = collect($report['rows'])->firstWhere('serial', '9.2');

        $this->assertNotNull($row);
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
            'onboarding_date' => '2026-05-15',
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

    public function test_program_deliverables_2_1_1_counts_potential_lakhpati_onboardings(): void
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

        $hub = Hub::query()->create(['slug' => 'lakhpati-onb-hub', 'name' => 'Hub', 'sort_order' => 1]);
        $district = District::query()->create([
            'hub_id' => $hub->id,
            'slug' => 'lakhpati-onb-district',
            'name' => 'Lakhpati District',
            'sort_order' => 1,
        ]);

        $this->seedLockedOnboarding($hub, $district, [
            'applicant_name' => 'SHG Group',
            'source' => 'phase3',
            'payload' => ['category' => 'SHG'],
        ]);
        $this->seedLockedOnboarding($hub, $district, [
            'applicant_name' => 'Member Individual',
            'source' => 'phase3',
            'payload' => ['category' => 'Individual', 'is_member' => 'Yes'],
        ]);
        $this->seedLockedOnboarding($hub, $district, [
            'applicant_name' => 'Lakhpati Only',
            'source' => 'phase3',
            'payload' => ['category' => 'Individual', 'lakhpati' => 'Yes'],
        ]);
        $this->seedLockedOnboarding($hub, $district, [
            'applicant_name' => 'Legacy SHG',
            'source' => 'legacy_phase2',
            'payload' => ['category' => 'SHG'],
        ]);

        $filter = new ProgramDeliverablesFilter($fy->id, null, null, null, null, null);
        $scope = ProgramDeliverablesScope::forUser(User::factory()->make(['role' => 'state_admin']));
        $report = app(ProgramDeliverablesReportService::class)->build($filter, $scope);

        $parent = collect($report['rows'])->firstWhere('serial', '2.1');
        $child = collect($report['rows'])->firstWhere('serial', '2.1.1');

        $this->assertNotNull($parent);
        $this->assertNotNull($child);
        $this->assertSame(4, $parent['achievement']);
        $this->assertSame(2, $child['achievement']);

        $breakdown = app(ProgramDeliverablesAchievementBreakdownService::class)->build($filter, $scope, '2.1.1');
        $this->assertSame(2, $breakdown['total']);
        $this->assertSame('Potential Lakhpati Didi/ SHG/CBO onboardings', $breakdown['source_type_label']);
    }

    public function test_program_deliverables_2_1_1_counts_legacy_lakhpati_and_member_yes(): void
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

        $hub = Hub::query()->create(['slug' => 'legacy-lakhpati-hub', 'name' => 'Hub', 'sort_order' => 1]);
        $district = District::query()->create([
            'hub_id' => $hub->id,
            'slug' => 'legacy-lakhpati-district',
            'name' => 'Legacy Lakhpati District',
            'sort_order' => 1,
        ]);

        $this->seedLockedOnboarding($hub, $district, [
            'applicant_name' => 'Km Pushpa Legacy',
            'source' => 'legacy_phase2',
            'payload' => ['category' => 'Individual', 'lakhpati' => 'Yes', 'is_shg_member' => 'Yes'],
        ]);
        $this->seedLockedOnboarding($hub, $district, [
            'applicant_name' => 'Legacy Lakhpati Only',
            'source' => 'legacy_phase2',
            'payload' => ['category' => 'Individual', 'lakhpati' => 'Yes', 'is_shg_member' => 'No'],
        ]);

        $filter = new ProgramDeliverablesFilter($fy->id, null, null, null, null, null);
        $scope = ProgramDeliverablesScope::forUser(User::factory()->make(['role' => 'state_admin']));
        $report = app(ProgramDeliverablesReportService::class)->build($filter, $scope);
        $child = collect($report['rows'])->firstWhere('serial', '2.1.1');

        $this->assertNotNull($child);
        $this->assertSame(1, $child['achievement']);
    }

    public function test_bst_sessions_target_uses_planned_month_sessions_sum_for_fy_and_filters(): void
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

        $hub = Hub::query()->create(['slug' => 'bst-target-hub', 'name' => 'BST Target Hub', 'sort_order' => 1]);
        $districtA = District::query()->create([
            'hub_id' => $hub->id,
            'slug' => 'bst-target-a',
            'name' => 'BST Target A',
            'sort_order' => 1,
        ]);
        $districtB = District::query()->create([
            'hub_id' => $hub->id,
            'slug' => 'bst-target-b',
            'name' => 'BST Target B',
            'sort_order' => 2,
        ]);

        foreach ([
            [$districtA->id, 2026, 5, 1, 'A May 1'],
            [$districtA->id, 2026, 5, 2, 'A May 2'],
            [$districtA->id, 2026, 6, 1, 'A Jun 1'],
            [$districtB->id, 2026, 5, 1, 'B May 1'],
        ] as [$districtId, $year, $month, $sortOrder, $name]) {
            TrainingPackageMonthSession::query()->create([
                'district_id' => $districtId,
                'calendar_year' => $year,
                'calendar_month' => $month,
                'sort_order' => $sortOrder,
                'session_name' => $name,
                'is_extra' => false,
            ]);
        }

        $scope = ProgramDeliverablesScope::forUser(User::factory()->make(['role' => 'state_admin']));

        $fullFyReport = app(ProgramDeliverablesReportService::class)->build(
            new ProgramDeliverablesFilter($fy->id, null, null, null, null, null),
            $scope,
        );
        $fullFyRow = collect($fullFyReport['rows'])->firstWhere('serial', '3.1');
        $this->assertSame(4, $fullFyRow['target']);

        $mayReport = app(ProgramDeliverablesReportService::class)->build(
            new ProgramDeliverablesFilter($fy->id, null, 5, 2026, null, null),
            $scope,
        );
        $mayRow = collect($mayReport['rows'])->firstWhere('serial', '3.1');
        $this->assertSame(3, $mayRow['target']);

        $districtMayReport = app(ProgramDeliverablesReportService::class)->build(
            new ProgramDeliverablesFilter($fy->id, (int) $districtA->id, 5, 2026, null, null),
            $scope,
        );
        $districtMayRow = collect($districtMayReport['rows'])->firstWhere('serial', '3.1');
        $this->assertSame(2, $districtMayRow['target']);
    }

    public function test_program_deliverables_3_1_and_3_2_count_bst_sessions_and_unique_participants(): void
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

        $hub = Hub::query()->create(['slug' => 'bst-deliv-hub', 'name' => 'BST Hub', 'sort_order' => 1]);
        $district = District::query()->create([
            'hub_id' => $hub->id,
            'slug' => 'bst-deliv-district',
            'name' => 'BST District',
            'sort_order' => 1,
        ]);
        $staff = User::factory()->create([
            'role' => 'district_staff',
            'district_id' => $district->id,
            'hub_id' => $hub->id,
            'is_active' => true,
        ]);

        TrainingPackageMonthSession::query()->create([
            'district_id' => $district->id,
            'calendar_year' => 2026,
            'calendar_month' => 5,
            'sort_order' => 1,
            'session_name' => 'Empty planned slot',
        ]);

        $this->seedBstTrainingPackage($district, $staff, [
            'event_date' => '2026-05-10',
            'training_batch_name' => 'Batch Alpha',
            'training_packages' => ['t1'],
            'selected_incubatee_ids' => [101, 102],
            'selected_incubatees_snapshot' => [
                ['incubatee_id' => 101, 'name' => 'Incubatee A'],
                ['incubatee_id' => 102, 'name' => 'Incubatee B'],
            ],
        ]);
        $this->seedBstTrainingPackage($district, $staff, [
            'event_date' => '2026-06-12',
            'training_batch_name' => 'Batch Beta',
            'training_packages' => ['t2', 't3'],
            'selected_incubatee_ids' => [101, 103],
            'selected_incubatees_snapshot' => [
                ['incubatee_id' => 101, 'name' => 'Incubatee A'],
                ['incubatee_id' => 103, 'name' => 'Incubatee C'],
            ],
        ]);

        $filter = new ProgramDeliverablesFilter($fy->id, null, null, null, null, null);
        $scope = ProgramDeliverablesScope::forUser(User::factory()->make(['role' => 'state_admin']));
        $report = app(ProgramDeliverablesReportService::class)->build($filter, $scope);

        $sessions = collect($report['rows'])->firstWhere('serial', '3.1');
        $participants = collect($report['rows'])->firstWhere('serial', '3.2');

        $this->assertNotNull($sessions);
        $this->assertNotNull($participants);
        $this->assertSame(1, $sessions['target']);
        $this->assertSame(2, $sessions['achievement']);
        $this->assertSame(3, $participants['achievement']);

        $sessionsBreakdown = app(ProgramDeliverablesAchievementBreakdownService::class)->build($filter, $scope, '3.1');
        $this->assertSame(2, $sessionsBreakdown['total']);
        $this->assertCount(2, $sessionsBreakdown['records']);
        $this->assertSame('Business skills training sessions (conducted)', $sessionsBreakdown['source_type_label']);

        $participantsBreakdown = app(ProgramDeliverablesAchievementBreakdownService::class)->build($filter, $scope, '3.2');
        $this->assertSame(3, $participantsBreakdown['total']);
        $this->assertSame(3, $participants['achievement']);
        $this->assertCount(3, $participantsBreakdown['records']);
        $this->assertCount(
            $participantsBreakdown['total'],
            $participantsBreakdown['records'],
            'Breakdown records must include every unique incubatee, not a capped subset',
        );
        $districtSum = collect($participantsBreakdown['by_district'])->sum('count');
        $this->assertSame(3, $districtSum, 'District unique counts must sum to statewide unique total');

        $incubateeA = collect($participantsBreakdown['records'])->firstWhere('applicant', 'Incubatee A');
        $this->assertNotNull($incubateeA);
        $this->assertSame(2, (int) ($incubateeA['session_count'] ?? 0));
        $this->assertCount(2, (array) ($incubateeA['sessions'] ?? []));
        $sessionsText = implode("\n", (array) ($incubateeA['sessions'] ?? []));
        $this->assertStringContainsString('Batch Alpha', $sessionsText);
        $this->assertStringContainsString('Batch Beta', $sessionsText);
    }

    public function test_program_deliverables_3_2_breakdown_includes_all_unique_participants_beyond_100(): void
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

        $hub = Hub::query()->create(['slug' => 'bst-cap-hub', 'name' => 'BST Cap Hub', 'sort_order' => 1]);
        $district = District::query()->create([
            'hub_id' => $hub->id,
            'slug' => 'bst-cap-district',
            'name' => 'BST Cap District',
            'sort_order' => 1,
        ]);
        $staff = User::factory()->create([
            'role' => 'district_staff',
            'district_id' => $district->id,
            'hub_id' => $hub->id,
            'is_active' => true,
        ]);

        $ids = range(1, 105);
        $snapshot = array_map(
            static fn (int $id): array => ['incubatee_id' => $id, 'name' => 'Incubatee '.$id],
            $ids,
        );

        $this->seedBstTrainingPackage($district, $staff, [
            'event_date' => '2026-05-15',
            'training_batch_name' => 'Large batch',
            'selected_incubatee_ids' => $ids,
            'selected_incubatees_snapshot' => $snapshot,
        ]);

        $filter = new ProgramDeliverablesFilter($fy->id, null, null, null, null, null);
        $scope = ProgramDeliverablesScope::forUser(User::factory()->make(['role' => 'state_admin']));
        $breakdown = app(ProgramDeliverablesAchievementBreakdownService::class)->build($filter, $scope, '3.2');

        $this->assertSame(105, $breakdown['total']);
        $this->assertCount(105, $breakdown['records']);
    }

    public function test_program_deliverables_technical_training_potential_lakhpati_counts_submitted_sessions(): void
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

        $hub = Hub::query()->create(['slug' => 'tt-hub', 'name' => 'TT Hub', 'sort_order' => 1]);
        $district = District::query()->create([
            'hub_id' => $hub->id,
            'slug' => 'tt-district',
            'name' => 'TT District',
            'sort_order' => 1,
        ]);
        $staff = User::factory()->create([
            'role' => 'district_staff',
            'district_id' => $district->id,
            'hub_id' => $hub->id,
            'is_active' => true,
        ]);

        DB::table('potential_lakhpati_technical_trainings')->insert([
            [
                'submitted_by_user_id' => $staff->id,
                'submitted_by_name' => (string) $staff->name,
                'session_date' => '2026-05-10',
                'district_id' => $district->id,
                'district_name' => $district->name,
                'district_block_id' => null,
                'block' => 'Block A',
                'area' => 'Village A',
                'workshop_mode' => 'physical',
                'requesting_agency_type' => 'reap',
                'session_title' => 'REAP food processing',
                'session_brief' => null,
                'male_participants' => 5,
                'female_participants' => 20,
                'participants_total' => 25,
                'participants_json' => json_encode([]),
                'attendance_media_json' => json_encode([]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'submitted_by_user_id' => $staff->id,
                'submitted_by_name' => (string) $staff->name,
                'session_date' => '2026-06-12',
                'district_id' => $district->id,
                'district_name' => $district->name,
                'district_block_id' => null,
                'block' => 'Block B',
                'area' => 'Village B',
                'workshop_mode' => 'virtual',
                'requesting_agency_type' => 'nrlm_usrlm',
                'session_title' => 'NRLM packaging',
                'session_brief' => 'Virtual session',
                'male_participants' => 0,
                'female_participants' => 0,
                'participants_total' => 0,
                'participants_json' => json_encode([]),
                'attendance_media_json' => json_encode([]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $filter = new ProgramDeliverablesFilter($fy->id, null, null, null, null, null);
        $scope = ProgramDeliverablesScope::forUser(User::factory()->make(['role' => 'state_admin']));
        $report = app(ProgramDeliverablesReportService::class)->build($filter, $scope);

        $row = collect($report['rows'])->firstWhere('name', 'Technical Trainings to Potential Lakhpati Didis/ SHG Members/ CBOs');
        $this->assertNotNull($row);
        $this->assertSame(2, (int) ($row['achievement'] ?? 0));

        $breakdown = app(ProgramDeliverablesAchievementBreakdownService::class)->build($filter, $scope, (string) $row['serial']);
        $this->assertSame(2, $breakdown['total']);
        $this->assertCount(2, $breakdown['records']);
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
            'participants_female_count' => 6,
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
            ->assertJsonPath('source_type_label', 'Field work visits & block workshops');
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
        if (! class_exists(Dompdf::class)) {
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

    public function test_social_media_post_breakdown_returns_logged_posts(): void
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
        ]);

        $admin = User::factory()->create(['role' => 'state_admin', 'is_active' => true]);

        $this->actingAs($admin)
            ->getJson(route('admin.deliverables.breakdown', [
                'fiscal_year_id' => $fy->id,
                'serial' => '10.1',
            ]))
            ->assertOk()
            ->assertJsonPath('serial', '10.1')
            ->assertJsonPath('total', 2)
            ->assertJsonPath('source_type_label', 'Logged social media posts')
            ->assertJsonCount(2, 'records');
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
            'status' => 'approved',
            'submitted_at' => now(),
            'approved_at' => now(),
            'approved_by' => $staffA->id,
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
            'status' => 'approved',
            'submitted_at' => now(),
            'approved_at' => now(),
            'approved_by' => $staffB->id,
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
        $this->assertSame(0, $partnersState['achievement']);
        $this->assertSame(2, $incubateesState['achievement']);

        $districtReport = app(ProgramDeliverablesReportService::class)->build(
            $filter,
            ProgramDeliverablesScope::forUser($staffA)
        );
        $partnersDistrict = collect($districtReport['rows'])->firstWhere('name', 'No of Partners outreach');
        $incubateesDistrict = collect($districtReport['rows'])->firstWhere('name', 'Incubatees linked to online/offline Market');
        $this->assertNotNull($partnersDistrict);
        $this->assertNotNull($incubateesDistrict);
        $this->assertSame(0, $partnersDistrict['achievement']);
        $this->assertSame(1, $incubateesDistrict['achievement']);
    }

    public function test_market_linkage_incubatee_deliverable_counts_all_approved_linkages_regardless_of_linkage_date(): void
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

        $hub = Hub::query()->create(['slug' => 'ml-date-hub', 'name' => 'ML Date Hub', 'sort_order' => 99]);
        $district = District::query()->create(['hub_id' => $hub->id, 'slug' => 'ml-date-dist', 'name' => 'ML Date District', 'sort_order' => 99]);
        $staff = User::factory()->create([
            'role' => 'district_staff',
            'hub_id' => $hub->id,
            'district_id' => $district->id,
            'is_active' => true,
        ]);

        $cfaId = (int) DB::table('cfa_submissions')->insertGetId([
            'district_id' => $district->id,
            'application_no' => 'ML-DATE-001',
            'applicant_name' => 'Legacy Link Incubatee',
            'phone' => '9000000099',
            'payload' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $submissionId = (int) DB::table('market_linkage_submissions')->insertGetId([
            'submitted_by_user_id' => $staff->id,
            'submitted_by_name' => $staff->name,
            'district_id' => $district->id,
            'district_name' => $district->name,
            'cfa_submission_id' => $cfaId,
            'incubatee_name' => 'Legacy Link Incubatee',
            'application_no' => 'ML-DATE-001',
            'status' => 'approved',
            'submitted_at' => now(),
            'approved_at' => now(),
            'approved_by' => $staff->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('market_linkage_partners')->insert([
            'market_linkage_submission_id' => $submissionId,
            'partner_name' => 'Pre Phase3 Partner',
            'linkage_mode' => 'offline',
            'linkage_date' => '2025-12-01',
            'sort_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $filter = new ProgramDeliverablesFilter($fy->id, null, null, null, null, null);
        $report = app(ProgramDeliverablesReportService::class)->build(
            $filter,
            ProgramDeliverablesScope::forUser($staff)
        );

        $incubatees = collect($report['rows'])->firstWhere('name', 'Incubatees linked to online/offline Market');
        $this->assertNotNull($incubatees);
        $this->assertSame(1, $incubatees['achievement']);
    }

    public function test_market_linkage_incubatee_breakdown_splits_offline_and_online(): void
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

        $hub = Hub::query()->create(['slug' => 'ml-mode-hub', 'name' => 'ML Mode Hub', 'sort_order' => 99]);
        $district = District::query()->create(['hub_id' => $hub->id, 'slug' => 'ml-mode-dist', 'name' => 'ML Mode District', 'sort_order' => 99]);
        $staff = User::factory()->create([
            'role' => 'district_staff',
            'hub_id' => $hub->id,
            'district_id' => $district->id,
            'is_active' => true,
        ]);

        $cfaOffline = (int) DB::table('cfa_submissions')->insertGetId([
            'district_id' => $district->id,
            'application_no' => 'ML-OFF-001',
            'applicant_name' => 'Offline Incubatee',
            'phone' => '9000000101',
            'payload' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $cfaOnline = (int) DB::table('cfa_submissions')->insertGetId([
            'district_id' => $district->id,
            'application_no' => 'ML-ON-001',
            'applicant_name' => 'Online Incubatee',
            'phone' => '9000000102',
            'payload' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach ([
            [$cfaOffline, 'Offline Incubatee', 'ML-OFF-001', 'offline', 'Local Buyer'],
            [$cfaOnline, 'Online Incubatee', 'ML-ON-001', 'online', 'Amazon'],
        ] as [$cfaId, $name, $appNo, $mode, $partner]) {
            $submissionId = (int) DB::table('market_linkage_submissions')->insertGetId([
                'submitted_by_user_id' => $staff->id,
                'submitted_by_name' => $staff->name,
                'district_id' => $district->id,
                'district_name' => $district->name,
                'cfa_submission_id' => $cfaId,
                'incubatee_name' => $name,
                'application_no' => $appNo,
                'status' => 'approved',
                'submitted_at' => now(),
                'approved_at' => now(),
                'approved_by' => $staff->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('market_linkage_partners')->insert([
                'market_linkage_submission_id' => $submissionId,
                'partner_name' => $partner,
                'linkage_mode' => $mode,
                'linkage_date' => '2026-05-16',
                'sort_order' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $filter = new ProgramDeliverablesFilter($fy->id, $district->id, null, null, null, null);
        $report = app(ProgramDeliverablesReportService::class)->build(
            $filter,
            ProgramDeliverablesScope::forUser($staff),
        );
        $row = collect($report['rows'])->firstWhere('name', 'Incubatees linked to online/offline Market');
        $this->assertNotNull($row);

        $breakdown = app(ProgramDeliverablesAchievementBreakdownService::class)->build(
            $filter,
            ProgramDeliverablesScope::forUser($staff),
            (string) $row['serial'],
        );

        $this->assertSame(2, $breakdown['total']);
        $this->assertSame(1, $breakdown['offline_incubatees'] ?? null);
        $this->assertSame(1, $breakdown['online_incubatees'] ?? null);
        $offlineRow = collect($breakdown['by_service'] ?? [])->firstWhere('service', 'Offline');
        $onlineRow = collect($breakdown['by_service'] ?? [])->firstWhere('service', 'Online');
        $this->assertSame(1, $offlineRow['count'] ?? null);
        $this->assertSame(1, $onlineRow['count'] ?? null);
        $this->assertSame('Offline', $breakdown['records'][0]['linkage_mode'] ?? null);
    }

    public function test_market_linkage_incubatee_deliverable_includes_orphan_service_cases(): void
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

        $hub = Hub::query()->create(['slug' => 'ml-sc-hub', 'name' => 'ML SC Hub', 'sort_order' => 99]);
        $district = District::query()->create(['hub_id' => $hub->id, 'slug' => 'ml-sc-dist', 'name' => 'ML SC District', 'sort_order' => 99]);
        $staff = User::factory()->create([
            'role' => 'district_staff',
            'hub_id' => $hub->id,
            'district_id' => $district->id,
            'is_active' => true,
        ]);

        $category = ServiceCategory::query()->create(['slug' => 'ml_sc_services', 'name' => 'ML SC Services', 'sort_order' => 99]);
        $service = Service::query()->create([
            'service_category_id' => $category->id,
            'code' => 'incubatees_linked_to_online_offline_market',
            'name' => 'Incubatees linked to online/offline Market',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $cfaOnline = (int) DB::table('cfa_submissions')->insertGetId([
            'district_id' => $district->id,
            'application_no' => 'ML-SC-ON-001',
            'applicant_name' => 'Online ML Incubatee',
            'phone' => '9000000201',
            'payload' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $cfaOfflineSc = (int) DB::table('cfa_submissions')->insertGetId([
            'district_id' => $district->id,
            'application_no' => 'ML-SC-OFF-001',
            'applicant_name' => 'Offline SC Incubatee',
            'phone' => '9000000202',
            'payload' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $submissionId = (int) DB::table('market_linkage_submissions')->insertGetId([
            'submitted_by_user_id' => $staff->id,
            'submitted_by_name' => $staff->name,
            'district_id' => $district->id,
            'district_name' => $district->name,
            'cfa_submission_id' => $cfaOnline,
            'incubatee_name' => 'Online ML Incubatee',
            'application_no' => 'ML-SC-ON-001',
            'status' => 'approved',
            'submitted_at' => now(),
            'approved_at' => now(),
            'approved_by' => $staff->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('market_linkage_partners')->insert([
            'market_linkage_submission_id' => $submissionId,
            'partner_name' => 'Amazon',
            'linkage_mode' => 'online',
            'linkage_date' => '2026-05-16',
            'sort_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        ServiceCase::query()->create([
            'cfa_submission_id' => $cfaOfflineSc,
            'service_id' => $service->id,
            'status' => ServiceCase::STATUS_APPROVED,
            'reference_number' => 'SC-ML-OFF-1',
            'payload' => ['m' => 'offline', 'p' => 'Local Buyer'],
            'submitted_at' => '2026-05-16 09:00:00',
            'approved_at' => '2026-05-16 10:00:00',
        ]);

        $filter = new ProgramDeliverablesFilter($fy->id, $district->id, null, null, null, null);
        $scope = ProgramDeliverablesScope::forUser($staff);
        $report = app(ProgramDeliverablesReportService::class)->build($filter, $scope);
        $row = collect($report['rows'])->firstWhere('name', 'Incubatees linked to online/offline Market');

        $this->assertNotNull($row);
        $this->assertSame(2, $row['achievement']);

        $breakdown = app(ProgramDeliverablesAchievementBreakdownService::class)->build(
            $filter,
            $scope,
            (string) $row['serial'],
        );

        $this->assertSame(2, $breakdown['total']);
        $this->assertSame(1, $breakdown['offline_incubatees'] ?? null);
        $this->assertSame(1, $breakdown['online_incubatees'] ?? null);
        $this->assertTrue(
            collect($breakdown['records'] ?? [])->contains(
                fn (array $record): bool => ($record['applicant'] ?? '') === 'Offline SC Incubatee'
                    && ($record['linkage_mode'] ?? '') === 'Offline',
            ),
        );
    }

    public function test_market_linkage_incubatee_deliverable_does_not_double_count_when_both_ml_and_service_case_exist(): void
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

        $hub = Hub::query()->create(['slug' => 'ml-dedupe-hub', 'name' => 'ML Dedupe Hub', 'sort_order' => 99]);
        $district = District::query()->create(['hub_id' => $hub->id, 'slug' => 'ml-dedupe-dist', 'name' => 'ML Dedupe District', 'sort_order' => 99]);
        $staff = User::factory()->create([
            'role' => 'district_staff',
            'hub_id' => $hub->id,
            'district_id' => $district->id,
            'is_active' => true,
        ]);

        $category = ServiceCategory::query()->create(['slug' => 'ml_dedupe_services', 'name' => 'ML Dedupe Services', 'sort_order' => 99]);
        $service = Service::query()->create([
            'service_category_id' => $category->id,
            'code' => 'incubatees_linked_to_online_offline_market',
            'name' => 'Incubatees linked to online/offline Market',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $cfaId = (int) DB::table('cfa_submissions')->insertGetId([
            'district_id' => $district->id,
            'application_no' => 'ML-DEDUPE-001',
            'applicant_name' => 'Shared Incubatee',
            'phone' => '9000000301',
            'payload' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $submissionId = (int) DB::table('market_linkage_submissions')->insertGetId([
            'submitted_by_user_id' => $staff->id,
            'submitted_by_name' => $staff->name,
            'district_id' => $district->id,
            'district_name' => $district->name,
            'cfa_submission_id' => $cfaId,
            'incubatee_name' => 'Shared Incubatee',
            'application_no' => 'ML-DEDUPE-001',
            'status' => 'approved',
            'submitted_at' => now(),
            'approved_at' => now(),
            'approved_by' => $staff->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('market_linkage_partners')->insert([
            'market_linkage_submission_id' => $submissionId,
            'partner_name' => 'Amazon',
            'linkage_mode' => 'online',
            'linkage_date' => '2026-05-16',
            'sort_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        ServiceCase::query()->create([
            'cfa_submission_id' => $cfaId,
            'service_id' => $service->id,
            'status' => ServiceCase::STATUS_APPROVED,
            'reference_number' => 'SC-ML-DEDUPE-1',
            'payload' => ['m' => 'offline', 'p' => 'Legacy Offline Partner'],
            'submitted_at' => '2026-05-16 09:00:00',
            'approved_at' => '2026-05-16 10:00:00',
        ]);

        $filter = new ProgramDeliverablesFilter($fy->id, $district->id, null, null, null, null);
        $scope = ProgramDeliverablesScope::forUser($staff);
        $report = app(ProgramDeliverablesReportService::class)->build($filter, $scope);
        $row = collect($report['rows'])->firstWhere('name', 'Incubatees linked to online/offline Market');

        $this->assertNotNull($row);
        $this->assertSame(1, $row['achievement']);

        $breakdown = app(ProgramDeliverablesAchievementBreakdownService::class)->build(
            $filter,
            $scope,
            (string) $row['serial'],
        );

        $this->assertSame(1, $breakdown['total']);
        $this->assertSame(0, $breakdown['offline_incubatees'] ?? null);
        $this->assertSame(1, $breakdown['online_incubatees'] ?? null);
    }

    public function test_partner_outreach_deliverable_counts_outreach_and_onboarded_separately(): void
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

        $sanjna = User::factory()->create([
            'role' => 'state_staff',
            'name' => 'Sanjna Mishra',
            'is_active' => true,
        ]);

        DB::table('marketing_partner_outreach_entries')->insert([
            'outreach_date' => '2026-05-10',
            'partner_name' => 'Himalayan Foods Pvt Ltd',
            'partner_designation' => 'Procurement Head',
            'partner_link' => null,
            'cohort_or_sector' => 'food_processing',
            'cohort_or_sector_other' => null,
            'poc_name' => 'Ravi',
            'poc_phone' => '9876543210',
            'remarks' => null,
            'status' => 'outreach',
            'onboarding_date' => null,
            'submitted_by_user_id' => $sanjna->id,
            'submitted_by_name' => $sanjna->name,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('marketing_partner_outreach_entries')->insert([
            'outreach_date' => '2026-05-12',
            'partner_name' => 'Uttarakhand Handloom Co',
            'partner_designation' => 'Director',
            'partner_link' => null,
            'cohort_or_sector' => 'handloom_handicraft',
            'cohort_or_sector_other' => null,
            'poc_name' => 'Meera',
            'poc_phone' => '9876543211',
            'remarks' => null,
            'status' => 'onboarded_mou',
            'onboarding_date' => '2026-05-20',
            'submitted_by_user_id' => $sanjna->id,
            'submitted_by_name' => $sanjna->name,
            'status_updated_by_user_id' => $sanjna->id,
            'status_updated_by_name' => $sanjna->name,
            'status_updated_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $filter = new ProgramDeliverablesFilter($fy->id, null, null, null, null, null);
        $report = app(ProgramDeliverablesReportService::class)->build(
            $filter,
            ProgramDeliverablesScope::forUser(User::factory()->make(['role' => 'state_admin']))
        );

        $outreach = collect($report['rows'])->firstWhere('name', 'No of Partners outreach');
        $onboarded = collect($report['rows'])->firstWhere('name', 'Marketing Partners Onboarded through (LoA/LoI/MoU)');

        $this->assertNotNull($outreach);
        $this->assertNotNull($onboarded);
        $this->assertSame(2, $outreach['achievement']);
        $this->assertSame(1, $onboarded['achievement']);
    }

    public function test_market_linkage_partner_names_no_longer_count_toward_partner_outreach_deliverable(): void
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

        $hub = Hub::query()->create(['slug' => 'ml-partner-hub', 'name' => 'ML Partner Hub', 'sort_order' => 98]);
        $district = District::query()->create(['hub_id' => $hub->id, 'slug' => 'ml-partner-dist', 'name' => 'ML Partner District', 'sort_order' => 98]);
        $staff = User::factory()->create([
            'role' => 'district_staff',
            'hub_id' => $hub->id,
            'district_id' => $district->id,
            'is_active' => true,
        ]);

        $cfaId = (int) DB::table('cfa_submissions')->insertGetId([
            'district_id' => $district->id,
            'application_no' => 'ML-PARTNER-001',
            'applicant_name' => 'Partner Test Incubatee',
            'phone' => '9000000088',
            'payload' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $submissionId = (int) DB::table('market_linkage_submissions')->insertGetId([
            'submitted_by_user_id' => $staff->id,
            'submitted_by_name' => $staff->name,
            'district_id' => $district->id,
            'district_name' => $district->name,
            'cfa_submission_id' => $cfaId,
            'incubatee_name' => 'Partner Test Incubatee',
            'application_no' => 'ML-PARTNER-001',
            'status' => 'approved',
            'submitted_at' => now(),
            'approved_at' => now(),
            'approved_by' => $staff->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('market_linkage_partners')->insert([
            [
                'market_linkage_submission_id' => $submissionId,
                'partner_name' => 'Old Date Partner',
                'linkage_mode' => 'offline',
                'linkage_date' => '2025-11-01',
                'sort_order' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'market_linkage_submission_id' => $submissionId,
                'partner_name' => 'old date partner',
                'linkage_mode' => 'online',
                'linkage_date' => '2026-05-01',
                'sort_order' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $filter = new ProgramDeliverablesFilter($fy->id, null, null, null, null, null);
        $report = app(ProgramDeliverablesReportService::class)->build(
            $filter,
            ProgramDeliverablesScope::forUser($staff)
        );

        $partners = collect($report['rows'])->firstWhere('name', 'No of Partners outreach');
        $this->assertNotNull($partners);
        $this->assertSame(0, $partners['achievement']);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function seedLockedOnboarding(Hub $hub, District $district, array $overrides = []): void
    {
        $payload = (array) ($overrides['payload'] ?? []);
        unset($overrides['payload']);

        $cfaId = (int) DB::table('cfa_submissions')->insertGetId(array_merge([
            'district_id' => $district->id,
            'applicant_name' => 'Applicant',
            'phone' => '9876543210',
            'source' => 'phase3',
            'payload' => json_encode($payload),
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));

        $batch = OnboardingBatch::query()->create([
            'hub_id' => $hub->id,
            'district_id' => $district->id,
            'name' => 'Batch '.$cfaId,
            'target_size' => 1,
            'status' => 'locked',
            'locked_at' => '2026-05-15 10:00:00',
            'onboarding_date' => '2026-05-15',
        ]);

        OnboardingBatchCfa::query()->create([
            'onboarding_batch_id' => $batch->id,
            'cfa_submission_id' => $cfaId,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function seedBstTrainingPackage(District $district, User $staff, array $overrides = []): void
    {
        TrainingPackage::query()->create(array_merge([
            'submitted_by_user_id' => $staff->id,
            'submitted_by_name' => (string) $staff->name,
            'event_date' => '2026-05-10',
            'district_id' => $district->id,
            'district_name' => $district->name,
            'training_package' => 't1',
            'training_packages' => ['t1'],
            'attendance_file_path' => null,
            'attendance_file_name' => null,
            'attendance_file_mime' => null,
            'attendance_file_size_bytes' => null,
            'attendance_media_json' => [],
            'selected_incubatee_ids' => [],
            'selected_incubatees_snapshot' => [],
        ], $overrides));
    }

    public function test_reap_support_achievement_counts_only_flagged_convergence_services(): void
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

        $hub = Hub::query()->create(['slug' => 'reap-hub', 'name' => 'Hub', 'sort_order' => 99]);
        $district = District::query()->create([
            'hub_id' => $hub->id,
            'slug' => 'reap-district',
            'name' => 'Reap District',
            'sort_order' => 99,
        ]);

        $convergenceCategory = ServiceCategory::query()->create([
            'slug' => 'convergence-with-line-departments',
            'name' => 'Schematic Convergence',
            'sort_order' => 99,
        ]);

        $throughReapService = Service::query()->create([
            'service_category_id' => $convergenceCategory->id,
            'code' => 'p_m_e_g_p',
            'name' => 'PMEGP',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $plainService = Service::query()->create([
            'service_category_id' => $convergenceCategory->id,
            'code' => 'm_s_y_2_0',
            'name' => 'MSY 2.0',
            'sort_order' => 2,
            'is_active' => true,
        ]);

        $cfaId = (int) DB::table('cfa_submissions')->insertGetId([
            'district_id' => $district->id,
            'applicant_name' => 'Reap Applicant',
            'phone' => '9999999911',
            'payload' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        ServiceCase::query()->create([
            'cfa_submission_id' => $cfaId,
            'service_id' => $throughReapService->id,
            'status' => ServiceCase::STATUS_APPROVED,
            'reference_number' => 'SC-REAP-1',
            'submitted_at' => '2026-05-10 09:00:00',
            'payload' => ['through_reap' => '1'],
        ]);

        ServiceCase::query()->create([
            'cfa_submission_id' => $cfaId,
            'service_id' => $plainService->id,
            'status' => ServiceCase::STATUS_APPROVED,
            'reference_number' => 'SC-REAP-2',
            'submitted_at' => '2026-05-11 09:00:00',
            'payload' => ['through_reap' => '0'],
        ]);

        $filter = new ProgramDeliverablesFilter($fy->id, null, null, null, null, null);
        $scope = ProgramDeliverablesScope::forUser(User::factory()->make(['role' => 'state_admin']));
        $report = app(ProgramDeliverablesReportService::class)->build($filter, $scope);
        $row81 = collect($report['rows'])->firstWhere('serial', '8.1');
        $row = collect($report['rows'])->firstWhere('serial', '8.2');

        $this->assertNotNull($row81);
        $this->assertNotNull($row);
        $this->assertSame(2, $row81['achievement']);
        $this->assertSame(1, $row['achievement']);
        $this->assertGreaterThanOrEqual($row['achievement'], $row81['achievement']);
    }

    public function test_convergence_through_reap_store_show_badge_and_deliverables_count(): void
    {
        app(AppSettingsService::class)->setMany(['service_module.enabled' => true]);

        $fy = FiscalYear::query()->firstOrCreate(
            ['code' => '2026-27'],
            [
                'name' => 'FY 2026-27',
                'starts_on' => '2026-04-01',
                'ends_on' => '2027-03-31',
                'is_active' => true,
            ]
        );

        $hub = Hub::query()->create(['slug' => 'reap-flow-hub', 'name' => 'Hub', 'sort_order' => 98]);
        $district = District::query()->create([
            'hub_id' => $hub->id,
            'slug' => 'reap-flow-district',
            'name' => 'Reap Flow District',
            'sort_order' => 98,
        ]);

        $staff = User::factory()->create([
            'role' => 'district_staff',
            'district_id' => $district->id,
            'is_active' => true,
        ]);

        $convergenceCategory = ServiceCategory::query()->create([
            'slug' => 'convergence-with-line-departments',
            'name' => 'Schematic Convergence',
            'sort_order' => 98,
        ]);

        $service = Service::query()->create([
            'service_category_id' => $convergenceCategory->id,
            'code' => 'p_m_e_g_p_flow',
            'name' => 'PMEGP Flow',
            'sort_order' => 1,
            'is_active' => true,
            'requires_approval' => false,
            'field_schema' => [
                ['key' => 'scheme_name', 'label' => 'Scheme', 'type' => 'text', 'required' => true],
                ['key' => 'scheme_registration_date', 'label' => 'Date', 'type' => 'date', 'required' => true],
            ],
        ]);

        $cfaId = (int) DB::table('cfa_submissions')->insertGetId([
            'district_id' => $district->id,
            'applicant_name' => 'Reap Flow Applicant',
            'phone' => '9999999922',
            'payload' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $batchId = (int) DB::table('onboarding_batches')->insertGetId([
            'hub_id' => $hub->id,
            'district_id' => $district->id,
            'name' => 'Flow batch',
            'target_size' => 1,
            'status' => 'locked',
            'locked_at' => now(),
            'onboarding_date' => '2026-05-01',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('onboarding_batch_cfa')->insert([
            'onboarding_batch_id' => $batchId,
            'cfa_submission_id' => $cfaId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($staff)
            ->post(route('staff.services.store'), [
                'cfa_submission_id' => $cfaId,
                'service_id' => $service->id,
                'payload' => [
                    'through_reap' => '1',
                    'scheme_name' => 'PMEGP Flow',
                    'scheme_registration_date' => '2026-05-12',
                    'reap_sector' => 'farm',
                    'reap_amount' => '1_lakh',
                    'reap_activity' => 'REAP support for farm enterprise setup.',
                ],
                'payload_files' => [
                    'reap_document' => UploadedFile::fake()->create('reap-support.docx', 120),
                ],
            ])
            ->assertRedirect(route('staff.services.index'));

        $case = ServiceCase::query()->where('service_id', $service->id)->latest('id')->firstOrFail();
        $this->assertSame('1', $case->payload['through_reap'] ?? null);
        $this->assertSame('farm', $case->payload['reap_sector'] ?? null);
        $this->assertSame('1_lakh', $case->payload['reap_amount'] ?? null);
        $this->assertSame('REAP support for farm enterprise setup.', $case->payload['reap_activity'] ?? null);
        $this->assertSame('reap-support.docx', $case->payload['reap_document'] ?? null);
        $this->assertTrue((bool) $case->through_reap);

        $this->actingAs($staff)
            ->get(route('staff.services.show', $case))
            ->assertOk()
            ->assertSee('Through REAP', false)
            ->assertSee('Farm', false)
            ->assertSee('1 Lakh', false)
            ->assertSee('REAP support for farm enterprise setup.', false);

        $filter = new ProgramDeliverablesFilter($fy->id, null, null, $district->id, null, null);
        $scope = ProgramDeliverablesScope::forUser($staff);
        $report = app(ProgramDeliverablesReportService::class)->build($filter, $scope);

        $row81 = collect($report['rows'])->firstWhere('serial', '8.1');
        $row82 = collect($report['rows'])->firstWhere('serial', '8.2');
        $row83 = collect($report['rows'])->firstWhere('serial', '8.3');

        $this->assertNotNull($row81);
        $this->assertNotNull($row82);
        $this->assertNotNull($row83);
        $this->assertSame(1, $row81['achievement']);
        $this->assertSame(1, $row82['achievement']);
        $this->assertSame(0, $row83['achievement']);
        $this->assertGreaterThanOrEqual($row82['achievement'], $row81['achievement']);

        $breakdown = app(ProgramDeliverablesAchievementBreakdownService::class)->build($filter, $scope, '8.2');
        $this->assertSame(1, $breakdown['total']);

        $this->actingAs($staff)
            ->get(route('staff.deliverables.breakdown', ['serial' => '8.2']))
            ->assertOk()
            ->assertJsonPath('total', 1);
    }

    public function test_approved_convergence_case_can_toggle_through_reap_without_reapproval(): void
    {
        app(AppSettingsService::class)->setMany(['service_module.enabled' => true]);

        $hub = Hub::query()->create(['slug' => 'reap-edit-hub', 'name' => 'Hub', 'sort_order' => 97]);
        $district = District::query()->create([
            'hub_id' => $hub->id,
            'slug' => 'reap-edit-district',
            'name' => 'Reap Edit District',
            'sort_order' => 97,
        ]);

        $staff = User::factory()->create([
            'role' => 'district_staff',
            'district_id' => $district->id,
            'is_active' => true,
        ]);

        $convergenceCategory = ServiceCategory::query()->create([
            'slug' => 'convergence-with-line-departments',
            'name' => 'Schematic Convergence',
            'sort_order' => 97,
        ]);

        $service = Service::query()->create([
            'service_category_id' => $convergenceCategory->id,
            'code' => 'p_m_e_g_p_edit',
            'name' => 'PMEGP Edit',
            'sort_order' => 1,
            'is_active' => true,
            'requires_approval' => true,
            'field_schema' => [
                ['key' => 'scheme_name', 'label' => 'Scheme', 'type' => 'text', 'required' => true],
                ['key' => 'scheme_registration_date', 'label' => 'Date', 'type' => 'date', 'required' => true],
            ],
        ]);

        $cfaId = (int) DB::table('cfa_submissions')->insertGetId([
            'district_id' => $district->id,
            'applicant_name' => 'Reap Edit Applicant',
            'phone' => '9999999933',
            'payload' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $case = ServiceCase::query()->create([
            'cfa_submission_id' => $cfaId,
            'service_id' => $service->id,
            'status' => ServiceCase::STATUS_APPROVED,
            'approved_at' => '2026-05-15 10:00:00',
            'submitted_at' => '2026-05-14 10:00:00',
            'submitted_by' => $staff->id,
            'created_by' => $staff->id,
            'payload' => [
                'scheme_name' => 'PMEGP Edit',
                'scheme_registration_date' => '2026-05-10',
                'through_reap' => '0',
            ],
        ]);

        $this->actingAs($staff)
            ->patch(route('staff.services.update', $case), [
                'payload' => [
                    'through_reap' => '1',
                    'scheme_name' => 'PMEGP Edit',
                    'scheme_registration_date' => '2026-05-10',
                    'reap_sector' => 'non_farm',
                    'reap_amount' => '3_lakh',
                    'reap_activity' => 'Pitch deck and business plan support.',
                ],
                'payload_files' => [
                    'reap_document' => UploadedFile::fake()->create('reap-plan.pdf', 100),
                ],
            ])
            ->assertRedirect(route('staff.services.index'));

        $case->refresh();
        $this->assertSame(ServiceCase::STATUS_APPROVED, $case->status);
        $this->assertSame('1', $case->payload['through_reap'] ?? null);
        $this->assertSame('non_farm', $case->payload['reap_sector'] ?? null);
        $this->assertTrue((bool) $case->through_reap);
    }

    public function test_through_reap_requires_reap_detail_fields_on_store(): void
    {
        app(AppSettingsService::class)->setMany(['service_module.enabled' => true]);

        $hub = Hub::query()->create(['slug' => 'reap-val-hub', 'name' => 'Hub', 'sort_order' => 96]);
        $district = District::query()->create([
            'hub_id' => $hub->id,
            'slug' => 'reap-val-district',
            'name' => 'Reap Val District',
            'sort_order' => 96,
        ]);

        $staff = User::factory()->create([
            'role' => 'district_staff',
            'district_id' => $district->id,
            'is_active' => true,
        ]);

        $convergenceCategory = ServiceCategory::query()->create([
            'slug' => 'convergence-with-line-departments',
            'name' => 'Schematic Convergence',
            'sort_order' => 96,
        ]);

        $service = Service::query()->create([
            'service_category_id' => $convergenceCategory->id,
            'code' => 'p_m_e_g_p_val',
            'name' => 'PMEGP Val',
            'sort_order' => 1,
            'is_active' => true,
            'requires_approval' => false,
            'field_schema' => [
                ['key' => 'scheme_name', 'label' => 'Scheme', 'type' => 'text', 'required' => true],
                ['key' => 'scheme_registration_date', 'label' => 'Date', 'type' => 'date', 'required' => true],
            ],
        ]);

        $cfaId = (int) DB::table('cfa_submissions')->insertGetId([
            'district_id' => $district->id,
            'applicant_name' => 'Reap Val Applicant',
            'phone' => '9999999944',
            'payload' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $batchId = (int) DB::table('onboarding_batches')->insertGetId([
            'hub_id' => $hub->id,
            'district_id' => $district->id,
            'name' => 'Val batch',
            'target_size' => 1,
            'status' => 'locked',
            'locked_at' => now(),
            'onboarding_date' => '2026-05-01',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('onboarding_batch_cfa')->insert([
            'onboarding_batch_id' => $batchId,
            'cfa_submission_id' => $cfaId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($staff)
            ->from(route('staff.services.create'))
            ->post(route('staff.services.store'), [
                'cfa_submission_id' => $cfaId,
                'service_id' => $service->id,
                'payload' => [
                    'through_reap' => '1',
                    'scheme_name' => 'PMEGP Val',
                    'scheme_registration_date' => '2026-05-12',
                ],
            ])
            ->assertRedirect(route('staff.services.create'))
            ->assertSessionHasErrors(['reap_sector', 'reap_document']);

        $case = ServiceCase::query()->where('service_id', $service->id)->first();
        $this->assertNotNull($case);
        $this->assertSame(ServiceCase::STATUS_DRAFT, $case->status);
    }

    public function test_report_uses_row_metadata_overrides(): void
    {
        ProgramDeliverableRowMetadata::query()->create([
            'serial' => '1.1',
            'indicator_type' => 'Non-Key',
            'level' => 'State',
        ]);

        ProgramDeliverableRowMetadataService::resetCacheForTesting();

        $filter = new ProgramDeliverablesFilter(null, null, null, null, null, null);
        $scope = ProgramDeliverablesScope::forUser(User::factory()->make(['role' => 'state_admin']));
        $report = app(ProgramDeliverablesReportService::class)->build($filter, $scope);
        $row = collect($report['rows'])->firstWhere('serial', '1.1');

        $this->assertNotNull($row);
        $this->assertSame('Non-Key', $row['indicator_type']);
        $this->assertSame('State', $row['level']);
    }

    public function test_report_filters_rows_by_indicator_type_and_level(): void
    {
        $scope = ProgramDeliverablesScope::forUser(User::factory()->make(['role' => 'state_admin']));
        $service = app(ProgramDeliverablesReportService::class);

        $allRows = $service->build(new ProgramDeliverablesFilter(null, null, null, null, null, null), $scope)['rows'];
        $allLeafSerials = collect($allRows)
            ->reject(fn (array $row) => in_array($row['row_type'], ['pillar', 'subcategory'], true))
            ->pluck('serial')
            ->all();

        $stateLevelRows = $service->build(
            new ProgramDeliverablesFilter(null, null, null, null, null, null, null, null, 'State'),
            $scope,
        )['rows'];
        $stateLeafSerials = collect($stateLevelRows)
            ->reject(fn (array $row) => in_array($row['row_type'], ['pillar', 'subcategory'], true))
            ->pluck('serial')
            ->all();

        $this->assertNotEmpty($stateLeafSerials);
        $this->assertContains('1.2', $stateLeafSerials);
        $this->assertNotContains('1.1', $stateLeafSerials);
        $this->assertContains('1', collect($stateLevelRows)->pluck('serial')->all());

        $nonKeyRows = $service->build(
            new ProgramDeliverablesFilter(null, null, null, null, null, null, null, 'Non-Key', null),
            $scope,
        )['rows'];
        $nonKeyLeafSerials = collect($nonKeyRows)
            ->reject(fn (array $row) => in_array($row['row_type'], ['pillar', 'subcategory'], true))
            ->pluck('serial')
            ->all();

        $this->assertNotEmpty($nonKeyLeafSerials);
        $this->assertNotContains('1.1', $nonKeyLeafSerials);
        $this->assertLessThan(count($allLeafSerials), count($nonKeyLeafSerials));
    }

    public function test_deliverables_page_shows_indicator_metadata_filters(): void
    {
        $admin = User::factory()->create(['role' => 'state_admin', 'is_active' => true]);

        $this->actingAs($admin)
            ->get(route('admin.deliverables.index'))
            ->assertOk()
            ->assertSee('Type of Indicator', false)
            ->assertSee('Spoke/ Hub/ State', false)
            ->assertSee('name="indicator_type"', false)
            ->assertSee('name="level"', false);
    }

    public function test_deliverables_page_applies_indicator_metadata_filters_from_query(): void
    {
        $admin = User::factory()->create(['role' => 'state_admin', 'is_active' => true]);

        $this->actingAs($admin)
            ->get(route('admin.deliverables.index', ['level' => 'State']))
            ->assertOk()
            ->assertSee('District Level Workshops')
            ->assertDontSee('Call for Application');
    }

    public function test_row_metadata_update_requires_edit_mode_enabled(): void
    {
        $admin = User::factory()->create(['role' => 'state_admin', 'is_active' => true]);

        $this->actingAs($admin)
            ->patchJson(route('admin.deliverables.row-metadata.update'), [
                'serial' => '1.1',
                'field' => 'level',
                'value' => 'State',
            ])
            ->assertForbidden();
    }

    public function test_state_admin_can_update_row_metadata_when_edit_mode_enabled(): void
    {
        app(AppSettingsService::class)->setMany(['deliverables.indicator_metadata_editable' => true]);

        $admin = User::factory()->create(['role' => 'state_admin', 'is_active' => true]);

        $this->actingAs($admin)
            ->patchJson(route('admin.deliverables.row-metadata.update'), [
                'serial' => '1.1',
                'field' => 'indicator_type',
                'value' => 'Non-Key',
            ])
            ->assertOk()
            ->assertJson([
                'serial' => '1.1',
                'indicator_type' => 'Non-Key',
            ]);

        $this->assertDatabaseHas('program_deliverable_row_metadata', [
            'serial' => '1.1',
            'indicator_type' => 'Non-Key',
        ]);
    }

    public function test_deliverables_page_shows_editable_selects_when_edit_mode_enabled(): void
    {
        app(AppSettingsService::class)->setMany(['deliverables.indicator_metadata_editable' => true]);

        $admin = User::factory()->create(['role' => 'state_admin', 'is_active' => true]);

        $this->actingAs($admin)
            ->get(route('admin.deliverables.index'))
            ->assertOk()
            ->assertSee('dlv-meta-select', false)
            ->assertSee('Edit mode is on', false);
    }

    public function test_deliverables_page_does_not_show_read_only_settings_banner(): void
    {
        $admin = User::factory()->create(['role' => 'state_admin', 'is_active' => true]);

        $this->actingAs($admin)
            ->get(route('admin.deliverables.index'))
            ->assertOk()
            ->assertDontSee('Indicator columns are read-only', false)
            ->assertDontSee('Enable edit mode in Service module settings', false);
    }

    public function test_service_module_settings_page_shows_deliverables_edit_toggle(): void
    {
        $admin = User::factory()->create(['role' => 'state_admin', 'is_active' => true]);

        $this->actingAs($admin)
            ->get(route('admin.service-module-settings.edit'))
            ->assertOk()
            ->assertSee('Deliverables page — edit mode', false)
            ->assertSee('deliverables_indicator_metadata_editable', false);
    }

    public function test_spoc_deliverables_scope_is_statewide(): void
    {
        $scope = ProgramDeliverablesScope::forUser(User::factory()->make(['role' => 'state_staff']));

        $this->assertNull($scope->districtIds);
        $this->assertTrue($scope->usesStateTargets);
        $this->assertSame('All districts (state)', $scope->scopeLabel(null));
        $this->assertTrue($scope->canPickDistrict());
    }

    public function test_dedicated_reap_support_service_store_counts_mis_8_2(): void
    {
        app(AppSettingsService::class)->setMany(['service_module.enabled' => true]);

        $fy = FiscalYear::query()->firstOrCreate(
            ['code' => '2026-27'],
            [
                'name' => 'FY 2026-27',
                'starts_on' => '2026-04-01',
                'ends_on' => '2027-03-31',
                'is_active' => true,
            ]
        );

        $hub = Hub::query()->create(['slug' => 'dedicated-reap-hub', 'name' => 'Hub', 'sort_order' => 95]);
        $district = District::query()->create([
            'hub_id' => $hub->id,
            'slug' => 'dedicated-reap-district',
            'name' => 'Dedicated Reap District',
            'sort_order' => 95,
        ]);

        $staff = User::factory()->create([
            'role' => 'district_staff',
            'district_id' => $district->id,
            'is_active' => true,
        ]);

        $category = ServiceCategory::query()->create([
            'slug' => 'reap_support',
            'name' => 'REAP Support',
            'sort_order' => 95,
        ]);

        $service = Service::query()->create([
            'service_category_id' => $category->id,
            'code' => 'support_muy_incubatee_reap',
            'name' => 'Support to MUY Incubatee through REAP',
            'sort_order' => 1,
            'is_active' => true,
            'requires_approval' => false,
            'counts_toward_reap_support' => true,
        ]);

        $cfaId = (int) DB::table('cfa_submissions')->insertGetId([
            'district_id' => $district->id,
            'applicant_name' => 'Dedicated Reap Applicant',
            'phone' => '9999999955',
            'payload' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $batchId = (int) DB::table('onboarding_batches')->insertGetId([
            'hub_id' => $hub->id,
            'district_id' => $district->id,
            'name' => 'Dedicated reap batch',
            'target_size' => 1,
            'status' => 'locked',
            'locked_at' => now(),
            'onboarding_date' => '2026-05-01',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('onboarding_batch_cfa')->insert([
            'onboarding_batch_id' => $batchId,
            'cfa_submission_id' => $cfaId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($staff)
            ->post(route('staff.services.store'), [
                'cfa_submission_id' => $cfaId,
                'service_id' => $service->id,
                'payload' => [
                    'through_reap' => '1',
                    'reap_sector' => 'non_farm',
                    'reap_amount' => '3_lakh',
                    'reap_activity' => 'Equipment support through REAP.',
                ],
                'payload_files' => [
                    'reap_document' => UploadedFile::fake()->create('reap-plan.pdf', 100),
                ],
            ])
            ->assertRedirect(route('staff.services.index'));

        $case = ServiceCase::query()->where('service_id', $service->id)->latest('id')->firstOrFail();
        $this->assertTrue($case->isReapSupportServiceCase());
        $this->assertTrue($case->displaysReapSupportRoute());
        $this->assertSame('1', $case->payload['through_reap'] ?? null);
        $this->assertSame('non_farm', $case->payload['reap_sector'] ?? null);
        $this->assertTrue((bool) $case->through_reap);

        $this->actingAs($staff)
            ->get(route('staff.services.create'))
            ->assertOk()
            ->assertSee('counts_toward_reap_support', false)
            ->assertSee('Support to MUY Incubatee through REAP', false);

        $filter = new ProgramDeliverablesFilter($fy->id, null, null, $district->id, null, null);
        $scope = ProgramDeliverablesScope::forUser($staff);
        $report = app(ProgramDeliverablesReportService::class)->build($filter, $scope);

        $row81 = collect($report['rows'])->firstWhere('serial', '8.1');
        $row82 = collect($report['rows'])->firstWhere('serial', '8.2');
        $row83 = collect($report['rows'])->firstWhere('serial', '8.3');
        $this->assertNotNull($row81);
        $this->assertNotNull($row82);
        $this->assertSame(1, $row81['achievement']);
        $this->assertSame(1, $row82['achievement']);
        $this->assertSame(0, $row83['achievement'] ?? 0);
        $this->assertGreaterThanOrEqual($row82['achievement'], $row81['achievement']);
    }

    public function test_staff_services_index_reap_support_8_2_filter_shows_unified_label(): void
    {
        app(AppSettingsService::class)->setMany(['service_module.enabled' => true]);

        $hub = Hub::query()->create(['slug' => 'reap-list-hub', 'name' => 'Hub', 'sort_order' => 96]);
        $district = District::query()->create([
            'hub_id' => $hub->id,
            'slug' => 'reap-list-district',
            'name' => 'Reap List District',
            'sort_order' => 96,
        ]);

        $staff = User::factory()->create([
            'role' => 'district_staff',
            'district_id' => $district->id,
            'is_active' => true,
        ]);

        $convergenceCategory = ServiceCategory::query()->create([
            'slug' => 'convergence-with-line-departments',
            'name' => 'Schematic Convergence',
            'sort_order' => 96,
        ]);

        $convergenceService = Service::query()->create([
            'service_category_id' => $convergenceCategory->id,
            'code' => 'pmegp_list',
            'name' => 'PMEGP',
            'sort_order' => 1,
            'is_active' => true,
            'requires_approval' => false,
        ]);

        $reapCategory = ServiceCategory::query()->create([
            'slug' => 'reap_support_list',
            'name' => 'REAP Support',
            'sort_order' => 97,
        ]);

        $reapService = Service::query()->create([
            'service_category_id' => $reapCategory->id,
            'code' => 'support_muy_incubatee_reap_list',
            'name' => 'Support to MUY Incubatee through REAP',
            'sort_order' => 1,
            'is_active' => true,
            'requires_approval' => false,
            'counts_toward_reap_support' => true,
        ]);

        $cfaReapId = (int) DB::table('cfa_submissions')->insertGetId([
            'district_id' => $district->id,
            'applicant_name' => 'Dedicated Reap List',
            'phone' => '9999999944',
            'payload' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $cfaConvergenceId = (int) DB::table('cfa_submissions')->insertGetId([
            'district_id' => $district->id,
            'applicant_name' => 'Convergence Reap List',
            'phone' => '9999999945',
            'payload' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $cfaPlainId = (int) DB::table('cfa_submissions')->insertGetId([
            'district_id' => $district->id,
            'applicant_name' => 'Plain Convergence',
            'phone' => '9999999946',
            'payload' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        ServiceCase::query()->create([
            'cfa_submission_id' => $cfaReapId,
            'service_id' => $reapService->id,
            'status' => ServiceCase::STATUS_APPROVED,
            'created_by' => $staff->id,
            'submitted_by' => $staff->id,
            'through_reap' => true,
            'payload' => ['through_reap' => '1'],
        ]);

        ServiceCase::query()->create([
            'cfa_submission_id' => $cfaConvergenceId,
            'service_id' => $convergenceService->id,
            'status' => ServiceCase::STATUS_APPROVED,
            'created_by' => $staff->id,
            'submitted_by' => $staff->id,
            'through_reap' => true,
            'payload' => ['through_reap' => '1', 'scheme_name' => 'PMEGP'],
        ]);

        ServiceCase::query()->create([
            'cfa_submission_id' => $cfaPlainId,
            'service_id' => $convergenceService->id,
            'status' => ServiceCase::STATUS_APPROVED,
            'created_by' => $staff->id,
            'submitted_by' => $staff->id,
            'through_reap' => false,
            'payload' => ['through_reap' => '0'],
        ]);

        $label = \App\Support\ConvergenceReapSupport::MIS_8_2_LIST_LABEL;

        $this->actingAs($staff)
            ->get(route('staff.services.index', [
                'service_id' => \App\Support\ConvergenceReapSupport::MIS_8_2_LIST_FILTER,
            ]))
            ->assertOk()
            ->assertSee($label, false)
            ->assertSee('via PMEGP', false)
            ->assertSee('Dedicated Reap List', false)
            ->assertSee('Convergence Reap List', false)
            ->assertDontSee('Plain Convergence', false);
    }

    public function test_report_assigns_performance_tone_from_achievement_pct(): void
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

        $cfa = Deliverable::query()->updateOrCreate(
            ['code' => 'cfa'],
            [
                'sort_order' => 9999,
                'name' => 'Call for Application',
                'mis_entry_label' => 'Call for Application',
                'is_active' => true,
            ]
        );

        StateDeliverableTarget::query()->create([
            'fiscal_year_id' => $fy->id,
            'deliverable_id' => $cfa->id,
            'target_total' => 100,
        ]);

        $hub = Hub::query()->create(['slug' => 'tone-hub', 'name' => 'Hub', 'sort_order' => 1]);
        $district = District::query()->create([
            'hub_id' => $hub->id,
            'slug' => 'tone-district',
            'name' => 'Tone District',
            'sort_order' => 1,
        ]);

        DB::table('cfa_submissions')->insert([
            'district_id' => $district->id,
            'fiscal_year_id' => $fy->id,
            'applicant_name' => 'Low achievement',
            'phone' => '9000000099',
            'payload' => json_encode([]),
            'created_at' => '2025-06-01',
            'updated_at' => now(),
        ]);

        $filter = new ProgramDeliverablesFilter($fy->id, null, null, null, null, null);
        $scope = ProgramDeliverablesScope::forUser(User::factory()->make(['role' => 'state_admin']));
        $report = app(ProgramDeliverablesReportService::class)->build($filter, $scope);
        $row = collect($report['rows'])->firstWhere('serial', '1.1');

        $this->assertNotNull($row);
        $this->assertSame(1, $row['achievement_pct']);
        $this->assertSame('critical', $row['performance_tone']);
    }

    public function test_deliverables_page_renders_performance_pct_badges(): void
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

        $cfa = Deliverable::query()->updateOrCreate(
            ['code' => 'cfa'],
            [
                'sort_order' => 9999,
                'name' => 'Call for Application',
                'mis_entry_label' => 'Call for Application',
                'is_active' => true,
            ]
        );

        StateDeliverableTarget::query()->create([
            'fiscal_year_id' => $fy->id,
            'deliverable_id' => $cfa->id,
            'target_total' => 100,
        ]);

        $hub = Hub::query()->create(['slug' => 'badge-hub', 'name' => 'Hub', 'sort_order' => 1]);
        $district = District::query()->create([
            'hub_id' => $hub->id,
            'slug' => 'badge-district',
            'name' => 'Badge District',
            'sort_order' => 1,
        ]);

        DB::table('cfa_submissions')->insert([
            'district_id' => $district->id,
            'fiscal_year_id' => $fy->id,
            'applicant_name' => 'Badge test',
            'phone' => '9000000100',
            'payload' => json_encode([]),
            'created_at' => '2025-06-01',
            'updated_at' => now(),
        ]);

        $admin = User::factory()->create(['role' => 'state_admin', 'is_active' => true]);

        $this->actingAs($admin)
            ->get(route('admin.deliverables.index', ['fiscal_year_id' => $fy->id]))
            ->assertOk()
            ->assertSee('dlv-pct-badge--critical', false)
            ->assertSee('dlv-pct-bar__fill--critical', false)
            ->assertSee('needs attention', false)
            ->assertSee('on track', false);
    }
}
