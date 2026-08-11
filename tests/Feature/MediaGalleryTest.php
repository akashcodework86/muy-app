<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

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
}
