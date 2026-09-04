<?php

namespace Tests\Feature;

use App\Models\CfaSubmission;
use App\Models\District;
use App\Models\Hub;
use App\Models\OnboardingBatch;
use App\Models\OnboardingBatchCfa;
use App\Models\User;
use App\Services\IncubateeProvisioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IncubateePhoneLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_incubatee_logs_in_with_ten_digit_mobile_and_same_password(): void
    {
        [$cfa, $district] = $this->seedCfa('9876543210');
        User::factory()->create([
            'role' => 'incubatee',
            'name' => 'Priya',
            'phone' => '9876543210',
            'password' => '9876543210',
            'cfa_submission_id' => $cfa->id,
            'district_id' => $district->id,
            'is_active' => true,
        ]);

        $this->post(route('login'), [
            'login' => '9876543210',
            'password' => '9876543210',
        ])->assertRedirect(route('incubatee.dashboard'));

        $this->assertAuthenticated();
        $this->assertSame('incubatee', auth()->user()->role);
    }

    public function test_staff_still_log_in_with_email(): void
    {
        $user = User::factory()->create([
            'role' => 'state_admin',
            'email' => 'admin@example.com',
            'password' => 'password',
            'is_active' => true,
        ]);

        $this->post(route('login'), [
            'login' => 'admin@example.com',
            'password' => 'password',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_disabled_incubatee_is_sent_to_login_not_a_missing_route(): void
    {
        [$cfa, $district] = $this->seedCfa('9876543210');
        $incubatee = User::factory()->create([
            'role' => 'incubatee',
            'name' => 'Priya',
            'phone' => '9876543210',
            'password' => '9876543210',
            'cfa_submission_id' => $cfa->id,
            'district_id' => $district->id,
            'is_active' => false,
        ]);

        $this->actingAs($incubatee)
            ->get(route('incubatee.dashboard'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('login');

        $this->assertGuest();
    }

    public function test_provisioning_skips_missing_phone_and_creates_when_phone_saved(): void
    {
        [$cfa, $district, $hub] = $this->seedCfa('');
        $batch = OnboardingBatch::query()->create([
            'hub_id' => $hub->id,
            'district_id' => $district->id,
            'name' => 'Locked',
            'target_size' => 10,
            'status' => 'locked',
            'locked_at' => now(),
        ]);
        OnboardingBatchCfa::query()->create([
            'onboarding_batch_id' => $batch->id,
            'cfa_submission_id' => $cfa->id,
        ]);

        $service = app(IncubateeProvisioningService::class);
        $first = $service->provision((int) $batch->id, false);
        $this->assertSame(0, $first['created']);
        $this->assertCount(1, $first['missing_phones']);

        $service->saveLoginPhone($cfa->fresh(), '9123456789');
        $second = $service->provision((int) $batch->id, false);
        $this->assertSame(1, $second['created']);

        $this->assertDatabaseHas('users', [
            'role' => 'incubatee',
            'cfa_submission_id' => $cfa->id,
            'phone' => '9123456789',
        ]);
    }

    public function test_duplicate_phone_is_blocked_until_new_number_is_saved(): void
    {
        [$hub, $district] = $this->seedHub();
        $firstCfa = $this->makeCfa($district, 'Priya', '9000000001');
        $secondCfa = $this->makeCfa($district, 'Ravi', '9000000001');
        $batch = OnboardingBatch::query()->create([
            'hub_id' => $hub->id,
            'district_id' => $district->id,
            'name' => 'Locked',
            'target_size' => 10,
            'status' => 'locked',
            'locked_at' => now(),
        ]);
        OnboardingBatchCfa::query()->create(['onboarding_batch_id' => $batch->id, 'cfa_submission_id' => $firstCfa->id]);
        OnboardingBatchCfa::query()->create(['onboarding_batch_id' => $batch->id, 'cfa_submission_id' => $secondCfa->id]);

        $service = app(IncubateeProvisioningService::class);
        $result = $service->provision((int) $batch->id, false);
        $this->assertSame(1, $result['created']);
        $this->assertCount(1, $result['duplicate_phones']);

        $service->saveLoginPhone($secondCfa->fresh(), '9000000002');
        $again = $service->provision((int) $batch->id, false);
        $this->assertSame(1, $again['created']);
        $this->assertDatabaseHas('users', ['cfa_submission_id' => $secondCfa->id, 'phone' => '9000000002']);
    }

    public function test_leftover_login_is_created_later_for_one_member(): void
    {
        [$hub, $district] = $this->seedHub();
        $ready = $this->makeCfa($district, 'Priya', '9000000001');
        $leftover = $this->makeCfa($district, 'Ravi', '');
        $batch = OnboardingBatch::query()->create([
            'hub_id' => $hub->id,
            'district_id' => $district->id,
            'name' => 'Locked',
            'target_size' => 10,
            'status' => 'locked',
            'locked_at' => now(),
        ]);
        OnboardingBatchCfa::query()->create(['onboarding_batch_id' => $batch->id, 'cfa_submission_id' => $ready->id]);
        OnboardingBatchCfa::query()->create(['onboarding_batch_id' => $batch->id, 'cfa_submission_id' => $leftover->id]);

        $service = app(IncubateeProvisioningService::class);
        $bulk = $service->provision((int) $batch->id, false);
        $this->assertSame(1, $bulk['created']);
        $this->assertCount(1, $bulk['missing_phones']);
        $this->assertDatabaseMissing('users', ['cfa_submission_id' => $leftover->id]);

        $service->saveLoginPhone($leftover->fresh(), '9000000003');
        $one = $service->provisionOneInBatch((int) $batch->id, (int) $leftover->id);
        $this->assertSame('created', $one['status']);
        $this->assertDatabaseHas('users', [
            'role' => 'incubatee',
            'cfa_submission_id' => $leftover->id,
            'phone' => '9000000003',
        ]);
    }

    public function test_pending_row_is_created_even_if_cfa_email_already_exists(): void
    {
        [$hub, $district] = $this->seedHub();
        User::factory()->create([
            'role' => 'state_staff',
            'email' => 'shared@example.com',
            'is_active' => true,
        ]);
        $cfa = $this->makeCfa($district, 'Jai Maa SHG', '9888888888');
        $cfa->payload = ['email' => 'shared@example.com'];
        $cfa->save();
        $batch = OnboardingBatch::query()->create([
            'hub_id' => $hub->id,
            'district_id' => $district->id,
            'name' => 'Locked',
            'target_size' => 10,
            'status' => 'locked',
            'locked_at' => now(),
        ]);
        OnboardingBatchCfa::query()->create([
            'onboarding_batch_id' => $batch->id,
            'cfa_submission_id' => $cfa->id,
        ]);

        $one = app(IncubateeProvisioningService::class)->provisionOneInBatch((int) $batch->id, (int) $cfa->id);
        $this->assertSame('created', $one['status']);
        $created = User::query()->where('cfa_submission_id', $cfa->id)->where('role', 'incubatee')->first();
        $this->assertNotNull($created);
        $this->assertSame('9888888888', $created->phone);
        $this->assertNotSame('shared@example.com', $created->email);
    }

    public function test_pending_reason_is_phone_only_not_cfa_email(): void
    {
        [$hub, $district] = $this->seedHub();
        User::factory()->create([
            'role' => 'incubatee',
            'name' => 'Maa Bhagwati SHG',
            'email' => 'rg064345@gmail.com',
            'phone' => '9111111111',
            'is_active' => true,
        ]);
        $cfa = $this->makeCfa($district, 'Purnagiri SHG', '9837489212');
        $cfa->payload = ['email' => 'rg064345@gmail.com'];
        $cfa->save();

        $reason = IncubateeProvisioningService::describePendingIssue($cfa, null, [], []);
        $this->assertSame('not_created', $reason['issue']);
        $this->assertStringNotContainsString('email', strtolower((string) $reason['detail']));
        $this->assertStringContainsString('mobile', strtolower((string) $reason['detail']));
    }

    public function test_incubatee_cannot_change_profile_only_password(): void
    {
        [$cfa, $district] = $this->seedCfa('9876501234');
        $incubatee = User::factory()->create([
            'role' => 'incubatee',
            'name' => 'Priya',
            'phone' => '9876501234',
            'password' => '9876501234',
            'cfa_submission_id' => $cfa->id,
            'district_id' => $district->id,
            'is_active' => true,
        ]);

        $this->actingAs($incubatee)
            ->put(route('account.settings.profile.update'), [
                'name' => 'Hacked',
                'email' => 'x@example.com',
                'phone' => '9000000000',
            ])
            ->assertRedirect(route('account.settings.edit'));

        $this->assertSame('Priya', $incubatee->fresh()->name);
        $this->assertSame('9876501234', $incubatee->fresh()->phone);
    }

    public function test_convert_existing_switches_email_accounts_to_mobile_password(): void
    {
        [$cfa, $district] = $this->seedCfa('9876543210');
        User::factory()->create([
            'role' => 'incubatee',
            'name' => 'Priya',
            'email' => 'priya.old@example.com',
            'phone' => '9876543210',
            'password' => 'Muy@2026',
            'cfa_submission_id' => $cfa->id,
            'district_id' => $district->id,
            'is_active' => true,
        ]);

        $result = app(IncubateeProvisioningService::class)->convertExisting(false);
        $this->assertSame(1, $result['converted']);

        $this->post(route('login'), [
            'login' => '9876543210',
            'password' => '9876543210',
        ])->assertRedirect(route('incubatee.dashboard'));

        $this->post(route('logout'));
        $this->post(route('login'), [
            'login' => 'priya.old@example.com',
            'password' => '9876543210',
        ])->assertSessionHasErrors('login');
    }

    public function test_already_converted_phone_login_is_skipped(): void
    {
        [$cfa, $district] = $this->seedCfa('9123456780');
        User::factory()->create([
            'role' => 'incubatee',
            'phone' => '9123456780',
            'password' => '9123456780',
            'cfa_submission_id' => $cfa->id,
            'district_id' => $district->id,
            'is_active' => true,
        ]);

        $result = app(IncubateeProvisioningService::class)->convertExisting(false);
        $this->assertSame(0, $result['converted']);
        $this->assertSame(1, $result['skipped']);
    }

    /**
     * @return array{0: CfaSubmission, 1: District, 2: Hub}
     */
    private function seedCfa(string $phone): array
    {
        [$hub, $district] = $this->seedHub();
        $cfa = $this->makeCfa($district, 'Applicant', $phone);

        return [$cfa, $district, $hub];
    }

    /**
     * @return array{0: Hub, 1: District}
     */
    private function seedHub(): array
    {
        $hub = Hub::query()->create(['slug' => 'login-hub', 'name' => 'Hub', 'sort_order' => 1]);
        $district = District::query()->create([
            'hub_id' => $hub->id,
            'slug' => 'login-district',
            'name' => 'Login District',
            'sort_order' => 1,
        ]);

        return [$hub, $district];
    }

    private function makeCfa(District $district, string $name, string $phone): CfaSubmission
    {
        return CfaSubmission::query()->create([
            'district_id' => $district->id,
            'applicant_name' => $name,
            'phone' => $phone !== '' ? $phone : '',
            'application_no' => 'APP-'.uniqid(),
            'payload' => [],
        ]);
    }
}
