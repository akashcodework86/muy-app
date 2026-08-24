<?php

namespace Tests\Feature;

use App\Models\District;
use App\Models\Hub;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_district_staff_dashboard_loads_with_theme_and_insights(): void
    {
        $hub = Hub::query()->create(['slug' => 'hub-staff', 'name' => 'Garhwal Region', 'sort_order' => 1]);
        $district = District::query()->create([
            'hub_id' => $hub->id,
            'slug' => 'pauri-staff',
            'name' => 'Pauri Garhwal',
            'sort_order' => 1,
        ]);

        $staff = User::factory()->create([
            'role' => 'district_staff',
            'hub_id' => $hub->id,
            'district_id' => $district->id,
            'is_active' => true,
        ]);

        $this->actingAs($staff)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Welcome')
            ->assertSee('Insights')
            ->assertSee('District Staff Cockpit');
    }

    public function test_district_staff_cfa_dropdown_includes_year_data_links_for_assigned_district(): void
    {
        $hub = Hub::query()->create(['slug' => 'hub-cfa-nav', 'name' => 'Garhwal Region', 'sort_order' => 1]);
        $district = District::query()->create([
            'hub_id' => $hub->id,
            'slug' => 'pauri-cfa-nav',
            'name' => 'Pauri Garhwal',
            'sort_order' => 1,
        ]);

        $staff = User::factory()->create([
            'role' => 'district_staff',
            'hub_id' => $hub->id,
            'district_id' => $district->id,
            'is_active' => true,
        ]);

        $this->actingAs($staff)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('CFA (FY 2024-25 Data)', false)
            ->assertSee('CFA (FY 2025-26 Data)', false)
            ->assertSee(route('staff.phase1-data'), false)
            ->assertSee(route('staff.phase2-data'), false);
    }

    public function test_district_staff_without_district_does_not_see_year_cfa_links(): void
    {
        $staff = User::factory()->create([
            'role' => 'district_staff',
            'hub_id' => null,
            'district_id' => null,
            'is_active' => true,
        ]);

        $this->actingAs($staff)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('CFA (FY 2024-25 Data)', false)
            ->assertDontSee('CFA (FY 2025-26 Data)', false)
            ->assertDontSee(route('staff.phase1-data'), false)
            ->assertDontSee(route('staff.phase2-data'), false);
    }
}
