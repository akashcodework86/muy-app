<?php

namespace Tests\Feature;

use App\Models\District;
use App\Models\DistrictServiceSpoc;
use App\Models\Hub;
use App\Models\MarketLinkagePartner;
use App\Models\MarketLinkageSubmission;
use App\Models\ServiceCase;
use App\Models\User;
use App\Services\MarketLinkageWorkflowService;
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
                    'link_url' => 'https://www.amazon.in/seller',
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
        $response->assertRedirect(route('staff.services.index', ['status' => ServiceCase::STATUS_PENDING_APPROVAL]));

        $this->assertDatabaseHas('market_linkage_submissions', [
            'cfa_submission_id' => $cfaId,
            'incubatee_name' => 'Test Incubatee',
            'district_id' => $district->id,
            'submitted_by_user_id' => $staff->id,
            'status' => ServiceCase::STATUS_PENDING_APPROVAL,
        ]);

        $this->assertSame(2, MarketLinkagePartner::query()->where('market_linkage_submission_id', $submission->id)->count());

        $withDoc = MarketLinkagePartner::query()->where('partner_name', 'Local Mandi')->first();
        $this->assertNotNull($withDoc);
        $this->assertTrue($withDoc->hasDocument());

        $online = MarketLinkagePartner::query()->where('partner_name', 'Amazon India')->first();
        $this->assertNotNull($online);
        $this->assertSame('https://www.amazon.in/seller', $online->link_url);
    }

    public function test_online_partner_requires_link_url(): void
    {
        $district = $this->createDistrict();
        $staff = User::factory()->create([
            'role' => 'district_staff',
            'district_id' => $district->id,
            'is_active' => true,
        ]);
        $cfaId = $this->seedOnboardedApplicant($district, 'APP-ML-URL-1', 'URL Test');

        $this->actingAs($staff)
            ->post(route('staff.market-linkages.store'), [
                'cfa_submission_id' => $cfaId,
                'partners' => [
                    [
                        'partner_name' => 'Online Shop',
                        'linkage_mode' => 'online',
                        'linkage_date' => '2026-05-10',
                    ],
                ],
            ])
            ->assertSessionHasErrors('partners.0.link_url');
    }

    public function test_online_partner_accepts_plain_link_or_domain_url(): void
    {
        $district = $this->createDistrict();
        $staff = User::factory()->create([
            'role' => 'district_staff',
            'district_id' => $district->id,
            'is_active' => true,
        ]);
        $cfaId = $this->seedOnboardedApplicant($district, 'APP-ML-URL-3', 'Flexible Link Test');

        $this->actingAs($staff)
            ->post(route('staff.market-linkages.store'), [
                'cfa_submission_id' => $cfaId,
                'partners' => [
                    [
                        'partner_name' => 'Text Link Shop',
                        'linkage_mode' => 'online',
                        'linkage_date' => '2026-05-12',
                        'link' => 'ONDC seller dashboard listing',
                    ],
                    [
                        'partner_name' => 'Domain Shop',
                        'linkage_mode' => 'online',
                        'linkage_date' => '2026-05-13',
                        'link_url' => 'www.example.com/market',
                    ],
                ],
            ])
            ->assertRedirect();

        $text = MarketLinkagePartner::query()->where('partner_name', 'Text Link Shop')->first();
        $domain = MarketLinkagePartner::query()->where('partner_name', 'Domain Shop')->first();
        $this->assertNotNull($text);
        $this->assertSame('ONDC seller dashboard listing', $text->link_url);
        $this->assertNull(MarketLinkagePartner::clickableHref($text->link_url));
        $this->assertNotNull($domain);
        $this->assertSame('https://www.example.com/market', $domain->link_url);
    }

    public function test_offline_partner_link_url_is_optional(): void
    {
        $district = $this->createDistrict();
        $staff = User::factory()->create([
            'role' => 'district_staff',
            'district_id' => $district->id,
            'is_active' => true,
        ]);
        $cfaId = $this->seedOnboardedApplicant($district, 'APP-ML-URL-2', 'Offline URL Test');

        $this->actingAs($staff)
            ->post(route('staff.market-linkages.store'), [
                'cfa_submission_id' => $cfaId,
                'partners' => [
                    [
                        'partner_name' => 'Local Bazaar',
                        'linkage_mode' => 'offline',
                        'linkage_date' => '2026-05-11',
                    ],
                ],
            ])
            ->assertRedirect();

        $partner = MarketLinkagePartner::query()->first();
        $this->assertNotNull($partner);
        $this->assertNull($partner->link_url);
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
                    'link_url' => 'https://example.com/partner-a',
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
                ['partner_name' => 'Flipkart', 'linkage_mode' => 'online', 'linkage_date' => '2026-05-15', 'link_url' => 'https://www.flipkart.com/seller'],
            ],
        ]);
        $this->actingAs($staff)->post(route('staff.market-linkages.store'), [
            'cfa_submission_id' => $cfaId,
            'partners' => [
                ['partner_name' => 'Amazon', 'linkage_mode' => 'offline', 'linkage_date' => '2026-05-16'],
            ],
        ]);
        MarketLinkageSubmission::query()->update([
            'status' => ServiceCase::STATUS_APPROVED,
            'approved_at' => now(),
            'approved_by' => $staff->id,
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

    public function test_new_partner_name_is_saved_to_catalog_for_future_dropdown(): void
    {
        $district = $this->createDistrict();
        $staff = User::factory()->create([
            'role' => 'district_staff',
            'district_id' => $district->id,
            'is_active' => true,
        ]);
        $cfaId = $this->seedOnboardedApplicant($district, 'APP-ML-CAT-1', 'Catalog Test Incubatee');
        $newPartner = 'Brand New Partner XYZ 2026';

        $this->actingAs($staff)->post(route('staff.market-linkages.store'), [
            'cfa_submission_id' => $cfaId,
            'partners' => [
                [
                    'partner_name' => $newPartner,
                    'linkage_mode' => 'online',
                    'linkage_date' => '2026-05-20',
                    'link_url' => 'https://example.com/new-partner',
                ],
            ],
        ])->assertRedirect();

        $this->assertDatabaseHas('market_linkage_partner_names', [
            'name' => $newPartner,
        ]);

        $options = app(\App\Services\MarketLinkagePartnerCatalogService::class)->options();
        $this->assertContains($newPartner, $options);
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
                    'link_url' => 'https://ondc.org/example',
                ],
            ],
        ]);
        $this->approveAllMarketLinkages($staff);

        $this->actingAs($staff)
            ->get(route('staff.market-linkages.create', ['cfa_submission_id' => $cfaId]))
            ->assertOk()
            ->assertSee('Previously recorded')
            ->assertSee('Recorded earlier')
            ->assertSee('ONDC')
            ->assertSee('ml-partner-combo', false)
            ->assertSee('PARTNER_NAME_OPTIONS', false);
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
                    'link_url' => 'https://www.meesho.com/seller',
                ],
            ],
        ]);
        $this->approveAllMarketLinkages($staff);

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

    public function test_spoc_can_approve_market_linkage_and_it_appears_on_my_services(): void
    {
        app(AppSettingsService::class)->setMany(['service_module.enabled' => true]);

        $district = $this->createDistrict();
        $staff = User::factory()->create([
            'role' => 'district_staff',
            'district_id' => $district->id,
            'is_active' => true,
        ]);
        $spoc = User::factory()->create(['role' => 'state_staff', 'is_active' => true]);
        DistrictServiceSpoc::query()->create([
            'district_id' => $district->id,
            'state_staff_user_id' => $spoc->id,
            'assigned_by' => $staff->id,
            'assigned_at' => now(),
        ]);
        $cfaId = $this->seedOnboardedApplicant($district, 'APP-ML-SPOC-1', 'SPOC Flow Test');

        $this->actingAs($staff)->post(route('staff.market-linkages.store'), [
            'cfa_submission_id' => $cfaId,
            'partners' => [
                [
                    'partner_name' => 'Approved Shop',
                    'linkage_mode' => 'online',
                    'linkage_date' => '2026-05-20',
                    'link_url' => 'https://example.com/shop',
                ],
            ],
        ]);

        $submission = MarketLinkageSubmission::query()->first();
        $this->assertNotNull($submission);
        $this->assertSame(ServiceCase::STATUS_PENDING_APPROVAL, $submission->status);
        $this->assertSame((int) $spoc->id, (int) $submission->spoc_user_id);

        $this->actingAs($spoc)
            ->post(route('spoc.market-linkages.approve', $submission))
            ->assertRedirect();

        $submission->refresh();
        $this->assertSame(ServiceCase::STATUS_APPROVED, $submission->status);

        $this->actingAs($staff)
            ->get(route('staff.services.index'))
            ->assertOk()
            ->assertSee('SPOC Flow Test')
            ->assertSee('Market Linkage')
            ->assertSee('approved', false);
    }

    public function test_district_staff_can_delete_own_pending_market_linkage(): void
    {
        $district = $this->createDistrict();
        $staff = User::factory()->create([
            'role' => 'district_staff',
            'district_id' => $district->id,
            'is_active' => true,
        ]);
        $otherStaff = User::factory()->create([
            'role' => 'district_staff',
            'district_id' => $district->id,
            'is_active' => true,
        ]);
        $cfaId = $this->seedOnboardedApplicant($district, 'APP-ML-DEL-1', 'Delete Test Incubatee');

        $this->actingAs($staff)->post(route('staff.market-linkages.store'), [
            'cfa_submission_id' => $cfaId,
            'partners' => [
                ['partner_name' => 'To Delete Shop', 'linkage_mode' => 'online', 'linkage_date' => '2026-05-20', 'link_url' => 'https://example.com/del'],
            ],
        ]);

        $submission = MarketLinkageSubmission::query()->firstOrFail();

        $this->actingAs($otherStaff)
            ->delete(route('staff.market-linkages.destroy', $submission))
            ->assertForbidden();

        $this->actingAs($staff)
            ->delete(route('staff.market-linkages.destroy', $submission))
            ->assertRedirect(route('staff.services.index'));

        $this->assertDatabaseMissing('market_linkage_submissions', ['id' => $submission->id]);
        $this->assertSame(0, MarketLinkagePartner::query()->where('market_linkage_submission_id', $submission->id)->count());
    }

    public function test_hub_admin_can_view_market_linkage_dashboard_with_district_breakdown(): void
    {
        [$hub, $district, $hubAdmin] = $this->seedHubFixtures('kumaon', 'Almora');
        $staff = User::factory()->create([
            'role' => 'district_staff',
            'hub_id' => $hub->id,
            'district_id' => $district->id,
            'is_active' => true,
        ]);
        $cfaId = $this->seedOnboardedApplicant($district, 'APP-ML-HUB-1', 'Hub View Test');

        $this->actingAs($staff)->post(route('staff.market-linkages.store'), [
            'cfa_submission_id' => $cfaId,
            'partners' => [
                ['partner_name' => 'Meesho', 'linkage_mode' => 'online', 'linkage_date' => '2026-05-15', 'link_url' => 'https://www.meesho.com/seller'],
                ['partner_name' => 'Local Buyer', 'linkage_mode' => 'offline', 'linkage_date' => '2026-05-16'],
            ],
        ]);
        $this->approveAllMarketLinkages($staff);

        $this->actingAs($hubAdmin)
            ->get(route('hub.market-linkages.dashboard'))
            ->assertOk()
            ->assertSee('District-wise linked incubatees', false)
            ->assertSee('Almora', false)
            ->assertSee('Hub View Test', false)
            ->assertSee('Meesho', false)
            ->assertSee('Local Buyer', false)
            ->assertSee('>2<', false);
    }

    public function test_hub_admin_dashboard_is_scoped_to_own_hub(): void
    {
        [$hubA, $districtA, $hubAdminA] = $this->seedHubFixtures('hub-a', 'District A');
        [$hubB, $districtB] = $this->seedHubFixtures('hub-b', 'District B');

        $staffA = User::factory()->create([
            'role' => 'district_staff',
            'hub_id' => $hubA->id,
            'district_id' => $districtA->id,
            'is_active' => true,
        ]);
        $staffB = User::factory()->create([
            'role' => 'district_staff',
            'hub_id' => $hubB->id,
            'district_id' => $districtB->id,
            'is_active' => true,
        ]);

        $cfaA = $this->seedOnboardedApplicant($districtA, 'APP-ML-HUB-A', 'Incubatee A');
        $cfaB = $this->seedOnboardedApplicant($districtB, 'APP-ML-HUB-B', 'Incubatee B');

        $this->actingAs($staffA)->post(route('staff.market-linkages.store'), [
            'cfa_submission_id' => $cfaA,
            'partners' => [
                ['partner_name' => 'Partner A', 'linkage_mode' => 'online', 'linkage_date' => '2026-05-10', 'link_url' => 'https://example.com/a'],
            ],
        ]);
        $this->actingAs($staffB)->post(route('staff.market-linkages.store'), [
            'cfa_submission_id' => $cfaB,
            'partners' => [
                ['partner_name' => 'Partner B', 'linkage_mode' => 'online', 'linkage_date' => '2026-05-11', 'link_url' => 'https://example.com/b'],
            ],
        ]);
        $this->approveAllMarketLinkages($staffA);

        $this->actingAs($hubAdminA)
            ->get(route('hub.market-linkages.dashboard'))
            ->assertOk()
            ->assertSee('Incubatee A', false)
            ->assertSee('Partner A', false)
            ->assertSee('District A', false)
            ->assertDontSee('Incubatee B')
            ->assertDontSee('Partner B')
            ->assertDontSee('District B');
    }

    public function test_pending_market_linkage_not_counted_on_dashboard_until_approved(): void
    {
        $district = $this->createDistrict();
        $staff = User::factory()->create([
            'role' => 'district_staff',
            'district_id' => $district->id,
            'is_active' => true,
        ]);
        $cfaId = $this->seedOnboardedApplicant($district, 'APP-ML-PEND-1', 'Pending Dash Test');

        $this->actingAs($staff)->post(route('staff.market-linkages.store'), [
            'cfa_submission_id' => $cfaId,
            'partners' => [
                ['partner_name' => 'Pending Only', 'linkage_mode' => 'online', 'linkage_date' => '2026-05-20', 'link_url' => 'https://example.com/p'],
            ],
        ]);

        $this->actingAs($staff)
            ->get(route('staff.market-linkages.dashboard'))
            ->assertOk()
            ->assertDontSee('Pending Only');

        $submission = MarketLinkageSubmission::query()->firstOrFail();
        app(MarketLinkageWorkflowService::class)->approve($submission, User::factory()->create(['role' => 'state_staff', 'is_active' => true]));

        $this->actingAs($staff)
            ->get(route('staff.market-linkages.dashboard'))
            ->assertOk()
            ->assertSee('Pending Only');
    }

    private function approveAllMarketLinkages(User $approver): void
    {
        MarketLinkageSubmission::query()->update([
            'status' => ServiceCase::STATUS_APPROVED,
            'approved_at' => now(),
            'approved_by' => $approver->id,
        ]);
    }

    /**
     * @return array{0: Hub, 1: District, 2: User}
     */
    private function seedHubFixtures(string $hubSlug = 'kumaon', string $districtName = 'Almora'): array
    {
        $hub = Hub::query()->create([
            'slug' => $hubSlug,
            'name' => ucfirst(str_replace('-', ' ', $hubSlug)).' Region',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $district = District::query()->create([
            'hub_id' => $hub->id,
            'slug' => strtolower(str_replace(' ', '-', $districtName)),
            'name' => $districtName,
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $admin = User::factory()->create([
            'role' => 'hub_admin',
            'hub_id' => $hub->id,
            'is_active' => true,
        ]);

        return [$hub, $district, $admin];
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
