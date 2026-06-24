<?php

namespace Tests\Feature;

use App\Models\CommunityOrganizationOutreachVisit;
use App\Models\Designation;
use App\Models\District;
use App\Models\FiscalYear;
use App\Models\Hub;
use App\Models\User;
use App\Services\Deliverables\ProgramDeliverablesFilter;
use App\Services\Deliverables\ProgramDeliverablesScope;
use App\Services\ProgramDeliverablesReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommunityOrganizationOutreachTest extends TestCase
{
    use RefreshDatabase;

    public function test_hub_admin_can_submit_outreach_visit_with_other_org_type_and_uploads(): void
    {
        [$hub, $district, $admin] = $this->seedHubFixtures();

        $response = $this->actingAs($admin)->post(route('hub.community-org-outreach.store'), [
            'visit_date' => '2026-06-10',
            'district_id' => $district->id,
            'organization_name' => 'Local Cooperative Union',
            'organization_type' => 'other',
            'organization_type_other' => 'Farmers producer company',
            'person_met_name' => 'Priya Sharma',
            'person_met_designation' => 'Coordinator',
            'poc_name' => 'Priya Sharma',
            'poc_phone' => '9876543210',
            'purpose' => 'awareness',
            'meeting_mode' => 'physical',
            'remarks' => 'Discussed CFA outreach plan',
            'documents' => [
                \Illuminate\Http\UploadedFile::fake()->create('minutes.pdf', 120, 'application/pdf'),
            ],
            'photos' => [
                \Illuminate\Http\UploadedFile::fake()->image('visit.jpg'),
            ],
        ]);

        $response->assertRedirect(route('hub.community-org-outreach.dashboard'));

        $visit = CommunityOrganizationOutreachVisit::query()->first();
        $this->assertNotNull($visit);
        $this->assertSame('Farmers producer company', $visit->organization_type_other);
        $this->assertCount(1, (array) $visit->documents_json);
        $this->assertCount(1, (array) $visit->photos_json);
    }

    public function test_hub_admin_must_specify_other_org_type_when_other_selected(): void
    {
        [, $district, $admin] = $this->seedHubFixtures();

        $this->actingAs($admin)
            ->post(route('hub.community-org-outreach.store'), [
                'visit_date' => '2026-06-10',
                'district_id' => $district->id,
                'organization_name' => 'Mystery Org',
                'organization_type' => 'other',
                'person_met_name' => 'Someone',
                'poc_name' => 'Someone',
                'poc_phone' => '9876543210',
                'purpose' => 'awareness',
                'meeting_mode' => 'physical',
            ])
            ->assertSessionHasErrors('organization_type_other');
    }

    public function test_hub_admin_can_submit_outreach_visit(): void
    {
        [$hub, $district, $admin] = $this->seedHubFixtures();

        $response = $this->actingAs($admin)->post(route('hub.community-org-outreach.store'), [
            'visit_date' => '2026-06-10',
            'district_id' => $district->id,
            'organization_name' => 'Garhwal SHG Federation',
            'organization_type' => 'shg_federation',
            'person_met_name' => 'Priya Sharma',
            'person_met_designation' => 'Coordinator',
            'poc_name' => 'Priya Sharma',
            'poc_phone' => '9876543210',
            'purpose' => 'awareness',
            'meeting_mode' => 'physical',
            'remarks' => 'Discussed CFA outreach plan',
        ]);

        $response->assertRedirect(route('hub.community-org-outreach.dashboard'));

        $this->assertDatabaseHas('community_organization_outreach_visits', [
            'hub_id' => $hub->id,
            'district_id' => $district->id,
            'organization_name' => 'Garhwal SHG Federation',
            'poc_phone' => '9876543210',
            'submitted_by_user_id' => $admin->id,
        ]);
    }

    public function test_district_staff_without_allowed_designation_cannot_submit_outreach_visit(): void
    {
        [, $district] = $this->seedHubFixtures();
        $designation = $this->designation('District Staff', 99);

        $staff = User::factory()->create([
            'role' => 'district_staff',
            'designation_id' => $designation->id,
            'hub_id' => $district->hub_id,
            'district_id' => $district->id,
            'is_active' => true,
        ]);

        $this->actingAs($staff)
            ->get(route('staff.community-org-outreach.create'))
            ->assertForbidden();
    }

    /**
     * @dataProvider allowedDistrictStaffDesignationProvider
     */
    public function test_allowed_district_staff_can_submit_outreach_visit_for_own_district(string $designationName): void
    {
        [$hub, $district] = $this->seedHubFixtures();
        $designation = $this->designation($designationName, 1);
        $staff = User::factory()->create([
            'role' => 'district_staff',
            'designation_id' => $designation->id,
            'hub_id' => $hub->id,
            'district_id' => $district->id,
            'is_active' => true,
        ]);

        $this->actingAs($staff)
            ->post(route('staff.community-org-outreach.store'), [
                'visit_date' => '2026-06-10',
                'district_id' => $district->id,
                'organization_name' => 'Garhwal SHG Federation',
                'organization_type' => 'shg_federation',
                'person_met_name' => 'Priya Sharma',
                'person_met_designation' => 'Coordinator',
                'poc_name' => 'Priya Sharma',
                'poc_phone' => '9876543210',
                'purpose' => 'awareness',
                'meeting_mode' => 'physical',
            ])
            ->assertRedirect(route('staff.community-org-outreach.dashboard'));

        $this->assertDatabaseHas('community_organization_outreach_visits', [
            'hub_id' => $hub->id,
            'district_id' => $district->id,
            'organization_name' => 'Garhwal SHG Federation',
            'submitted_by_user_id' => $staff->id,
        ]);
    }

    public function test_muy_spoke_cannot_submit_outreach_visit_for_another_district(): void
    {
        [$hub, $districtA] = $this->seedHubFixtures('hub-a', 'District A');
        $districtB = District::query()->create([
            'hub_id' => $hub->id,
            'slug' => 'district-b',
            'name' => 'District B',
            'sort_order' => 2,
        ]);
        $designation = $this->designation('MUY Spoke', 1);
        $staff = User::factory()->create([
            'role' => 'district_staff',
            'designation_id' => $designation->id,
            'hub_id' => $hub->id,
            'district_id' => $districtA->id,
            'is_active' => true,
        ]);

        $this->actingAs($staff)
            ->post(route('staff.community-org-outreach.store'), [
                'visit_date' => '2026-06-10',
                'district_id' => $districtB->id,
                'organization_name' => 'Other District Org',
                'organization_type' => 'ngo',
                'person_met_name' => 'Someone',
                'poc_name' => 'Someone',
                'poc_phone' => '9876543210',
                'purpose' => 'awareness',
                'meeting_mode' => 'physical',
            ])
            ->assertSessionHasErrors('district_id');
    }

    public function test_district_staff_dashboard_is_scoped_to_own_district(): void
    {
        [$hub, $districtA] = $this->seedHubFixtures('hub-a', 'District A');
        $districtB = District::query()->create([
            'hub_id' => $hub->id,
            'slug' => 'district-b',
            'name' => 'District B',
            'sort_order' => 2,
        ]);
        $designation = $this->designation('Incubation Manager', 1);
        $staff = User::factory()->create([
            'role' => 'district_staff',
            'designation_id' => $designation->id,
            'hub_id' => $hub->id,
            'district_id' => $districtA->id,
            'is_active' => true,
        ]);
        $otherStaff = User::factory()->create([
            'role' => 'district_staff',
            'designation_id' => $designation->id,
            'hub_id' => $hub->id,
            'district_id' => $districtB->id,
            'is_active' => true,
        ]);

        CommunityOrganizationOutreachVisit::query()->create($this->visitPayload($hub, $districtA, $staff, 'Org A'));
        CommunityOrganizationOutreachVisit::query()->create($this->visitPayload($hub, $districtB, $otherStaff, 'Org B'));

        $this->actingAs($staff)
            ->get(route('staff.community-org-outreach.dashboard'))
            ->assertOk()
            ->assertSee('Org A')
            ->assertDontSee('Org B');
    }

    /**
     * @return list<array{0: string}>
     */
    public static function allowedDistrictStaffDesignationProvider(): array
    {
        return [
            ['MUY Spoke'],
            ['Incubation Manager'],
            ['Business Planning & Development Expert'],
        ];
    }

    public function test_dashboard_shows_doc_links_and_photo_thumbnails(): void
    {
        [$hub, $district, $admin] = $this->seedHubFixtures();

        CommunityOrganizationOutreachVisit::query()->create(array_merge(
            $this->visitPayload($hub, $district, $admin, 'With Media'),
            [
                'documents_json' => [[
                    'path' => 'community-org-outreach-documents/test.pdf',
                    'original_name' => 'minutes.pdf',
                    'mime' => 'application/pdf',
                    'type' => 'document',
                ]],
                'photos_json' => [[
                    'path' => 'community-org-outreach-photos/test.jpg',
                    'original_name' => 'visit.jpg',
                    'mime' => 'image/jpeg',
                    'type' => 'image',
                ]],
            ]
        ));

        CommunityOrganizationOutreachVisit::query()->create($this->visitPayload($hub, $district, $admin, 'Without Media'));

        \Illuminate\Support\Facades\Storage::fake();
        \Illuminate\Support\Facades\Storage::put('community-org-outreach-documents/test.pdf', 'pdf');
        \Illuminate\Support\Facades\Storage::put('community-org-outreach-photos/test.jpg', 'jpg');

        $this->actingAs($admin)
            ->get(route('hub.community-org-outreach.dashboard'))
            ->assertOk()
            ->assertSee('View doc')
            ->assertSee('No doc')
            ->assertSee('No images')
            ->assertSee('Visit photo 1', false);
    }

    public function test_hub_admin_dashboard_is_scoped_to_own_hub(): void
    {
        [$hubA, $districtA, $adminA] = $this->seedHubFixtures('hub-a', 'District A');
        [$hubB, $districtB] = $this->seedHubFixtures('hub-b', 'District B');

        CommunityOrganizationOutreachVisit::query()->create($this->visitPayload($hubA, $districtA, $adminA, 'Org A'));
        CommunityOrganizationOutreachVisit::query()->create($this->visitPayload($hubB, $districtB, $adminA, 'Org B'));

        $this->actingAs($adminA)
            ->get(route('hub.community-org-outreach.dashboard'))
            ->assertOk()
            ->assertSee('Org A')
            ->assertDontSee('Org B');
    }

    public function test_state_admin_dashboard_shows_all_hubs(): void
    {
        [$hubA, $districtA, $adminA] = $this->seedHubFixtures('hub-a', 'District A');
        [$hubB, $districtB] = $this->seedHubFixtures('hub-b', 'District B');

        CommunityOrganizationOutreachVisit::query()->create($this->visitPayload($hubA, $districtA, $adminA, 'Org A'));
        CommunityOrganizationOutreachVisit::query()->create($this->visitPayload($hubB, $districtB, $adminA, 'Org B'));

        $stateAdmin = User::factory()->create([
            'role' => 'state_admin',
            'is_active' => true,
        ]);

        $this->actingAs($stateAdmin)
            ->get(route('admin.community-org-outreach.dashboard'))
            ->assertOk()
            ->assertSee('Org A')
            ->assertSee('Org B')
            ->assertSee('Community organization outreach (MIS 1.5)', false);
    }

    public function test_outreach_visits_map_to_mis_row_1_5(): void
    {
        $fy = FiscalYear::query()->firstOrCreate(
            ['code' => '2026-27'],
            [
                'name' => 'FY 2026-27',
                'starts_on' => '2026-04-01',
                'ends_on' => '2027-03-31',
                'is_active' => true,
            ]
        );

        [$hub, $district, $admin] = $this->seedHubFixtures();

        CommunityOrganizationOutreachVisit::query()->create($this->visitPayload($hub, $district, $admin, 'Test NGO', '2026-06-15'));
        CommunityOrganizationOutreachVisit::query()->create($this->visitPayload($hub, $district, $admin, 'Test CBO', '2026-06-18'));

        $filter = new ProgramDeliverablesFilter($fy->id, $district->id, null, null, null, null);
        $scope = new ProgramDeliverablesScope('hub_admin', $hub->id, [$district->id], false);
        $report = app(ProgramDeliverablesReportService::class)->build($filter, $scope);

        $row = collect($report['rows'])->firstWhere('serial', '1.5');

        $this->assertNotNull($row);
        $this->assertSame(2, $row['achievement']);
        $this->assertNull($row['target']);
    }

    /**
     * @return array{0: Hub, 1: District, 2: User}
     */
    private function seedHubFixtures(string $hubSlug = 'kumaon', string $districtName = 'Almora'): array
    {
        $hub = Hub::query()->create([
            'slug' => $hubSlug,
            'name' => ucfirst(str_replace('-', ' ', $hubSlug)).' Region',
            'sort_order' => 1,
        ]);

        $district = District::query()->create([
            'hub_id' => $hub->id,
            'slug' => strtolower(str_replace(' ', '-', $districtName)),
            'name' => $districtName,
            'sort_order' => 1,
        ]);

        $admin = User::factory()->create([
            'role' => 'hub_admin',
            'hub_id' => $hub->id,
            'is_active' => true,
        ]);

        return [$hub, $district, $admin];
    }

    private function designation(string $name, int $sortOrder = 0): Designation
    {
        return Designation::query()->updateOrCreate(
            ['name' => $name],
            ['sort_order' => $sortOrder],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function visitPayload(Hub $hub, District $district, User $admin, string $orgName, string $visitDate = '2026-06-10'): array
    {
        return [
            'hub_id' => $hub->id,
            'hub_name' => $hub->name,
            'district_id' => $district->id,
            'district_name' => $district->name,
            'visit_date' => $visitDate,
            'organization_name' => $orgName,
            'organization_type' => 'ngo',
            'person_met_name' => 'Contact Person',
            'person_met_designation' => 'Lead',
            'poc_name' => 'Contact Person',
            'poc_phone' => '9123456789',
            'purpose' => 'partnership',
            'meeting_mode' => 'physical',
            'follow_up_required' => false,
            'submitted_by_user_id' => $admin->id,
            'submitted_by_name' => $admin->name,
            'status' => \App\Models\ServiceCase::STATUS_APPROVED,
            'submitted_at' => now(),
            'approved_at' => now(),
            'approved_by' => $admin->id,
        ];
    }
}
