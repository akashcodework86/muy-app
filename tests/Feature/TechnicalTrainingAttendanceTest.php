<?php

namespace Tests\Feature;

use App\Models\District;
use App\Models\FiscalYear;
use App\Models\Hub;
use App\Models\TechnicalTraining;
use App\Models\User;
use App\Models\ServiceCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TechnicalTrainingAttendanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_district_staff_can_submit_technical_training_attendance(): void
    {
        $district = $this->createDistrict();
        $staff = User::factory()->create([
            'role' => 'district_staff',
            'district_id' => $district->id,
            'is_active' => true,
        ]);
        $incubateeId = $this->seedOnboardedIncubatee($district);

        $response = $this->actingAs($staff)->post(route('staff.technical-trainings.store'), [
            'session_date' => '2026-05-18',
            'session_name' => 'Product design workshop',
            'session_brief' => 'Hands-on session for packaging design.',
            'selected_incubatees' => [$incubateeId],
        ]);

        $response->assertRedirect(route('staff.technical-trainings.dashboard'));

        $this->assertDatabaseHas('technical_trainings', [
            'district_id' => $district->id,
            'session_name' => 'Product design workshop',
            'session_brief' => 'Hands-on session for packaging design.',
            'submitted_by_user_id' => $staff->id,
            'status' => ServiceCase::STATUS_PENDING_APPROVAL,
        ]);
    }

    public function test_district_staff_can_submit_non_onboarded_applicant(): void
    {
        $district = $this->createDistrict();
        $staff = User::factory()->create([
            'role' => 'district_staff',
            'district_id' => $district->id,
            'is_active' => true,
        ]);
        $applicantId = $this->seedPhase3Applicant($district, 'Non Onboarded Applicant');

        $response = $this->actingAs($staff)->post(route('staff.technical-trainings.store'), [
            'session_date' => '2026-05-18',
            'session_name' => 'Open session',
            'selected_incubatees' => [$applicantId],
        ]);

        $response->assertRedirect(route('staff.technical-trainings.dashboard'));

        $entry = TechnicalTraining::query()->firstOrFail();
        $this->assertSame([$applicantId], (array) $entry->selected_incubatee_ids);
        $this->assertSame('non_onboarded', $entry->selected_incubatees_snapshot[0]['onboard_status'] ?? null);
    }

    public function test_create_form_lists_non_onboarded_applicants(): void
    {
        $district = $this->createDistrict();
        $staff = User::factory()->create([
            'role' => 'district_staff',
            'district_id' => $district->id,
            'is_active' => true,
        ]);
        $this->seedOnboardedIncubatee($district);
        $this->seedPhase3Applicant($district, 'Visible Non Onboarded');

        $response = $this->actingAs($staff)->get(route('staff.technical-trainings.create'));

        $response->assertOk();
        $response->assertSee('Visible Non Onboarded');
        $response->assertSee('Not onboarded');
        $response->assertSee('rbiphase3 applicants in district');
    }

    public function test_district_staff_cannot_submit_without_district_assignment(): void
    {
        $staff = User::factory()->create([
            'role' => 'district_staff',
            'district_id' => null,
            'is_active' => true,
        ]);

        $response = $this->actingAs($staff)->post(route('staff.technical-trainings.store'), [
            'session_date' => '2026-05-18',
            'session_name' => 'Product design workshop',
            'selected_incubatees' => [1],
        ]);

        $response->assertStatus(422);
    }

    public function test_district_staff_dashboard_is_scoped_to_own_district(): void
    {
        $districtA = $this->createDistrict('district-a', 'District A');
        $districtB = $this->createDistrict('district-b', 'District B');
        $staffA = User::factory()->create([
            'role' => 'district_staff',
            'district_id' => $districtA->id,
            'is_active' => true,
        ]);

        $this->createTechnicalTraining($districtA, $staffA, 'Visible session');
        $this->createTechnicalTraining($districtB, User::factory()->create([
            'role' => 'district_staff',
            'district_id' => $districtB->id,
            'is_active' => true,
        ]), 'Hidden session');

        $response = $this->actingAs($staffA)->get(route('staff.technical-trainings.dashboard'));

        $response->assertOk();
        $response->assertSee('Visible session');
        $response->assertDontSee('Hidden session');
    }

    public function test_state_admin_and_spoc_can_view_statewide_dashboard(): void
    {
        $district = $this->createDistrict();
        $staff = User::factory()->create([
            'role' => 'district_staff',
            'district_id' => $district->id,
            'is_active' => true,
        ]);
        $admin = User::factory()->create(['role' => 'state_admin', 'is_active' => true]);
        $spoc = User::factory()->create(['role' => 'state_staff', 'is_active' => true]);

        $this->createTechnicalTraining($district, $staff, 'Statewide session');

        $this->actingAs($admin)->get(route('admin.technical-trainings.dashboard'))
            ->assertOk()
            ->assertSee('Statewide session');

        $this->actingAs($spoc)->get(route('spoc.technical-trainings.dashboard'))
            ->assertOk()
            ->assertSee('Statewide session');
    }

    public function test_only_original_submitter_can_edit_entry(): void
    {
        $district = $this->createDistrict();
        $submitter = User::factory()->create([
            'role' => 'district_staff',
            'district_id' => $district->id,
            'is_active' => true,
        ]);
        $otherStaff = User::factory()->create([
            'role' => 'district_staff',
            'district_id' => $district->id,
            'is_active' => true,
        ]);
        $incubateeId = $this->seedOnboardedIncubatee($district);
        $entry = $this->createTechnicalTraining($district, $submitter, 'Original session', [$incubateeId]);
        $entry->update(['status' => ServiceCase::STATUS_SENT_BACK]);

        $this->actingAs($otherStaff)->get(route('staff.technical-trainings.edit', $entry))
            ->assertForbidden();

        $this->actingAs($submitter)->put(route('staff.technical-trainings.update', $entry), [
            'session_date' => '2026-05-20',
            'session_name' => 'Updated session',
            'session_brief' => 'Updated brief',
            'selected_incubatees' => [$incubateeId],
        ])->assertRedirect(route('staff.technical-trainings.dashboard'));

        $this->assertDatabaseHas('technical_trainings', [
            'id' => $entry->id,
            'session_name' => 'Updated session',
            'session_brief' => 'Updated brief',
        ]);
    }

    public function test_district_staff_can_upload_multiple_attendance_media_files(): void
    {
        Storage::fake();
        $district = $this->createDistrict();
        $staff = User::factory()->create([
            'role' => 'district_staff',
            'district_id' => $district->id,
            'is_active' => true,
        ]);
        $incubateeId = $this->seedOnboardedIncubatee($district);

        $response = $this->actingAs($staff)->post(route('staff.technical-trainings.store'), [
            'session_date' => '2026-05-18',
            'session_name' => 'Multi-file session',
            'selected_incubatees' => [$incubateeId],
            'attendance_media' => [
                UploadedFile::fake()->image('photo-one.jpg'),
                UploadedFile::fake()->create('notes.docx', 120, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
                UploadedFile::fake()->create('clip.mp4', 240, 'video/mp4'),
            ],
        ]);

        $response->assertRedirect(route('staff.technical-trainings.dashboard'));

        $entry = TechnicalTraining::query()->firstOrFail();
        $this->assertCount(3, (array) $entry->attendance_media_json);
        Storage::assertExists((string) $entry->attendance_media_json[0]['path']);
        Storage::assertExists((string) $entry->attendance_media_json[1]['path']);
        Storage::assertExists((string) $entry->attendance_media_json[2]['path']);
    }

    public function test_update_appends_new_attendance_media_without_removing_existing(): void
    {
        Storage::fake();
        $district = $this->createDistrict();
        $staff = User::factory()->create([
            'role' => 'district_staff',
            'district_id' => $district->id,
            'is_active' => true,
        ]);
        $incubateeId = $this->seedOnboardedIncubatee($district);
        $existingPath = 'technical-training-attendance-media/existing.jpg';
        Storage::put($existingPath, 'existing image');
        $entry = $this->createTechnicalTraining($district, $staff, 'Existing session', [$incubateeId], null, [[
            'path' => $existingPath,
            'original_name' => 'existing.jpg',
            'mime' => 'image/jpeg',
            'size_bytes' => 14,
            'type' => 'image',
        ]]);
        $entry->update(['status' => ServiceCase::STATUS_SENT_BACK]);

        $response = $this->actingAs($staff)->put(route('staff.technical-trainings.update', $entry), [
            'session_date' => '2026-05-19',
            'session_name' => 'Existing session',
            'selected_incubatees' => [$incubateeId],
            'attendance_media' => [
                UploadedFile::fake()->create('extra.pdf', 100, 'application/pdf'),
            ],
        ]);

        $response->assertRedirect(route('staff.technical-trainings.dashboard'));

        $entry->refresh();
        $this->assertCount(2, (array) $entry->attendance_media_json);
        $this->assertSame($existingPath, $entry->attendance_media_json[0]['path']);
        Storage::assertExists((string) $entry->attendance_media_json[1]['path']);
    }

    public function test_show_entry_displays_onboarding_status_for_each_selected_applicant(): void
    {
        $district = $this->createDistrict();
        $staff = User::factory()->create([
            'role' => 'district_staff',
            'district_id' => $district->id,
            'is_active' => true,
        ]);

        $onboardedId = $this->seedOnboardedIncubatee($district);
        $notOnboardedId = $this->seedPhase3Applicant($district, 'Pending Applicant');

        $entry = TechnicalTraining::query()->create([
            'submitted_by_user_id' => $staff->id,
            'submitted_by_name' => (string) $staff->name,
            'event_date' => '2026-05-18',
            'district_id' => $district->id,
            'district_name' => $district->name,
            'session_name' => 'View session',
            'attendance_media_json' => [],
            'selected_incubatee_ids' => [$onboardedId, $notOnboardedId],
            'selected_incubatees_snapshot' => [
                [
                    'incubatee_id' => $onboardedId,
                    'source' => 'phase3',
                    'name' => 'Onboarded Applicant',
                    'application_no' => 'APP-onboarded',
                ],
                [
                    'incubatee_id' => $notOnboardedId,
                    'source' => 'phase3',
                    'name' => 'Pending Applicant',
                    'application_no' => 'APP-pending-applicant',
                ],
            ],
        ]);

        $this->actingAs($staff)
            ->get(route('staff.technical-trainings.show', $entry))
            ->assertOk()
            ->assertSee('Onboarding Status', false)
            ->assertSee('Onboarded', false)
            ->assertSee('Not onboarded', false)
            ->assertSee('Onboarded Applicant', false)
            ->assertSee('Pending Applicant', false);
    }

    public function test_authorized_user_can_download_uploaded_attachment(): void
    {
        $district = $this->createDistrict();
        $staff = User::factory()->create([
            'role' => 'district_staff',
            'district_id' => $district->id,
            'is_active' => true,
        ]);
        $incubateeId = $this->seedOnboardedIncubatee($district);
        $path = 'technical-training-attendance-media/test-upload.docx';
        Storage::put($path, 'sample document');

        $entry = $this->createTechnicalTraining($district, $staff, 'Attachment session', [$incubateeId], null, [[
            'path' => $path,
            'original_name' => 'test-upload.docx',
            'mime' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'size_bytes' => 15,
            'type' => 'document',
        ]]);

        $this->actingAs($staff)->get(route('staff.technical-trainings.attachment', $entry))
            ->assertOk()
            ->assertHeader('content-disposition');
    }

    public function test_export_includes_session_name_and_brief_headers(): void
    {
        $district = $this->createDistrict();
        $staff = User::factory()->create([
            'role' => 'district_staff',
            'district_id' => $district->id,
            'is_active' => true,
        ]);
        $incubateeId = $this->seedOnboardedIncubatee($district);
        $this->createTechnicalTraining($district, $staff, 'Export session', [$incubateeId], 'Export brief');

        $response = $this->actingAs($staff)->get(route('staff.technical-trainings.export'));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('Sr. No.', $response->streamedContent());
        $this->assertStringContainsString('Session Name', $response->streamedContent());
        $this->assertStringContainsString('Session Brief', $response->streamedContent());
        $this->assertStringContainsString('Male', $response->streamedContent());
        $this->assertStringContainsString('Female', $response->streamedContent());
        $this->assertStringContainsString('Total', $response->streamedContent());
        $this->assertStringContainsString('Export session', $response->streamedContent());
        $this->assertStringContainsString('Export brief', $response->streamedContent());
    }

    /**
     * @param  list<int>  $incubateeIds
     */
    private function createTechnicalTraining(
        District $district,
        User $staff,
        string $sessionName,
        array $incubateeIds = [1],
        ?string $sessionBrief = null,
        array $attendanceMedia = [],
    ): TechnicalTraining {
        return TechnicalTraining::query()->create([
            'submitted_by_user_id' => $staff->id,
            'submitted_by_name' => (string) $staff->name,
            'event_date' => '2026-05-18',
            'district_id' => $district->id,
            'district_name' => $district->name,
            'session_name' => $sessionName,
            'session_brief' => $sessionBrief,
            'attendance_media_json' => $attendanceMedia,
            'selected_incubatee_ids' => $incubateeIds,
            'selected_incubatees_snapshot' => [[
                'incubatee_id' => $incubateeIds[0],
                'source' => 'phase3',
                'name' => 'Test Applicant',
                'application_no' => 'APP-1',
                'phone' => '9999999999',
                'gender' => 'M',
                'village' => 'Village',
                'block_name' => 'Block',
                'onboarding_batch_id' => 1,
                'onboarding_batch_name' => 'Batch 1',
            ]],
            'status' => ServiceCase::STATUS_APPROVED,
            'submitted_at' => now(),
            'approved_at' => now(),
            'approved_by' => $staff->id,
        ]);
    }

    private function seedOnboardedIncubatee(District $district): int
    {
        $cfaId = $this->seedPhase3Applicant($district, 'Test Applicant');

        $batchId = (int) DB::table('onboarding_batches')->insertGetId([
            'hub_id' => $district->hub_id,
            'district_id' => $district->id,
            'name' => 'Batch 1',
            'target_size' => 1,
            'status' => 'locked',
            'locked_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('onboarding_batch_cfa')->insert([
            'onboarding_batch_id' => $batchId,
            'cfa_submission_id' => $cfaId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $cfaId;
    }

    private function seedPhase3Applicant(District $district, string $name): int
    {
        $fiscalYearId = (int) (FiscalYear::phase3Default()?->id ?? 0);

        return (int) DB::table('cfa_submissions')->insertGetId([
            'district_id' => $district->id,
            'fiscal_year_id' => $fiscalYearId > 0 ? $fiscalYearId : null,
            'applicant_name' => $name,
            'application_no' => 'APP-'.str_replace(' ', '-', strtolower($name)),
            'phone' => '98'.str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT),
            'payload' => json_encode(['gender' => 'M', 'village' => 'Village', 'block' => 'Block']),
            'created_at' => '2026-05-01',
            'updated_at' => now(),
        ]);
    }

    private function createDistrict(string $slug = 'test-district', string $name = 'Test District'): District
    {
        $hub = Hub::query()->firstOrCreate(
            ['slug' => 'test-hub'],
            ['name' => 'Test Hub', 'sort_order' => 1]
        );

        return District::query()->create([
            'hub_id' => $hub->id,
            'slug' => $slug,
            'name' => $name,
            'sort_order' => 1,
        ]);
    }
}
