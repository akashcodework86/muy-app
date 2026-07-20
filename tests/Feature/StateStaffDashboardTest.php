<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StateStaffDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_state_staff_dashboard_uses_operational_full_width_content(): void
    {
        $user = User::factory()->create([
            'role' => 'state_staff',
            'is_active' => true,
            'name' => 'State SPOC',
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Welcome back, State SPOC')
            ->assertSee('Your approval queue')
            ->assertSee('Assigned districts')
            ->assertSee('Quick actions')
            ->assertSee('Open approval queue')
            ->assertDontSee('Wait for the state admin to assign you one or more districts');
    }
}
