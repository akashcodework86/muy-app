<?php

namespace Tests\Feature;

use App\Models\AccelerationServiceSession;
use App\Models\FiscalYear;
use App\Models\User;
use App\Services\AccelerationServicesIncubateeService;
use App\Services\Deliverables\ProgramDeliverablesFilter;
use App\Services\Deliverables\ProgramDeliverablesScope;
use App\Services\MisMonthlyTargetIndicatorBootstrapService;
use App\Services\ProgramDeliverablesReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AccelerationServicesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(MisMonthlyTargetIndicatorBootstrapService::class)->ensureDeliverables();
        $this->artisan('migrate', ['--path' => 'database/migrations/2026_07_06_120000_create_acceleration_service_tables.php']);
        $this->artisan('migrate', ['--path' => 'database/migrations/2026_07_10_120000_add_payload_to_acceleration_service_items.php']);
        $this->artisan('migrate', ['--path' => 'database/migrations/2026_07_15_120000_add_is_draft_to_acceleration_service_sessions.php']);
        $this->artisan('migrate', ['--path' => 'database/migrations/2026_07_17_120000_add_approval_workflow_to_acceleration_services.php']);
    }

    public function test_ankur_can_open_dashboard_and_store_session(): void
    {
        $ankur = User::factory()->create([
            'role' => 'state_staff',
            'email' => 'ankur.rawat@pwc.com',
            'is_active' => true,
        ]);

        $fy = FiscalYear::query()->create([
            'code' => '2026-27',
            'name' => 'FY 2026-27',
            'starts_on' => '2026-04-01',
            'ends_on' => '2027-03-31',
            'is_active' => true,
        ]);

        $this->mock(AccelerationServicesIncubateeService::class, function ($mock) use ($fy): void {
            $mock->shouldReceive('findPhase1Applicant')->with(101)->andReturn([
                'legacy_phase1_application_id' => 101,
                'incubatee_key' => 'p1:101',
                'applicant_name' => 'Phase One Applicant',
                'application_no' => 'APP-101',
                'phone' => '9876543210',
                'district_name' => 'Almora',
                'onboard_label' => 'Onboarded',
            ]);
            $mock->shouldReceive('resolveFiscalYearIdForDate')->andReturn((int) $fy->id);
            $mock->shouldReceive('shouldCountFor72')->andReturn(true);
        });

        $this->actingAs($ankur)
            ->get(route('spoc.acceleration-services.create'))
            ->assertOk()
            ->assertSee('7.2');

        $this->actingAs($ankur)
            ->get(route('spoc.acceleration-services.dashboard'))
            ->assertOk()
            ->assertSee('Dashboard');

        $this->actingAs($ankur)
            ->post(route('spoc.acceleration-services.store'), [
                'service_date' => '2026-05-20',
                'legacy_phase1_application_id' => 101,
                'service_detail' => ['business_formalization', 'market_linkage'],
                'cross_cutting' => ['buyer_seller_meet'],
                'payload' => [
                    'business_formalization' => [
                        'service_item_date' => '2026-05-18',
                        'registration_type' => 'udyam',
                        'registration_no' => 'UDYAM-UK-00-0000000',
                        'registration_date' => '2026-05-01',
                    ],
                    'market_linkage' => [
                        'service_item_date' => '2026-05-19',
                        'market_type' => 'offline',
                        'partner_or_buyer' => 'Local mart',
                    ],
                    'buyer_seller_meet' => [
                        'service_item_date' => '2026-05-20',
                        'district' => 'Almora',
                        'meet_name' => 'Local meet',
                        'outcome_type' => ['sales'],
                        'buyer_name' => 'Local buyer',
                    ],
                ],
                'media' => [
                    'business_formalization' => [UploadedFile::fake()->image('reg.jpg')],
                ],
            ])
            ->assertRedirect();

        $session = AccelerationServiceSession::query()->first();
        $this->assertNotNull($session);
        $this->assertTrue($session->counts_for_7_2);
        $this->assertFalse((bool) $session->is_draft);
        $this->assertSame(3, $session->items()->count());
        $bf = $session->items()->where('item_key', 'business_formalization')->first();
        $this->assertSame('udyam', $bf->payload['registration_type'] ?? null);
        $bsm = $session->items()->where('item_key', 'buyer_seller_meet')->first();
        $this->assertSame(['sales'], $bsm->payload['outcome_type'] ?? null);
    }

    public function test_follow_up_session_does_not_recount_7_2_for_same_incubatee(): void
    {
        $ankur = User::factory()->create([
            'role' => 'state_staff',
            'email' => 'ankur.rawat@pwc.com',
            'is_active' => true,
        ]);

        $fy = FiscalYear::query()->create([
            'code' => '2026-27',
            'name' => 'FY 2026-27',
            'starts_on' => '2026-04-01',
            'ends_on' => '2027-03-31',
            'is_active' => true,
        ]);

        DB::table('acceleration_service_sessions')->insert([
            [
                'service_date' => '2026-05-01',
                'fiscal_year_id' => $fy->id,
                'legacy_phase1_application_id' => 55,
                'incubatee_key' => 'p1:55',
                'incubatee_source' => 'phase1',
                'applicant_name' => 'Test Applicant',
                'application_no' => 'APP-55',
                'phone' => '9876543210',
                'district_name' => 'Almora',
                'onboard_label' => 'Onboarded',
                'counts_for_7_2' => true,
                'submitted_by_user_id' => $ankur->id,
                'submitted_by_name' => $ankur->name,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->mock(AccelerationServicesIncubateeService::class, function ($mock) use ($fy): void {
            $mock->shouldReceive('findPhase1Applicant')->with(55)->andReturn([
                'legacy_phase1_application_id' => 55,
                'incubatee_key' => 'p1:55',
                'applicant_name' => 'Test Applicant',
                'application_no' => 'APP-55',
                'phone' => '9876543210',
                'district_name' => 'Almora',
                'onboard_label' => 'Onboarded',
            ]);
            $mock->shouldReceive('resolveFiscalYearIdForDate')->andReturn((int) $fy->id);
            $mock->shouldReceive('shouldCountFor72')->andReturn(false);
        });

        $this->actingAs($ankur)
            ->post(route('spoc.acceleration-services.store'), [
                'service_date' => '2026-06-10',
                'legacy_phase1_application_id' => 55,
                'partnership' => ['tbi_graphic_era'],
                'payload' => [
                    'tbi_graphic_era' => [
                        'service_item_date' => '2026-06-10',
                        'domain' => 'food',
                        'start_date' => '2026-06-01',
                        'end_date' => '2026-08-01',
                        'duration_term' => 'long_term',
                        'period_from_month' => '2026-06',
                        'period_to_month' => '2026-08',
                        'support_types' => ['mentoring', 'market_access'],
                        'support_topic_mentoring' => 'Food packaging mentoring',
                        'support_topic_market_access' => 'Buyer connects and cold chain basics',
                    ],
                ],
            ])
            ->assertRedirect();

        $followUp = AccelerationServiceSession::query()->orderByDesc('id')->first();
        $this->assertFalse((bool) $followUp->counts_for_7_2);
    }

    public function test_non_allowlisted_user_cannot_submit(): void
    {
        $other = User::factory()->create([
            'role' => 'state_staff',
            'email' => 'other@pwc.com',
            'is_active' => true,
        ]);

        $this->actingAs($other)
            ->post(route('spoc.acceleration-services.store'), [
                'service_date' => '2026-05-20',
                'legacy_phase1_application_id' => 1,
                'service_detail' => ['soft_skills'],
            ])
            ->assertForbidden();
    }

    public function test_district_staff_can_submit_in_house_only_and_open_dashboard(): void
    {
        $hubId = DB::table('hubs')->insertGetId([
            'slug' => 'test-hub',
            'name' => 'Test Hub',
            'sort_order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $districtId = DB::table('districts')->insertGetId([
            'hub_id' => $hubId,
            'slug' => 'almora-test',
            'name' => 'Almora',
            'sort_order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $staff = User::factory()->create([
            'role' => 'district_staff',
            'email' => 'district.staff@example.com',
            'is_active' => true,
            'district_id' => $districtId,
        ]);

        $fy = FiscalYear::query()->create([
            'code' => '2026-27',
            'name' => 'FY 2026-27',
            'starts_on' => '2026-04-01',
            'ends_on' => '2027-03-31',
            'is_active' => true,
        ]);

        $this->mock(AccelerationServicesIncubateeService::class, function ($mock) use ($fy): void {
            $mock->shouldReceive('findPhase1Applicant')->with(201)->andReturn([
                'legacy_phase1_application_id' => 201,
                'incubatee_key' => 'p1:201',
                'applicant_name' => 'District Applicant',
                'application_no' => 'APP-201',
                'phone' => '9876543210',
                'district_name' => 'Almora',
                'onboard_label' => 'Onboarded',
            ]);
            $mock->shouldReceive('resolveFiscalYearIdForDate')->andReturn((int) $fy->id);
            $mock->shouldReceive('shouldCountFor72')->andReturn(true);
        });

        $this->actingAs($staff)
            ->get(route('staff.acceleration-services.create'))
            ->assertOk()
            ->assertSee('In-house')
            ->assertSee('Add another market linkage')
            ->assertDontSee('Soft skill', false)
            ->assertDontSee('Other Convergence Support', false)
            ->assertDontSee('Cross-cutting initiative', false)
            ->assertDontSee('External — Co-incubation partners', false);

        $this->actingAs($staff)
            ->get(route('staff.acceleration-services.dashboard'))
            ->assertOk();

        $this->actingAs($staff)
            ->post(route('staff.acceleration-services.store'), [
                'service_date' => '2026-05-20',
                'legacy_phase1_application_id' => 201,
                'service_detail' => ['business_formalization'],
                'cross_cutting' => ['buyer_seller_meet'],
                'partnership' => ['tbi_graphic_era'],
                'payload' => [
                    'business_formalization' => [
                        'service_item_date' => '2026-05-18',
                        'registration_type' => 'udyam',
                        'registration_no' => 'UDYAM-UK-00-0000001',
                        'registration_date' => '2026-05-01',
                    ],
                    'buyer_seller_meet' => [
                        'service_item_date' => '2026-05-20',
                        'district' => 'Almora',
                        'meet_name' => 'Should ignore',
                        'outcome_type' => ['sales'],
                        'buyer_name' => 'Buyer',
                    ],
                ],
                'media' => [
                    'business_formalization' => [UploadedFile::fake()->image('reg.jpg')],
                ],
            ])
            ->assertRedirect();

        $session = AccelerationServiceSession::query()->first();
        $this->assertNotNull($session);
        $this->assertSame(1, $session->items()->count());
        $this->assertSame('business_formalization', $session->items()->first()->item_key);
        $this->assertSame((int) $staff->id, (int) $session->submitted_by_user_id);
    }

    public function test_deliverable_counts_unique_initiations_for_7_2_row(): void
    {
        $fy = FiscalYear::query()->create([
            'code' => '2026-27',
            'name' => 'FY 2026-27',
            'starts_on' => '2026-04-01',
            'ends_on' => '2027-03-31',
            'is_active' => true,
        ]);

        foreach (['p1:1', 'p1:2', 'p1:1'] as $index => $key) {
            DB::table('acceleration_service_sessions')->insert([
                'service_date' => '2026-05-'.str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT),
                'fiscal_year_id' => $fy->id,
                'legacy_phase1_application_id' => $index + 1,
                'incubatee_key' => $key,
                'incubatee_source' => 'phase1',
                'applicant_name' => 'Applicant '.$index,
                'application_no' => 'A-'.$index,
                'phone' => null,
                'district_name' => 'Almora',
                'onboard_label' => 'Onboarded',
                'counts_for_7_2' => $index < 2,
                'status' => 'approved',
                'submitted_by_user_id' => 1,
                'submitted_by_name' => 'Ankur',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $admin = User::factory()->create(['role' => 'state_admin', 'is_active' => true]);
        $this->actingAs($admin);

        $report = app(ProgramDeliverablesReportService::class)->build(
            new ProgramDeliverablesFilter($fy->id, null, null, null, null, null),
            ProgramDeliverablesScope::forUser($admin),
        );

        $row = collect($report['rows'])->first(fn (array $r) => ($r['source_type'] ?? '') === 'acceleration_services_initiation_count');
        $this->assertNotNull($row);
        $this->assertSame(2, (int) ($row['achievement'] ?? -1));
    }

    public function test_maker_checker_flow_district_entry_needs_two_approvals(): void
    {
        $hubId = DB::table('hubs')->insertGetId([
            'slug' => 'wf-hub', 'name' => 'WF Hub', 'sort_order' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $districtId = DB::table('districts')->insertGetId([
            'hub_id' => $hubId, 'slug' => 'wf-almora', 'name' => 'Almora', 'sort_order' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $staff = User::factory()->create([
            'role' => 'district_staff',
            'email' => 'wf.staff@example.com',
            'is_active' => true,
            'district_id' => $districtId,
        ]);
        $ankur = User::factory()->create([
            'role' => 'state_staff',
            'email' => 'ankur.rawat@pwc.com',
            'is_active' => true,
        ]);
        $aadil = User::factory()->create([
            'role' => 'state_staff',
            'email' => 'aadil.ishrat@pwc.com',
            'is_active' => true,
        ]);

        $fy = FiscalYear::query()->create([
            'code' => '2026-27',
            'name' => 'FY 2026-27',
            'starts_on' => '2026-04-01',
            'ends_on' => '2027-03-31',
            'is_active' => true,
        ]);

        $this->mock(AccelerationServicesIncubateeService::class, function ($mock) use ($fy): void {
            $mock->shouldReceive('findPhase1Applicant')->with(301)->andReturn([
                'legacy_phase1_application_id' => 301,
                'incubatee_key' => 'p1:301',
                'applicant_name' => 'Workflow Applicant',
                'application_no' => 'APP-301',
                'phone' => '9876543210',
                'district_name' => 'Almora',
                'onboard_label' => 'Onboarded',
            ]);
            $mock->shouldReceive('resolveFiscalYearIdForDate')->andReturn((int) $fy->id);
            $mock->shouldReceive('shouldCountFor72')->andReturn(true);
        });

        // 1) District staff submits → pending state review; not counted yet.
        $this->actingAs($staff)
            ->post(route('staff.acceleration-services.store'), [
                'service_date' => '2026-05-20',
                'legacy_phase1_application_id' => 301,
                'service_detail' => ['business_formalization'],
                'payload' => [
                    'business_formalization' => [
                        'service_item_date' => '2026-05-18',
                        'registration_type' => 'udyam',
                        'registration_no' => 'UDYAM-UK-00-0000001',
                        'registration_date' => '2026-05-01',
                    ],
                ],
                'media' => [
                    'business_formalization' => [UploadedFile::fake()->image('reg.jpg')],
                ],
            ])
            ->assertRedirect();

        $session = AccelerationServiceSession::query()->firstOrFail();
        $this->assertSame('pending_review', (string) $session->status);
        $this->assertSame(0, \App\Support\AccelerationServicesDeliverablesSupport::countUniqueInitiations(
            \Carbon\Carbon::parse('2026-04-01'),
            \Carbon\Carbon::parse('2027-03-31'),
        ));
        $this->assertSame(1, $session->events()->where('action', 'submitted')->count());

        app(\App\Services\AppSettingsService::class)->setMany(['service_module.enabled' => true]);

        // Maker sees the entry on My services with its approval status.
        $this->actingAs($staff)->get(route('staff.services.index'))
            ->assertOk()
            ->assertSee('Acceleration services (7.2)')
            ->assertSee('Workflow Applicant')
            ->assertSee('Pending state review');

        // Ankur sees it in the main service-cases table; Aadil does not yet (still pending state review).
        $this->actingAs($ankur)->get(route('spoc.service-cases.index'))
            ->assertOk()
            ->assertSee('Acceleration services (7.2)')
            ->assertSee('Workflow Applicant')
            ->assertSee('Pending state review');
        $this->actingAs($aadil)->get(route('spoc.service-cases.index'))
            ->assertOk()
            ->assertDontSee('Workflow Applicant');

        // 2) Ankur sends it back with remarks → maker edits and resubmits.
        $this->actingAs($ankur)
            ->post(route('spoc.acceleration-services.send-back', $session), ['remarks' => 'Fix registration number'])
            ->assertRedirect();
        $session->refresh();
        $this->assertSame('sent_back', (string) $session->status);
        $this->assertSame('Fix registration number', (string) $session->sent_back_remarks);

        $this->actingAs($staff)
            ->put(route('staff.acceleration-services.update', $session), [
                'service_date' => '2026-05-20',
                'legacy_phase1_application_id' => 301,
                'service_detail' => ['business_formalization'],
                'payload' => [
                    'business_formalization' => [
                        'service_item_date' => '2026-05-18',
                        'registration_type' => 'udyam',
                        'registration_no' => 'UDYAM-UK-01-0000001',
                        'registration_date' => '2026-05-01',
                    ],
                ],
                'media' => [
                    'business_formalization' => [UploadedFile::fake()->image('reg.jpg')],
                ],
            ])
            ->assertRedirect();
        $session->refresh();
        $this->assertSame('pending_review', (string) $session->status);
        $this->assertSame(1, $session->events()->where('action', 'resubmitted')->count());

        // 3) Ankur approves → pending final (Aadil). Aadil approves → approved + counted.
        $this->actingAs($ankur)
            ->post(route('spoc.acceleration-services.approve', $session))
            ->assertRedirect();
        $session->refresh();
        $this->assertSame('pending_final', (string) $session->status);
        $this->assertSame((int) $ankur->id, (int) $session->first_approved_by_user_id);

        // Aadil cannot be skipped: staff still not counted.
        $this->assertSame(0, \App\Support\AccelerationServicesDeliverablesSupport::countUniqueInitiations(
            \Carbon\Carbon::parse('2026-04-01'),
            \Carbon\Carbon::parse('2027-03-31'),
        ));

        $this->actingAs($aadil)
            ->post(route('spoc.acceleration-services.approve', $session))
            ->assertRedirect();
        $session->refresh();
        $this->assertSame('approved', (string) $session->status);
        $this->assertSame((int) $aadil->id, (int) $session->final_approved_by_user_id);
        $this->assertSame(1, \App\Support\AccelerationServicesDeliverablesSupport::countUniqueInitiations(
            \Carbon\Carbon::parse('2026-04-01'),
            \Carbon\Carbon::parse('2027-03-31'),
        ));

        // Approved history stays on the service-cases table for both checkers.
        $this->actingAs($aadil)->get(route('spoc.service-cases.index', ['status' => 'approved']))
            ->assertOk()
            ->assertSee('Workflow Applicant')
            ->assertSee('Approved');
        $this->actingAs($ankur)->get(route('spoc.service-cases.index', ['status' => 'approved']))
            ->assertOk()
            ->assertSee('Workflow Applicant');

        // 4) Approved entry is locked for the maker.
        $this->actingAs($staff)
            ->get(route('staff.acceleration-services.edit', $session))
            ->assertRedirect(route('staff.acceleration-services.show', $session));
        $this->actingAs($staff)
            ->put(route('staff.acceleration-services.update', $session), [
                'service_date' => '2026-05-21',
                'legacy_phase1_application_id' => 301,
                'service_detail' => ['business_formalization'],
                'payload' => [
                    'business_formalization' => [
                        'service_item_date' => '2026-05-18',
                        'registration_type' => 'udyam',
                        'registration_no' => 'UDYAM-UK-00-0000001',
                        'registration_date' => '2026-05-01',
                    ],
                ],
            ])
            ->assertSessionHasErrors();

        // All stakeholders can see the detail page with the log.
        foreach ([$staff, $ankur, $aadil] as $viewer) {
            $prefix = $viewer->role === 'district_staff' ? 'staff' : 'spoc';
            $this->actingAs($viewer)
                ->get(route($prefix.'.acceleration-services.show', $session))
                ->assertOk()
                ->assertSee('Activity log')
                ->assertSee('Final approval');
        }
    }

    public function test_ankur_entry_goes_straight_to_final_approval(): void
    {
        $ankur = User::factory()->create([
            'role' => 'state_staff',
            'email' => 'ankur.rawat@pwc.com',
            'is_active' => true,
        ]);
        $aadil = User::factory()->create([
            'role' => 'state_staff',
            'email' => 'aadil.ishrat@pwc.com',
            'is_active' => true,
        ]);

        $fy = FiscalYear::query()->create([
            'code' => '2026-27',
            'name' => 'FY 2026-27',
            'starts_on' => '2026-04-01',
            'ends_on' => '2027-03-31',
            'is_active' => true,
        ]);

        $this->mock(AccelerationServicesIncubateeService::class, function ($mock) use ($fy): void {
            $mock->shouldReceive('findPhase1Applicant')->with(401)->andReturn([
                'legacy_phase1_application_id' => 401,
                'incubatee_key' => 'p1:401',
                'applicant_name' => 'Spoc Applicant',
                'application_no' => 'APP-401',
                'phone' => '9876543210',
                'district_name' => 'Almora',
                'onboard_label' => 'Onboarded',
            ]);
            $mock->shouldReceive('resolveFiscalYearIdForDate')->andReturn((int) $fy->id);
            $mock->shouldReceive('shouldCountFor72')->andReturn(true);
        });

        $this->actingAs($ankur)
            ->post(route('spoc.acceleration-services.store'), [
                'service_date' => '2026-05-20',
                'legacy_phase1_application_id' => 401,
                'service_detail' => ['business_formalization'],
                'payload' => [
                    'business_formalization' => [
                        'service_item_date' => '2026-05-18',
                        'registration_type' => 'udyam',
                        'registration_no' => 'UDYAM-UK-00-0000001',
                        'registration_date' => '2026-05-01',
                    ],
                ],
                'media' => [
                    'business_formalization' => [UploadedFile::fake()->image('reg.jpg')],
                ],
            ])
            ->assertRedirect();

        $session = AccelerationServiceSession::query()->firstOrFail();
        $this->assertSame('pending_final', (string) $session->status);

        // Ankur cannot approve his own entry.
        $this->actingAs($ankur)
            ->post(route('spoc.acceleration-services.approve', $session))
            ->assertForbidden();

        $this->actingAs($aadil)
            ->post(route('spoc.acceleration-services.approve', $session))
            ->assertRedirect();
        $session->refresh();
        $this->assertSame('approved', (string) $session->status);
    }

    public function test_prior_form_items_expose_district_assigned_service_for_ankur_prefill(): void
    {
        $fy = FiscalYear::query()->create([
            'code' => '2026-27',
            'name' => 'FY 2026-27',
            'starts_on' => '2026-04-01',
            'ends_on' => '2027-03-31',
            'is_active' => true,
        ]);

        $sessionId = DB::table('acceleration_service_sessions')->insertGetId([
            'service_date' => '2026-05-10',
            'fiscal_year_id' => $fy->id,
            'legacy_phase1_application_id' => 916460916,
            'incubatee_key' => 'p1:916460916',
            'incubatee_source' => 'phase1',
            'applicant_name' => 'Vikas Pant',
            'application_no' => '916460916',
            'phone' => '9000000000',
            'district_name' => 'Almora',
            'onboard_label' => 'Onboarded',
            'counts_for_7_2' => true,
            'is_draft' => false,
            'status' => 'pending_review',
            'submitted_by_user_id' => 99,
            'submitted_by_name' => 'District Staff',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('acceleration_service_items')->insert([
            'session_id' => $sessionId,
            'section' => 'service_detail',
            'item_key' => 'market_linkage',
            'item_label' => 'Market Linkage',
            'remarks' => null,
            'is_custom' => false,
            'is_buyer_seller_meet' => false,
            'payload' => json_encode([
                'service_item_date' => '2026-05-10',
                'buyer_name' => 'Test Buyer',
                'order_value' => '5000',
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $prior = app(AccelerationServicesIncubateeService::class)
            ->priorAccelerationFormItems('p1:916460916');

        $this->assertCount(1, $prior);
        $this->assertSame('market_linkage', $prior[0]['item_key']);
        $this->assertSame('District Staff', $prior[0]['assigned_by']);
        $this->assertSame('Test Buyer', $prior[0]['payload']['buyer_name'] ?? null);

        $draftId = DB::table('acceleration_service_sessions')->insertGetId([
            'service_date' => '2026-05-11',
            'fiscal_year_id' => $fy->id,
            'legacy_phase1_application_id' => 916460916,
            'incubatee_key' => 'p1:916460916',
            'incubatee_source' => 'phase1',
            'applicant_name' => 'Vikas Pant',
            'application_no' => '916460916',
            'phone' => '9000000000',
            'district_name' => 'Almora',
            'onboard_label' => 'Onboarded',
            'counts_for_7_2' => false,
            'is_draft' => true,
            'status' => 'draft',
            'submitted_by_user_id' => 1,
            'submitted_by_name' => 'Draft User',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('acceleration_service_items')->insert([
            'session_id' => $draftId,
            'section' => 'partnership',
            'item_key' => 'tbi_graphic_era',
            'item_label' => 'TBI Graphic Era',
            'remarks' => null,
            'is_custom' => false,
            'is_buyer_seller_meet' => false,
            'payload' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $priorAgain = app(AccelerationServicesIncubateeService::class)
            ->priorAccelerationFormItems('p1:916460916');
        $this->assertCount(1, $priorAgain);
        $this->assertSame('market_linkage', $priorAgain[0]['item_key']);
    }
}
