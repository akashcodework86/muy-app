<?php

namespace App\Console\Commands;

use App\Services\GramPanchayatCsvImporter;
use Illuminate\Console\Command;

class ImportGramPanchayatsCommand extends Command
{
    protected $signature = 'gram-panchayats:import
                            {path : Path to CSV (Sr.No, State/UT, District, Block, Gram Panchayat)}';

    protected $description = 'Import gram panchayats from a flat CSV master file';

    public function handle(GramPanchayatCsvImporter $importer): int
    {
        $result = $importer->importFromPath((string) $this->argument('path'));

        if (! $result['success']) {
            $this->error((string) $result['error']);

            return self::FAILURE;
        }

        $this->info("Import complete. Inserted: {$result['inserted']}, updated: {$result['updated']}, skipped: {$result['skipped']}.");

        if ($result['unmatched_districts'] !== []) {
            $this->warn('Unmatched districts ('.count($result['unmatched_districts']).'):');
            foreach ($result['unmatched_districts'] as $name) {
                $this->line('  - '.$name);
            }
        }

        if ($result['unmatched_blocks'] !== []) {
            $this->warn('Unmatched blocks ('.count($result['unmatched_blocks']).'):');
            foreach ($result['unmatched_blocks'] as $name) {
                $this->line('  - '.$name);
            }
        }

        return self::SUCCESS;
    }
}
