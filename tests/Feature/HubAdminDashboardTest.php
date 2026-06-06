<?php

namespace Tests\Feature;

use App\Models\District;
use App\Models\FiscalYear;
use App\Models\Hub;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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

    public function test_hub_district_target_chart_includes_monthly_period_tabs(): void
    {
        $hub = Hub::query()->create(['slug' => 'hub-tabs', 'name' => 'Garhwal Region', 'sort_order' => 1]);
        $district = District::query()->create([
            'hub_id' => $hub->id,
            'slug' => 'dehradun-tabs',
            'name' => 'Dehradun',
            'sort_order' => 1,
        ]);

        $fy = FiscalYear::query()->create([
            'code' => 'FY2025-26',
            'name' => 'FY 2025-26',
            'starts_on' => '2025-04-01',
            'ends_on' => '2026-03-31',
            'is_active' => true,
            'is_phase3_default' => true,
        ]);

        DB::table('cfa_submissions')->insert([
            'district_id' => $district->id,
            'fiscal_year_id' => $fy->id,
            'application_no' => 'HUB-TAB-001',
            'applicant_name' => 'Tab Test Applicant',
            'phone' => '9000000099',
            'payload' => json_encode([]),
            'created_at' => '2026-05-10 10:00:00',
            'updated_at' => now(),
        ]);

        $admin = User::factory()->create([
            'role' => 'hub_admin',
            'hub_id' => $hub->id,
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('District CFA vs target')
            ->assertSee('Full FY')
            ->assertSee('data-sad-dt-tab="m1"', false);
    }
}
