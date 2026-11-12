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

    public function test_pool_filter_defaults_to_all_years(): void
    {
        config()->set('database.connections.legacy.database', '');
        config()->set('database.connections.legacy_phase1.database', '');

        [, , $fiscalYear, $manager] = $this->poolFixture('default-all');

        $this->actingAs($manager)
            ->get(route('hub.batches.index'))
            ->assertOk()
            ->assertSee('<option value="all" selected>All</option>', false)
            ->assertDontSee('value="'.$fiscalYear->id.'" selected', false);
    }

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
            'phone' => '+91 98765 01234',
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
        $identityConflict = $service->onboardingConflictForCfa((int) $duplicate->id);
        $this->assertNotNull($identityConflict);
        $this->assertSame('PHONE-ORIGINAL', $identityConflict['application_no']);

        $sameApplication = CfaSubmission::query()->create([
            'application_no' => ' PHONE-ORIGINAL ',
            'fiscal_year_id' => $fiscalYear->id,
            'district_id' => $district->id,
            'applicant_name' => 'Application Number Duplicate',
            'phone' => '9000000000',
            'payload' => ['form_stage' => 'seed'],
        ]);
        $applicationConflict = $service->onboardingConflictForCfa((int) $sameApplication->id);
        $this->assertNotNull($applicationConflict);
        $this->assertSame('PHONE-ORIGINAL', $applicationConflict['application_no']);
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

    public function test_shared_application_number_ending_is_not_an_onboarding_conflict(): void
    {
        config()->set('database.connections.legacy.database', '');
        config()->set('database.connections.legacy_phase1.database', '');

        [$hub, $district, $fiscalYear, $manager] = $this->poolFixture('suffix');
        $onboarded = CfaSubmission::query()->create([
            'application_no' => '40403002',
            'fiscal_year_id' => $fiscalYear->id,
            'district_id' => $district->id,
            'applicant_name' => 'Pooja Shahi',
            'phone' => '9837389846',
            'payload' => ['form_stage' => 'early'],
        ]);
        $candidateCfa = CfaSubmission::query()->create([
            'application_no' => 'A0403002',
            'fiscal_year_id' => $fiscalYear->id,
            'district_id' => $district->id,
            'applicant_name' => 'Kanksha Negi',
            'phone' => '7983576922',
            'payload' => ['form_stage' => 'early'],
        ]);
        $batch = OnboardingBatch::query()->create([
            'hub_id' => $hub->id,
            'district_id' => $district->id,
            'name' => 'Nainital-batch1-May-2026',
            'target_size' => 1,
            'status' => 'locked',
            'created_by' => $manager->id,
        ]);
        OnboardingBatchCfa::query()->create([
            'onboarding_batch_id' => $batch->id,
            'cfa_submission_id' => $onboarded->id,
        ]);

        $service = app(HubBatchService::class);
        $this->assertNull($service->onboardingConflictForCfa((int) $candidateCfa->id));

        $search = $service->handleApi('pool_list', $manager, [
            'district_id' => $district->id,
            'fiscal_year_id' => $fiscalYear->id,
            'q' => 'A0403002',
        ]);
        $this->assertTrue($search['ok']);
        $this->assertCount(1, $search['data']['candidates']);
        $this->assertTrue($search['data']['candidates'][0]['eligible']);
        $this->assertSame('A0403002', $search['data']['candidates'][0]['application_no']);
    }

    public function test_rbi_prefix_still_matches_the_same_application(): void
    {
        config()->set('database.connections.legacy.database', '');
        config()->set('database.connections.legacy_phase1.database', '');

        [$hub, $district, $fiscalYear, $manager] = $this->poolFixture('rbi-prefix');
        $onboarded = CfaSubmission::query()->create([
            'application_no' => 'RBI1770186398',
            'fiscal_year_id' => $fiscalYear->id,
            'district_id' => $district->id,
            'applicant_name' => 'Prefix Applicant',
            'phone' => '9811111111',
            'payload' => ['form_stage' => 'seed'],
        ]);
        $numeric = CfaSubmission::query()->create([
            'application_no' => '1770186398',
            'fiscal_year_id' => $fiscalYear->id,
            'district_id' => $district->id,
            'applicant_name' => 'Numeric Applicant',
            'phone' => '9822222222',
            'payload' => ['form_stage' => 'seed'],
        ]);
        $batch = OnboardingBatch::query()->create([
            'hub_id' => $hub->id,
            'district_id' => $district->id,
            'name' => 'Prefix batch',
            'target_size' => 1,
            'status' => 'locked',
            'created_by' => $manager->id,
        ]);
        OnboardingBatchCfa::query()->create([
            'onboarding_batch_id' => $batch->id,
            'cfa_submission_id' => $onboarded->id,
        ]);

        $conflict = app(HubBatchService::class)->onboardingConflictForCfa((int) $numeric->id);
        $this->assertNotNull($conflict);
        $this->assertSame('RBI1770186398', $conflict['application_no']);
    }

    public function test_all_pool_filter_searches_every_fiscal_year(): void
    {
        config()->set('database.connections.legacy.database', '');
        config()->set('database.connections.legacy_phase1.database', '');

        [$hub, $district, $currentYear, $manager] = $this->poolFixture('all-years');
        $olderYear = FiscalYear::query()->create([
            'code' => '2024-25-all',
            'name' => 'FY 2024-25',
            'starts_on' => '2024-04-01',
            'ends_on' => '2025-03-31',
            'is_active' => false,
        ]);
        CfaSubmission::query()->create([
            'application_no' => '664050186',
            'fiscal_year_id' => $olderYear->id,
            'district_id' => $district->id,
            'applicant_name' => 'Bhawana Joshi',
            'phone' => '8077215619',
            'payload' => ['form_stage' => 'early'],
        ]);

        $service = app(HubBatchService::class);
        $allYears = $service->handleApi('pool_list', $manager, [
            'district_id' => $district->id,
            'pool_filter' => 'all',
            'q' => '664050186',
        ]);
        $this->assertTrue($allYears['ok']);
        $this->assertCount(1, $allYears['data']['candidates']);
        $this->assertTrue($allYears['data']['candidates'][0]['eligible']);

        $currentYearOnly = $service->handleApi('pool_list', $manager, [
            'district_id' => $district->id,
            'fiscal_year_id' => $currentYear->id,
            'q' => '664050186',
        ]);
        $this->assertTrue($currentYearOnly['ok']);
        $this->assertSame('different_fiscal_year', $currentYearOnly['data']['candidates'][0]['eligibility_status']);
    }

    /** @return array{0: Hub, 1: District, 2: FiscalYear, 3: User} */
    private function poolFixture(string $suffix): array
    {
        $hub = Hub::query()->create(['slug' => $suffix.'-hub', 'name' => 'Hub '.$suffix, 'sort_order' => 1]);
        $district = District::query()->create([
            'hub_id' => $hub->id,
            'slug' => $suffix.'-district',
            'name' => 'District '.$suffix,
            'sort_order' => 1,
        ]);
        $fiscalYear = FiscalYear::query()->create([
            'code' => '2026-27-'.$suffix,
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

        return [$hub, $district, $fiscalYear, $manager];
    }
}
