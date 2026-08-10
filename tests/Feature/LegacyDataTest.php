<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class LegacyDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_state_admin_can_open_legacy_data_page(): void
    {
        Cache::forever('legacy-data-explorer:version', 1);
        Cache::store('file')->put('legacy-data-explorer:dataset:v1', collect([
            [
                'phase' => 'Phase 1', 'financial_year' => 'FY 2021-22', 'application_no' => 'APP-1',
                'applicant' => 'Demo Applicant', 'phone' => '9876543210', 'district' => 'Almora',
                'block' => 'Hawalbagh', 'beneficiary_type' => 'Individual', 'business_category' => 'Homestay',
                'business_stage' => 'Early', 'gender' => 'Female', 'education' => 'Graduate',
                'onboarding_date' => '10 Apr 2021', 'onboarding_date_sort' => 1617993000,
                'service_items' => [['label' => 'Udyam Registration', 'detail' => '', 'status' => 'Completed', 'date' => 'Date NA']],
                'services_count' => 1, 'services' => 'Udyam Registration', 'cfa_id' => 1,
            ],
        ]), now()->addMinute());

        $admin = User::factory()->create(['role' => 'state_admin', 'is_active' => true]);

        $this->actingAs($admin)
            ->get(route('admin.legacy-data.index'))
            ->assertOk()
            ->assertSee('Onboarding &amp; Services Explorer', false)
            ->assertSee('Demo Applicant')
            ->assertSee('Legacy Data');
    }

    public function test_non_state_admin_cannot_open_legacy_data_page(): void
    {
        $staff = User::factory()->create(['role' => 'district_staff', 'is_active' => true]);

        $this->actingAs($staff)
            ->get('/admin/legacy-data')
            ->assertForbidden();
    }
}
