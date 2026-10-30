<?php

namespace Tests\Feature;

use App\Models\CfaSubmission;
use App\Models\District;
use App\Models\FiscalYear;
use App\Models\Hub;
use App\Models\User;
use App\Services\HubBatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class HubBatchLegacyOnboardedConflictTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_phase2_row_with_blank_status_blocks_onboarding_conflict(): void
    {
        $this->bindSqliteLegacyConnection();

        $legacyAppId = (int) DB::connection('legacy')->table('rbi_applications')->insertGetId([
            'application_no' => '40809999',
            'submission_date' => '2025-08-01',
        ]);
        DB::connection('legacy')->table('rbi_applicant_details')->insert([
            'application_id' => $legacyAppId,
            'applicant_name' => 'Legacy Blank Status',
            'phone' => '7454089464',
            'district' => 'Almora',
        ]);
        DB::connection('legacy')->table('rbi_onboarded_applicants')->insert([
            'application_id' => $legacyAppId,
            'onboarding_batch_id' => null,
            'status' => '',
            'onboarded_at' => null,
            'created_at' => '2025-09-01 10:00:00',
        ]);

        $hub = Hub::query()->create(['slug' => 'kumaon-test', 'name' => 'Kumaon', 'sort_order' => 1]);
        $district = District::query()->create([
            'hub_id' => $hub->id,
            'slug' => 'almora-test',
            'name' => 'Almora',
            'sort_order' => 1,
        ]);
        $fiscalYear = FiscalYear::query()->firstOrCreate(
            ['code' => '2025-26'],
            [
                'name' => 'FY 2025-26',
                'starts_on' => '2025-04-02',
                'ends_on' => '2026-04-01',
                'is_active' => true,
            ]
        );
        $cfa = CfaSubmission::query()->create([
            'application_no' => '40809999',
            'fiscal_year_id' => $fiscalYear->id,
            'district_id' => $district->id,
            'applicant_name' => 'Legacy Blank Status',
            'phone' => '7454089464',
            'payload' => ['legacy_application_id' => $legacyAppId],
        ]);

        $conflict = app(HubBatchService::class)->onboardingConflictForCfa((int) $cfa->id);

        $this->assertNotNull($conflict);
        $this->assertSame('legacy_phase2', $conflict['type']);
        $this->assertSame('40809999', $conflict['application_no']);
    }

    private function bindSqliteLegacyConnection(): void
    {
        $path = sys_get_temp_dir().'/muy-hub-batch-legacy-conflict.sqlite';
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
            $table->date('submission_date')->nullable();
        });
        $schema->create('rbi_applicant_details', function ($table): void {
            $table->increments('id');
            $table->unsignedInteger('application_id');
            $table->string('applicant_name')->nullable();
            $table->string('phone')->nullable();
            $table->string('district')->nullable();
        });
        $schema->create('rbi_onboarded_applicants', function ($table): void {
            $table->increments('id');
            $table->unsignedInteger('application_id');
            $table->unsignedInteger('onboarding_batch_id')->nullable();
            $table->string('status')->nullable();
            $table->timestamp('onboarded_at')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }
}
