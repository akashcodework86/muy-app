<?php

namespace Tests\Feature;

use App\Models\CommunityOrganizationOutreachVisit;
use App\Models\District;
use App\Models\FiscalYear;
use App\Models\Hub;
use App\Models\ServiceCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class HubAdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_hub_admin_dashboard_loads(): void
    {
        $hub = Hub::query()->create(['slug' => 'hub-dash', 'name' => 'Kumaon Region', 'sort_order' => 1]);
        District::query()->create([
            'hub_id' => $hub->id,
            'slug' => 'pauri-dash',
            'name' => 'Pauri Garhwal',
            'sort_order' => 1,
        ]);

        $admin = User::factory()->create([
            'role' => 'hub_admin',
            'hub_id' => $hub->id,
            'is_active' => true,
        ]);
        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Welcome')
            ->assertSee('Field Highlights')
            ->assertSee('Approved photos from this hub', false);
    }

    public function test_hub_highlights_only_include_approved_photos_from_own_hub(): void
    {
        Storage::fake('local');

        $ownHub = Hub::query()->create(['slug' => 'own-highlight-hub', 'name' => 'Own Hub', 'sort_order' => 1]);
        $otherHub = Hub::query()->create(['slug' => 'other-highlight-hub', 'name' => 'Other Hub', 'sort_order' => 2]);
        $ownDistrict = District::query()->create([
            'hub_id' => $ownHub->id,
            'slug' => 'own-highlight-district',
            'name' => 'Own District',
            'sort_order' => 1,
        ]);
        $otherDistrict = District::query()->create([
            'hub_id' => $otherHub->id,
            'slug' => 'other-highlight-district',
            'name' => 'Other District',
            'sort_order' => 2,
        ]);
        $admin = User::factory()->create([
            'role' => 'hub_admin',
            'hub_id' => $ownHub->id,
            'is_active' => true,
        ]);
        FiscalYear::query()->updateOrCreate(['code' => '2026-27'], [
            'name' => 'FY 2026-27',
            'starts_on' => '2026-04-01',
            'ends_on' => '2027-03-31',
            'is_active' => true,
        ]);

        Storage::put('field-highlights/own.jpg', 'own-image');
        Storage::put('field-highlights/other.jpg', 'other-image');

        $ownVisit = $this->createOutreachVisit(
            $admin,
            $ownHub,
            $ownDistrict,
            'Own approved activity',
            'field-highlights/own.jpg',
            ServiceCase::STATUS_APPROVED,
        );
        $otherVisit = $this->createOutreachVisit(
            $admin,
            $otherHub,
            $otherDistrict,
            'Other hub activity',
            'field-highlights/other.jpg',
            ServiceCase::STATUS_APPROVED,
        );

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Own approved activity')
            ->assertDontSee('Other hub activity');

        $this->actingAs($admin)
            ->get(route('hub.field-highlights.image', [
                'module' => 'community_org_outreach',
                'record' => $ownVisit->id,
                'collection' => 'photos_json',
                'index' => 0,
            ]))
            ->assertOk();

        $this->actingAs($admin)
            ->get(route('hub.field-highlights.image', [
                'module' => 'community_org_outreach',
                'record' => $otherVisit->id,
                'collection' => 'photos_json',
                'index' => 0,
            ]))
            ->assertForbidden();
    }

    public function test_hub_district_target_chart_includes_monthly_period_tabs(): void
    {
        $hub = Hub::query()->create(['slug' => 'hub-tabs', 'name' => 'Garhwal Region', 'sort_order' => 1]);
        $district = District::query()->create([
            'hub_id' => $hub->id,
            'slug' => 'dehradun-tabs',
            'name' => 'Dehradun',
            'sort_order' => 1,
        ]);

        $fy = FiscalYear::query()->create([
            'code' => 'FY2025-26',
            'name' => 'FY 2025-26',
            'starts_on' => '2025-04-01',
            'ends_on' => '2026-03-31',
            'is_active' => true,
            'is_phase3_default' => true,
        ]);

        DB::table('cfa_submissions')->insert([
            'district_id' => $district->id,
            'fiscal_year_id' => $fy->id,
            'application_no' => 'HUB-TAB-001',
            'applicant_name' => 'Tab Test Applicant',
            'phone' => '9000000099',
            'payload' => json_encode([]),
            'created_at' => '2026-05-10 10:00:00',
            'updated_at' => now(),
        ]);

        $admin = User::factory()->create([
            'role' => 'hub_admin',
            'hub_id' => $hub->id,
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('District CFA vs target')
            ->assertSee('Full FY')
            ->assertSee('data-sad-dt-tab="m1"', false);
    }

    private function createOutreachVisit(
        User $submitter,
        Hub $hub,
        District $district,
        string $organization,
        string $photoPath,
        string $status,
    ): CommunityOrganizationOutreachVisit {
        return CommunityOrganizationOutreachVisit::query()->create([
            'hub_id' => $hub->id,
            'hub_name' => $hub->name,
            'district_id' => $district->id,
            'district_name' => $district->name,
            'visit_date' => now()->toDateString(),
            'organization_name' => $organization,
            'organization_type' => 'ngo',
            'person_met_name' => 'Contact Person',
            'poc_name' => 'POC',
            'poc_phone' => '9000000000',
            'purpose' => 'awareness_orientation',
            'meeting_mode' => 'physical',
            'photos_json' => [[
                'path' => $photoPath,
                'original_name' => basename($photoPath),
                'mime' => 'image/jpeg',
            ]],
            'submitted_by_user_id' => $submitter->id,
            'submitted_by_name' => $submitter->name,
            'status' => $status,
            'submitted_at' => now(),
            'approved_at' => $status === ServiceCase::STATUS_APPROVED ? now() : null,
            'approved_by' => $status === ServiceCase::STATUS_APPROVED ? $submitter->id : null,
        ]);
    }
}
