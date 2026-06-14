<?php

namespace Tests\Feature;

use App\Models\DemoDay;
use App\Models\FiscalYear;
use App\Models\FundingSchematicPartnerOutreachEntry;
use App\Models\User;
use App\Services\Deliverables\ProgramDeliverablesFilter;
use App\Services\Deliverables\ProgramDeliverablesScope;
use App\Services\ProgramDeliverablesReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class FundingSchematicConvergenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_govind_can_create_demo_day_and_partner_outreach(): void
    {
        $govind = User::factory()->create([
            'role' => 'state_staff',
            'name' => 'Govind Singh Dhami',
            'email' => 'govind.dhami@pwc.com',
            'is_active' => true,
        ]);

        $this->actingAs($govind)
            ->get(route('spoc.demo-days.create'))
            ->assertOk();

        $this->actingAs($govind)
            ->post(route('spoc.demo-days.store'), [
                'event_date' => '2026-06-01',
                'event_name' => 'State Demo Day',
                'event_type' => 'workshop',
            ])
            ->assertSessionHasErrors('event_photos');

        $this->actingAs($govind)
            ->get(route('spoc.funding-partners-outreach.create'))
            ->assertOk();

        $this->actingAs($govind)
            ->post(route('spoc.funding-partners-outreach.store'), [
                'outreach_date' => '2026-06-02',
                'outreach_mode' => 'call',
                'partners' => [[
                    'partner_name' => 'ABC Investors',
                    'partner_type' => 'investor',
                    'poc_phone' => '9876543210',
                ]],
            ])
            ->assertRedirect(route('spoc.funding-partners-outreach.dashboard'));

        $this->assertDatabaseCount('funding_schematic_partner_outreach_entries', 1);
    }

    public function test_other_state_staff_cannot_submit_funding_modules(): void
    {
        $other = User::factory()->create([
            'role' => 'state_staff',
            'name' => 'Other Staff',
            'is_active' => true,
        ]);

        $this->actingAs($other)
            ->get(route('spoc.demo-days.create'))
            ->assertForbidden();

        $this->actingAs($other)
            ->get(route('spoc.funding-partners-outreach.create'))
            ->assertForbidden();
    }

    public function test_state_admin_can_view_dashboards(): void
    {
        $admin = User::factory()->create(['role' => 'state_admin', 'name' => 'State Admin', 'is_active' => true]);
        $govind = User::factory()->create(['role' => 'state_staff', 'name' => 'Govind Singh Dhami', 'is_active' => true]);

        DemoDay::query()->create([
            'event_date' => '2026-06-01',
            'event_name' => 'Demo Event',
            'event_type' => 'workshop',
            'male_participants' => 1,
            'female_participants' => 2,
            'incubatee_name' => 'Test Incubatee',
            'entered_by_user_id' => $govind->id,
            'entered_by_name' => $govind->name,
        ]);

        FundingSchematicPartnerOutreachEntry::query()->create([
            'batch_id' => (string) Str::uuid(),
            'outreach_date' => '2026-06-02',
            'outreach_mode' => 'email',
            'partner_name' => 'Partner X',
            'partner_type' => 'investor',
            'poc_phone' => '9876543210',
            'submitted_by_user_id' => $govind->id,
            'submitted_by_name' => $govind->name,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.demo-days.dashboard'))
            ->assertOk()
            ->assertSee('Demo Event', false);

        $this->actingAs($admin)
            ->get(route('admin.funding-partners-outreach.dashboard'))
            ->assertOk()
            ->assertSee('Partner X', false);
    }

    public function test_deliverables_report_counts_funding_modules(): void
    {
        $fy = FiscalYear::query()->create([
            'code' => '2026-27',
            'name' => 'FY 2026-27',
            'starts_on' => '2026-04-01',
            'ends_on' => '2027-03-31',
            'is_active' => true,
        ]);

        DemoDay::query()->create([
            'event_date' => '2026-06-10',
            'event_name' => 'Demo A',
            'event_type' => 'workshop',
            'male_participants' => 5,
            'female_participants' => 5,
            'incubatee_name' => 'Inc A',
            'entered_by_user_id' => 1,
            'entered_by_name' => 'Govind',
        ]);

        FundingSchematicPartnerOutreachEntry::query()->create([
            'batch_id' => (string) Str::uuid(),
            'outreach_date' => '2026-06-11',
            'outreach_mode' => 'call',
            'partner_name' => 'Unique Partner',
            'partner_type' => 'bank_nbfc',
            'poc_phone' => '9876543210',
            'submitted_by_user_id' => 1,
            'submitted_by_name' => 'Govind',
        ]);

        $filter = new ProgramDeliverablesFilter($fy->id, null, null, null, null, null);
        $scope = ProgramDeliverablesScope::forUser(User::factory()->make(['role' => 'state_admin']));
        $report = app(ProgramDeliverablesReportService::class)->build($filter, $scope);

        $demoRow = collect($report['rows'])->first(fn (array $row) => ($row['source_type'] ?? '') === 'demo_days_count');
        $partnerRow = collect($report['rows'])->first(fn (array $row) => ($row['source_type'] ?? '') === 'funding_schematic_partners_outreach_count');

        $this->assertNotNull($demoRow);
        $this->assertSame(1, (int) ($demoRow['achievement'] ?? 0));
        $this->assertNotNull($partnerRow);
        $this->assertSame(1, (int) ($partnerRow['achievement'] ?? 0));
    }
}
