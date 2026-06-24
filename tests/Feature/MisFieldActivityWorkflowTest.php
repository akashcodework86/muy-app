<?php

namespace Tests\Feature;

use App\Models\CommunityOrganizationOutreachVisit;
use App\Models\District;
use App\Models\Hub;
use App\Models\LineDepartmentMeeting;
use App\Models\ServiceCase;
use App\Models\TechnicalTraining;
use App\Models\User;
use App\Services\AppSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class MisFieldActivityWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['mis_field_activity_approval.approver_email' => 'aadil.ishrat@pwc.com']);
        app(AppSettingsService::class)->setMany(['service_module.enabled' => true]);
    }

    public function test_submission_goes_to_pending_approval_and_notifies_approver(): void
    {
        Notification::fake();

        $district = $this->createDistrict();
        $submitter = User::factory()->create([
            'role' => 'district_staff',
            'district_id' => $district->id,
            'is_active' => true,
        ]);
        $approver = User::factory()->create([
            'role' => 'state_staff',
            'email' => 'aadil.ishrat@pwc.com',
            'is_active' => true,
        ]);

        $training = TechnicalTraining::query()->create([
            'submitted_by_user_id' => $submitter->id,
            'submitted_by_name' => $submitter->name,
            'event_date' => '2026-06-01',
            'district_id' => $district->id,
            'district_name' => $district->name,
            'session_name' => 'Test session',
            'attendance_media_json' => [],
            'selected_incubatee_ids' => [1],
            'selected_incubatees_snapshot' => [['incubatee_id' => 1, 'name' => 'A']],
        ]);

        app(\App\Services\MisFieldActivityWorkflowService::class)
            ->submitForApproval($training, (int) $submitter->id);

        $training->refresh();
        $this->assertSame(ServiceCase::STATUS_PENDING_APPROVAL, $training->status);
        $this->assertSame((int) $approver->id, (int) $training->spoc_user_id);

        Notification::assertSentTo($approver, \App\Notifications\ServiceCaseWorkflowNotification::class);
    }

    public function test_approver_can_approve_pending_technical_training(): void
    {
        Notification::fake();

        $district = $this->createDistrict();
        $submitter = User::factory()->create([
            'role' => 'district_staff',
            'district_id' => $district->id,
            'is_active' => true,
        ]);
        $approver = User::factory()->create([
            'role' => 'state_staff',
            'email' => 'aadil.ishrat@pwc.com',
            'is_active' => true,
        ]);

        $training = TechnicalTraining::query()->create([
            'submitted_by_user_id' => $submitter->id,
            'submitted_by_name' => $submitter->name,
            'event_date' => '2026-06-01',
            'district_id' => $district->id,
            'district_name' => $district->name,
            'session_name' => 'Approve me',
            'attendance_media_json' => [],
            'status' => ServiceCase::STATUS_PENDING_APPROVAL,
            'spoc_user_id' => $approver->id,
            'submitted_at' => now(),
            'selected_incubatee_ids' => [1],
            'selected_incubatees_snapshot' => [['incubatee_id' => 1, 'name' => 'A']],
        ]);

        $response = $this->actingAs($approver)->post(
            route('spoc.field-mis-approvals.approve', ['module' => 'technical_training', 'record' => $training->id])
        );

        $response->assertRedirect(route('spoc.service-cases.index', ['status' => ServiceCase::STATUS_PENDING_APPROVAL]));
        $training->refresh();
        $this->assertSame(ServiceCase::STATUS_APPROVED, $training->status);
        Notification::assertSentTo($submitter, \App\Notifications\ServiceCaseWorkflowNotification::class);
    }

    public function test_approver_cannot_review_own_submission(): void
    {
        $approver = User::factory()->create([
            'role' => 'state_staff',
            'email' => 'aadil.ishrat@pwc.com',
            'is_active' => true,
        ]);
        $district = $this->createDistrict();

        $training = TechnicalTraining::query()->create([
            'submitted_by_user_id' => $approver->id,
            'submitted_by_name' => $approver->name,
            'event_date' => '2026-06-01',
            'district_id' => $district->id,
            'district_name' => $district->name,
            'session_name' => 'Self submit',
            'attendance_media_json' => [],
            'status' => ServiceCase::STATUS_PENDING_APPROVAL,
            'spoc_user_id' => $approver->id,
            'submitted_at' => now(),
            'selected_incubatee_ids' => [1],
            'selected_incubatees_snapshot' => [['incubatee_id' => 1, 'name' => 'A']],
        ]);

        $this->actingAs($approver)->post(
            route('spoc.field-mis-approvals.approve', ['module' => 'technical_training', 'record' => $training->id])
        )->assertStatus(422);
    }

    public function test_submitter_can_withdraw_pending_community_org_visit(): void
    {
        $hub = Hub::query()->create(['slug' => 'test-hub', 'name' => 'Test Hub', 'sort_order' => 1]);
        $district = District::query()->create([
            'hub_id' => $hub->id,
            'slug' => 'test-district',
            'name' => 'Test District',
            'sort_order' => 1,
        ]);
        $submitter = User::factory()->create([
            'role' => 'hub_admin',
            'hub_id' => $hub->id,
            'is_active' => true,
        ]);

        $visit = CommunityOrganizationOutreachVisit::query()->create([
            'hub_id' => $hub->id,
            'hub_name' => $hub->name,
            'district_id' => $district->id,
            'district_name' => $district->name,
            'visit_date' => '2026-06-10',
            'organization_name' => 'Test NGO',
            'organization_type' => 'ngo',
            'person_met_name' => 'Person',
            'poc_name' => 'POC',
            'poc_phone' => '9876543210',
            'purpose' => 'awareness',
            'meeting_mode' => 'physical',
            'submitted_by_user_id' => $submitter->id,
            'submitted_by_name' => $submitter->name,
            'status' => ServiceCase::STATUS_PENDING_APPROVAL,
            'submitted_at' => now(),
        ]);

        $this->actingAs($submitter)->delete(route('hub.community-org-outreach.destroy', $visit))
            ->assertRedirect(route('hub.community-org-outreach.dashboard'));

        $this->assertDatabaseMissing('community_organization_outreach_visits', ['id' => $visit->id]);
    }

    public function test_only_approved_line_department_meetings_count_in_deliverables(): void
    {
        LineDepartmentMeeting::query()->create([
            'submitted_by_user_id' => 1,
            'submitted_by_name' => 'Staff',
            'meeting_date' => '2026-06-05',
            'meeting_level' => 'state',
            'meeting_mode' => 'physical',
            'department_name' => 'Agriculture',
            'official_name' => 'Official',
            'official_designation' => 'Director',
            'muy_staff_present' => 'Team',
            'meeting_purpose' => 'coordination',
            'agenda_summary' => 'Agenda',
            'outcome_decision' => 'Outcome',
            'proof_media_json' => [['path' => 'x', 'original_name' => 'a.pdf']],
            'status' => ServiceCase::STATUS_PENDING_APPROVAL,
            'submitted_at' => now(),
        ]);

        LineDepartmentMeeting::query()->create([
            'submitted_by_user_id' => 1,
            'submitted_by_name' => 'Staff',
            'meeting_date' => '2026-06-06',
            'meeting_level' => 'state',
            'meeting_mode' => 'physical',
            'department_name' => 'Horticulture',
            'official_name' => 'Official 2',
            'official_designation' => 'Director',
            'muy_staff_present' => 'Team',
            'meeting_purpose' => 'coordination',
            'agenda_summary' => 'Agenda',
            'outcome_decision' => 'Outcome',
            'proof_media_json' => [['path' => 'y', 'original_name' => 'b.pdf']],
            'status' => ServiceCase::STATUS_APPROVED,
            'submitted_at' => now(),
            'approved_at' => now(),
        ]);

        $count = \App\Support\LineDepartmentMeetingDeliverablesSupport::countMeetings(
            \Carbon\Carbon::parse('2026-06-01'),
            \Carbon\Carbon::parse('2026-06-30'),
        );

        $this->assertSame(1, $count);
    }

    public function test_dedicated_approver_can_access_unified_approval_queue(): void
    {
        $district = $this->createDistrict();
        $submitter = User::factory()->create([
            'role' => 'district_staff',
            'district_id' => $district->id,
            'is_active' => true,
        ]);
        $approver = User::factory()->create([
            'role' => 'state_staff',
            'email' => 'aadil.ishrat@pwc.com',
            'is_active' => true,
        ]);
        $other = User::factory()->create(['role' => 'state_staff', 'is_active' => true]);

        TechnicalTraining::query()->create([
            'submitted_by_user_id' => $submitter->id,
            'submitted_by_name' => $submitter->name,
            'event_date' => '2026-06-01',
            'district_id' => $district->id,
            'district_name' => $district->name,
            'session_name' => 'Queue session',
            'attendance_media_json' => [],
            'selected_incubatee_ids' => [1],
            'selected_incubatees_snapshot' => [['incubatee_id' => 1, 'name' => 'A']],
            'status' => ServiceCase::STATUS_PENDING_APPROVAL,
            'spoc_user_id' => $approver->id,
            'submitted_at' => now(),
        ]);

        $this->actingAs($approver)->get(route('spoc.field-mis-approvals.index'))
            ->assertRedirect(route('spoc.service-cases.index', ['status' => ServiceCase::STATUS_PENDING_APPROVAL]));
        $this->actingAs($approver)->get(route('spoc.service-cases.index'))
            ->assertOk()
            ->assertSee('Queue session');
        $this->actingAs($other)->get(route('spoc.field-mis-approvals.index'))->assertForbidden();
    }

    public function test_dedicated_approver_can_view_field_mis_review_page(): void
    {
        $district = $this->createDistrict();
        $submitter = User::factory()->create([
            'role' => 'district_staff',
            'district_id' => $district->id,
            'is_active' => true,
        ]);
        $approver = User::factory()->create([
            'role' => 'state_staff',
            'email' => 'aadil.ishrat@pwc.com',
            'is_active' => true,
        ]);

        $training = TechnicalTraining::query()->create([
            'submitted_by_user_id' => $submitter->id,
            'submitted_by_name' => $submitter->name,
            'event_date' => '2026-06-01',
            'district_id' => $district->id,
            'district_name' => $district->name,
            'session_name' => 'Review session',
            'session_brief' => 'Brief text',
            'attendance_media_json' => [],
            'selected_incubatee_ids' => [1],
            'selected_incubatees_snapshot' => [['incubatee_id' => 1, 'name' => 'A']],
            'status' => ServiceCase::STATUS_PENDING_APPROVAL,
            'submitted_at' => now(),
        ]);

        $this->actingAs($approver)
            ->get(route('spoc.field-mis-approvals.show', ['technical_training', $training->id]))
            ->assertOk()
            ->assertSee('Review session')
            ->assertSee('Brief text')
            ->assertSee('Approval actions');
    }

    public function test_dedicated_approver_can_view_community_org_outreach_record(): void
    {
        $district = $this->createDistrict('other-district', 'Other District');
        $hub = Hub::query()->first();
        $submitter = User::factory()->create([
            'role' => 'district_staff',
            'district_id' => $district->id,
            'is_active' => true,
        ]);
        $approver = User::factory()->create([
            'role' => 'state_staff',
            'email' => 'aadil.ishrat@pwc.com',
            'is_active' => true,
        ]);

        $visit = CommunityOrganizationOutreachVisit::query()->create([
            'submitted_by_user_id' => $submitter->id,
            'submitted_by_name' => $submitter->name,
            'hub_id' => $hub->id,
            'hub_name' => $hub->name,
            'district_id' => $district->id,
            'district_name' => $district->name,
            'visit_date' => '2026-06-10',
            'organization_name' => 'Test NGO',
            'organization_type' => 'ngo',
            'person_met_name' => 'Contact',
            'poc_name' => 'POC',
            'poc_phone' => '9999999999',
            'purpose' => 'awareness',
            'meeting_mode' => 'physical',
            'remarks' => 'Follow up needed',
            'documents_json' => [],
            'photos_json' => [],
            'status' => ServiceCase::STATUS_PENDING_APPROVAL,
            'submitted_at' => now(),
        ]);

        $this->actingAs($approver)
            ->get(route('spoc.community-org-outreach.show', $visit))
            ->assertOk()
            ->assertSee('Test NGO')
            ->assertSee('Follow up needed');
    }

    private function createDistrict(string $slug = 'test-district', string $name = 'Test District'): District
    {
        $hub = Hub::query()->create(['slug' => 'hub-'.$slug, 'name' => 'Hub', 'sort_order' => 1]);

        return District::query()->create([
            'hub_id' => $hub->id,
            'slug' => $slug,
            'name' => $name,
            'sort_order' => 1,
        ]);
    }
}
