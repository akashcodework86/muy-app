<?php

namespace App\Console\Commands;

use App\Services\IncubateeProvisioningService;
use Illuminate\Console\Command;

class ProvisionIncubateeUsersCommand extends Command
{
    protected $signature = 'incubatees:provision-users
                            {--batch-id= : Only CFAs in this onboarding_batches.id (locked member list)}
                            {--dry-run : Show actions without writing}';

    protected $description = 'Create incubatee portal users (10-digit mobile login; initial password = mobile)';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $batchIdOpt = $this->option('batch-id');
        $batchId = ($batchIdOpt !== null && $batchIdOpt !== '') ? (int) $batchIdOpt : null;

        try {
            $result = app(IncubateeProvisioningService::class)->provision($batchId, $dry);
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        } catch (\InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Done. Created: {$result['created']}, converted: {$result['converted']}, skipped: {$result['skipped']}.");
        if ($result['missing_phones'] !== []) {
            $this->warn('No 10-digit mobile (enter on hub batch screen): '.count($result['missing_phones']).' row(s).');
        }
        if ($result['duplicate_phones'] !== []) {
            $this->warn('Mobile already used (enter a new number on hub batch screen): '.count($result['duplicate_phones']).' row(s).');
        }
        if (! $dry) {
            $this->comment('Login ID is the 10-digit mobile. Initial password is the same number until they change it.');
        }

        return self::SUCCESS;
    }
}
