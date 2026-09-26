<?php

namespace Tests\Feature;

use App\Models\CfaSubmission;
use App\Models\District;
use App\Models\FiscalYear;
use App\Models\Hub;
use App\Models\OnboardingBatch;
use App\Models\OnboardingBatchCfa;
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

    public function test_same_phone_as_an_onboarded_cfa_is_not_eligible_again(): void
    {
        config()->set('database.connections.legacy.database', '');
        config()->set('database.connections.legacy_phase1.database', '');

        $hub = Hub::query()->create(['slug' => 'phone-hub', 'name' => 'Phone Hub', 'sort_order' => 1]);
        $district = District::query()->create([
            'hub_id' => $hub->id,
            'slug' => 'phone-district',
            'name' => 'Phone District',
            'sort_order' => 1,
        ]);
        $fiscalYear = FiscalYear::query()->create([
            'code' => '2026-27-phone',
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
        $original = CfaSubmission::query()->create([
            'application_no' => 'PHONE-ORIGINAL',
            'fiscal_year_id' => $fiscalYear->id,
            'district_id' => $district->id,
            'applicant_name' => 'Original Applicant',
            'phone' => '9876501234',
            'payload' => ['form_stage' => 'early'],
        ]);
        $duplicate = CfaSubmission::query()->create([
            'application_no' => 'PHONE-DUPLICATE',
            'fiscal_year_id' => $fiscalYear->id,
            'district_id' => $district->id,
            'applicant_name' => 'Duplicate Applicant',
            'phone' => '9876501234',
            'payload' => ['form_stage' => 'early'],
        ]);
        $lockedBatch = OnboardingBatch::query()->create([
            'hub_id' => $hub->id,
            'district_id' => $district->id,
            'name' => 'Existing locked batch',
            'target_size' => 1,
            'status' => 'locked',
            'created_by' => $manager->id,
        ]);
        OnboardingBatchCfa::query()->create([
            'onboarding_batch_id' => $lockedBatch->id,
            'cfa_submission_id' => $original->id,
        ]);

        $service = app(HubBatchService::class);
        $browse = $service->handleApi('pool_list', $manager, [
            'district_id' => $district->id,
            'fiscal_year_id' => $fiscalYear->id,
            'q' => '',
        ]);
        $this->assertTrue($browse['ok']);
        $this->assertSame([], $browse['data']['candidates']);

        $search = $service->handleApi('pool_list', $manager, [
            'district_id' => $district->id,
            'fiscal_year_id' => $fiscalYear->id,
            'q' => $duplicate->application_no,
        ]);
        $this->assertTrue($search['ok']);
        $this->assertCount(1, $search['data']['candidates']);
        $candidate = $search['data']['candidates'][0];
        $this->assertFalse($candidate['eligible']);
        $this->assertSame('phone_onboarded', $candidate['eligibility_status']);
        $this->assertSame('Mobile already onboarded', $candidate['eligibility_label']);
        $this->assertStringContainsString('PHONE-ORIGINAL', $candidate['eligibility_detail']);
    }
}
