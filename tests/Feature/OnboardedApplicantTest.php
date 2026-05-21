<?php

namespace Tests\Feature;

use App\Models\District;
use App\Models\Hub;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OnboardedApplicantTest extends TestCase
{
    use RefreshDatabase;

    public function test_state_admin_sees_district_summaries_and_applicant_cards(): void
    {
        $admin = User::factory()->create([
            'role' => 'state_admin',
            'is_active' => true,
        ]);

        $districtA = $this->createDistrict('almora', 'Almora');
        $districtB = $this->createDistrict('nainital', 'Nainital');

        $this->seedOnboardedApplicant($districtA, '40801001', 'Female Applicant', 'female');
        $this->seedOnboardedApplicant($districtA, '40801002', 'Male Applicant', 'male');
        $this->seedOnboardedApplicant($districtB, '40802001', 'Other Applicant', 'female');

        $response = $this->actingAs($admin)->get(route('admin.onboarded.index'));

        $response->assertOk();
        $response->assertSee('District-wise onboarding');
        $response->assertSee('Total onboarded');
        $response->assertSee('Almora');
        $response->assertSee('Nainital');
        $response->assertSee('Female Applicant');
        $response->assertSee('Applicant records');
    }

    public function test_district_filter_limits_applicant_list(): void
    {
        $admin = User::factory()->create([
            'role' => 'state_admin',
            'is_active' => true,
        ]);

        $districtA = $this->createDistrict('pauri', 'Pauri');
        $districtB = $this->createDistrict('tehri', 'Tehri');

        $this->seedOnboardedApplicant($districtA, '40803001', 'Pauri Applicant', 'female');
        $this->seedOnboardedApplicant($districtB, '40803002', 'Tehri Applicant', 'male');

        $response = $this->actingAs($admin)->get(route('admin.onboarded.index', [
            'district' => $districtA->id,
        ]));

        $response->assertOk();
        $response->assertSee('Pauri Applicant');
        $response->assertDontSee('Tehri Applicant');
    }

    private function seedOnboardedApplicant(District $district, string $applicationNo, string $name, string $gender): int
    {
        $cfaId = (int) DB::table('cfa_submissions')->insertGetId([
            'district_id' => $district->id,
            'application_no' => $applicationNo,
            'applicant_name' => $name,
            'phone' => '9876543210',
            'source' => 'phase3',
            'payload' => json_encode([
                'gender' => $gender,
                'block' => 'Test Block',
                'guardian_name' => 'Guardian',
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $batchId = (int) DB::table('onboarding_batches')->insertGetId([
            'hub_id' => $district->hub_id,
            'district_id' => $district->id,
            'name' => $district->name.'-batch1',
            'target_size' => 1,
            'status' => 'locked',
            'locked_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('onboarding_batch_cfa')->insert([
            'onboarding_batch_id' => $batchId,
            'cfa_submission_id' => $cfaId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $cfaId;
    }

    private function createDistrict(string $slug, string $name): District
    {
        $hub = Hub::query()->create([
            'slug' => 'hub-'.$slug,
            'name' => 'Hub '.$name,
            'sort_order' => 1,
        ]);

        return District::query()->create([
            'hub_id' => $hub->id,
            'slug' => $slug,
            'name' => $name,
            'sort_order' => 1,
        ]);
    }
}
