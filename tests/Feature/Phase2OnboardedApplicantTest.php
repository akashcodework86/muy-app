<?php

namespace Tests\Feature;

use App\Models\District;
use App\Models\FiscalYear;
use App\Models\Hub;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class Phase2OnboardedApplicantTest extends TestCase
{
    use RefreshDatabase;

    public function test_state_admin_sees_2025_26_onboarding_page(): void
    {
        $admin = User::factory()->create([
            'role' => 'state_admin',
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.onboarded-2025-26.index'));

        $response->assertOk();
        $response->assertSee('2025-26 onboarding');
        $response->assertSee('Total onboarded');
        $response->assertSee('Districts covered');
        $response->assertSee('Women onboarded');
        $response->assertSee('Potential Lakhpati Didi/ SHG Members/ CBOs');
        $response->assertSee('Applicant records');
        $response->assertSee('Category');
        $response->assertSee('Caste');
        $response->assertSee('FY 2025-26');
        $response->assertDontSee('Phase 3: SHG/CBO or member Yes');
    }

    public function test_nav_link_is_present_on_onboarded_page(): void
    {
        $admin = User::factory()->create([
            'role' => 'state_admin',
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.onboarded.index'));

        $response->assertOk();
        $response->assertSee(route('admin.onboarded-2025-26.index'), false);
        $response->assertSee('2025-26 onboarding');
    }

    public function test_page_lists_phase2_onboarded_applicants_from_legacy_db(): void
    {
        $this->bindSqliteLegacyConnection();

        $admin = User::factory()->create([
            'role' => 'state_admin',
            'is_active' => true,
        ]);
        $this->createDistrict('almora-p2', 'Almora');
        FiscalYear::query()->firstOrCreate(
            ['code' => '2025-26'],
            [
                'name' => 'FY 2025-26',
                'starts_on' => '2025-04-02',
                'ends_on' => '2026-04-01',
                'is_active' => false,
            ]
        );

        $this->seedLegacyOnboardedApplicant('P2-40801001', 'Phase Two Woman', 'Almora', 'female', 'Yes', 'Yes');

        $response = $this->actingAs($admin)->get(route('admin.onboarded-2025-26.index'));

        $response->assertOk();
        $response->assertSee('Phase Two Woman');
        $response->assertSee('P2-40801001');
        $response->assertSee('Almora');
        $response->assertSee('Category');
        $response->assertSee('Caste');
        $response->assertSee('SC');
        $response->assertSee('FY 2025-26');
    }

    public function test_export_downloads_csv(): void
    {
        $this->bindSqliteLegacyConnection();

        $admin = User::factory()->create([
            'role' => 'state_admin',
            'is_active' => true,
        ]);
        $this->createDistrict('nainital-p2', 'Nainital');
        $this->seedLegacyOnboardedApplicant('P2-40802001', 'Export Applicant', 'Nainital', 'male', 'No', 'No');

        $response = $this->actingAs($admin)->get(route('admin.onboarded-2025-26.export'));

        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('content-type'));
        $csv = $response->streamedContent();
        $this->assertStringContainsString('Export Applicant', $csv);
        $this->assertStringContainsString('FY 2025-26', $csv);
    }

    private function bindSqliteLegacyConnection(): void
    {
        $path = sys_get_temp_dir().'/muy-phase2-onboarded-test.sqlite';
        if (file_exists($path)) {
            unlink($path);
        }
        touch($path);

        config()->set('database.connections.legacy', [
            'driver' => 'sqlite',
            'database' => $path,
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);
        DB::purge('legacy');

        $schema = Schema::connection('legacy');
        $schema->create('rbi_applications', function ($table): void {
            $table->increments('id');
            $table->string('application_no')->nullable();
            $table->string('form_stage')->nullable();
            $table->string('business_category')->nullable();
            $table->string('category')->nullable();
            $table->string('product')->nullable();
            $table->string('other_product')->nullable();
            $table->date('submission_date')->nullable();
        });
        $schema->create('rbi_applicant_details', function ($table): void {
            $table->increments('id');
            $table->unsignedInteger('application_id');
            $table->string('applicant_name')->nullable();
            $table->string('phone')->nullable();
            $table->string('guardian_name')->nullable();
            $table->string('gender')->nullable();
            $table->string('dob')->nullable();
            $table->string('district')->nullable();
            $table->string('block')->nullable();
            $table->string('is_shg_member')->nullable();
            $table->string('shg_name')->nullable();
            $table->string('lakhpati')->nullable();
            $table->string('caste')->nullable();
        });
        $schema->create('rbi_onboarded_applicants', function ($table): void {
            $table->increments('id');
            $table->unsignedInteger('application_id');
            $table->unsignedInteger('onboarding_batch_id')->nullable();
            $table->string('status')->nullable();
            $table->timestamp('onboarded_at')->nullable();
            $table->timestamp('created_at')->nullable();
        });
        $schema->create('rbi_onboarding_batches', function ($table): void {
            $table->increments('id');
            $table->string('batch_name')->nullable();
            $table->date('onboarding_date')->nullable();
        });
    }

    private function seedLegacyOnboardedApplicant(
        string $applicationNo,
        string $name,
        string $district,
        string $gender,
        string $lakhpati,
        string $isShgMember,
        string $caste = 'SC',
    ): void {
        $appId = (int) DB::connection('legacy')->table('rbi_applications')->insertGetId([
            'application_no' => $applicationNo,
            'form_stage' => 'early',
            'business_category' => 'Food Processing',
            'category' => 'Individual',
            'submission_date' => '2025-08-10',
        ]);

        DB::connection('legacy')->table('rbi_applicant_details')->insert([
            'application_id' => $appId,
            'applicant_name' => $name,
            'phone' => '9876543210',
            'guardian_name' => 'Guardian',
            'gender' => $gender,
            'district' => $district,
            'block' => 'Test Block',
            'is_shg_member' => $isShgMember,
            'lakhpati' => $lakhpati,
            'caste' => $caste,
        ]);

        $batchId = (int) DB::connection('legacy')->table('rbi_onboarding_batches')->insertGetId([
            'batch_name' => $district.'-batch1',
            'onboarding_date' => '2025-09-01',
        ]);

        DB::connection('legacy')->table('rbi_onboarded_applicants')->insert([
            'application_id' => $appId,
            'onboarding_batch_id' => $batchId,
            'status' => 'onboarded',
            'onboarded_at' => '2025-09-01 10:00:00',
            'created_at' => '2025-09-01 10:00:00',
        ]);
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
