<?php

namespace App\Console\Commands;

use App\Services\LegacyPhase2\LegacyStaffImportService;
use Illuminate\Console\Command;

class ImportLegacyStaffCommand extends Command
{
    protected $signature = 'import:legacy-staff
                            {--dry-run : List counts only; do not write to muy.users}
                            {--limit= : Max legacy users to process (debug)}';

    protected $description = 'Import rbiphase2 users (field roles) into Laravel district_staff; sets legacy_user_id';

    public function handle(LegacyStaffImportService $importer): int
    {
        $dry = (bool) $this->option('dry-run');
        $limitOpt = $this->option('limit');
        $limit = $limitOpt !== null && $limitOpt !== '' ? max(1, (int) $limitOpt) : null;

        $this->info('Legacy DB: '.config('database.connections.legacy.database').' @ '.config('database.connections.legacy.host'));
        $this->info('Roles: '.implode(', ', config('legacy_phase2.staff_import.roles', [])));
        $this->info($dry ? 'Dry-run (no writes).' : 'Writing to muy.users…');
        if ($limit !== null) {
            $this->warn("Limit: {$limit} rows.");
        }

        try {
            $stats = $importer->run($dry, $limit);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->table(
            ['Metric', 'Count'],
            [
                [$dry ? 'Would create' : 'Created', $stats['created']],
                [$dry ? 'Would update' : 'Updated', $stats['updated']],
                ['Skipped: empty email', $stats['skipped_no_email']],
                ['Skipped: invalid email', $stats['skipped_bad_email']],
                ['Skipped: district not resolved', $stats['skipped_no_district']],
                ['Skipped: email / legacy_user_id conflict', $stats['skipped_email_conflict']],
            ]
        );

        $this->printSamples('Empty email (samples)', $stats['samples_no_email']);
        $this->printSamples('Invalid email (samples)', $stats['samples_bad_email']);
        $this->printSamples('District not resolved (samples)', $stats['samples_no_district']);
        $this->printSamples('Email conflict (samples)', $stats['samples_email_conflict']);

        if ($dry) {
            $this->info('Dry-run complete. Run without --dry-run to import.');
        } else {
            $this->info('Import complete. New users: password is `'.config('legacy_phase2.staff_import.default_password').'` (change in production).');
        }

        return self::SUCCESS;
    }

    /**
     * @param  list<string>  $lines
     */
    private function printSamples(string $title, array $lines): void
    {
        if ($lines === []) {
            return;
        }
        $this->newLine();
        $this->warn($title);
        foreach ($lines as $line) {
            $this->line('  — '.$line);
        }
    }
}
