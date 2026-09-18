<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DataCentreApplicationAnalysisTest extends TestCase
{
    use RefreshDatabase;

    public function test_analysis_tab_renders_with_phase_filter(): void
    {
        $admin = User::factory()->create([
            'role' => 'state_admin',
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.data-centre.index', [
            'view' => 'analysis',
            'phase' => 'combined',
        ]));

        $response->assertOk();
        $response->assertSee('Analysis Card (All phase)');
        $response->assertSee('Filters — Analysis Card (All phase)');
    }
}
