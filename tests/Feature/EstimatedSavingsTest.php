<?php

namespace Tests\Feature;

use App\Models\District;
use App\Models\FiscalYear;
use App\Models\Hub;
use App\Models\MarketLinkagePartner;
use App\Models\MarketLinkageSubmission;
use App\Models\ServiceCase;
use App\Models\User;
use App\Services\AdminDashboardInsightsService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EstimatedSavingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_online_market_linkage_adds_five_thousand_per_unique_approved_incubatee(): void
    {
        DB::connection()->getPdo()->sqliteCreateFunction(
            'CONCAT',
            static fn (...$parts): string => implode('', $parts),
        );

        $hub = Hub::query()->create([
            'name' => 'Savings Test Hub',
            'slug' => 'savings-test-hub',
            'is_active' => true,
        ]);
        $district = District::query()->create([
            'hub_id' => $hub->id,
            'name' => 'Savings Test District',
            'slug' => 'savings-test-district',
            'is_active' => true,
        ]);
        $staff = User::factory()->create([
            'role' => 'district_staff',
            'district_id' => $district->id,
            'is_active' => true,
        ]);

        $approvedOnline = $this->createLinkage($district, $staff, 'Online Incubatee', ServiceCase::STATUS_APPROVED, [
            ['Amazon', 'online'],
            ['Flipkart', 'online'],
        ]);
        $this->createLinkage($district, $staff, 'Offline Incubatee', ServiceCase::STATUS_APPROVED, [
            ['Local buyer', 'offline'],
        ]);
        $this->createLinkage($district, $staff, 'Pending Online Incubatee', ServiceCase::STATUS_PENDING_APPROVAL, [
            ['Meesho', 'online'],
        ]);

        $fy = new FiscalYear([
            'starts_on' => '2026-04-02',
            'ends_on' => '2027-04-01',
        ]);
        $result = app(AdminDashboardInsightsService::class)->estimatedSavings(
            $fy,
            Carbon::parse('2026-04-01')->startOfDay(),
        );

        $this->assertSame(5000.0, $result['total_till_date']);
        $this->assertSame(5000.0, $result['total_this_fy']);
        $this->assertSame([
            'name' => 'Online Market Linkage',
            'avg_price' => 5000.0,
            'approved_count' => 1,
            'savings' => 5000.0,
        ], $result['top_services'][0]);
        $this->assertCount(2, $approvedOnline->partners);
    }

    /**
     * @param  list<array{0: string, 1: string}>  $partners
     */
    private function createLinkage(District $district, User $staff, string $name, string $status, array $partners): MarketLinkageSubmission
    {
        $cfaId = (int) DB::table('cfa_submissions')->insertGetId([
            'district_id' => $district->id,
            'applicant_name' => $name,
            'phone' => '9'.str_pad((string) random_int(1, 999999999), 9, '0', STR_PAD_LEFT),
            'payload' => json_encode([]),
            'created_at' => '2026-08-01 10:00:00',
            'updated_at' => '2026-08-01 10:00:00',
        ]);

        $submission = MarketLinkageSubmission::query()->create([
            'submitted_by_user_id' => $staff->id,
            'submitted_by_name' => $staff->name,
            'district_id' => $district->id,
            'district_name' => $district->name,
            'cfa_submission_id' => $cfaId,
            'incubatee_name' => $name,
            'application_no' => 'ML-'.$cfaId,
            'status' => $status,
            'submitted_at' => '2026-08-01 10:00:00',
            'approved_at' => $status === ServiceCase::STATUS_APPROVED ? '2026-08-02 10:00:00' : null,
            'approved_by' => $status === ServiceCase::STATUS_APPROVED ? $staff->id : null,
        ]);

        foreach ($partners as $index => [$partnerName, $mode]) {
            MarketLinkagePartner::query()->create([
                'market_linkage_submission_id' => $submission->id,
                'partner_name' => $partnerName,
                'linkage_mode' => $mode,
                'linkage_date' => '2026-08-01',
                'sort_order' => $index + 1,
            ]);
        }

        return $submission->load('partners');
    }
}
