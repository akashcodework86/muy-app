<?php

namespace Tests\Feature;

use App\Models\SocialMediaPost;
use App\Models\User;
use App\Services\SocialMediaPostPreviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SocialMediaPostTest extends TestCase
{
    use RefreshDatabase;

    public function test_social_media_index_redirects_submitter_to_create(): void
    {
        $sanjna = User::factory()->create([
            'role' => 'state_staff',
            'name' => 'Sanjna Mishra',
            'is_active' => true,
        ]);

        $this->actingAs($sanjna)
            ->get(route('spoc.social-media-posts.index'))
            ->assertRedirect(route('spoc.social-media-posts.create'));
    }

    public function test_sanjna_state_staff_can_create_post(): void
    {
        Http::fake([
            'https://www.instagram.com/api/v1/oembed/*' => Http::response([
                'thumbnail_url' => 'https://cdn.example.com/thumb.jpg',
                'title' => 'Launch post preview',
                'author_name' => 'muy_uk',
            ]),
            'https://api.instagram.com/oembed*' => Http::response([], 404),
        ]);

        $sanjna = User::factory()->create([
            'role' => 'state_staff',
            'name' => 'Sanjna Mishra',
            'is_active' => true,
        ]);

        $response = $this->actingAs($sanjna)->post(route('spoc.social-media-posts.store'), [
            'posted_on' => '2026-05-20',
            'post_url' => 'https://www.instagram.com/reel/abc123/',
            'posted_platforms' => ['instagram', 'facebook'],
            'description' => 'Launch post',
        ]);

        $response->assertRedirect(route('spoc.social-media-posts.dashboard'));

        $this->assertDatabaseHas('social_media_posts', [
            'submitted_by_user_id' => $sanjna->id,
            'post_url' => 'https://www.instagram.com/reel/abc123/',
            'description' => 'Launch post',
            'thumbnail_url' => 'https://cdn.example.com/thumb.jpg',
            'preview_title' => 'Launch post preview',
            'platform' => 'Instagram',
        ]);

        $post = SocialMediaPost::query()->where('post_url', 'https://www.instagram.com/reel/abc123/')->first();
        $this->assertNotNull($post);
        $this->assertSame(['instagram', 'facebook'], $post->posted_platforms);
    }

    public function test_create_post_requires_at_least_one_platform(): void
    {
        $sanjna = User::factory()->create([
            'role' => 'state_staff',
            'name' => 'Sanjna Mishra',
            'is_active' => true,
        ]);

        $this->actingAs($sanjna)
            ->post(route('spoc.social-media-posts.store'), [
                'posted_on' => '2026-05-20',
                'post_url' => 'https://www.instagram.com/reel/abc123/',
                'description' => 'Launch post',
            ])
            ->assertSessionHasErrors('posted_platforms');
    }

    public function test_other_state_staff_cannot_create_post(): void
    {
        $other = User::factory()->create([
            'role' => 'state_staff',
            'name' => 'Other SPOC',
            'is_active' => true,
        ]);

        $this->actingAs($other)
            ->get(route('spoc.social-media-posts.create'))
            ->assertForbidden();
    }

    public function test_state_admin_can_view_dashboard(): void
    {
        $admin = User::factory()->create([
            'role' => 'state_admin',
            'is_active' => true,
        ]);

        $sanjna = User::factory()->create([
            'role' => 'state_staff',
            'name' => 'Sanjna Mishra',
            'is_active' => true,
        ]);

        SocialMediaPost::query()->create([
            'submitted_by_user_id' => $sanjna->id,
            'submitted_by_name' => $sanjna->name,
            'posted_on' => '2026-05-19',
            'post_url' => 'https://example.com/post/2',
            'platform' => 'Instagram',
            'thumbnail_url' => 'https://cdn.example.com/admin-thumb.jpg',
            'preview_title' => 'Test reel',
            'description' => null,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.social-media-posts.dashboard'))
            ->assertOk()
            ->assertSee('1 total')
            ->assertSee('Total entries:')
            ->assertSee('https://example.com/post/2')
            ->assertSee('https://cdn.example.com/admin-thumb.jpg', false);
    }

    public function test_submitter_sees_only_own_entries_on_dashboard(): void
    {
        $sanjna = User::factory()->create([
            'role' => 'state_staff',
            'name' => 'Sanjna Mishra',
            'is_active' => true,
        ]);

        $other = User::factory()->create([
            'role' => 'state_staff',
            'name' => 'Other SPOC',
            'is_active' => true,
        ]);

        SocialMediaPost::query()->create([
            'submitted_by_user_id' => $sanjna->id,
            'submitted_by_name' => $sanjna->name,
            'posted_on' => '2026-05-18',
            'post_url' => 'https://example.com/mine',
            'description' => null,
        ]);

        SocialMediaPost::query()->create([
            'submitted_by_user_id' => $other->id,
            'submitted_by_name' => $other->name,
            'posted_on' => '2026-05-17',
            'post_url' => 'https://example.com/theirs',
            'description' => null,
        ]);

        $this->actingAs($sanjna)
            ->get(route('spoc.social-media-posts.dashboard'))
            ->assertOk()
            ->assertSee('https://example.com/mine')
            ->assertDontSee('https://example.com/theirs');
    }

    public function test_sanjna_can_delete_own_post(): void
    {
        $sanjna = User::factory()->create([
            'role' => 'state_staff',
            'name' => 'Sanjna Mishra',
            'is_active' => true,
        ]);

        $post = SocialMediaPost::query()->create([
            'submitted_by_user_id' => $sanjna->id,
            'submitted_by_name' => $sanjna->name,
            'posted_on' => '2026-05-20',
            'post_url' => 'https://example.com/to-delete',
            'description' => null,
        ]);

        $this->actingAs($sanjna)
            ->delete(route('spoc.social-media-posts.destroy', $post))
            ->assertRedirect(route('spoc.social-media-posts.dashboard'));

        $this->assertDatabaseMissing('social_media_posts', ['id' => $post->id]);
    }

    public function test_state_admin_can_delete_any_post(): void
    {
        $admin = User::factory()->create([
            'role' => 'state_admin',
            'is_active' => true,
        ]);

        $sanjna = User::factory()->create([
            'role' => 'state_staff',
            'name' => 'Sanjna Mishra',
            'is_active' => true,
        ]);

        $post = SocialMediaPost::query()->create([
            'submitted_by_user_id' => $sanjna->id,
            'submitted_by_name' => $sanjna->name,
            'posted_on' => '2026-05-20',
            'post_url' => 'https://example.com/admin-delete',
            'description' => null,
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.social-media-posts.destroy', $post))
            ->assertRedirect(route('admin.social-media-posts.dashboard'));

        $this->assertDatabaseMissing('social_media_posts', ['id' => $post->id]);
    }

    public function test_other_state_staff_cannot_delete_post(): void
    {
        $sanjna = User::factory()->create([
            'role' => 'state_staff',
            'name' => 'Sanjna Mishra',
            'is_active' => true,
        ]);

        $other = User::factory()->create([
            'role' => 'state_staff',
            'name' => 'Other SPOC',
            'is_active' => true,
        ]);

        $post = SocialMediaPost::query()->create([
            'submitted_by_user_id' => $sanjna->id,
            'submitted_by_name' => $sanjna->name,
            'posted_on' => '2026-05-20',
            'post_url' => 'https://example.com/protected',
            'description' => null,
        ]);

        $this->actingAs($other)
            ->delete(route('spoc.social-media-posts.destroy', $post))
            ->assertForbidden();

        $this->assertDatabaseHas('social_media_posts', ['id' => $post->id]);
    }

    public function test_preview_returns_instagram_embed_for_instagram_url(): void
    {
        Http::fake([
            'https://www.instagram.com/api/v1/oembed/*' => Http::response([
                'thumbnail_url' => 'https://cdninstagram.com/thumb.jpg',
                'title' => 'Reel preview',
                'author_name' => 'muy_uk',
            ]),
            'https://api.instagram.com/oembed*' => Http::response([], 404),
        ]);

        $sanjna = User::factory()->create([
            'role' => 'state_staff',
            'name' => 'Sanjna Mishra',
            'is_active' => true,
        ]);

        $url = 'https://www.instagram.com/p/DYFlvO0kQg9/';

        $this->actingAs($sanjna)
            ->getJson(route('spoc.social-media-posts.preview', ['url' => $url]))
            ->assertOk()
            ->assertJsonPath('mode', 'instagram_embed')
            ->assertJsonPath('platform', 'Instagram')
            ->assertJsonPath('url', $url);
    }

    public function test_preview_returns_thumbnail_for_instagram_when_oembed_available(): void
    {
        Http::fake([
            'https://www.instagram.com/api/v1/oembed/*' => Http::response([
                'thumbnail_url' => 'https://cdninstagram.com/thumb.jpg',
                'title' => 'Reel preview',
                'author_name' => 'muy_uk',
            ]),
            'https://api.instagram.com/oembed*' => Http::response([], 404),
        ]);

        $sanjna = User::factory()->create([
            'role' => 'state_staff',
            'name' => 'Sanjna Mishra',
            'is_active' => true,
        ]);

        $url = 'https://www.instagram.com/reel/DXrh2ZBDLua/';

        $this->actingAs($sanjna)
            ->getJson(route('spoc.social-media-posts.preview', ['url' => $url]))
            ->assertOk()
            ->assertJsonPath('mode', 'instagram_embed')
            ->assertJsonPath('platform', 'Instagram')
            ->assertJsonPath('url', $url);
    }

    public function test_preview_service_maps_youtube_to_embed(): void
    {
        $preview = app(SocialMediaPostPreviewService::class)->resolve(
            'https://www.youtube.com/watch?v=dQw4w9WgXcQ'
        );

        $this->assertSame('iframe', $preview['mode']);
        $this->assertStringContainsString('youtube.com/embed/', (string) $preview['iframe_src']);
    }
}
