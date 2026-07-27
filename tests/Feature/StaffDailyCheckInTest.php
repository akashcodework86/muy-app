<?php

namespace Tests\Feature;

use App\Models\Designation;
use App\Models\StaffCheckIn;
use App\Models\User;
use App\Support\StaffDailyCheckInAccess;
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
            ->assertSee('Attendance history')
            ->assertSee('Absent reason');
    }

    public function test_check_in_history_shows_absent_reason_and_add_button(): void
    {
        $staff = User::factory()->create([
            'role' => 'district_staff',
            'is_active' => true,
        ]);

        $reasonDate = now()->copy()->subDays(2);
        while ($reasonDate->isSunday()) {
            $reasonDate->subDay();
        }

        \App\Models\StaffAbsenceReason::query()->create([
            'user_id' => $staff->id,
            'absence_date' => $reasonDate->toDateString(),
            'reason' => 'Medical leave',
        ]);

        $response = $this->actingAs($staff)
            ->get(route('staff-daily-check-in.index'));

        $response->assertOk()
            ->assertSee('Medical leave');

        if (! now()->isSunday()) {
            $response->assertSee('Add reason');
        }
    }

    public function test_cdo_staff_are_excluded_from_admin_attendance_dashboard(): void
    {
        $admin = User::factory()->create([
            'role' => 'state_admin',
            'is_active' => true,
        ]);

        $cdoDesignation = Designation::query()->firstOrCreate(
            ['name' => 'CDO'],
            ['sort_order' => 1],
        );
        $cdo = User::factory()->create([
            'role' => 'district_staff',
            'is_active' => true,
            'designation_id' => $cdoDesignation->id,
            'name' => 'CDO Person Unique',
        ]);

        StaffCheckIn::query()->create([
            'user_id' => $cdo->id,
            'check_in_date' => now()->toDateString(),
            'marked_at' => now(),
            'latitude' => 30.5,
            'longitude' => 78.0,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.staff-check-ins.index'))
            ->assertOk()
            ->assertDontSee('CDO Person Unique');
    }

    public function test_cdo_staff_are_not_required_to_check_in(): void
    {
        $cdoDesignation = Designation::query()->firstOrCreate(
            ['name' => 'CDO'],
            ['sort_order' => 1],
        );
        $cdo = User::factory()->create([
            'role' => 'district_staff',
            'is_active' => true,
            'designation_id' => $cdoDesignation->id,
        ]);

        $this->assertFalse(StaffDailyCheckInAccess::isRequired($cdo));
    }

    public function test_state_staff_spoc_are_not_required_to_check_in(): void
    {
        $spoc = User::factory()->create([
            'role' => 'state_staff',
            'is_active' => true,
        ]);

        $this->assertFalse(StaffDailyCheckInAccess::isRequired($spoc));
    }

    public function test_state_admin_can_export_monthly_attendance_excel(): void
    {
        if (! class_exists(\PhpOffice\PhpSpreadsheet\Spreadsheet::class)) {
            $this->markTestSkipped('PhpSpreadsheet not installed');
        }

        $admin = User::factory()->create([
            'role' => 'state_admin',
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.staff-check-ins.monthly-export.excel', ['month' => now()->format('Y-m')]))
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_state_admin_can_export_monthly_attendance_pdf(): void
    {
        if (! class_exists(\Dompdf\Dompdf::class)) {
            $this->markTestSkipped('Dompdf not installed');
        }

        $admin = User::factory()->create([
            'role' => 'state_admin',
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.staff-check-ins.monthly-export.pdf', ['month' => now()->format('Y-m')]))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_monthly_sheet_view_is_shown_by_default(): void
    {
        $admin = User::factory()->create([
            'role' => 'state_admin',
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.staff-check-ins.index'))
            ->assertOk()
            ->assertSee('Monthly sheet')
            ->assertSee('Export Excel')
            ->assertSee('Export PDF')
            ->assertSee('Absent (click for reason)');
    }

    public function test_staff_can_submit_absence_reason_for_today(): void
    {
        $staff = User::factory()->create([
            'role' => 'district_staff',
            'is_active' => true,
        ]);

        $this->actingAs($staff)->post(route('staff-daily-check-in.absence-reason.store'), [
            'absence_date' => now()->toDateString(),
            'reason' => 'Medical appointment',
            'month' => now()->format('Y-m'),
        ])->assertRedirect(route('staff-daily-check-in.index', ['month' => now()->format('Y-m')]));

        $this->assertDatabaseHas('staff_absence_reasons', [
            'user_id' => $staff->id,
            'reason' => 'Medical appointment',
        ]);
    }

    public function test_state_admin_daily_view_shows_absent_reason_column(): void
    {
        $admin = User::factory()->create([
            'role' => 'state_admin',
            'is_active' => true,
        ]);

        $staff = User::factory()->create([
            'role' => 'district_staff',
            'is_active' => true,
            'name' => 'Absent Staff Person',
        ]);

        \App\Models\StaffAbsenceReason::query()->create([
            'user_id' => $staff->id,
            'absence_date' => now()->toDateString(),
            'reason' => 'On official duty',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.staff-check-ins.index', ['view' => 'daily', 'date' => now()->toDateString()]))
            ->assertOk()
            ->assertSee('Absent reason')
            ->assertSee('On official duty');
    }

    public function test_staff_attendance_page_shows_monthly_grid(): void
    {
        $staff = User::factory()->create([
            'role' => 'district_staff',
            'is_active' => true,
        ]);

        $this->actingAs($staff)
            ->get(route('staff-daily-check-in.index'))
            ->assertOk()
            ->assertSee('Monthly sheet')
            ->assertSee('Attendance calendar');
    }
}
