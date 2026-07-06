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
            ->get(route('spoc.acceleration-services.dashboard'))
            ->assertOk()
            ->assertSee('7.2');

        $this->actingAs($ankur)
            ->post(route('spoc.acceleration-services.store'), [
                'service_date' => '2026-05-20',
                'legacy_phase1_application_id' => 101,
                'service_detail' => ['business_formalization', 'soft_skills'],
                'cross_cutting' => ['buyer_seller_meet'],
                'remarks' => [
                    'business_formalization' => 'Udyam guidance',
                    'buyer_seller_meet' => 'Local meet',
                ],
            ])
            ->assertRedirect(route('spoc.acceleration-services.dashboard'));

        $session = AccelerationServiceSession::query()->first();
        $this->assertNotNull($session);
        $this->assertTrue($session->counts_for_7_2);
        $this->assertSame(3, $session->items()->count());
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
}
