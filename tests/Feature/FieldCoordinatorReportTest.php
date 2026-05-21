<?php

namespace Tests\Feature;

use App\Models\Designation;
use App\Models\District;
use App\Models\FieldCoordinatorAttendanceReport;
use App\Models\Hub;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FieldCoordinatorReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_state_admin_sees_field_coordinator_report_page(): void
    {
        $admin = User::factory()->create(['role' => 'state_admin', 'is_active' => true]);
        $district = $this->createDistrict('dehradun', 'Dehradun');
        $this->seedReport($district, 'FC One', 10);

        $this->actingAs($admin)
            ->get(route('admin.field-coordinator-reports.index'))
            ->assertOk()
            ->assertSee('Field Coordinator Report')
            ->assertSee('FC One');
    }

    public function test_field_coordinator_staff_sees_only_own_reports(): void
    {
        $district = $this->createDistrict('almora', 'Almora');
        $designation = Designation::query()->create(['name' => 'Field Coordinator', 'sort_order' => 1]);
        $fc = User::factory()->create([
            'role' => 'district_staff',
            'district_id' => $district->id,
            'designation_id' => $designation->id,
            'name' => 'My FC User',
            'is_active' => true,
        ]);
        $otherFc = User::factory()->create([
            'role' => 'district_staff',
            'district_id' => $district->id,
            'designation_id' => $designation->id,
            'name' => 'Other FC User',
            'is_active' => true,
        ]);

        $this->seedReport($district, 'My FC User', 5, (int) $fc->id);
        $this->seedReport($district, 'Other FC User', 8, (int) $otherFc->id);

        $this->actingAs($fc)
            ->get(route('staff.field-coordinator-reports.index'))
            ->assertOk()
            ->assertSee('My FC User')
            ->assertDontSee('Other FC User');
    }

    public function test_non_fc_district_staff_sees_district_reports(): void
    {
        $district = $this->createDistrict('pauri', 'Pauri');
        $otherDistrict = $this->createDistrict('tehri', 'Tehri');
        $designation = Designation::query()->create(['name' => 'Field Coordinator', 'sort_order' => 1]);
        $viewer = User::factory()->create([
            'role' => 'district_staff',
            'district_id' => $district->id,
            'designation_id' => null,
            'is_active' => true,
        ]);
        $fc = User::factory()->create([
            'role' => 'district_staff',
            'district_id' => $district->id,
            'designation_id' => $designation->id,
            'name' => 'District FC',
            'is_active' => true,
        ]);

        $this->seedReport($district, 'District FC', 4, (int) $fc->id);
        $outsideFc = User::factory()->create([
            'role' => 'district_staff',
            'district_id' => $otherDistrict->id,
            'designation_id' => $designation->id,
            'is_active' => true,
        ]);
        $this->seedReport($otherDistrict, 'Outside FC', 9, (int) $outsideFc->id);

        $this->actingAs($viewer)
            ->get(route('staff.field-coordinator-reports.index'))
            ->assertOk()
            ->assertSee('District FC')
            ->assertDontSee('Outside FC');
    }

    private function seedReport(District $district, string $coordinatorName, int $participants, ?int $userId = null): void
    {
        FieldCoordinatorAttendanceReport::query()->create([
            'field_coordinator_user_id' => $userId ?? 1,
            'field_coordinator_name' => $coordinatorName,
            'visit_date' => '2026-05-20',
            'entry_date' => '2026-05-20',
            'area' => 'Test area',
            'block' => 'Test block',
            'district_id' => $district->id,
            'participants_total' => $participants,
            'participants_male_count' => 2,
            'participants_female_count' => max(0, $participants - 2),
            'villages_visited_total' => 1,
            'cfas_filled_total' => 0,
            'outreach_programmes_total' => 0,
        ]);
    }

    private function createDistrict(string $slug, string $name): District
    {
        $hub = Hub::query()->firstOrCreate(
            ['slug' => 'fc-report-hub-'.$slug],
            ['name' => 'Hub '.$name, 'sort_order' => 1]
        );

        return District::query()->create([
            'hub_id' => $hub->id,
            'slug' => $slug,
            'name' => $name,
            'sort_order' => 1,
        ]);
    }
}
