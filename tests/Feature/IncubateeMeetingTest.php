<?php

namespace Tests\Feature;

use App\Models\CfaSubmission;
use App\Models\Designation;
use App\Models\District;
use App\Models\Hub;
use App\Models\IncubateeMeeting;
use App\Models\User;
use App\Support\IncubateeLocale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class IncubateeMeetingTest extends TestCase
{
    use RefreshDatabase;

    public function test_state_admin_can_schedule_meeting_visible_to_all_incubatees(): void
    {
        [$first, $district] = $this->seedIncubatee();
        $second = $this->makeIncubatee($district, 'Rina Joshi', '9333333333', 'APP-INC-2');
        $admin = User::factory()->create(['role' => 'state_admin', 'is_active' => true]);
        $when = now()->addDay()->format('Y-m-d\TH:i');

        $this->actingAs($admin)
            ->get(route('admin.incubatee-meetings.dashboard'))
            ->assertOk()
            ->assertSee('Incubatee meetings', false)
            ->assertSee('Schedule meeting', false);

        $this->actingAs($admin)
            ->post(route('admin.incubatee-meetings.store'), [
                'title' => 'All-incubatee orientation',
                'scheduled_at' => $when,
                'duration_minutes' => 45,
                'mode' => IncubateeMeeting::MODE_ONLINE,
                'meeting_link' => 'https://meet.example.com/orientation',
                'agenda' => 'Introductions and questions',
            ])
            ->assertRedirect(route('admin.incubatee-meetings.dashboard'));

        $row = IncubateeMeeting::query()->first();
        $this->assertNotNull($row);
        $this->assertSame('All-incubatee orientation', $row->title);
        $this->assertSame((int) $admin->id, (int) $row->created_by_user_id);
        $this->assertSame(IncubateeMeeting::STATUS_SCHEDULED, $row->status);

        $this->actingAs($first)
            ->get(route('incubatee.dashboard'))
            ->assertOk()
            ->assertSee('All-incubatee orientation', false)
            ->assertSee('Introductions and questions', false)
            ->assertSee('https://meet.example.com/orientation', false);

        $this->actingAs($second)
            ->withSession([IncubateeLocale::COOKIE => 'en'])
            ->get(route('incubatee.dashboard'))
            ->assertOk()
            ->assertSee('All-incubatee orientation', false)
            ->assertSee('Upcoming meetings', false);
    }

    public function test_hub_im_and_bpde_can_create_but_field_coordinator_cannot(): void
    {
        [, $district] = $this->seedIncubatee();
        $hub = User::factory()->create([
            'role' => 'hub_admin',
            'hub_id' => $district->hub_id,
            'is_active' => true,
        ]);
        $im = $this->makeStaff($district, 'Incubation Manager');
        $bpde = $this->makeStaff($district, 'BPDE');
        $fc = $this->makeStaff($district, 'Field Coordinator');
        $payload = [
            'title' => 'Weekly catch-up',
            'scheduled_at' => now()->addDays(2)->format('Y-m-d\TH:i'),
            'mode' => IncubateeMeeting::MODE_IN_PERSON,
            'venue' => 'District hub hall',
        ];

        $this->actingAs($hub)
            ->post(route('hub.incubatee-meetings.store'), $payload)
            ->assertRedirect(route('hub.incubatee-meetings.dashboard'));

        $this->actingAs($im)
            ->post(route('staff.incubatee-meetings.store'), [
                ...$payload,
                'title' => 'IM office hours',
            ])
            ->assertRedirect(route('staff.incubatee-meetings.dashboard'));

        $this->actingAs($bpde)
            ->post(route('staff.incubatee-meetings.store'), [
                ...$payload,
                'title' => 'BPDE planning session',
            ])
            ->assertRedirect(route('staff.incubatee-meetings.dashboard'));

        $this->actingAs($fc)
            ->get(route('staff.incubatee-meetings.dashboard'))
            ->assertForbidden();

        $this->actingAs($fc)
            ->post(route('staff.incubatee-meetings.store'), $payload)
            ->assertForbidden();

        $this->assertSame(3, IncubateeMeeting::query()->count());
    }

    public function test_only_creator_can_edit_cancel_or_mark_done(): void
    {
        Storage::fake();
        [, $district] = $this->seedIncubatee();
        $creator = $this->makeStaff($district, 'Incubation Manager');
        $other = $this->makeStaff($district, 'BPDE');
        $meeting = IncubateeMeeting::query()->create([
            'title' => 'Creator only session',
            'scheduled_at' => now()->addDay(),
            'mode' => IncubateeMeeting::MODE_ONLINE,
            'meeting_link' => 'https://meet.example.com/creator',
            'status' => IncubateeMeeting::STATUS_SCHEDULED,
            'created_by_user_id' => $creator->id,
        ]);

        $this->actingAs($other)
            ->get(route('staff.incubatee-meetings.edit', $meeting))
            ->assertForbidden();

        $this->actingAs($other)
            ->post(route('staff.incubatee-meetings.cancel', $meeting))
            ->assertForbidden();

        $this->actingAs($other)
            ->post(route('staff.incubatee-meetings.complete.store', $meeting), [
                'proof' => UploadedFile::fake()->image('shot.jpg'),
            ])
            ->assertForbidden();

        $this->actingAs($other)
            ->get(route('staff.incubatee-meetings.dashboard'))
            ->assertOk()
            ->assertSee('Creator only session', false)
            ->assertDontSee('>Edit<', false)
            ->assertDontSee('Mark done', false);

        $this->actingAs($creator)
            ->put(route('staff.incubatee-meetings.update', $meeting), [
                'title' => 'Creator only session (updated)',
                'scheduled_at' => now()->addDays(3)->format('Y-m-d\TH:i'),
                'mode' => IncubateeMeeting::MODE_ONLINE,
                'meeting_link' => 'https://meet.example.com/updated',
                'agenda' => 'Bring questions',
            ])
            ->assertRedirect(route('staff.incubatee-meetings.dashboard'));

        $this->assertSame('Creator only session (updated)', $meeting->fresh()->title);

        $this->actingAs($creator)
            ->post(route('staff.incubatee-meetings.complete.store', $meeting), [
                'proof' => UploadedFile::fake()->create('attendance.pdf', 40, 'application/pdf'),
            ])
            ->assertRedirect(route('staff.incubatee-meetings.dashboard'));

        $fresh = $meeting->fresh();
        $this->assertSame(IncubateeMeeting::STATUS_DONE, $fresh->status);
        $this->assertNotNull($fresh->proof_path);
        $this->assertSame((int) $creator->id, (int) $fresh->done_by_user_id);

        $this->actingAs($other)
            ->get(route('staff.incubatee-meetings.dashboard'))
            ->assertOk()
            ->assertSee('Creator only session (updated)', false)
            ->assertDontSee('>Edit<', false)
            ->assertDontSee('Mark done', false);
    }

    public function test_past_and_done_meetings_show_in_history_not_upcoming(): void
    {
        [$incubatee] = $this->seedIncubatee();
        $admin = User::factory()->create(['role' => 'state_admin', 'is_active' => true]);

        IncubateeMeeting::query()->create([
            'title' => 'Yesterday briefing',
            'scheduled_at' => now()->subDay(),
            'mode' => IncubateeMeeting::MODE_IN_PERSON,
            'venue' => 'Hall A',
            'status' => IncubateeMeeting::STATUS_DONE,
            'created_by_user_id' => $admin->id,
            'done_at' => now()->subDay(),
            'done_by_user_id' => $admin->id,
        ]);

        $this->actingAs($incubatee)
            ->withSession([IncubateeLocale::COOKIE => 'en'])
            ->get(route('incubatee.dashboard'))
            ->assertOk()
            ->assertSee('Yesterday briefing', false)
            ->assertSee('Past meetings', false)
            ->assertSee('No upcoming meetings', false);
    }

    public function test_spoc_can_open_meetings_dashboard(): void
    {
        $this->seedIncubatee();
        $spoc = User::factory()->create(['role' => 'state_staff', 'is_active' => true]);

        $this->actingAs($spoc)
            ->get(route('spoc.incubatee-meetings.dashboard'))
            ->assertOk()
            ->assertSee('Incubatee meetings', false);
    }

    /**
     * @return array{0: User, 1: District}
     */
    private function seedIncubatee(): array
    {
        $hub = Hub::query()->create(['slug' => 'meet-hub', 'name' => 'Meet Hub', 'sort_order' => 1]);
        $district = District::query()->create([
            'hub_id' => $hub->id,
            'slug' => 'meet-district',
            'name' => 'Meet District',
            'sort_order' => 1,
        ]);
        $incubatee = $this->makeIncubatee($district, 'Priya Sharma', '9444444444', 'APP-MEET-1');

        return [$incubatee, $district];
    }

    private function makeIncubatee(District $district, string $name, string $phone, string $appNo): User
    {
        $cfa = CfaSubmission::query()->create([
            'district_id' => $district->id,
            'applicant_name' => $name,
            'phone' => $phone,
            'application_no' => $appNo,
            'payload' => [],
        ]);

        return User::factory()->create([
            'role' => 'incubatee',
            'name' => $name,
            'phone' => $phone,
            'password' => $phone,
            'cfa_submission_id' => $cfa->id,
            'district_id' => $district->id,
            'is_active' => true,
        ]);
    }

    private function makeStaff(District $district, string $designationName): User
    {
        $designation = Designation::query()->firstOrCreate(
            ['name' => $designationName],
            ['sort_order' => 1]
        );

        return User::factory()->create([
            'role' => 'district_staff',
            'district_id' => $district->id,
            'hub_id' => $district->hub_id,
            'designation_id' => $designation->id,
            'is_active' => true,
        ]);
    }
}
