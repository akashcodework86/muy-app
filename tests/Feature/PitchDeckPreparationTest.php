<?php

namespace Tests\Feature;

use App\Models\Deliverable;
use App\Models\District;
use App\Models\FiscalYear;
use App\Models\Hub;
use App\Models\OnboardingBatch;
use App\Models\OnboardingBatchCfa;
use App\Models\PitchDeckPreparation;
use App\Models\Service;
use App\Models\ServiceCase;
use App\Models\ServiceCategory;
use App\Models\User;
use App\Support\PitchDeckCombinedDeliverablesSupport;
use App\Services\Deliverables\ProgramDeliverablesAchievementBreakdownService;
use App\Services\Deliverables\ProgramDeliverablesFilter;
use App\Services\Deliverables\ProgramDeliverablesScope;
use App\Services\MisMonthlyTargetIndicatorBootstrapService;
use App\Services\PitchDeckIncubateeCatalogService;
use App\Services\ProgramDeliverablesReportService;
use App\Services\StateMonthlyTargetIndicatorBootstrapService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PitchDeckPreparationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(MisMonthlyTargetIndicatorBootstrapService::class)->ensureDeliverables();
        app(StateMonthlyTargetIndicatorBootstrapService::class)->ensureDeliverables();
    }

    public function test_anjali_can_open_form_and_store_entry(): void
    {
        Storage::fake();

        [$district, , $cfaId] = $this->createOnboardedCfa();

        $anjali = User::factory()->create([
            'role' => 'state_staff',
            'name' => 'Anjali Sood',
            'email' => 'anjali.sood@pwc.com',
            'is_active' => true,
        ]);

        $this->actingAs($anjali)
            ->get(route('spoc.pitch-deck-preparations.create'))
            ->assertOk()
            ->assertSee('8.3');

        $this->actingAs($anjali)
            ->post(route('spoc.pitch-deck-preparations.store'), [
                'incubatee_source' => 'cfa',
                'cfa_submission_id' => $cfaId,
                'prepared_on' => '2026-05-20',
                'prepared_for' => 'Investor meet',
                'support_mode' => 'virtual',
                'remarks' => 'First draft',
                'deck_file' => UploadedFile::fake()->create('deck.pdf', 200, 'application/pdf'),
            ])
            ->assertRedirect(route('spoc.pitch-deck-preparations.dashboard'))
            ->assertSessionHas('status');

        $this->assertDatabaseCount('pitch_deck_preparations', 1);
        $row = PitchDeckPreparation::query()->first();
        $this->assertSame($cfaId, (int) $row->cfa_submission_id);
        $this->assertSame($district->id, (int) $row->district_id);
        $this->assertSame('Investor meet', $row->prepared_for);
        $this->assertTrue(Storage::exists((string) $row->deck_file_path));
    }

    public function test_duplicate_incubatee_is_blocked(): void
    {
        Storage::fake();

        [, , $cfaId] = $this->createOnboardedCfa();

        $anjali = User::factory()->create([
            'role' => 'state_staff',
            'name' => 'Anjali Sood',
            'is_active' => true,
        ]);

        PitchDeckPreparation::query()->create([
            'cfa_submission_id' => $cfaId,
            'district_id' => District::query()->first()->id,
            'incubatee_name' => 'Test Incubatee',
            'application_no' => '40803999',
            'entered_by_user_id' => $anjali->id,
            'entered_by_name' => $anjali->name,
            'prepared_on' => '2026-05-01',
            'deck_file_disk' => 'local',
            'deck_file_path' => 'pitch-deck-preparations/test.pdf',
            'deck_file_name' => 'test.pdf',
        ]);

        $this->actingAs($anjali)
            ->post(route('spoc.pitch-deck-preparations.store'), [
                'incubatee_source' => 'cfa',
                'cfa_submission_id' => $cfaId,
                'prepared_on' => '2026-05-21',
                'deck_file' => UploadedFile::fake()->create('deck2.pdf', 100, 'application/pdf'),
            ])
            ->assertSessionHasErrors('incubatee_key');

        $this->assertDatabaseCount('pitch_deck_preparations', 1);
    }

    public function test_state_admin_can_view_dashboard_read_only(): void
    {
        $admin = User::factory()->create([
            'role' => 'state_admin',
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.pitch-deck-preparations.dashboard'))
            ->assertOk()
            ->assertSee('8.3')
            ->assertSee('Onboarding');

        $this->actingAs($admin)
            ->get(route('spoc.pitch-deck-preparations.create'))
            ->assertForbidden();
    }

    public function test_deck_inline_preview_is_available_for_pdf(): void
    {
        Storage::fake();

        [$district, , $cfaId] = $this->createOnboardedCfa();

        $anjali = User::factory()->create([
            'role' => 'state_staff',
            'name' => 'Anjali Sood',
            'is_active' => true,
        ]);

        $this->actingAs($anjali)
            ->post(route('spoc.pitch-deck-preparations.store'), [
                'incubatee_source' => 'cfa',
                'cfa_submission_id' => $cfaId,
                'prepared_on' => '2026-05-20',
                'deck_file' => UploadedFile::fake()->create('deck.pdf', 100, 'application/pdf'),
            ]);

        $entry = PitchDeckPreparation::query()->firstOrFail();

        $this->actingAs($anjali)
            ->get(route('spoc.pitch-deck-preparations.deck', ['pitchDeckPreparation' => $entry, 'inline' => 1]))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->actingAs($anjali)
            ->get(route('spoc.pitch-deck-preparations.show', $entry))
            ->assertOk()
            ->assertSee('View deck')
            ->assertSee('js-pdp-deck-preview', false)
            ->assertSee('pdpDeckModal', false);
    }

    public function test_dashboard_and_detail_show_onboarding_status(): void
    {
        Storage::fake();

        [$district, , $cfaId] = $this->createOnboardedCfa();

        $anjali = User::factory()->create([
            'role' => 'state_staff',
            'name' => 'Anjali Sood',
            'is_active' => true,
        ]);

        $this->actingAs($anjali)
            ->post(route('spoc.pitch-deck-preparations.store'), [
                'incubatee_source' => 'cfa',
                'cfa_submission_id' => $cfaId,
                'prepared_on' => '2026-05-20',
                'deck_file' => UploadedFile::fake()->create('deck.pdf', 100, 'application/pdf'),
            ]);

        $entry = PitchDeckPreparation::query()->firstOrFail();

        $this->actingAs($anjali)
            ->get(route('spoc.pitch-deck-preparations.dashboard'))
            ->assertOk()
            ->assertSee('Onboarded')
            ->assertSee('Full detail view')
            ->assertSee('View deck');

        $this->actingAs($anjali)
            ->get(route('spoc.pitch-deck-preparations.show', $entry))
            ->assertOk()
            ->assertSee('Incubatee details')
            ->assertSee('Onboarded')
            ->assertSee('Batch PDP');
    }

    public function test_incubatee_search_includes_non_onboarded_with_status(): void
    {
        $hub = Hub::query()->create(['slug' => 'pdp-search-hub', 'name' => 'Hub', 'sort_order' => 1]);
        $district = District::query()->create([
            'hub_id' => $hub->id,
            'slug' => 'pdp-search-dist',
            'name' => 'Search District',
            'sort_order' => 1,
        ]);

        $onboardedId = (int) DB::table('cfa_submissions')->insertGetId([
            'district_id' => $district->id,
            'application_no' => '40804001',
            'applicant_name' => 'Onboarded Applicant',
            'phone' => '9000000101',
            'payload' => json_encode(['form_stage' => 'seed']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $notOnboardedId = (int) DB::table('cfa_submissions')->insertGetId([
            'district_id' => $district->id,
            'application_no' => '40804002',
            'applicant_name' => 'Pending Applicant',
            'phone' => '9000000102',
            'payload' => json_encode(['form_stage' => 'seed']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $batch = OnboardingBatch::query()->create([
            'hub_id' => $hub->id,
            'district_id' => $district->id,
            'name' => 'Batch Search',
            'target_size' => 80,
            'status' => 'locked',
            'locked_at' => now(),
            'onboarding_date' => '2026-05-01',
        ]);

        OnboardingBatchCfa::query()->create([
            'onboarding_batch_id' => $batch->id,
            'cfa_submission_id' => $onboardedId,
        ]);

        $anjali = User::factory()->create([
            'role' => 'state_staff',
            'name' => 'Anjali Sood',
            'is_active' => true,
        ]);

        $response = $this->actingAs($anjali)
            ->getJson(route('spoc.pitch-deck-preparations.incubatees.search', ['q' => '4080400']))
            ->assertOk();

        $rows = collect($response->json('results'));
        $onboarded = $rows->firstWhere('cfa_submission_id', $onboardedId);
        $pending = $rows->firstWhere('cfa_submission_id', $notOnboardedId);

        $this->assertNotNull($onboarded);
        $this->assertTrue($onboarded['is_onboarded']);
        $this->assertSame('Onboarded', $onboarded['onboarding_status']);
        $this->assertNotNull($pending);
        $this->assertFalse($pending['is_onboarded']);
        $this->assertSame('Not onboarded', $pending['onboarding_status']);

        $catalog = app(PitchDeckIncubateeCatalogService::class)->search('Pending');
        $pending = collect($catalog)->firstWhere('cfa_submission_id', $notOnboardedId);
        $this->assertNotNull($pending);
        $this->assertFalse($pending['is_onboarded']);
    }

    public function test_can_store_entry_for_non_onboarded_applicant(): void
    {
        Storage::fake();

        $hub = Hub::query()->create(['slug' => 'pdp-nb-hub', 'name' => 'Hub', 'sort_order' => 1]);
        $district = District::query()->create([
            'hub_id' => $hub->id,
            'slug' => 'pdp-nb-dist',
            'name' => 'NB District',
            'sort_order' => 1,
        ]);

        $cfaId = (int) DB::table('cfa_submissions')->insertGetId([
            'district_id' => $district->id,
            'application_no' => '40804003',
            'applicant_name' => 'Not Onboarded Yet',
            'phone' => '9000000103',
            'payload' => json_encode(['form_stage' => 'seed']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $anjali = User::factory()->create([
            'role' => 'state_staff',
            'name' => 'Anjali Sood',
            'is_active' => true,
        ]);

        $this->actingAs($anjali)
            ->post(route('spoc.pitch-deck-preparations.store'), [
                'incubatee_source' => 'cfa',
                'cfa_submission_id' => $cfaId,
                'prepared_on' => '2026-05-22',
                'deck_file' => UploadedFile::fake()->create('deck.pdf', 100, 'application/pdf'),
            ])
            ->assertRedirect(route('spoc.pitch-deck-preparations.dashboard'))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('pitch_deck_preparations', [
            'cfa_submission_id' => $cfaId,
            'incubatee_name' => 'Not Onboarded Yet',
        ]);
    }

    public function test_deliverables_count_combines_services_and_state_team(): void
    {
        Storage::fake();

        [$district, , $cfaId] = $this->createOnboardedCfa();
        $pitchDeckService = $this->createPitchDeckService();

        ServiceCase::query()->create([
            'cfa_submission_id' => $cfaId,
            'service_id' => $pitchDeckService->id,
            'status' => ServiceCase::STATUS_APPROVED,
            'approved_at' => '2026-05-18 10:00:00',
        ]);

        $anjali = User::factory()->create([
            'role' => 'state_staff',
            'name' => 'Anjali Sood',
            'is_active' => true,
        ]);

        $this->actingAs($anjali)
            ->post(route('spoc.pitch-deck-preparations.store'), [
                'incubatee_source' => 'cfa',
                'cfa_submission_id' => $cfaId,
                'prepared_on' => '2026-05-20',
                'deck_file' => UploadedFile::fake()->create('deck.pdf', 100, 'application/pdf'),
            ]);

        $fy = FiscalYear::query()->firstOrCreate(
            ['code' => '2026-27'],
            [
                'name' => 'FY 2026-27',
                'starts_on' => '2026-04-01',
                'ends_on' => '2027-03-31',
                'is_active' => true,
            ]
        );

        Deliverable::query()->where('code', 'pitch_deck_prep')->firstOrFail();

        $filter = new ProgramDeliverablesFilter($fy->id, $district->id, null, null, null, null);
        $scope = ProgramDeliverablesScope::forUser(User::factory()->make(['role' => 'state_admin']));
        $report = app(ProgramDeliverablesReportService::class)->build($filter, $scope);
        $row = collect($report['rows'])->firstWhere('serial', '8.3');

        $this->assertNotNull($row);
        $this->assertSame(2, $row['achievement']);

        $breakdown = app(ProgramDeliverablesAchievementBreakdownService::class)->build($filter, $scope, '8.3');

        $this->assertSame(2, $breakdown['total']);
        $this->assertCount(2, $breakdown['by_service']);
        $this->assertSame(
            PitchDeckCombinedDeliverablesSupport::LABEL_SERVICES,
            $breakdown['by_service'][0]['service'] ?? null,
        );
        $this->assertSame(1, $breakdown['by_service'][0]['count'] ?? null);
        $this->assertSame(
            PitchDeckCombinedDeliverablesSupport::LABEL_STATE_TEAM,
            $breakdown['by_service'][1]['service'] ?? null,
        );
        $this->assertSame(1, $breakdown['by_service'][1]['count'] ?? null);
        $this->assertGreaterThanOrEqual(2, count($breakdown['records']));
        $serviceLabels = array_column($breakdown['records'], 'service');
        $this->assertContains(PitchDeckCombinedDeliverablesSupport::LABEL_SERVICES, $serviceLabels);
        $this->assertContains(PitchDeckCombinedDeliverablesSupport::LABEL_STATE_TEAM, $serviceLabels);
    }

    public function test_state_admin_dashboard_shows_unified_sources(): void
    {
        Storage::fake();

        [$district, , $cfaId] = $this->createOnboardedCfa();
        $service = $this->createPitchDeckService();

        ServiceCase::query()->create([
            'cfa_submission_id' => $cfaId,
            'service_id' => $service->id,
            'status' => ServiceCase::STATUS_APPROVED,
            'approved_at' => '2026-05-18 10:00:00',
        ]);

        $anjali = User::factory()->create([
            'role' => 'state_staff',
            'name' => 'Anjali Sood',
            'is_active' => true,
        ]);

        $this->actingAs($anjali)
            ->post(route('spoc.pitch-deck-preparations.store'), [
                'incubatee_source' => 'cfa',
                'cfa_submission_id' => $cfaId,
                'prepared_on' => '2026-05-20',
                'deck_file' => UploadedFile::fake()->create('deck.pdf', 100, 'application/pdf'),
            ]);

        $admin = User::factory()->create([
            'role' => 'state_admin',
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.pitch-deck-preparations.dashboard'))
            ->assertOk()
            ->assertSee('District staff')
            ->assertSee('State team')
            ->assertSee(PitchDeckCombinedDeliverablesSupport::LABEL_SERVICES, false)
            ->assertSee(PitchDeckCombinedDeliverablesSupport::LABEL_STATE_TEAM, false)
            ->assertSee('Service case')
            ->assertSee('Full detail view');
    }

    /**
     * @return array{0: District, 1: OnboardingBatch, 2: int}
     */
    private function createOnboardedCfa(): array
    {
        $hub = Hub::query()->create(['slug' => 'pdp-hub', 'name' => 'Hub', 'sort_order' => 1]);
        $district = District::query()->create([
            'hub_id' => $hub->id,
            'slug' => 'pdp-dist',
            'name' => 'PDP District',
            'sort_order' => 1,
        ]);

        $cfaId = (int) DB::table('cfa_submissions')->insertGetId([
            'district_id' => $district->id,
            'application_no' => '40803999',
            'applicant_name' => 'Test Incubatee',
            'phone' => '9000000099',
            'payload' => json_encode(['form_stage' => 'seed']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $batch = OnboardingBatch::query()->create([
            'hub_id' => $hub->id,
            'district_id' => $district->id,
            'name' => 'Batch PDP',
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

    private function createConvergenceService(): Service
    {
        $category = ServiceCategory::query()->create([
            'slug' => 'convergence-with-line-departments',
            'name' => 'Schematic Convergence',
            'sort_order' => 99,
        ]);

        return Service::query()->create([
            'service_category_id' => $category->id,
            'code' => 'p_m_e_g_p',
            'name' => 'PMEGP',
            'sort_order' => 1,
            'is_active' => true,
        ]);
    }

    private function createPitchDeckService(): Service
    {
        $child = ServiceCategory::query()->create(['slug' => 'pdp-inc', 'name' => 'Inc', 'sort_order' => 1]);
        $mis = Deliverable::query()->where('code', 'pitch_deck_prep')->firstOrFail();

        return Service::query()->create([
            'service_category_id' => $child->id,
            'deliverable_id' => $mis->id,
            'code' => 'pitch_deck',
            'name' => 'Pitch Deck',
            'sort_order' => 1,
            'is_active' => true,
        ]);
    }
}
