<?php

namespace App\Console\Commands;

use App\Models\Service;
use App\Models\ServiceCase;
use App\Models\ServiceCaseAttachment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Removes all rows from the services catalog table so state admins can rebuild
 * a fresh catalog from the UI.
 *
 * By default refuses to run if any service_cases exist (FK restrictOnDelete).
 * Pass --with-cases to delete cases first (attachments on disk + DB rows).
 */
class WipeServiceCatalogCommand extends Command
{
    protected $signature = 'catalog:wipe-services
                            {--force : Skip confirmation prompt}
                            {--with-cases : Delete all service cases (and attachment files) first}';

    protected $description = 'Delete every catalog service (optionally all service cases first)';

    public function handle(): int
    {
        $caseCount = ServiceCase::query()->count();

        if ($caseCount > 0 && ! $this->option('with-cases')) {
            $this->error("There are {$caseCount} service case(s). Delete them first, or re-run with --with-cases (deletes cases, events, attachments, and files).");

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm('This will remove ALL catalog services. Continue?', false)) {
            $this->warn('Aborted.');

            return self::FAILURE;
        }

        $deletedCases = 0;
        $deletedServices = 0;

        DB::transaction(function () use (&$deletedCases, &$deletedServices): void {
            if ($this->option('with-cases')) {
                $attachments = ServiceCaseAttachment::query()->get();
                foreach ($attachments as $attachment) {
                    $attachment->deleteFileIfLocal();
                }
                $deletedCases = ServiceCase::query()->count();
                ServiceCase::query()->delete();
            }

            $deletedServices = Service::query()->count();
            Service::query()->delete();
        });

        if ($deletedCases > 0) {
            $this->info("Deleted {$deletedCases} service case(s).");
        }
        $this->info("Deleted {$deletedServices} catalog service(s). Admin can add fresh services under Service catalog.");

        return self::SUCCESS;
    }
}
