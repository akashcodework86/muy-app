<?php

namespace Tests\Feature;

use App\Models\CfaSubmission;
use App\Models\Designation;
use App\Models\District;
use App\Models\DistrictServiceSpoc;
use App\Models\FiscalYear;
use App\Models\Hub;
use App\Models\MentorshipRequest;
use App\Models\User;
use App\Services\Deliverables\ProgramDeliverablesFilter;
use App\Services\Deliverables\ProgramDeliverablesScope;
use App\Services\MisMonthlyTargetIndicatorBootstrapService;
use App\Services\ProgramDeliverablesReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MentorshipRequestWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(MisMonthlyTargetIndicatorBootstrapService::class)->ensureDeliverables();
    }

    public function test_im_schedules_and_completes_session_and_unique_counts_in_5_2(): void
    {
        Storage::fake();
        [$im, $district, $cfaA, $cfaB] = $this->seedImAndTwoIncubatees();
        $reqA = $this->makeRequest($cfaA, $im);
        $reqB = $this->makeRequest($cfaB, $im);

        $this->actingAs($im)
            ->post(route('staff.mentorship-requests.schedule.store'), [
                'ids' => [$reqA->id, $reqB->id],
                'scheduled_at' => now()->addDay()->format('Y-m-d\TH:i'),
                'meeting_link' => 'https://meet.example.com/session',
            ])
            ->assertRedirect();

        $reqA->refresh();
        $this->assertSame(MentorshipRequest::STATUS_SCHEDULED, $reqA->status);
        $this->assertNotNull($reqA->mentorship_session_id);
        $this->assertSame($reqA->mentorship_session_id, $reqB->fresh()->mentorship_session_id);

        $session = $reqA->session;
        $this->actingAs($im)
            ->post(route('staff.mentorship-requests.complete.store', $session), [
                'proof' => UploadedFile::fake()->image('meeting.jpg', 800, 600),
            ])
            ->assertRedirect();

        $this->assertSame(MentorshipRequest::STATUS_DONE, $reqA->fresh()->status);
        $this->assertSame(MentorshipRequest::STATUS_DONE, $reqB->fresh()->status);

        $fy = FiscalYear::query()->firstOrCreate(
            ['code' => '2026-27'],
            [
                'name' => 'FY 2026-27',
                'starts_on' => '2026-04-01',
                'ends_on' => '2027-03-31',
                'is_active' => true,
            ]
        );
        $admin = User::factory()->create(['role' => 'state_admin', 'is_active' => true]);
        $filter = new ProgramDeliverablesFilter($fy->id, null, null, null, null, null);
        $scope = ProgramDeliverablesScope::forUser($admin);
        $report = app(ProgramDeliverablesReportService::class)->build($filter, $scope);
        $row = collect($report['rows'])->firstWhere('serial', '5.2');
        $this->assertNotNull($row);
        $this->assertSame(2, (int) $row['achievement']);
    }

    public function test_same_incubatee_two_done_requests_count_once_in_5_2(): void
    {
        Storage::fake();
        [$im, $district, $cfaA] = $this->seedImAndOneIncubatee();
        $first = $this->makeRequest($cfaA, $im, 'financial');
        $this->actingAs($im)->post(route('staff.mentorship-requests.schedule.store'), [
            'ids' => [$first->id],
            'scheduled_at' => now()->format('Y-m-d\TH:i'),
        ]);
        $this->actingAs($im)->post(route('staff.mentorship-requests.complete.store', $first->fresh()->session), [
            'proof' => UploadedFile::fake()->image('one.jpg'),
        ]);

        $second = $this->makeRequest($cfaA, $im, 'legal');
        $this->actingAs($im)->post(route('staff.mentorship-requests.schedule.store'), [
            'ids' => [$second->id],
            'scheduled_at' => now()->format('Y-m-d\TH:i'),
        ]);
        $this->actingAs($im)->post(route('staff.mentorship-requests.complete.store', $second->fresh()->session), [
            'proof' => UploadedFile::fake()->image('two.jpg'),
        ]);

        $fy = FiscalYear::query()->firstOrCreate(
            ['code' => '2026-27'],
            [
                'name' => 'FY 2026-27',
                'starts_on' => '2026-04-01',
                'ends_on' => '2027-03-31',
                'is_active' => true,
            ]
        );
        $admin = User::factory()->create(['role' => 'state_admin', 'is_active' => true]);
        $filter = new ProgramDeliverablesFilter($fy->id, null, null, null, null, null);
        $report = app(ProgramDeliverablesReportService::class)->build($filter, ProgramDeliverablesScope::forUser($admin));
        $row = collect($report['rows'])->firstWhere('serial', '5.2');
        $this->assertSame(1, (int) $row['achievement']);
    }

    public function test_non_im_district_staff_can_view_but_not_schedule(): void
    {
        [$im, $district, $cfaA] = $this->seedImAndOneIncubatee();
        $req = $this->makeRequest($cfaA, $im);
        $spoke = Designation::query()->firstOrCreate(
            ['name' => 'MUY Spoke'],
            ['sort_order' => 2]
        );
        $staff = User::factory()->create([
            'role' => 'district_staff',
            'district_id' => $district->id,
            'designation_id' => $spoke->id,
            'is_active' => true,
        ]);

        $this->actingAs($staff)
            ->get(route('staff.mentorship-requests.dashboard'))
            ->assertOk()
            ->assertSee($cfaA->applicant_name);

        $this->actingAs($staff)
            ->post(route('staff.mentorship-requests.schedule.store'), [
                'ids' => [$req->id],
                'scheduled_at' => now()->format('Y-m-d\TH:i'),
            ])
            ->assertForbidden();
    }

    public function test_spoc_sees_only_assigned_district(): void
    {
        [$im, $district, $cfaA] = $this->seedImAndOneIncubatee();
        $this->makeRequest($cfaA, $im);
        $otherDistrict = District::query()->create([
            'hub_id' => $district->hub_id,
            'slug' => 'other-d',
            'name' => 'Other District',
            'sort_order' => 9,
        ]);
        $otherCfa = CfaSubmission::query()->create([
            'district_id' => $otherDistrict->id,
            'applicant_name' => 'Hidden Person',
            'phone' => '9111111111',
            'payload' => [],
        ]);
        $this->makeRequest($otherCfa, $im);

        $spoc = User::factory()->create(['role' => 'state_staff', 'is_active' => true]);
        DistrictServiceSpoc::query()->create([
            'district_id' => $district->id,
            'state_staff_user_id' => $spoc->id,
            'assigned_at' => now(),
        ]);

        $this->actingAs($spoc)
            ->get(route('spoc.mentorship-requests.dashboard'))
            ->assertOk()
            ->assertSee($cfaA->applicant_name)
            ->assertDontSee('Hidden Person');
    }

    public function test_state_admin_dashboard_exports_xlsx_with_current_filters(): void
    {
        [$im, $district, $cfaA] = $this->seedImAndOneIncubatee();
        $this->makeRequest($cfaA, $im);
        $admin = User::factory()->create(['role' => 'state_admin', 'is_active' => true]);

        $this->actingAs($admin)
            ->get(route('admin.mentorship-requests.dashboard'))
            ->assertOk()
            ->assertSee('Export Excel')
            ->assertSee($cfaA->applicant_name);

        $response = $this->actingAs($admin)
            ->get(route('admin.mentorship-requests.export', [
                'status' => MentorshipRequest::STATUS_PENDING,
                'q' => 'Priya',
            ]));

        $response->assertOk();
        $this->assertStringContainsString('.xlsx', (string) $response->headers->get('content-disposition'));
        $this->assertStringContainsString(
            'mentorship-requests-',
            (string) $response->headers->get('content-disposition')
        );
    }

    public function test_incubatee_can_cancel_pending_but_not_done(): void
    {
        Storage::fake();
        [$im, $district, $cfaA] = $this->seedImAndOneIncubatee();
        $incubatee = User::query()->where('cfa_submission_id', $cfaA->id)->first();
        $pending = $this->makeRequest($cfaA, $incubatee);

        $this->actingAs($incubatee)
            ->post(route('incubatee.mentorship-requests.cancel', $pending))
            ->assertRedirect();
        $this->assertSame(MentorshipRequest::STATUS_CANCELLED, $pending->fresh()->status);

        $doneReq = $this->makeRequest($cfaA, $incubatee);
        $this->actingAs($im)->post(route('staff.mentorship-requests.schedule.store'), [
            'ids' => [$doneReq->id],
            'scheduled_at' => now()->format('Y-m-d\TH:i'),
        ]);
        $this->actingAs($im)->post(route('staff.mentorship-requests.complete.store', $doneReq->fresh()->session), [
            'proof' => UploadedFile::fake()->image('done.jpg'),
        ]);

        $this->actingAs($incubatee)
            ->post(route('incubatee.mentorship-requests.cancel', $doneReq->fresh()))
            ->assertSessionHasErrors('cancel');
        $this->assertSame(MentorshipRequest::STATUS_DONE, $doneReq->fresh()->status);
    }

    public function test_batch_rejects_mixed_categories(): void
    {
        [$im, $district, $cfaA] = $this->seedImAndOneIncubatee();
        $cfaB = CfaSubmission::query()->create([
            'district_id' => $district->id,
            'applicant_name' => 'Second',
            'phone' => '9222222222',
            'payload' => [],
        ]);
        User::factory()->create([
            'role' => 'incubatee',
            'phone' => '9222222222',
            'password' => '9222222222',
            'cfa_submission_id' => $cfaB->id,
            'district_id' => $district->id,
            'is_active' => true,
        ]);
        $a = $this->makeRequest($cfaA, $im, 'financial');
        $b = $this->makeRequest($cfaB, $im, 'legal');

        $this->actingAs($im)
            ->post(route('staff.mentorship-requests.schedule.store'), [
                'ids' => [$a->id, $b->id],
                'scheduled_at' => now()->format('Y-m-d\TH:i'),
            ])
            ->assertSessionHasErrors('ids');
    }

    /**
     * @return array{0: User, 1: District, 2: CfaSubmission, 3: CfaSubmission}
     */
    private function seedImAndTwoIncubatees(): array
    {
        [$im, $district, $cfaA] = $this->seedImAndOneIncubatee();
        $cfaB = CfaSubmission::query()->create([
            'district_id' => $district->id,
            'applicant_name' => 'Ravi Sharma',
            'phone' => '9333333333',
            'application_no' => 'APP-B',
            'payload' => [],
        ]);
        User::factory()->create([
            'role' => 'incubatee',
            'name' => 'Ravi Sharma',
            'phone' => '9333333333',
            'password' => '9333333333',
            'cfa_submission_id' => $cfaB->id,
            'district_id' => $district->id,
            'is_active' => true,
        ]);

        return [$im, $district, $cfaA, $cfaB];
    }

    /**
     * @return array{0: User, 1: District, 2: CfaSubmission}
     */
    private function seedImAndOneIncubatee(): array
    {
        $hub = Hub::query()->create(['slug' => 'mr-hub', 'name' => 'Hub', 'sort_order' => 1]);
        $district = District::query()->create([
            'hub_id' => $hub->id,
            'slug' => 'mr-district',
            'name' => 'MR District',
            'sort_order' => 1,
        ]);
        $designation = Designation::query()->firstOrCreate(
            ['name' => 'Incubation Manager'],
            ['sort_order' => 1]
        );
        $im = User::factory()->create([
            'role' => 'district_staff',
            'district_id' => $district->id,
            'hub_id' => $hub->id,
            'designation_id' => $designation->id,
            'is_active' => true,
        ]);
        $cfaA = CfaSubmission::query()->create([
            'district_id' => $district->id,
            'applicant_name' => 'Priya Sharma',
            'phone' => '9444444444',
            'application_no' => 'APP-A',
            'payload' => [],
        ]);
        User::factory()->create([
            'role' => 'incubatee',
            'name' => 'Priya Sharma',
            'phone' => '9444444444',
            'password' => '9444444444',
            'cfa_submission_id' => $cfaA->id,
            'district_id' => $district->id,
            'is_active' => true,
        ]);

        return [$im, $district, $cfaA];
    }

    private function makeRequest(CfaSubmission $cfa, User $requestedBy, string $category = 'financial'): MentorshipRequest
    {
        $user = User::query()->where('cfa_submission_id', $cfa->id)->where('role', 'incubatee')->first() ?: $requestedBy;

        return MentorshipRequest::query()->create([
            'cfa_submission_id' => $cfa->id,
            'requested_by_user_id' => (int) $user->id,
            'category' => $category,
            'comment' => 'Need help',
            'status' => MentorshipRequest::STATUS_PENDING,
        ]);
    }
}
