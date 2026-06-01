<?php

namespace App\Console\Commands;

use App\Services\Deliverables\Exports\DeliverablesExcelSupport;
use Illuminate\Console\Command;

class CheckDeliverablesExportCommand extends Command
{
    protected $signature = 'deliverables:export-check';

    protected $description = 'Verify PhpSpreadsheet and Zip are available for deliverables .xlsx exports';

    public function handle(): int
    {
        $vendorPath = base_path('vendor/phpoffice/phpspreadsheet');
        $vendorExists = is_dir($vendorPath);

        $this->line('PhpSpreadsheet vendor path: '.($vendorExists ? 'found' : 'MISSING'));
        $this->line('Zip extension: '.(class_exists(\ZipArchive::class) ? 'enabled' : 'MISSING'));
        $this->line('PhpSpreadsheet class: '.(class_exists(\PhpOffice\PhpSpreadsheet\Spreadsheet::class) ? 'loadable' : 'NOT loadable'));

        $issue = DeliverablesExcelSupport::availabilityIssue();
        if ($issue === null) {
            $this->info('Deliverables .xlsx export is fully available.');

            return self::SUCCESS;
        }

        $this->warn('Deliverables .xlsx export is NOT available: '.$issue);
        $this->line('Exports will download CSV until you run: composer install --no-dev');
        $this->line('Working directory: '.base_path());

        return self::FAILURE;
    }
}
