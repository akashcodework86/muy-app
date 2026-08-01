<?php

namespace App\Console\Commands;

use App\Services\Exports\Phase3331ShgMembersPackDataService;
use App\Services\Exports\Phase3331ShgMembersPackExcelExport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class ExportPhase3331ShgMembersPackCommand extends Command
{
    protected $signature = 'exports:phase3-3-3-1-shg-members-pack
                            {--path= : Absolute or storage-relative path for the xlsx}';

    protected $description = 'Build 3.3.1 Technical Trainings Excel for SHG Federation sessions (counts + details)';

    public function handle(Phase3331ShgMembersPackDataService $data, Phase3331ShgMembersPackExcelExport $excel): int
    {
        $this->info('Building 3.3.1 SHG members pack…');
        $pack = $data->build();

        $relative = 'exports/phase3-3_3_1-shg-members-'.now()->format('Ymd_His').'.xlsx';
        $custom = (string) $this->option('path');
        if ($custom !== '') {
            $absolute = str_starts_with($custom, '/')
                ? $custom
                : Storage::disk('public')->path($custom);
        } else {
            $absolute = Storage::disk('public')->path($relative);
        }

        $excel->writeToPath($pack, $absolute);

        $this->table(
            ['Section', 'Metric', 'Count'],
            collect($pack['summary_rows'] ?? [])
                ->take(12)
                ->map(fn ($r) => [$r['section'], $r['metric'], $r['count']])
                ->all()
        );

        $this->info('Sessions: '.count($pack['sessions'] ?? []));
        $this->info('Participant rows: '.count($pack['participants'] ?? []));
        $this->info('Wrote: '.$absolute);

        return self::SUCCESS;
    }
}
