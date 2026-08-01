<?php

namespace Tests\Feature;

use App\Models\District;
use App\Models\DistrictServiceSpoc;
use App\Models\Hub;
use App\Models\Service;
use App\Models\ServiceCase;
use App\Models\ServiceCaseAttachment;
use App\Models\ServiceCaseEvent;
use App\Models\ServiceCategory;
use App\Models\User;
use App\Services\AppSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SpocApprovalAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_module_off_blocks_new_staff_cases_but_keeps_existing_cases_and_spoc_approval_available(): void
    {
        app(AppSettingsService::class)->setMany(['service_module.enabled' => false]);

        [, $staff, $spoc, , $case] = $this->seedPendingCaseWithAttachment();

        $this->actingAs($staff)
            ->get(route('staff.services.index'))
            ->assertOk()
            ->assertSee('New submissions are paused')
            ->assertSee('Audit Test Applicant');

        $this->actingAs($staff)
            ->get(route('staff.services.create'))
            ->assertForbidden();

        $this->actingAs($staff)
            ->post(route('staff.services.store'), [])
            ->assertForbidden();

        $this->actingAs($staff)
            ->get(route('staff.services.show', $case))
            ->assertOk();

        $case->update(['status' => ServiceCase::STATUS_SENT_BACK]);
        $this->actingAs($staff)
            ->get(route('staff.services.edit', $case))
            ->assertOk();
        $case->update(['status' => ServiceCase::STATUS_PENDING_APPROVAL]);

        $this->actingAs($spoc)
            ->get(route('spoc.service-cases.index'))
            ->assertOk()
            ->assertSee('Audit Test Applicant');

        $this->actingAs($spoc)
            ->post(route('spoc.service-cases.approve', $case))
            ->assertRedirect();

        $this->assertSame(ServiceCase::STATUS_APPROVED, $case->fresh()->status);
    }

    public function test_approve_without_document_view_is_logged_in_event_meta(): void
    {
        app(AppSettingsService::class)->setMany(['service_module.enabled' => true]);

        [$district, $staff, $spoc, $service, $case] = $this->seedPendingCaseWithAttachment();

        $this->actingAs($spoc)
            ->post(route('spoc.service-cases.approve', $case), [
                'approval_channel' => 'queue_quick_review',
                'client_review_seconds' => 5,
            ])
            ->assertRedirect();

        $event = ServiceCaseEvent::query()
            ->where('service_case_id', $case->id)
            ->where('action', 'spoc_approved')
            ->first();

        $this->assertNotNull($event);
        $meta = is_array($event->meta) ? $event->meta : [];
        $this->assertTrue($meta['had_attachment'] ?? false);
        $this->assertFalse($meta['document_viewed'] ?? true);
        $this->assertTrue($meta['approved_without_document_view'] ?? false);
        $this->assertSame('queue_quick_review', $meta['approval_channel'] ?? null);
        $this->assertGreaterThanOrEqual(5, (int) ($meta['case_page_seconds'] ?? 0));
    }

    public function test_downloading_attachment_marks_document_viewed_before_approve(): void
    {
        app(AppSettingsService::class)->setMany(['service_module.enabled' => true]);
        Storage::fake('local');

        [$district, $staff, $spoc, $service, $case, $attachment] = $this->seedPendingCaseWithAttachment(returnAttachment: true);

        $this->actingAs($spoc)
            ->get(route('spoc.service-cases.attachments.download', [$case, $attachment]))
            ->assertOk();

        $this->actingAs($spoc)
            ->post(route('spoc.service-cases.approve', $case), [
                'approval_channel' => 'full_page',
            ])
            ->assertRedirect();

        $event = ServiceCaseEvent::query()
            ->where('service_case_id', $case->id)
            ->where('action', 'spoc_approved')
            ->first();

        $meta = is_array($event?->meta) ? $event->meta : [];
        $this->assertTrue($meta['document_viewed'] ?? false);
        $this->assertFalse($meta['approved_without_document_view'] ?? true);
        $this->assertSame('download', $meta['document_view_source'] ?? null);
    }

    public function test_review_telemetry_endpoint_records_document_view(): void
    {
        app(AppSettingsService::class)->setMany(['service_module.enabled' => true]);

        [, , $spoc, , $case] = $this->seedPendingCaseWithAttachment();

        $this->actingAs($spoc)
            ->postJson(route('spoc.service-cases.review-telemetry', $case), [
                'event' => 'document_viewed',
                'source' => 'queue_modal',
            ])
            ->assertOk();

        $cache = Cache::get('spoc_case_review:'.$spoc->id.':'.$case->id, []);
        $this->assertNotEmpty($cache['document_viewed_at'] ?? null);
    }

    public function test_state_admin_can_view_spoc_approval_audit_report(): void
    {
        app(AppSettingsService::class)->setMany(['service_module.enabled' => true]);

        [, , $spoc, , $case] = $this->seedPendingCaseWithAttachment();
        $admin = User::factory()->create(['role' => 'state_admin', 'is_active' => true]);

        $this->actingAs($spoc)
            ->post(route('spoc.service-cases.approve', $case))
            ->assertRedirect();

        $this->actingAs($admin)
            ->get(route('admin.spoc-approval-audit.index', ['flag' => 'without_doc']))
            ->assertOk()
            ->assertSee('SPOC approval audit', false)
            ->assertSee($spoc->name, false)
            ->assertSee('Not opened', false);
    }

    public function test_district_staff_cannot_view_spoc_approval_audit_report(): void
    {
        $staff = User::factory()->create(['role' => 'district_staff', 'is_active' => true]);

        $this->actingAs($staff)
            ->get(route('admin.spoc-approval-audit.index'))
            ->assertForbidden();
    }

    /**
     * @return array{0: District, 1: User, 2: User, 3: Service, 4: ServiceCase, 5?: ServiceCaseAttachment}
     */
    private function seedPendingCaseWithAttachment(bool $returnAttachment = false): array
    {
        $district = $this->createDistrict();
        $staff = User::factory()->create([
            'role' => 'district_staff',
            'district_id' => $district->id,
            'is_active' => true,
        ]);
        $spoc = User::factory()->create(['role' => 'state_staff', 'is_active' => true]);
        DistrictServiceSpoc::query()->create([
            'district_id' => $district->id,
            'state_staff_user_id' => $spoc->id,
            'assigned_by' => $staff->id,
            'assigned_at' => now(),
        ]);

        $category = ServiceCategory::query()->create([
            'slug' => 'audit_test_services',
            'name' => 'Audit Test Services',
            'sort_order' => 1,
        ]);
        $service = Service::query()->create([
            'service_category_id' => $category->id,
            'code' => 'audit_test_service',
            'name' => 'Audit Test Service',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $cfaId = (int) DB::table('cfa_submissions')->insertGetId([
            'district_id' => $district->id,
            'applicant_name' => 'Audit Test Applicant',
            'phone' => '9999999922',
            'payload' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $case = ServiceCase::query()->create([
            'cfa_submission_id' => $cfaId,
            'service_id' => $service->id,
            'status' => ServiceCase::STATUS_PENDING_APPROVAL,
            'reference_number' => 'SC-AUDIT-1',
            'submitted_at' => now(),
            'submitted_by' => $staff->id,
            'created_by' => $staff->id,
        ]);

        $path = 'service-cases/test-doc.jpg';
        if (! Storage::disk('local')->exists($path)) {
            Storage::disk('local')->put($path, 'fake-image');
        }

        $attachment = ServiceCaseAttachment::query()->create([
            'service_case_id' => $case->id,
            'disk' => 'local',
            'path' => $path,
            'original_name' => 'test-doc.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 100,
            'uploaded_by' => $staff->id,
        ]);

        $result = [$district, $staff, $spoc, $service, $case];
        if ($returnAttachment) {
            $result[] = $attachment;
        }

        return $result;
    }

    private function createDistrict(): District
    {
        $hub = Hub::query()->create([
            'name' => 'Audit Test Hub',
            'slug' => 'audit-test-hub',
            'is_active' => true,
        ]);

        return District::query()->create([
            'hub_id' => $hub->id,
            'name' => 'Audit Test District',
            'slug' => 'audit-test-district',
            'is_active' => true,
        ]);
    }
}
