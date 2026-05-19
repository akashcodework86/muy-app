<?php

namespace Tests\Feature;

use App\Models\Designation;
use App\Models\District;
use App\Models\DistrictBlock;
use App\Models\FieldCoordinatorAttendanceReport;
use App\Models\GramPanchayat;
use App\Models\Hub;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FieldCoordinatorAttendanceEditDeleteTest extends TestCase
{
    use RefreshDatabase;

    public function test_field_coordinator_can_edit_participant_counts(): void
    {
        [$staff, $block, $gp, $district, $report] = $this->createReport();

        $this->actingAs($staff)
            ->put(route('staff.attendance.update', $report), [
                'visit_date' => '2026-04-25',
                'district_block_id' => $block->id,
                'gram_panchayat_id' => $gp->id,
                'area' => 'Demo Area',
                'participants_male_count' => 8,
                'participants_female_count' => 12,
                'remark' => 'Updated',
            ])
            ->assertRedirect(route('staff.attendance.index'));

        $report->refresh();
        $this->assertSame(8, $report->participants_male_count);
        $this->assertSame(12, $report->participants_female_count);
        $this->assertSame(20, $report->participants_total);
        $this->assertSame($gp->id, $report->gram_panchayat_id);
    }

    public function test_field_coordinator_can_delete_own_submission(): void
    {
        Storage::fake();

        [$staff, , , , $report] = $this->createReport();

        $this->actingAs($staff)
            ->delete(route('staff.attendance.destroy', $report))
            ->assertRedirect(route('staff.attendance.index'));

        $this->assertDatabaseMissing('field_coordinator_attendance_reports', ['id' => $report->id]);
    }

    public function test_field_coordinator_cannot_edit_another_users_submission(): void
    {
        [$staff, $block, $gp, , $report] = $this->createReport();

        $other = User::factory()->create([
            'role' => 'district_staff',
            'district_id' => $report->district_id,
            'designation_id' => $staff->designation_id,
            'is_active' => true,
        ]);

        $this->actingAs($other)
            ->put(route('staff.attendance.update', $report), [
                'visit_date' => '2026-04-25',
                'district_block_id' => $report->district_block_id,
                'gram_panchayat_id' => $report->gram_panchayat_id,
                'area' => 'Hack',
                'participants_male_count' => 1,
                'participants_female_count' => 0,
            ])
            ->assertForbidden();
    }

    /**
     * @return array{0: User, 1: DistrictBlock, 2: GramPanchayat, 3: District, 4: FieldCoordinatorAttendanceReport}
     */
    private function createReport(): array
    {
        $district = District::query()->create([
            'hub_id' => Hub::query()->firstOrCreate(['slug' => 'edit-del-hub'], ['name' => 'Hub', 'sort_order' => 1])->id,
            'slug' => 'dehradun-edit',
            'name' => 'Dehradun',
            'sort_order' => 1,
        ]);
        $designation = Designation::query()->create(['name' => 'Field Coordinator', 'sort_order' => 1]);
        $staff = User::factory()->create([
            'role' => 'district_staff',
            'district_id' => $district->id,
            'designation_id' => $designation->id,
            'is_active' => true,
        ]);
        $block = DistrictBlock::query()->create([
            'district_id' => $district->id,
            'name' => 'Sahaspur',
        ]);
        $gp = GramPanchayat::query()->create([
            'district_id' => $district->id,
            'district_block_id' => $block->id,
            'name' => 'Demo GP',
        ]);
        $report = FieldCoordinatorAttendanceReport::query()->create([
            'field_coordinator_user_id' => $staff->id,
            'field_coordinator_name' => $staff->name,
            'visit_date' => '2026-04-25',
            'entry_date' => '2026-04-25',
            'block' => $block->name,
            'district_block_id' => $block->id,
            'gram_panchayat_id' => null,
            'area' => 'Demo Area',
            'district_id' => $district->id,
            'participants_male_count' => 0,
            'participants_female_count' => 0,
            'participants_total' => 20,
        ]);

        return [$staff, $block, $gp, $district, $report];
    }
}
