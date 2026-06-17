<?php

namespace Tests\Feature;

use App\Models\Deliverable;
use App\Models\District;
use App\Models\FiscalYear;
use App\Models\Hub;
use App\Models\OfficialDistrictMonthlyTarget;
use App\Models\OfficialStateMonthlyTarget;
use App\Models\User;
use App\Services\OfficialMonthlyTargetCodeResolver;
use App\Services\ServiceTargetDeliverableSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OfficialMonthlyTargetsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(ServiceTargetDeliverableSyncService::class)->syncAllServices();

        $kumaon = Hub::query()->create(['slug' => 'kumaon', 'name' => 'Kumaon', 'sort_order' => 1]);
        $garhwal = Hub::query()->create(['slug' => 'garhwal', 'name' => 'Garhwal', 'sort_order' => 2]);

        foreach ([
            [$kumaon->id, 'almora', 'Almora'],
            [$garhwal->id, 'dehradun', 'Dehradun'],
        ] as [$hubId, $slug, $name]) {
            District::query()->create([
                'hub_id' => $hubId,
                'slug' => $slug,
                'name' => $name,
                'sort_order' => 1,
            ]);
        }
    }

    public function test_2_1_and_2_1_1_map_to_distinct_deliverables(): void
    {
        app(ServiceTargetDeliverableSyncService::class)->syncAllServices();

        $resolver = app(OfficialMonthlyTargetCodeResolver::class);
        $parent = $resolver->deliverableForMisSerial('2.1', 'Incubatees Onboarded');
        $child = $resolver->deliverableForMisSerial('2.1.1', 'Onboarding of Potential Lakhpati Didi/ SHG Members/ CBOs*');

        $this->assertNotSame($parent->id, $child->id);
        $this->assertSame('onboarding', $parent->code);
        $this->assertSame('potential_lakhpati_onboarding', $child->code);
    }

    public function test_apply_district_block_from_official_config_matches_state_total_for_onboarding(): void
    {
        $fy = FiscalYear::query()->create([
            'code' => '2026-27',
            'name' => 'FY 2026-27',
            'starts_on' => '2026-04-01',
            'ends_on' => '2027-03-31',
            'is_active' => true,
        ]);

        $onboarding = app(OfficialMonthlyTargetCodeResolver::class)
            ->deliverableForMisSerial('2.1', 'Onboarding');

        foreach ([1 => 550, 2 => 650, 3 => 700] as $month => $count) {
            OfficialStateMonthlyTarget::query()->create([
                'fiscal_year_id' => $fy->id,
                'deliverable_id' => $onboarding->id,
                'month_number' => $month,
                'target_count' => $count,
            ]);
        }

        OfficialDistrictMonthlyTarget::query()->create([
            'fiscal_year_id' => $fy->id,
            'district_id' => District::query()->where('slug', 'almora')->value('id'),
            'deliverable_id' => $onboarding->id,
            'month_number' => 1,
            'target_count' => 99,
        ]);

        $result = app(\App\Services\OfficialDistrictMonthlyTargetService::class)
            ->applyDistrictBlockFromOfficialConfig($fy->id, '2.1', 'Onboarding');

        $this->assertSame(2, $result['districts']);
        $this->assertSame(525, OfficialDistrictMonthlyTarget::query()
            ->where('fiscal_year_id', $fy->id)
            ->where('deliverable_id', $onboarding->id)
            ->where('district_id', District::query()->where('slug', 'almora')->value('id'))
            ->sum('target_count'));
        $this->assertSame(525 + 595, $result['state_total']);
    }

    public function test_official_state_monthly_page_renders(): void
    {
        $admin = User::factory()->create(['role' => 'state_admin', 'is_active' => true]);

        $this->actingAs($admin)
            ->get(route('admin.targets.official-state-monthly'))
            ->assertOk()
            ->assertSee('State target month wise')
            ->assertSee('Put targets automatically')
            ->assertSee('Update targets')
            ->assertDontSee('Assign targets')
            ->assertSee('1.1')
            ->assertSee('Call for Application')
            ->assertSee('District allocated')
            ->assertSee('Alignment')
            ->assertSee('District target month wise');
    }

    public function test_official_state_update_writes_official_monthly_targets(): void
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

        $admin = User::factory()->create(['role' => 'state_admin', 'is_active' => true]);
        $targets = $this->officialStateTargetsPayload();

        $this->actingAs($admin)
            ->post(route('admin.targets.official-state-monthly.apply'), [
                'fiscal_year_id' => $fy->id,
                'targets' => $targets,
            ])
            ->assertRedirect(route('admin.targets.official-state-monthly', ['fiscal_year_id' => $fy->id]))
            ->assertSessionHas('status');

        $cfa = Deliverable::query()->where('code', 'cfa')->first();
        $this->assertNotNull($cfa);

        $this->assertSame(28000, (int) OfficialStateMonthlyTarget::query()
            ->where('fiscal_year_id', $fy->id)
            ->where('deliverable_id', $cfa->id)
            ->sum('target_count'));
    }

    public function test_official_state_update_accepts_json_payload(): void
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

        $admin = User::factory()->create(['role' => 'state_admin', 'is_active' => true]);
        $targets = $this->officialStateTargetsPayload();
        $cfa = Deliverable::query()->where('code', 'cfa')->firstOrFail();
        $payload = json_encode([(string) $cfa->id => $targets[(int) $cfa->id]]);

        $this->actingAs($admin)
            ->post(route('admin.targets.official-state-monthly.apply'), [
                'fiscal_year_id' => $fy->id,
                'targets_payload' => $payload,
            ])
            ->assertRedirect(route('admin.targets.official-state-monthly', ['fiscal_year_id' => $fy->id]))
            ->assertSessionHas('status');

        $this->assertSame(28000, (int) OfficialStateMonthlyTarget::query()
            ->where('fiscal_year_id', $fy->id)
            ->where('deliverable_id', $cfa->id)
            ->sum('target_count'));
    }

    public function test_official_district_monthly_page_renders(): void
    {
        $admin = User::factory()->create(['role' => 'state_admin', 'is_active' => true]);

        $this->actingAs($admin)
            ->get(route('admin.targets.official-district-monthly'))
            ->assertOk()
            ->assertSee('District target month wise')
            ->assertSee('Put targets automatically')
            ->assertSee('Update targets')
            ->assertSee('District allocation')
            ->assertSee('State target (saved)')
            ->assertSee('State target month wise')
            ->assertSee('Call for application')
            ->assertSee('Onboarding')
            ->assertSee('Onboarding of Potential Lakhpati Didi/ SHG Members/ CBOs*');
    }

    public function test_onboarding_and_lakhpati_district_blocks_map_to_distinct_deliverables(): void
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

        $viewData = app(\App\Services\OfficialDistrictMonthlyTargetService::class)->buildViewData((int) $fy->id);
        $onboardingBlock = collect($viewData['district_blocks'] ?? [])
            ->first(fn (array $block) => ($block['mis_serial'] ?? '') === '2.1' && ($block['name'] ?? '') === 'Onboarding');
        $lakhpatiBlock = collect($viewData['district_blocks'] ?? [])
            ->first(fn (array $block) => ($block['mis_serial'] ?? '') === '2.1.1');

        $this->assertNotNull($onboardingBlock);
        $this->assertNotNull($lakhpatiBlock);
        $this->assertTrue((bool) ($onboardingBlock['mapped'] ?? false));
        $this->assertTrue((bool) ($lakhpatiBlock['mapped'] ?? false));
        $this->assertNotSame(
            (int) ($onboardingBlock['deliverable']->id ?? 0),
            (int) ($lakhpatiBlock['deliverable']->id ?? 0),
        );
    }

    public function test_official_district_update_writes_official_district_monthly_targets(): void
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

        $admin = User::factory()->create(['role' => 'state_admin', 'is_active' => true]);
        $payload = $this->officialDistrictTargetsPayload();

        $this->actingAs($admin)
            ->post(route('admin.targets.official-district-monthly.apply'), array_merge(
                ['fiscal_year_id' => $fy->id],
                $payload,
            ))
            ->assertRedirect(route('admin.targets.official-district-monthly', ['fiscal_year_id' => $fy->id]))
            ->assertSessionHas('status');

        $cfa = Deliverable::query()->where('code', 'cfa')->first();
        $almora = District::query()->where('slug', 'almora')->first();
        $this->assertNotNull($cfa);
        $this->assertNotNull($almora);

        $this->assertSame(2100, (int) OfficialDistrictMonthlyTarget::query()
            ->where('fiscal_year_id', $fy->id)
            ->where('deliverable_id', $cfa->id)
            ->where('district_id', $almora->id)
            ->sum('target_count'));
    }

    public function test_official_district_update_accepts_json_payload(): void
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

        $admin = User::factory()->create(['role' => 'state_admin', 'is_active' => true]);
        $payloadData = $this->officialDistrictTargetsPayload();
        $cfa = Deliverable::query()->where('code', 'cfa')->firstOrFail();
        $almora = District::query()->where('slug', 'almora')->firstOrFail();
        $districtPayload = json_encode([
            'blocks' => [
                (string) $cfa->id => $payloadData['blocks'][(int) $cfa->id],
            ],
            'state_only' => [],
        ]);

        $this->actingAs($admin)
            ->post(route('admin.targets.official-district-monthly.apply'), [
                'fiscal_year_id' => $fy->id,
                'district_payload' => $districtPayload,
            ])
            ->assertRedirect(route('admin.targets.official-district-monthly', ['fiscal_year_id' => $fy->id]))
            ->assertSessionHas('status');

        $this->assertSame(2100, (int) OfficialDistrictMonthlyTarget::query()
            ->where('fiscal_year_id', $fy->id)
            ->where('deliverable_id', $cfa->id)
            ->where('district_id', $almora->id)
            ->sum('target_count'));
    }

    public function test_district_save_warns_when_allocation_does_not_match_state_target(): void
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

        $cfa = app(OfficialMonthlyTargetCodeResolver::class)
            ->deliverableForMisSerial('1.1', 'Call for application');
        $almora = District::query()->where('slug', 'almora')->firstOrFail();

        foreach (range(1, 12) as $month) {
            OfficialStateMonthlyTarget::query()->create([
                'fiscal_year_id' => $fy->id,
                'deliverable_id' => $cfa->id,
                'month_number' => $month,
                'target_count' => 100,
            ]);
        }

        $admin = User::factory()->create(['role' => 'state_admin', 'is_active' => true]);
        $payload = $this->officialDistrictTargetsPayload();

        $this->actingAs($admin)
            ->post(route('admin.targets.official-district-monthly.apply'), array_merge(
                ['fiscal_year_id' => $fy->id],
                $payload,
            ))
            ->assertRedirect(route('admin.targets.official-district-monthly', ['fiscal_year_id' => $fy->id]))
            ->assertSessionHas('status')
            ->assertSessionHasErrors('apply');

        $this->assertSame(2100, (int) OfficialDistrictMonthlyTarget::query()
            ->where('fiscal_year_id', $fy->id)
            ->where('deliverable_id', $cfa->id)
            ->where('district_id', $almora->id)
            ->sum('target_count'));
    }

    public function test_state_page_shows_district_alignment_for_split_services(): void
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

        $cfa = app(OfficialMonthlyTargetCodeResolver::class)
            ->deliverableForMisSerial('1.1', 'Call for application');
        $almora = District::query()->where('slug', 'almora')->firstOrFail();

        foreach (range(1, 12) as $month) {
            OfficialStateMonthlyTarget::query()->create([
                'fiscal_year_id' => $fy->id,
                'deliverable_id' => $cfa->id,
                'month_number' => $month,
                'target_count' => 100,
            ]);
            OfficialDistrictMonthlyTarget::query()->create([
                'fiscal_year_id' => $fy->id,
                'deliverable_id' => $cfa->id,
                'district_id' => $almora->id,
                'month_number' => $month,
                'target_count' => 100,
            ]);
        }

        $admin = User::factory()->create(['role' => 'state_admin', 'is_active' => true]);

        $this->actingAs($admin)
            ->get(route('admin.targets.official-state-monthly', ['fiscal_year_id' => $fy->id]))
            ->assertOk()
            ->assertSee('Match');
    }

    public function test_district_page_shows_mismatch_summary_cards_when_not_aligned(): void
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

        $cfa = app(OfficialMonthlyTargetCodeResolver::class)
            ->deliverableForMisSerial('1.1', 'Call for application');
        $almora = District::query()->where('slug', 'almora')->firstOrFail();

        foreach (range(1, 12) as $month) {
            OfficialStateMonthlyTarget::query()->create([
                'fiscal_year_id' => $fy->id,
                'deliverable_id' => $cfa->id,
                'month_number' => $month,
                'target_count' => 100,
            ]);
            OfficialDistrictMonthlyTarget::query()->create([
                'fiscal_year_id' => $fy->id,
                'deliverable_id' => $cfa->id,
                'district_id' => $almora->id,
                'month_number' => $month,
                'target_count' => 250,
            ]);
        }

        $admin = User::factory()->create(['role' => 'state_admin', 'is_active' => true]);

        $this->actingAs($admin)
            ->get(route('admin.targets.official-district-monthly', ['fiscal_year_id' => $fy->id]))
            ->assertOk()
            ->assertSee('State vs district alignment issues')
            ->assertSee('Call for application')
            ->assertSee('Over by');
    }

    /**
     * @return array<int, array<int, int>>
     */
    private function officialStateTargetsPayload(): array
    {
        $resolver = app(OfficialMonthlyTargetCodeResolver::class);
        $targets = [];

        foreach (config('official_state_monthly_targets.rows', []) as $row) {
            if (! is_array($row) || ($row['row_type'] ?? '') !== 'leaf') {
                continue;
            }

            try {
                $deliverable = $resolver->deliverableForMisSerial(
                    (string) ($row['serial'] ?? ''),
                    (string) ($row['name'] ?? ''),
                );
            } catch (\InvalidArgumentException) {
                continue;
            }

            $months = is_array($row['months'] ?? null) ? $row['months'] : [];
            for ($m = 1; $m <= 12; $m++) {
                $targets[(int) $deliverable->id][$m] = max(0, (int) ($months[$m - 1] ?? $months[$m] ?? 0));
            }
        }

        return $targets;
    }

    /**
     * @return array{blocks: array<int, array<string, mixed>>, state_only: array<int, array<int, int>>}
     */
    private function officialDistrictTargetsPayload(): array
    {
        $resolver = app(OfficialMonthlyTargetCodeResolver::class);
        $districtsBySlug = District::query()->pluck('id', 'slug')->all();
        $blocks = [];
        $stateOnly = [];

        foreach (config('official_district_monthly_targets.district_blocks', []) as $block) {
            if (! is_array($block) || ($block['mis_serial'] ?? '') === '') {
                continue;
            }

            try {
                $deliverable = $resolver->deliverableForMisSerial(
                    (string) $block['mis_serial'],
                    (string) ($block['name'] ?? ''),
                );
            } catch (\InvalidArgumentException) {
                continue;
            }

            $deliverableId = (int) $deliverable->id;
            $districtMonths = [];

            foreach ((array) ($block['districts'] ?? []) as $slug => $months) {
                $districtId = (int) ($districtsBySlug[$slug] ?? 0);
                if ($districtId <= 0 || ! is_array($months)) {
                    continue;
                }

                for ($m = 1; $m <= 12; $m++) {
                    $districtMonths[$districtId][$m] = max(0, (int) ($months[$m - 1] ?? $months[$m] ?? 0));
                }
            }

            if ($districtMonths !== []) {
                $blocks[$deliverableId] = ['districts' => $districtMonths];
            }
        }

        foreach (config('official_district_monthly_targets.state_only_rows', []) as $row) {
            if (! is_array($row)) {
                continue;
            }

            try {
                $deliverable = $resolver->deliverableForMisSerial(
                    (string) ($row['mis_serial'] ?? ''),
                    (string) ($row['name'] ?? ''),
                );
            } catch (\InvalidArgumentException) {
                continue;
            }

            $months = is_array($row['months'] ?? null) ? $row['months'] : [];
            for ($m = 1; $m <= 12; $m++) {
                $stateOnly[(int) $deliverable->id][$m] = max(0, (int) ($months[$m - 1] ?? $months[$m] ?? 0));
            }
        }

        return ['blocks' => $blocks, 'state_only' => $stateOnly];
    }
}
