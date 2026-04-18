<?php

namespace App\Console\Commands;

use App\Models\CfaSubmission;
use App\Models\OnboardingBatch;
use App\Models\OnboardingBatchCfa;
use App\Models\User;
use App\Services\IncubateeLoginEmailResolver;
use Illuminate\Console\Command;

class ExportIncubateeLoginSheetCommand extends Command
{
    protected $signature = 'incubatees:export-login-sheet
                            {batch_id : onboarding_batches.id for the locked batch}
                            {--csv : Print CSV only (password hint on stderr)}';

    protected $description = 'List login email + applicant details for handouts (after or before provisioning)';

    public function handle(): int
    {
        $batchId = (int) $this->argument('batch_id');
        $batch = OnboardingBatch::query()->find($batchId);
        if ($batch === null) {
            $this->error("No onboarding batch with id {$batchId}.");

            return self::FAILURE;
        }

        $cfaIds = OnboardingBatchCfa::query()
            ->where('onboarding_batch_id', $batchId)
            ->orderBy('id')
            ->pluck('cfa_submission_id')
            ->all();

        if ($cfaIds === []) {
            $this->warn('This batch has no locked incubatees yet.');

            return self::SUCCESS;
        }

        $submissions = CfaSubmission::query()
            ->whereIn('id', $cfaIds)
            ->orderBy('id')
            ->get();

        $plain = (string) config('incubatee.default_password', '');
        $rows = [];
        foreach ($submissions as $submission) {
            $user = User::query()
                ->where('cfa_submission_id', $submission->id)
                ->where('role', 'incubatee')
                ->first();
            $loginEmail = $user?->email ?? IncubateeLoginEmailResolver::forSubmission($submission);
            $rows[] = [
                $submission->applicant_name ?: '—',
                (string) ($submission->phone ?? ''),
                $loginEmail,
                (string) ($submission->application_no ?? ''),
                (string) $submission->id,
                $user !== null ? 'yes' : 'no',
            ];
        }

        $headers = ['Name', 'Phone', 'Login email', 'Application no.', 'CFA id', 'Account created'];

        if ($this->option('csv')) {
            fwrite(STDERR, 'Initial password (same for all new accounts): '.$plain."\n");
            $out = fopen('php://output', 'w');
            fputcsv($out, $headers);
            foreach ($rows as $r) {
                fputcsv($out, $r);
            }
            fclose($out);

            return self::SUCCESS;
        }

        $this->info('Batch: '.$batch->name.' (id '.$batchId.')');
        $this->table($headers, $rows);
        $this->newLine();
        $this->comment('Initial password (INCUBATEE_DEFAULT_PASSWORD): '.$plain);
        $this->comment('Tip: php artisan incubatees:export-login-sheet '.$batchId.' --csv > incubatees.csv');

        return self::SUCCESS;
    }
}
