<?php

namespace Tests\Feature;

use App\Models\Deliverable;
use App\Models\District;
use App\Models\FiscalYear;
use App\Models\Hub;
use App\Models\OfficialDistrictMonthlyTarget;
use App\Models\OfficialHubMonthlyTarget;
use App\Models\OfficialStateMonthlyTarget;
use App\Models\User;
use App\Services\Deliverables\ProgramDeliverablesFilter;
use App\Services\Deliverables\ProgramDeliverablesScope;
use App\Services\OfficialMonthlyTargetCodeResolver;
use App\Services\ProgramDeliverablesReportService;
use App\Services\StateMonthlyTargetIndicatorBootstrapService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OfficialMonthlyTargetsDeliverablesTest extends TestCase
{
    use RefreshDatabase;

    public function test_deliverables_uses_official_district_monthly_targets_full_year_and_q1_filter(): void
    {
        app(\App\Services\ServiceTargetDeliverableSyncService::class)->syncAllServices();

        $fy = FiscalYear::query()->firstOrCreate(
            ['code' => '2026-27'],
            [
                'name' => 'FY 2026-27',
                'starts_on' => '2026-04-01',
                'ends_on' => '2027-03-31',
                'is_active' => true,
            ]
        );

        $hub = Hub::query()->create(['slug' => 'kumaon', 'name' => 'Kumaon', 'sort_order' => 1]);
        $almora = District::query()->create([
            'hub_id' => $hub->id,
            'slug' => 'almora',
            'name' => 'Almora',
            'sort_order' => 1,
        ]);

        $cfa = app(OfficialMonthlyTargetCodeResolver::class)
            ->deliverableForMisSerial('1.1', 'Call for application');

        foreach ([1 => 10, 2 => 20, 3 => 30, 4 => 40] as $month => $count) {
            OfficialDistrictMonthlyTarget::query()->create([
                'fiscal_year_id' => $fy->id,
                'district_id' => $almora->id,
                'deliverable_id' => $cfa->id,
                'month_number' => $month,
                'target_count' => $count,
            ]);
        }

        $scope = ProgramDeliverablesScope::forUser(User::factory()->make(['role' => 'state_admin']));

        $fullReport = app(ProgramDeliverablesReportService::class)->build(
            new ProgramDeliverablesFilter($fy->id, null, null, null, null, null),
            $scope,
        );
        $fullRow = collect($fullReport['rows'])->firstWhere('serial', '1.1');
        $this->assertNotNull($fullRow);
        $this->assertSame(100, $fullRow['target']);

        $q1Report = app(ProgramDeliverablesReportService::class)->build(
            new ProgramDeliverablesFilter($fy->id, null, null, null, null, null, 1),
            $scope,
        );
        $q1Row = collect($q1Report['rows'])->firstWhere('serial', '1.1');
        $this->assertNotNull($q1Row);
        $this->assertSame(60, $q1Row['target']);

        $districtReport = app(ProgramDeliverablesReportService::class)->build(
            new ProgramDeliverablesFilter($fy->id, $almora->id, null, null, null, null),
            $scope,
        );
        $districtRow = collect($districtReport['rows'])->firstWhere('serial', '1.1');
        $this->assertNotNull($districtRow);
        $this->assertSame(100, $districtRow['target']);
    }

    public function test_deliverables_uses_official_state_monthly_targets_with_month_filter(): void
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

        foreach ([1 => 5, 2 => 7, 3 => 9] as $month => $count) {
            OfficialStateMonthlyTarget::query()->create([
                'fiscal_year_id' => $fy->id,
                'deliverable_id' => $deliverable->id,
                'month_number' => $month,
                'target_count' => $count,
            ]);
        }

        $scope = ProgramDeliverablesScope::forUser(User::factory()->make(['role' => 'state_admin']));

        $aprilReport = app(ProgramDeliverablesReportService::class)->build(
            new ProgramDeliverablesFilter($fy->id, null, 4, 2026, null, null),
            $scope,
        );
        $aprilRow = collect($aprilReport['rows'])->firstWhere('serial', '10.1');
        $this->assertNotNull($aprilRow);
        $this->assertSame(5, $aprilRow['target']);

        $q1Report = app(ProgramDeliverablesReportService::class)->build(
            new ProgramDeliverablesFilter($fy->id, null, null, null, null, null, 1),
            $scope,
        );
        $q1Row = collect($q1Report['rows'])->firstWhere('serial', '10.1');
        $this->assertNotNull($q1Row);
        $this->assertSame(21, $q1Row['target']);
    }

    public function test_deliverables_quarter_filter_sums_three_full_months(): void
    {
        app(\App\Services\ServiceTargetDeliverableSyncService::class)->syncAllServices();

        $fy = FiscalYear::query()->firstOrCreate(
            ['code' => '2026-27'],
            [
                'name' => 'FY 2026-27',
                'starts_on' => '2026-04-01',
                'ends_on' => '2027-03-31',
                'is_active' => true,
            ]
        );

        $hub = Hub::query()->create(['slug' => 'kumaon', 'name' => 'Kumaon', 'sort_order' => 1]);
        $almora = District::query()->create([
            'hub_id' => $hub->id,
            'slug' => 'almora',
            'name' => 'Almora',
            'sort_order' => 1,
        ]);

        $cfa = app(OfficialMonthlyTargetCodeResolver::class)
            ->deliverableForMisSerial('1.1', 'Call for application');

        foreach ([1 => 4550, 2 => 5040, 3 => 5600] as $month => $count) {
            OfficialDistrictMonthlyTarget::query()->create([
                'fiscal_year_id' => $fy->id,
                'district_id' => $almora->id,
                'deliverable_id' => $cfa->id,
                'month_number' => $month,
                'target_count' => $count,
            ]);
        }

        $scope = ProgramDeliverablesScope::forUser(User::factory()->make(['role' => 'state_admin']));

        $q1Report = app(ProgramDeliverablesReportService::class)->build(
            new ProgramDeliverablesFilter($fy->id, null, null, null, null, null, 1),
            $scope,
        );
        $q1Row = collect($q1Report['rows'])->firstWhere('serial', '1.1');
        $this->assertNotNull($q1Row);
        $this->assertSame(15190, $q1Row['target']);
    }

    public function test_deliverables_uses_state_official_cfa_when_no_district_official_rows(): void
    {
        app(\App\Services\ServiceTargetDeliverableSyncService::class)->syncAllServices();

        $fy = FiscalYear::query()->firstOrCreate(
            ['code' => '2026-27'],
            [
                'name' => 'FY 2026-27',
                'starts_on' => '2026-04-01',
                'ends_on' => '2027-03-31',
                'is_active' => true,
            ]
        );

        $cfa = app(OfficialMonthlyTargetCodeResolver::class)
            ->deliverableForMisSerial('1.1', 'Call for application');

        foreach ([1 => 4550, 2 => 5040, 3 => 5600] as $month => $count) {
            OfficialStateMonthlyTarget::query()->create([
                'fiscal_year_id' => $fy->id,
                'deliverable_id' => $cfa->id,
                'month_number' => $month,
                'target_count' => $count,
            ]);
        }

        $scope = ProgramDeliverablesScope::forUser(User::factory()->make(['role' => 'state_admin']));

        $q1Report = app(ProgramDeliverablesReportService::class)->build(
            new ProgramDeliverablesFilter($fy->id, null, null, null, null, null, 1),
            $scope,
        );
        $q1Row = collect($q1Report['rows'])->firstWhere('serial', '1.1');
        $this->assertNotNull($q1Row);
        $this->assertSame(15190, $q1Row['target']);
    }

    public function test_deliverables_q1_target_uses_full_fiscal_months_when_fy_starts_mid_april(): void
    {
        app(\App\Services\ServiceTargetDeliverableSyncService::class)->syncAllServices();

        $fy = FiscalYear::query()->firstOrCreate(
            ['code' => '2026-27'],
            [
                'name' => 'FY 2026-27',
                'starts_on' => '2026-04-02',
                'ends_on' => '2027-04-01',
                'is_active' => true,
            ]
        );
        $fy->update(['starts_on' => '2026-04-02', 'ends_on' => '2027-04-01']);

        $hub = Hub::query()->create(['slug' => 'kumaon', 'name' => 'Kumaon', 'sort_order' => 1]);
        $almora = District::query()->create([
            'hub_id' => $hub->id,
            'slug' => 'almora',
            'name' => 'Almora',
            'sort_order' => 1,
        ]);

        $cfa = app(OfficialMonthlyTargetCodeResolver::class)
            ->deliverableForMisSerial('1.1', 'Call for application');

        foreach ([1 => 4550, 2 => 5040, 3 => 5600] as $month => $count) {
            OfficialDistrictMonthlyTarget::query()->create([
                'fiscal_year_id' => $fy->id,
                'district_id' => $almora->id,
                'deliverable_id' => $cfa->id,
                'month_number' => $month,
                'target_count' => $count,
            ]);
        }

        $scope = ProgramDeliverablesScope::forUser(User::factory()->make(['role' => 'state_admin']));

        $q1Report = app(ProgramDeliverablesReportService::class)->build(
            new ProgramDeliverablesFilter($fy->id, null, null, null, null, null, 1),
            $scope,
        );
        $q1Row = collect($q1Report['rows'])->firstWhere('serial', '1.1');
        $this->assertNotNull($q1Row);
        $this->assertSame(15190, $q1Row['target']);
    }

    public function test_deliverables_prefers_state_official_onboarding_over_partial_district_sum(): void
    {
        app(\App\Services\ServiceTargetDeliverableSyncService::class)->syncAllServices();

        $fy = FiscalYear::query()->firstOrCreate(
            ['code' => '2026-27'],
            [
                'name' => 'FY 2026-27',
                'starts_on' => '2026-04-01',
                'ends_on' => '2027-03-31',
                'is_active' => true,
            ]
        );

        $hub = Hub::query()->create(['slug' => 'kumaon', 'name' => 'Kumaon', 'sort_order' => 1]);
        $almora = District::query()->create([
            'hub_id' => $hub->id,
            'slug' => 'almora',
            'name' => 'Almora',
            'sort_order' => 1,
        ]);

        $onboarding = app(OfficialMonthlyTargetCodeResolver::class)
            ->deliverableForMisSerial('2.1', 'Incubatees Onboarded');

        foreach ([1 => 550, 2 => 650, 3 => 700, 4 => 700, 5 => 700, 6 => 800, 7 => 600, 8 => 600, 9 => 500, 10 => 400, 11 => 400, 12 => 400] as $month => $count) {
            OfficialStateMonthlyTarget::query()->create([
                'fiscal_year_id' => $fy->id,
                'deliverable_id' => $onboarding->id,
                'month_number' => $month,
                'target_count' => $count,
            ]);
        }

        foreach ([1 => 100, 2 => 200, 3 => 300] as $month => $count) {
            OfficialDistrictMonthlyTarget::query()->create([
                'fiscal_year_id' => $fy->id,
                'district_id' => $almora->id,
                'deliverable_id' => $onboarding->id,
                'month_number' => $month,
                'target_count' => $count,
            ]);
        }

        $scope = ProgramDeliverablesScope::forUser(User::factory()->make(['role' => 'state_admin']));

        $report = app(ProgramDeliverablesReportService::class)->build(
            new ProgramDeliverablesFilter($fy->id, null, null, null, null, null),
            $scope,
        );
        $row = collect($report['rows'])->firstWhere('serial', '2.1');
        $this->assertNotNull($row);
        $this->assertSame(7000, $row['target']);
    }

    public function test_deliverables_district_filter_includes_state_only_targets(): void
    {
        app(\App\Services\ServiceTargetDeliverableSyncService::class)->syncAllServices();
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

        $hub = Hub::query()->create(['slug' => 'kumaon', 'name' => 'Kumaon', 'sort_order' => 1]);
        $almora = District::query()->create([
            'hub_id' => $hub->id,
            'slug' => 'almora',
            'name' => 'Almora',
            'sort_order' => 1,
        ]);

        $cfa = app(OfficialMonthlyTargetCodeResolver::class)
            ->deliverableForMisSerial('1.1', 'Call for application');
        $social = Deliverable::query()->where('code', 'social_media')->firstOrFail();

        foreach ([1 => 10, 2 => 20, 3 => 30, 4 => 40] as $month => $count) {
            OfficialDistrictMonthlyTarget::query()->create([
                'fiscal_year_id' => $fy->id,
                'district_id' => $almora->id,
                'deliverable_id' => $cfa->id,
                'month_number' => $month,
                'target_count' => $count,
            ]);
        }

        foreach ([1 => 5, 2 => 7, 3 => 9, 4 => 11] as $month => $count) {
            OfficialStateMonthlyTarget::query()->create([
                'fiscal_year_id' => $fy->id,
                'deliverable_id' => $social->id,
                'month_number' => $month,
                'target_count' => $count,
            ]);
        }

        $scope = ProgramDeliverablesScope::forUser(User::factory()->make(['role' => 'state_admin']));

        $report = app(ProgramDeliverablesReportService::class)->build(
            new ProgramDeliverablesFilter($fy->id, $almora->id, null, null, null, null),
            $scope,
        );

        $cfaRow = collect($report['rows'])->firstWhere('serial', '1.1');
        $this->assertNotNull($cfaRow);
        $this->assertSame(100, $cfaRow['target']);

        $socialRow = collect($report['rows'])->firstWhere('serial', '10.1');
        $this->assertNotNull($socialRow);
        $this->assertSame(32, $socialRow['target']);
    }

    public function test_deliverables_bst_sessions_uses_official_monthly_not_training_plan(): void
    {
        app(\App\Services\ServiceTargetDeliverableSyncService::class)->syncAllServices();
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

        $bst = app(OfficialMonthlyTargetCodeResolver::class)
            ->deliverableForMisSerial('3.1', 'Business Skills Training Sessions');

        foreach ([1 => 10, 2 => 20, 3 => 30] as $month => $count) {
            OfficialStateMonthlyTarget::query()->create([
                'fiscal_year_id' => $fy->id,
                'deliverable_id' => $bst->id,
                'month_number' => $month,
                'target_count' => $count,
            ]);
        }

        $scope = ProgramDeliverablesScope::forUser(User::factory()->make(['role' => 'state_admin']));

        $q1Report = app(ProgramDeliverablesReportService::class)->build(
            new ProgramDeliverablesFilter($fy->id, null, null, null, null, null, 1),
            $scope,
        );
        $q1Row = collect($q1Report['rows'])->firstWhere('serial', '3.1');
        $this->assertNotNull($q1Row);
        $this->assertSame(60, $q1Row['target']);
    }

    public function test_deliverables_district_level_workshops_uses_official_district_monthly_with_filters(): void
    {
        app(\App\Services\ServiceTargetDeliverableSyncService::class)->syncAllServices();

        $fy = FiscalYear::query()->firstOrCreate(
            ['code' => '2026-27'],
            [
                'name' => 'FY 2026-27',
                'starts_on' => '2026-04-01',
                'ends_on' => '2027-03-31',
                'is_active' => true,
            ]
        );

        $hub = Hub::query()->create(['slug' => 'kumaon', 'name' => 'Kumaon', 'sort_order' => 1]);
        $almora = District::query()->create([
            'hub_id' => $hub->id,
            'slug' => 'almora',
            'name' => 'Almora',
            'sort_order' => 1,
        ]);
        $bageshwar = District::query()->create([
            'hub_id' => $hub->id,
            'slug' => 'bageshwar',
            'name' => 'Bageshwar',
            'sort_order' => 2,
        ]);

        $workshops = app(OfficialMonthlyTargetCodeResolver::class)
            ->deliverableForMisSerial('1.2', 'District Level Workshops');

        foreach ([$almora, $bageshwar] as $district) {
            foreach ([1 => 0, 2 => 1, 3 => 0, 4 => 1, 7 => 1, 10 => 1] as $month => $count) {
                OfficialDistrictMonthlyTarget::query()->create([
                    'fiscal_year_id' => $fy->id,
                    'district_id' => $district->id,
                    'deliverable_id' => $workshops->id,
                    'month_number' => $month,
                    'target_count' => $count,
                ]);
            }
        }

        $scope = ProgramDeliverablesScope::forUser(User::factory()->make(['role' => 'state_admin']));

        $fullReport = app(ProgramDeliverablesReportService::class)->build(
            new ProgramDeliverablesFilter($fy->id, null, null, null, null, null),
            $scope,
        );
        $fullRow = collect($fullReport['rows'])->firstWhere('serial', '1.2');
        $this->assertNotNull($fullRow);
        $this->assertSame(8, $fullRow['target']);

        $q1Report = app(ProgramDeliverablesReportService::class)->build(
            new ProgramDeliverablesFilter($fy->id, null, null, null, null, null, 1),
            $scope,
        );
        $q1Row = collect($q1Report['rows'])->firstWhere('serial', '1.2');
        $this->assertNotNull($q1Row);
        $this->assertSame(2, $q1Row['target']);

        $districtReport = app(ProgramDeliverablesReportService::class)->build(
            new ProgramDeliverablesFilter($fy->id, $almora->id, null, null, null, null, 1),
            $scope,
        );
        $districtRow = collect($districtReport['rows'])->firstWhere('serial', '1.2');
        $this->assertNotNull($districtRow);
        $this->assertSame(1, $districtRow['target']);
    }

    public function test_deliverables_district_level_workshops_prefers_state_official_on_state_view(): void
    {
        app(\App\Services\ServiceTargetDeliverableSyncService::class)->syncAllServices();

        $fy = FiscalYear::query()->firstOrCreate(
            ['code' => '2026-27'],
            [
                'name' => 'FY 2026-27',
                'starts_on' => '2026-04-01',
                'ends_on' => '2027-03-31',
                'is_active' => true,
            ]
        );

        $hub = Hub::query()->create(['slug' => 'kumaon', 'name' => 'Kumaon', 'sort_order' => 1]);
        $almora = District::query()->create([
            'hub_id' => $hub->id,
            'slug' => 'almora',
            'name' => 'Almora',
            'sort_order' => 1,
        ]);

        $workshops = app(OfficialMonthlyTargetCodeResolver::class)
            ->deliverableForMisSerial('1.2', 'District Level Workshops');

        foreach ([1 => 0, 2 => 13, 3 => 0, 4 => 13] as $month => $count) {
            OfficialStateMonthlyTarget::query()->create([
                'fiscal_year_id' => $fy->id,
                'deliverable_id' => $workshops->id,
                'month_number' => $month,
                'target_count' => $count,
            ]);
        }

        OfficialDistrictMonthlyTarget::query()->create([
            'fiscal_year_id' => $fy->id,
            'district_id' => $almora->id,
            'deliverable_id' => $workshops->id,
            'month_number' => 2,
            'target_count' => 1,
        ]);

        $scope = ProgramDeliverablesScope::forUser(User::factory()->make(['role' => 'state_admin']));

        $q1Report = app(ProgramDeliverablesReportService::class)->build(
            new ProgramDeliverablesFilter($fy->id, null, null, null, null, null, 1),
            $scope,
        );
        $q1Row = collect($q1Report['rows'])->firstWhere('serial', '1.2');
        $this->assertNotNull($q1Row);
        $this->assertSame(13, $q1Row['target']);
    }

    public function test_deliverables_date_range_filter_sums_overlapping_official_months(): void
    {
        app(\App\Services\ServiceTargetDeliverableSyncService::class)->syncAllServices();

        $fy = FiscalYear::query()->firstOrCreate(
            ['code' => '2026-27'],
            [
                'name' => 'FY 2026-27',
                'starts_on' => '2026-04-01',
                'ends_on' => '2027-03-31',
                'is_active' => true,
            ]
        );

        $hub = Hub::query()->create(['slug' => 'kumaon', 'name' => 'Kumaon', 'sort_order' => 1]);
        $almora = District::query()->create([
            'hub_id' => $hub->id,
            'slug' => 'almora',
            'name' => 'Almora',
            'sort_order' => 1,
        ]);

        $cfa = app(OfficialMonthlyTargetCodeResolver::class)
            ->deliverableForMisSerial('1.1', 'Call for application');

        foreach ([1 => 10, 2 => 20, 3 => 30, 4 => 40] as $month => $count) {
            OfficialDistrictMonthlyTarget::query()->create([
                'fiscal_year_id' => $fy->id,
                'district_id' => $almora->id,
                'deliverable_id' => $cfa->id,
                'month_number' => $month,
                'target_count' => $count,
            ]);
        }

        $scope = ProgramDeliverablesScope::forUser(User::factory()->make(['role' => 'state_admin']));

        $twoMonthReport = app(ProgramDeliverablesReportService::class)->build(
            new ProgramDeliverablesFilter($fy->id, $almora->id, null, null, '2026-04-01', '2026-05-31'),
            $scope,
        );
        $twoMonthRow = collect($twoMonthReport['rows'])->firstWhere('serial', '1.1');
        $this->assertNotNull($twoMonthRow);
        $this->assertSame(30, $twoMonthRow['target']);

        $partialMonthReport = app(ProgramDeliverablesReportService::class)->build(
            new ProgramDeliverablesFilter($fy->id, $almora->id, null, null, '2026-04-15', '2026-05-10'),
            $scope,
        );
        $partialMonthRow = collect($partialMonthReport['rows'])->firstWhere('serial', '1.1');
        $this->assertNotNull($partialMonthRow);
        $this->assertSame(30, $partialMonthRow['target']);
    }

    public function test_deliverables_hub_targets_only_on_primary_hub_districts(): void
    {
        app(\App\Services\ServiceTargetDeliverableSyncService::class)->syncAllServices();

        $fy = FiscalYear::query()->firstOrCreate(
            ['code' => '2026-27'],
            [
                'name' => 'FY 2026-27',
                'starts_on' => '2026-04-01',
                'ends_on' => '2027-03-31',
                'is_active' => true,
            ]
        );

        $hub = Hub::query()->create(['slug' => 'kumaon', 'name' => 'Kumaon', 'sort_order' => 1]);
        $almora = District::query()->create([
            'hub_id' => $hub->id,
            'slug' => 'almora',
            'name' => 'Almora',
            'sort_order' => 1,
        ]);
        $bageshwar = District::query()->create([
            'hub_id' => $hub->id,
            'slug' => 'bageshwar',
            'name' => 'Bageshwar',
            'sort_order' => 2,
        ]);

        $utdb = app(OfficialMonthlyTargetCodeResolver::class)
            ->deliverableForMisSerial('4.2.3', 'UTDB');

        foreach ([1 => 5, 2 => 7] as $month => $count) {
            OfficialHubMonthlyTarget::query()->create([
                'fiscal_year_id' => $fy->id,
                'hub_id' => $hub->id,
                'deliverable_id' => $utdb->id,
                'month_number' => $month,
                'target_count' => $count,
            ]);
        }

        $scope = ProgramDeliverablesScope::forUser(User::factory()->make(['role' => 'state_admin']));

        $almoraReport = app(ProgramDeliverablesReportService::class)->build(
            new ProgramDeliverablesFilter($fy->id, $almora->id, null, null, null, null),
            $scope,
        );
        $almoraRow = collect($almoraReport['rows'])->firstWhere('serial', '4.2.3');
        $this->assertNotNull($almoraRow);
        $this->assertSame(12, $almoraRow['target']);

        $bageshwarReport = app(ProgramDeliverablesReportService::class)->build(
            new ProgramDeliverablesFilter($fy->id, $bageshwar->id, null, null, null, null),
            $scope,
        );
        $bageshwarRow = collect($bageshwarReport['rows'])->firstWhere('serial', '4.2.3');
        $this->assertNotNull($bageshwarRow);
        $this->assertSame(0, $bageshwarRow['target']);
    }
}
