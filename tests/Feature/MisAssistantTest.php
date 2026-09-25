<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\MisAssistantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class MisAssistantTest extends TestCase
{
    use RefreshDatabase;

    public function test_state_admin_can_open_mis_assistant(): void
    {
        $admin = User::factory()->create(['role' => 'state_admin', 'is_active' => true]);

        $this->actingAs($admin)
            ->get(route('admin.mis-assistant.index'))
            ->assertOk()
            ->assertSee('Ask about programme data')
            ->assertSee('READ ONLY');
    }

    public function test_non_state_admin_cannot_open_mis_assistant(): void
    {
        $staff = User::factory()->create(['role' => 'district_staff', 'is_active' => true]);

        $this->actingAs($staff)
            ->get('/admin/mis-assistant')
            ->assertForbidden();
    }

    public function test_question_endpoint_returns_structured_read_only_answer(): void
    {
        $admin = User::factory()->create(['role' => 'state_admin', 'is_active' => true]);
        $this->mock(MisAssistantService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('answer')->once()->with('Aaj kitne forms aaye?')->andReturn([
                'ok' => true,
                'intent' => 'cfa',
                'answer' => 'Found 11 CFA applications.',
                'metric' => 'CFA applications',
                'total' => 11,
                'rows' => [],
                'filters' => ['Today'],
                'source_url' => '/admin/cfa-applications',
                'note' => 'Live read-only MIS data.',
            ]);
        });

        $this->actingAs($admin)
            ->postJson(route('admin.mis-assistant.ask'), ['question' => 'Aaj kitne forms aaye?'])
            ->assertOk()
            ->assertJsonPath('intent', 'cfa')
            ->assertJsonPath('total', 11);
    }
}
