<?php

namespace Tests\Feature;

use App\Models\District;
use App\Models\EapEdpSession;
use App\Models\Hub;
use App\Models\User;
use App\Services\AppSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EapEdpSessionAttendanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(AppSettingsService::class)->setMany([
            'staff_nav.eap_edp_session.visible' => true,
        ]);
    }

    public function test_district_staff_can_store_session_photos(): void
    {
        Storage::fake();
        $district = $this->createDistrict();
        $staff = User::factory()->create([
            'role' => 'district_staff',
            'district_id' => $district->id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($staff)->post(route('staff.eap-edp-sessions.store'), [
            'session_date' => '2026-05-20',
            'topic' => 'EAP mobilization camp',
            'workshop_mode' => 'physical',
            'attendance_male_count' => 12,
            'attendance_female_count' => 18,
            'attendance_media' => [
                UploadedFile::fake()->create('attendance.pdf', 100, 'application/pdf'),
            ],
            'session_photos' => [
                UploadedFile::fake()->image('camp-1.jpg'),
                UploadedFile::fake()->image('camp-2.png'),
            ],
        ]);

        $response->assertRedirect(route('staff.eap-edp-sessions.dashboard'));

        $entry = EapEdpSession::query()->firstOrFail();
        $this->assertTrue(Schema::hasColumn('eap_edp_sessions', 'session_photos_json'));
        $this->assertCount(2, (array) $entry->session_photos_json);
        Storage::disk('local')->assertExists((string) $entry->session_photos_json[0]['path']);
        Storage::disk('local')->assertExists((string) $entry->session_photos_json[1]['path']);
    }

    public function test_update_can_add_and_remove_session_photos(): void
    {
        Storage::fake();
        $district = $this->createDistrict('test-district-2', 'Test District 2');
        $staff = User::factory()->create([
            'role' => 'district_staff',
            'district_id' => $district->id,
            'is_active' => true,
        ]);

        $entry = EapEdpSession::query()->create([
            'submitted_by_user_id' => $staff->id,
            'submitted_by_name' => $staff->name,
            'event_date' => '2026-05-18',
            'district_id' => $district->id,
            'district_name' => $district->name,
            'program_type' => 'eap_edp',
            'topic' => 'Original',
            'workshop_mode' => 'physical',
            'attendance_male_count' => 1,
            'attendance_female_count' => 2,
            'attendance_total_count' => 3,
            'attendance_media_json' => [[
                'path' => 'eap-edp-session-media/sheet.pdf',
                'original_name' => 'sheet.pdf',
                'mime' => 'application/pdf',
                'type' => 'document',
            ]],
            'session_photos_json' => [[
                'path' => UploadedFile::fake()->image('old.jpg')->store('eap-edp-session-photos'),
                'original_name' => 'old.jpg',
                'mime' => 'image/jpeg',
                'type' => 'image',
            ]],
            'selected_incubatee_ids' => [],
            'selected_incubatees_snapshot' => [],
        ]);

        $oldPath = (string) $entry->session_photos_json[0]['path'];

        $response = $this->actingAs($staff)->put(route('staff.eap-edp-sessions.update', $entry), [
            'session_date' => '2026-05-19',
            'topic' => 'Updated',
            'workshop_mode' => 'virtual',
            'attendance_male_count' => 2,
            'attendance_female_count' => 3,
            'remove_photo_indices' => [0],
            'session_photos' => [
                UploadedFile::fake()->image('new.jpg'),
            ],
        ]);

        $response->assertRedirect(route('staff.eap-edp-sessions.dashboard'));

        $entry->refresh();
        $this->assertCount(1, (array) $entry->session_photos_json);
        $this->assertSame('new.jpg', (string) $entry->session_photos_json[0]['original_name']);
        Storage::disk('local')->assertMissing($oldPath);
        Storage::disk('local')->assertExists((string) $entry->session_photos_json[0]['path']);
    }

    private function createDistrict(string $slug = 'eap-test-district', string $name = 'Test District'): District
    {
        $hub = Hub::query()->firstOrCreate(
            ['slug' => 'eap-test-hub'],
            ['name' => 'EAP Test Hub', 'sort_order' => 1]
        );

        return District::query()->create([
            'hub_id' => $hub->id,
            'slug' => $slug,
            'name' => $name,
            'sort_order' => 1,
        ]);
    }
}
