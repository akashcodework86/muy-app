<?php

namespace App\Services\Targets\Exports;

use App\Models\FiscalYear;
use App\Services\Deliverables\Exports\DeliverablesExcelSupport;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OfficialStateMonthlyTargetsCsvExport
{
    /**
     * UTF-8 CSV with BOM — opens in Excel when PhpSpreadsheet is unavailable.
     *
     * @param  list<array<string, mixed>>  $grid
     * @param  array<int|string, int>  $columnTotals
     * @param  array<int, string>  $monthLabels
     */
    public function download(
        array $grid,
        array $columnTotals,
        array $monthLabels,
        ?FiscalYear $fiscalYear,
    ): StreamedResponse {
        $fySlug = OfficialMonthlyTargetsExcelSupport::fySlug($fiscalYear?->name ?? 'FY');
        $fileName = 'state-targets-'.$fySlug.'-'.now()->format('Ymd-His').'.csv';
        $tz = config('app.timezone');

        return response()->streamDownload(function () use ($grid, $columnTotals, $monthLabels, $fiscalYear, $tz): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }

            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['State target month wise — saved targets']);
            fputcsv($out, ['Fiscal year', $fiscalYear?->name ?? '—']);
            fputcsv($out, ['Export type', 'Saved monthly targets (database)']);
            fputcsv($out, ['Generated at', now()->timezone($tz)->format('d M Y, g:i A T')]);
            fputcsv($out, []);

            $headers = ['S.N.', 'Indicator', 'Official total', 'Type'];
            foreach ($monthLabels as $label) {
                $headers[] = $label;
            }
            $headers = array_merge($headers, ['Row total', 'Saved', 'District allocated', 'Alignment']);
            fputcsv($out, $headers);

            $savedColTotals = array_fill(1, 12, 0);
            $grandSaved = 0;

            foreach ($grid as $row) {
                $rowType = (string) ($row['row_type'] ?? '');

                if (in_array($rowType, ['category', 'subcategory'], true)) {
                    fputcsv($out, [
                        DeliverablesExcelSupport::sanitizeCell($row['serial'] ?? ''),
                        DeliverablesExcelSupport::sanitizeCell($row['name'] ?? ''),
                    ]);

                    continue;
                }

                if ($rowType !== 'leaf') {
                    continue;
                }

                $savedMonths = (array) ($row['saved_months'] ?? []);
                $savedTotal = (int) ($row['saved_total'] ?? 0);
                $grandSaved += $savedTotal;
                $indicator = trim(($row['serial'] ?? '').' — '.($row['name'] ?? ''), ' —');

                $line = [
                    DeliverablesExcelSupport::sanitizeCell($row['sn'] ?? ''),
                    DeliverablesExcelSupport::sanitizeCell($indicator),
                    (int) ($row['official_total'] ?? 0),
                    DeliverablesExcelSupport::sanitizeCell($row['indicator_type'] ?? ''),
                ];

                for ($m = 1; $m <= 12; $m++) {
                    $val = (int) ($savedMonths[$m] ?? 0);
                    $savedColTotals[$m] += $val;
                    $line[] = $val;
                }

                $line[] = $savedTotal;
                $line[] = $savedTotal;

                if ((bool) ($row['has_district_split'] ?? false)) {
                    $line[] = (int) ($row['district_allocated_total'] ?? 0);
                    $verify = (array) ($row['verify_district'] ?? []);
                    $line[] = DeliverablesExcelSupport::sanitizeCell($verify['label'] ?? '—');
                } else {
                    $line[] = 'N/A';
                    $line[] = '—';
                }

                fputcsv($out, $line);
            }

            $footer = ['Column totals', '', '', ''];
            for ($m = 1; $m <= 12; $m++) {
                $footer[] = $savedColTotals[$m];
            }
            $footer[] = $grandSaved;
            $footer[] = (int) ($columnTotals['grand_saved'] ?? $grandSaved);
            $footer[] = '';
            $footer[] = '';
            fputcsv($out, $footer);

            fclose($out);
        }, $fileName, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
