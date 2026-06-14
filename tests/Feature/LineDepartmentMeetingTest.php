<?php

namespace Tests\Feature;

use App\Models\Deliverable;
use App\Models\Designation;
use App\Models\District;
use App\Models\FiscalYear;
use App\Models\Hub;
use App\Models\LineDepartmentMeeting;
use App\Models\User;
use App\Services\Deliverables\ProgramDeliverablesAchievementBreakdownService;
use App\Services\Deliverables\ProgramDeliverablesFilter;
use App\Services\Deliverables\ProgramDeliverablesScope;
use App\Services\MisMonthlyTargetIndicatorBootstrapService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LineDepartmentMeetingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(MisMonthlyTargetIndicatorBootstrapService::class)->ensureDeliverables();
    }

    public function test_hub_admin_can_store_meeting(): void
    {
        Storage::fake();

        $hub = Hub::query()->create(['slug' => 'test-hub', 'name' => 'Test Hub', 'sort_order' => 1]);
        $district = District::query()->create(['hub_id' => $hub->id, 'slug' => 'test-district', 'name' => 'Test District', 'sort_order' => 1]);
        $hubAdmin = User::factory()->create(['role' => 'hub_admin', 'hub_id' => $hub->id, 'is_active' => true]);

        $this->actingAs($hubAdmin)
            ->post(route('hub.line-department-meetings.store'), [
                'meeting_date' => now()->toDateString(),
                'meeting_level' => 'hub',
                'hub_id' => $hub->id,
                'meeting_mode' => 'physical',
                'department_name' => 'Tourism',
                'official_name' => 'Rajesh Kumar',
                'official_designation' => 'Director',
                'muy_staff_present' => 'A, B',
                'meeting_purpose' => 'convergence',
                'agenda_summary' => 'Scheme alignment',
                'outcome_decision' => 'Follow-up in April',
                'proof_media' => [UploadedFile::fake()->create('minutes.pdf', 100, 'application/pdf')],
            ])
            ->assertRedirect(route('hub.line-department-meetings.dashboard'));

        $this->assertDatabaseCount('line_department_meetings', 1);
        $this->assertSame((int) $hub->id, (int) LineDepartmentMeeting::query()->value('hub_id'));
        $this->assertNull(LineDepartmentMeeting::query()->value('district_id'));
    }

    public function test_incubation_manager_can_store_spoke_meeting(): void
    {
        Storage::fake();

        $hub = Hub::query()->create(['slug' => 'im-hub', 'name' => 'IM Hub', 'sort_order' => 2]);
        $district = District::query()->create(['hub_id' => $hub->id, 'slug' => 'im-district', 'name' => 'IM District', 'sort_order' => 2]);
        $designation = Designation::query()->create(['name' => 'Incubation Manager', 'sort_order' => 1]);
        $im = User::factory()->create([
            'role' => 'district_staff',
            'district_id' => $district->id,
            'designation_id' => $designation->id,
            'is_active' => true,
        ]);

        $this->actingAs($im)
            ->post(route('staff.line-department-meetings.store'), [
                'meeting_date' => now()->toDateString(),
                'meeting_level' => 'spoke',
                'hub_id' => $hub->id,
                'district_id' => $district->id,
                'meeting_mode' => 'physical',
                'department_name' => 'Agriculture',
                'official_name' => 'Official Name',
                'official_designation' => 'DDO',
                'muy_staff_present' => 'IM only',
                'meeting_purpose' => 'onboarding_support',
                'agenda_summary' => 'Support onboarding',
                'outcome_decision' => 'Referrals agreed',
                'proof_media' => [UploadedFile::fake()->create('proof.pdf', 100, 'application/pdf')],
            ])
            ->assertRedirect(route('staff.line-department-meetings.dashboard'));

        $this->assertDatabaseCount('line_department_meetings', 1);
    }

    public function test_program_deliverables_counts_meetings_for_indicator_12_2(): void
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

        LineDepartmentMeeting::query()->create([
            'submitted_by_user_id' => 1,
            'submitted_by_name' => 'User',
            'meeting_date' => '2026-05-12',
            'meeting_level' => 'state',
            'meeting_mode' => 'physical',
            'department_name' => 'Tourism',
            'official_name' => 'Official',
            'official_designation' => 'Director',
            'muy_staff_present' => 'Staff',
            'meeting_purpose' => 'convergence',
            'agenda_summary' => 'Agenda',
            'outcome_decision' => 'Outcome',
            'proof_media_json' => [['path' => 'x', 'original_name' => 'a.pdf']],
        ]);

        Deliverable::query()->where('code', 'line_department_meeting')->firstOrFail();

        $filter = new ProgramDeliverablesFilter($fy->id, null, null, null, null, null);
        $scope = ProgramDeliverablesScope::forUser(User::factory()->make(['role' => 'state_admin']));
        $breakdown = app(ProgramDeliverablesAchievementBreakdownService::class)->build($filter, $scope, '12.2');

        $this->assertSame(1, $breakdown['total']);
    }
}
