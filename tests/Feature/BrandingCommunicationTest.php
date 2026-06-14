<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BrandingCommunicationTest extends TestCase
{
    use RefreshDatabase;

    private function sanjna(): User
    {
        return User::factory()->create([
            'role' => 'state_staff',
            'name' => 'Sanjna Mishra',
            'is_active' => true,
        ]);
    }

    public function test_non_allowlisted_user_cannot_create_case_study_entry(): void
    {
        $user = User::factory()->create(['role' => 'state_staff', 'name' => 'Other Staff', 'is_active' => true]);

        $this->actingAs($user)
            ->get(route('spoc.case-study-entries.create'))
            ->assertForbidden();
    }

    public function test_sanjna_can_view_case_study_dashboard(): void
    {
        $sanjna = $this->sanjna();

        $this->actingAs($sanjna)
            ->get(route('spoc.case-study-entries.dashboard'))
            ->assertOk();
    }

    public function test_state_admin_can_view_newsletter_dashboard(): void
    {
        $admin = User::factory()->create(['role' => 'state_admin', 'is_active' => true]);

        $this->actingAs($admin)
            ->get(route('admin.muy-newsletters.dashboard'))
            ->assertOk();
    }

    public function test_sanjna_can_create_newsletter_with_document(): void
    {
        Storage::fake('local');
        $sanjna = $this->sanjna();

        $response = $this->actingAs($sanjna)->post(route('spoc.muy-newsletters.store'), [
            'issue_date' => '2026-06-01',
            'issue_edition' => 'Issue 1',
            'title' => 'MUY Highlights',
            'distribution_mode' => 'email',
            'document' => UploadedFile::fake()->create('newsletter.pdf', 100, 'application/pdf'),
        ]);

        $response->assertRedirect(route('spoc.muy-newsletters.dashboard'));

        $this->assertDatabaseHas('muy_newsletter_entries', [
            'title' => 'MUY Highlights',
            'submitted_by_user_id' => $sanjna->id,
        ]);
    }

    public function test_sanjna_can_create_media_campaign_with_document_and_multimedia(): void
    {
        Storage::fake('local');
        $sanjna = $this->sanjna();

        $response = $this->actingAs($sanjna)->post(route('spoc.media-campaigns.store'), [
            'campaign_date' => '2026-06-02',
            'media_type' => 'radio',
            'channel_name' => 'Red FM',
            'coverage_area' => 'Dehradun',
            'campaign_title' => 'MUY Radio Spot',
            'document' => UploadedFile::fake()->create('script.pdf', 100, 'application/pdf'),
            'multimedia' => [
                UploadedFile::fake()->image('proof.jpg'),
            ],
        ]);

        $response->assertRedirect(route('spoc.media-campaigns.dashboard'));

        $this->assertDatabaseHas('media_campaign_entries', [
            'campaign_title' => 'MUY Radio Spot',
            'submitted_by_user_id' => $sanjna->id,
        ]);

        $this->assertDatabaseCount('media_campaign_attachments', 1);
    }
}
