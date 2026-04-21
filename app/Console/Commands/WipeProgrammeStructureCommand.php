<?php

namespace App\Console\Commands;

use App\Models\Deliverable;
use App\Models\Service;
use App\Models\ServiceCase;
use App\Models\ServiceCaseAttachment;
use App\Models\ServiceCategory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Clears programme structure so everything can be recreated from admin:
 * service cases (+ attachment files), catalog services, categories, MIS deliverables,
 * and all state/district/staff targets linked to deliverables (cascade).
 *
 * Does not touch users, CFA submissions, fiscal years, districts, or legacy DB.
 */
class WipeProgrammeStructureCommand extends Command
{
    protected $signature = 'programme:wipe-structure
                            {--force : Skip confirmation prompt}
                            {--wipe-app-settings : Also delete all rows from app_settings}';

    protected $description = 'Remove deliverables, targets, service catalog, categories, and service cases for a fresh rebuild';

    public function handle(): int
    {
        if (! $this->option('force') && ! $this->confirm(
            'This deletes ALL MIS deliverables, all targets, the full service catalog (categories + services), and all service cases. Continue?',
            false
        )) {
            $this->warn('Aborted.');

            return self::FAILURE;
        }

        $counts = [
            'attachments' => 0,
            'cases' => 0,
            'services' => 0,
            'categories' => 0,
            'deliverables' => 0,
            'app_settings' => 0,
        ];

        DB::transaction(function () use (&$counts): void {
            foreach (ServiceCaseAttachment::query()->get() as $attachment) {
                $attachment->deleteFileIfLocal();
                $attachment->delete();
                $counts['attachments']++;
            }

            $counts['cases'] = ServiceCase::query()->count();
            ServiceCase::query()->delete();

            $counts['services'] = Service::query()->count();
            Service::query()->delete();

            $counts['categories'] = ServiceCategory::query()->count();
            $guard = 0;
            while (ServiceCategory::query()->exists()) {
                $deleted = ServiceCategory::query()
                    ->whereDoesntHave('children')
                    ->delete();
                if ($deleted === 0) {
                    throw new \RuntimeException('Could not delete service categories (check parent_id integrity).');
                }
                $guard++;
                if ($guard > 500) {
                    throw new \RuntimeException('Service category delete loop exceeded safety limit.');
                }
            }

            $counts['deliverables'] = Deliverable::query()->count();
            Deliverable::query()->delete();

            if ($this->option('wipe-app-settings') && Schema::hasTable('app_settings')) {
                $counts['app_settings'] = DB::table('app_settings')->count();
                DB::table('app_settings')->delete();
            }
        });

        $this->info('Wiped programme structure.');
        $this->table(
            ['Item', 'Removed'],
            [
                ['Attachment files + rows', (string) $counts['attachments']],
                ['Service cases', (string) $counts['cases']],
                ['Services', (string) $counts['services']],
                ['Service categories', (string) $counts['categories']],
                ['Deliverables (+ cascaded state/district/staff targets)', (string) $counts['deliverables']],
                ['app_settings', $this->option('wipe-app-settings') ? (string) $counts['app_settings'] : 'kept'],
            ]
        );

        return self::SUCCESS;
    }
}
