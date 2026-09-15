<?php

namespace Tests\Feature;

use App\Models\District;
use App\Models\Hub;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\User;
use App\Support\StateAdminServiceNavIndex;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StateAdminServiceNavSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_includes_menu_and_catalog_destinations_for_state_admin(): void
    {
        $admin = User::factory()->create(['role' => 'state_admin', 'is_active' => true]);
        [$active] = $this->createCatalogServices();

        $items = StateAdminServiceNavIndex::forUser($admin);
        $byLabel = collect($items)->keyBy('label');

        $this->assertSame(route('admin.bills.dashboard'), $byLabel['Bills']['url']);
        $this->assertSame('menu', $byLabel['Bills']['kind']);
        $this->assertSame(
            route('admin.phase3-services.index', ['service_id' => $active->id]),
            $byLabel['FSSAI']['url']
        );
        $this->assertSame('catalog', $byLabel['FSSAI']['kind']);
        $this->assertFalse($byLabel->has('Hidden Inactive Service'));
    }

    public function test_index_is_empty_for_hub_admin(): void
    {
        $hub = Hub::query()->create(['slug' => 'nav-hub', 'name' => 'Nav Hub', 'sort_order' => 1]);
        $hubAdmin = User::factory()->create([
            'role' => 'hub_admin',
            'hub_id' => $hub->id,
            'is_active' => true,
        ]);

        $this->assertSame([], StateAdminServiceNavIndex::forUser($hubAdmin));
    }

    public function test_state_admin_topbar_renders_search_and_catalog_jump_url(): void
    {
        $admin = User::factory()->create(['role' => 'state_admin', 'is_active' => true]);
        [$service] = $this->createCatalogServices();

        $html = $this->actingAs($admin)
            ->get(route('admin.service-catalog.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('id="stateAdminServiceSearch"', $html);
        $this->assertStringContainsString('id="stateAdminServiceSearchItems"', $html);
        $this->assertStringContainsString(route('admin.bills.dashboard'), $html);

        $this->assertSame(1, preg_match('/id="stateAdminServiceSearchItems">(.+?)<\/script>/s', $html, $match));
        $payload = json_decode($match[1], true);
        $this->assertIsArray($payload);
        $fssai = collect($payload)->firstWhere('label', 'FSSAI');
        $this->assertIsArray($fssai);
        $this->assertSame('catalog', $fssai['kind']);
        $this->assertSame(
            route('admin.phase3-services.index', ['service_id' => $service->id]),
            $fssai['url']
        );
    }

    public function test_hub_admin_topbar_does_not_render_service_search(): void
    {
        $hub = Hub::query()->create(['slug' => 'nav-hub-2', 'name' => 'Nav Hub 2', 'sort_order' => 1]);
        District::query()->create([
            'hub_id' => $hub->id,
            'slug' => 'nav-district-2',
            'name' => 'Nav District 2',
            'sort_order' => 1,
        ]);
        $hubAdmin = User::factory()->create([
            'role' => 'hub_admin',
            'hub_id' => $hub->id,
            'is_active' => true,
        ]);

        $html = $this->actingAs($hubAdmin)
            ->get(route('dashboard'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('id="stateAdminServiceSearch"', $html);
    }

    /**
     * @return array{0: Service, 1: Service}
     */
    private function createCatalogServices(): array
    {
        $category = ServiceCategory::query()->create([
            'slug' => 'legal-support-services',
            'name' => 'Legal support',
            'sort_order' => 1,
        ]);

        $active = Service::query()->create([
            'service_category_id' => $category->id,
            'code' => 'fssai',
            'name' => 'FSSAI',
            'sort_order' => 1,
            'is_active' => true,
            'requires_approval' => false,
        ]);
        $inactive = Service::query()->create([
            'service_category_id' => $category->id,
            'code' => 'hidden-inactive-service',
            'name' => 'Hidden Inactive Service',
            'sort_order' => 2,
            'is_active' => false,
            'requires_approval' => false,
        ]);

        return [$active, $inactive];
    }
}
