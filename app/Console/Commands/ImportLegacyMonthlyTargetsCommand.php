<?php

namespace App\Console\Commands;

use App\Services\LegacyPhase2\LegacyMonthlyTargetsImportService;
use Illuminate\Console\Command;

class ImportLegacyMonthlyTargetsCommand extends Command
{
    protected $signature = 'import:legacy-monthly-targets
                            {--fy-code=2025-26 : Fiscal year code in muy.fiscal_years (legacy Phase 2 data → FY 2025-26)}
                            {--dry-run : Count only; do not write to staff_monthly_targets}
                            {--unmapped-limit=20 : How many distinct unmapped activity types to print}';

    protected $description = 'Import rbiphase2.monthly_activity_targets into staff_monthly_targets (match users by email, district_staff only)';

    public function handle(LegacyMonthlyTargetsImportService $importer): int
    {
        $fyCode = (string) $this->option('fy-code');
        $dry = (bool) $this->option('dry-run');

        $this->info('Legacy DB: '.config('database.connections.legacy.database').' @ '.config('database.connections.legacy.host'));
        $this->info('Target FY code: '.$fyCode.($dry ? ' (dry-run)' : ''));

        try {
            $stats = $importer->run($fyCode, $dry);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->table(
            ['Metric', 'Count'],
            [
                ['Rows that would import / imported', $stats['imported']],
                ['Skipped: no Laravel district_staff user (email match)', $stats['skipped_no_user']],
                ['Skipped: district label mismatch (row vs Laravel user district)', $stats['skipped_district']],
                ['Skipped: unknown activity_type mapping', $stats['skipped_no_deliverable']],
                ['Skipped: bad month (not 1–12)', $stats['skipped_bad_month']],
            ]
        );

        if ($stats['unmapped_types'] !== []) {
            $this->warn('Unmapped activity_type values (add to config/legacy_phase2.php):');
            $limit = max(1, (int) $this->option('unmapped-limit'));
            $this->line(LegacyMonthlyTargetsImportService::formatUnmappedSummary($stats['unmapped_types'], $limit));
        }

        if ($dry) {
            $this->info('Dry-run complete. Run without --dry-run to write rows.');
        } else {
            $this->info('Import complete.');
        }

        return self::SUCCESS;
    }
}
