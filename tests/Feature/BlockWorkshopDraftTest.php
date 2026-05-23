<?php

namespace Tests\Feature;

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

class BlockWorkshopDraftTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_create_draft_and_autosave_participants(): void
    {
        if (! FieldCoordinatorAttendanceReport::supportsDraftWorkflow()) {
            $this->markTestSkipped('Draft workflow migration not applied.');
        }

        Storage::fake();

        [$staff, $block, $gp] = $this->staffWithBlockAndGp();

        $create = $this->actingAs($staff)->postJson(route('staff.attendance.draft.create'));
        $create->assertCreated();
        $draftId = (int) $create->json('id');

        $this->actingAs($staff)->patchJson(route('staff.attendance.draft.meta', $draftId), [
            'visit_date' => '2026-05-20',
            'district_block_id' => $block->id,
            'gram_panchayat_id' => $gp->id,
            'area' => 'Market',
            'participants_male_count' => 1,
            'participants_female_count' => 1,
        ])->assertOk()
            ->assertJsonPath('participants_total', 2);

        $this->actingAs($staff)->patchJson(route('staff.attendance.participants.save', $draftId), [
            'participants' => [
                ['name' => 'Ram', 'mobile' => '9876543210', 'gender' => 'M', 'gram_panchayat_id' => $gp->id, 'gram_panchayat_name' => $gp->name],
                ['name' => 'Sita', 'mobile' => '', 'gender' => 'F', 'gram_panchayat_id' => $gp->id, 'gram_panchayat_name' => $gp->name],
            ],
        ])->assertOk();

        $draft = FieldCoordinatorAttendanceReport::query()->findOrFail($draftId);
        $this->assertTrue($draft->isDraft());
        $this->assertCount(2, $draft->participantRows());
        $this->assertSame('Ram', $draft->participantRows()[0]['name']);
    }

    public function test_staff_can_submit_draft_with_photos(): void
    {
        if (! FieldCoordinatorAttendanceReport::supportsDraftWorkflow()) {
            $this->markTestSkipped('Draft workflow migration not applied.');
        }

        Storage::fake();

        [$staff, $block, $gp] = $this->staffWithBlockAndGp();

        $draft = FieldCoordinatorAttendanceReport::query()->create([
            'field_coordinator_user_id' => $staff->id,
            'field_coordinator_name' => $staff->name,
            'visit_date' => '2026-05-20',
            'entry_date' => '2026-05-20',
            'district_id' => $staff->district_id,
            'district_block_id' => $block->id,
            'gram_panchayat_id' => $gp->id,
            'block' => $block->name,
            'area' => 'Market',
            'status' => FieldCoordinatorAttendanceReport::STATUS_DRAFT,
            'participants_male_count' => 1,
            'participants_female_count' => 0,
            'participants_total' => 1,
            'participants_json' => [],
        ]);

        $this->actingAs($staff)->post(route('staff.attendance.photos.upload', $draft), [
            'visit_media' => [
                UploadedFile::fake()->create('w.jpg', 100, 'image/jpeg'),
            ],
        ])->assertOk();

        $this->actingAs($staff)->post(route('staff.attendance.draft.submit', $draft), [
            'visit_date' => '2026-05-20',
            'district_block_id' => $block->id,
            'gram_panchayat_id' => $gp->id,
            'area' => 'Market',
            'participants_male_count' => 1,
            'participants_female_count' => 0,
        ])->assertRedirect(route('staff.attendance.index'));

        $draft->refresh();
        $this->assertTrue($draft->isSubmitted());
        $this->assertNotEmpty($draft->visitMediaItems());
    }

    public function test_get_submit_url_redirects_to_draft_form(): void
    {
        if (! FieldCoordinatorAttendanceReport::supportsDraftWorkflow()) {
            $this->markTestSkipped('Draft workflow migration not applied.');
        }

        [$staff, $block, $gp] = $this->staffWithBlockAndGp();

        $draft = FieldCoordinatorAttendanceReport::query()->create([
            'field_coordinator_user_id' => $staff->id,
            'field_coordinator_name' => $staff->name,
            'visit_date' => '2026-05-20',
            'entry_date' => '2026-05-20',
            'district_id' => $staff->district_id,
            'status' => FieldCoordinatorAttendanceReport::STATUS_DRAFT,
            'participants_total' => 0,
        ]);

        $this->actingAs($staff)
            ->get(route('staff.attendance.draft.submit.redirect', $draft))
            ->assertRedirect(route('staff.attendance.index', ['draft' => $draft->id]));
    }

    public function test_drafts_are_excluded_from_district_view_list(): void
    {
        if (! FieldCoordinatorAttendanceReport::supportsDraftWorkflow()) {
            $this->markTestSkipped('Draft workflow migration not applied.');
        }

        [$staff, $block, $gp] = $this->staffWithBlockAndGp();

        FieldCoordinatorAttendanceReport::query()->create([
            'field_coordinator_user_id' => $staff->id,
            'field_coordinator_name' => $staff->name,
            'visit_date' => '2026-05-18',
            'entry_date' => '2026-05-18',
            'district_id' => $staff->district_id,
            'status' => FieldCoordinatorAttendanceReport::STATUS_DRAFT,
            'participants_total' => 0,
        ]);

        FieldCoordinatorAttendanceReport::query()->create([
            'field_coordinator_user_id' => $staff->id,
            'field_coordinator_name' => $staff->name,
            'visit_date' => '2026-05-19',
            'entry_date' => '2026-05-19',
            'district_id' => $staff->district_id,
            'status' => FieldCoordinatorAttendanceReport::STATUS_SUBMITTED,
            'block' => $block->name,
            'participants_total' => 5,
            'visit_media_json' => [['path' => 'x', 'original_name' => 'a.jpg']],
        ]);

        $this->actingAs($staff)
            ->get(route('staff.attendance.view'))
            ->assertOk()
            ->assertSee('5')
            ->assertDontSee('Draft');
    }

    /**
     * @return array{0: User, 1: DistrictBlock, 2: GramPanchayat}
     */
    private function staffWithBlockAndGp(): array
    {
        $hub = Hub::query()->create(['slug' => 'bw-hub', 'name' => 'Hub', 'sort_order' => 1]);
        $district = District::query()->create([
            'hub_id' => $hub->id,
            'slug' => 'bw-district',
            'name' => 'District',
            'sort_order' => 1,
        ]);
        $staff = User::factory()->create([
            'role' => 'district_staff',
            'district_id' => $district->id,
            'hub_id' => $hub->id,
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
