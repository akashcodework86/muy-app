<?php

namespace App\Console\Commands;

use App\Models\ServiceCase;
use App\Support\ConvergenceReapSupport;
use App\Support\ConvergenceReapSupportDeliverablesSupport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SyncReapSupportCommand extends Command
{
    protected $signature = 'reap:sync {--dry-run : Report only; do not update data}';

    protected $description = 'Bootstrap REAP MIS 8.2 catalog flags and backfill through_reap for live parity';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $this->info('REAP MIS 8.2 live sync'.($dryRun ? ' (dry run)' : ''));

        if (! Schema::hasColumn('services', 'counts_toward_reap_support')) {
            $this->error('Missing column services.counts_toward_reap_support — run migrations first.');

            return self::FAILURE;
        }

        if (! Schema::hasColumn('service_cases', 'through_reap')) {
            $this->error('Missing column service_cases.through_reap — run migrations first.');

            return self::FAILURE;
        }

        $codes = ConvergenceReapSupport::knownReapSupportServiceCodes();
        $this->line('Known REAP service codes: '.implode(', ', $codes));

        $convergenceIds = ConvergenceReapSupportDeliverablesSupport::convergenceServiceIds();
        $reapIds = ConvergenceReapSupportDeliverablesSupport::reapSupportServiceIds();
        $this->line('Active convergence service IDs: '.(count($convergenceIds) > 0 ? implode(', ', $convergenceIds) : 'none'));
        $this->line('REAP-support service IDs: '.(count($reapIds) > 0 ? implode(', ', $reapIds) : 'none'));

        $flaggedServices = (int) DB::table('services')->where('counts_toward_reap_support', true)->count();
        $throughReapCases = (int) DB::table('service_cases')->where('through_reap', true)->count();
        $payloadReapCases = $this->countPayloadThroughReapCases();

        $this->table(['Metric', 'Count'], [
            ['Services with counts_toward_reap_support', (string) $flaggedServices],
            ['Cases with through_reap = 1', (string) $throughReapCases],
            ['Cases with payload through_reap flag', (string) $payloadReapCases],
        ]);

        if ($dryRun) {
            $this->comment('Dry run complete. Re-run without --dry-run to apply fixes.');

            return self::SUCCESS;
        }

        $servicesUpdated = ConvergenceReapSupport::bootstrapReapSupportServices();
        $casesUpdated = ConvergenceReapSupport::backfillThroughReapFlags();

        $this->info("Updated {$servicesUpdated} service(s) with counts_toward_reap_support.");
        $this->info("Updated {$casesUpdated} service case(s) with through_reap = 1.");

        $listingCount = ServiceCase::query()
            ->tap(fn ($q) => ConvergenceReapSupportDeliverablesSupport::applyListingScope($q, 'service_cases'))
            ->count();

        $this->info("Admin reap_support_8_2 filter would list {$listingCount} case(s) (all statuses).");

        return self::SUCCESS;
    }

    private function countPayloadThroughReapCases(): int
    {
        if (! Schema::hasTable('service_cases')) {
            return 0;
        }

        $driver = Schema::getConnection()->getDriverName();
        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            return 0;
        }

        return (int) DB::table('service_cases')
            ->whereRaw("LOWER(COALESCE(CAST(JSON_UNQUOTE(JSON_EXTRACT(payload, '$.\"through_reap\"')) AS CHAR), '')) IN ('1', 'true', 'yes', 'on')")
            ->count();
    }
}
