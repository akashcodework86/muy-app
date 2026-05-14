<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\AppSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffPhase3AttendanceNavTest extends TestCase
{
    use RefreshDatabase;

    public function test_district_staff_is_redirected_when_technical_training_nav_hidden(): void
    {
        $staff = User::factory()->create([
            'role' => 'district_staff',
            'district_id' => null,
            'is_active' => true,
        ]);

        app(AppSettingsService::class)->setMany([
            'staff_nav.technical_training.visible' => false,
        ]);

        $this->actingAs($staff)->get(route('staff.technical-trainings.dashboard'))
            ->assertRedirect(route('dashboard'));
    }

    public function test_state_admin_can_update_staff_nav_settings(): void
    {
        $admin = User::factory()->create(['role' => 'state_admin', 'is_active' => true]);

        $this->actingAs($admin)->put(route('admin.staff-phase3-attendance-nav.update'), [
            'training_package' => '0',
            'technical_training' => '1',
            'eap_edp_session' => '0',
            'district_workshop' => '1',
        ])->assertRedirect(route('admin.staff-phase3-attendance-nav.edit'));

        $s = app(AppSettingsService::class);
        $this->assertFalse($s->isEnabled('staff_nav.training_package.visible'));
        $this->assertTrue($s->isEnabled('staff_nav.technical_training.visible'));
        $this->assertFalse($s->isEnabled('staff_nav.eap_edp_session.visible'));
        $this->assertTrue($s->isEnabled('staff_nav.district_workshop.visible'));
    }
}
