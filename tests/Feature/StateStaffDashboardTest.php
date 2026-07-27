<?php

namespace Tests\Feature;

use App\Models\District;
use App\Models\DistrictServiceSpoc;
use App\Models\Hub;
use App\Models\MarketLinkageSubmission;
use App\Models\Service;
use App\Models\ServiceCase;
use App\Models\ServiceCategory;
use App\Models\User;
use App\Services\AppSettingsService;
use App\Services\SpocApprovalQueueStatsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StateStaffDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_state_staff_dashboard_uses_operational_full_width_content(): void
    {
        $user = User::factory()->create([
            'role' => 'state_staff',
            'is_active' => true,
            'name' => 'State SPOC',
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Welcome back, State SPOC')
            ->assertSee('Your approval queue')
            ->assertSee('Assigned districts')
            ->assertSee('Quick actions')
            ->assertSee('Open approval queue')
            ->assertDontSee('Wait for the state admin to assign you one or more districts');
    }

    public function test_dashboard_pending_and_approved_match_service_cases_queue(): void
    {
        app(AppSettingsService::class)->setMany(['service_module.enabled' => true]);

        $district = $this->createDistrict();
        $staff = User::factory()->create([
            'role' => 'district_staff',
            'district_id' => $district->id,
            'is_active' => true,
        ]);
        $otherSpoc = User::factory()->create([
            'role' => 'state_staff',
            'is_active' => true,
        ]);
        $spoc = User::factory()->create([
            'role' => 'state_staff',
            'email' => 'ankur.rawat@pwc.com',
            'is_active' => true,
            'name' => 'Queue SPOC',
        ]);
        DistrictServiceSpoc::query()->create([
            'district_id' => $district->id,
            'state_staff_user_id' => $spoc->id,
            'assigned_by' => $staff->id,
            'assigned_at' => now(),
        ]);

        $category = ServiceCategory::query()->create([
            'slug' => 'dashboard-queue-services',
            'name' => 'Dashboard Queue Services',
            'sort_order' => 1,
        ]);
        $service = Service::query()->create([
            'service_category_id' => $category->id,
            'code' => 'dashboard_queue_service',
            'name' => 'Dashboard Queue Service',
            'sort_order' => 1,
            'is_active' => true,
            'requires_approval' => true,
        ]);

        $pendingCase = $this->createCase($district, $service, ServiceCase::STATUS_PENDING_APPROVAL, 'SC-DASH-P1');
        $approvedByOther = $this->createCase($district, $service, ServiceCase::STATUS_APPROVED, 'SC-DASH-A1');
        $approvedByOther->forceFill([
            'approved_by' => $otherSpoc->id,
            'approved_at' => now(),
        ])->save();
        $approvedBySpoc = $this->createCase($district, $service, ServiceCase::STATUS_APPROVED, 'SC-DASH-A2');
        $approvedBySpoc->forceFill([
            'approved_by' => $spoc->id,
            'approved_at' => now(),
        ])->save();

        if (MarketLinkageSubmission::supportsWorkflow()) {
            MarketLinkageSubmission::query()->create([
                'submitted_by_user_id' => $staff->id,
                'submitted_by_name' => $staff->name,
                'district_id' => $district->id,
                'district_name' => $district->name,
                'cfa_submission_id' => $pendingCase->cfa_submission_id,
                'incubatee_name' => 'ML Pending Incubatee',
                'application_no' => 'APP-ML-P1',
                'status' => ServiceCase::STATUS_PENDING_APPROVAL,
                'spoc_user_id' => $spoc->id,
                'submitted_at' => now(),
            ]);
            MarketLinkageSubmission::query()->create([
                'submitted_by_user_id' => $staff->id,
                'submitted_by_name' => $staff->name,
                'district_id' => $district->id,
                'district_name' => $district->name,
                'cfa_submission_id' => $approvedByOther->cfa_submission_id,
                'incubatee_name' => 'ML Approved Incubatee',
                'application_no' => 'APP-ML-A1',
                'status' => ServiceCase::STATUS_APPROVED,
                'spoc_user_id' => $spoc->id,
                'submitted_at' => now()->subDay(),
                'approved_at' => now(),
                'approved_by' => $otherSpoc->id,
            ]);
        }

        $stats = app(SpocApprovalQueueStatsService::class)->forSpoc($spoc);

        $expectedPending = 1; // service case
        if (MarketLinkageSubmission::supportsWorkflow()) {
            $expectedPending += 1;
        }
        $this->assertSame($expectedPending, $stats['pending']);

        // Matches service-cases "Approved" tab: all approved in queue, not only approved_by = me.
        $expectedApproved = 2; // two service cases
        if (MarketLinkageSubmission::supportsWorkflow()) {
            $expectedApproved += 1;
        }
        $this->assertSame($expectedApproved, $stats['approved']);

        $this->actingAs($spoc)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Pending approvals', false)
            ->assertSee('Approved', false)
            ->assertDontSee('Approved by you', false)
            ->assertSee('>'.number_format($expectedPending).'<', false)
            ->assertSee('>'.number_format($expectedApproved).'<', false);

        $this->actingAs($spoc)
            ->get(route('spoc.service-cases.index'))
            ->assertOk()
            ->assertSee('Pending approval ('.number_format($expectedPending).')', false)
            ->assertSee('Approved ('.number_format($expectedApproved).')', false);
    }

    private function createCase(District $district, Service $service, string $status, string $reference): ServiceCase
    {
        $cfaId = (int) DB::table('cfa_submissions')->insertGetId([
            'district_id' => $district->id,
            'applicant_name' => 'Dashboard Applicant '.$reference,
            'phone' => '9999999900',
            'payload' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return ServiceCase::query()->create([
            'cfa_submission_id' => $cfaId,
            'service_id' => $service->id,
            'status' => $status,
            'reference_number' => $reference,
            'submitted_at' => now(),
        ]);
    }

    private function createDistrict(): District
    {
        $hub = Hub::query()->create([
            'name' => 'Dashboard Test Hub',
            'slug' => 'dashboard-test-hub',
            'is_active' => true,
        ]);

        return District::query()->create([
            'hub_id' => $hub->id,
            'name' => 'Dashboard Test District',
            'slug' => 'dashboard-test-district',
            'is_active' => true,
        ]);
    }
}
