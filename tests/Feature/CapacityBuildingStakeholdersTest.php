<?php

namespace Tests\Feature;

use App\Models\Deliverable;
use App\Models\FiscalYear;
use App\Models\StakeholderCapacityBuildingSession;
use App\Models\User;
use App\Services\Deliverables\ProgramDeliverablesFilter;
use App\Services\Deliverables\ProgramDeliverablesScope;
use App\Services\Deliverables\ProgramDeliverablesAchievementBreakdownService;
use App\Services\MisMonthlyTargetIndicatorBootstrapService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CapacityBuildingStakeholdersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(MisMonthlyTargetIndicatorBootstrapService::class)->ensureDeliverables();
    }

    public function test_aadil_can_open_form_and_store_session(): void
    {
        Storage::fake();

        $aadil = User::factory()->create([
            'role' => 'state_staff',
            'email' => 'aadil.ishrat@pwc.com',
            'is_active' => true,
        ]);

        $this->actingAs($aadil)
            ->get(route('spoc.capacity-building-stakeholders.create'))
            ->assertOk()
            ->assertSee('3.4');

        $this->actingAs($aadil)
            ->post(route('spoc.capacity-building-stakeholders.store'), [
                'session_date' => '2026-05-15',
                'workshop_mode' => 'physical',
                'venue' => 'Dehradun state office',
                'stakeholder_type' => 'reap',
                'session_title' => 'MUY referral process for REAP staff',
                'topics_covered' => 'Onboarding, CFA, reporting',
                'staff_trained_total' => 18,
                'attendance_media' => [
                    UploadedFile::fake()->create('attendance.pdf', 100, 'application/pdf'),
                ],
            ])
            ->assertRedirect(route('spoc.capacity-building-stakeholders.dashboard'))
            ->assertSessionHas('status');

        $this->assertDatabaseCount('stakeholder_capacity_building_sessions', 1);
        $row = StakeholderCapacityBuildingSession::query()->first();
        $this->assertSame('reap', $row->stakeholder_type);
        $this->assertSame(18, (int) $row->staff_trained_total);
    }

    public function test_aadil_can_store_session_with_workshop_photos(): void
    {
        Storage::fake();

        $aadil = User::factory()->create([
            'role' => 'state_staff',
            'email' => 'aadil.ishrat@pwc.com',
            'is_active' => true,
        ]);

        $this->actingAs($aadil)
            ->post(route('spoc.capacity-building-stakeholders.store'), [
                'session_date' => '2026-05-15',
                'workshop_mode' => 'physical',
                'venue' => 'Dehradun state office',
                'stakeholder_type' => 'reap',
                'session_title' => 'Session with photos',
                'staff_trained_total' => 10,
                'attendance_media' => [
                    UploadedFile::fake()->create('attendance.pdf', 100, 'application/pdf'),
                ],
                'workshop_photos' => [
                    UploadedFile::fake()->image('workshop-1.jpg'),
                    UploadedFile::fake()->image('workshop-2.png'),
                ],
            ])
            ->assertRedirect(route('spoc.capacity-building-stakeholders.dashboard'));

        $row = StakeholderCapacityBuildingSession::query()->firstOrFail();
        $this->assertCount(2, (array) $row->workshop_photos_json);
        $this->assertTrue(Storage::exists((string) $row->workshop_photos_json[0]['path']));
        $this->assertTrue(Storage::exists((string) $row->workshop_photos_json[1]['path']));
    }

    public function test_other_stakeholder_type_requires_specify_field_and_department(): void
    {
        Storage::fake();

        $aadil = User::factory()->create([
            'role' => 'state_staff',
            'email' => 'aadil.ishrat@pwc.com',
            'is_active' => true,
        ]);

        $this->actingAs($aadil)
            ->post(route('spoc.capacity-building-stakeholders.store'), [
                'session_date' => '2026-05-16',
                'workshop_mode' => 'virtual',
                'venue' => 'Online',
                'stakeholder_type' => 'other',
                'stakeholder_type_other' => 'NGO partner',
                'department_name' => 'Himalayan NGO',
                'session_title' => 'Partner orientation',
                'staff_trained_total' => 10,
                'attendance_media' => [
                    UploadedFile::fake()->create('sheet.xlsx', 50, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'),
                ],
            ])
            ->assertRedirect(route('spoc.capacity-building-stakeholders.dashboard'));

        $row = StakeholderCapacityBuildingSession::query()->first();
        $this->assertSame('other', $row->stakeholder_type);
        $this->assertSame('NGO partner', $row->stakeholder_type_other);
        $this->assertSame('Himalayan NGO', $row->department_name);
    }

    public function test_non_aadil_state_staff_cannot_submit(): void
    {
        $other = User::factory()->create([
            'role' => 'state_staff',
            'email' => 'other.staff@pwc.com',
            'is_active' => true,
        ]);

        $this->actingAs($other)
            ->get(route('spoc.capacity-building-stakeholders.create'))
            ->assertForbidden();
    }

    public function test_state_admin_can_view_dashboard(): void
    {
        $admin = User::factory()->create(['role' => 'state_admin', 'is_active' => true]);

        $this->actingAs($admin)
            ->get(route('admin.capacity-building-stakeholders.dashboard'))
            ->assertOk()
            ->assertSee('3.4');
    }

    public function test_aadil_can_edit_own_session_without_reuploading_attendance(): void
    {
        Storage::fake();

        $aadil = User::factory()->create([
            'role' => 'state_staff',
            'email' => 'aadil.ishrat@pwc.com',
            'is_active' => true,
        ]);

        $row = StakeholderCapacityBuildingSession::query()->create([
            'submitted_by_user_id' => $aadil->id,
            'submitted_by_name' => $aadil->name,
            'session_date' => '2026-05-10',
            'workshop_mode' => 'physical',
            'venue' => 'Dehradun',
            'stakeholder_type' => 'reap',
            'session_title' => 'Original title',
            'staff_trained_total' => 12,
            'attendance_media_json' => [['path' => 'cbs/attendance.pdf', 'original_name' => 'a.pdf', 'mime' => 'application/pdf', 'type' => 'document']],
        ]);

        $this->actingAs($aadil)
            ->get(route('spoc.capacity-building-stakeholders.edit', $row))
            ->assertOk()
            ->assertSee('Edit session');

        $this->actingAs($aadil)
            ->put(route('spoc.capacity-building-stakeholders.update', $row), [
                'session_date' => '2026-05-11',
                'workshop_mode' => 'virtual',
                'venue' => 'Online',
                'stakeholder_type' => 'reap',
                'session_title' => 'Updated title',
                'staff_trained_total' => 15,
            ])
            ->assertRedirect(route('spoc.capacity-building-stakeholders.dashboard'))
            ->assertSessionHas('status');

        $row->refresh();
        $this->assertSame('Updated title', $row->session_title);
        $this->assertSame(15, (int) $row->staff_trained_total);
        $this->assertSame('virtual', $row->workshop_mode);
        $this->assertCount(1, (array) $row->attendance_media_json);
    }

    public function test_aadil_can_delete_own_session(): void
    {
        Storage::fake();
        Storage::put('cbs/attendance.pdf', 'fake');

        $aadil = User::factory()->create([
            'role' => 'state_staff',
            'email' => 'aadil.ishrat@pwc.com',
            'is_active' => true,
        ]);

        $row = StakeholderCapacityBuildingSession::query()->create([
            'submitted_by_user_id' => $aadil->id,
            'submitted_by_name' => $aadil->name,
            'session_date' => '2026-05-10',
            'workshop_mode' => 'physical',
            'venue' => 'Dehradun',
            'stakeholder_type' => 'reap',
            'session_title' => 'To delete',
            'staff_trained_total' => 5,
            'attendance_media_json' => [['path' => 'cbs/attendance.pdf', 'original_name' => 'a.pdf', 'mime' => 'application/pdf', 'type' => 'document']],
        ]);

        $this->actingAs($aadil)
            ->delete(route('spoc.capacity-building-stakeholders.destroy', $row))
            ->assertRedirect(route('spoc.capacity-building-stakeholders.dashboard'))
            ->assertSessionHas('status');

        $this->assertDatabaseMissing('stakeholder_capacity_building_sessions', ['id' => $row->id]);
        Storage::assertMissing('cbs/attendance.pdf');
    }

    public function test_non_owner_cannot_edit_or_delete_session(): void
    {
        $aadil = User::factory()->create([
            'role' => 'state_staff',
            'email' => 'aadil.ishrat@pwc.com',
            'is_active' => true,
        ]);

        $other = User::factory()->create([
            'role' => 'state_staff',
            'email' => 'other.staff@pwc.com',
            'is_active' => true,
        ]);

        $row = StakeholderCapacityBuildingSession::query()->create([
            'submitted_by_user_id' => $aadil->id,
            'submitted_by_name' => $aadil->name,
            'session_date' => '2026-05-10',
            'workshop_mode' => 'physical',
            'venue' => 'Dehradun',
            'stakeholder_type' => 'reap',
            'session_title' => 'Protected',
            'staff_trained_total' => 5,
            'attendance_media_json' => [['path' => 'cbs/attendance.pdf', 'original_name' => 'a.pdf', 'mime' => 'application/pdf', 'type' => 'document']],
        ]);

        $this->actingAs($other)
            ->get(route('spoc.capacity-building-stakeholders.edit', $row))
            ->assertForbidden();

        $this->actingAs($other)
            ->delete(route('spoc.capacity-building-stakeholders.destroy', $row))
            ->assertForbidden();
    }

    public function test_program_deliverables_counts_submitted_sessions_for_indicator_3_4(): void
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

        $aadil = User::factory()->create([
            'role' => 'state_staff',
            'email' => 'aadil.ishrat@pwc.com',
            'is_active' => true,
        ]);

        StakeholderCapacityBuildingSession::query()->create([
            'submitted_by_user_id' => $aadil->id,
            'submitted_by_name' => $aadil->name,
            'session_date' => '2026-05-10',
            'workshop_mode' => 'physical',
            'venue' => 'Kumaon hub',
            'stakeholder_type' => 'usrlm',
            'session_title' => 'USRLM convergence',
            'staff_trained_total' => 12,
            'attendance_media_json' => [['path' => 'fake/path.pdf', 'original_name' => 'a.pdf', 'mime' => 'application/pdf', 'type' => 'document']],
        ]);

        StakeholderCapacityBuildingSession::query()->create([
            'submitted_by_user_id' => $aadil->id,
            'submitted_by_name' => $aadil->name,
            'session_date' => '2026-06-01',
            'workshop_mode' => 'virtual',
            'venue' => 'Online',
            'stakeholder_type' => 'line_department',
            'department_name' => 'Agriculture',
            'session_title' => 'Line dept orientation',
            'staff_trained_total' => 8,
            'attendance_media_json' => [['path' => 'fake/path2.pdf', 'original_name' => 'b.pdf', 'mime' => 'application/pdf', 'type' => 'document']],
        ]);

        Deliverable::query()->where('code', 'capacity_building_stakeholders')->firstOrFail();

        $filter = new ProgramDeliverablesFilter($fy->id, null, null, null, null, null);
        $scope = ProgramDeliverablesScope::forUser(User::factory()->make(['role' => 'state_admin']));
        $breakdown = app(ProgramDeliverablesAchievementBreakdownService::class)->build($filter, $scope, '3.4');

        $this->assertSame(2, $breakdown['total']);
        $this->assertCount(2, $breakdown['records']);
    }
}
