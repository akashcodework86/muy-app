<?php

namespace Tests\Feature;

use App\Models\CfaSubmission;
use App\Models\District;
use App\Models\Hub;
use App\Models\Service;
use App\Models\ServiceCase;
use App\Models\ServiceCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffServiceSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_runs_before_pagination_and_reports_matching_total(): void
    {
        $hub = Hub::query()->create(['slug' => 'search-hub', 'name' => 'Search Hub', 'sort_order' => 1]);
        $district = District::query()->create([
            'hub_id' => $hub->id,
            'slug' => 'search-district',
            'name' => 'Search District',
            'sort_order' => 1,
        ]);
        $staff = User::factory()->create([
            'role' => 'district_staff',
            'district_id' => $district->id,
            'hub_id' => $hub->id,
            'is_active' => true,
        ]);
        $category = ServiceCategory::query()->create([
            'slug' => 'search-services',
            'name' => 'Search Services',
            'sort_order' => 1,
        ]);
        $service = Service::query()->create([
            'service_category_id' => $category->id,
            'code' => 'search-service',
            'name' => 'Search Service',
            'sort_order' => 1,
            'is_active' => true,
            'requires_approval' => false,
        ]);

        $target = CfaSubmission::query()->create([
            'district_id' => $district->id,
            'application_no' => 'TARGET-001',
            'applicant_name' => 'Jai Baba Enterprise',
            'phone' => '9000000001',
            'payload' => [],
        ]);
        $targetCase = ServiceCase::query()->create([
            'cfa_submission_id' => $target->id,
            'service_id' => $service->id,
            'status' => ServiceCase::STATUS_APPROVED,
            'created_by' => $staff->id,
            'submitted_by' => $staff->id,
        ]);
        $targetCase->forceFill([
            'created_at' => now()->subMonths(3),
            'updated_at' => now()->subMonths(3),
        ])->saveQuietly();

        for ($i = 1; $i <= 21; $i++) {
            $submission = CfaSubmission::query()->create([
                'district_id' => $district->id,
                'application_no' => 'RECENT-'.$i,
                'applicant_name' => 'Recent Applicant '.$i,
                'phone' => '91'.str_pad((string) $i, 8, '0', STR_PAD_LEFT),
                'payload' => [],
            ]);
            ServiceCase::query()->create([
                'cfa_submission_id' => $submission->id,
                'service_id' => $service->id,
                'status' => ServiceCase::STATUS_APPROVED,
                'created_by' => $staff->id,
                'submitted_by' => $staff->id,
                'created_at' => now()->subMinutes($i),
                'updated_at' => now()->subMinutes($i),
            ]);
        }

        $this->actingAs($staff)
            ->get(route('staff.services.index'))
            ->assertOk()
            ->assertDontSee('Jai Baba Enterprise');

        $this->actingAs($staff)
            ->get(route('staff.services.index', ['q' => 'Jai Baba']))
            ->assertOk()
            ->assertSee('Jai Baba Enterprise')
            ->assertSee('1 matching service record')
            ->assertDontSee('Recent Applicant 1');
    }
}
