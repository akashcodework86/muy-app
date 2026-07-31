<?php

namespace Tests\Feature;

use App\Models\MarketingPartnerOutreachEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PartnerOutreachTest extends TestCase
{
    use RefreshDatabase;

    public function test_partner_outreach_index_redirects_sanjna_to_create(): void
    {
        $sanjna = User::factory()->create([
            'role' => 'state_staff',
            'name' => 'Sanjna Mishra',
            'is_active' => true,
        ]);

        $this->actingAs($sanjna)
            ->get(route('spoc.partner-outreach.index'))
            ->assertRedirect(route('spoc.partner-outreach.create'));
    }

    public function test_sanjna_can_create_partner_outreach_entry(): void
    {
        $sanjna = User::factory()->create([
            'role' => 'state_staff',
            'name' => 'Sanjna Mishra',
            'is_active' => true,
        ]);

        $response = $this->actingAs($sanjna)->post(route('spoc.partner-outreach.store'), [
            'outreach_date' => '2026-06-10',
            'partner_name' => 'Mountain Retail Pvt Ltd',
            'partner_designation' => 'CEO',
            'partner_link' => 'https://example.com/partner',
            'cohort_or_sector' => 'retail_trade',
            'poc_name' => 'Anita',
            'poc_contact_method' => 'phone',
            'poc_phone' => '9876543210',
            'remarks' => 'Initial meeting done',
        ]);

        $response->assertRedirect(route('spoc.partner-outreach.dashboard'));

        $this->assertDatabaseHas('marketing_partner_outreach_entries', [
            'partner_name' => 'Mountain Retail Pvt Ltd',
            'partner_designation' => 'CEO',
            'status' => MarketingPartnerOutreachEntry::STATUS_OUTREACH,
            'submitted_by_user_id' => $sanjna->id,
        ]);
    }

    public function test_other_state_staff_cannot_create_partner_outreach_entry(): void
    {
        $other = User::factory()->create([
            'role' => 'state_staff',
            'name' => 'Other Staff',
            'is_active' => true,
        ]);

        $this->actingAs($other)
            ->get(route('spoc.partner-outreach.create'))
            ->assertForbidden();
    }

    public function test_state_admin_can_view_dashboard_but_not_update_status(): void
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

        $entry = MarketingPartnerOutreachEntry::query()->create([
            'outreach_date' => '2026-06-10',
            'partner_name' => 'Test Partner',
            'partner_designation' => 'Manager',
            'cohort_or_sector' => 'services',
            'poc_phone' => '9876543210',
            'status' => MarketingPartnerOutreachEntry::STATUS_OUTREACH,
            'submitted_by_user_id' => $sanjna->id,
            'submitted_by_name' => $sanjna->name,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.partner-outreach.dashboard'))
            ->assertOk();

        $this->actingAs($admin)
            ->patch(route('spoc.partner-outreach.update-status', $entry), [
                'status' => MarketingPartnerOutreachEntry::STATUS_ONBOARDED_LOA,
                'onboarding_date' => '2026-06-12',
            ])
            ->assertForbidden();
    }

    public function test_sanjna_can_update_status_to_onboarded_mou(): void
    {
        Storage::fake('local');

        $sanjna = User::factory()->create([
            'role' => 'state_staff',
            'name' => 'Sanjna Mishra',
            'is_active' => true,
        ]);

        $entry = MarketingPartnerOutreachEntry::query()->create([
            'outreach_date' => '2026-06-10',
            'partner_name' => 'MoU Partner',
            'partner_designation' => 'Director',
            'cohort_or_sector' => 'food_processing',
            'poc_phone' => '9876543210',
            'status' => MarketingPartnerOutreachEntry::STATUS_IN_DISCUSSION,
            'submitted_by_user_id' => $sanjna->id,
            'submitted_by_name' => $sanjna->name,
        ]);

        $response = $this->actingAs($sanjna)->patch(route('spoc.partner-outreach.update-status', $entry), [
            'status' => MarketingPartnerOutreachEntry::STATUS_ONBOARDED_MOU,
            'onboarding_date' => '2026-06-15',
            'agreement_document' => UploadedFile::fake()->create('mou.pdf', 100, 'application/pdf'),
        ]);

        $response->assertRedirect(route('spoc.partner-outreach.show', $entry));

        $entry->refresh();
        $this->assertSame(MarketingPartnerOutreachEntry::STATUS_ONBOARDED_MOU, $entry->status);
        $this->assertTrue($entry->hasAgreementDocument());
        $this->assertSame($sanjna->id, (int) $entry->status_updated_by_user_id);
    }

    public function test_onboarded_partner_status_cannot_be_changed_again(): void
    {
        $sanjna = User::factory()->create([
            'role' => 'state_staff',
            'name' => 'Sanjna Mishra',
            'is_active' => true,
        ]);

        $entry = MarketingPartnerOutreachEntry::query()->create([
            'outreach_date' => '2026-06-10',
            'partner_name' => 'Locked Partner',
            'partner_designation' => 'CEO',
            'cohort_or_sector' => 'services',
            'poc_phone' => '9876543210',
            'status' => MarketingPartnerOutreachEntry::STATUS_ONBOARDED_LOI,
            'onboarding_date' => '2026-06-12',
            'submitted_by_user_id' => $sanjna->id,
            'submitted_by_name' => $sanjna->name,
        ]);

        $this->actingAs($sanjna)
            ->patch(route('spoc.partner-outreach.update-status', $entry), [
                'status' => MarketingPartnerOutreachEntry::STATUS_DECLINED,
            ])
            ->assertSessionHasErrors('status');
    }
}
