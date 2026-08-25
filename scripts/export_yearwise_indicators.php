<?php

/**
 * Year-wise CFA / onboarding / Udyam / FSSAI / GST / market linkage / convergence.
 *
 * Usage: php scripts/export_yearwise_indicators.php
 */

use App\Services\Exports\YearwiseIndicatorExcelExport;
use App\Services\Exports\YearwiseIndicatorWorkbookService;

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

ini_set('memory_limit', '1024M');
set_time_limit(0);

$service = app(YearwiseIndicatorWorkbookService::class);
$excel = app(YearwiseIndicatorExcelExport::class);

fwrite(STDERR, "Building year-wise indicator payload...\n");
$payload = $service->buildExportPayload(null, null);

$outDir = public_path('exports');
if (! is_dir($outDir)) {
    mkdir($outDir, 0755, true);
}
$filename = 'MUY_Yearwise_Indicators_2020-21_to_2026-27.xlsx';
$outPath = $outDir.'/'.$filename;

fwrite(STDERR, "Writing Excel ({$outPath})...\n");
$excel->writeToPath($payload, $outPath);

$bytes = filesize($outPath) ?: 0;
$totals = $payload['totals'] ?? [];
fwrite(STDERR, 'Done. '.number_format($bytes).' bytes. CFA='.($totals['cfa'] ?? 0).' onboard='.($totals['onboarding'] ?? 0).PHP_EOL);
echo $outPath.PHP_EOL;
