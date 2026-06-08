<?php

namespace Tests\Feature;

use App\Models\Designation;
use App\Models\District;
use App\Models\FiscalYear;
use App\Models\Hub;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CfaSubmissionIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_application_number_search_finds_record_outside_default_fy_scope(): void
    {
        $admin = User::factory()->create(['role' => 'state_admin', 'is_active' => true]);
        $district = $this->createDistrict();

        $activeFy = FiscalYear::query()->create([
            'code' => 'FY2025-26',
            'name' => 'FY 2025-26',
            'starts_on' => '2025-04-01',
            'ends_on' => '2026-03-31',
            'is_active' => true,
            'is_phase3_default' => true,
        ]);

        DB::table('cfa_submissions')->insert([
            'district_id' => $district->id,
            'fiscal_year_id' => $activeFy->id,
            'application_no' => '5040602D',
            'applicant_name' => 'In Scope Applicant',
            'phone' => '9000000001',
            'payload' => json_encode([]),
            'created_at' => '2026-05-01',
            'updated_at' => now(),
        ]);

        DB::table('cfa_submissions')->insert([
            'district_id' => $district->id,
            'fiscal_year_id' => null,
            'application_no' => '4010101X',
            'applicant_name' => 'Old Scope Applicant',
            'phone' => '9000000002',
            'payload' => json_encode([]),
            'created_at' => '2025-01-15',
            'updated_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.cfa.index'))
            ->assertOk()
            ->assertSee('In Scope Applicant')
            ->assertDontSee('Old Scope Applicant');

        $this->actingAs($admin)
            ->get(route('admin.cfa.index', ['application_no' => '4010101X']))
            ->assertOk()
            ->assertSee('4010101X')
            ->assertSee('Old Scope Applicant');
    }

    public function test_designation_filter_limits_list_to_matching_referral_staff(): void
    {
        $admin = User::factory()->create(['role' => 'state_admin', 'is_active' => true]);
        $district = $this->createDistrict();

        $imDesignation = Designation::query()->create(['name' => 'Incubation Manager', 'sort_order' => 1]);
        $fcDesignation = Designation::query()->create(['name' => 'Field Coordinator', 'sort_order' => 2]);

        $imStaff = User::factory()->create([
            'role' => 'district_staff',
            'district_id' => $district->id,
            'designation_id' => $imDesignation->id,
            'is_active' => true,
        ]);
        $fcStaff = User::factory()->create([
            'role' => 'district_staff',
            'district_id' => $district->id,
            'designation_id' => $fcDesignation->id,
            'is_active' => true,
        ]);

        $activeFy = FiscalYear::query()->create([
            'code' => 'FY2026-27',
            'name' => 'FY 2026-27',
            'starts_on' => '2026-04-01',
            'ends_on' => '2027-03-31',
            'is_active' => true,
            'is_phase3_default' => true,
        ]);

        DB::table('cfa_submissions')->insert([
            [
                'district_id' => $district->id,
                'fiscal_year_id' => $activeFy->id,
                'referral_user_id' => $imStaff->id,
                'application_no' => 'IM-001',
                'applicant_name' => 'IM Applicant',
                'phone' => '9000000101',
                'payload' => json_encode([]),
                'created_at' => '2026-05-01',
                'updated_at' => now(),
            ],
            [
                'district_id' => $district->id,
                'fiscal_year_id' => $activeFy->id,
                'referral_user_id' => $fcStaff->id,
                'application_no' => 'FC-001',
                'applicant_name' => 'FC Applicant',
                'phone' => '9000000102',
                'payload' => json_encode([]),
                'created_at' => '2026-05-01',
                'updated_at' => now(),
            ],
        ]);

        $this->actingAs($admin)
            ->get(route('admin.cfa.index', ['designation_id' => $imDesignation->id]))
            ->assertOk()
            ->assertSee('IM Applicant')
            ->assertDontSee('FC Applicant');
    }

    private function createDistrict(): District
    {
        $hub = Hub::query()->create([
            'slug' => 'cfa-index-hub',
            'name' => 'CFA Hub',
            'sort_order' => 1,
        ]);

        return District::query()->create([
            'hub_id' => $hub->id,
            'slug' => 'cfa-index-district',
            'name' => 'Test District',
            'sort_order' => 1,
        ]);
    }
}
