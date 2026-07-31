<?php

namespace Tests\Feature;

use App\Models\District;
use App\Models\Hub;
use App\Models\OnboardingBatch;
use App\Models\OnboardingBatchCfa;
use App\Models\ServiceCase;
use App\Models\User;
use App\Services\AppSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class StaffBatchShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_district_staff_sees_view_link_for_batch_members(): void
    {
        [$district, $batch, $cfaId] = $this->createLockedBatchWithMember();

        $staff = User::factory()->create([
            'role' => 'district_staff',
            'district_id' => $district->id,
            'hub_id' => $district->hub_id,
            'is_active' => true,
        ]);

        $this->actingAs($staff)
            ->get(route('staff.batches.show', $batch))
            ->assertOk()
            ->assertSee('View')
            ->assertSee(route('staff.applications.show', $cfaId), false);
    }

    public function test_district_staff_sees_add_intervention_when_service_module_enabled(): void
    {
        [$district, $batch, $cfaId] = $this->createLockedBatchWithMember();

        app(AppSettingsService::class)->setMany(['service_module.enabled' => true]);

        $staff = User::factory()->create([
            'role' => 'district_staff',
            'district_id' => $district->id,
            'hub_id' => $district->hub_id,
            'is_active' => true,
        ]);

        $this->actingAs($staff)
            ->get(route('staff.batches.show', $batch))
            ->assertOk()
            ->assertSee('Add intervention')
            ->assertSee(route('staff.services.create', ['cfa_submission_id' => $cfaId]), false);
    }

    public function test_batch_services_given_shows_market_linkage(): void
    {
        if (! Schema::hasTable('market_linkage_submissions') || ! Schema::hasTable('market_linkage_partners')) {
            $this->markTestSkipped('Market linkage tables are not available.');
        }

        [$district, $batch, $cfaId] = $this->createLockedBatchWithMember();

        $staff = User::factory()->create([
            'role' => 'district_staff',
            'district_id' => $district->id,
            'hub_id' => $district->hub_id,
            'is_active' => true,
        ]);

        $submissionId = (int) DB::table('market_linkage_submissions')->insertGetId([
            'submitted_by_user_id' => $staff->id,
            'submitted_by_name' => $staff->name,
            'district_id' => $district->id,
            'district_name' => $district->name,
            'cfa_submission_id' => $cfaId,
            'incubatee_name' => 'Suman Devi',
            'application_no' => '40803999',
            'status' => ServiceCase::STATUS_APPROVED,
            'submitted_at' => now(),
            'approved_at' => now(),
            'approved_by' => $staff->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('market_linkage_partners')->insert([
            'market_linkage_submission_id' => $submissionId,
            'partner_name' => 'Local Mandi',
            'linkage_mode' => 'offline',
            'linkage_date' => '2026-05-15',
            'sort_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($staff)
            ->get(route('staff.batches.show', $batch))
            ->assertOk()
            ->assertSee('Services given')
            ->assertSee('Market Linkage · Offline · Local Mandi');
    }

    /**
     * @return array{0: District, 1: OnboardingBatch, 2: int}
     */
    private function createLockedBatchWithMember(): array
    {
        $hub = Hub::query()->create(['slug' => 'batch-show-hub', 'name' => 'Hub', 'sort_order' => 1]);
        $district = District::query()->create([
            'hub_id' => $hub->id,
            'slug' => 'batch-show-dist',
            'name' => 'Batch Show District',
            'sort_order' => 1,
        ]);

        $cfaId = (int) DB::table('cfa_submissions')->insertGetId([
            'district_id' => $district->id,
            'application_no' => '40803999',
            'applicant_name' => 'Suman Devi',
            'phone' => '9000000099',
            'payload' => json_encode([
                'form_stage' => 'seed',
                'business_category' => 'Handicraft',
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $batch = OnboardingBatch::query()->create([
            'hub_id' => $hub->id,
            'district_id' => $district->id,
            'name' => 'Batch 8 Test',
            'target_size' => 80,
            'status' => 'locked',
            'locked_at' => now(),
            'onboarding_date' => '2026-05-01',
        ]);

        OnboardingBatchCfa::query()->create([
            'onboarding_batch_id' => $batch->id,
            'cfa_submission_id' => $cfaId,
        ]);

        return [$district, $batch, $cfaId];
    }
}
