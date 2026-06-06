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
}
