<?php

namespace Tests\Feature;

use App\Models\BlockWorkshop;
use App\Models\District;
use App\Models\Hub;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

class MediaGalleryTest extends TestCase
{
    use RefreshDatabase;

    public function test_state_admin_can_open_media_gallery(): void
    {
        $admin = User::factory()->create(['role' => 'state_admin', 'is_active' => true]);

        $this->actingAs($admin)
            ->get(route('admin.media-gallery.index'))
            ->assertOk()
            ->assertSee('Media Gallery')
            ->assertSee('Block Workshops');
    }

    public function test_state_admin_can_open_known_section(): void
    {
        $admin = User::factory()->create(['role' => 'state_admin', 'is_active' => true]);

        $this->actingAs($admin)
            ->get(route('admin.media-gallery.section', 'block-workshops'))
            ->assertOk()
            ->assertSee('Block Workshops');
    }

    public function test_unknown_section_returns_404(): void
    {
        $admin = User::factory()->create(['role' => 'state_admin', 'is_active' => true]);

        $this->actingAs($admin)
            ->get(route('admin.media-gallery.section', 'not-a-real-section'))
            ->assertNotFound();
    }

    public function test_non_state_admin_cannot_open_media_gallery(): void
    {
        $staff = User::factory()->create(['role' => 'district_staff', 'is_active' => true]);

        $this->actingAs($staff)
            ->get('/admin/media-gallery')
            ->assertForbidden();
    }

    public function test_album_shows_description_and_briefing(): void
    {
        [$admin, $workshop] = $this->createWorkshopAlbum();

        $this->actingAs($admin)
            ->get(route('admin.media-gallery.show', ['block-workshops', $workshop->id]))
            ->assertOk()
            ->assertSee('Demo Area')
            ->assertSee('Village orientation on CFA onboarding')
            ->assertSee('About this event')
            ->assertSee('Select all')
            ->assertSee('Copy original');
    }

    public function test_section_lists_album_briefing(): void
    {
        [$admin] = $this->createWorkshopAlbum();

        $this->actingAs($admin)
            ->get(route('admin.media-gallery.section', 'block-workshops'))
            ->assertOk()
            ->assertSee('Village orientation on CFA onboarding');
    }

    public function test_photo_thumb_is_cached_and_smaller_than_original(): void
    {
        [$admin, $workshop] = $this->createWorkshopAlbum();

        $thumb = $this->actingAs($admin)->get(route('admin.media-gallery.photo', [
            'section' => 'block-workshops',
            'record' => $workshop->id,
            'collection' => 'visit_media_json',
            'index' => 0,
            'inline' => 1,
            'size' => 'thumb',
        ]));
        $thumb->assertOk();
        $this->assertStringContainsString('private', (string) $thumb->headers->get('Cache-Control'));
        $this->assertStringContainsString('max-age=86400', (string) $thumb->headers->get('Cache-Control'));
        $thumb->assertHeader('Content-Type', 'image/jpeg');

        $original = $this->actingAs($admin)->get(route('admin.media-gallery.photo', [
            'section' => 'block-workshops',
            'record' => $workshop->id,
            'collection' => 'visit_media_json',
            'index' => 0,
        ]));
        $original->assertOk();
        $original->assertHeader('content-disposition', 'attachment; filename="workshop-a.jpg"');

        $this->assertLessThan(strlen($original->streamedContent()), strlen($thumb->streamedContent()));
    }

    public function test_zip_download_can_include_only_selected_originals(): void
    {
        [$admin, $workshop] = $this->createWorkshopAlbum();

        $response = $this->actingAs($admin)->get(route('admin.media-gallery.zip', [
            'section' => 'block-workshops',
            'record' => $workshop->id,
            'indices' => '0',
        ]));
        $response->assertOk();

        $tmp = tempnam(sys_get_temp_dir(), 'mgtest_');
        $this->assertIsString($tmp);
        file_put_contents($tmp, $response->streamedContent());

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($tmp) === true);
        $this->assertSame(1, $zip->numFiles);
        $this->assertSame('workshop-a.jpg', $zip->getNameIndex(0));
        $zip->close();
        @unlink($tmp);
    }

    /**
     * @return array{0: User, 1: BlockWorkshop}
     */
    private function createWorkshopAlbum(): array
    {
        Storage::fake('local');

        $admin = User::factory()->create(['role' => 'state_admin', 'is_active' => true]);
        $hub = Hub::query()->firstOrCreate(['slug' => 'mg-hub'], ['name' => 'Media Hub', 'sort_order' => 1]);
        $district = District::query()->create([
            'hub_id' => $hub->id,
            'slug' => 'mg-district',
            'name' => 'Media District',
            'sort_order' => 1,
        ]);

        $first = UploadedFile::fake()->image('workshop-a.jpg', 1600, 1200)->storeAs('gallery-test', 'workshop-a.jpg');
        $second = UploadedFile::fake()->image('workshop-b.jpg', 1600, 1200)->storeAs('gallery-test', 'workshop-b.jpg');

        $workshop = BlockWorkshop::query()->create([
            'field_coordinator_name' => 'Field Staff',
            'visit_date' => '2026-05-18',
            'entry_date' => '2026-05-18',
            'district_id' => $district->id,
            'block' => 'Garur',
            'area' => 'Demo Area',
            'remark' => 'Village orientation on CFA onboarding',
            'status' => BlockWorkshop::STATUS_SUBMITTED,
            'participants_json' => [],
            'visit_media_json' => [
                ['path' => $first, 'original_name' => 'workshop-a.jpg', 'mime' => 'image/jpeg'],
                ['path' => $second, 'original_name' => 'workshop-b.jpg', 'mime' => 'image/jpeg'],
            ],
        ]);

        return [$admin, $workshop];
    }
}
