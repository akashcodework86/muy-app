<?php

namespace Tests\Feature;

use App\Models\CaseStudyShortlist;
use App\Models\CaseStudyEntry;
use App\Models\CfaSubmission;
use App\Models\District;
use App\Models\FiscalYear;
use App\Models\Hub;
use App\Models\OnboardingBatch;
use App\Models\TechnicalTraining;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CaseStudyShortlistTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_district_staff_can_shortlist_onboarded_candidate_and_cannot_repeat_them(): void
    {
        Carbon::setTestNow('2026-08-05 10:00:00');
        [$district, $staff] = $this->districtAndStaff();
        $candidate = $this->onboardedCandidate($district, 1, '9999900001');

        $this->actingAs($staff)->post(route('staff.case-study-shortlists.store'), [
            'source' => 'phase3', 'source_application_id' => $candidate->id,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('case_study_shortlists', [
            'candidate_key' => 'phase3:'.$candidate->id,
            'district_id' => $district->id,
            'program_year' => '2026-27',
            'shortlist_month' => '2026-08-01',
            'created_by_user_id' => $staff->id,
        ]);

        $this->actingAs($staff)->post(route('staff.case-study-shortlists.store'), [
            'source' => 'phase3', 'source_application_id' => $candidate->id,
        ])->assertSessionHasErrors('candidate');
        $this->assertSame(1, CaseStudyShortlist::query()->count());
    }

    public function test_monthly_limit_is_shared_by_all_staff_in_the_district(): void
    {
        Carbon::setTestNow('2026-08-05 10:00:00');
        [$district, $staff] = $this->districtAndStaff();
        $secondStaff = User::factory()->create(['role' => 'district_staff', 'district_id' => $district->id, 'is_active' => true]);

        foreach (range(1, 5) as $number) {
            $candidate = $this->onboardedCandidate($district, $number, '99999000'.str_pad((string) $number, 2, '0', STR_PAD_LEFT));
            $this->actingAs($number % 2 ? $staff : $secondStaff)->post(route('staff.case-study-shortlists.store'), [
                'source' => 'phase3', 'source_application_id' => $candidate->id,
            ])->assertSessionHasNoErrors();
        }

        $sixth = $this->onboardedCandidate($district, 6, '9999900006');
        $this->actingAs($secondStaff)->post(route('staff.case-study-shortlists.store'), [
            'source' => 'phase3', 'source_application_id' => $sixth->id,
        ])->assertSessionHasErrors('candidate');
        $this->assertSame(5, CaseStudyShortlist::query()->whereNull('removed_at')->count());
    }

    public function test_hub_admin_sees_only_own_hub_and_can_remark_and_remove_with_reason(): void
    {
        Carbon::setTestNow('2026-08-05 10:00:00');
        [$district, $staff, $hub] = $this->districtAndStaff();
        $candidate = $this->onboardedCandidate($district, 1, '9999900001');
        $this->actingAs($staff)->post(route('staff.case-study-shortlists.store'), [
            'source' => 'phase3', 'source_application_id' => $candidate->id,
        ]);
        $shortlist = CaseStudyShortlist::query()->firstOrFail();
        $hubAdmin = User::factory()->create(['role' => 'hub_admin', 'hub_id' => $hub->id, 'is_active' => true]);

        $this->actingAs($hubAdmin)->get(route('hub.case-study-shortlists.index'))
            ->assertOk()->assertSee($candidate->applicant_name);
        $this->actingAs($hubAdmin)->post(route('hub.case-study-shortlists.remarks.store', $shortlist), [
            'remark' => 'Strong candidate for a food-processing case study.',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('case_study_shortlist_remarks', ['case_study_shortlist_id' => $shortlist->id, 'user_id' => $hubAdmin->id]);

        $this->actingAs($hubAdmin)->delete(route('hub.case-study-shortlists.destroy', $shortlist), [])
            ->assertSessionHasErrors('removal_reason');
        $this->actingAs($hubAdmin)->delete(route('hub.case-study-shortlists.destroy', $shortlist), ['removal_reason' => 'Selected by mistake'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertNotNull($shortlist->fresh()->removed_at);
    }

    public function test_only_creator_can_remove_a_district_staff_nomination(): void
    {
        Carbon::setTestNow('2026-08-05 10:00:00');
        [$district, $creator] = $this->districtAndStaff();
        $otherStaff = User::factory()->create(['role' => 'district_staff', 'district_id' => $district->id, 'is_active' => true]);
        $candidate = $this->onboardedCandidate($district, 1, '9999900001');
        $this->actingAs($creator)->post(route('staff.case-study-shortlists.store'), ['source' => 'phase3', 'source_application_id' => $candidate->id]);
        $shortlist = CaseStudyShortlist::query()->firstOrFail();

        $this->actingAs($otherStaff)->delete(route('staff.case-study-shortlists.destroy', $shortlist))->assertForbidden();
        $this->actingAs($creator)->delete(route('staff.case-study-shortlists.destroy', $shortlist))->assertRedirect();
        $this->assertNotNull($shortlist->fresh()->removed_at);
    }

    public function test_state_admin_can_view_professional_profile_and_nominate_for_configured_services(): void
    {
        Carbon::setTestNow('2026-08-05 10:00:00');
        [$district, $staff] = $this->districtAndStaff();
        $candidate = $this->onboardedCandidate($district, 1, '9999900001');
        $this->actingAs($staff)->post(route('staff.case-study-shortlists.store'), [
            'source' => 'phase3', 'source_application_id' => $candidate->id,
        ]);
        $shortlist = CaseStudyShortlist::query()->firstOrFail();
        $stateAdmin = User::factory()->create(['role' => 'state_admin', 'is_active' => true]);

        $this->actingAs($stateAdmin)->get(route('admin.case-study-shortlists.show', $shortlist))
            ->assertOk()
            ->assertSee('Incubatee professional profile')
            ->assertSee('Acceleration Services')
            ->assertSee('Pitch Deck Preparation')
            ->assertSee('Technical Training')
            ->assertSee('Case Study');

        $this->actingAs($stateAdmin)->put(route('admin.case-study-shortlists.nominations.update', $shortlist), [
            'services' => ['acceleration', 'pitch_deck', 'technical_training', 'case_study'],
            'nomination_note' => 'Strong candidate for structured support.',
        ])->assertRedirect()->assertSessionHasNoErrors();

        foreach (['acceleration', 'pitch_deck', 'technical_training', 'case_study'] as $code) {
            $this->assertDatabaseHas('case_study_shortlist_nominations', [
                'case_study_shortlist_id' => $shortlist->id,
                'service_code' => $code,
                'status' => 'nominated',
                'nominated_by_user_id' => $stateAdmin->id,
            ]);
        }
        $this->assertDatabaseCount('case_study_shortlist_nomination_events', 4);
    }

    public function test_hub_and_district_users_can_view_profile_but_only_state_admin_can_update_nominations(): void
    {
        Carbon::setTestNow('2026-08-05 10:00:00');
        [$district, $staff, $hub] = $this->districtAndStaff();
        $candidate = $this->onboardedCandidate($district, 1, '9999900001');
        $this->actingAs($staff)->post(route('staff.case-study-shortlists.store'), [
            'source' => 'phase3', 'source_application_id' => $candidate->id,
        ]);
        $shortlist = CaseStudyShortlist::query()->firstOrFail();
        $hubAdmin = User::factory()->create(['role' => 'hub_admin', 'hub_id' => $hub->id, 'is_active' => true]);

        $this->actingAs($staff)->get(route('staff.case-study-shortlists.show', $shortlist))
            ->assertOk()->assertSee('No active service nominations yet.');
        $this->actingAs($hubAdmin)->get(route('hub.case-study-shortlists.show', $shortlist))
            ->assertOk()->assertSee('No active service nominations yet.');
        $this->actingAs($hubAdmin)->put(route('admin.case-study-shortlists.nominations.update', $shortlist), [
            'services' => ['technical_training'],
        ])->assertForbidden();
    }

    public function test_completed_technical_training_and_case_study_are_not_available_for_duplicate_nomination(): void
    {
        Carbon::setTestNow('2026-08-05 10:00:00');
        [$district, $staff] = $this->districtAndStaff();
        $candidate = $this->onboardedCandidate($district, 1, '9999900001');
        $this->actingAs($staff)->post(route('staff.case-study-shortlists.store'), [
            'source' => 'phase3', 'source_application_id' => $candidate->id,
        ]);
        $shortlist = CaseStudyShortlist::query()->firstOrFail();
        $stateAdmin = User::factory()->create(['role' => 'state_admin', 'is_active' => true]);
        TechnicalTraining::query()->create([
            'submitted_by_user_id' => $stateAdmin->id, 'submitted_by_name' => $stateAdmin->name,
            'event_date' => '2026-08-02', 'district_id' => $district->id, 'district_name' => $district->name,
            'session_name' => 'Product quality', 'attendance_media_json' => [],
            'selected_incubatee_ids' => [$candidate->id], 'selected_incubatees_snapshot' => [], 'status' => 'approved',
        ]);
        CaseStudyEntry::query()->create([
            'story_title' => 'Growth story', 'story_type' => 'case_study', 'cfa_submission_id' => $candidate->id,
            'incubatee_name' => $candidate->applicant_name, 'application_no' => $candidate->application_no,
            'story_date' => '2026-08-03', 'submitted_by_user_id' => $stateAdmin->id, 'submitted_by_name' => $stateAdmin->name,
        ]);

        $this->actingAs($stateAdmin)->get(route('admin.case-study-shortlists.show', $shortlist))
            ->assertOk()->assertSee('Already received');
        $this->actingAs($stateAdmin)->put(route('admin.case-study-shortlists.nominations.update', $shortlist), [
            'services' => ['technical_training'],
        ])->assertSessionHasErrors('services');
        $this->assertDatabaseMissing('case_study_shortlist_nominations', [
            'case_study_shortlist_id' => $shortlist->id, 'service_code' => 'technical_training',
        ]);
    }

    /** @return array{District, User, Hub} */
    private function districtAndStaff(): array
    {
        $hub = Hub::query()->create(['slug' => 'shortlist-hub', 'name' => 'Shortlist Hub', 'sort_order' => 1]);
        $district = District::query()->create(['hub_id' => $hub->id, 'slug' => 'shortlist-district', 'name' => 'Shortlist District', 'sort_order' => 1]);
        $staff = User::factory()->create(['role' => 'district_staff', 'district_id' => $district->id, 'is_active' => true]);

        return [$district, $staff, $hub];
    }

    private function onboardedCandidate(District $district, int $number, string $phone): CfaSubmission
    {
        $fy = FiscalYear::query()->firstOrCreate(['code' => '2026-27'], [
            'name' => 'FY 2026-27', 'starts_on' => '2026-04-01', 'ends_on' => '2027-03-31', 'is_active' => true,
        ]);
        $candidate = CfaSubmission::query()->create([
            'application_no' => 'SHORT-'.$number, 'fiscal_year_id' => $fy->id, 'district_id' => $district->id,
            'source' => 'public_form', 'applicant_name' => 'Candidate '.$number, 'phone' => $phone,
            'payload' => ['block' => 'Test Block', 'gender' => 'Female', 'business_category' => 'Food Processing', 'form_stage' => 'Growth', 'product' => 'Pickle'],
        ]);
        $batch = OnboardingBatch::query()->create([
            'hub_id' => $district->hub_id, 'district_id' => $district->id, 'name' => 'Batch '.$number,
            'target_size' => 10, 'status' => 'locked', 'locked_at' => now(),
        ]);
        DB::table('onboarding_batch_cfa')->insert([
            'onboarding_batch_id' => $batch->id, 'cfa_submission_id' => $candidate->id, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return $candidate;
    }
}
