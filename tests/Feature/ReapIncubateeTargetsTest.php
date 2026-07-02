<?php

namespace Tests\Feature;

use App\Models\District;
use App\Models\FiscalYear;
use App\Models\Hub;
use App\Models\Service;
use App\Models\ServiceCase;
use App\Models\ServiceCategory;
use App\Models\User;
use App\Services\AppSettingsService;
use App\Services\ReapIncubateeTargetProgressService;
use App\Support\ConvergenceReapSupport;
use App\Support\ReapIncubateeTargets;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ReapIncubateeTargetsTest extends TestCase
{
    use RefreshDatabase;

    public function test_official_config_grand_totals_match_fy_plan(): void
    {
        $targets = ReapIncubateeTargets::emptyBucketCounts();
        foreach (array_keys((array) config('reap_incubatee_targets.districts', [])) as $slug) {
            $row = ReapIncubateeTargets::targetsForDistrictSlug($slug);
            foreach (ReapIncubateeTargets::bucketKeys() as $bucket) {
                $targets[$bucket] += (int) $row[$bucket];
            }
        }

        $this->assertSame(65, $targets[ReapIncubateeTargets::BUCKET_FARM_1_LAKH]);
        $this->assertSame(65, $targets[ReapIncubateeTargets::BUCKET_FARM_3_LAKH]);
        $this->assertSame(182, $targets[ReapIncubateeTargets::BUCKET_NON_FARM_1_LAKH]);
        $this->assertSame(188, $targets[ReapIncubateeTargets::BUCKET_NON_FARM_3_LAKH]);
        $this->assertSame(500, ReapIncubateeTargets::sumBuckets($targets));
    }

    public function test_progress_service_counts_approved_cases_by_bucket(): void
    {
        app(AppSettingsService::class)->setMany(['service_module.enabled' => true]);

        FiscalYear::query()->firstOrCreate(
            ['code' => '2026-27'],
            [
                'name' => 'FY 2026-27',
                'starts_on' => '2026-04-01',
                'ends_on' => '2027-03-31',
                'is_active' => true,
            ]
        );

        $hub = Hub::query()->create(['slug' => 'reap-target-hub', 'name' => 'Hub', 'sort_order' => 1]);
        $district = District::query()->create([
            'hub_id' => $hub->id,
            'slug' => 'almora',
            'name' => 'Almora',
            'sort_order' => 1,
        ]);

        $category = ServiceCategory::query()->create([
            'slug' => 'reap_support_targets',
            'name' => 'REAP Support',
            'sort_order' => 1,
        ]);

        $service = Service::query()->create([
            'service_category_id' => $category->id,
            'code' => 'support_muy_incubatee_reap',
            'name' => 'Support to MUY Incubatee through REAP',
            'sort_order' => 1,
            'is_active' => true,
            'requires_approval' => false,
            'counts_toward_reap_support' => true,
        ]);

        $cfaId = (int) DB::table('cfa_submissions')->insertGetId([
            'district_id' => $district->id,
            'applicant_name' => 'REAP Target Applicant',
            'phone' => '9999999901',
            'payload' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        ServiceCase::query()->create([
            'cfa_submission_id' => $cfaId,
            'service_id' => $service->id,
            'status' => ServiceCase::STATUS_APPROVED,
            'approved_at' => '2026-05-15 10:00:00',
            'through_reap' => true,
            'payload' => [
                'through_reap' => '1',
                'reap_sector' => 'farm',
                'reap_amount' => '1_lakh',
            ],
        ]);

        $progress = app(ReapIncubateeTargetProgressService::class)->districtProgress((int) $district->id);

        $this->assertNotNull($progress);
        $this->assertSame('Almora', $progress['district']['name']);
        $this->assertSame(1, $progress['buckets'][ReapIncubateeTargets::BUCKET_FARM_1_LAKH]['approved']);
        $this->assertSame(5, $progress['buckets'][ReapIncubateeTargets::BUCKET_FARM_1_LAKH]['target']);
        $this->assertSame(1, $progress['totals']['approved']);
    }

    public function test_state_admin_reap_targets_page_shows_district_achievement_table(): void
    {
        $admin = User::factory()->create([
            'role' => 'state_admin',
            'is_active' => true,
        ]);

        FiscalYear::query()->firstOrCreate(
            ['code' => '2026-27'],
            [
                'name' => 'FY 2026-27',
                'starts_on' => '2026-04-01',
                'ends_on' => '2027-03-31',
                'is_active' => true,
            ]
        );

        $hub = Hub::query()->create(['slug' => 'reap-admin-hub', 'name' => 'Kumaon', 'sort_order' => 1]);
        District::query()->create([
            'hub_id' => $hub->id,
            'slug' => 'almora',
            'name' => 'Almora',
            'sort_order' => 1,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.targets.reap-incubatee'))
            ->assertOk()
            ->assertSee('REAP incubatee targets', false)
            ->assertSee('Almora', false)
            ->assertSee('Grand total (achievement / target)', false);
    }

    public function test_staff_create_and_reap_list_show_district_target_panel(): void
    {
        app(AppSettingsService::class)->setMany(['service_module.enabled' => true]);

        FiscalYear::query()->firstOrCreate(
            ['code' => '2026-27'],
            [
                'name' => 'FY 2026-27',
                'starts_on' => '2026-04-01',
                'ends_on' => '2027-03-31',
                'is_active' => true,
            ]
        );

        $hub = Hub::query()->create(['slug' => 'reap-panel-hub', 'name' => 'Hub', 'sort_order' => 2]);
        $district = District::query()->create([
            'hub_id' => $hub->id,
            'slug' => 'dehradun',
            'name' => 'Dehradun',
            'sort_order' => 1,
        ]);

        $staff = User::factory()->create([
            'role' => 'district_staff',
            'district_id' => $district->id,
            'is_active' => true,
        ]);

        $this->actingAs($staff)
            ->get(route('staff.services.create'))
            ->assertOk()
            ->assertSee('District REAP targets', false)
            ->assertSee('Dehradun', false);

        $this->actingAs($staff)
            ->get(route('staff.services.index', ['service_id' => ConvergenceReapSupport::MIS_8_2_LIST_FILTER]))
            ->assertOk()
            ->assertSee('District REAP targets', false)
            ->assertSee('0/21', false);
    }

    public function test_reap_document_accepts_non_pdf_file_types(): void
    {
        app(AppSettingsService::class)->setMany(['service_module.enabled' => true]);

        $hub = Hub::query()->create(['slug' => 'reap-doc-hub', 'name' => 'Hub', 'sort_order' => 3]);
        $district = District::query()->create([
            'hub_id' => $hub->id,
            'slug' => 'almora',
            'name' => 'Almora',
            'sort_order' => 1,
        ]);

        $staff = User::factory()->create([
            'role' => 'district_staff',
            'district_id' => $district->id,
            'is_active' => true,
        ]);

        $category = ServiceCategory::query()->create([
            'slug' => 'reap_support_doc',
            'name' => 'REAP Support',
            'sort_order' => 1,
        ]);

        $service = Service::query()->create([
            'service_category_id' => $category->id,
            'code' => 'support_muy_incubatee_reap',
            'name' => 'Support to MUY Incubatee through REAP',
            'sort_order' => 1,
            'is_active' => true,
            'requires_approval' => false,
            'counts_toward_reap_support' => true,
        ]);

        $cfaId = (int) DB::table('cfa_submissions')->insertGetId([
            'district_id' => $district->id,
            'applicant_name' => 'Doc Type Applicant',
            'phone' => '9999999902',
            'payload' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $batchId = (int) DB::table('onboarding_batches')->insertGetId([
            'hub_id' => $hub->id,
            'district_id' => $district->id,
            'name' => 'Doc type batch',
            'target_size' => 1,
            'status' => 'locked',
            'locked_at' => now(),
            'onboarding_date' => '2026-05-01',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('onboarding_batch_cfa')->insert([
            'onboarding_batch_id' => $batchId,
            'cfa_submission_id' => $cfaId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($staff)
            ->post(route('staff.services.store'), [
                'cfa_submission_id' => $cfaId,
                'service_id' => $service->id,
                'payload' => [
                    'through_reap' => '1',
                    'reap_sector' => 'farm',
                    'reap_amount' => '1_lakh',
                    'reap_activity' => 'REAP support activity.',
                ],
                'payload_files' => [
                    'reap_document' => UploadedFile::fake()->create('support-letter.docx', 50),
                ],
            ])
            ->assertRedirect(route('staff.services.index'));
    }
}
