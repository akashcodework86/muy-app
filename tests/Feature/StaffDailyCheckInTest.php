<?php

namespace Tests\Feature;

use App\Models\StaffCheckIn;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffDailyCheckInTest extends TestCase
{
    use RefreshDatabase;

    public function test_district_staff_can_mark_daily_check_in_with_location(): void
    {
        $staff = User::factory()->create([
            'role' => 'district_staff',
            'is_active' => true,
        ]);

        $response = $this->actingAs($staff)->post(route('staff-daily-check-in.store'), [
            'latitude' => 30.3165,
            'longitude' => 78.0322,
            'accuracy_m' => 12.5,
        ]);

        $response->assertRedirect(route('staff-daily-check-in.index'));
        $this->assertDatabaseHas('staff_check_ins', [
            'user_id' => $staff->id,
            'latitude' => 30.3165,
            'longitude' => 78.0322,
        ]);
    }

    public function test_cannot_mark_twice_on_same_day(): void
    {
        $staff = User::factory()->create([
            'role' => 'hub_admin',
            'is_active' => true,
        ]);

        StaffCheckIn::query()->create([
            'user_id' => $staff->id,
            'check_in_date' => now()->toDateString(),
            'marked_at' => now(),
            'latitude' => 1,
            'longitude' => 2,
        ]);

        $this->actingAs($staff)->post(route('staff-daily-check-in.store'), [
            'latitude' => 30.1,
            'longitude' => 78.1,
        ])->assertRedirect(route('staff-daily-check-in.index'));

        $this->assertSame(1, StaffCheckIn::query()->where('user_id', $staff->id)->count());
    }

    public function test_state_admin_cannot_access_check_in(): void
    {
        $admin = User::factory()->create([
            'role' => 'state_admin',
            'is_active' => true,
        ]);

        $this->actingAs($admin)->get(route('staff-daily-check-in.index'))->assertForbidden();
    }

    public function test_state_admin_can_export_staff_check_ins(): void
    {
        if (! class_exists(\PhpOffice\PhpSpreadsheet\Spreadsheet::class)) {
            $this->markTestSkipped('PhpSpreadsheet not installed');
        }

        $admin = User::factory()->create([
            'role' => 'state_admin',
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.staff-check-ins.export', ['date' => now()->toDateString()]))
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_state_admin_can_view_staff_check_in_dashboard(): void
    {
        $admin = User::factory()->create([
            'role' => 'state_admin',
            'is_active' => true,
        ]);

        $staff = User::factory()->create([
            'role' => 'district_staff',
            'is_active' => true,
        ]);

        StaffCheckIn::query()->create([
            'user_id' => $staff->id,
            'check_in_date' => now()->toDateString(),
            'marked_at' => now(),
            'latitude' => 30.5,
            'longitude' => 78.0,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.staff-check-ins.index'))
            ->assertOk()
            ->assertSee($staff->name);
    }

    public function test_check_in_page_shows_attendance_history(): void
    {
        $staff = User::factory()->create([
            'role' => 'district_staff',
            'is_active' => true,
        ]);

        StaffCheckIn::query()->create([
            'user_id' => $staff->id,
            'check_in_date' => now()->subDay()->toDateString(),
            'marked_at' => now()->subDay(),
            'latitude' => 30.1,
            'longitude' => 78.1,
        ]);

        $this->actingAs($staff)
            ->get(route('staff-daily-check-in.index'))
            ->assertOk()
            ->assertSee('Attendance history');
    }
}
