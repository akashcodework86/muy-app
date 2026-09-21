<?php

namespace Tests\Feature;

use App\Models\CfaSubmission;
use App\Models\Designation;
use App\Models\District;
use App\Models\FiscalYear;
use App\Models\Hub;
use App\Models\User;
use App\Support\OnboardingPriorityAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class OnboardingPriorityListTest extends TestCase
{
    use RefreshDatabase;

    public function test_incubation_manager_sees_district_priority_list(): void
    {
        [$district, $fy] = $this->seedDistrictAndFy();
        $im = $this->createIncubationManager($district);
        $this->createSubmission($district, $fy, 'APP-HIGH', [
            'form_stage' => 'Seed',
            'training_received' => 'Yes',
            'financial_support' => 'Yes',
            'migrated_for_employment' => 'Yes',
            'empwomen' => 'Yes',
            'expectations' => ['a', 'b', 'c', 'd', 'e', 'g'],
            'techuse' => 'Website',
            'sustainability' => 'Yes',
        ]);

        $this->actingAs($im)
            ->get(route('staff.onboarding-priority.index'))
            ->assertOk()
            ->assertSee('Onboarding Priority List')
            ->assertSee('APP-HIGH')
            ->assertSee('Score');
    }

    public function test_non_incubation_manager_staff_gets_forbidden(): void
    {
        [$district, $fy] = $this->seedDistrictAndFy();
        unset($fy);
        $staff = User::factory()->create([
            'role' => 'district_staff',
            'district_id' => $district->id,
            'is_active' => true,
        ]);

        $this->actingAs($staff)
            ->get(route('staff.onboarding-priority.index'))
            ->assertForbidden();
    }

    public function test_hub_admin_can_open_hub_priority_list(): void
    {
        [$district, $fy] = $this->seedDistrictAndFy();
        $hub = Hub::query()->find($district->hub_id);
        $hubAdmin = User::factory()->create([
            'role' => 'hub_admin',
            'hub_id' => $hub->id,
            'is_active' => true,
        ]);
        $this->createSubmission($district, $fy, 'APP-HUB', [
            'form_stage' => 'Early',
            'is_registered' => 'Yes',
            'business_age' => '7-12 months',
            'loan_taken' => 'No',
            'regular_buyer' => 'No',
            'training_received' => 'Yes',
            'current_employment' => 'No',
            'turnover_last_fy' => '50000',
            'financial_support' => 'Yes',
            'migrated_for_employment' => 'Yes',
            'empwomen' => 'Yes',
            'expectations' => ['a', 'b', 'c'],
            'techuse' => 'Social media',
            'sustainability' => 'Yes',
        ]);

        $this->actingAs($hubAdmin)
            ->get(route('hub.onboarding-priority.index', ['stage' => 'early']))
            ->assertOk()
            ->assertSee('APP-HUB')
            ->assertSee(route('hub.batches.cfa.show', CfaSubmission::query()->where('application_no', 'APP-HUB')->firstOrFail()), false);
    }

    public function test_defaults_to_seed_stage_tab(): void
    {
        [$district, $fy] = $this->seedDistrictAndFy();
        $im = $this->createIncubationManager($district);
        $this->createSubmission($district, $fy, 'APP-SEED-DEFAULT', [
            'form_stage' => 'Seed',
            'training_received' => 'Yes',
            'financial_support' => 'Yes',
            'migrated_for_employment' => 'Yes',
            'empwomen' => 'Yes',
            'expectations' => ['a', 'b'],
            'techuse' => 'WhatsApp',
            'sustainability' => 'Yes',
        ]);
        $this->createSubmission($district, $fy, 'APP-EARLY-HIDDEN', [
            'form_stage' => 'Early',
            'is_registered' => 'Yes',
            'business_age' => '0',
            'loan_taken' => 'No',
            'regular_buyer' => 'No',
            'training_received' => 'No',
            'current_employment' => 'No',
            'turnover_last_fy' => '0',
            'financial_support' => 'No',
            'migrated_for_employment' => 'No',
            'empwomen' => 'No',
            'expectations' => ['a'],
            'techuse' => 'WhatsApp',
            'sustainability' => 'No',
        ]);

        $this->actingAs($im)
            ->get(route('staff.onboarding-priority.index'))
            ->assertOk()
            ->assertSee('APP-SEED-DEFAULT')
            ->assertDontSee('APP-EARLY-HIDDEN');
    }

    public function test_all_stages_view_shows_stage_wise_rank_labels(): void
    {
        [$district, $fy] = $this->seedDistrictAndFy();
        $im = $this->createIncubationManager($district);
        $this->createSubmission($district, $fy, 'APP-SEED-RANK', [
            'form_stage' => 'Seed',
            'training_received' => 'Yes',
            'financial_support' => 'Yes',
            'migrated_for_employment' => 'Yes',
            'empwomen' => 'Yes',
            'expectations' => ['a', 'b', 'c', 'd', 'e', 'g'],
            'techuse' => 'Website',
            'sustainability' => 'Yes',
        ]);
        $this->createSubmission($district, $fy, 'APP-GROWTH-RANK', [
            'form_stage' => 'Growth',
            'is_registered' => 'Yes',
            'business_age' => '>24 months',
            'loan_taken' => 'No',
            'regular_buyer' => 'No',
            'training_received' => 'No',
            'current_employment' => 'No',
            'turnover_last_fy' => '700000',
            'financial_support' => 'No',
            'migrated_for_employment' => 'No',
            'empwomen' => 'No',
            'expectations' => ['a'],
            'techuse' => 'WhatsApp',
            'sustainability' => 'No',
        ]);

        $this->actingAs($im)
            ->get(route('staff.onboarding-priority.index', ['stage' => 'all']))
            ->assertOk()
            ->assertSee('Seed #1', false)
            ->assertSee('Growth #1', false)
            ->assertSee('Stage rank', false);
    }

    public function test_breakdown_shows_all_dimension_labels(): void
    {
        [$district, $fy] = $this->seedDistrictAndFy();
        $im = $this->createIncubationManager($district);
        $this->createSubmission($district, $fy, 'APP-BREAKDOWN', [
            'form_stage' => 'Seed',
            'training_received' => 'Yes',
            'financial_support' => 'Yes',
            'migrated_for_employment' => 'Yes',
            'empwomen' => 'Yes',
            'expectations' => ['a', 'b'],
            'techuse' => 'WhatsApp',
            'sustainability' => 'Yes',
        ]);

        $this->actingAs($im)
            ->get(route('staff.onboarding-priority.index'))
            ->assertOk()
            ->assertSee('Vision &amp; clarity', false)
            ->assertSee('Innovation &amp; technology', false)
            ->assertSee('Environmental impact', false)
            ->assertSee('Breakdown', false);
    }

    public function test_state_admin_can_open_statewide_priority_list(): void
    {
        [$district, $fy] = $this->seedDistrictAndFy();
        $admin = User::factory()->create(['role' => 'state_admin', 'is_active' => true]);
        $this->createSubmission($district, $fy, 'APP-STATE', [
            'form_stage' => 'Seed',
            'training_received' => 'Yes',
            'financial_support' => 'Yes',
            'migrated_for_employment' => 'Yes',
            'empwomen' => 'Yes',
            'expectations' => ['a', 'b'],
            'techuse' => 'WhatsApp',
            'sustainability' => 'Yes',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.onboarding-priority.index'))
            ->assertOk()
            ->assertSee('APP-STATE');
    }

    public function test_onboarding_priority_route_names_are_registered(): void
    {
        foreach ([
            'admin.onboarding-priority.index',
            'admin.onboarding-priority.export',
            'hub.onboarding-priority.index',
            'hub.onboarding-priority.export',
            'staff.onboarding-priority.index',
            'staff.onboarding-priority.export',
        ] as $name) {
            $this->assertTrue(Route::has($name), $name.' should be registered');
        }
    }

    public function test_incubation_manager_dashboard_does_not_crash_and_links_to_priority_list(): void
    {
        [$district] = $this->seedDistrictAndFy();
        $im = $this->createIncubationManager($district);

        $this->assertNotNull(OnboardingPriorityAccess::indexUrl($im));

        $this->actingAs($im)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Onboarding priority', false)
            ->assertSee(route('staff.onboarding-priority.index'), false);
    }

    /** @return array{0: District, 1: FiscalYear} */
    private function seedDistrictAndFy(): array
    {
        $hub = Hub::query()->create(['slug' => 'priority-hub', 'name' => 'Priority Hub', 'sort_order' => 1]);
        $district = District::query()->create([
            'hub_id' => $hub->id,
            'slug' => 'priority-district',
            'name' => 'Priority District',
            'sort_order' => 1,
        ]);
        $fy = FiscalYear::query()->create([
            'code' => '2026-27',
            'name' => 'FY 2026-27',
            'starts_on' => '2026-04-01',
            'ends_on' => '2027-03-31',
            'is_active' => true,
        ]);

        return [$district, $fy];
    }

    private function createIncubationManager(District $district): User
    {
        $designation = Designation::query()->create([
            'name' => 'Incubation Manager',
            'sort_order' => 1,
        ]);

        return User::factory()->create([
            'role' => 'district_staff',
            'district_id' => $district->id,
            'designation_id' => $designation->id,
            'is_active' => true,
        ]);
    }

    /** @param  array<string, mixed>  $payload
     */
    private function createSubmission(District $district, FiscalYear $fy, string $applicationNo, array $payload): CfaSubmission
    {
        return CfaSubmission::query()->create([
            'application_no' => $applicationNo,
            'fiscal_year_id' => $fy->id,
            'district_id' => $district->id,
            'applicant_name' => 'Priority Applicant',
            'phone' => '9876543210',
            'payload' => $payload,
        ]);
    }
}
