<?php

namespace Tests\Feature;

use App\Models\District;
use App\Models\Hub;
use App\Models\TrainingPackage;
use App\Models\TrainingPackageMonthSession;
use App\Models\User;
use App\Services\TrainingPackageMonthSessionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TrainingPackageMonthSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_sync_named_sessions_for_a_district_month(): void
    {
        $district = $this->createDistrict();
        $admin = User::factory()->create([
            'role' => 'state_admin',
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->post(route('admin.training-package-month-plans.store'), [
            'calendar_year' => 2026,
            'calendar_month' => 5,
            'districts' => [
                [
                    'district_id' => $district->id,
                    'sessions' => [
                        ['session_name' => 'Session Alpha'],
                        ['session_name' => 'Session Beta'],
                    ],
                ],
            ],
        ]);

        $response->assertRedirect(route('admin.training-package-month-plans.index', [
            'calendar_year' => 2026,
            'calendar_month' => 5,
        ]));

        $this->assertDatabaseHas('training_package_month_sessions', [
            'district_id' => $district->id,
            'calendar_year' => 2026,
            'calendar_month' => 5,
            'session_name' => 'Session Alpha',
            'sort_order' => 1,
        ]);
        $this->assertDatabaseHas('training_package_month_sessions', [
            'district_id' => $district->id,
            'session_name' => 'Session Beta',
            'sort_order' => 2,
        ]);
    }

    public function test_sync_does_not_delete_filled_slots(): void
    {
        $district = $this->createDistrict();
        $admin = User::factory()->create(['role' => 'state_admin', 'is_active' => true]);
        $service = app(TrainingPackageMonthSessionService::class);

        $service->syncMonthPlan(2026, 5, [[
            'district_id' => $district->id,
            'sessions' => [
                ['session_name' => 'Filled session'],
                ['session_name' => 'Open session'],
            ],
        ]], (int) $admin->id);

        $filledSlot = TrainingPackageMonthSession::query()->where('session_name', 'Filled session')->firstOrFail();
        $openSlot = TrainingPackageMonthSession::query()->where('session_name', 'Open session')->firstOrFail();

        $staff = User::factory()->create([
            'role' => 'district_staff',
            'district_id' => $district->id,
            'is_active' => true,
        ]);

        TrainingPackage::query()->create([
            'submitted_by_user_id' => $staff->id,
            'submitted_by_name' => 'Staff One',
            'event_date' => '2026-05-10',
            'district_id' => $district->id,
            'district_name' => $district->name,
            'month_session_id' => $filledSlot->id,
            'training_package' => 't1',
            'training_packages' => ['t1'],
            'attendance_file_path' => null,
            'attendance_file_name' => null,
            'attendance_file_mime' => null,
            'attendance_file_size_bytes' => null,
            'attendance_media_json' => [],
            'selected_incubatee_ids' => [1],
            'selected_incubatees_snapshot' => [['incubatee_id' => 1, 'name' => 'A']],
        ]);

        $service->syncMonthPlan(2026, 5, [[
            'district_id' => $district->id,
            'sessions' => [
                ['id' => $filledSlot->id, 'session_name' => 'Filled session'],
                ['id' => $openSlot->id, 'session_name' => 'Renamed open session'],
            ],
        ]], (int) $admin->id);

        $this->assertDatabaseHas('training_package_month_sessions', [
            'id' => $filledSlot->id,
            'session_name' => 'Filled session',
        ]);
        $this->assertDatabaseHas('training_package_month_sessions', [
            'id' => $openSlot->id,
            'session_name' => 'Renamed open session',
        ]);
    }

    public function test_open_slot_lookup_rejects_filled_slots(): void
    {
        $district = $this->createDistrict();
        $staff = User::factory()->create([
            'role' => 'district_staff',
            'district_id' => $district->id,
            'is_active' => true,
        ]);

        $slot = TrainingPackageMonthSession::query()->create([
            'district_id' => $district->id,
            'calendar_year' => 2026,
            'calendar_month' => 5,
            'sort_order' => 1,
            'session_name' => 'Taken session',
        ]);

        TrainingPackage::query()->create([
            'submitted_by_user_id' => $staff->id,
            'submitted_by_name' => 'Staff One',
            'event_date' => '2026-05-10',
            'district_id' => $district->id,
            'district_name' => $district->name,
            'month_session_id' => $slot->id,
            'training_package' => 't1',
            'training_packages' => ['t1'],
            'attendance_file_path' => null,
            'attendance_file_name' => null,
            'attendance_file_mime' => null,
            'attendance_file_size_bytes' => null,
            'attendance_media_json' => [],
            'selected_incubatee_ids' => [1],
            'selected_incubatees_snapshot' => [['incubatee_id' => 1, 'name' => 'A']],
        ]);

        $service = app(TrainingPackageMonthSessionService::class);
        $this->assertNull($service->findOpenSlotForDistrict((int) $slot->id, (int) $district->id));
    }

    public function test_session_date_must_match_planned_month(): void
    {
        $district = $this->createDistrict();
        $slot = TrainingPackageMonthSession::query()->create([
            'district_id' => $district->id,
            'calendar_year' => 2026,
            'calendar_month' => 5,
            'sort_order' => 1,
            'session_name' => 'May session',
        ]);

        $service = app(TrainingPackageMonthSessionService::class);

        $this->expectException(ValidationException::class);
        $service->assertSessionDateMatchesSlot($slot, '2026-06-01');
    }

    public function test_month_session_id_is_unique_per_submission(): void
    {
        $district = $this->createDistrict();
        $staffOne = User::factory()->create([
            'role' => 'district_staff',
            'district_id' => $district->id,
            'is_active' => true,
        ]);
        $staffTwo = User::factory()->create([
            'role' => 'district_staff',
            'district_id' => $district->id,
            'is_active' => true,
        ]);

        $slot = TrainingPackageMonthSession::query()->create([
            'district_id' => $district->id,
            'calendar_year' => 2026,
            'calendar_month' => 5,
            'sort_order' => 1,
            'session_name' => 'Shared slot',
        ]);

        TrainingPackage::query()->create([
            'submitted_by_user_id' => $staffOne->id,
            'submitted_by_name' => 'Staff One',
            'event_date' => '2026-05-10',
            'district_id' => $district->id,
            'district_name' => $district->name,
            'month_session_id' => $slot->id,
            'training_package' => 't1',
            'training_packages' => ['t1'],
            'attendance_file_path' => null,
            'attendance_file_name' => null,
            'attendance_file_mime' => null,
            'attendance_file_size_bytes' => null,
            'attendance_media_json' => [],
            'selected_incubatee_ids' => [1],
            'selected_incubatees_snapshot' => [['incubatee_id' => 1, 'name' => 'A']],
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        TrainingPackage::query()->create([
            'submitted_by_user_id' => $staffTwo->id,
            'submitted_by_name' => 'Staff Two',
            'event_date' => '2026-05-11',
            'district_id' => $district->id,
            'district_name' => $district->name,
            'month_session_id' => $slot->id,
            'training_package' => 't2',
            'training_packages' => ['t2'],
            'attendance_file_path' => null,
            'attendance_file_name' => null,
            'attendance_file_mime' => null,
            'attendance_file_size_bytes' => null,
            'attendance_media_json' => [],
            'selected_incubatee_ids' => [2],
            'selected_incubatees_snapshot' => [['incubatee_id' => 2, 'name' => 'B']],
        ]);
    }

    public function test_district_month_summary_splits_planned_and_extra_counts(): void
    {
        $district = $this->createDistrict();
        $staff = User::factory()->create([
            'role' => 'district_staff',
            'district_id' => $district->id,
            'is_active' => true,
        ]);
        $service = app(TrainingPackageMonthSessionService::class);

        $service->syncMonthPlan(2026, 5, [[
            'district_id' => $district->id,
            'sessions' => [
                ['session_name' => 'Planned session'],
            ],
        ]], (int) $staff->id);

        $plannedSlot = TrainingPackageMonthSession::query()->where('session_name', 'Planned session')->firstOrFail();
        $extraSlot = $service->createExtraSlotForDistrictMonth(
            (int) $district->id,
            2026,
            5,
            'Community outreach',
            (int) $staff->id,
            '2026-05-12',
        );

        TrainingPackage::query()->create([
            'submitted_by_user_id' => $staff->id,
            'submitted_by_name' => 'Staff One',
            'event_date' => '2026-05-12',
            'district_id' => $district->id,
            'district_name' => $district->name,
            'month_session_id' => $extraSlot->id,
            'training_package' => 't1',
            'training_packages' => ['t1'],
            'attendance_file_path' => null,
            'attendance_file_name' => null,
            'attendance_file_mime' => null,
            'attendance_file_size_bytes' => null,
            'attendance_media_json' => [],
            'selected_incubatee_ids' => [1],
            'selected_incubatees_snapshot' => [['incubatee_id' => 1, 'name' => 'A']],
        ]);

        $summary = $service->districtMonthSummary((int) $district->id, 2026, 5);

        $this->assertSame(1, $summary['required']);
        $this->assertSame(0, $summary['filled']);
        $this->assertSame(1, $summary['remaining']);
        $this->assertSame(1, $summary['extra_filled']);
        $this->assertTrue((bool) $plannedSlot->fresh()->is_extra === false);
        $this->assertTrue((bool) $extraSlot->fresh()->is_extra);
    }

    public function test_sync_month_plan_preserves_extra_slots(): void
    {
        $district = $this->createDistrict();
        $admin = User::factory()->create(['role' => 'state_admin', 'is_active' => true]);
        $staff = User::factory()->create([
            'role' => 'district_staff',
            'district_id' => $district->id,
            'is_active' => true,
        ]);
        $service = app(TrainingPackageMonthSessionService::class);

        $extraSlot = $service->createExtraSlotForDistrictMonth(
            (int) $district->id,
            2026,
            5,
            'Extra retained',
            (int) $staff->id,
            '2026-05-15',
        );

        $service->syncMonthPlan(2026, 5, [[
            'district_id' => $district->id,
            'sessions' => [
                ['session_name' => 'New planned session'],
            ],
        ]], (int) $admin->id);

        $this->assertDatabaseHas('training_package_month_sessions', [
            'id' => $extraSlot->id,
            'session_name' => 'Extra retained',
            'is_extra' => true,
        ]);
    }

    public function test_find_open_slot_rejects_extra_slots(): void
    {
        $district = $this->createDistrict();
        $staff = User::factory()->create([
            'role' => 'district_staff',
            'district_id' => $district->id,
            'is_active' => true,
        ]);
        $service = app(TrainingPackageMonthSessionService::class);

        $extraSlot = $service->createExtraSlotForDistrictMonth(
            (int) $district->id,
            2026,
            5,
            'Extra only',
            (int) $staff->id,
            '2026-05-15',
        );

        $this->assertNull($service->findOpenSlotForDistrict((int) $extraSlot->id, (int) $district->id));
    }

    public function test_extra_session_date_must_match_selected_month(): void
    {
        $district = $this->createDistrict();
        $staff = User::factory()->create([
            'role' => 'district_staff',
            'district_id' => $district->id,
            'is_active' => true,
        ]);
        $service = app(TrainingPackageMonthSessionService::class);

        $this->expectException(ValidationException::class);

        $service->createExtraSlotForDistrictMonth(
            (int) $district->id,
            2026,
            5,
            'Wrong month extra',
            (int) $staff->id,
            '2026-06-01',
        );
    }

    public function test_staff_can_submit_extra_session(): void
    {
        $district = $this->createDistrict();
        $staff = User::factory()->create([
            'role' => 'district_staff',
            'district_id' => $district->id,
            'is_active' => true,
        ]);
        $incubateeId = $this->seedOnboardedIncubatee($district);

        $response = $this->actingAs($staff)->post(route('staff.training-packages.store'), [
            'session_mode' => 'extra',
            'plan_year' => 2026,
            'plan_month' => 5,
            'extra_session_name' => 'Field visit',
            'session_date' => '2026-05-18',
            'training_packages' => ['t1'],
            'selected_incubatees' => [$incubateeId],
        ]);

        $response->assertRedirect(route('staff.training-packages.dashboard'));

        $this->assertDatabaseHas('training_package_month_sessions', [
            'district_id' => $district->id,
            'calendar_year' => 2026,
            'calendar_month' => 5,
            'session_name' => 'Field visit',
            'is_extra' => true,
        ]);
    }

    public function test_planned_slot_flow_still_requires_month_session_id(): void
    {
        $district = $this->createDistrict();
        $staff = User::factory()->create([
            'role' => 'district_staff',
            'district_id' => $district->id,
            'is_active' => true,
        ]);
        $incubateeId = $this->seedOnboardedIncubatee($district);

        $response = $this->actingAs($staff)->post(route('staff.training-packages.store'), [
            'session_mode' => 'planned',
            'plan_year' => 2026,
            'plan_month' => 5,
            'session_date' => '2026-05-18',
            'training_packages' => ['t1'],
            'selected_incubatees' => [$incubateeId],
        ]);

        $response->assertSessionHasErrors('month_session_id');
    }

    private function seedOnboardedIncubatee(District $district): int
    {
        $cfaId = (int) DB::table('cfa_submissions')->insertGetId([
            'district_id' => $district->id,
            'applicant_name' => 'Test Applicant',
            'phone' => '9999999999',
            'payload' => json_encode(['gender' => 'M', 'village' => 'Village', 'block' => 'Block']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $batchId = (int) DB::table('onboarding_batches')->insertGetId([
            'hub_id' => $district->hub_id,
            'district_id' => $district->id,
            'name' => 'Batch 1',
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

    private function createDistrict(): District
    {
        $hub = Hub::query()->create([
            'slug' => 'test-hub',
            'name' => 'Test Hub',
            'sort_order' => 1,
        ]);

        return District::query()->create([
            'hub_id' => $hub->id,
            'slug' => 'test-district',
            'name' => 'Test District',
            'sort_order' => 1,
        ]);
    }
}
