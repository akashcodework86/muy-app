<?php

namespace Tests\Feature;

use App\Models\District;
use App\Models\DistrictWorkshopSession;
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

        $district = $this->createDistrict();
        $staff = User::factory()->create([
            'role' => 'district_staff',
            'district_id' => $district->id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($staff)->post(route('staff.district-workshop-sessions.store'), [
            'session_date' => '2026-05-18',
            'workshop_mode' => 'physical',
            'male_participants' => 12,
            'female_participants' => 8,
            'notes' => 'District awareness workshop',
            'attendance_media' => [
                UploadedFile::fake()->create('attendance.pdf', 100, 'application/pdf'),
            ],
        ]);

        $response->assertRedirect(route('staff.district-workshop-sessions.dashboard'));

        $this->assertDatabaseHas('district_workshop_sessions', [
            'district_id' => $district->id,
            'submitted_by_user_id' => $staff->id,
            'male_participants' => 12,
            'female_participants' => 8,
            'notes' => 'District awareness workshop',
        ]);

        $session = DistrictWorkshopSession::query()->first();
        $this->assertNotNull($session);
        $this->assertCount(1, (array) $session->attendance_media_json);
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

        $response->assertSessionHasErrors(['male_participants', 'attendance_media']);
        $this->assertDatabaseCount('district_workshop_sessions', 0);
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
}
