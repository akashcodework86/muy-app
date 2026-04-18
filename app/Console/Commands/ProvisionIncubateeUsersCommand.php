<?php

namespace App\Console\Commands;

use App\Models\CfaSubmission;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ProvisionIncubateeUsersCommand extends Command
{
    protected $signature = 'incubatees:provision-users {--dry-run : Show actions without writing}';

    protected $description = 'Create incubatee portal users (email + default password) for CFA submissions in a locked batch';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $plain = (string) config('incubatee.default_password', '');
        if ($plain === '') {
            $this->error('config incubatee.default_password is empty. Set INCUBATEE_DEFAULT_PASSWORD in .env');

            return self::FAILURE;
        }

        $query = CfaSubmission::query()
            ->whereHas('onboardingBatchMembership')
            ->with(['onboardingBatchMembership.batch']);

        $created = 0;
        $skipped = 0;

        $query->chunkById(100, function ($submissions) use ($dry, $plain, &$created, &$skipped) {
            foreach ($submissions as $submission) {
                $payload = is_array($submission->payload) ? $submission->payload : [];
                $emailRaw = $payload['email'] ?? null;
                if (! is_string($emailRaw) || trim($emailRaw) === '') {
                    $this->warn("Skip CFA #{$submission->id}: no email in payload.");
                    $skipped++;

                    continue;
                }
                $email = Str::lower(trim($emailRaw));

                $existing = User::query()->where('email', $email)->first();
                if ($existing !== null) {
                    if ($existing->role === 'incubatee' && (int) $existing->cfa_submission_id === (int) $submission->id) {
                        $skipped++;

                        continue;
                    }
                    $this->warn("Skip {$email}: user exists (role {$existing->role}).");

                    $skipped++;

                    continue;
                }

                if (User::query()->where('cfa_submission_id', $submission->id)->exists()) {
                    $skipped++;

                    continue;
                }

                $name = $submission->applicant_name ?: 'Incubatee';

                if ($dry) {
                    $this->line("[dry-run] Would create incubatee user {$email} for CFA submission {$submission->id}");
                    $created++;

                    continue;
                }

                User::query()->create([
                    'name' => $name,
                    'email' => $email,
                    'password' => Hash::make($plain),
                    'role' => 'incubatee',
                    'cfa_submission_id' => $submission->id,
                    'is_active' => true,
                    'hub_id' => null,
                    'district_id' => $submission->district_id,
                ]);
                $created++;
            }
        });

        $this->info("Done. Created: {$created}, skipped: {$skipped}.");
        if (! $dry) {
            $this->comment('Default password is set from INCUBATEE_DEFAULT_PASSWORD / config.');
        }

        return self::SUCCESS;
    }
}
