<?php

namespace Tests\Feature;

use App\Models\District;
use App\Models\FiscalYear;
use App\Models\Hub;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewPptGeneratorAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_state_admin_can_open_review_ppt_page(): void
    {
        $this->enableReviewPpt();
        $this->seedFiscalYear();
        $admin = User::factory()->create(['role' => 'state_admin', 'is_active' => true]);

        $this->actingAs($admin)
            ->get(route('admin.review-ppt.index'))
            ->assertOk()
            ->assertSee('All 13 districts')
            ->assertSee('MUY Review PowerPoint');
    }

    public function test_hub_admin_can_open_review_ppt_on_admin_and_hub_urls(): void
    {
        $this->enableReviewPpt();
        $this->seedFiscalYear();
        [$hubAdmin] = $this->createHubAdminWithDistricts(['almora', 'nainital']);

        $this->actingAs($hubAdmin)
            ->get(route('admin.review-ppt.index'))
            ->assertOk()
            ->assertSee('All hub districts')
            ->assertSee('Almora')
            ->assertDontSee('All 13 districts');

        $this->actingAs($hubAdmin)
            ->get(route('hub.review-ppt.index'))
            ->assertOk()
            ->assertSee('Review PowerPoint');
    }

    public function test_district_staff_cannot_open_review_ppt(): void
    {
        $this->enableReviewPpt();
        $this->seedFiscalYear();
        $staff = User::factory()->create(['role' => 'district_staff', 'is_active' => true]);

        $this->actingAs($staff)
            ->get(route('admin.review-ppt.index'))
            ->assertForbidden();
    }

    public function test_review_ppt_nav_hidden_while_feature_disabled(): void
    {
        config(['features.review_ppt' => false]);
        $this->seedFiscalYear();
        $admin = User::factory()->create(['role' => 'state_admin', 'is_active' => true]);
        [$hubAdmin] = $this->createHubAdminWithDistricts(['almora', 'nainital']);

        $this->actingAs($admin)
            ->get(route('admin.review-ppt.index'))
            ->assertOk()
            ->assertSee('MUY Review PowerPoint');

        $this->actingAs($hubAdmin)
            ->get(route('hub.review-ppt.index'))
            ->assertOk()
            ->assertSee('Review PowerPoint');
    }

    private function enableReviewPpt(): void
    {
        config(['features.review_ppt' => true]);
    }

    /** @param list<string> $slugs */
    private function createHubAdminWithDistricts(array $slugs): array
    {
        $hub = Hub::query()->create(['slug' => 'kumaon-hub', 'name' => 'Kumaon Hub', 'sort_order' => 1]);
        foreach ($slugs as $index => $slug) {
            District::query()->create([
                'hub_id' => $hub->id,
                'slug' => $slug,
                'name' => ucwords(str_replace('-', ' ', $slug)),
                'sort_order' => $index + 1,
            ]);
        }
        $hubAdmin = User::factory()->create([
            'role' => 'hub_admin',
            'hub_id' => $hub->id,
            'is_active' => true,
        ]);

        return [$hubAdmin, $hub];
    }

    private function seedFiscalYear(): void
    {
        FiscalYear::query()->create([
            'code' => '2026-27',
            'name' => 'FY 2026-27',
            'starts_on' => '2026-04-01',
            'ends_on' => '2027-03-31',
            'is_active' => true,
        ]);
    }
}
