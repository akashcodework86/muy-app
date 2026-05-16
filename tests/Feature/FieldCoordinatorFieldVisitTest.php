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
        $this->assertSame('Chaupal meeting', $report->remark);
        $this->assertCount(2, $report->visitMediaItems());
        Storage::disk('local')->assertExists((string) $report->visit_media_json[0]['path']);
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
