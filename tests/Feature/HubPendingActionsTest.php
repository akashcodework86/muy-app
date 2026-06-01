<?php

namespace Tests\Feature;

use App\Models\District;
use App\Models\Hub;
use App\Models\Service;
use App\Models\ServiceCase;
use App\Models\ServiceCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class HubPendingActionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_hub_admin_pending_actions_page_is_scoped_to_hub_districts(): void
    {
        $hubA = Hub::query()->create(['slug' => 'hub-a-pending', 'name' => 'Kumaon Region', 'sort_order' => 1]);
        $hubB = Hub::query()->create(['slug' => 'hub-b-pending', 'name' => 'Garhwal Region', 'sort_order' => 2]);

        $districtA = District::query()->create([
            'hub_id' => $hubA->id,
            'slug' => 'pauri-pending',
            'name' => 'Pauri Garhwal',
            'sort_order' => 1,
        ]);
        $districtB = District::query()->create([
            'hub_id' => $hubB->id,
            'slug' => 'dehradun-pending',
            'name' => 'Dehradun',
            'sort_order' => 1,
        ]);

        $staffA = User::factory()->create([
            'role' => 'district_staff',
            'hub_id' => $hubA->id,
            'district_id' => $districtA->id,
            'is_active' => true,
        ]);
        $staffB = User::factory()->create([
            'role' => 'district_staff',
            'hub_id' => $hubB->id,
            'district_id' => $districtB->id,
            'is_active' => true,
        ]);

        $hubAdmin = User::factory()->create([
            'role' => 'hub_admin',
            'hub_id' => $hubA->id,
            'is_active' => true,
        ]);

        $category = ServiceCategory::query()->create(['slug' => 'legal-pending', 'name' => 'Legal', 'sort_order' => 1]);
        $service = Service::query()->create([
            'service_category_id' => $category->id,
            'code' => 'gst_pending',
            'slug' => 'gst-pending',
            'name' => 'GST',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $cfaA = (int) DB::table('cfa_submissions')->insertGetId([
            'district_id' => $districtA->id,
            'applicant_name' => 'Hub A Applicant',
            'phone' => '9000000001',
            'payload' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $cfaB = (int) DB::table('cfa_submissions')->insertGetId([
            'district_id' => $districtB->id,
            'applicant_name' => 'Hub B Applicant',
            'phone' => '9000000002',
            'payload' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        ServiceCase::query()->create([
            'cfa_submission_id' => $cfaA,
            'service_id' => $service->id,
            'status' => ServiceCase::STATUS_PENDING_APPROVAL,
            'submitted_by' => $staffA->id,
            'submitted_at' => now(),
        ]);
        ServiceCase::query()->create([
            'cfa_submission_id' => $cfaB,
            'service_id' => $service->id,
            'status' => ServiceCase::STATUS_PENDING_APPROVAL,
            'submitted_by' => $staffB->id,
            'submitted_at' => now(),
        ]);

        $this->actingAs($hubAdmin)
            ->get(route('hub.pending-actions.index'))
            ->assertOk()
            ->assertSee('Pending Actions')
            ->assertSee('Kumaon Region')
            ->assertSee('Hub A Applicant')
            ->assertSee('Pauri Garhwal')
            ->assertDontSee('Hub B Applicant');
    }

    public function test_state_admin_still_sees_all_pending_actions(): void
    {
        $hub = Hub::query()->create(['slug' => 'state-pending-hub', 'name' => 'Hub', 'sort_order' => 1]);
        $district = District::query()->create([
            'hub_id' => $hub->id,
            'slug' => 'almora-pending',
            'name' => 'Almora',
            'sort_order' => 1,
        ]);
        $staff = User::factory()->create([
            'role' => 'district_staff',
            'hub_id' => $hub->id,
            'district_id' => $district->id,
            'is_active' => true,
        ]);
        $admin = User::factory()->create(['role' => 'state_admin', 'is_active' => true]);

        $category = ServiceCategory::query()->create(['slug' => 'biz-pending', 'name' => 'Business', 'sort_order' => 1]);
        $service = Service::query()->create([
            'service_category_id' => $category->id,
            'code' => 'fssai_pending',
            'slug' => 'fssai-pending',
            'name' => 'FSSAI',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $cfaId = (int) DB::table('cfa_submissions')->insertGetId([
            'district_id' => $district->id,
            'applicant_name' => 'State View Applicant',
            'phone' => '9000000003',
            'payload' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        ServiceCase::query()->create([
            'cfa_submission_id' => $cfaId,
            'service_id' => $service->id,
            'status' => ServiceCase::STATUS_PENDING_APPROVAL,
            'submitted_by' => $staff->id,
            'submitted_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.pending-actions.index'))
            ->assertOk()
            ->assertSee('State View Applicant');
    }
}
