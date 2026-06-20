<?php

namespace App\Services\Targets\Exports;

use App\Models\FiscalYear;
use App\Services\Deliverables\Exports\DeliverablesExcelSupport;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OfficialDistrictMonthlyTargetsCsvExport
{
    /**
     * @param  list<array<string, mixed>>  $blocks
     * @param  list<array<string, mixed>>  $stateOnlyRows
     * @param  array<int, string>  $monthLabels
     */
    public function download(
        array $blocks,
        array $stateOnlyRows,
        array $monthLabels,
        ?FiscalYear $fiscalYear,
        bool $hubOnlyPage,
    ): StreamedResponse {
        $prefix = $hubOnlyPage ? 'hub-targets' : 'district-targets';
        $fySlug = OfficialMonthlyTargetsExcelSupport::fySlug($fiscalYear?->name ?? 'FY');
        $fileName = $prefix.'-'.$fySlug.'-'.now()->format('Ymd-His').'.csv';
        $tz = config('app.timezone');
        $title = $hubOnlyPage
            ? 'Hub target distribution — saved targets'
            : 'District target month wise — saved targets';

        return response()->streamDownload(function () use ($blocks, $stateOnlyRows, $monthLabels, $fiscalYear, $hubOnlyPage, $title, $tz): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }

            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, [$title]);
            fputcsv($out, ['Fiscal year', $fiscalYear?->name ?? '—']);
            fputcsv($out, ['Export type', 'Saved monthly targets (database)']);
            fputcsv($out, ['Page', $hubOnlyPage ? 'Hub distribution' : 'District allocation']);
            fputcsv($out, ['Generated at', now()->timezone($tz)->format('d M Y, g:i A T')]);
            fputcsv($out, []);

            $tableHeaders = OfficialMonthlyTargetsExcelSupport::districtTableHeaders($monthLabels);

            foreach ($blocks as $block) {
                $this->writeBlock($out, $block, $tableHeaders);
                fputcsv($out, []);
            }

            if (! $hubOnlyPage && $stateOnlyRows !== []) {
                fputcsv($out, ['State-level monthly targets (no district split)']);
                $headers = ['S.N.', 'Indicator', 'Level'];
                foreach ($monthLabels as $label) {
                    $headers[] = $label;
                }
                $headers[] = 'Total';
                fputcsv($out, $headers);

                foreach ($stateOnlyRows as $row) {
                    $savedMonths = (array) ($row['saved_months'] ?? []);
                    $indicator = trim(($row['mis_serial'] ?? '').' — '.($row['name'] ?? ''), ' —');
                    $line = [
                        DeliverablesExcelSupport::sanitizeCell($row['excel_sn'] ?? ''),
                        DeliverablesExcelSupport::sanitizeCell($indicator),
                        DeliverablesExcelSupport::sanitizeCell($row['level'] ?? ''),
                    ];
                    for ($m = 1; $m <= 12; $m++) {
                        $line[] = (int) ($savedMonths[$m] ?? 0);
                    }
                    $line[] = (int) ($row['saved_total'] ?? 0);
                    fputcsv($out, $line);
                }
            }

            fclose($out);
        }, $fileName, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @param  resource  $out
     * @param  list<string>  $tableHeaders
     */
    private function writeBlock($out, array $block, array $tableHeaders): void
    {
        $serial = (string) ($block['mis_serial'] ?? '');
        $name = (string) ($block['name'] ?? '');
        $excelSn = (string) ($block['excel_sn'] ?? '');
        $title = trim(($excelSn !== '' ? $excelSn.'. ' : '').($serial !== '' ? $serial.' — ' : '').$name);
        $stateSavedTotal = (int) ($block['state_saved_total'] ?? 0);
        $verify = (array) ($block['verify_saved'] ?? []);
        $title .= ' | State target (saved): '.number_format($stateSavedTotal);
        if ($verify !== []) {
            $title .= ' | '.($verify['label'] ?? '');
        }

        fputcsv($out, [$title]);
        fputcsv($out, $tableHeaders);

        $colTotals = array_fill(1, 12, 0);
        $grandTotal = 0;

        foreach ((array) ($block['district_rows'] ?? []) as $dRow) {
            $savedMonths = (array) ($dRow['saved_months'] ?? []);
            $rowTotal = (int) ($dRow['saved_total'] ?? 0);
            $grandTotal += $rowTotal;

            $line = [DeliverablesExcelSupport::sanitizeCell($dRow['district']->name ?? '')];
            for ($m = 1; $m <= 12; $m++) {
                $val = (int) ($savedMonths[$m] ?? 0);
                $colTotals[$m] += $val;
                $line[] = $val;
            }
            $line[] = $rowTotal;
            fputcsv($out, $line);
        }

        $footer = ['District allocation'];
        for ($m = 1; $m <= 12; $m++) {
            $footer[] = $colTotals[$m];
        }
        $footer[] = $grandTotal;
        fputcsv($out, $footer);

        $stateSavedMonths = (array) ($block['state_saved_months'] ?? []);
        $stateLine = ['State target (saved)'];
        for ($m = 1; $m <= 12; $m++) {
            $stateLine[] = (int) ($stateSavedMonths[$m] ?? 0);
        }
        $stateLine[] = $stateSavedTotal;
        fputcsv($out, $stateLine);

        $hubRows = (array) ($block['hub_rows'] ?? []);
        if ($hubRows !== []) {
            fputcsv($out, ['Hub target distribution']);
            fputcsv($out, array_merge(['Hub'], array_slice($tableHeaders, 1)));

            foreach ($hubRows as $hRow) {
                $savedMonths = (array) ($hRow['saved_months'] ?? []);
                $line = [DeliverablesExcelSupport::sanitizeCell($hRow['hub']->name ?? '')];
                for ($m = 1; $m <= 12; $m++) {
                    $line[] = (int) ($savedMonths[$m] ?? 0);
                }
                $line[] = (int) ($hRow['saved_total'] ?? 0);
                fputcsv($out, $line);
            }
        }
    }
}
