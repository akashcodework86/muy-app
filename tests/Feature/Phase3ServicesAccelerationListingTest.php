<?php

namespace Tests\Feature;

use App\Models\AccelerationServiceSession;
use App\Models\FiscalYear;
use App\Models\User;
use App\Support\AccelerationServicesApproval;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class Phase3ServicesAccelerationListingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('migrate', ['--path' => 'database/migrations/2026_07_06_120000_create_acceleration_service_tables.php']);
        $this->artisan('migrate', ['--path' => 'database/migrations/2026_07_10_120000_add_payload_to_acceleration_service_items.php']);
        $this->artisan('migrate', ['--path' => 'database/migrations/2026_07_15_120000_add_is_draft_to_acceleration_service_sessions.php']);
        $this->artisan('migrate', ['--path' => 'database/migrations/2026_07_17_120000_add_approval_workflow_to_acceleration_services.php']);
    }

    public function test_admin_phase3_services_lists_acceleration_approvals(): void
    {
        $admin = User::factory()->create(['role' => 'state_admin', 'is_active' => true]);
        $staff = User::factory()->create(['role' => 'district_staff', 'is_active' => true]);
        $fy = FiscalYear::query()->create([
            'code' => '2026-27',
            'name' => 'FY 2026-27',
            'starts_on' => '2026-04-01',
            'ends_on' => '2027-03-31',
            'is_active' => true,
        ]);

        $this->insertSession([
            'fiscal_year_id' => $fy->id,
            'submitted_by_user_id' => $staff->id,
            'submitted_by_name' => $staff->name,
            'applicant_name' => 'Accel Pending Applicant',
            'application_no' => 'ACCEL-PENDING-1',
            'district_name' => 'Haridwar',
            'status' => AccelerationServicesApproval::STATUS_PENDING_REVIEW,
            'is_draft' => false,
        ]);
        $this->insertSession([
            'fiscal_year_id' => $fy->id,
            'submitted_by_user_id' => $staff->id,
            'submitted_by_name' => $staff->name,
            'applicant_name' => 'Accel Approved Applicant',
            'application_no' => 'ACCEL-APPROVED-1',
            'district_name' => 'Haridwar',
            'status' => AccelerationServicesApproval::STATUS_APPROVED,
            'is_draft' => false,
        ]);
        $this->insertSession([
            'fiscal_year_id' => $fy->id,
            'submitted_by_user_id' => $staff->id,
            'submitted_by_name' => $staff->name,
            'applicant_name' => 'Accel Draft Applicant',
            'application_no' => 'ACCEL-DRAFT-1',
            'district_name' => 'Haridwar',
            'status' => AccelerationServicesApproval::STATUS_DRAFT,
            'is_draft' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.phase3-services.index', ['service_id' => AccelerationServiceSession::LIST_FILTER]))
            ->assertOk()
            ->assertSee('Accel Pending Applicant')
            ->assertSee('Accel Approved Applicant')
            ->assertSee(AccelerationServiceSession::SERVICE_LIST_LABEL)
            ->assertSee('Pending state review')
            ->assertSee('acceleration approvals')
            ->assertDontSee('Accel Draft Applicant');

        $this->actingAs($admin)
            ->get(route('admin.phase3-services.index', [
                'service_id' => AccelerationServiceSession::LIST_FILTER,
                'status' => 'pending_approval',
            ]))
            ->assertOk()
            ->assertSee('Accel Pending Applicant')
            ->assertDontSee('Accel Approved Applicant');
    }

    public function test_admin_phase3_services_month_filter_limits_to_selected_fy_month(): void
    {
        $admin = User::factory()->create(['role' => 'state_admin', 'is_active' => true]);
        $staff = User::factory()->create(['role' => 'district_staff', 'is_active' => true]);
        $fy = FiscalYear::query()->create([
            'code' => '2026-27',
            'name' => 'FY 2026-27',
            'starts_on' => '2026-04-01',
            'ends_on' => '2027-03-31',
            'is_active' => true,
        ]);

        $this->insertSession([
            'fiscal_year_id' => $fy->id,
            'submitted_by_user_id' => $staff->id,
            'submitted_by_name' => $staff->name,
            'applicant_name' => 'Accel July Applicant',
            'application_no' => 'ACCEL-JUL-1',
            'district_name' => 'Haridwar',
            'status' => AccelerationServicesApproval::STATUS_APPROVED,
            'is_draft' => false,
            'service_date' => '2026-07-15',
        ]);
        $this->insertSession([
            'fiscal_year_id' => $fy->id,
            'submitted_by_user_id' => $staff->id,
            'submitted_by_name' => $staff->name,
            'applicant_name' => 'Accel August Applicant',
            'application_no' => 'ACCEL-AUG-1',
            'district_name' => 'Haridwar',
            'status' => AccelerationServicesApproval::STATUS_APPROVED,
            'is_draft' => false,
            'service_date' => '2026-08-20',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.phase3-services.index', [
                'service_id' => AccelerationServiceSession::LIST_FILTER,
            ]))
            ->assertOk()
            ->assertSee('Apr 2026')
            ->assertSee('Mar 2027')
            ->assertSee('Accel July Applicant')
            ->assertSee('Accel August Applicant');

        $this->actingAs($admin)
            ->get(route('admin.phase3-services.index', [
                'service_id' => AccelerationServiceSession::LIST_FILTER,
                'month' => 8,
            ]))
            ->assertOk()
            ->assertSee('Accel August Applicant')
            ->assertDontSee('Accel July Applicant');
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function insertSession(array $overrides): int
    {
        return (int) DB::table('acceleration_service_sessions')->insertGetId(array_merge([
            'service_date' => '2026-08-01',
            'legacy_phase1_application_id' => 9001,
            'incubatee_key' => 'p1:9001',
            'incubatee_source' => 'phase1',
            'phone' => '9876543210',
            'onboard_label' => 'Onboarded',
            'counts_for_7_2' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }
}
