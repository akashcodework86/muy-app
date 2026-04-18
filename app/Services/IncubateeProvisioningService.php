<?php

namespace App\Services;

use App\Models\CfaSubmission;
use App\Models\OnboardingBatch;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class IncubateeProvisioningService
{
    /**
     * Create incubatee users for CFA submissions in (optional) locked batch membership.
     *
     * @return array{created: int, skipped: int, placeholder_email_rows: int, dry_run: bool}
     */
    public function provision(?int $onboardingBatchId, bool $dryRun): array
    {
        $plain = (string) config('incubatee.default_password', '');
        if ($plain === '') {
            throw new \RuntimeException('INCUBATEE_DEFAULT_PASSWORD / config incubatee.default_password is empty.');
        }

        if ($onboardingBatchId !== null && ! OnboardingBatch::query()->whereKey($onboardingBatchId)->exists()) {
            throw new \InvalidArgumentException('No onboarding batch with id '.$onboardingBatchId.'.');
        }

        $query = CfaSubmission::query()
            ->whereHas('onboardingBatchMembership', function ($q) use ($onboardingBatchId): void {
                if ($onboardingBatchId !== null) {
                    $q->where('onboarding_batch_id', $onboardingBatchId);
                }
            });

        $created = 0;
        $skipped = 0;
        $placeholderEmailRows = 0;

        $query->chunkById(100, function ($submissions) use ($dryRun, $plain, &$created, &$skipped, &$placeholderEmailRows): void {
            foreach ($submissions as $submission) {
                $email = IncubateeLoginEmailResolver::forSubmission($submission);
                $payload = is_array($submission->payload) ? $submission->payload : [];
                $hadRealEmail = is_string($payload['email'] ?? null) && trim((string) $payload['email']) !== '';
                if (! $hadRealEmail) {
                    $placeholderEmailRows++;
                }

                $existing = User::query()->where('email', $email)->first();
                if ($existing !== null) {
                    if ($existing->role === 'incubatee' && (int) $existing->cfa_submission_id === (int) $submission->id) {
                        $skipped++;

                        continue;
                    }
                    $skipped++;

                    continue;
                }

                if (User::query()->where('cfa_submission_id', $submission->id)->exists()) {
                    $skipped++;

                    continue;
                }

                if ($dryRun) {
                    $created++;

                    continue;
                }

                User::query()->create([
                    'name' => $submission->applicant_name ?: 'Incubatee',
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

        return [
            'created' => $created,
            'skipped' => $skipped,
            'placeholder_email_rows' => $placeholderEmailRows,
            'dry_run' => $dryRun,
        ];
    }
}
