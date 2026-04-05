<?php

namespace App\Console\Commands;

use App\Models\FiscalYear;
use App\Services\LegacyPhase2\LegacyRbiServicesAssignedAchievementService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ReportLegacyRbiServicesUnmappedCommand extends Command
{
    protected $signature = 'report:legacy-rbi-services-unmapped
                            {--fy-code=2024-25 : Fiscal year code (for assigned_date window)}';

    protected $description = 'List rbi_services_assigned (category, service_name) rows in FY that do not map to a deliverable';

    public function handle(LegacyRbiServicesAssignedAchievementService $resolver): int
    {
        if ((string) config('database.connections.legacy.database', '') === '') {
            $this->error('Set LEGACY_DB_DATABASE in .env');

            return self::FAILURE;
        }

        if (! Schema::connection('legacy')->hasTable('rbi_services_assigned')) {
            $this->error('Legacy table rbi_services_assigned not found.');

            return self::FAILURE;
        }

        $fy = FiscalYear::query()->where('code', (string) $this->option('fy-code'))->first();
        if (! $fy) {
            $this->error('Fiscal year not found.');

            return self::FAILURE;
        }

        $start = $fy->starts_on->toDateString();
        $end = $fy->ends_on->toDateString();

        $unmapped = [];

        DB::connection('legacy')
            ->table('rbi_services_assigned')
            ->select(['category', 'service_name', 'assigned_date', 'doc_date'])
            ->orderBy('category')
            ->orderBy('service_name')
            ->chunk(2000, function ($chunk) use ($resolver, $fy, &$unmapped): void {
                foreach ($chunk as $row) {
                    $at = $resolver->eventCarbon((string) ($row->doc_date ?? ''), $row->assigned_date ?? null);
                    if ($at === null || ! $resolver->carbonInFiscalYear($at, $fy)) {
                        continue;
                    }
                    if ($resolver->resolveDeliverableCode((string) $row->category, (string) $row->service_name) !== null) {
                        continue;
                    }
                    $k = (string) $row->category."\t".(string) $row->service_name;
                    $unmapped[$k] = ($unmapped[$k] ?? 0) + 1;
                }
            });

        arsort($unmapped);
        $this->info('FY '.$fy->code.' by doc_date (fallback assigned_date) '.$start.' … '.$end);
        $this->info('Unmapped distinct pairs: '.count($unmapped));

        $rows = [];
        $i = 0;
        foreach ($unmapped as $k => $cnt) {
            [$cat, $svc] = explode("\t", $k, 2);
            $rows[] = [$cat, $svc, $cnt];
            $i++;
            if ($i >= 40) {
                break;
            }
        }

        $this->table(['category', 'service_name', 'rows in FY'], $rows);

        return self::SUCCESS;
    }
}
