<?php

namespace Tests\Feature;

use App\Models\Deliverable;
use App\Models\FiscalYear;
use App\Models\StakeholderConsultationWorkshop;
use App\Models\User;
use App\Services\Deliverables\ProgramDeliverablesAchievementBreakdownService;
use App\Services\Deliverables\ProgramDeliverablesFilter;
use App\Services\Deliverables\ProgramDeliverablesScope;
use App\Services\MisMonthlyTargetIndicatorBootstrapService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StakeholderConsultationWorkshopTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(MisMonthlyTargetIndicatorBootstrapService::class)->ensureDeliverables();
    }

    public function test_aadil_can_store_workshop(): void
    {
        Storage::fake();

        $aadil = User::factory()->create([
            'role' => 'state_staff',
            'email' => 'aadil.ishrat@pwc.com',
            'is_active' => true,
        ]);

        $this->actingAs($aadil)
            ->post(route('spoc.stakeholder-consultation-workshops.store'), [
                'workshop_date' => now()->toDateString(),
                'workshop_title' => 'Tourism synergy workshop',
                'workshop_mode' => 'physical',
                'venue' => 'Dehradun',
                'organizing_level' => 'state',
                'primary_departments' => ['tourism', 'usrlm'],
                'stakeholder_types' => ['line_department', 'reap'],
                'total_participants' => 25,
                'consultation_theme' => 'Convergence planning',
                'key_outcomes' => 'Joint action plan drafted',
                'attendance_media' => [UploadedFile::fake()->create('attendance.pdf', 100, 'application/pdf')],
            ])
            ->assertRedirect(route('spoc.stakeholder-consultation-workshops.dashboard'));

        $this->assertDatabaseCount('stakeholder_consultation_workshops', 1);
    }

    public function test_non_aadil_cannot_submit(): void
    {
        $other = User::factory()->create(['role' => 'state_staff', 'email' => 'other@example.com', 'is_active' => true]);

        $this->actingAs($other)
            ->get(route('spoc.stakeholder-consultation-workshops.create'))
            ->assertForbidden();
    }

    public function test_program_deliverables_counts_workshops_for_indicator_12_1(): void
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

        StakeholderConsultationWorkshop::query()->create([
            'submitted_by_user_id' => 1,
            'submitted_by_name' => 'Aadil',
            'workshop_date' => '2026-05-10',
            'workshop_title' => 'W1',
            'workshop_mode' => 'physical',
            'venue' => 'Venue',
            'organizing_level' => 'state',
            'primary_departments_json' => ['tourism'],
            'stakeholder_types_json' => ['reap'],
            'total_participants' => 10,
            'consultation_theme' => 'Theme',
            'key_outcomes' => 'Outcomes',
            'attendance_media_json' => [['path' => 'x', 'original_name' => 'a.pdf']],
        ]);
        StakeholderConsultationWorkshop::query()->create([
            'submitted_by_user_id' => 1,
            'submitted_by_name' => 'Aadil',
            'workshop_date' => '2026-06-01',
            'workshop_title' => 'W2',
            'workshop_mode' => 'virtual',
            'venue' => 'Online',
            'organizing_level' => 'state',
            'primary_departments_json' => ['agriculture'],
            'stakeholder_types_json' => ['usrlm'],
            'total_participants' => 5,
            'consultation_theme' => 'Theme 2',
            'key_outcomes' => 'Outcomes 2',
            'attendance_media_json' => [['path' => 'y', 'original_name' => 'b.pdf']],
        ]);

        Deliverable::query()->where('code', 'stakeholder_consultation_workshop')->firstOrFail();

        $filter = new ProgramDeliverablesFilter($fy->id, null, null, null, null, null);
        $scope = ProgramDeliverablesScope::forUser(User::factory()->make(['role' => 'state_admin']));
        $breakdown = app(ProgramDeliverablesAchievementBreakdownService::class)->build($filter, $scope, '12.1');

        $this->assertSame(2, $breakdown['total']);
        $this->assertCount(2, $breakdown['records']);
    }
}
