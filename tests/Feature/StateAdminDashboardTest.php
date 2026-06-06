<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StateAdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_state_admin_dashboard_loads(): void
    {
        $admin = User::factory()->create(['role' => 'state_admin', 'is_active' => true]);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Welcome');
    }

    public function test_state_admin_cfa_index_uses_revamp_theme(): void
    {
        $admin = User::factory()->create(['role' => 'state_admin', 'is_active' => true]);

        $this->actingAs($admin)
            ->get(route('admin.cfa.index'))
            ->assertOk()
            ->assertSee('admin-app-body--state-theme-revamp', false);
    }

    public function test_state_admin_theme_persists_on_admin_pages(): void
    {
        $admin = User::factory()->create(['role' => 'state_admin', 'is_active' => true]);

        $this->actingAs($admin)
            ->get(route('dashboard', ['theme' => 'legacy']))
            ->assertOk();

        $this->actingAs($admin)
            ->get(route('admin.cfa.index'))
            ->assertOk()
            ->assertSee('admin-app-body--state-theme-legacy', false);
    }
}
