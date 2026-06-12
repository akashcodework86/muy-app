<?php

namespace Tests\Feature;

use App\Models\BusinessAccelerationPartnerOutreachEntry;
use App\Models\FiscalYear;
use App\Models\User;
use App\Services\Deliverables\ProgramDeliverablesFilter;
use App\Services\Deliverables\ProgramDeliverablesScope;
use App\Services\MisMonthlyTargetIndicatorBootstrapService;
use App\Services\ProgramDeliverablesReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BusinessAccelerationPartnersOutreachTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(MisMonthlyTargetIndicatorBootstrapService::class)->ensureDeliverables();
    }

    public function test_ankur_can_open_form_and_store_partners(): void
    {
        $ankur = User::factory()->create([
            'role' => 'state_staff',
            'name' => 'Ankur Rawat',
            'is_active' => true,
        ]);

        $this->actingAs($ankur)
            ->get(route('spoc.business-acceleration-partners-outreach.create'))
            ->assertOk()
            ->assertSee('7.1');

        $this->actingAs($ankur)
            ->post(route('spoc.business-acceleration-partners-outreach.store'), [
                'outreach_date' => '2026-05-20',
                'outreach_mode' => 'virtual',
                'partners' => [
                    [
                        'partner_name' => 'Accel Corp India',
                        'partner_type' => 'corporate',
                        'poc_name' => 'Priya Sharma',
                        'poc_phone' => '9876543210',
                        'remarks' => 'Intro call',
                    ],
                    [
                        'partner_name' => 'accel corp india',
                        'partner_type' => 'investor_vc',
                        'poc_name' => 'Raj Mehta',
                    ],
                ],
            ])
            ->assertRedirect(route('spoc.business-acceleration-partners-outreach.dashboard'))
            ->assertSessionHas('status');

        $this->assertDatabaseCount('business_acceleration_partner_outreach_entries', 2);
        $rows = BusinessAccelerationPartnerOutreachEntry::query()->get();
        $this->assertSame($rows[0]->batch_id, $rows[1]->batch_id);
        $this->assertSame('virtual', $rows[0]->outreach_mode);
    }

    public function test_non_allowlisted_state_staff_cannot_submit(): void
    {
        $other = User::factory()->create([
            'role' => 'state_staff',
            'name' => 'Other Staff',
            'is_active' => true,
        ]);

        $this->actingAs($other)
            ->get(route('spoc.business-acceleration-partners-outreach.create'))
            ->assertForbidden();
    }

    public function test_deliverable_counts_unique_partners_for_business_acceleration_row(): void
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

        $ankur = User::factory()->create([
            'role' => 'state_staff',
            'name' => 'Ankur Rawat',
            'is_active' => true,
        ]);

        $batchId = (string) \Illuminate\Support\Str::uuid();

        BusinessAccelerationPartnerOutreachEntry::query()->create([
            'batch_id' => $batchId,
            'outreach_date' => '2026-05-10',
            'outreach_mode' => 'physical',
            'partner_name' => 'Partner Alpha',
            'partner_type' => 'corporate',
            'poc_name' => 'A',
            'submitted_by_user_id' => $ankur->id,
            'submitted_by_name' => $ankur->name,
        ]);

        BusinessAccelerationPartnerOutreachEntry::query()->create([
            'batch_id' => $batchId,
            'outreach_date' => '2026-05-10',
            'outreach_mode' => 'physical',
            'partner_name' => 'partner alpha',
            'partner_type' => 'ngo',
            'poc_name' => 'B',
            'submitted_by_user_id' => $ankur->id,
            'submitted_by_name' => $ankur->name,
        ]);

        BusinessAccelerationPartnerOutreachEntry::query()->create([
            'batch_id' => (string) \Illuminate\Support\Str::uuid(),
            'outreach_date' => '2026-05-12',
            'outreach_mode' => 'phone_email',
            'partner_name' => 'Partner Beta',
            'partner_type' => 'bank_fi',
            'poc_name' => 'C',
            'submitted_by_user_id' => $ankur->id,
            'submitted_by_name' => $ankur->name,
        ]);

        $filter = new ProgramDeliverablesFilter($fy->id, null, null, null, null, null);
        $report = app(ProgramDeliverablesReportService::class)->build(
            $filter,
            ProgramDeliverablesScope::forUser(User::factory()->make(['role' => 'state_admin']))
        );

        $baRow = collect($report['rows'])->first(fn (array $row) => ($row['source_type'] ?? '') === 'business_acceleration_partners_outreach_count');
        $this->assertNotNull($baRow, 'Expected Business Acceleration 7.1 row in deliverables report.');
        $this->assertSame(2, $baRow['achievement']);
    }
}
