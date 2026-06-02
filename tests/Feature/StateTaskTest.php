<?php

namespace Tests\Feature;

use App\Models\StateTask;
use App\Models\StateTaskAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StateTaskTest extends TestCase
{
    use RefreshDatabase;

    public function test_state_admin_can_create_publish_and_assign_task_to_multiple_staff(): void
    {
        $admin = User::factory()->create(['role' => 'state_admin', 'is_active' => true]);
        $spocA = User::factory()->create(['role' => 'state_staff', 'is_active' => true]);
        $spocB = User::factory()->create(['role' => 'state_staff', 'is_active' => true]);

        $response = $this->actingAs($admin)->post(route('admin.state-tasks.store'), [
            'title' => 'District review drive',
            'description' => 'Review pending cases in mapped districts.',
            'start_date' => now()->toDateString(),
            'due_date' => now()->addDays(7)->toDateString(),
            'target_value' => 20,
            'assignee_ids' => [$spocA->id, $spocB->id],
            'publish' => 1,
        ]);

        $response->assertRedirect();
        $task = StateTask::query()->first();
        $this->assertNotNull($task);
        $this->assertSame(StateTask::STATUS_PUBLISHED, $task->status);
        $this->assertSame(2, $task->assignments()->count());
    }

    public function test_state_staff_can_update_submit_and_admin_can_complete(): void
    {
        Storage::fake('local');

        $admin = User::factory()->create(['role' => 'state_admin', 'is_active' => true]);
        $spoc = User::factory()->create(['role' => 'state_staff', 'is_active' => true]);
        $otherSpoc = User::factory()->create(['role' => 'state_staff', 'is_active' => true]);

        $this->actingAs($admin)->post(route('admin.state-tasks.store'), [
            'title' => 'Follow-up calls',
            'due_date' => now()->addDays(3)->toDateString(),
            'target_value' => 10,
            'assignee_ids' => [$spoc->id, $otherSpoc->id],
            'publish' => 1,
        ]);

        $task = StateTask::query()->firstOrFail();
        $assignment = StateTaskAssignment::query()
            ->where('state_task_id', $task->id)
            ->where('assignee_user_id', $spoc->id)
            ->firstOrFail();

        $this->actingAs($spoc)
            ->post(route('spoc.state-tasks.progress', $task), [
                'progress_value' => 7,
                'staff_note' => 'Seven districts covered.',
            ])
            ->assertRedirect();

        $assignment->refresh();
        $this->assertSame(StateTaskAssignment::STATUS_IN_PROGRESS, $assignment->status);
        $this->assertSame(7, (int) $assignment->progress_value);

        $this->actingAs($spoc)
            ->post(route('spoc.state-tasks.submit', $task), [
                'staff_note' => 'Ready for review.',
            ])
            ->assertRedirect();

        $assignment->refresh();
        $this->assertSame(StateTaskAssignment::STATUS_SUBMITTED, $assignment->status);

        $this->actingAs($admin)
            ->post(route('admin.state-tasks.assignments.complete', [$task, $assignment]), [
                'admin_note' => 'Good work.',
            ])
            ->assertRedirect();

        $assignment->refresh();
        $this->assertSame(StateTaskAssignment::STATUS_COMPLETED, $assignment->status);
        $this->assertNotNull($assignment->completed_at);
    }

    public function test_state_staff_cannot_view_task_they_are_not_assigned_to(): void
    {
        $admin = User::factory()->create(['role' => 'state_admin', 'is_active' => true]);
        $spoc = User::factory()->create(['role' => 'state_staff', 'is_active' => true]);
        $other = User::factory()->create(['role' => 'state_staff', 'is_active' => true]);

        $this->actingAs($admin)->post(route('admin.state-tasks.store'), [
            'title' => 'Private task',
            'assignee_ids' => [$spoc->id],
            'publish' => 1,
        ]);

        $task = StateTask::query()->firstOrFail();

        $this->actingAs($other)
            ->get(route('spoc.state-tasks.show', $task))
            ->assertNotFound();
    }

    public function test_admin_can_attach_document_to_task(): void
    {
        Storage::fake('local');

        $admin = User::factory()->create(['role' => 'state_admin', 'is_active' => true]);
        $spoc = User::factory()->create(['role' => 'state_staff', 'is_active' => true]);

        $this->actingAs($admin)->post(route('admin.state-tasks.store'), [
            'title' => 'Guideline task',
            'assignee_ids' => [$spoc->id],
            'attachments' => [
                UploadedFile::fake()->create('guide.pdf', 100, 'application/pdf'),
            ],
            'publish' => 1,
        ]);

        $task = StateTask::query()->with('attachments')->firstOrFail();
        $this->assertCount(1, $task->attachments);

        $this->actingAs($admin)
            ->get(route('admin.state-tasks.attachments.download', [$task, $task->attachments->first()]))
            ->assertOk();
    }

    public function test_admin_can_send_back_submitted_assignment(): void
    {
        $admin = User::factory()->create(['role' => 'state_admin', 'is_active' => true]);
        $spoc = User::factory()->create(['role' => 'state_staff', 'is_active' => true]);

        $this->actingAs($admin)->post(route('admin.state-tasks.store'), [
            'title' => 'Resubmit task',
            'assignee_ids' => [$spoc->id],
            'publish' => 1,
        ]);

        $task = StateTask::query()->firstOrFail();

        $this->actingAs($spoc)->post(route('spoc.state-tasks.submit', $task));

        $assignment = StateTaskAssignment::query()->where('assignee_user_id', $spoc->id)->firstOrFail();

        $this->actingAs($admin)
            ->post(route('admin.state-tasks.assignments.send-back', [$task, $assignment]), [
                'admin_note' => 'Please add district-wise breakdown.',
            ])
            ->assertRedirect();

        $assignment->refresh();
        $this->assertSame(StateTaskAssignment::STATUS_SENT_BACK, $assignment->status);
        $this->assertSame('Please add district-wise breakdown.', $assignment->admin_note);
    }
}
