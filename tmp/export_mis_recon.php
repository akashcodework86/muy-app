<?php

use App\Services\Deliverables\ProgramDeliverablesFilter;
use App\Services\Deliverables\ProgramDeliverablesScope;
use App\Services\ProgramDeliverablesReportService;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$scope = new ProgramDeliverablesScope('state_admin', null, null, true);
$service = $app->make(ProgramDeliverablesReportService::class);
$periods = [
    'April 2026' => [4, 2026, null],
    'May 2026' => [5, 2026, null],
    'June 2026' => [6, 2026, null],
    'Q1 Apr-Jun 2026' => [null, null, 1],
];

$output = [
    'generated_at' => now()->toIso8601String(),
    'database' => config('database.connections.'.config('database.default').'.database'),
    'periods' => [],
];

foreach ($periods as $label => [$month, $year, $quarter]) {
    $filter = new ProgramDeliverablesFilter(
        fiscalYearId: 1,
        districtId: null,
        month: $month,
        year: $year,
        dateFrom: null,
        dateTo: null,
        quarter: $quarter,
    );
    $report = $service->build($filter, $scope, attachCompanionColumns: false);
    $rows = [];
    foreach ($report['rows'] as $row) {
        if (in_array($row['row_type'] ?? '', ['pillar', 'subcategory'], true)) {
            continue;
        }
        $rows[] = [
            'serial' => $row['serial'] ?? '',
            'name' => $row['name'] ?? '',
            'indicator_type' => $row['indicator_type'] ?? '',
            'level' => $row['level'] ?? '',
            'target' => $row['target'] ?? null,
            'achievement' => (int) ($row['achievement'] ?? 0),
        ];
    }
    $output['periods'][$label] = $rows;
}

$path = __DIR__.'/mis_apr_jun_2026.json';
file_put_contents($path, json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
echo "Wrote {$path}\n";
