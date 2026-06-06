<?php

namespace Tests\Feature;

use App\Models\District;
use App\Models\Hub;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HubAdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_hub_admin_dashboard_loads(): void
    {
        $hub = Hub::query()->create(['slug' => 'hub-dash', 'name' => 'Kumaon Region', 'sort_order' => 1]);
        District::query()->create([
            'hub_id' => $hub->id,
            'slug' => 'pauri-dash',
            'name' => 'Pauri Garhwal',
            'sort_order' => 1,
        ]);

        $admin = User::factory()->create([
            'role' => 'hub_admin',
            'hub_id' => $hub->id,
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Welcome');
    }
}
