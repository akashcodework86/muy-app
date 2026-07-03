<?php

namespace Tests\Feature;

use App\Models\Deliverable;
use App\Models\Designation;
use App\Models\District;
use App\Models\FiscalYear;
use App\Models\Hub;
use App\Models\LineDepartmentMeeting;
use App\Models\ServiceCase;
use App\Models\User;
use App\Services\Deliverables\ProgramDeliverablesAchievementBreakdownService;
use App\Services\Deliverables\ProgramDeliverablesFilter;
use App\Services\Deliverables\ProgramDeliverablesScope;
use App\Services\MisMonthlyTargetIndicatorBootstrapService;
use App\Services\AppSettingsService;
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
                'meeting_purpose' => 'convergence',
                'agenda_remark_outcome' => 'Scheme alignment. Follow-up in April.',
                'meeting_media' => [UploadedFile::fake()->create('minutes.pdf', 100, 'application/pdf')],
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
                'meeting_purpose' => 'onboarding_support',
                'agenda_remark_outcome' => 'Support onboarding. Referrals agreed.',
                'meeting_media' => [UploadedFile::fake()->create('proof.pdf', 100, 'application/pdf')],
            ])
            ->assertRedirect(route('staff.line-department-meetings.dashboard'));

        $this->assertDatabaseCount('line_department_meetings', 1);
    }

    public function test_any_district_staff_can_store_spoke_meeting(): void
    {
        Storage::fake();

        $hub = Hub::query()->create(['slug' => 'fc-hub', 'name' => 'FC Hub', 'sort_order' => 3]);
        $district = District::query()->create(['hub_id' => $hub->id, 'slug' => 'fc-district', 'name' => 'FC District', 'sort_order' => 3]);
        $designation = Designation::query()->create(['name' => 'Field Coordinator', 'sort_order' => 2]);
        $fieldCoordinator = User::factory()->create([
            'role' => 'district_staff',
            'district_id' => $district->id,
            'designation_id' => $designation->id,
            'is_active' => true,
        ]);

        $this->actingAs($fieldCoordinator)
            ->post(route('staff.line-department-meetings.store'), [
                'meeting_date' => now()->toDateString(),
                'meeting_level' => 'spoke',
                'hub_id' => $hub->id,
                'district_id' => $district->id,
                'meeting_mode' => 'physical',
                'department_name' => 'Rural Development',
                'official_name' => 'Official Name',
                'official_designation' => 'BDO',
                'meeting_purpose' => 'onboarding_support',
                'agenda_remark_outcome' => 'District coordination. Next steps agreed.',
                'meeting_media' => [UploadedFile::fake()->create('proof.pdf', 100, 'application/pdf')],
            ])
            ->assertRedirect(route('staff.line-department-meetings.dashboard'));

        $this->assertDatabaseCount('line_department_meetings', 1);
    }

    public function test_any_spoc_can_store_state_level_meeting(): void
    {
        Storage::fake();

        $spoc = User::factory()->create([
            'role' => 'state_staff',
            'email' => 'other.spoc@pwc.com',
            'is_active' => true,
        ]);

        $this->actingAs($spoc)
            ->post(route('spoc.line-department-meetings.store'), [
                'meeting_date' => now()->toDateString(),
                'meeting_level' => 'state',
                'meeting_mode' => 'physical',
                'department_name' => 'Tourism',
                'official_name' => 'Rajesh Kumar',
                'official_designation' => 'Director',
                'meeting_purpose' => 'convergence',
                'agenda_remark_outcome' => 'Scheme alignment. Follow-up in April.',
                'meeting_media' => [UploadedFile::fake()->create('minutes.pdf', 100, 'application/pdf')],
            ])
            ->assertRedirect(route('spoc.line-department-meetings.dashboard'));

        $this->assertDatabaseCount('line_department_meetings', 1);
    }

    public function test_hub_level_district_staff_meeting_appears_on_dashboard_and_services_list(): void
    {
        Storage::fake();

        $hub = Hub::query()->create(['slug' => 'hub-list', 'name' => 'Hub List', 'sort_order' => 10]);
        $district = District::query()->create(['hub_id' => $hub->id, 'slug' => 'dist-list', 'name' => 'Dist List', 'sort_order' => 10]);
        $designation = Designation::query()->create(['name' => 'Field Coordinator', 'sort_order' => 3]);
        $staff = User::factory()->create([
            'role' => 'district_staff',
            'district_id' => $district->id,
            'hub_id' => $hub->id,
            'designation_id' => $designation->id,
            'is_active' => true,
        ]);

        $this->actingAs($staff)
            ->post(route('staff.line-department-meetings.store'), [
                'meeting_date' => now()->toDateString(),
                'meeting_level' => 'hub',
                'hub_id' => $hub->id,
                'meeting_mode' => 'physical',
                'department_name' => 'Tourism',
                'official_name' => 'Official Name',
                'official_designation' => 'Director',
                'meeting_purpose' => 'convergence',
                'agenda_remark_outcome' => 'Hub coordination. Follow-up planned.',
                'meeting_media' => [UploadedFile::fake()->create('proof.pdf', 100, 'application/pdf')],
            ])
            ->assertRedirect(route('staff.line-department-meetings.dashboard'));

        $meeting = LineDepartmentMeeting::query()->firstOrFail();
        $this->assertNull($meeting->district_id);
        $this->assertSame(ServiceCase::STATUS_PENDING_APPROVAL, $meeting->status);

        $this->actingAs($staff)
            ->get(route('staff.line-department-meetings.dashboard'))
            ->assertOk()
            ->assertSee('Tourism')
            ->assertSee('Pending approval');

        app(AppSettingsService::class)->setMany(['service_module.enabled' => true]);

        $this->actingAs($staff)
            ->get(route('staff.services.index', ['scope' => 'my']))
            ->assertOk()
            ->assertSee('12.2')
            ->assertSee('Pending approval');
    }

    public function test_hub_admin_can_store_meeting_without_media(): void
    {
        $hub = Hub::query()->create(['slug' => 'no-media-hub', 'name' => 'No Media Hub', 'sort_order' => 11]);
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
                'meeting_purpose' => 'convergence',
                'agenda_remark_outcome' => 'Coordination meeting held.',
            ])
            ->assertRedirect(route('hub.line-department-meetings.dashboard'));

        $meeting = LineDepartmentMeeting::query()->firstOrFail();
        $this->assertSame([], $meeting->proof_media_json);
        $this->assertNull($meeting->photos_json);
        $this->assertFalse($meeting->hasMeetingMedia());
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
            'meeting_purpose' => 'convergence',
            'agenda_remark_outcome' => 'Agenda and outcome',
            'agenda_summary' => 'Agenda',
            'outcome_decision' => 'Outcome',
            'proof_media_json' => [['path' => 'x', 'original_name' => 'a.pdf']],
            'status' => ServiceCase::STATUS_APPROVED,
            'submitted_at' => now(),
            'approved_at' => now(),
        ]);

        Deliverable::query()->where('code', 'line_department_meeting')->firstOrFail();

        $filter = new ProgramDeliverablesFilter($fy->id, null, null, null, null, null);
        $scope = ProgramDeliverablesScope::forUser(User::factory()->make(['role' => 'state_admin']));
        $breakdown = app(ProgramDeliverablesAchievementBreakdownService::class)->build($filter, $scope, '12.2');

        $this->assertSame(1, $breakdown['total']);
    }

    public function test_program_deliverables_scopes_meetings_by_district(): void
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

        $hub = Hub::query()->create(['slug' => 'ldm-del-hub', 'name' => 'LDM Hub', 'sort_order' => 20]);
        $districtA = District::query()->create(['hub_id' => $hub->id, 'slug' => 'ldm-dist-a', 'name' => 'LDM District A', 'sort_order' => 20]);
        $districtB = District::query()->create(['hub_id' => $hub->id, 'slug' => 'ldm-dist-b', 'name' => 'LDM District B', 'sort_order' => 21]);

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

        LineDepartmentMeeting::query()->create([
            'submitted_by_user_id' => $staffA->id,
            'submitted_by_name' => $staffA->name,
            'meeting_date' => '2026-05-12',
            'meeting_level' => 'spoke',
            'hub_id' => $hub->id,
            'hub_name' => $hub->name,
            'district_id' => $districtA->id,
            'district_name' => $districtA->name,
            'meeting_mode' => 'physical',
            'department_name' => 'Tourism',
            'official_name' => 'Official A',
            'official_designation' => 'Director',
            'meeting_purpose' => 'convergence',
            'agenda_remark_outcome' => 'Outcome A',
            'agenda_summary' => '',
            'outcome_decision' => '',
            'proof_media_json' => [['path' => 'x', 'original_name' => 'a.pdf']],
            'status' => ServiceCase::STATUS_APPROVED,
            'submitted_at' => now(),
            'approved_at' => now(),
        ]);

        LineDepartmentMeeting::query()->create([
            'submitted_by_user_id' => $staffB->id,
            'submitted_by_name' => $staffB->name,
            'meeting_date' => '2026-05-13',
            'meeting_level' => 'hub',
            'hub_id' => $hub->id,
            'hub_name' => $hub->name,
            'district_id' => null,
            'district_name' => null,
            'meeting_mode' => 'physical',
            'department_name' => 'Agriculture',
            'official_name' => 'Official B',
            'official_designation' => 'Director',
            'meeting_purpose' => 'convergence',
            'agenda_remark_outcome' => 'Outcome B',
            'agenda_summary' => '',
            'outcome_decision' => '',
            'proof_media_json' => [['path' => 'y', 'original_name' => 'b.pdf']],
            'status' => ServiceCase::STATUS_APPROVED,
            'submitted_at' => now(),
            'approved_at' => now(),
        ]);

        Deliverable::query()->where('code', 'line_department_meeting')->firstOrFail();

        $filter = new ProgramDeliverablesFilter($fy->id, $districtA->id, null, null, null, null);
        $scope = ProgramDeliverablesScope::forUser(User::factory()->make(['role' => 'state_admin']));
        $breakdown = app(ProgramDeliverablesAchievementBreakdownService::class)->build($filter, $scope, '12.2');

        $this->assertSame(2, app(ProgramDeliverablesAchievementBreakdownService::class)->build(
            new ProgramDeliverablesFilter($fy->id, null, null, null, null, null),
            $scope,
            '12.2',
        )['total']);

        $this->assertSame(1, $breakdown['total']);
        $this->assertSame('LDM District A', $breakdown['by_district'][0]['district'] ?? null);
        $this->assertSame(1, $breakdown['by_district'][0]['count'] ?? 0);
    }
}
