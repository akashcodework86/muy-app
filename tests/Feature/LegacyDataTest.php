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
        Cache::store('file')->put('legacy-data-explorer:dataset:s2:v1', collect([
            [
                'phase' => 'Phase 1', 'financial_year' => 'FY 2021-22', 'application_no' => 'APP-1',
                'applicant' => 'Demo Applicant', 'phone' => '9876543210', 'district' => 'Almora',
                'block' => 'Hawalbagh', 'beneficiary_type' => 'Individual', 'business_category' => 'Homestay',
                'business_stage' => 'Early', 'gender' => 'Female', 'education' => 'Graduate',
                'onboarding_date' => '10 Apr 2021', 'onboarding_date_sort' => 1617993000,
                'service_items' => [[
                    'label' => 'Udyam Registration', 'original_label' => 'Udyam Registration',
                    'mapped' => true, 'mapping_source' => 'exact', 'service_id' => 1,
                    'detail' => '', 'status' => 'Approved', 'date' => 'Date NA',
                ]],
                'services_count' => 1, 'services' => 'Udyam Registration',
                'original_services' => 'Udyam Registration', 'cfa_id' => 1,
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

    public function test_legacy_services_default_to_approved_reporting(): void
    {
        Cache::forever('legacy-data-explorer:version', 7);
        Cache::store('file')->put('legacy-data-explorer:dataset:s2:v7', collect([
            $this->cachedRow('APP-1', 'Phase 1', 'Approved'),
            $this->cachedRow('APP-2', 'Phase 3', 'pending_approval'),
        ]), now()->addMinute());

        $admin = User::factory()->create(['role' => 'state_admin', 'is_active' => true]);

        $this->actingAs($admin)
            ->get(route('admin.legacy-data.index', ['view' => 'services']))
            ->assertOk()
            ->assertSee('APP-1')
            ->assertDontSee('APP-2');

        $this->actingAs($admin)
            ->get(route('admin.legacy-data.index', ['view' => 'services', 'service_status' => '__all__']))
            ->assertOk()
            ->assertSee('APP-1')
            ->assertSee('APP-2');
    }

    public function test_non_state_admin_cannot_open_legacy_data_page(): void
    {
        $staff = User::factory()->create(['role' => 'district_staff', 'is_active' => true]);

        $this->actingAs($staff)
            ->get('/admin/legacy-data')
            ->assertForbidden();
    }

    /** @return array<string,mixed> */
    private function cachedRow(string $applicationNo, string $phase, string $status): array
    {
        return [
            'phase' => $phase, 'financial_year' => $phase === 'Phase 3' ? 'FY 2026-27' : 'FY 2021-22',
            'application_no' => $applicationNo, 'applicant' => 'Demo Applicant', 'phone' => '9876543210',
            'district' => 'Almora', 'block' => 'Hawalbagh', 'beneficiary_type' => 'Individual',
            'business_category' => 'Homestay', 'business_stage' => 'Early', 'gender' => 'Female',
            'education' => 'Graduate', 'onboarding_date' => '10 Apr 2021', 'onboarding_date_sort' => 1617993000,
            'service_items' => [[
                'label' => 'Business Model Canvas', 'original_label' => 'Business Model Canvas',
                'mapped' => true, 'mapping_source' => $phase === 'Phase 3' ? 'phase3_master' : 'exact',
                'service_id' => 1, 'detail' => '', 'status' => $status, 'date' => 'Date NA',
            ]],
            'services_count' => 1, 'services' => 'Business Model Canvas',
            'original_services' => 'Business Model Canvas', 'cfa_id' => crc32($applicationNo),
        ];
    }
}
