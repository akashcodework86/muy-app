<?php

namespace App\Console\Commands;

use App\Services\Exports\Phase3ShgCboReapPackDataService;
use App\Services\Exports\Phase3ShgCboReapPackExcelExport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class ExportPhase3ShgCboReapPackCommand extends Command
{
    protected $signature = 'exports:phase3-shg-cbo-reap-pack
                            {--path= : Absolute or storage-relative path for the xlsx}';

    protected $description = 'Build Phase-3 SHG members / CBO Excel (counts + member service details)';

    public function handle(Phase3ShgCboReapPackDataService $data, Phase3ShgCboReapPackExcelExport $excel): int
    {
        $this->info('Building SHG members / CBO pack…');
        $pack = $data->build();

        $relative = 'exports/phase3-shg-cbo-services-'.now()->format('Ymd_His').'.xlsx';
        $custom = (string) $this->option('path');
        if ($custom !== '') {
            $absolute = str_starts_with($custom, '/')
                ? $custom
                : Storage::disk('public')->path($custom);
        } else {
            $absolute = Storage::disk('public')->path($relative);
        }

        $excel->writeToPath($pack, $absolute);

        foreach (['shg' => 'SHG members', 'cbo' => 'CBO', 'reap82' => '8.2 REAP'] as $key => $label) {
            $rows = collect($pack[$key]['summary_rows'] ?? [])
                ->filter(fn ($r) => ($r['metric'] ?? '') !== '—')
                ->take(12)
                ->map(fn ($r) => [$r['section'], $r['metric'], $r['count']])
                ->all();
            $this->info($label.' (top summary rows)');
            $this->table(['Section', 'Metric', 'Count'], $rows);
            $this->line('Detail rows: '.count($pack[$key]['details'] ?? []));
            $this->newLine();
        }

        $latest = Storage::disk('public')->path('exports/phase3-shg-cbo-reap-pack-latest.xlsx');
        @copy($absolute, $latest);

        $this->info('Wrote: '.$absolute);
        $this->info('Download: '.url('storage/exports/phase3-shg-cbo-reap-pack-latest.xlsx'));
        $this->info('Admin (live): '.url('/admin/exports/phase3-shg-cbo-reap-pack'));

        return self::SUCCESS;
    }
}
