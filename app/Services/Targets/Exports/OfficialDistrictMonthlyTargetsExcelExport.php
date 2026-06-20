<?php

namespace App\Services\Targets\Exports;

use App\Models\FiscalYear;
use App\Services\Deliverables\Exports\DeliverablesExcelSupport;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OfficialDistrictMonthlyTargetsExcelExport
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
        DeliverablesExcelSupport::ensureAvailable();

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle($hubOnlyPage ? 'Hub targets' : 'District targets');

        $lastCol = OfficialMonthlyTargetsExcelSupport::columnLetter(13);
        $title = $hubOnlyPage
            ? 'Hub target distribution — saved targets'
            : 'District target month wise — saved targets';

        $sheet->setCellValue('A1', $title);
        $sheet->mergeCells('A1:'.$lastCol.'1');
        OfficialMonthlyTargetsExcelSupport::applyRowFill($sheet, 'A1:'.$lastCol.'1', OfficialMonthlyTargetsExcelSupport::HEADER_FILL, true);
        $sheet->getStyle('A1')->getFont()->getColor()->setRGB('FFFFFF');
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $tz = config('app.timezone');
        $metaEnd = DeliverablesExcelSupport::writeMetaBlock($sheet, 3, [
            ['Fiscal year', $fiscalYear?->name ?? '—'],
            ['Export type', 'Saved monthly targets (database)'],
            ['Page', $hubOnlyPage ? 'Hub distribution' : 'District allocation'],
            ['Generated at', now()->timezone($tz)->format('d M Y, g:i A T')],
        ]);

        $rowNum = $metaEnd + 1;
        $tableHeaders = OfficialMonthlyTargetsExcelSupport::districtTableHeaders($monthLabels);

        foreach ($blocks as $block) {
            $rowNum = $this->writeBlock($sheet, $block, $tableHeaders, $lastCol, $rowNum);
            $rowNum++;
        }

        if (! $hubOnlyPage && $stateOnlyRows !== []) {
            $rowNum = $this->writeStateOnlySection($sheet, $stateOnlyRows, $monthLabels, $lastCol, $rowNum);
        }

        $sheet->getColumnDimension('A')->setWidth(22);
        for ($m = 1; $m <= 12; $m++) {
            $sheet->getColumnDimension(OfficialMonthlyTargetsExcelSupport::columnLetter($m))->setWidth(9);
        }
        $sheet->getColumnDimension($lastCol)->setWidth(10);

        $prefix = $hubOnlyPage ? 'hub-targets' : 'district-targets';
        $fySlug = OfficialMonthlyTargetsExcelSupport::fySlug($fiscalYear?->name ?? 'FY');

        return DeliverablesExcelSupport::streamDownload(
            $spreadsheet,
            $prefix.'-'.$fySlug.'-'.now()->format('Ymd-His').'.xlsx',
        );
    }

    /**
     * @param  list<string>  $tableHeaders
     */
    private function writeBlock(
        \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet,
        array $block,
        array $tableHeaders,
        string $lastCol,
        int $rowNum,
    ): int {
        $serial = (string) ($block['mis_serial'] ?? '');
        $name = (string) ($block['name'] ?? '');
        $excelSn = (string) ($block['excel_sn'] ?? '');
        $title = trim(($excelSn !== '' ? $excelSn.'. ' : '').($serial !== '' ? $serial.' — ' : '').$name);
        $stateSavedTotal = (int) ($block['state_saved_total'] ?? 0);
        $verify = (array) ($block['verify_saved'] ?? []);
        $title .= ' | State target (saved): '.number_format($stateSavedTotal);
        if ($verify !== []) {
            $title .= ' | '.$verify['label'];
        }

        OfficialMonthlyTargetsExcelSupport::writeBlockTitle($sheet, $rowNum, $title, $lastCol);
        $rowNum++;

        OfficialMonthlyTargetsExcelSupport::writeHeaderRow(
            $sheet,
            $rowNum,
            $tableHeaders,
            OfficialMonthlyTargetsExcelSupport::MONTH_HEADER_FILL,
            false,
        );
        $rowNum++;

        $districtRows = (array) ($block['district_rows'] ?? []);
        $colTotals = array_fill(1, 12, 0);
        $grandTotal = 0;

        foreach ($districtRows as $dRow) {
            $savedMonths = (array) ($dRow['saved_months'] ?? []);
            $rowTotal = (int) ($dRow['saved_total'] ?? 0);
            $grandTotal += $rowTotal;

            $sheet->setCellValue('A'.$rowNum, OfficialMonthlyTargetsExcelSupport::sanitize($dRow['district']->name ?? ''));
            for ($m = 1; $m <= 12; $m++) {
                $val = (int) ($savedMonths[$m] ?? 0);
                $colTotals[$m] += $val;
                OfficialMonthlyTargetsExcelSupport::setNumericCell(
                    $sheet,
                    OfficialMonthlyTargetsExcelSupport::columnLetter($m).$rowNum,
                    $val,
                );
            }
            OfficialMonthlyTargetsExcelSupport::setNumericCell($sheet, $lastCol.$rowNum, $rowTotal);
            OfficialMonthlyTargetsExcelSupport::applyDataBorders($sheet, 'A'.$rowNum.':'.$lastCol.$rowNum);
            $rowNum++;
        }

        $sheet->setCellValue('A'.$rowNum, 'District allocation');
        for ($m = 1; $m <= 12; $m++) {
            OfficialMonthlyTargetsExcelSupport::setNumericCell(
                $sheet,
                OfficialMonthlyTargetsExcelSupport::columnLetter($m).$rowNum,
                $colTotals[$m],
            );
        }
        OfficialMonthlyTargetsExcelSupport::setNumericCell($sheet, $lastCol.$rowNum, $grandTotal);
        OfficialMonthlyTargetsExcelSupport::applyRowFill($sheet, 'A'.$rowNum.':'.$lastCol.$rowNum, OfficialMonthlyTargetsExcelSupport::FOOTER_FILL, true);
        $rowNum++;

        $stateSavedMonths = (array) ($block['state_saved_months'] ?? []);
        $sheet->setCellValue('A'.$rowNum, 'State target (saved)');
        for ($m = 1; $m <= 12; $m++) {
            OfficialMonthlyTargetsExcelSupport::setNumericCell(
                $sheet,
                OfficialMonthlyTargetsExcelSupport::columnLetter($m).$rowNum,
                (int) ($stateSavedMonths[$m] ?? 0),
            );
        }
        OfficialMonthlyTargetsExcelSupport::setNumericCell($sheet, $lastCol.$rowNum, $stateSavedTotal);
        OfficialMonthlyTargetsExcelSupport::applyRowFill($sheet, 'A'.$rowNum.':'.$lastCol.$rowNum, OfficialMonthlyTargetsExcelSupport::STATE_HEADER_FILL, true);
        $rowNum++;

        $hubRows = (array) ($block['hub_rows'] ?? []);
        if ($hubRows !== []) {
            $rowNum++;
            OfficialMonthlyTargetsExcelSupport::writeSectionTitle(
                $sheet,
                $rowNum,
                'Hub target distribution',
                $lastCol,
                OfficialMonthlyTargetsExcelSupport::STATE_HEADER_FILL,
            );
            $rowNum++;

            OfficialMonthlyTargetsExcelSupport::writeHeaderRow(
                $sheet,
                $rowNum,
                array_merge(['Hub'], array_slice($tableHeaders, 1)),
                OfficialMonthlyTargetsExcelSupport::STATE_HEADER_FILL,
                false,
            );
            $rowNum++;

            foreach ($hubRows as $hRow) {
                $savedMonths = (array) ($hRow['saved_months'] ?? []);
                $rowTotal = (int) ($hRow['saved_total'] ?? 0);
                $sheet->setCellValue('A'.$rowNum, OfficialMonthlyTargetsExcelSupport::sanitize($hRow['hub']->name ?? ''));
                for ($m = 1; $m <= 12; $m++) {
                    OfficialMonthlyTargetsExcelSupport::setNumericCell(
                        $sheet,
                        OfficialMonthlyTargetsExcelSupport::columnLetter($m).$rowNum,
                        (int) ($savedMonths[$m] ?? 0),
                    );
                }
                OfficialMonthlyTargetsExcelSupport::setNumericCell($sheet, $lastCol.$rowNum, $rowTotal);
                OfficialMonthlyTargetsExcelSupport::applyDataBorders($sheet, 'A'.$rowNum.':'.$lastCol.$rowNum);
                $rowNum++;
            }
        }

        return $rowNum;
    }

    /**
     * @param  list<array<string, mixed>>  $stateOnlyRows
     * @param  array<int, string>  $monthLabels
     */
    private function writeStateOnlySection(
        \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet,
        array $stateOnlyRows,
        array $monthLabels,
        string $lastCol,
        int $rowNum,
    ): int {
        $rowNum++;
        OfficialMonthlyTargetsExcelSupport::writeSectionTitle(
            $sheet,
            $rowNum,
            'State-level monthly targets (no district split)',
            $lastCol,
        );
        $rowNum++;

        $headers = ['S.N.', 'Indicator', 'Level'];
        foreach ($monthLabels as $label) {
            $headers[] = $label;
        }
        $headers[] = 'Total';

        $sectionLastCol = OfficialMonthlyTargetsExcelSupport::columnLetter(max(count($headers) - 1, 0));
        OfficialMonthlyTargetsExcelSupport::writeHeaderRow(
            $sheet,
            $rowNum,
            $headers,
            OfficialMonthlyTargetsExcelSupport::STATE_HEADER_FILL,
            false,
        );
        $rowNum++;

        foreach ($stateOnlyRows as $row) {
            $savedMonths = (array) ($row['saved_months'] ?? []);
            $savedTotal = (int) ($row['saved_total'] ?? 0);
            $indicator = trim(($row['mis_serial'] ?? '').' — '.($row['name'] ?? ''), ' —');

            $sheet->setCellValue('A'.$rowNum, OfficialMonthlyTargetsExcelSupport::sanitize($row['excel_sn'] ?? ''));
            $sheet->setCellValue('B'.$rowNum, OfficialMonthlyTargetsExcelSupport::sanitize($indicator));
            $sheet->setCellValue('C'.$rowNum, OfficialMonthlyTargetsExcelSupport::sanitize($row['level'] ?? ''));

            for ($m = 1; $m <= 12; $m++) {
                OfficialMonthlyTargetsExcelSupport::setNumericCell(
                    $sheet,
                    OfficialMonthlyTargetsExcelSupport::columnLetter(2 + $m).$rowNum,
                    (int) ($savedMonths[$m] ?? 0),
                );
            }
            OfficialMonthlyTargetsExcelSupport::setNumericCell(
                $sheet,
                OfficialMonthlyTargetsExcelSupport::columnLetter(15).$rowNum,
                $savedTotal,
            );

            if (! ($row['mapped'] ?? true)) {
                OfficialMonthlyTargetsExcelSupport::applyRowFill(
                    $sheet,
                    'A'.$rowNum.':'.$sectionLastCol.$rowNum,
                    OfficialMonthlyTargetsExcelSupport::UNMAPPED_FILL,
                );
            }

            OfficialMonthlyTargetsExcelSupport::applyDataBorders($sheet, 'A'.$rowNum.':'.$sectionLastCol.$rowNum);
            $rowNum++;
        }

        return $rowNum;
    }
}
