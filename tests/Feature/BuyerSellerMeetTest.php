<?php

namespace Tests\Feature;

use App\Models\BuyerSellerMeetEntry;
use App\Models\District;
use App\Models\FiscalYear;
use App\Models\User;
use App\Services\Deliverables\ProgramDeliverablesFilter;
use App\Services\Deliverables\ProgramDeliverablesScope;
use App\Services\ProgramDeliverablesReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BuyerSellerMeetTest extends TestCase
{
    use RefreshDatabase;

    public function test_state_staff_can_log_meet_and_deliverable_10_5_counts_it(): void
    {
        Storage::fake('local');

        $fy = FiscalYear::query()->create([
            'code' => '2026-27',
            'name' => 'FY 2026-27',
            'starts_on' => '2026-04-01',
            'ends_on' => '2027-03-31',
            'is_active' => true,
        ]);

        $hubId = \DB::table('hubs')->insertGetId([
            'slug' => 'bsm-hub', 'name' => 'BSM Hub', 'sort_order' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $districtId = District::query()->insertGetId([
            'hub_id' => $hubId, 'slug' => 'bsm-dehradun', 'name' => 'Dehradun', 'sort_order' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $spoc = User::factory()->create([
            'role' => 'state_staff',
            'name' => 'Sanjna Mishra',
            'is_active' => true,
        ]);

        $payload = [
            'meet_date' => '2026-08-15',
            'district_id' => $districtId,
            'title' => 'Monsoon buyer seller meet',
            'venue' => 'Hub hall',
            'total_participants' => 25,
            'participants' => [
                [
                    'kind' => 'manual',
                    'role' => 'buyer',
                    'participant_name' => 'Buyer One',
                    'phone' => '9999900001',
                    'application_no' => '',
                ],
                [
                    'kind' => 'manual',
                    'role' => 'seller',
                    'participant_name' => 'Seller One',
                    'phone' => '9999900002',
                    'application_no' => '',
                ],
            ],
            'photos' => [UploadedFile::fake()->create('photo.jpg', 100, 'image/jpeg')],
            'attendance_files' => [UploadedFile::fake()->create('sheet.pdf', 100, 'application/pdf')],
        ];

        $this->actingAs($spoc)
            ->post(route('spoc.buyer-seller-meets.store'), $payload)
            ->assertRedirect(route('spoc.buyer-seller-meets.dashboard'));

        $this->assertSame(1, BuyerSellerMeetEntry::query()->count());

        $admin = User::factory()->create(['role' => 'state_admin', 'is_active' => true]);
        $filter = new ProgramDeliverablesFilter($fy->id, null, 8, 2026, '2026-08-01', '2026-08-31');
        $scope = ProgramDeliverablesScope::forUser($admin);
        $report = app(ProgramDeliverablesReportService::class)->build($filter, $scope);
        $row = collect($report['rows'])->firstWhere('serial', '10.5');

        $this->assertNotNull($row);
        $this->assertSame(1, (int) ($row['achievement'] ?? -1));
    }

    public function test_spoc_cannot_delete_meet(): void
    {
        $spoc = User::factory()->create([
            'role' => 'state_staff',
            'name' => 'Sanjna Mishra',
            'is_active' => true,
        ]);

        FiscalYear::query()->create([
            'code' => '2026-27',
            'name' => 'FY 2026-27',
            'starts_on' => '2026-04-01',
            'ends_on' => '2027-03-31',
            'is_active' => true,
        ]);

        $hubId = \DB::table('hubs')->insertGetId([
            'slug' => 'x-hub', 'name' => 'X Hub', 'sort_order' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $districtId = District::query()->insertGetId([
            'hub_id' => $hubId, 'slug' => 'x-dist', 'name' => 'Almora', 'sort_order' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $entry = BuyerSellerMeetEntry::query()->create([
            'meet_date' => '2026-08-01',
            'district_id' => $districtId,
            'title' => 'T',
            'venue' => 'V',
            'total_participants' => 10,
            'submitted_by_user_id' => $spoc->id,
            'submitted_by_name' => $spoc->name,
        ]);

        $this->actingAs($spoc)
            ->delete(route('admin.buyer-seller-meets.destroy', $entry))
            ->assertForbidden();
    }
}
