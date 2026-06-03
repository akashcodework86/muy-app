<?php

namespace Tests\Feature;

use App\Models\District;
use App\Models\DistrictBlock;
use App\Models\EapEdpSession;
use App\Models\GramPanchayat;
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

    public function test_district_staff_can_store_session_with_venue_photos_and_optional_attendance(): void
    {
        Storage::fake();
        [$staff, $block, $gp] = $this->staffWithBlockAndGp();

        $response = $this->actingAs($staff)->post(route('staff.eap-edp-sessions.store'), [
            'session_date' => '2026-05-20',
            'venue_name_address' => 'Community Hall, Main Road, Tehri Garhwal',
            'workshop_mode' => 'physical',
            'attendance_male_count' => 1,
            'attendance_female_count' => 1,
            'district_block_id' => $block->id,
            'gram_panchayat_id' => $gp->id,
            'participants' => [
                ['name' => 'Ram', 'mobile' => '9876543210', 'gender' => 'M', 'gram_panchayat_id' => $gp->id, 'gram_panchayat_name' => $gp->name],
                ['name' => 'Sita', 'mobile' => '', 'gender' => 'F', 'gram_panchayat_id' => $gp->id, 'gram_panchayat_name' => $gp->name],
            ],
            'session_photos' => [
                UploadedFile::fake()->create('camp-1.jpg', 100, 'image/jpeg'),
                UploadedFile::fake()->create('camp-2.png', 100, 'image/png'),
            ],
        ]);

        $response->assertRedirect(route('staff.eap-edp-sessions.dashboard'));

        $entry = EapEdpSession::query()->firstOrFail();
        $this->assertSame('Community Hall, Main Road, Tehri Garhwal', (string) $entry->display_venue);
        $this->assertTrue(Schema::hasColumn('eap_edp_sessions', 'session_photos_json'));
        $this->assertCount(2, (array) $entry->session_photos_json);
        $this->assertSame([], (array) $entry->attendance_media_json);
        $this->assertCount(2, $entry->participantRows());
        $this->assertSame('Ram', $entry->participantRows()[0]['name']);
        Storage::disk('local')->assertExists((string) $entry->session_photos_json[0]['path']);
    }

    public function test_store_requires_session_photos(): void
    {
        [$staff, $block, $gp] = $this->staffWithBlockAndGp('eap-no-photo', 'No Photo District');

        $response = $this->actingAs($staff)->post(route('staff.eap-edp-sessions.store'), [
            'session_date' => '2026-05-20',
            'venue_name_address' => 'Test venue',
            'workshop_mode' => 'physical',
            'attendance_male_count' => 1,
            'attendance_female_count' => 0,
            'district_block_id' => $block->id,
            'gram_panchayat_id' => $gp->id,
        ]);

        $response->assertSessionHasErrors(['session_photos']);
        $this->assertDatabaseCount('eap_edp_sessions', 0);
    }

    public function test_update_can_add_and_remove_session_photos(): void
    {
        Storage::fake();
        [$staff, $block, $gp] = $this->staffWithBlockAndGp('test-district-2', 'Test District 2');

        $entry = EapEdpSession::query()->create([
            'submitted_by_user_id' => $staff->id,
            'submitted_by_name' => $staff->name,
            'event_date' => '2026-05-18',
            'district_id' => $staff->district_id,
            'district_name' => 'Test District 2',
            'program_type' => 'eap_edp',
            'venue_name_address' => 'Original venue',
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
                'path' => UploadedFile::fake()->create('old.jpg', 100, 'image/jpeg')->store('eap-edp-session-photos'),
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
            'venue_name_address' => 'Updated venue address',
            'workshop_mode' => 'virtual',
            'attendance_male_count' => 1,
            'attendance_female_count' => 1,
            'district_block_id' => $block->id,
            'gram_panchayat_id' => $gp->id,
            'participants' => [
                ['name' => 'A', 'gender' => 'M', 'gram_panchayat_id' => $gp->id, 'gram_panchayat_name' => $gp->name],
                ['name' => 'B', 'gender' => 'F', 'gram_panchayat_id' => $gp->id, 'gram_panchayat_name' => $gp->name],
            ],
            'remove_photo_indices' => [0],
            'session_photos' => [
                UploadedFile::fake()->create('new.jpg', 100, 'image/jpeg'),
            ],
        ]);

        $response->assertRedirect(route('staff.eap-edp-sessions.dashboard'));

        $entry->refresh();
        $this->assertSame('Updated venue address', (string) $entry->display_venue);
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

    /**
     * @return array{0: User, 1: DistrictBlock, 2: GramPanchayat}
     */
    private function staffWithBlockAndGp(string $districtSlug = 'eap-test-district', string $districtName = 'Test District'): array
    {
        $district = $this->createDistrict($districtSlug, $districtName);
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

        return [$staff, $block, $gp];
    }
}
