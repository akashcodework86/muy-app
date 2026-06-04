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

    public function test_saved_designation_percents_restore_on_page_reload(): void
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

        $hub = Hub::query()->create(['slug' => 'save-hub', 'name' => 'Hub', 'sort_order' => 1]);
        $district = District::query()->create([
            'hub_id' => $hub->id,
            'slug' => 'save-district',
            'name' => 'Save District',
            'sort_order' => 1,
        ]);

        $deliverable = Deliverable::query()->create([
            'sort_order' => 3,
            'code' => 'svc_save',
            'name' => 'Save Test Service',
            'mis_entry_label' => 'SAVE',
            'is_active' => true,
        ]);

        DistrictDeliverableTarget::query()->create([
            'fiscal_year_id' => $fy->id,
            'district_id' => $district->id,
            'deliverable_id' => $deliverable->id,
            'target_total' => 1000,
        ]);

        $designationA = Designation::query()->create(['name' => 'Role A', 'sort_order' => 1]);
        $designationB = Designation::query()->create(['name' => 'Role B', 'sort_order' => 2]);
        User::factory()->create([
            'role' => 'district_staff',
            'hub_id' => $hub->id,
            'district_id' => $district->id,
            'designation_id' => $designationA->id,
            'is_active' => true,
        ]);
        User::factory()->create([
            'role' => 'district_staff',
            'hub_id' => $hub->id,
            'district_id' => $district->id,
            'designation_id' => $designationB->id,
            'is_active' => true,
        ]);

        $admin = User::factory()->create(['role' => 'state_admin', 'is_active' => true]);
        $service = app(ServiceTargetAllocationService::class);
        $keyA = $service->designationKey($designationA->id);
        $keyB = $service->designationKey($designationB->id);

        $this->actingAs($admin)
            ->post(route('admin.targets.allocate-by-service.apply'), [
                'fiscal_year_id' => $fy->id,
                'district_id' => $district->id,
                'deliverable_id' => $deliverable->id,
                'percent' => [$keyA => 70, $keyB => 30],
            ])
            ->assertRedirect();

        $url = route('admin.targets.allocate-by-service', [
            'fiscal_year_id' => $fy->id,
            'district_id' => $district->id,
            'deliverable_id' => $deliverable->id,
        ]);

        $this->actingAs($admin)
            ->get($url)
            ->assertOk()
            ->assertSee('name="percent['.$keyA.']"', false)
            ->assertSee('value="70"', false)
            ->assertSee('value="30"', false);
    }

    public function test_apply_accepts_manual_month_overrides(): void
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

        $hub = Hub::query()->create(['slug' => 'manual-hub', 'name' => 'Hub', 'sort_order' => 1]);
        $district = District::query()->create([
            'hub_id' => $hub->id,
            'slug' => 'manual-district',
            'name' => 'Manual District',
            'sort_order' => 1,
        ]);

        $deliverable = Deliverable::query()->create([
            'sort_order' => 4,
            'code' => 'svc_manual',
            'name' => 'Manual Service',
            'mis_entry_label' => 'MAN',
            'is_active' => true,
        ]);

        DistrictDeliverableTarget::query()->create([
            'fiscal_year_id' => $fy->id,
            'district_id' => $district->id,
            'deliverable_id' => $deliverable->id,
            'target_total' => 18,
        ]);

        $designation = Designation::query()->create(['name' => 'Field Coordinator', 'sort_order' => 1]);
        $staff = User::factory()->create([
            'role' => 'district_staff',
            'hub_id' => $hub->id,
            'district_id' => $district->id,
            'designation_id' => $designation->id,
            'is_active' => true,
        ]);

        $admin = User::factory()->create(['role' => 'state_admin', 'is_active' => true]);
        $key = app(ServiceTargetAllocationService::class)->designationKey($designation->id);

        $months = [];
        foreach (range(1, 12) as $month) {
            $months[$month] = $month <= 6 ? 3 : 0;
        }

        $this->actingAs($admin)
            ->post(route('admin.targets.allocate-by-service.apply'), [
                'fiscal_year_id' => $fy->id,
                'district_id' => $district->id,
                'deliverable_id' => $deliverable->id,
                'percent' => [$key => 100],
                'months' => [
                    $staff->id => $months,
                ],
            ])
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertSame(18, (int) StaffMonthlyTarget::query()
            ->where('fiscal_year_id', $fy->id)
            ->where('user_id', $staff->id)
            ->where('deliverable_id', $deliverable->id)
            ->sum('target_count'));

        $this->assertSame(3, (int) StaffMonthlyTarget::query()
            ->where('fiscal_year_id', $fy->id)
            ->where('user_id', $staff->id)
            ->where('deliverable_id', $deliverable->id)
            ->where('month_number', 1)
            ->value('target_count'));
    }

    public function test_apply_rejects_manual_months_not_matching_district_target(): void
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

        $hub = Hub::query()->create(['slug' => 'bad-manual-hub', 'name' => 'Hub', 'sort_order' => 1]);
        $district = District::query()->create([
            'hub_id' => $hub->id,
            'slug' => 'bad-manual-district',
            'name' => 'Bad Manual District',
            'sort_order' => 1,
        ]);

        $deliverable = Deliverable::query()->create([
            'sort_order' => 5,
            'code' => 'svc_bad_manual',
            'name' => 'Bad Manual Service',
            'mis_entry_label' => 'BAD',
            'is_active' => true,
        ]);

        DistrictDeliverableTarget::query()->create([
            'fiscal_year_id' => $fy->id,
            'district_id' => $district->id,
            'deliverable_id' => $deliverable->id,
            'target_total' => 18,
        ]);

        $designation = Designation::query()->create(['name' => 'Field Coordinator', 'sort_order' => 1]);
        $staff = User::factory()->create([
            'role' => 'district_staff',
            'hub_id' => $hub->id,
            'district_id' => $district->id,
            'designation_id' => $designation->id,
            'is_active' => true,
        ]);

        $admin = User::factory()->create(['role' => 'state_admin', 'is_active' => true]);
        $key = app(ServiceTargetAllocationService::class)->designationKey($designation->id);

        $months = array_fill(1, 12, 1);

        $this->actingAs($admin)
            ->from(route('admin.targets.allocate-by-service'))
            ->post(route('admin.targets.allocate-by-service.apply'), [
                'fiscal_year_id' => $fy->id,
                'district_id' => $district->id,
                'deliverable_id' => $deliverable->id,
                'percent' => [$key => 100],
                'months' => [
                    $staff->id => $months,
                ],
            ])
            ->assertRedirect(route('admin.targets.allocate-by-service', [
                'fiscal_year_id' => $fy->id,
                'district_id' => $district->id,
                'deliverable_id' => $deliverable->id,
            ]))
            ->assertSessionHasErrors('months');
    }

    public function test_index_shows_saved_staff_monthly_targets_after_apply(): void
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

        $hub = Hub::query()->create(['slug' => 'reload-hub', 'name' => 'Hub', 'sort_order' => 1]);
        $district = District::query()->create([
            'hub_id' => $hub->id,
            'slug' => 'reload-district',
            'name' => 'Reload District',
            'sort_order' => 1,
        ]);

        $deliverable = Deliverable::query()->create([
            'sort_order' => 6,
            'code' => 'svc_reload',
            'name' => 'Reload Service',
            'mis_entry_label' => 'REL',
            'is_active' => true,
        ]);

        DistrictDeliverableTarget::query()->create([
            'fiscal_year_id' => $fy->id,
            'district_id' => $district->id,
            'deliverable_id' => $deliverable->id,
            'target_total' => 18,
        ]);

        $designation = Designation::query()->create(['name' => 'Field Coordinator', 'sort_order' => 1]);
        $staff = User::factory()->create([
            'role' => 'district_staff',
            'hub_id' => $hub->id,
            'district_id' => $district->id,
            'designation_id' => $designation->id,
            'is_active' => true,
            'name' => 'Reload Staff',
        ]);

        $admin = User::factory()->create(['role' => 'state_admin', 'is_active' => true]);
        $key = app(ServiceTargetAllocationService::class)->designationKey($designation->id);

        $months = [];
        foreach (range(1, 12) as $month) {
            $months[$month] = $month <= 6 ? 3 : 0;
        }

        $this->actingAs($admin)
            ->post(route('admin.targets.allocate-by-service.apply'), [
                'fiscal_year_id' => $fy->id,
                'district_id' => $district->id,
                'deliverable_id' => $deliverable->id,
                'percent' => [$key => 100],
                'months' => [
                    $staff->id => $months,
                ],
            ])
            ->assertRedirect();

        $url = route('admin.targets.allocate-by-service', [
            'fiscal_year_id' => $fy->id,
            'district_id' => $district->id,
            'deliverable_id' => $deliverable->id,
        ]);

        $response = $this->actingAs($admin)->get($url);

        $response->assertOk()
            ->assertSee('saved staff targets', false)
            ->assertSee('preferSaved = true', false);

        $html = $response->getContent();
        $this->assertIsString($html);
        $this->assertStringContainsString('"user_id":'.$staff->id, $html);
        $this->assertStringContainsString('"annual_total":18', $html);
        $this->assertMatchesRegularExpression('/"months":\{[^}]*"1":3/', $html);
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
