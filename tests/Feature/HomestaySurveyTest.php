<?php

namespace Tests\Feature;

use App\Models\CfaSubmission;
use App\Models\District;
use App\Models\HomestaySurveyResponse;
use App\Models\Hub;
use App\Models\User;
use App\Services\AppSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use ZipArchive;

class HomestaySurveyTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_survey_page_loads(): void
    {
        $this->get(route('homestay-survey.show'))
            ->assertOk()
            ->assertSee('Homestay Progress Survey', false);
    }

    public function test_lookup_rejects_unknown_phone(): void
    {
        $this->postJson(route('homestay-survey.lookup'), ['phone' => '9876543210'])
            ->assertNotFound()
            ->assertJson(['status' => 'not_found']);
    }

    public function test_lookup_and_submit_for_phase3_homestay(): void
    {
        $district = $this->createDistrict('hs-survey', 'Nainital');
        CfaSubmission::query()->create([
            'application_no' => 'MUY-HS-001',
            'district_id' => $district->id,
            'source' => 'phase3',
            'applicant_name' => 'Test Host',
            'phone' => '9876543210',
            'payload' => [
                'business_category' => 'Homestay',
                'gender' => 'Female',
                'dob' => '1990-05-01',
                'caste' => 'OBC',
                'village' => 'Test Village',
                'block' => 'Test Block',
                'pincode' => '263001',
                'location_type' => 'Rural',
                'email' => 'host@example.com',
                'form_stage' => 'Early',
            ],
        ]);

        $this->postJson(route('homestay-survey.lookup'), ['phone' => '9876543210'])
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('prefill.respondent_name', 'Test Host')
            ->assertJsonPath('prefill.caste', 'OBC');

        $this->post(route('homestay-survey.store'), [
            'phone' => '9876543210',
            'consent' => '1',
            'answers' => [
                'respondent_name' => 'Test Host',
                'gender' => 'Female',
                'enterprise_name' => 'Hill View Homestay',
                'district' => 'Nainital',
                'room_count' => '2',
            ],
        ])->assertRedirect(route('homestay-survey.thanks'));

        $this->get(route('homestay-survey.thanks'))
            ->assertOk()
            ->assertSee('Thank you', false)
            ->assertSee('Fill new entry', false);

        // Refresh / direct revisit should open a new blank form
        $this->get(route('homestay-survey.thanks'))
            ->assertRedirect(route('homestay-survey.show'));

        $this->assertDatabaseHas('homestay_survey_responses', [
            'phone' => '9876543210',
            'applicant_name' => 'Test Host',
            'phase' => 'Phase 3',
        ]);

        $this->postJson(route('homestay-survey.lookup'), ['phone' => '9876543210'])
            ->assertStatus(409)
            ->assertJson(['status' => 'already_submitted']);

        $this->followingRedirects()
            ->get(route('homestay-survey.already-submitted', ['phone' => '9876543210']))
            ->assertOk()
            ->assertSee('Already submitted', false)
            ->assertSee('Fill new entry', false);

        $this->get(route('homestay-survey.already-submitted'))
            ->assertRedirect(route('homestay-survey.show'));
    }

    public function test_state_admin_can_toggle_prefill_lock_and_view_dashboard(): void
    {
        $admin = User::factory()->create(['role' => 'state_admin', 'is_active' => true]);

        HomestaySurveyResponse::query()->create([
            'phone' => '9123456789',
            'phase' => 'Phase 3',
            'source_id' => 1,
            'applicant_name' => 'Host Two',
            'district' => 'Dehradun',
            'prefill_snapshot' => [],
            'answers' => ['respondent_name' => 'Host Two', 'consent' => true],
            'submitted_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.homestay-survey.index'))
            ->assertOk()
            ->assertSee('Host Two', false)
            ->assertSee('Editable', false)
            ->assertSee('Analysis Excel', false);

        $this->actingAs($admin)
            ->post(route('admin.homestay-survey.prefill-lock'), ['prefill_locked' => 1])
            ->assertRedirect();

        $this->assertTrue(app(AppSettingsService::class)->isEnabled('homestay_survey.prefill_locked'));

        $response = HomestaySurveyResponse::query()->firstOrFail();
        $this->actingAs($admin)
            ->get(route('admin.homestay-survey.show', $response))
            ->assertOk()
            ->assertSee('Host Two', false);
    }

    public function test_state_admin_can_filter_dashboard_by_acceleration_support(): void
    {
        $admin = User::factory()->create(['role' => 'state_admin', 'is_active' => true]);

        HomestaySurveyResponse::query()->create([
            'phone' => '9111111111',
            'phase' => 'Phase 3',
            'applicant_name' => 'Willing Host',
            'district' => 'Nainital',
            'prefill_snapshot' => [],
            'answers' => ['respondent_name' => 'Willing Host', 'acceleration_support' => 'Yes', 'consent' => true],
            'submitted_at' => now(),
        ]);
        HomestaySurveyResponse::query()->create([
            'phone' => '9222222222',
            'phase' => 'Phase 2',
            'applicant_name' => 'Unwilling Host',
            'district' => 'Dehradun',
            'prefill_snapshot' => [],
            'answers' => ['respondent_name' => 'Unwilling Host', 'acceleration_support' => 'No', 'consent' => true],
            'submitted_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.homestay-survey.index', ['acceleration' => 'Yes']))
            ->assertOk()
            ->assertSee('Willing Host', false)
            ->assertDontSee('Unwilling Host', false)
            ->assertSee('Acceleration support', false);

        $this->actingAs($admin)
            ->get(route('admin.homestay-survey.index', ['phase' => 'Phase 2', 'district' => 'Dehradun']))
            ->assertOk()
            ->assertSee('Unwilling Host', false)
            ->assertDontSee('Willing Host', false);
    }

    public function test_state_admin_export_downloads_xlsx_and_respects_filters(): void
    {
        $admin = User::factory()->create(['role' => 'state_admin', 'is_active' => true]);

        HomestaySurveyResponse::query()->create([
            'phone' => '9333333333',
            'phase' => 'Phase 3',
            'applicant_name' => 'Export Yes Host',
            'district' => 'Almora',
            'prefill_snapshot' => [],
            'answers' => ['respondent_name' => 'Export Yes Host', 'acceleration_support' => 'Yes', 'consent' => true],
            'submitted_at' => now(),
        ]);
        HomestaySurveyResponse::query()->create([
            'phone' => '9444444444',
            'phase' => 'Phase 1',
            'applicant_name' => 'Export No Host',
            'district' => 'Tehri',
            'prefill_snapshot' => [],
            'answers' => ['respondent_name' => 'Export No Host', 'acceleration_support' => 'No', 'consent' => true],
            'submitted_at' => now(),
        ]);

        $all = $this->actingAs($admin)->get(route('admin.homestay-survey.export'));
        $all->assertOk();
        $all->assertDownload();
        $this->assertStringContainsString('.xlsx', (string) $all->headers->get('content-disposition'));

        $filtered = $this->actingAs($admin)->get(route('admin.homestay-survey.export', ['acceleration' => 'Yes']));
        $filtered->assertOk();
        $filtered->assertDownload();

        $path = $filtered->baseResponse->getFile()->getPathname();
        $this->assertFileExists($path);

        if (! class_exists(ZipArchive::class)) {
            return;
        }

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path) === true);
        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        $this->assertIsString($sheet);
        $this->assertStringContainsString('Export Yes Host', $sheet);
        $this->assertStringNotContainsString('Export No Host', $sheet);
    }

    public function test_state_admin_analysis_excel_has_expected_sheets_and_respects_acceleration_filter(): void
    {
        if (! class_exists(ZipArchive::class)) {
            $this->markTestSkipped('ext-zip required');
        }

        $admin = User::factory()->create(['role' => 'state_admin', 'is_active' => true]);

        HomestaySurveyResponse::query()->create([
            'phone' => '9555555555',
            'phase' => 'Phase 3',
            'application_no' => 'MUY-ACC-YES',
            'applicant_name' => 'Analysis Yes Host',
            'district' => 'Nainital',
            'prefill_snapshot' => [],
            'answers' => [
                'respondent_name' => 'Analysis Yes Host',
                'enterprise_name' => 'Lake View Stay',
                'acceleration_support' => 'Yes',
                'gender' => 'Female',
                'progress_rating' => '5 Excellent',
                'recommend_muy' => '5',
                'consent' => true,
            ],
            'submitted_at' => now(),
        ]);
        HomestaySurveyResponse::query()->create([
            'phone' => '9666666666',
            'phase' => 'Phase 1',
            'application_no' => 'MUY-ACC-NO',
            'applicant_name' => 'Analysis No Host',
            'district' => 'Haridwar',
            'prefill_snapshot' => [],
            'answers' => [
                'respondent_name' => 'Analysis No Host',
                'acceleration_support' => 'No',
                'consent' => true,
            ],
            'submitted_at' => now(),
        ]);

        $svc = app(\App\Services\Exports\HomestaySurveyAnalysisWorkbookService::class);
        $allRows = HomestaySurveyResponse::query()->orderBy('id')->get();
        $sheets = $svc->sheets($allRows, [
            'q' => '',
            'phase' => '',
            'district' => '',
            'acceleration' => '',
        ]);
        $this->assertSame(\App\Services\Exports\HomestaySurveyAnalysisWorkbookService::SHEET_TITLES, array_keys($sheets));

        $accelJoined = json_encode($sheets['Acceleration list'], JSON_THROW_ON_ERROR);
        $this->assertStringContainsString('Analysis Yes Host', $accelJoined);
        $this->assertStringNotContainsString('Analysis No Host', $accelJoined);

        $filtered = $this->actingAs($admin)->get(route('admin.homestay-survey.analysis-export', ['acceleration' => 'Yes']));
        $filtered->assertOk();
        $filtered->assertDownload();

        $path = $filtered->baseResponse->getFile()->getPathname();
        $this->assertFileExists($path);

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path) === true);
        $workbook = $zip->getFromName('xl/workbook.xml');
        $this->assertIsString($workbook);
        foreach (\App\Services\Exports\HomestaySurveyAnalysisWorkbookService::SHEET_TITLES as $title) {
            $this->assertStringContainsString(
                htmlspecialchars($title, ENT_XML1 | ENT_QUOTES, 'UTF-8'),
                $workbook
            );
        }

        $accelXml = $zip->getFromName('xl/worksheets/sheet10.xml');
        $rawXml = $zip->getFromName('xl/worksheets/sheet11.xml');
        $zip->close();
        $this->assertIsString($accelXml);
        $this->assertIsString($rawXml);
        $this->assertStringContainsString('Analysis Yes Host', $accelXml);
        $this->assertStringNotContainsString('Analysis No Host', $accelXml);
        $this->assertStringContainsString('Analysis Yes Host', $rawXml);
        $this->assertStringNotContainsString('Analysis No Host', $rawXml);
        $this->assertStringContainsString('Revenue during', $rawXml);
        $this->assertStringContainsString('Other income', $rawXml);
    }

    private function createDistrict(string $slug, string $name): District
    {
        $hub = Hub::query()->create([
            'slug' => 'hub-'.$slug,
            'name' => 'Hub '.$name,
            'sort_order' => 1,
        ]);

        return District::query()->create([
            'hub_id' => $hub->id,
            'slug' => $slug,
            'name' => $name,
            'sort_order' => 1,
        ]);
    }
}
