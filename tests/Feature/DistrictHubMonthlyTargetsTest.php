<?php

namespace Tests\Feature;

use App\Models\Deliverable;
use App\Models\District;
use App\Models\DistrictDeliverableTarget;
use App\Models\DistrictMonthlyTarget;
use App\Models\FiscalYear;
use App\Models\Hub;
use App\Models\HubMonthlyTarget;
use App\Models\StateDeliverableTarget;
use App\Models\User;
use App\Services\DistrictHubMonthlyTargetPresetService;
use App\Services\DistrictHubMonthlyTargetsService;
use App\Services\MisMonthlyTargetIndicatorBootstrapService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DistrictHubMonthlyTargetsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(MisMonthlyTargetIndicatorBootstrapService::class)->ensureDeliverables();
    }

    public function test_page_renders_for_state_admin(): void
    {
        $admin = User::factory()->create(['role' => 'state_admin', 'is_active' => true]);

        $this->actingAs($admin)
            ->get(route('admin.targets.district-hub-monthly'))
            ->assertOk()
            ->assertSee('Monthly targets')
            ->assertSee('1.3')
            ->assertSee('Business Skills Training Sessions')
            ->assertSee('Hub only');
    }

    public function test_dropdown_only_lists_configured_mis_indicators(): void
    {
        Deliverable::query()->create([
            'sort_order' => 999,
            'code' => 'svc_not_allowed',
            'name' => 'Not Allowed Service',
            'mis_entry_label' => 'NO',
            'is_active' => true,
        ]);

        $admin = User::factory()->create(['role' => 'state_admin', 'is_active' => true]);

        $this->actingAs($admin)
            ->get(route('admin.targets.district-hub-monthly'))
            ->assertOk()
            ->assertSee('Awareness cum Outreach activities')
            ->assertDontSee('Not Allowed Service');
    }

    public function test_save_district_monthly_grid_for_field_work_indicator(): void
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

        $hub = Hub::query()->create(['slug' => 'mh-hub', 'name' => 'Test Hub', 'sort_order' => 1]);
        $district = District::query()->create([
            'hub_id' => $hub->id,
            'slug' => 'mh-district',
            'name' => 'Test District',
            'sort_order' => 1,
        ]);

        $deliverable = Deliverable::query()->where('code', 'field_work_workshops')->firstOrFail();

        DistrictDeliverableTarget::query()->create([
            'fiscal_year_id' => $fy->id,
            'district_id' => $district->id,
            'deliverable_id' => $deliverable->id,
            'target_total' => 20,
        ]);

        $months = [];
        foreach (range(1, 12) as $month) {
            $months[$month] = $month >= 3 ? 2 : 0;
        }

        $admin = User::factory()->create(['role' => 'state_admin', 'is_active' => true]);

        $this->actingAs($admin)
            ->post(route('admin.targets.district-hub-monthly.district'), [
                'fiscal_year_id' => $fy->id,
                'deliverable_id' => $deliverable->id,
                'districts' => [
                    $district->id => $months,
                ],
            ])
            ->assertRedirect(route('admin.targets.district-hub-monthly', [
                'fiscal_year_id' => $fy->id,
                'deliverable_id' => $deliverable->id,
                'tab' => DistrictHubMonthlyTargetsService::SCOPE_DISTRICT,
            ]))
            ->assertSessionHas('status');

        $this->assertSame(20, (int) DistrictMonthlyTarget::query()
            ->where('fiscal_year_id', $fy->id)
            ->where('district_id', $district->id)
            ->where('deliverable_id', $deliverable->id)
            ->sum('target_count'));
    }

    public function test_save_hub_monthly_grid_for_capacity_building(): void
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

        $hub = Hub::query()->create(['slug' => 'hub-monthly', 'name' => 'Kumaon Region', 'sort_order' => 1]);

        $deliverable = Deliverable::query()->where('code', 'capacity_building_stakeholders')->firstOrFail();

        StateDeliverableTarget::query()->create([
            'fiscal_year_id' => $fy->id,
            'deliverable_id' => $deliverable->id,
            'target_total' => 12,
        ]);

        $months = [1 => 0, 2 => 1, 3 => 0, 4 => 1, 5 => 0, 6 => 1, 7 => 0, 8 => 1, 9 => 0, 10 => 1, 11 => 1, 12 => 0];

        $admin = User::factory()->create(['role' => 'state_admin', 'is_active' => true]);

        $this->actingAs($admin)
            ->post(route('admin.targets.district-hub-monthly.hub'), [
                'fiscal_year_id' => $fy->id,
                'deliverable_id' => $deliverable->id,
                'hubs' => [
                    $hub->id => $months,
                ],
            ])
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertSame(6, (int) HubMonthlyTarget::query()
            ->where('fiscal_year_id', $fy->id)
            ->where('hub_id', $hub->id)
            ->where('deliverable_id', $deliverable->id)
            ->sum('target_count'));
    }

    public function test_page_shows_official_allocation_buttons_on_district_tab(): void
    {
        $admin = User::factory()->create(['role' => 'state_admin', 'is_active' => true]);

        $this->actingAs($admin)
            ->get(route('admin.targets.district-hub-monthly', ['tab' => DistrictHubMonthlyTargetsService::SCOPE_DISTRICT]))
            ->assertOk()
            ->assertSee('Load official allocation')
            ->assertSee('1.3 — Awareness cum Outreach activities')
            ->assertSee('1.3.1 — Outreach participants')
            ->assertSee('3.1 — Business Skills Training Sessions')
            ->assertSee('3.2 — BST participants');
    }

    public function test_apply_district_preset_loads_official_allocation(): void
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

        $hub = Hub::query()->create(['slug' => 'kumaon', 'name' => 'Kumaon', 'sort_order' => 1]);
        $district = District::query()->create([
            'hub_id' => $hub->id,
            'slug' => 'almora',
            'name' => 'Almora',
            'sort_order' => 1,
        ]);

        $deliverable = Deliverable::query()->where('code', 'field_work_workshops')->firstOrFail();
        $admin = User::factory()->create(['role' => 'state_admin', 'is_active' => true]);

        $this->actingAs($admin)
            ->post(route('admin.targets.district-hub-monthly.preset'), [
                'fiscal_year_id' => $fy->id,
                'deliverable_code' => 'field_work_workshops',
            ])
            ->assertRedirect(route('admin.targets.district-hub-monthly', [
                'fiscal_year_id' => $fy->id,
                'deliverable_id' => $deliverable->id,
                'tab' => DistrictHubMonthlyTargetsService::SCOPE_DISTRICT,
            ]))
            ->assertSessionHas('status');

        $expected = config('district_hub_monthly_target_presets.field_work_workshops.districts.almora');
        foreach (range(1, 12) as $month) {
            $this->assertSame(
                (int) $expected[$month - 1],
                (int) DistrictMonthlyTarget::query()
                    ->where('fiscal_year_id', $fy->id)
                    ->where('district_id', $district->id)
                    ->where('deliverable_id', $deliverable->id)
                    ->where('month_number', $month)
                    ->value('target_count'),
            );
        }

        $this->assertSame(
            array_sum($expected),
            (int) DistrictDeliverableTarget::query()
                ->where('fiscal_year_id', $fy->id)
                ->where('district_id', $district->id)
                ->where('deliverable_id', $deliverable->id)
                ->value('target_total'),
        );
    }

    public function test_preset_service_totals_match_spreadsheet(): void
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

        $hub = Hub::query()->create(['slug' => 'kumaon', 'name' => 'Kumaon', 'sort_order' => 1]);
        foreach (array_keys(config('district_hub_monthly_target_presets.field_work_participants.districts')) as $slug) {
            District::query()->create([
                'hub_id' => $hub->id,
                'slug' => $slug,
                'name' => ucfirst(str_replace('-', ' ', $slug)),
                'sort_order' => 1,
            ]);
        }

        $service = app(DistrictHubMonthlyTargetPresetService::class);

        $this->assertSame(10_000, $service->applyDistrictPreset((int) $fy->id, 'field_work_participants')['state_total']);
        $this->assertSame(350, $service->applyDistrictPreset((int) $fy->id, 'bst_sessions')['state_total']);
        $this->assertSame(7000, $service->applyDistrictPreset((int) $fy->id, 'bst_participations')['state_total']);
    }

    public function test_pending_lists_incomplete_monthly_plan(): void
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

        $deliverable = Deliverable::query()->where('code', 'bst_sessions')->firstOrFail();

        StateDeliverableTarget::query()->create([
            'fiscal_year_id' => $fy->id,
            'deliverable_id' => $deliverable->id,
            'target_total' => 100,
        ]);

        $pending = app(DistrictHubMonthlyTargetsService::class)->pendingDeliverables(
            (int) $fy->id,
            DistrictHubMonthlyTargetsService::SCOPE_DISTRICT,
        );

        $this->assertNotEmpty($pending);
        $this->assertContains(
            'bst_sessions',
            collect($pending)->pluck('deliverable.code')->all(),
        );
    }
}
