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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FieldCoordinatorFieldVisitTest extends TestCase
{
    use RefreshDatabase;

    public function test_district_staff_can_submit_visit_photos_without_field_coordinator_designation(): void
    {
        Storage::fake();

        $district = $this->createDistrict('pithoragarh', 'Pithoragarh');
        $designation = Designation::query()->create(['name' => 'District Coordinator', 'sort_order' => 1]);
        $staff = User::factory()->create([
            'role' => 'district_staff',
            'district_id' => $district->id,
            'designation_id' => $designation->id,
            'is_active' => true,
        ]);
        $block = DistrictBlock::query()->create([
            'district_id' => $district->id,
            'name' => 'Dharchula',
            'sort_order' => 0,
        ]);
        $gp = GramPanchayat::query()->create([
            'district_id' => $district->id,
            'district_block_id' => $block->id,
            'name' => 'Gangolihat',
        ]);

        $this->actingAs($staff)
            ->get(route('staff.attendance.index'))
            ->assertOk()
            ->assertSee('New block level workshop');

        $this->actingAs($staff)->post(route('staff.attendance.store'), [
            'visit_date' => '2026-05-16',
            'district_block_id' => $block->id,
            'gram_panchayat_id' => $gp->id,
            'area' => 'Market area',
            'participants_male_count' => 5,
            'participants_female_count' => 7,
            'visit_media' => [
                UploadedFile::fake()->create('visit-1.jpg', 100, 'image/jpeg'),
            ],
        ])->assertRedirect(route('staff.attendance.index'));

        $this->assertDatabaseHas('field_coordinator_attendance_reports', [
            'field_coordinator_user_id' => $staff->id,
            'participants_total' => 12,
        ]);
    }

    public function test_field_coordinator_can_submit_visit_photos(): void
    {
        Storage::fake();

        $district = $this->createDistrict('almora', 'Almora');
        $designation = Designation::query()->create(['name' => 'Field Coordinator', 'sort_order' => 1]);
        $staff = User::factory()->create([
            'role' => 'district_staff',
            'district_id' => $district->id,
            'designation_id' => $designation->id,
            'is_active' => true,
        ]);
        $block = DistrictBlock::query()->create([
            'district_id' => $district->id,
            'name' => 'Bhaisiya Chhana',
            'sort_order' => 0,
        ]);
        $gp = GramPanchayat::query()->create([
            'district_id' => $district->id,
            'district_block_id' => $block->id,
            'name' => 'Dungerlekh',
        ]);

        $response = $this->actingAs($staff)->post(route('staff.attendance.store'), [
            'visit_date' => '2026-05-16',
            'district_block_id' => $block->id,
            'gram_panchayat_id' => $gp->id,
            'area' => 'Supai village',
            'participants_male_count' => 12,
            'participants_female_count' => 18,
            'remark' => 'Chaupal meeting',
            'visit_media' => [
                UploadedFile::fake()->create('visit-1.jpg', 100, 'image/jpeg'),
                UploadedFile::fake()->create('visit-2.png', 100, 'image/png'),
            ],
        ]);

        $response->assertRedirect(route('staff.attendance.index'));

        $report = FieldCoordinatorAttendanceReport::query()->firstOrFail();
        $this->assertSame('Bhaisiya Chhana', $report->block);
        $this->assertSame($gp->id, $report->gram_panchayat_id);
        $this->assertSame('Supai village', $report->area);
        $this->assertSame(30, $report->participants_total);
        $this->assertSame(12, $report->participants_male_count);
        $this->assertSame(18, $report->participants_female_count);
        $this->assertSame('Chaupal meeting', $report->remark);
        $this->assertCount(2, $report->visitMediaItems());
        Storage::disk('local')->assertExists((string) $report->visit_media_json[0]['path']);
        $this->assertFalse($report->hasAttendanceSheet());
    }

    public function test_gram_panchayat_api_is_scoped_to_block_and_district(): void
    {
        $district = $this->createDistrict('almora-api', 'Almora');
        $otherDistrict = $this->createDistrict('dehradun', 'Dehradun');
        $designation = Designation::query()->create(['name' => 'Field Coordinator', 'sort_order' => 1]);
        $staff = User::factory()->create([
            'role' => 'district_staff',
            'district_id' => $district->id,
            'designation_id' => $designation->id,
            'is_active' => true,
        ]);
        $block = DistrictBlock::query()->create([
            'district_id' => $district->id,
            'name' => 'Bhaisiya Chhana',
        ]);
        $otherBlock = DistrictBlock::query()->create([
            'district_id' => $otherDistrict->id,
            'name' => 'Other Block',
        ]);
        GramPanchayat::query()->create([
            'district_id' => $district->id,
            'district_block_id' => $block->id,
            'name' => 'Dungerlekh',
        ]);
        GramPanchayat::query()->create([
            'district_id' => $otherDistrict->id,
            'district_block_id' => $otherBlock->id,
            'name' => 'Secret GP',
        ]);

        $this->actingAs($staff)
            ->getJson(route('staff.attendance.gram-panchayats', ['district_block_id' => $block->id]))
            ->assertOk()
            ->assertJsonPath('items.0.name', 'Dungerlekh');

        $this->actingAs($staff)
            ->getJson(route('staff.attendance.gram-panchayats', ['district_block_id' => $otherBlock->id]))
            ->assertForbidden();
    }

    private function createDistrict(string $slug, string $name): District
    {
        $hub = Hub::query()->firstOrCreate(
            ['slug' => 'fc-test-hub'],
            ['name' => 'FC Test Hub', 'sort_order' => 1]
        );

        return District::query()->create([
            'hub_id' => $hub->id,
            'slug' => $slug,
            'name' => $name,
            'sort_order' => 1,
        ]);
    }
}
