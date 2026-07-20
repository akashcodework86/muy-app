<?php

namespace Tests\Feature;

use App\Models\CfaSubmission;
use App\Models\District;
use App\Models\FiscalYear;
use App\Models\Hub;
use App\Models\OnboardingBatch;
use App\Models\OnboardingBatchDraftCfa;
use App\Models\User;
use App\Services\HubBatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HubBatchPoolSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_explains_that_a_cfa_is_already_in_the_current_draft(): void
    {
        config()->set('database.connections.legacy.database', '');
        config()->set('database.connections.legacy_phase1.database', '');

        $hub = Hub::query()->create([
            'slug' => 'garhwal-test',
            'name' => 'Garhwal Test',
            'sort_order' => 1,
        ]);
        $district = District::query()->create([
            'hub_id' => $hub->id,
            'slug' => 'haridwar-test',
            'name' => 'Haridwar',
            'sort_order' => 1,
        ]);
        $fiscalYear = FiscalYear::query()->create([
            'code' => '2026-27',
            'name' => 'FY 2026-27',
            'starts_on' => '2026-04-02',
            'ends_on' => '2027-04-01',
            'is_active' => true,
        ]);
        $manager = User::factory()->create([
            'role' => 'hub_admin',
            'hub_id' => $hub->id,
            'is_active' => true,
        ]);
        $cfa = CfaSubmission::query()->create([
            'application_no' => '60800034',
            'fiscal_year_id' => $fiscalYear->id,
            'district_id' => $district->id,
            'applicant_name' => 'FARAHT JAHAN',
            'phone' => '9999999999',
            'payload' => ['form_stage' => 'early'],
        ]);
        $draft = OnboardingBatch::query()->create([
            'hub_id' => $hub->id,
            'district_id' => $district->id,
            'name' => 'Haridwar-batch1-Jul-2026',
            'target_size' => 33,
            'status' => 'draft',
            'created_by' => $manager->id,
        ]);
        OnboardingBatchDraftCfa::query()->create([
            'onboarding_batch_id' => $draft->id,
            'cfa_submission_id' => $cfa->id,
        ]);

        $service = app(HubBatchService::class);

        $browseResult = $service->handleApi('pool_list', $manager, [
            'district_id' => $district->id,
            'fiscal_year_id' => $fiscalYear->id,
            'q' => '',
        ]);
        $this->assertTrue($browseResult['ok']);
        $this->assertSame([], $browseResult['data']['candidates']);

        $searchResult = $service->handleApi('pool_list', $manager, [
            'district_id' => $district->id,
            'fiscal_year_id' => $fiscalYear->id,
            'q' => '60800034',
        ]);

        $this->assertTrue($searchResult['ok']);
        $this->assertCount(1, $searchResult['data']['candidates']);
        $candidate = $searchResult['data']['candidates'][0];
        $this->assertSame('60800034', $candidate['application_no']);
        $this->assertFalse($candidate['eligible']);
        $this->assertSame('current_draft', $candidate['eligibility_status']);
        $this->assertSame('Already in current draft', $candidate['eligibility_label']);
        $this->assertSame($draft->id, $candidate['batch_id']);

        $this->actingAs($manager)
            ->get(route('hub.batches.index'))
            ->assertOk()
            ->assertSee('Search current draft')
            ->assertSee('viewCurrentDraftMember', false);
    }
}
