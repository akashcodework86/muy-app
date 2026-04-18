<?php

namespace App\Console\Commands;

use App\Services\IncubateeProvisioningService;
use Illuminate\Console\Command;

class ProvisionIncubateeUsersCommand extends Command
{
    protected $signature = 'incubatees:provision-users
                            {--batch-id= : Only CFAs in this onboarding_batches.id (locked member list)}
                            {--dry-run : Show actions without writing}';

    protected $description = 'Create incubatee portal users (login email + default password) for CFA submissions in a locked batch';

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

        $this->info("Done. Created: {$result['created']}, skipped: {$result['skipped']}.");
        if ($result['placeholder_email_rows'] > 0) {
            $this->comment("Placeholder login email (no CFA email): {$result['placeholder_email_rows']} row(s). Use -v to list each.");
        }
        if (! $dry) {
            $this->comment('Default password is set from INCUBATEE_DEFAULT_PASSWORD / config.');
        }

        return self::SUCCESS;
    }
}
