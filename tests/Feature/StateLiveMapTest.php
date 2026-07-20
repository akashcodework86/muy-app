<?php

namespace Tests\Feature;

use App\Models\District;
use App\Models\FiscalYear;
use App\Models\Hub;
use App\Models\StaffCheckIn;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StateLiveMapTest extends TestCase
{
    use RefreshDatabase;

    public function test_state_admin_can_open_live_map_page(): void
    {
        $admin = User::factory()->create(['role' => 'state_admin', 'is_active' => true]);

        $this->actingAs($admin)
            ->get(route('admin.live-map.index'))
            ->assertOk()
            ->assertSee('Field Activity Map')
            ->assertSee('Attendance')
            ->assertSee('CFA activity')
            ->assertSee('Approved services')
            ->assertSee('District attendance')
            ->assertSee('id="slm-map"', false);
    }

    public function test_live_map_data_returns_district_metrics_and_staff_pins(): void
    {
        $hub = Hub::query()->create(['slug' => 'garhwal-map', 'name' => 'Garhwal Region', 'sort_order' => 1]);
        $district = District::query()->create([
            'hub_id' => $hub->id,
            'slug' => 'dehradun-map',
            'name' => 'Dehradun',
            'sort_order' => 1,
        ]);
        $fy = FiscalYear::query()->create([
            'code' => 'FY2025-26',
            'name' => 'FY 2025-26',
            'starts_on' => '2025-04-01',
            'ends_on' => '2026-03-31',
            'is_active' => true,
        ]);

        $today = now()->timezone(config('app.timezone'))->toDateString();

        DB::table('cfa_submissions')->insert([
            'district_id' => $district->id,
            'fiscal_year_id' => $fy->id,
            'application_no' => 'MAP-CFA-1',
            'applicant_name' => 'Map Applicant',
            'phone' => '9000000101',
            'payload' => json_encode([]),
            'created_at' => $today.' 12:00:00',
            'updated_at' => now(),
        ]);

        $staff = User::factory()->create([
            'role' => 'district_staff',
            'hub_id' => $hub->id,
            'district_id' => $district->id,
            'is_active' => true,
        ]);

        StaffCheckIn::query()->create([
            'user_id' => $staff->id,
            'check_in_date' => $today,
            'marked_at' => now(),
            'latitude' => 30.3165,
            'longitude' => 78.0322,
            'accuracy_m' => 15,
        ]);

        $admin = User::factory()->create(['role' => 'state_admin', 'is_active' => true]);

        $this->actingAs($admin)
            ->getJson(route('admin.live-map.data', ['date' => $today]))
            ->assertOk()
            ->assertJsonPath('summary.staff_on_map', 1)
            ->assertJsonFragment(['name' => 'Dehradun', 'cfa_fy' => 1, 'cfa_today' => 1])
            ->assertJsonFragment(['name' => $staff->name, 'lat' => 30.3165]);
    }

    public function test_district_staff_cannot_access_live_map(): void
    {
        $staff = User::factory()->create(['role' => 'district_staff', 'is_active' => true]);

        $this->actingAs($staff)
            ->get(route('admin.live-map.index'))
            ->assertForbidden();
    }
}
