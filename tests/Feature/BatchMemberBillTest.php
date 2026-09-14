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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BatchMemberBillTest extends TestCase
{
    use RefreshDatabase;

    public function test_district_staff_sees_add_bill_on_batch_members(): void
    {
        [$district, $batch, $cfa] = $this->createLockedBatchWithMember();
        $staff = $this->staffFor($district);

        $this->actingAs($staff)
            ->get(route('staff.batches.show', $batch))
            ->assertOk()
            ->assertSee('Add bill')
            ->assertSee(route('staff.batches.members.bills', [$batch, $cfa]), false);
    }

    public function test_district_staff_can_open_add_bill_page(): void
    {
        [$district, $batch, $cfa] = $this->createLockedBatchWithMember();
        $staff = $this->staffFor($district);

        $this->actingAs($staff)
            ->get(route('staff.batches.members.bills', [$batch, $cfa]))
            ->assertOk()
            ->assertSee('Add bill')
            ->assertSee('No bills added yet for this member')
            ->assertSee('Add more bill')
            ->assertSee($cfa->applicant_name);
    }

    public function test_district_staff_can_add_multiple_bills_and_stays_on_page(): void
    {
        Storage::fake('local');

        [$district, $batch, $cfa] = $this->createLockedBatchWithMember();
        $staff = $this->staffFor($district);
        $date = now()->toDateString();

        $response = $this->actingAs($staff)->post(route('staff.batches.members.bills.store', [$batch, $cfa]), [
            'bills' => [
                [
                    'bill_date' => $date,
                    'amount' => '1250.50',
                    'bill_number' => 'BILL-001',
                    'document' => UploadedFile::fake()->create('bill-one.pdf', 40, 'application/pdf'),
                ],
                [
                    'bill_date' => $date,
                    'amount' => '80',
                    'bill_number' => 'BILL-002',
                    'document' => UploadedFile::fake()->create('bill-two.jpg', 30, 'image/jpeg'),
                ],
            ],
        ]);

        $response
            ->assertRedirect(route('staff.batches.members.bills', [$batch, $cfa]))
            ->assertSessionHas('status', '2 bills added.');

        $this->assertSame(2, IncubateeBill::query()->count());
        $this->assertDatabaseHas('incubatee_bills', [
            'cfa_submission_id' => $cfa->id,
            'onboarding_batch_id' => $batch->id,
            'bill_number' => 'BILL-001',
            'created_by' => $staff->id,
        ]);

        $saved = IncubateeBill::query()->where('bill_number', 'BILL-001')->first();
        $this->assertNotNull($saved);
        $this->assertTrue($saved->hasDocument());
        Storage::disk('local')->assertExists($saved->document_path);

        $this->actingAs($staff)
            ->get(route('staff.batches.members.bills', [$batch, $cfa]))
            ->assertOk()
            ->assertSee('BILL-001')
            ->assertSee('BILL-002')
            ->assertSee('₹ 1,250.50')
            ->assertSee('One thousand two hundred and fifty and fifty paise', false);
    }

    public function test_duplicate_bill_number_is_rejected(): void
    {
        Storage::fake('local');

        [$district, $batch, $cfa] = $this->createLockedBatchWithMember();
        $staff = $this->staffFor($district);

        IncubateeBill::query()->create([
            'onboarding_batch_id' => $batch->id,
            'cfa_submission_id' => $cfa->id,
            'district_id' => $district->id,
            'bill_date' => now()->toDateString(),
            'amount' => 10,
            'bill_number' => 'DUP-9',
            'document_disk' => 'local',
            'document_path' => 'incubatee-bills/existing.pdf',
            'created_by' => $staff->id,
        ]);

        $this->actingAs($staff)
            ->from(route('staff.batches.members.bills', [$batch, $cfa]))
            ->post(route('staff.batches.members.bills.store', [$batch, $cfa]), [
                'bills' => [
                    [
                        'bill_date' => now()->toDateString(),
                        'amount' => '20',
                        'bill_number' => 'dup-9',
                        'document' => UploadedFile::fake()->create('new.pdf', 20, 'application/pdf'),
                    ],
                ],
            ])
            ->assertRedirect(route('staff.batches.members.bills', [$batch, $cfa]))
            ->assertSessionHasErrors(['bills.0.bill_number']);

        $this->assertSame(1, IncubateeBill::query()->count());
    }

    public function test_bill_date_outside_current_month_is_rejected(): void
    {
        Storage::fake('local');

        [$district, $batch, $cfa] = $this->createLockedBatchWithMember();
        $staff = $this->staffFor($district);

        $this->actingAs($staff)
            ->from(route('staff.batches.members.bills', [$batch, $cfa]))
            ->post(route('staff.batches.members.bills.store', [$batch, $cfa]), [
                'bills' => [
                    [
                        'bill_date' => now()->copy()->subMonth()->startOfMonth()->toDateString(),
                        'amount' => '20',
                        'bill_number' => 'OLD-1',
                        'document' => UploadedFile::fake()->create('old.pdf', 20, 'application/pdf'),
                    ],
                ],
            ])
            ->assertRedirect(route('staff.batches.members.bills', [$batch, $cfa]))
            ->assertSessionHasErrors(['bills.0.bill_date']);
    }

    public function test_staff_from_another_district_cannot_add_bills(): void
    {
        [$district, $batch, $cfa] = $this->createLockedBatchWithMember();
        $otherHub = Hub::query()->create(['slug' => 'other-hub', 'name' => 'Other Hub', 'sort_order' => 2]);
        $otherDistrict = District::query()->create([
            'hub_id' => $otherHub->id,
            'slug' => 'other-dist',
            'name' => 'Other District',
            'sort_order' => 1,
        ]);
        $otherStaff = $this->staffFor($otherDistrict);

        $this->actingAs($otherStaff)
            ->get(route('staff.batches.members.bills', [$batch, $cfa]))
            ->assertForbidden();
    }

    public function test_state_admin_cannot_open_add_bill_page(): void
    {
        [$district, $batch, $cfa] = $this->createLockedBatchWithMember();
        $admin = User::factory()->create([
            'role' => 'state_admin',
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('staff.batches.members.bills', [$batch, $cfa]))
            ->assertForbidden();
    }

    public function test_district_staff_can_download_bill_document(): void
    {
        Storage::fake('local');

        [$district, $batch, $cfa] = $this->createLockedBatchWithMember();
        $staff = $this->staffFor($district);

        $this->actingAs($staff)->post(route('staff.batches.members.bills.store', [$batch, $cfa]), [
            'bills' => [
                [
                    'bill_date' => now()->toDateString(),
                    'amount' => '99',
                    'bill_number' => 'DL-1',
                    'document' => UploadedFile::fake()->create('receipt.pdf', 20, 'application/pdf'),
                ],
            ],
        ])->assertRedirect();

        $bill = IncubateeBill::query()->first();
        $this->assertNotNull($bill);

        $this->actingAs($staff)
            ->get(route('staff.batches.members.bills.document', [$batch, $cfa, $bill]))
            ->assertOk();
    }

    /**
     * @return array{0: District, 1: OnboardingBatch, 2: CfaSubmission}
     */
    private function createLockedBatchWithMember(): array
    {
        $hub = Hub::query()->create(['slug' => 'bill-hub', 'name' => 'Hub', 'sort_order' => 1]);
        $district = District::query()->create([
            'hub_id' => $hub->id,
            'slug' => 'bill-dist',
            'name' => 'Bill District',
            'sort_order' => 1,
        ]);

        $cfaId = (int) DB::table('cfa_submissions')->insertGetId([
            'district_id' => $district->id,
            'application_no' => '40804001',
            'applicant_name' => 'Suman Devi',
            'phone' => '9000000100',
            'payload' => json_encode(['form_stage' => 'seed']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $batch = OnboardingBatch::query()->create([
            'hub_id' => $hub->id,
            'district_id' => $district->id,
            'name' => 'Batch Bills Test',
            'target_size' => 80,
            'status' => 'locked',
            'locked_at' => now(),
            'onboarding_date' => now()->toDateString(),
        ]);

        OnboardingBatchCfa::query()->create([
            'onboarding_batch_id' => $batch->id,
            'cfa_submission_id' => $cfaId,
        ]);

        $cfa = CfaSubmission::query()->findOrFail($cfaId);

        return [$district, $batch, $cfa];
    }

    private function staffFor(District $district): User
    {
        return User::factory()->create([
            'role' => 'district_staff',
            'district_id' => $district->id,
            'hub_id' => $district->hub_id,
            'is_active' => true,
        ]);
    }
}
