<?php

namespace Tests\Feature;

use App\Models\Deliverable;
use App\Models\FiscalYear;
use App\Models\StateDeliverableTarget;
use App\Models\StateMonthlyTarget;
use App\Models\User;
use App\Services\StateMonthlyTargetIndicatorBootstrapService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StateMonthlyTargetsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(StateMonthlyTargetIndicatorBootstrapService::class)->ensureDeliverables();
    }

    public function test_page_renders_for_state_admin(): void
    {
        $admin = User::factory()->create(['role' => 'state_admin', 'is_active' => true]);

        $this->actingAs($admin)
            ->get(route('admin.targets.state-monthly'))
            ->assertOk()
            ->assertSee('State monthly targets')
            ->assertSee('3.3')
            ->assertSee('Technical Trainings to Incubatees')
            ->assertSee('8.3')
            ->assertSee('Incubatees Pitch Deck Preparation')
            ->assertSee('Technical Trainings to Potential Lakhpati Didis')
            ->assertSee('Mentorship Support through online portal')
            ->assertSee('Initiation of acceleration and co-incubation services')
            ->assertSee('Other Support Services - Labelling, Packaging, Logo Designing etc.')
            ->assertSee('Advance Licensing Support (Mandi Licensing, Lab Test etc.)')
            ->assertSee('Partnership & Forward Linkages')
            ->assertSee('6.1')
            ->assertSee('No of Partners outreach')
            ->assertSee('Marketing Partners Onboarded through (LoA/LoI/MoU)')
            ->assertSee('Branding, Communication & Knowledge Management')
            ->assertSee('10.1')
            ->assertSee('Social Media Post')
            ->assertSee('Product Development')
            ->assertSee('Identification and Submission of Proposal for New Product Development')
            ->assertSee('Paste M1–M12 targets (tab, space, or comma separated)');
    }

    public function test_page_only_lists_configured_services(): void
    {
        Deliverable::query()->create([
            'sort_order' => 999,
            'code' => 'svc_not_on_state_monthly',
            'name' => 'Not On State Monthly Page',
            'mis_entry_label' => 'NO',
            'is_active' => true,
        ]);

        $admin = User::factory()->create(['role' => 'state_admin', 'is_active' => true]);

        $this->actingAs($admin)
            ->get(route('admin.targets.state-monthly'))
            ->assertOk()
            ->assertSee('Capacity Building of stakeholders')
            ->assertDontSee('Not On State Monthly Page');
    }

    public function test_save_state_monthly_grid_for_technical_training(): void
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

        $deliverable = Deliverable::query()->where('code', 'technical_training_sessions')->firstOrFail();

        StateDeliverableTarget::query()->create([
            'fiscal_year_id' => $fy->id,
            'deliverable_id' => $deliverable->id,
            'target_total' => 12,
        ]);

        $months = array_fill(1, 12, 1);

        $admin = User::factory()->create(['role' => 'state_admin', 'is_active' => true]);

        $this->actingAs($admin)
            ->post(route('admin.targets.state-monthly.update'), [
                'fiscal_year_id' => $fy->id,
                'deliverables' => [
                    $deliverable->id => $months,
                ],
            ])
            ->assertRedirect(route('admin.targets.state-monthly', ['fiscal_year_id' => $fy->id]))
            ->assertSessionHas('status');

        $this->assertSame(12, (int) StateMonthlyTarget::query()
            ->where('fiscal_year_id', $fy->id)
            ->where('deliverable_id', $deliverable->id)
            ->sum('target_count'));
    }
}
