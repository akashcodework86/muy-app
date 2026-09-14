<?php

namespace Tests\Feature;

use App\Models\CfaSubmission;
use App\Models\District;
use App\Models\Hub;
use App\Models\IncubateeBill;
use App\Models\OnboardingBatch;
use App\Models\OnboardingBatchCfa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class IncubateeBillDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_state_admin_sees_all_bills_on_dashboard(): void
    {
        [$districtA, $batchA, $cfaA] = $this->createLockedBatchWithMember('alpha', 'Bill Alpha');
        [$districtB, $batchB, $cfaB] = $this->createLockedBatchWithMember('beta', 'Bill Beta');
        $this->makeBill($districtA, $batchA, $cfaA, 'BILL-ALPHA-1', 100);
        $this->makeBill($districtB, $batchB, $cfaB, 'BILL-BETA-1', 50);

        $admin = User::factory()->create(['role' => 'state_admin', 'is_active' => true]);

        $this->actingAs($admin)
            ->get(route('admin.bills.dashboard'))
            ->assertOk()
            ->assertSee('BILL-ALPHA-1')
            ->assertSee('BILL-BETA-1')
            ->assertSee('Export Excel')
            ->assertSee('All hubs');
    }

    public function test_spoc_sees_all_bills_and_filters(): void
    {
        [$districtA, $batchA, $cfaA] = $this->createLockedBatchWithMember('alpha', 'Suman Devi');
        $this->makeBill($districtA, $batchA, $cfaA, 'SPOC-ALL-1', 1250.50);

        $spoc = User::factory()->create(['role' => 'state_staff', 'is_active' => true]);

        $this->actingAs($spoc)
            ->get(route('spoc.bills.dashboard'))
            ->assertOk()
            ->assertSee('SPOC-ALL-1')
            ->assertSee('Suman Devi')
            ->assertSee('All hubs')
            ->assertSee('All districts');

        $this->actingAs($spoc)
            ->get(route('spoc.bills.dashboard', ['q' => 'no-such-bill']))
            ->assertOk()
            ->assertDontSee('SPOC-ALL-1')
            ->assertSee('No bills match these filters');
    }

    public function test_hub_admin_sees_only_own_hub_bills(): void
    {
        [$districtA, $batchA, $cfaA] = $this->createLockedBatchWithMember('alpha', 'Alpha Person');
        [$districtB, $batchB, $cfaB] = $this->createLockedBatchWithMember('beta', 'Beta Person');
        $this->makeBill($districtA, $batchA, $cfaA, 'HUB-A-1', 10);
        $this->makeBill($districtB, $batchB, $cfaB, 'HUB-B-1', 20);

        $hubAdmin = User::factory()->create([
            'role' => 'hub_admin',
            'hub_id' => $districtA->hub_id,
            'is_active' => true,
        ]);

        $this->actingAs($hubAdmin)
            ->get(route('hub.bills.dashboard'))
            ->assertOk()
            ->assertSee('HUB-A-1')
            ->assertDontSee('HUB-B-1');
    }

    public function test_district_staff_cannot_open_admin_bills_dashboard(): void
    {
        [$district] = $this->createLockedBatchWithMember('staff', 'Staff Person');
        $staff = User::factory()->create([
            'role' => 'district_staff',
            'district_id' => $district->id,
            'hub_id' => $district->hub_id,
            'is_active' => true,
        ]);

        $this->actingAs($staff)
            ->get(route('admin.bills.dashboard'))
            ->assertForbidden();
    }

    public function test_state_admin_can_export_bills(): void
    {
        [$district, $batch, $cfa] = $this->createLockedBatchWithMember('export', 'Export Person');
        $this->makeBill($district, $batch, $cfa, 'EXP-1', 99);

        $admin = User::factory()->create(['role' => 'state_admin', 'is_active' => true]);

        $this->actingAs($admin)
            ->get(route('admin.bills.export'))
            ->assertOk();
    }

    public function test_state_admin_can_download_bill_from_dashboard(): void
    {
        Storage::fake('local');
        [$district, $batch, $cfa] = $this->createLockedBatchWithMember('doc', 'Doc Person');
        $bill = $this->makeBill($district, $batch, $cfa, 'DOC-1', 40);
        Storage::disk('local')->put($bill->document_path, 'pdf-bytes');

        $admin = User::factory()->create(['role' => 'state_admin', 'is_active' => true]);

        $this->actingAs($admin)
            ->get(route('admin.bills.document', $bill))
            ->assertOk();
    }

    /**
     * @return array{0: District, 1: OnboardingBatch, 2: CfaSubmission}
     */
    private function createLockedBatchWithMember(string $slug, string $name, string $applicationNo = ''): array
    {
        $hub = Hub::query()->create(['slug' => $slug.'-hub', 'name' => ucfirst($slug).' Hub', 'sort_order' => 1]);
        $district = District::query()->create([
            'hub_id' => $hub->id,
            'slug' => $slug.'-dist',
            'name' => ucfirst($slug).' District',
            'sort_order' => 1,
        ]);

        $cfaId = (int) DB::table('cfa_submissions')->insertGetId([
            'district_id' => $district->id,
            'application_no' => $applicationNo !== '' ? $applicationNo : 'APP-'.$slug,
            'applicant_name' => $name,
            'phone' => '90000'.str_pad((string) random_int(10000, 99999), 5, '0', STR_PAD_LEFT),
            'payload' => json_encode(['form_stage' => 'seed']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $batch = OnboardingBatch::query()->create([
            'hub_id' => $hub->id,
            'district_id' => $district->id,
            'name' => ucfirst($slug).' Batch',
            'target_size' => 80,
            'status' => 'locked',
            'locked_at' => now(),
            'onboarding_date' => now()->toDateString(),
        ]);

        OnboardingBatchCfa::query()->create([
            'onboarding_batch_id' => $batch->id,
            'cfa_submission_id' => $cfaId,
        ]);

        return [$district, $batch, CfaSubmission::query()->findOrFail($cfaId)];
    }

    private function makeBill(District $district, OnboardingBatch $batch, CfaSubmission $cfa, string $number, float $amount): IncubateeBill
    {
        return IncubateeBill::query()->create([
            'onboarding_batch_id' => $batch->id,
            'cfa_submission_id' => $cfa->id,
            'district_id' => $district->id,
            'bill_date' => now()->toDateString(),
            'amount' => $amount,
            'bill_number' => $number,
            'document_disk' => 'local',
            'document_path' => 'incubatee-bills/'.$cfa->id.'/'.$number.'.pdf',
            'document_original_name' => $number.'.pdf',
            'created_by' => null,
        ]);
    }
}
