<?php

namespace Tests\Feature;

use App\Models\CfaSubmission;
use App\Models\District;
use App\Models\HomestaySurveyResponse;
use App\Models\Hub;
use App\Models\User;
use App\Services\AppSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomestaySurveyTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_survey_page_loads(): void
    {
        $this->get(route('homestay-survey.show'))
            ->assertOk()
            ->assertSee('Homestay Progress Survey', false);
    }

    public function test_lookup_rejects_unknown_phone(): void
    {
        $this->postJson(route('homestay-survey.lookup'), ['phone' => '9876543210'])
            ->assertNotFound()
            ->assertJson(['status' => 'not_found']);
    }

    public function test_lookup_and_submit_for_phase3_homestay(): void
    {
        $district = $this->createDistrict('hs-survey', 'Nainital');
        CfaSubmission::query()->create([
            'application_no' => 'MUY-HS-001',
            'district_id' => $district->id,
            'source' => 'phase3',
            'applicant_name' => 'Test Host',
            'phone' => '9876543210',
            'payload' => [
                'business_category' => 'Homestay',
                'gender' => 'Female',
                'dob' => '1990-05-01',
                'caste' => 'OBC',
                'village' => 'Test Village',
                'block' => 'Test Block',
                'pincode' => '263001',
                'location_type' => 'Rural',
                'email' => 'host@example.com',
                'form_stage' => 'Early',
            ],
        ]);

        $this->postJson(route('homestay-survey.lookup'), ['phone' => '9876543210'])
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('prefill.respondent_name', 'Test Host')
            ->assertJsonPath('prefill.caste', 'OBC');

        $this->post(route('homestay-survey.store'), [
            'phone' => '9876543210',
            'consent' => '1',
            'answers' => [
                'respondent_name' => 'Test Host',
                'gender' => 'Female',
                'enterprise_name' => 'Hill View Homestay',
                'district' => 'Nainital',
                'room_count' => '2',
            ],
        ])->assertRedirect(route('homestay-survey.thanks'));

        $this->get(route('homestay-survey.thanks'))
            ->assertOk()
            ->assertSee('Thank you', false)
            ->assertSee('Fill new entry', false);

        // Refresh / direct revisit should open a new blank form
        $this->get(route('homestay-survey.thanks'))
            ->assertRedirect(route('homestay-survey.show'));

        $this->assertDatabaseHas('homestay_survey_responses', [
            'phone' => '9876543210',
            'applicant_name' => 'Test Host',
            'phase' => 'Phase 3',
        ]);

        $this->postJson(route('homestay-survey.lookup'), ['phone' => '9876543210'])
            ->assertStatus(409)
            ->assertJson(['status' => 'already_submitted']);

        $this->followingRedirects()
            ->get(route('homestay-survey.already-submitted', ['phone' => '9876543210']))
            ->assertOk()
            ->assertSee('Already submitted', false)
            ->assertSee('Fill new entry', false);

        $this->get(route('homestay-survey.already-submitted'))
            ->assertRedirect(route('homestay-survey.show'));
    }

    public function test_state_admin_can_toggle_prefill_lock_and_view_dashboard(): void
    {
        $admin = User::factory()->create(['role' => 'state_admin', 'is_active' => true]);

        HomestaySurveyResponse::query()->create([
            'phone' => '9123456789',
            'phase' => 'Phase 3',
            'source_id' => 1,
            'applicant_name' => 'Host Two',
            'district' => 'Dehradun',
            'prefill_snapshot' => [],
            'answers' => ['respondent_name' => 'Host Two', 'consent' => true],
            'submitted_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.homestay-survey.index'))
            ->assertOk()
            ->assertSee('Host Two', false)
            ->assertSee('Editable', false);

        $this->actingAs($admin)
            ->post(route('admin.homestay-survey.prefill-lock'), ['prefill_locked' => 1])
            ->assertRedirect();

        $this->assertTrue(app(AppSettingsService::class)->isEnabled('homestay_survey.prefill_locked'));

        $response = HomestaySurveyResponse::query()->firstOrFail();
        $this->actingAs($admin)
            ->get(route('admin.homestay-survey.show', $response))
            ->assertOk()
            ->assertSee('Host Two', false);
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
