<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\StateAdminDashboardService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use ReflectionClass;
use Tests\TestCase;

class StateAdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_state_admin_dashboard_loads(): void
    {
        $admin = User::factory()->create(['role' => 'state_admin', 'is_active' => true]);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Welcome')
            ->assertSee('sadGroundTicker', false)
            ->assertSee('sad-stat-chips', false)
            ->assertSee('Field Highlights')
            ->assertSee('Approved field activity photos', false)
            ->assertSee('data-sad-pulse-tab="pace"', false)
            ->assertSee('Daily 14d')
            ->assertSee('Top Products — Onboarded Incubatees')
            ->assertSee('No onboarded product data yet')
            ->assertSee('CFA', false)
            ->assertDontSee('Classic theme')
            ->assertDontSee('FY 2026-27');
    }

    public function test_state_admin_theme_toggle_is_in_profile_menu(): void
    {
        $admin = User::factory()->create(['role' => 'state_admin', 'is_active' => true]);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Switch to classic theme');
    }

    public function test_state_admin_cfa_index_uses_revamp_theme(): void
    {
        $admin = User::factory()->create(['role' => 'state_admin', 'is_active' => true]);

        $this->actingAs($admin)
            ->get(route('admin.cfa.index'))
            ->assertOk()
            ->assertSee('admin-app-body--state-theme-revamp', false);
    }

    public function test_state_admin_theme_persists_on_admin_pages(): void
    {
        $admin = User::factory()->create(['role' => 'state_admin', 'is_active' => true]);

        $this->actingAs($admin)
            ->get(route('dashboard', ['theme' => 'legacy']))
            ->assertOk();

        $this->actingAs($admin)
            ->get(route('admin.cfa.index'))
            ->assertOk()
            ->assertSee('admin-app-body--state-theme-legacy', false);
    }

    public function test_onboarded_product_mix_uses_locked_phase_three_batches_and_normalizes_products(): void
    {
        $now = now();
        $hubId = DB::table('hubs')->insertGetId([
            'slug' => 'test-hub', 'name' => 'Test Hub', 'sort_order' => 1,
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $districtId = DB::table('districts')->insertGetId([
            'hub_id' => $hubId, 'slug' => 'test-district', 'name' => 'Test District', 'sort_order' => 1,
            'created_at' => $now, 'updated_at' => $now,
        ]);

        $makeCfa = function (string $applicationNo, array $payload) use ($districtId, $now): int {
            return DB::table('cfa_submissions')->insertGetId([
                'application_no' => $applicationNo,
                'district_id' => $districtId,
                'source' => 'test',
                'applicant_name' => $applicationNo,
                'phone' => '9999999999',
                'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        };

        $includedIds = [
            $makeCfa('PROD-1', ['product' => 'Pickle']),
            $makeCfa('PROD-2', ['product' => ' pickle ']),
            $makeCfa('PROD-3', ['product' => 'Others', 'other_product' => 'Chocolate Making']),
            $makeCfa('PROD-4', []),
        ];
        $makeCfa('NOT-ONBOARDED', ['product' => 'Pickle']);

        $batchId = DB::table('onboarding_batches')->insertGetId([
            'hub_id' => $hubId,
            'district_id' => $districtId,
            'name' => 'Locked Phase 3 batch',
            'target_size' => 4,
            'status' => 'locked',
            'locked_at' => '2026-08-01 10:00:00',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        foreach ($includedIds as $cfaId) {
            DB::table('onboarding_batch_cfa')->insert([
                'onboarding_batch_id' => $batchId,
                'cfa_submission_id' => $cfaId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $service = (new ReflectionClass(StateAdminDashboardService::class))->newInstanceWithoutConstructor();
        $method = new \ReflectionMethod(StateAdminDashboardService::class, 'onboardedProductMix');
        $method->setAccessible(true);
        $mix = $method->invoke($service, Carbon::create(2026, 4, 1)->startOfDay());

        $counts = collect($mix['items'])->pluck('count', 'product');
        $this->assertSame(4, $mix['total']);
        $this->assertSame(3, $mix['specified']);
        $this->assertSame(1, $mix['missing']);
        $this->assertSame(2, $mix['distinct']);
        $this->assertSame(2, $counts['Pickle']);
        $this->assertSame(1, $counts['Chocolate Making']);
        $this->assertSame(1, $counts['Not specified']);
    }
}
