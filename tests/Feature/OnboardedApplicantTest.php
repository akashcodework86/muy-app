<?php

namespace Tests\Feature;

use App\Models\District;
use App\Models\DistrictDeliverableTarget;
use App\Models\Deliverable;
use App\Models\FiscalYear;
use App\Models\Hub;
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
            'locked_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('onboarding_batch_cfa')->insert([
            'onboarding_batch_id' => $batchId,
            'cfa_submission_id' => $cfaId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $cfaId;
    }

    private function seedOnboardingTargets(District $districtA, District $districtB, int $stateTarget): void
    {
        $fiscalYear = FiscalYear::query()->create([
            'code' => '2026-27',
            'name' => '2026-27',
            'starts_on' => '2026-04-01',
            'ends_on' => '2027-03-31',
            'is_active' => true,
        ]);

        $deliverable = Deliverable::query()->create([
            'sort_order' => 4,
            'code' => 'onboarding',
            'name' => 'Number of Incubatees Onboarded',
            'mis_entry_label' => 'Onboarded Incubatees',
            'is_active' => true,
        ]);

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
