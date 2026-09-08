<?php

namespace Tests\Feature;

use App\Models\District;
use App\Models\Hub;
use App\Models\ServiceCase;
use App\Models\User;
use App\Services\MarketLinkages\AllPhasePartnerDirectoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MarketLinkagePartnerDirectoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_sees_approved_partners_and_linked_incubatees_from_phase3(): void
    {
        $district = $this->createDistrict();
        $admin = User::factory()->create(['role' => 'state_admin', 'is_active' => true]);
        $staff = User::factory()->create([
            'role' => 'district_staff',
            'district_id' => $district->id,
            'is_active' => true,
        ]);

        $cfaAmazon = $this->seedOnboardedApplicant($district, 'APP-DIR-001', 'Ramesh Rawat');
        $cfaFlip = $this->seedOnboardedApplicant($district, 'APP-DIR-002', 'Sita Bisht');

        $this->seedApprovedLinkage($staff, $district, $cfaAmazon, 'APP-DIR-001', 'Ramesh Rawat', 'Amazom', 'online', 'https://amazon.in/a');
        $this->seedApprovedLinkage($staff, $district, $cfaAmazon, 'APP-DIR-001', 'Ramesh Rawat', 'Amazon', 'offline');
        $this->seedApprovedLinkage($staff, $district, $cfaFlip, 'APP-DIR-002', 'Sita Bisht', 'Flipkart', 'online', 'https://flipkart.com/s');

        $pendingCfa = $this->seedOnboardedApplicant($district, 'APP-DIR-PEND', 'Pending Person');
        $this->seedPendingLinkage($staff, $district, $pendingCfa, 'APP-DIR-PEND', 'Pending Person', 'Meesho');

        $this->actingAs($admin)
            ->get(route('admin.market-linkages.partners'))
            ->assertOk()
            ->assertSee('Amazon', false)
            ->assertSee('Flipkart', false)
            ->assertDontSee('Meesho')
            ->assertDontSee('Pending Person');

        $this->actingAs($admin)
            ->get(route('admin.market-linkages.partners', ['q' => 'Ramesh']))
            ->assertOk()
            ->assertSee('Amazon', false)
            ->assertDontSee('Flipkart');

        $amazonKey = AllPhasePartnerDirectoryService::encodeKey('amazon');
        $this->actingAs($admin)
            ->get(route('admin.market-linkages.partners.show', ['partner' => $amazonKey]))
            ->assertOk()
            ->assertSee('Ramesh Rawat', false)
            ->assertSee('APP-DIR-001', false)
            ->assertSee('Onboarded', false)
            ->assertSee('Export Excel', false)
            ->assertDontSee('Sita Bisht');
    }

    public function test_partner_page_stays_open_when_filters_match_no_incubatees(): void
    {
        $district = $this->createDistrict();
        $admin = User::factory()->create(['role' => 'state_admin', 'is_active' => true]);
        $staff = User::factory()->create([
            'role' => 'district_staff',
            'district_id' => $district->id,
            'is_active' => true,
        ]);
        $cfa = $this->seedOnboardedApplicant($district, 'APP-DIR-EMPTY', 'Ramesh Rawat');
        $this->seedApprovedLinkage($staff, $district, $cfa, 'APP-DIR-EMPTY', 'Ramesh Rawat', 'Booking.com', 'online', 'https://booking.com/r');

        $key = AllPhasePartnerDirectoryService::encodeKey('booking com');
        $this->actingAs($admin)
            ->get(route('admin.market-linkages.partners.show', ['partner' => $key, 'q' => 'amazon']))
            ->assertOk()
            ->assertSee('Booking.com', false)
            ->assertSee('No incubatees for this partner with the current filters.', false)
            ->assertDontSee('Ramesh Rawat');
    }

    public function test_incubatee_export_respects_partner_and_mode_filters(): void
    {
        $district = $this->createDistrict();
        $admin = User::factory()->create(['role' => 'state_admin', 'is_active' => true]);
        $staff = User::factory()->create([
            'role' => 'district_staff',
            'district_id' => $district->id,
            'is_active' => true,
        ]);

        $cfaAmazon = $this->seedOnboardedApplicant($district, 'APP-DIR-001', 'Ramesh Rawat');
        $cfaFlip = $this->seedOnboardedApplicant($district, 'APP-DIR-002', 'Sita Bisht');
        $this->seedApprovedLinkage($staff, $district, $cfaAmazon, 'APP-DIR-001', 'Ramesh Rawat', 'Amazon', 'online', 'https://amazon.in/a');
        $this->seedApprovedLinkage($staff, $district, $cfaAmazon, 'APP-DIR-001', 'Ramesh Rawat', 'Amazon', 'offline');
        $this->seedApprovedLinkage($staff, $district, $cfaFlip, 'APP-DIR-002', 'Sita Bisht', 'Flipkart', 'online', 'https://flipkart.com/s');

        $amazonKey = AllPhasePartnerDirectoryService::encodeKey('amazon');
        $response = $this->actingAs($admin)->get(route('admin.market-linkages.partners.export', [
            'partner' => $amazonKey,
            'mode' => 'online',
        ]));
        $response->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $tempPath = tempnam(sys_get_temp_dir(), 'ml-inc-');
        file_put_contents($tempPath, $response->streamedContent());
        $workbook = \PhpOffice\PhpSpreadsheet\IOFactory::load($tempPath);
        $sheet = $workbook->getActiveSheet();
        $values = $sheet->toArray(null, true, true, true);
        $hay = collect($values)->map(fn ($row) => implode(' ', array_map(fn ($v) => (string) $v, $row)))->implode(' ');

        $this->assertStringContainsString('Ramesh Rawat', $hay);
        $this->assertStringContainsString('Online', $hay);
        $this->assertStringNotContainsString('Sita Bisht', $hay);
        $this->assertStringNotContainsString('Offline', $hay);

        $workbook->disconnectWorksheets();
        @unlink($tempPath);
    }

    public function test_staff_only_sees_own_district_partners(): void
    {
        $districtA = $this->createDistrict('dehradun', 'Dehradun');
        $districtB = $this->createDistrict('nainital', 'Nainital');
        $staffA = User::factory()->create([
            'role' => 'district_staff',
            'district_id' => $districtA->id,
            'is_active' => true,
        ]);
        $staffB = User::factory()->create([
            'role' => 'district_staff',
            'district_id' => $districtB->id,
            'is_active' => true,
        ]);

        $cfaA = $this->seedOnboardedApplicant($districtA, 'APP-DIR-A', 'Incubatee A');
        $cfaB = $this->seedOnboardedApplicant($districtB, 'APP-DIR-B', 'Incubatee B');
        $this->seedApprovedLinkage($staffA, $districtA, $cfaA, 'APP-DIR-A', 'Incubatee A', 'Goibibo', 'online', 'https://goibibo.com/a');
        $this->seedApprovedLinkage($staffB, $districtB, $cfaB, 'APP-DIR-B', 'Incubatee B', 'Agoda', 'online', 'https://agoda.com/b');

        $this->actingAs($staffA)
            ->get(route('staff.market-linkages.partners'))
            ->assertOk()
            ->assertSee('Goibibo', false)
            ->assertDontSee('Agoda');
    }

    public function test_guest_cannot_open_partner_directory(): void
    {
        $this->get(route('admin.market-linkages.partners'))->assertRedirect();
    }

    private function seedApprovedLinkage(
        User $staff,
        District $district,
        int $cfaId,
        string $appNo,
        string $name,
        string $partner,
        string $mode,
        ?string $url = null,
    ): void {
        $submissionId = (int) DB::table('market_linkage_submissions')->insertGetId([
            'submitted_by_user_id' => $staff->id,
            'submitted_by_name' => $staff->name,
            'district_id' => $district->id,
            'district_name' => $district->name,
            'cfa_submission_id' => $cfaId,
            'incubatee_name' => $name,
            'application_no' => $appNo,
            'status' => ServiceCase::STATUS_APPROVED,
            'approved_at' => now(),
            'submitted_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('market_linkage_partners')->insert([
            'market_linkage_submission_id' => $submissionId,
            'partner_name' => $partner,
            'linkage_mode' => $mode,
            'linkage_date' => '2026-05-10',
            'link_url' => $url,
            'sort_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function seedPendingLinkage(
        User $staff,
        District $district,
        int $cfaId,
        string $appNo,
        string $name,
        string $partner,
    ): void {
        $submissionId = (int) DB::table('market_linkage_submissions')->insertGetId([
            'submitted_by_user_id' => $staff->id,
            'submitted_by_name' => $staff->name,
            'district_id' => $district->id,
            'district_name' => $district->name,
            'cfa_submission_id' => $cfaId,
            'incubatee_name' => $name,
            'application_no' => $appNo,
            'status' => ServiceCase::STATUS_PENDING_APPROVAL,
            'submitted_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('market_linkage_partners')->insert([
            'market_linkage_submission_id' => $submissionId,
            'partner_name' => $partner,
            'linkage_mode' => 'online',
            'linkage_date' => '2026-05-11',
            'link_url' => 'https://meesho.com/x',
            'sort_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
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
            'name' => 'Test Hub '.$slug,
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
