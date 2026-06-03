<?php

namespace Tests\Feature;

use App\Models\District;
use App\Models\DistrictBlock;
use App\Models\DistrictWorkshopSession;
use App\Models\GramPanchayat;
use App\Models\Hub;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DistrictWorkshopSessionAttendanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_district_staff_can_submit_district_workshop_with_participant_counts_and_attendance_file(): void
    {
        Storage::fake('local');

        [$staff, $block, $gp, $district] = $this->staffWithBlockAndGp();

        $response = $this->actingAs($staff)->post(route('staff.district-workshop-sessions.store'), [
            'session_date' => '2026-05-18',
            'workshop_mode' => 'physical',
            'male_participants' => 1,
            'female_participants' => 1,
            'district_block_id' => $block->id,
            'gram_panchayat_id' => $gp->id,
            'participants' => [
                ['name' => 'Ram', 'gender' => 'M', 'gram_panchayat_id' => $gp->id, 'gram_panchayat_name' => $gp->name],
                ['name' => 'Sita', 'gender' => 'F', 'gram_panchayat_id' => $gp->id, 'gram_panchayat_name' => $gp->name],
            ],
            'notes' => 'District awareness workshop',
            'attendance_media' => [
                UploadedFile::fake()->create('attendance.pdf', 100, 'application/pdf'),
            ],
            'workshop_photos' => [
                UploadedFile::fake()->create('workshop-1.jpg', 100, 'image/jpeg'),
                UploadedFile::fake()->create('workshop-2.jpg', 100, 'image/jpeg'),
            ],
        ]);

        $response->assertRedirect(route('staff.district-workshop-sessions.dashboard'));

        $this->assertDatabaseHas('district_workshop_sessions', [
            'district_id' => $district->id,
            'submitted_by_user_id' => $staff->id,
            'male_participants' => 1,
            'female_participants' => 1,
            'participants_total' => 2,
            'notes' => 'District awareness workshop',
        ]);

        $session = DistrictWorkshopSession::query()->first();
        $this->assertNotNull($session);
        $this->assertCount(1, (array) $session->attendance_media_json);
        $this->assertCount(2, (array) $session->workshop_photos_json);
        $this->assertCount(2, $session->participantRows());
        $this->assertSame('Ram', $session->participantRows()[0]['name']);
    }

    public function test_district_staff_cannot_submit_with_too_many_workshop_photos(): void
    {
        Storage::fake('local');

        [$staff, $block] = $this->staffWithBlockAndGp();

        $response = $this->actingAs($staff)->post(route('staff.district-workshop-sessions.store'), [
            'session_date' => '2026-05-18',
            'workshop_mode' => 'physical',
            'male_participants' => 5,
            'female_participants' => 5,
            'district_block_id' => $block->id,
            'attendance_media' => [
                UploadedFile::fake()->create('attendance.pdf', 100, 'application/pdf'),
            ],
            'workshop_photos' => [
                UploadedFile::fake()->create('photo-1.jpg', 100, 'image/jpeg'),
                UploadedFile::fake()->create('photo-2.jpg', 100, 'image/jpeg'),
                UploadedFile::fake()->create('photo-3.jpg', 100, 'image/jpeg'),
                UploadedFile::fake()->create('photo-4.jpg', 100, 'image/jpeg'),
                UploadedFile::fake()->create('photo-5.jpg', 100, 'image/jpeg'),
                UploadedFile::fake()->create('photo-6.jpg', 100, 'image/jpeg'),
            ],
        ]);

        $response->assertSessionHasErrors(['workshop_photos']);
        $this->assertDatabaseCount('district_workshop_sessions', 0);
    }

    public function test_district_staff_cannot_submit_without_participants_or_attendance(): void
    {
        $district = $this->createDistrict();
        $staff = User::factory()->create([
            'role' => 'district_staff',
            'district_id' => $district->id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($staff)->post(route('staff.district-workshop-sessions.store'), [
            'session_date' => '2026-05-18',
            'workshop_mode' => 'physical',
            'male_participants' => 0,
            'female_participants' => 0,
        ]);

        $response->assertSessionHasErrors(['male_participants', 'workshop_photos']);
        $this->assertDatabaseCount('district_workshop_sessions', 0);
    }

    public function test_district_staff_can_submit_without_attendance_sheet_and_upload_later(): void
    {
        Storage::fake('local');

        [$staff, $block] = $this->staffWithBlockAndGp();

        $response = $this->actingAs($staff)->post(route('staff.district-workshop-sessions.store'), [
            'session_date' => '2026-05-18',
            'workshop_mode' => 'physical',
            'male_participants' => 3,
            'female_participants' => 2,
            'district_block_id' => $block->id,
            'workshop_photos' => [
                UploadedFile::fake()->create('workshop-1.jpg', 100, 'image/jpeg'),
            ],
        ]);

        $response->assertRedirect(route('staff.district-workshop-sessions.dashboard'));

        $session = DistrictWorkshopSession::query()->first();
        $this->assertNotNull($session);
        $this->assertTrue($session->isAttendancePending());
        $this->assertSame([], (array) $session->attendance_media_json);
    }

    public function test_district_staff_can_delete_own_district_workshop_entry(): void
    {
        Storage::fake('local');

        [$staff, $block] = $this->staffWithBlockAndGp();

        $this->actingAs($staff)->post(route('staff.district-workshop-sessions.store'), [
            'session_date' => '2026-05-18',
            'workshop_mode' => 'physical',
            'male_participants' => 2,
            'female_participants' => 3,
            'district_block_id' => $block->id,
            'workshop_photos' => [
                UploadedFile::fake()->create('workshop-1.jpg', 100, 'image/jpeg'),
            ],
        ]);

        $session = DistrictWorkshopSession::query()->first();
        $this->assertNotNull($session);

        $response = $this->actingAs($staff)->delete(route('staff.district-workshop-sessions.destroy', $session));

        $response->assertRedirect(route('staff.district-workshop-sessions.dashboard'));
        $this->assertDatabaseMissing('district_workshop_sessions', ['id' => $session->id]);
    }

    private function createDistrict(string $slug = 'dehradun', string $name = 'Dehradun'): District
    {
        $hub = Hub::query()->create([
            'name' => 'Test Hub',
            'slug' => 'test-hub-'.$slug,
            'is_active' => true,
        ]);

        return District::query()->create([
            'hub_id' => $hub->id,
            'name' => $name,
            'slug' => $slug,
            'is_active' => true,
        ]);
    }

    /**
     * @return array{0: User, 1: DistrictBlock, 2: GramPanchayat, 3: District}
     */
    private function staffWithBlockAndGp(string $slug = 'dehradun', string $name = 'Dehradun'): array
    {
        $district = $this->createDistrict($slug, $name);
        $staff = User::factory()->create([
            'role' => 'district_staff',
            'district_id' => $district->id,
            'is_active' => true,
        ]);
        $block = DistrictBlock::query()->create([
            'district_id' => $district->id,
            'name' => 'Block A',
            'sort_order' => 0,
        ]);
        $gp = GramPanchayat::query()->create([
            'district_id' => $district->id,
            'district_block_id' => $block->id,
            'name' => 'GP One',
        ]);

        return [$staff, $block, $gp, $district];
    }
}
