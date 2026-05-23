<?php

namespace Tests\Feature;

use App\Models\Deliverable;
use App\Models\Designation;
use App\Models\District;
use App\Models\DistrictDeliverableTarget;
use App\Models\FiscalYear;
use App\Models\Hub;
use App\Models\StaffMonthlyTarget;
use App\Models\User;
use App\Services\ServiceTargetAllocationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceTargetAllocationTest extends TestCase
{
    use RefreshDatabase;

    public function test_allocate_by_service_page_renders_for_admin(): void
    {
        $admin = User::factory()->create(['role' => 'state_admin', 'is_active' => true]);

        $this->actingAs($admin)
            ->get(route('admin.targets.allocate-by-service'))
            ->assertOk()
            ->assertSee('Allocate staff targets by service');
    }

    public function test_apply_splits_district_target_equally_within_designation(): void
    {
        $fy = FiscalYear::query()->firstOrCreate(
            ['code' => '2026-27'],
            [
                'name' => 'FY 2026-27',
                'starts_on' => '2026-04-01',
                'ends_on' => '2027-03-31',
                'is_active' => true,
            ]
        );

        $hub = Hub::query()->create(['slug' => 'alloc-hub', 'name' => 'Hub', 'sort_order' => 1]);
        $district = District::query()->create([
            'hub_id' => $hub->id,
            'slug' => 'alloc-district',
            'name' => 'Alloc District',
            'sort_order' => 1,
        ]);

        $deliverable = Deliverable::query()->create([
            'sort_order' => 1,
            'code' => 'cfa',
            'name' => 'Call for Application',
            'mis_entry_label' => 'CFA',
            'is_active' => true,
        ]);

        DistrictDeliverableTarget::query()->create([
            'fiscal_year_id' => $fy->id,
            'district_id' => $district->id,
            'deliverable_id' => $deliverable->id,
            'target_total' => 2100,
        ]);

        $designation = Designation::query()->create(['name' => 'Field Coordinator', 'sort_order' => 1]);
        $staffA = User::factory()->create([
            'role' => 'district_staff',
            'hub_id' => $hub->id,
            'district_id' => $district->id,
            'designation_id' => $designation->id,
            'is_active' => true,
            'name' => 'FC One',
        ]);
        $staffB = User::factory()->create([
            'role' => 'district_staff',
            'hub_id' => $hub->id,
            'district_id' => $district->id,
            'designation_id' => $designation->id,
            'is_active' => true,
            'name' => 'FC Two',
        ]);

        $admin = User::factory()->create(['role' => 'state_admin', 'is_active' => true]);
        $key = app(ServiceTargetAllocationService::class)->designationKey($designation->id);

        $this->actingAs($admin)
            ->post(route('admin.targets.allocate-by-service.apply'), [
                'fiscal_year_id' => $fy->id,
                'district_id' => $district->id,
                'deliverable_id' => $deliverable->id,
                'percent' => [$key => 100],
            ])
            ->assertRedirect(route('admin.targets.allocate-by-service', [
                'fiscal_year_id' => $fy->id,
                'district_id' => $district->id,
                'deliverable_id' => $deliverable->id,
            ]))
            ->assertSessionHas('status');

        foreach ([$staffA, $staffB] as $staff) {
            $annual = (int) StaffMonthlyTarget::query()
                ->where('fiscal_year_id', $fy->id)
                ->where('user_id', $staff->id)
                ->where('deliverable_id', $deliverable->id)
                ->sum('target_count');

            $this->assertSame(1050, $annual);
            $this->assertSame(12, StaffMonthlyTarget::query()
                ->where('fiscal_year_id', $fy->id)
                ->where('user_id', $staff->id)
                ->where('deliverable_id', $deliverable->id)
                ->count());
        }
    }

    public function test_apply_rejects_percentages_not_summing_to_100(): void
    {
        $fy = FiscalYear::query()->firstOrCreate(
            ['code' => '2026-27'],
            [
                'name' => 'FY 2026-27',
                'starts_on' => '2026-04-01',
                'ends_on' => '2027-03-31',
                'is_active' => true,
            ]
        );

        $hub = Hub::query()->create(['slug' => 'pct-hub', 'name' => 'Hub', 'sort_order' => 1]);
        $district = District::query()->create([
            'hub_id' => $hub->id,
            'slug' => 'pct-district',
            'name' => 'Pct District',
            'sort_order' => 1,
        ]);

        $deliverable = Deliverable::query()->create([
            'sort_order' => 2,
            'code' => 'svc_gst',
            'name' => 'GST',
            'mis_entry_label' => 'GST',
            'is_active' => true,
        ]);

        DistrictDeliverableTarget::query()->create([
            'fiscal_year_id' => $fy->id,
            'district_id' => $district->id,
            'deliverable_id' => $deliverable->id,
            'target_total' => 100,
        ]);

        $designation = Designation::query()->create(['name' => 'Field Coordinator', 'sort_order' => 1]);
        User::factory()->create([
            'role' => 'district_staff',
            'hub_id' => $hub->id,
            'district_id' => $district->id,
            'designation_id' => $designation->id,
            'is_active' => true,
        ]);

        $admin = User::factory()->create(['role' => 'state_admin', 'is_active' => true]);
        $key = app(ServiceTargetAllocationService::class)->designationKey($designation->id);

        $this->actingAs($admin)
            ->from(route('admin.targets.allocate-by-service'))
            ->post(route('admin.targets.allocate-by-service.apply'), [
                'fiscal_year_id' => $fy->id,
                'district_id' => $district->id,
                'deliverable_id' => $deliverable->id,
                'percent' => [$key => 80],
            ])
            ->assertRedirect(route('admin.targets.allocate-by-service', [
                'fiscal_year_id' => $fy->id,
                'district_id' => $district->id,
                'deliverable_id' => $deliverable->id,
            ]))
            ->assertSessionHasErrors('percent');
    }

    public function test_service_split_helpers_preserve_totals(): void
    {
        $service = app(ServiceTargetAllocationService::class);

        $this->assertSame([4, 3, 3], $service->splitInteger(10, 3));
        $this->assertSame(10, array_sum($service->splitInteger(10, 3)));

        $byPercent = $service->splitByPercentages(2100, ['a' => 60, 'b' => 40]);
        $this->assertSame(2100, array_sum($byPercent));

        $months = $service->splitAnnualToMonths(1050);
        $this->assertCount(12, $months);
        $this->assertSame(1050, array_sum($months));
    }
}
