<?php

namespace Tests\Feature;

use App\Models\District;
use App\Models\FiscalYear;
use App\Models\Hub;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CfaSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('cfa.search'))
            ->assertRedirect(route('login'));
    }

    public function test_incubatee_cannot_search(): void
    {
        $user = User::factory()->create(['role' => 'incubatee', 'is_active' => true]);

        $this->actingAs($user)
            ->get(route('cfa.search'))
            ->assertForbidden();
    }

    public function test_staff_can_search_current_cfa_by_name_phone_and_application_no(): void
    {
        $staff = User::factory()->create(['role' => 'district_staff', 'is_active' => true]);
        $district = $this->createDistrict();
        $fy = $this->createFiscalYear();

        DB::table('cfa_submissions')->insert([
            [
                'district_id' => $district->id,
                'fiscal_year_id' => $fy->id,
                'application_no' => 'MUY-SEARCH-11',
                'applicant_name' => 'Anita Rawat',
                'phone' => '9876543210',
                'payload' => json_encode([]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'district_id' => $district->id,
                'fiscal_year_id' => $fy->id,
                'application_no' => 'MUY-OTHER-22',
                'applicant_name' => 'Other Person',
                'phone' => '9000000001',
                'payload' => json_encode([]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->actingAs($staff)
            ->get(route('cfa.search', ['q' => 'Anita']))
            ->assertOk()
            ->assertSee('Anita Rawat')
            ->assertSee('MUY-SEARCH-11')
            ->assertSee('Current MIS')
            ->assertDontSee('Other Person');

        $this->actingAs($staff)
            ->get(route('cfa.search', ['q' => '9876543210']))
            ->assertOk()
            ->assertSee('Anita Rawat')
            ->assertDontSee('Other Person');

        $this->actingAs($staff)
            ->get(route('cfa.search', ['q' => 'MUY-SEARCH-11']))
            ->assertOk()
            ->assertSee('Anita Rawat')
            ->assertDontSee('Other Person');
    }

    public function test_state_admin_can_open_current_cfa_details_from_search(): void
    {
        $admin = User::factory()->create(['role' => 'state_admin', 'is_active' => true]);
        $district = $this->createDistrict();
        $fy = $this->createFiscalYear();

        $id = DB::table('cfa_submissions')->insertGetId([
            'district_id' => $district->id,
            'fiscal_year_id' => $fy->id,
            'application_no' => 'MUY-DETAIL-01',
            'applicant_name' => 'Detail Applicant',
            'phone' => '9123456789',
            'payload' => json_encode([
                'applicant_name' => 'Detail Applicant',
                'block' => 'Test Block',
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('cfa.search.current', ['cfa_submission' => $id, 'q' => 'Detail']))
            ->assertOk()
            ->assertSee('Detail Applicant')
            ->assertSee('MUY-DETAIL-01')
            ->assertSee('Back to list');
    }

    public function test_short_query_does_not_search(): void
    {
        $admin = User::factory()->create(['role' => 'state_admin', 'is_active' => true]);

        $this->actingAs($admin)
            ->get(route('cfa.search', ['q' => 'ab']))
            ->assertOk()
            ->assertSee('Enter at least 3 characters');
    }

    private function createFiscalYear(): FiscalYear
    {
        return FiscalYear::query()->create([
            'code' => 'FY2026-27',
            'name' => 'FY 2026-27',
            'starts_on' => '2026-04-01',
            'ends_on' => '2027-03-31',
            'is_active' => true,
        ]);
    }

    private function createDistrict(): District
    {
        $hub = Hub::query()->create([
            'slug' => 'cfa-search-hub',
            'name' => 'Search Hub',
            'sort_order' => 1,
        ]);

        return District::query()->create([
            'hub_id' => $hub->id,
            'slug' => 'cfa-search-district',
            'name' => 'Search District',
            'sort_order' => 1,
        ]);
    }
}
