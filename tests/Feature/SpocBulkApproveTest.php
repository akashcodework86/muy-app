<?php

namespace Tests\Feature;

use App\Models\District;
use App\Models\DistrictServiceSpoc;
use App\Models\Hub;
use App\Models\Service;
use App\Models\ServiceCase;
use App\Models\ServiceCategory;
use App\Models\User;
use App\Services\AppSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SpocBulkApproveTest extends TestCase
{
    use RefreshDatabase;

    public function test_allowed_spoc_can_bulk_approve_pending_cases(): void
    {
        app(AppSettingsService::class)->setMany(['service_module.enabled' => true]);

        [$district, $staff, $spoc, $service] = $this->seedSpocContext('akash.b.shrivastava@pwc.com');
        $caseA = $this->createPendingCase($district, $service, 'SC-BULK-1');
        $caseB = $this->createPendingCase($district, $service, 'SC-BULK-2');

        $this->actingAs($spoc)
            ->post(route('spoc.service-cases.bulk-approve'), [
                'case_ids' => [$caseA->id, $caseB->id],
            ])
            ->assertRedirect(route('spoc.service-cases.index'))
            ->assertSessionHas('status');

        $this->assertSame(ServiceCase::STATUS_APPROVED, $caseA->fresh()->status);
        $this->assertSame(ServiceCase::STATUS_APPROVED, $caseB->fresh()->status);
        $this->assertSame((int) $spoc->id, (int) $caseA->fresh()->approved_by);
    }

    public function test_other_spoc_cannot_bulk_approve(): void
    {
        app(AppSettingsService::class)->setMany(['service_module.enabled' => true]);

        [$district, $staff, $spoc, $service] = $this->seedSpocContext('other.spoc@example.com');
        $case = $this->createPendingCase($district, $service, 'SC-BULK-3');

        $this->actingAs($spoc)
            ->post(route('spoc.service-cases.bulk-approve'), [
                'case_ids' => [$case->id],
            ])
            ->assertForbidden();

        $this->assertSame(ServiceCase::STATUS_PENDING_APPROVAL, $case->fresh()->status);
    }

    public function test_bulk_approve_ui_only_visible_to_allowed_spoc(): void
    {
        app(AppSettingsService::class)->setMany(['service_module.enabled' => true]);

        [$district, $staff, $allowedSpoc, $service] = $this->seedSpocContext('akash.b.shrivastava@pwc.com');
        $this->createPendingCase($district, $service, 'SC-BULK-UI-1');

        $this->actingAs($allowedSpoc)
            ->get(route('spoc.service-cases.index'))
            ->assertOk()
            ->assertSee('Select pending on page', false)
            ->assertSee('Approve selected', false);

        $otherSpoc = User::factory()->create([
            'role' => 'state_staff',
            'email' => 'another.spoc@example.com',
            'is_active' => true,
        ]);

        $this->actingAs($otherSpoc)
            ->get(route('spoc.service-cases.index'))
            ->assertOk()
            ->assertDontSee('Select pending on page', false)
            ->assertDontSee('Approve selected', false);
    }

    /**
     * @return array{0: District, 1: User, 2: User, 3: Service}
     */
    private function seedSpocContext(string $spocEmail): array
    {
        $district = $this->createDistrict();
        $staff = User::factory()->create([
            'role' => 'district_staff',
            'district_id' => $district->id,
            'is_active' => true,
        ]);
        $spoc = User::factory()->create([
            'role' => 'state_staff',
            'email' => $spocEmail,
            'is_active' => true,
        ]);
        DistrictServiceSpoc::query()->create([
            'district_id' => $district->id,
            'state_staff_user_id' => $spoc->id,
            'assigned_by' => $staff->id,
            'assigned_at' => now(),
        ]);

        $category = ServiceCategory::query()->create([
            'slug' => 'bulk_test_services',
            'name' => 'Bulk Test Services',
            'sort_order' => 1,
        ]);
        $service = Service::query()->create([
            'service_category_id' => $category->id,
            'code' => 'bulk_test_service',
            'name' => 'Bulk Test Service',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        return [$district, $staff, $spoc, $service];
    }

    private function createPendingCase(District $district, Service $service, string $reference): ServiceCase
    {
        $cfaId = (int) DB::table('cfa_submissions')->insertGetId([
            'district_id' => $district->id,
            'applicant_name' => 'Bulk Test Applicant',
            'phone' => '9999999911',
            'payload' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return ServiceCase::query()->create([
            'cfa_submission_id' => $cfaId,
            'service_id' => $service->id,
            'status' => ServiceCase::STATUS_PENDING_APPROVAL,
            'reference_number' => $reference,
            'submitted_at' => now(),
        ]);
    }

    private function createDistrict(): District
    {
        $hub = Hub::query()->create([
            'name' => 'Bulk Test Hub',
            'slug' => 'bulk-test-hub',
            'is_active' => true,
        ]);

        return District::query()->create([
            'hub_id' => $hub->id,
            'name' => 'Bulk Test District',
            'slug' => 'bulk-test-district',
            'is_active' => true,
        ]);
    }
}
