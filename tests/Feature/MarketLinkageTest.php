<?php

namespace Tests\Feature;

use App\Models\District;
use App\Models\Hub;
use App\Models\MarketLinkagePartner;
use App\Models\MarketLinkageSubmission;
use App\Models\User;
use App\Services\AppSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MarketLinkageTest extends TestCase
{
    use RefreshDatabase;

    public function test_district_staff_can_submit_market_linkage_with_multiple_partners(): void
    {
        Storage::fake('local');

        $district = $this->createDistrict();
        $staff = User::factory()->create([
            'role' => 'district_staff',
            'district_id' => $district->id,
            'is_active' => true,
        ]);
        $cfaId = $this->seedOnboardedApplicant($district, 'APP-ML-001', 'Test Incubatee');

        $response = $this->actingAs($staff)->post(route('staff.market-linkages.store'), [
            'cfa_submission_id' => $cfaId,
            'partners' => [
                [
                    'partner_name' => 'Amazon India',
                    'linkage_mode' => 'online',
                    'linkage_date' => '2026-05-10',
                ],
                [
                    'partner_name' => 'Local Mandi',
                    'linkage_mode' => 'offline',
                    'linkage_date' => '2026-05-12',
                    'bill_document' => UploadedFile::fake()->create('bill.pdf', 50, 'application/pdf'),
                ],
            ],
        ]);

        $submission = MarketLinkageSubmission::query()->first();
        $this->assertNotNull($submission);
        $response->assertRedirect(route('staff.market-linkages.show', $submission));

        $this->assertDatabaseHas('market_linkage_submissions', [
            'cfa_submission_id' => $cfaId,
            'incubatee_name' => 'Test Incubatee',
            'district_id' => $district->id,
            'submitted_by_user_id' => $staff->id,
        ]);

        $this->assertSame(2, MarketLinkagePartner::query()->where('market_linkage_submission_id', $submission->id)->count());

        $withDoc = MarketLinkagePartner::query()->where('partner_name', 'Local Mandi')->first();
        $this->assertNotNull($withDoc);
        $this->assertTrue($withDoc->hasDocument());
    }

    public function test_same_incubatee_can_have_multiple_submissions(): void
    {
        $district = $this->createDistrict();
        $staff = User::factory()->create([
            'role' => 'district_staff',
            'district_id' => $district->id,
            'is_active' => true,
        ]);
        $cfaId = $this->seedOnboardedApplicant($district, 'APP-ML-002', 'Repeat Incubatee');

        $payload = [
            'cfa_submission_id' => $cfaId,
            'partners' => [
                [
                    'partner_name' => 'Partner A',
                    'linkage_mode' => 'online',
                    'linkage_date' => '2026-05-01',
                ],
            ],
        ];

        $this->actingAs($staff)->post(route('staff.market-linkages.store'), $payload)->assertRedirect();
        $this->actingAs($staff)->post(route('staff.market-linkages.store'), $payload)->assertRedirect();

        $this->assertSame(2, MarketLinkageSubmission::query()->where('cfa_submission_id', $cfaId)->count());
    }

    public function test_state_admin_can_view_dashboard(): void
    {
        $district = $this->createDistrict();
        $staff = User::factory()->create([
            'role' => 'district_staff',
            'district_id' => $district->id,
            'is_active' => true,
        ]);
        $admin = User::factory()->create(['role' => 'state_admin', 'is_active' => true]);
        $cfaId = $this->seedOnboardedApplicant($district, 'APP-ML-003', 'Admin View Test');

        $this->actingAs($staff)->post(route('staff.market-linkages.store'), [
            'cfa_submission_id' => $cfaId,
            'partners' => [
                ['partner_name' => 'Flipkart', 'linkage_mode' => 'online', 'linkage_date' => '2026-05-15'],
            ],
        ]);
        $this->actingAs($staff)->post(route('staff.market-linkages.store'), [
            'cfa_submission_id' => $cfaId,
            'partners' => [
                ['partner_name' => 'Amazon', 'linkage_mode' => 'offline', 'linkage_date' => '2026-05-16'],
            ],
        ]);

        $response = $this->actingAs($admin)->get(route('admin.market-linkages.dashboard'));
        $response->assertOk()
            ->assertSee('Unique partners', false)
            ->assertSee('Linked incubatees', false)
            ->assertSee('Admin View Test', false)
            ->assertSee('Flipkart', false)
            ->assertSee('Amazon', false)
            ->assertSee('>2<', false)
            ->assertSee('>1<', false);
    }

    public function test_services_create_page_shows_market_linkage_button(): void
    {
        app(AppSettingsService::class)->setMany(['service_module.enabled' => true]);

        $district = $this->createDistrict();
        $staff = User::factory()->create([
            'role' => 'district_staff',
            'district_id' => $district->id,
            'is_active' => true,
        ]);

        $this->seedOnboardedApplicant($district, 'APP-ML-004', 'Button Test');

        $this->actingAs($staff)
            ->get(route('staff.services.create'))
            ->assertOk()
            ->assertSee('Add market linkage');
    }

    public function test_market_linkage_create_page_shows_prior_partners_for_incubatee(): void
    {
        $district = $this->createDistrict();
        $staff = User::factory()->create([
            'role' => 'district_staff',
            'district_id' => $district->id,
            'is_active' => true,
        ]);
        $cfaId = $this->seedOnboardedApplicant($district, 'APP-ML-006', 'Create Prior Test');

        $this->actingAs($staff)->post(route('staff.market-linkages.store'), [
            'cfa_submission_id' => $cfaId,
            'partners' => [
                [
                    'partner_name' => 'ONDC',
                    'linkage_mode' => 'online',
                    'linkage_date' => '2026-05-01',
                ],
            ],
        ]);

        $this->actingAs($staff)
            ->get(route('staff.market-linkages.create', ['cfa_submission_id' => $cfaId]))
            ->assertOk()
            ->assertSee('Previously recorded')
            ->assertSee('Recorded earlier')
            ->assertSee('ONDC')
            ->assertSee('data-field="partner_name"', false);
    }

    public function test_services_create_page_includes_prior_market_linkage_data(): void
    {
        app(AppSettingsService::class)->setMany(['service_module.enabled' => true]);

        $district = $this->createDistrict();
        $staff = User::factory()->create([
            'role' => 'district_staff',
            'district_id' => $district->id,
            'is_active' => true,
        ]);
        $cfaId = $this->seedOnboardedApplicant($district, 'APP-ML-005', 'Prior Panel Test');

        $this->actingAs($staff)->post(route('staff.market-linkages.store'), [
            'cfa_submission_id' => $cfaId,
            'partners' => [
                [
                    'partner_name' => 'Meesho',
                    'linkage_mode' => 'online',
                    'linkage_date' => '2026-05-18',
                ],
            ],
        ]);

        $this->actingAs($staff)
            ->get(route('staff.services.create', ['cfa_submission_id' => $cfaId]))
            ->assertOk()
            ->assertSee('Market linkage', false)
            ->assertSee('Meesho')
            ->assertSee('PRIOR_MARKET_LINKAGE');
    }

    private function seedOnboardedApplicant(District $district, string $applicationNo, string $name): int
    {
        $cfaId = (int) DB::table('cfa_submissions')->insertGetId([
            'district_id' => $district->id,
            'application_no' => $applicationNo,
            'applicant_name' => $name,
            'phone' => '9876543210',
            'source' => 'phase3',
            'payload' => json_encode(['gender' => 'female']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $batchId = (int) DB::table('onboarding_batches')->insertGetId([
            'hub_id' => $district->hub_id,
            'district_id' => $district->id,
            'name' => $district->name.'-batch',
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

    private function createDistrict(string $slug = 'dehradun', string $name = 'Dehradun'): District
    {
        $hub = Hub::query()->create([
            'name' => 'Test Hub',
            'slug' => 'test-hub-'.$slug,
            'is_active' => true,
        ]);

        return District::query()->create([
            'hub_id' => $hub->id,
            'name' => $name,
            'slug' => $slug,
            'is_active' => true,
        ]);
    }
}
