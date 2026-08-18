<?php

namespace Tests\Feature;

use App\Models\District;
use App\Models\DistrictDeliverableTarget;
use App\Models\Deliverable;
use App\Models\FiscalYear;
use App\Models\Hub;
use App\Models\Service;
use App\Models\ServiceCase;
use App\Models\ServiceCategory;
use App\Models\StateDeliverableTarget;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OnboardedApplicantTest extends TestCase
{
    use RefreshDatabase;

    public function test_state_admin_sees_district_summaries_and_applicant_cards(): void
    {
        $admin = User::factory()->create([
            'role' => 'state_admin',
            'is_active' => true,
        ]);

        $districtA = $this->createDistrict('almora', 'Almora');
        $districtB = $this->createDistrict('nainital', 'Nainital');

        $this->seedOnboardedApplicant($districtA, '40801001', 'Female Applicant', 'female', 'Yes', 'phase3', null, 'Individual');
        $this->seedOnboardedApplicant($districtA, '40801002', 'Male Applicant', 'male', 'No', 'phase3', null, 'Individual');
        $this->seedOnboardedApplicant($districtB, '40802001', 'Other Applicant', 'female', 'yes', 'phase3', null, 'SHG');

        $response = $this->actingAs($admin)->get(route('admin.onboarded.index'));

        $response->assertOk();
        $response->assertSee('District-wise onboarding');
        $response->assertSee('Total onboarded');
        $response->assertSee('Potential Lakhpati Didi/ SHG Members/ CBOs');
        $response->assertSee('33%');
        $response->assertSeeText('1 Potential Lakhpati Didi/ SHG Members/ CBOs (100%)');
        $response->assertDontSee('Lakhpati Didi (Yes)');
        $response->assertSee('Almora');
        $response->assertSee('Nainital');
        $response->assertSee('Female Applicant');
        $response->assertSee('Applicant records');
    }

    public function test_potential_lakhpati_excludes_legacy_and_lakhpati_only_individual(): void
    {
        $admin = User::factory()->create([
            'role' => 'state_admin',
            'is_active' => true,
        ]);

        $district = $this->createDistrict('chamoli', 'Chamoli');

        $this->seedOnboardedApplicant($district, '40804001', 'Phase3 Lakhpati Only', 'female', 'Yes');
        $this->seedOnboardedApplicant($district, '40804002', 'Legacy SHG', 'female', null, 'legacy_phase2', null, 'SHG');

        $response = $this->actingAs($admin)->get(route('admin.onboarded.index'));

        $response->assertOk();
        $response->assertSeeText('0 Potential Lakhpati Didi/ SHG Members/ CBOs (0%)');
    }

    public function test_potential_lakhpati_counts_legacy_when_lakhpati_and_member_yes(): void
    {
        $admin = User::factory()->create([
            'role' => 'state_admin',
            'is_active' => true,
        ]);

        $district = $this->createDistrict('almora-legacy-lakhpati', 'Almora');

        $this->seedOnboardedApplicant(
            $district,
            '40804101',
            'Legacy Lakhpati Member',
            'female',
            'Yes',
            'legacy_phase2',
            null,
            'Individual',
            null,
            'Yes',
        );
        $this->seedOnboardedApplicant(
            $district,
            '40804102',
            'Legacy Lakhpati No Member',
            'female',
            'Yes',
            'legacy_phase2',
            null,
            'Individual',
            null,
            'No',
        );

        $response = $this->actingAs($admin)->get(route('admin.onboarded.index'));

        $response->assertOk();
        $response->assertSeeText('1 Potential Lakhpati Didi/ SHG Members/ CBOs (50%)');
    }

    public function test_onboarded_page_shows_target_progress_insights_and_sector_breakdown(): void
    {
        $admin = User::factory()->create([
            'role' => 'state_admin',
            'is_active' => true,
        ]);

        $districtA = $this->createDistrict('almora', 'Almora');
        $districtB = $this->createDistrict('nainital', 'Nainital');
        $this->seedOnboardingTargets($districtA, $districtB, stateTarget: 10);

        $this->seedOnboardedApplicant($districtA, '40805001', 'Homestay Applicant', 'female', null, 'phase3', 'Homestay');
        $this->seedOnboardedApplicant($districtA, '40805002', 'Agri Applicant', 'male', null, 'phase3', 'Agri Allied');
        $this->seedOnboardedApplicant($districtB, '40805003', 'Another Homestay', 'female', null, 'phase3', 'Homestay');

        $response = $this->actingAs($admin)->get(route('admin.onboarded.index'));

        $response->assertOk();
        $response->assertSee('State onboarding target');
        $response->assertSee('3 / 10');
        $response->assertSee('Key insights');
        $response->assertSee('Sector mix (all onboarded)');
        $response->assertSee('Homestay');
        $response->assertSee('Top sector: Homestay');
        $response->assertSee('Almora leads with');
    }

    public function test_potential_lakhpati_counts_shg_and_cbo_category(): void
    {
        $admin = User::factory()->create([
            'role' => 'state_admin',
            'is_active' => true,
        ]);

        $district = $this->createDistrict('rudraprayag', 'Rudraprayag');

        $this->seedOnboardedApplicant($district, '40806001', 'SHG Group', 'female', null, 'phase3', null, 'SHG');
        $this->seedOnboardedApplicant($district, '40806002', 'CBO Group', 'female', null, 'phase3', null, 'CBO');
        $this->seedOnboardedApplicant($district, '40806003', 'Lakhpati Individual', 'female', 'Yes', 'phase3', null, 'Individual');
        $this->seedOnboardedApplicant($district, '40806004', 'Other Individual', 'male', 'No', 'phase3', null, 'Individual');

        $response = $this->actingAs($admin)->get(route('admin.onboarded.index'));

        $response->assertOk();
        $response->assertSeeText('2 Potential Lakhpati Didi/ SHG Members/ CBOs (50%)');
    }

    public function test_potential_lakhpati_counts_individual_shg_member_yes(): void
    {
        $admin = User::factory()->create([
            'role' => 'state_admin',
            'is_active' => true,
        ]);

        $district = $this->createDistrict('bageshwar', 'Bageshwar');

        $this->seedOnboardedApplicant($district, '40807001', 'SHG Member Individual', 'female', 'No', 'phase3', null, 'Individual', 'Yes');
        $this->seedOnboardedApplicant($district, '40807002', 'Not Member', 'male', 'No', 'phase3', null, 'Individual', 'No');

        $response = $this->actingAs($admin)->get(route('admin.onboarded.index'));

        $response->assertOk();
        $response->assertSeeText('1 Potential Lakhpati Didi/ SHG Members/ CBOs (50%)');
    }

    public function test_onboarded_totals_bifurcate_phase3_and_legacy(): void
    {
        $admin = User::factory()->create([
            'role' => 'state_admin',
            'is_active' => true,
        ]);

        $district = $this->createDistrict('uttarkashi', 'Uttarkashi');

        $this->seedOnboardedApplicant($district, '40808001', 'Phase3 Applicant', 'female', null, 'phase3', 'Homestay');
        $this->seedOnboardedApplicant($district, '40808002', 'Legacy Applicant', 'female', null, 'legacy_phase2', 'Food Processing');

        $response = $this->actingAs($admin)->get(route('admin.onboarded.index'));

        $response->assertOk();
        $response->assertSee('1 Phase 3 · 1 Legacy');
        $response->assertSee('Homestay');
        $response->assertSee('Food Processing');
    }

    public function test_district_filter_limits_applicant_list(): void
    {
        $admin = User::factory()->create([
            'role' => 'state_admin',
            'is_active' => true,
        ]);

        $districtA = $this->createDistrict('pauri', 'Pauri');
        $districtB = $this->createDistrict('tehri', 'Tehri');

        $this->seedOnboardedApplicant($districtA, '40803001', 'Pauri Applicant', 'female');
        $this->seedOnboardedApplicant($districtB, '40803002', 'Tehri Applicant', 'male');

        $response = $this->actingAs($admin)->get(route('admin.onboarded.index', [
            'district' => $districtA->id,
        ]));

        $response->assertOk();
        $response->assertSee('Pauri Applicant');
        $response->assertDontSee('Tehri Applicant');
    }

    public function test_applicant_records_render_as_table_with_new_filters(): void
    {
        $admin = User::factory()->create([
            'role' => 'state_admin',
            'is_active' => true,
        ]);

        $district = $this->createDistrict('haridwar', 'Haridwar');
        $this->seedOnboardedApplicant($district, '40810001', 'Table Applicant', 'female', null, 'phase3', 'Homestay', 'Individual', null, null, null, null, null, null, '250000');

        $response = $this->actingAs($admin)->get(route('admin.onboarded.index'));

        $response->assertOk();
        $response->assertSee('Applicant records');
        $response->assertSee('Services taken');
        $response->assertSee('Annual income');
        $response->assertSee('Table Applicant');
        $response->assertSee('₹250,000');
        $response->assertDontSee('onb-applicant-card');
        $response->assertSee('All services');
        $response->assertSee('No services taken');
        $response->assertSee('0-2 lakh');
        $response->assertSee('20+ lakh');
    }

    public function test_category_filter_limits_applicant_list(): void
    {
        $admin = User::factory()->create([
            'role' => 'state_admin',
            'is_active' => true,
        ]);

        $district = $this->createDistrict('dehradun', 'Dehradun');
        $this->seedOnboardedApplicant($district, '40811001', 'Individual Applicant', 'female', null, 'phase3', null, 'Individual');
        $this->seedOnboardedApplicant($district, '40811002', 'SHG Applicant', 'female', null, 'phase3', null, 'SHG');

        $response = $this->actingAs($admin)->get(route('admin.onboarded.index', [
            'category' => 'SHG',
        ]));

        $response->assertOk();
        $response->assertSee('SHG Applicant');
        $response->assertDontSee('Individual Applicant');
    }

    public function test_annual_income_filter_limits_applicant_list(): void
    {
        $admin = User::factory()->create([
            'role' => 'state_admin',
            'is_active' => true,
        ]);

        $district = $this->createDistrict('udham', 'Udham Singh Nagar');
        $this->seedOnboardedApplicant($district, '40812001', 'Zero Income Applicant', 'female', null, 'phase3', null, 'Individual', null, null, null, null, null, null, '0');
        $this->seedOnboardedApplicant($district, '40812002', 'Mid Income Applicant', 'male', null, 'phase3', null, 'Individual', null, null, null, null, null, null, '250000');

        $response = $this->actingAs($admin)->get(route('admin.onboarded.index', [
            'income' => '2_5l',
        ]));

        $response->assertOk();
        $response->assertSee('Mid Income Applicant');
        $response->assertDontSee('Zero Income Applicant');
    }

    public function test_services_taken_column_and_filter(): void
    {
        $admin = User::factory()->create([
            'role' => 'state_admin',
            'is_active' => true,
        ]);

        $district = $this->createDistrict('champawat', 'Champawat');
        $servedId = $this->seedOnboardedApplicant($district, '40813001', 'Served Applicant', 'female');
        $this->seedOnboardedApplicant($district, '40813002', 'Unserved Applicant', 'male');

        $serviceCategory = ServiceCategory::query()->create([
            'slug' => 'onboarded-test-services',
            'name' => 'Onboarded Test Services',
            'sort_order' => 1,
        ]);
        $service = Service::query()->create([
            'service_category_id' => $serviceCategory->id,
            'code' => 'onboarded-gst',
            'name' => 'GST Registration',
            'sort_order' => 1,
            'is_active' => true,
        ]);
        $secondService = Service::query()->create([
            'service_category_id' => $serviceCategory->id,
            'code' => 'onboarded-fssai',
            'name' => 'FSSAI Registration',
            'sort_order' => 2,
            'is_active' => true,
        ]);
        ServiceCase::query()->create([
            'cfa_submission_id' => $servedId,
            'service_id' => $service->id,
            'status' => ServiceCase::STATUS_APPROVED,
            'created_by' => $admin->id,
        ]);
        ServiceCase::query()->create([
            'cfa_submission_id' => $servedId,
            'service_id' => $secondService->id,
            'status' => ServiceCase::STATUS_APPROVED,
            'created_by' => $admin->id,
        ]);

        $list = $this->actingAs($admin)->get(route('admin.onboarded.index'));
        $list->assertOk();
        $list->assertSee('Served Applicant');
        $list->assertSee('Unserved Applicant');
        $list->assertSee('GST Registration');
        $list->assertSee('FSSAI Registration');
        $list->assertSee('name="service"', false);
        $list->assertSee('All services');
        $list->assertSee(route('admin.onboarded.index', ['service' => $service->id]), false);

        $filtered = $this->actingAs($admin)->get(route('admin.onboarded.index', [
            'service' => $service->id,
        ]));
        $filtered->assertOk();
        $filtered->assertSee('Served Applicant');
        $filtered->assertDontSee('Unserved Applicant');

        $none = $this->actingAs($admin)->get(route('admin.onboarded.index', [
            'service' => '__none__',
        ]));
        $none->assertOk();
        $none->assertSee('Unserved Applicant');
        $none->assertDontSee('Served Applicant');
    }

    public function test_onboarded_at_uses_batch_creation_date(): void
    {
        $admin = User::factory()->create([
            'role' => 'state_admin',
            'is_active' => true,
        ]);

        $district = $this->createDistrict('pithoragarh', 'Pithoragarh');

        $this->seedOnboardedApplicant(
            $district,
            '40809001',
            'Dated Applicant',
            'female',
            null,
            'phase3',
            null,
            'Individual',
            null,
            null,
            '2026-01-15',
            '2026-06-21 09:00:00',
            '2026-06-20 14:30:00',
            '2026-05-22 00:40:11',
        );

        $response = $this->actingAs($admin)->get(route('admin.onboarded.index'));

        $response->assertOk();
        $response->assertSee('22 May 2026, 00:40');
        $response->assertDontSee('20 Jun 2026');
        $response->assertDontSee('15 Jan 2026');
        $response->assertDontSee('21 Jun 2026');
    }

    private function seedOnboardedApplicant(
        District $district,
        string $applicationNo,
        string $name,
        string $gender,
        ?string $lakhpati = null,
        string $source = 'phase3',
        ?string $businessCategory = null,
        string $category = 'Individual',
        ?string $isMember = null,
        ?string $isShgMember = null,
        ?string $onboardingDate = null,
        ?string $membershipCreatedAt = null,
        ?string $lockedAt = null,
        ?string $batchCreatedAt = null,
        ?string $turnoverLastFy = null,
    ): int {
        $payload = [
            'gender' => $gender,
            'block' => 'Test Block',
            'guardian_name' => 'Guardian',
            'category' => $category,
        ];
        if ($lakhpati !== null) {
            $payload['lakhpati'] = $lakhpati;
        }
        if ($businessCategory !== null) {
            $payload['business_category'] = $businessCategory;
        }
        if ($isMember !== null) {
            $payload['is_member'] = $isMember;
        }
        if ($isShgMember !== null) {
            $payload['is_shg_member'] = $isShgMember;
        }
        if ($turnoverLastFy !== null) {
            $payload['turnover_last_fy'] = $turnoverLastFy;
        }

        $cfaId = (int) DB::table('cfa_submissions')->insertGetId([
            'district_id' => $district->id,
            'application_no' => $applicationNo,
            'applicant_name' => $name,
            'phone' => '9876543210',
            'source' => $source,
            'payload' => json_encode($payload),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $batchId = (int) DB::table('onboarding_batches')->insertGetId([
            'hub_id' => $district->hub_id,
            'district_id' => $district->id,
            'name' => $district->name.'-batch1',
            'target_size' => 1,
            'status' => 'locked',
            'locked_at' => $lockedAt ?? now(),
            'onboarding_date' => $onboardingDate,
            'created_at' => $batchCreatedAt ?? now(),
            'updated_at' => $batchCreatedAt ?? now(),
        ]);

        DB::table('onboarding_batch_cfa')->insert([
            'onboarding_batch_id' => $batchId,
            'cfa_submission_id' => $cfaId,
            'created_at' => $membershipCreatedAt ?? now(),
            'updated_at' => $membershipCreatedAt ?? now(),
        ]);

        return $cfaId;
    }

    private function seedOnboardingTargets(District $districtA, District $districtB, int $stateTarget): void
    {
        $fiscalYear = FiscalYear::query()->firstOrCreate(
            ['code' => '2026-27'],
            [
                'name' => '2026-27',
                'starts_on' => '2026-04-01',
                'ends_on' => '2027-03-31',
                'is_active' => true,
            ]
        );

        $deliverable = Deliverable::query()->firstOrCreate(
            ['code' => 'onboarding'],
            [
                'sort_order' => 904,
                'name' => 'Number of Incubatees Onboarded',
                'mis_entry_label' => 'Onboarded Incubatees',
                'is_active' => true,
            ]
        );

        StateDeliverableTarget::query()->create([
            'fiscal_year_id' => $fiscalYear->id,
            'deliverable_id' => $deliverable->id,
            'target_total' => $stateTarget,
        ]);

        DistrictDeliverableTarget::query()->create([
            'fiscal_year_id' => $fiscalYear->id,
            'district_id' => $districtA->id,
            'deliverable_id' => $deliverable->id,
            'target_total' => 5,
        ]);

        DistrictDeliverableTarget::query()->create([
            'fiscal_year_id' => $fiscalYear->id,
            'district_id' => $districtB->id,
            'deliverable_id' => $deliverable->id,
            'target_total' => 5,
        ]);
    }

    private function createDistrict(string $slug, string $name): District
    {
        $hub = Hub::query()->create([
            'slug' => 'hub-'.$slug,
            'name' => 'Hub '.$name,
            'sort_order' => 1,
        ]);

        return District::query()->create([
            'hub_id' => $hub->id,
            'slug' => $slug,
            'name' => $name,
            'sort_order' => 1,
        ]);
    }
}
