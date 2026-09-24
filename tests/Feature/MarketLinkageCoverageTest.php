<?php

namespace Tests\Feature;

use App\Models\District;
use App\Models\FiscalYear;
use App\Models\Hub;
use App\Models\MarketLinkagePartner;
use App\Models\MarketLinkageSubmission;
use App\Models\ServiceCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MarketLinkageCoverageTest extends TestCase
{
    use RefreshDatabase;

    public function test_state_admin_sees_linked_and_not_linked_counts(): void
    {
        $district = $this->createDistrict();
        $linkedId = $this->seedOnboardedApplicant($district, 'APP-LINKED', 'Linked One', ['business_category' => 'Handicraft']);
        $this->seedOnboardedApplicant($district, 'APP-MISSING', 'Missing One', ['business_category' => 'Agriculture']);

        $admin = User::factory()->create(['role' => 'state_admin', 'is_active' => true]);

        $submission = MarketLinkageSubmission::query()->create([
            'submitted_by_user_id' => $admin->id,
            'submitted_by_name' => 'State Admin',
            'district_id' => $district->id,
            'district_name' => $district->name,
            'cfa_submission_id' => $linkedId,
            'incubatee_name' => 'Linked One',
            'application_no' => 'APP-LINKED',
            'status' => ServiceCase::STATUS_APPROVED,
            'submitted_at' => now(),
            'approved_at' => now(),
        ]);
        MarketLinkagePartner::query()->create([
            'market_linkage_submission_id' => $submission->id,
            'partner_name' => 'Local Buyer',
            'linkage_mode' => 'offline',
            'linkage_date' => '2026-05-01',
            'sort_order' => 1,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.market-linkages.coverage.index'))
            ->assertOk()
            ->assertSee('Market linkage coverage')
            ->assertSee('Not linked (1)', false)
            ->assertSee('Linked (1)', false)
            ->assertSee('APP-MISSING')
            ->assertSee('APP-LINKED');
    }

    public function test_sector_filter_limits_rows(): void
    {
        $district = $this->createDistrict();
        $this->seedOnboardedApplicant($district, 'APP-SEC-A', 'Alpha', ['business_category' => 'Handicraft']);
        $this->seedOnboardedApplicant($district, 'APP-SEC-B', 'Beta', ['business_category' => 'Agriculture']);

        $admin = User::factory()->create(['role' => 'state_admin', 'is_active' => true]);

        $this->actingAs($admin)
            ->get(route('admin.market-linkages.coverage.index', ['sector' => 'Handicraft']))
            ->assertOk()
            ->assertSee('APP-SEC-A')
            ->assertDontSee('APP-SEC-B');
    }

    public function test_phase3_cohort_uses_locked_batch_date_instead_of_cfa_fiscal_year(): void
    {
        $district = $this->createDistrict();
        $fy2627 = FiscalYear::query()->firstOrCreate(
            ['code' => '2026-27'],
            ['name' => 'FY 2026-27', 'starts_on' => '2026-04-01', 'ends_on' => '2027-03-31', 'is_active' => true],
        );
        $fy2526 = FiscalYear::query()->firstOrCreate(
            ['code' => '2025-26'],
            ['name' => 'FY 2025-26', 'starts_on' => '2025-04-01', 'ends_on' => '2026-03-31', 'is_active' => false],
        );

        $this->seedOnboardedApplicant($district, 'APP-FY-2627', 'Current FY', fiscalYearId: (int) $fy2627->id);
        $this->seedOnboardedApplicant($district, 'APP-STALE-FY', 'Current Batch With Stale FY', fiscalYearId: (int) $fy2526->id);

        $seededApplications = DB::table('onboarding_batch_cfa as obc')
            ->join('onboarding_batches as ob', 'ob.id', '=', 'obc.onboarding_batch_id')
            ->join('cfa_submissions as cs', 'cs.id', '=', 'obc.cfa_submission_id')
            ->where('ob.status', 'locked')
            ->where('ob.locked_at', '>=', '2026-04-01')
            ->orderBy('cs.application_no')
            ->pluck('cs.application_no')
            ->all();
        $this->assertSame(['APP-FY-2627', 'APP-STALE-FY'], $seededApplications);

        $admin = User::factory()->create(['role' => 'state_admin', 'is_active' => true]);

        $currentResponse = $this->actingAs($admin)
            ->get(route('admin.market-linkages.coverage.index', ['fiscal_year' => '2026-27']))
            ->assertOk();
        $currentApplications = collect($currentResponse->viewData('rows')->items())->pluck('application_no')->all();
        sort($currentApplications);
        $this->assertSame(['APP-FY-2627', 'APP-STALE-FY'], $currentApplications);

        $legacyResponse = $this->actingAs($admin)
            ->get(route('admin.market-linkages.coverage.index', ['fiscal_year' => '2025-26']))
            ->assertOk();
        $legacyApplications = collect($legacyResponse->viewData('rows')->items())->pluck('application_no')->all();
        $this->assertNotContains('APP-FY-2627', $legacyApplications);
        $this->assertNotContains('APP-STALE-FY', $legacyApplications);
    }

    public function test_fy_2025_26_does_not_mix_phase3_rows_into_legacy_cohort(): void
    {
        $district = $this->createDistrict();
        $fy2526 = FiscalYear::query()->firstOrCreate(
            ['code' => '2025-26'],
            ['name' => 'FY 2025-26', 'starts_on' => '2025-04-01', 'ends_on' => '2026-03-31', 'is_active' => false],
        );
        $cfaId = $this->seedOnboardedApplicant($district, 'APP-PEND-2526', 'Pending FY', fiscalYearId: (int) $fy2526->id);

        $admin = User::factory()->create(['role' => 'state_admin', 'is_active' => true]);

        MarketLinkageSubmission::query()->create([
            'submitted_by_user_id' => $admin->id,
            'submitted_by_name' => 'State Admin',
            'district_id' => $district->id,
            'district_name' => $district->name,
            'cfa_submission_id' => $cfaId,
            'incubatee_name' => 'Pending FY',
            'application_no' => 'APP-PEND-2526',
            'status' => ServiceCase::STATUS_PENDING_APPROVAL,
            'submitted_at' => now(),
        ]);

        $response = $this->actingAs($admin)
            ->get(route('admin.market-linkages.coverage.index', ['fiscal_year' => '2025-26']))
            ->assertOk();
        $applications = collect($response->viewData('rows')->items())->pluck('application_no')->all();
        $this->assertNotContains('APP-PEND-2526', $applications);
        $this->assertSame(0, (int) $response->viewData('summary')['pending']);
    }

    public function test_district_staff_is_scoped_to_own_district(): void
    {
        $hub = Hub::query()->create(['slug' => 'cov-hub', 'name' => 'Cov Hub', 'sort_order' => 1]);
        $d1 = District::query()->create(['hub_id' => $hub->id, 'slug' => 'cov-d1', 'name' => 'Cov D1', 'sort_order' => 1]);
        $d2 = District::query()->create(['hub_id' => $hub->id, 'slug' => 'cov-d2', 'name' => 'Cov D2', 'sort_order' => 2]);
        $this->seedOnboardedApplicant($d1, 'APP-D1', 'District One');
        $this->seedOnboardedApplicant($d2, 'APP-D2', 'District Two');

        $staff = User::factory()->create([
            'role' => 'district_staff',
            'district_id' => $d1->id,
            'is_active' => true,
        ]);

        $this->actingAs($staff)
            ->get(route('staff.market-linkages.coverage.index'))
            ->assertOk()
            ->assertSee('APP-D1')
            ->assertDontSee('APP-D2');
    }

    private function createDistrict(): District
    {
        $hub = Hub::query()->create(['slug' => 'ml-cov-hub', 'name' => 'ML Cov Hub', 'sort_order' => 1]);

        return District::query()->create([
            'hub_id' => $hub->id,
            'slug' => 'ml-cov-district',
            'name' => 'ML Cov District',
            'sort_order' => 1,
        ]);
    }

    /** @param  array<string, mixed>  $payloadExtra */
    private function seedOnboardedApplicant(District $district, string $applicationNo, string $name, array $payloadExtra = [], ?int $fiscalYearId = null): int
    {
        if ($fiscalYearId === null) {
            $fiscalYearId = (int) (FiscalYear::phase3Default()?->id ?? FiscalYear::query()->firstOrCreate(
                ['code' => '2026-27'],
                ['name' => 'FY 2026-27', 'starts_on' => '2026-04-01', 'ends_on' => '2027-03-31', 'is_active' => true],
            )->id);
        }

        $cfaId = (int) DB::table('cfa_submissions')->insertGetId([
            'district_id' => $district->id,
            'fiscal_year_id' => $fiscalYearId,
            'application_no' => $applicationNo,
            'applicant_name' => $name,
            'phone' => '9876543210',
            'source' => 'phase3',
            'payload' => json_encode($payloadExtra),
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
}
