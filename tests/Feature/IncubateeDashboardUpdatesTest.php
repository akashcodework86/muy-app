<?php

namespace Tests\Feature;

use App\Models\CfaSubmission;
use App\Models\Designation;
use App\Models\District;
use App\Models\Hub;
use App\Models\IncubateeServiceRequest;
use App\Models\Service;
use App\Models\ServiceCase;
use App\Models\ServiceCaseAttachment;
use App\Models\ServiceCategory;
use App\Models\User;
use App\Support\IncubateeLocale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class IncubateeDashboardUpdatesTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_defaults_to_hindi_and_hides_services_received(): void
    {
        [$incubatee] = $this->seedIncubatee();

        $this->actingAs($incubatee)
            ->get(route('incubatee.dashboard'))
            ->assertOk()
            ->assertSee('स्वागत है', false)
            ->assertSee('बिज़नेस मॉडल कैनवस', false)
            ->assertDontSee('Services received')
            ->assertDontSee('सेवाएँ मिलीं')
            ->assertDontSee('Total services tracked');
    }

    public function test_language_toggle_switches_to_english(): void
    {
        [$incubatee] = $this->seedIncubatee();

        $this->actingAs($incubatee)
            ->from(route('incubatee.dashboard'))
            ->post(route('incubatee.language'), ['locale' => 'en'])
            ->assertRedirect(route('incubatee.dashboard'));

        $this->actingAs($incubatee)
            ->withSession([IncubateeLocale::COOKIE => 'en'])
            ->get(route('incubatee.dashboard'))
            ->assertOk()
            ->assertSee('Welcome back', false)
            ->assertSee('Business Model Canvas', false)
            ->assertSee('admin-app-body--state-theme-revamp', false)
            ->assertSee('admin-app-body--incubatee', false);
    }

    public function test_dashboard_shows_bmc_document_view_and_download(): void
    {
        Storage::fake('local');
        [$incubatee, $cfa] = $this->seedIncubatee();
        $service = $this->makeService('bmc_canvas', 'Business Model Canvas');
        $case = ServiceCase::query()->create([
            'cfa_submission_id' => $cfa->id,
            'service_id' => $service->id,
            'status' => ServiceCase::STATUS_APPROVED,
            'reference_number' => 'SC-BMC-9',
            'delivered_on' => '2026-09-10',
        ]);
        Storage::disk('local')->put('bmc/canvas.pdf', '%PDF-1.4 test');
        $attachment = ServiceCaseAttachment::query()->create([
            'service_case_id' => $case->id,
            'disk' => 'local',
            'path' => 'bmc/canvas.pdf',
            'original_name' => 'bmc-canvas.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 12,
        ]);

        $this->actingAs($incubatee)
            ->get(route('incubatee.dashboard'))
            ->assertOk()
            ->assertSee('bmc-canvas.pdf', false)
            ->assertSee(route('incubatee.bmc-documents.view', $attachment), false)
            ->assertSee(route('incubatee.bmc-documents.download', $attachment), false)
            ->assertDontSee('Services delivered');

        $this->actingAs($incubatee)
            ->get(route('incubatee.bmc-documents.view', $attachment))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->actingAs($incubatee)
            ->get(route('incubatee.bmc-documents.download', $attachment))
            ->assertOk();
    }

    public function test_incubatee_can_submit_service_request_and_im_can_view_dashboard(): void
    {
        [$incubatee, $cfa, $district] = $this->seedIncubatee();
        $im = $this->makeIm($district);
        $service = $this->makeService('fssai', 'FSSAI');

        $this->actingAs($incubatee)
            ->post(route('incubatee.service-requests.store'), [
                'service_id' => $service->id,
                'comment' => 'FSSAI chahiye',
            ])
            ->assertRedirect();

        $row = IncubateeServiceRequest::query()->first();
        $this->assertNotNull($row);
        $this->assertSame(IncubateeServiceRequest::STATUS_PENDING, $row->status);
        $this->assertSame((int) $service->id, (int) $row->service_id);

        $this->actingAs($im)
            ->get(route('staff.service-requests.dashboard'))
            ->assertOk()
            ->assertSee($cfa->applicant_name)
            ->assertSee('FSSAI');

        $this->actingAs($im)
            ->post(route('staff.service-requests.status', $row), [
                'status' => IncubateeServiceRequest::STATUS_DONE,
                'staff_note' => 'Done',
            ])
            ->assertRedirect();

        $this->assertSame(IncubateeServiceRequest::STATUS_DONE, $row->fresh()->status);
    }

    public function test_state_admin_sees_service_request_dashboard(): void
    {
        [$incubatee, $cfa] = $this->seedIncubatee();
        $service = $this->makeService('gst', 'GST');
        IncubateeServiceRequest::query()->create([
            'cfa_submission_id' => $cfa->id,
            'requested_by_user_id' => $incubatee->id,
            'service_id' => $service->id,
            'comment' => 'Need GST',
            'status' => IncubateeServiceRequest::STATUS_PENDING,
        ]);
        $admin = User::factory()->create(['role' => 'state_admin', 'is_active' => true]);

        $this->actingAs($admin)
            ->get(route('admin.service-requests.dashboard'))
            ->assertOk()
            ->assertSee($cfa->applicant_name)
            ->assertSee('GST')
            ->assertSee('Incubatee dashboard', false)
            ->assertSee('Mentorship requests', false)
            ->assertSee('Service requests', false);
    }

    public function test_udyamita_kosh_renames_and_removes_downloads(): void
    {
        [$incubatee] = $this->seedIncubatee();

        $this->actingAs($incubatee)
            ->withSession([IncubateeLocale::COOKIE => 'en'])
            ->get(route('incubatee.udmita-kosh'))
            ->assertOk()
            ->assertSee('Udyamita Kosh')
            ->assertSee('Udyami Didi')
            ->assertSee('images/UdyamiDidi.png', false)
            ->assertSee('Starting and running a successful business')
            ->assertSee('https://www.youtube.com/playlist?list=PLGd-uiTAXX5o', false)
            ->assertSee('https://www.youtube.com/playlist?list=PLK7R7Wp_a3aQ', false)
            ->assertDontSee('Lakhpati Didi')
            ->assertDontSee('Downloads & Templates')
            ->assertDontSee('youtube.com/results?search_query', false);
    }

    public function test_dashboard_dates_include_ist_suffix(): void
    {
        [$incubatee, $cfa] = $this->seedIncubatee();
        $service = $this->makeService('gst', 'GST');
        IncubateeServiceRequest::query()->create([
            'cfa_submission_id' => $cfa->id,
            'requested_by_user_id' => $incubatee->id,
            'service_id' => $service->id,
            'comment' => 'Need GST',
            'status' => IncubateeServiceRequest::STATUS_PENDING,
        ]);

        $this->actingAs($incubatee)
            ->get(route('incubatee.dashboard'))
            ->assertOk()
            ->assertSee('IST');
    }

    public function test_profile_snapshot_shows_cfa_email_or_dash(): void
    {
        [$incubatee, $cfa] = $this->seedIncubatee();
        $incubatee->email = 'incubatee-1@ukrbi.in';
        $incubatee->save();

        $html = $this->actingAs($incubatee)
            ->withSession([IncubateeLocale::COOKIE => 'en'])
            ->get(route('incubatee.dashboard'))
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression('/<dt>Email<\/dt>\s*<dd>—<\/dd>/', $html);

        $cfa->payload = ['email' => 'priya.cfa@example.com'];
        $cfa->save();

        $html = $this->actingAs($incubatee)
            ->withSession([IncubateeLocale::COOKIE => 'en'])
            ->get(route('incubatee.dashboard'))
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression('/<dt>Email<\/dt>\s*<dd>priya\.cfa@example\.com<\/dd>/', $html);
        $this->assertDoesNotMatchRegularExpression('/<dt>Email<\/dt>\s*<dd>incubatee-1@ukrbi\.in<\/dd>/', $html);
    }

    /**
     * @return array{0: User, 1: CfaSubmission, 2: District}
     */
    private function seedIncubatee(): array
    {
        $hub = Hub::query()->create(['slug' => 'inc-hub', 'name' => 'Hub', 'sort_order' => 1]);
        $district = District::query()->create([
            'hub_id' => $hub->id,
            'slug' => 'inc-district',
            'name' => 'Inc District',
            'sort_order' => 1,
        ]);
        $cfa = CfaSubmission::query()->create([
            'district_id' => $district->id,
            'applicant_name' => 'Priya Sharma',
            'phone' => '9444444444',
            'application_no' => 'APP-INC-1',
            'payload' => [],
        ]);
        $incubatee = User::factory()->create([
            'role' => 'incubatee',
            'name' => 'Priya Sharma',
            'phone' => '9444444444',
            'password' => '9444444444',
            'cfa_submission_id' => $cfa->id,
            'district_id' => $district->id,
            'is_active' => true,
        ]);

        return [$incubatee, $cfa, $district];
    }

    private function makeIm(District $district): User
    {
        $designation = Designation::query()->firstOrCreate(
            ['name' => 'Incubation Manager'],
            ['sort_order' => 1]
        );

        return User::factory()->create([
            'role' => 'district_staff',
            'district_id' => $district->id,
            'hub_id' => $district->hub_id,
            'designation_id' => $designation->id,
            'is_active' => true,
        ]);
    }

    private function makeService(string $code, string $name): Service
    {
        $category = ServiceCategory::query()->firstOrCreate(
            ['slug' => 'legal_support_services'],
            ['name' => 'Services', 'sort_order' => 1]
        );

        return Service::query()->create([
            'service_category_id' => $category->id,
            'code' => $code,
            'name' => $name,
            'sort_order' => 1,
            'is_active' => true,
        ]);
    }
}
