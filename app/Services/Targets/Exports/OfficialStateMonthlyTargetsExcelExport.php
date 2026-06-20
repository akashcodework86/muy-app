<?php

namespace App\Services\Targets\Exports;

use App\Models\FiscalYear;
use App\Services\Deliverables\Exports\DeliverablesExcelSupport;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OfficialStateMonthlyTargetsExcelExport
{
    public function __construct(
        private readonly OfficialStateMonthlyTargetsCsvExport $csvExport,
    ) {}

    /**
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
        if (! DeliverablesExcelSupport::isAvailable()) {
            Log::warning('State targets export: using CSV fallback', [
                'reason' => DeliverablesExcelSupport::availabilityIssue(),
            ]);

            return $this->csvExport->download($grid, $columnTotals, $monthLabels, $fiscalYear);
        }

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('State targets');

        $sheet->setCellValue('A1', 'State target month wise — saved targets');
        $lastDataCol = OfficialMonthlyTargetsExcelSupport::columnLetter(19);
        $sheet->mergeCells('A1:'.$lastDataCol.'1');
        OfficialMonthlyTargetsExcelSupport::applyRowFill($sheet, 'A1:'.$lastDataCol.'1', OfficialMonthlyTargetsExcelSupport::HEADER_FILL, true);
        $sheet->getStyle('A1')->getFont()->getColor()->setRGB('FFFFFF');
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $tz = config('app.timezone');
        $metaEnd = DeliverablesExcelSupport::writeMetaBlock($sheet, 3, [
            ['Fiscal year', $fiscalYear?->name ?? '—'],
            ['Export type', 'Saved monthly targets (database)'],
            ['Generated at', now()->timezone($tz)->format('d M Y, g:i A T')],
        ]);

        $headerRow = $metaEnd + 1;
        $headers = [
            'S.N.',
            'Indicator',
            'Official total',
            'Type',
        ];
        foreach ($monthLabels as $label) {
            $headers[] = $label;
        }
        $headers = array_merge($headers, ['Row total', 'Saved', 'District allocated', 'Alignment']);

        OfficialMonthlyTargetsExcelSupport::writeHeaderRow($sheet, $headerRow, $headers);

        $monthStartCol = 3;
        for ($m = 1; $m <= 12; $m++) {
            $col = OfficialMonthlyTargetsExcelSupport::columnLetter($monthStartCol + $m);
            $sheet->getStyle($col.$headerRow)->getFill()
                ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setRGB(OfficialMonthlyTargetsExcelSupport::MONTH_HEADER_FILL);
        }
        $sheet->getStyle(OfficialMonthlyTargetsExcelSupport::columnLetter(16).$headerRow)->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setRGB(OfficialMonthlyTargetsExcelSupport::MONTH_HEADER_FILL);
        $sheet->getStyle(OfficialMonthlyTargetsExcelSupport::columnLetter(17).$headerRow)->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setRGB(OfficialMonthlyTargetsExcelSupport::SAVED_HEADER_FILL);
        foreach ([18, 19] as $idx) {
            $sheet->getStyle(OfficialMonthlyTargetsExcelSupport::columnLetter($idx).$headerRow)->getFill()
                ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setRGB(OfficialMonthlyTargetsExcelSupport::STATE_HEADER_FILL);
        }

        $rowNum = $headerRow + 1;
        $dataStart = $rowNum;
        $savedColTotals = array_fill(1, 12, 0);
        $grandSaved = 0;

        foreach ($grid as $row) {
            $rowType = (string) ($row['row_type'] ?? '');

            if (in_array($rowType, ['category', 'subcategory'], true)) {
                $sheet->setCellValue('A'.$rowNum, OfficialMonthlyTargetsExcelSupport::sanitize($row['serial'] ?? ''));
                $sheet->setCellValue('B'.$rowNum, OfficialMonthlyTargetsExcelSupport::sanitize($row['name'] ?? ''));
                $sheet->mergeCells('B'.$rowNum.':'.$lastDataCol.$rowNum);
                OfficialMonthlyTargetsExcelSupport::applyHeadingRow($sheet, 'A'.$rowNum.':'.$lastDataCol.$rowNum);
                $rowNum++;

                continue;
            }

            if ($rowType !== 'leaf') {
                continue;
            }

            $mapped = (bool) ($row['mapped'] ?? false);
            $savedMonths = (array) ($row['saved_months'] ?? []);
            $savedTotal = (int) ($row['saved_total'] ?? 0);
            $grandSaved += $savedTotal;

            $sheet->setCellValue('A'.$rowNum, OfficialMonthlyTargetsExcelSupport::sanitize($row['sn'] ?? ''));
            $indicator = trim(($row['serial'] ?? '').' — '.($row['name'] ?? ''), ' —');
            $sheet->setCellValue('B'.$rowNum, OfficialMonthlyTargetsExcelSupport::sanitize($indicator));
            OfficialMonthlyTargetsExcelSupport::setNumericCell($sheet, 'C'.$rowNum, (int) ($row['official_total'] ?? 0));
            $sheet->setCellValue('D'.$rowNum, OfficialMonthlyTargetsExcelSupport::sanitize($row['indicator_type'] ?? ''));

            for ($m = 1; $m <= 12; $m++) {
                $val = (int) ($savedMonths[$m] ?? 0);
                $savedColTotals[$m] += $val;
                $col = OfficialMonthlyTargetsExcelSupport::columnLetter($monthStartCol + $m);
                OfficialMonthlyTargetsExcelSupport::setNumericCell($sheet, $col.$rowNum, $val);
            }

            OfficialMonthlyTargetsExcelSupport::setNumericCell($sheet, 'Q'.$rowNum, $savedTotal);
            OfficialMonthlyTargetsExcelSupport::setNumericCell($sheet, 'R'.$rowNum, $savedTotal);

            $hasDistrictSplit = (bool) ($row['has_district_split'] ?? false);
            if ($hasDistrictSplit) {
                $districtTotal = (int) ($row['district_allocated_total'] ?? 0);
                OfficialMonthlyTargetsExcelSupport::setNumericCell($sheet, 'S'.$rowNum, $districtTotal);
                $verify = (array) ($row['verify_district'] ?? []);
                $sheet->setCellValue('T'.$rowNum, OfficialMonthlyTargetsExcelSupport::sanitize($verify['label'] ?? '—'));
            } else {
                $sheet->setCellValue('S'.$rowNum, 'N/A');
                $sheet->setCellValue('T'.$rowNum, '—');
            }

            if (! $mapped) {
                OfficialMonthlyTargetsExcelSupport::applyRowFill($sheet, 'A'.$rowNum.':'.$lastDataCol.$rowNum, OfficialMonthlyTargetsExcelSupport::UNMAPPED_FILL);
            }

            OfficialMonthlyTargetsExcelSupport::applyDataBorders($sheet, 'A'.$rowNum.':'.$lastDataCol.$rowNum);
            $rowNum++;
        }

        if ($rowNum > $dataStart) {
            $sheet->setCellValue('A'.$rowNum, 'Column totals');
            $sheet->mergeCells('A'.$rowNum.':D'.$rowNum);
            for ($m = 1; $m <= 12; $m++) {
                $col = OfficialMonthlyTargetsExcelSupport::columnLetter($monthStartCol + $m);
                OfficialMonthlyTargetsExcelSupport::setNumericCell($sheet, $col.$rowNum, $savedColTotals[$m]);
            }
            OfficialMonthlyTargetsExcelSupport::setNumericCell($sheet, 'Q'.$rowNum, $grandSaved);
            OfficialMonthlyTargetsExcelSupport::setNumericCell($sheet, 'R'.$rowNum, (int) ($columnTotals['grand_saved'] ?? $grandSaved));
            OfficialMonthlyTargetsExcelSupport::applyRowFill($sheet, 'A'.$rowNum.':'.$lastDataCol.$rowNum, OfficialMonthlyTargetsExcelSupport::FOOTER_FILL, true);
        }

        $sheet->getColumnDimension('A')->setWidth(6);
        $sheet->getColumnDimension('B')->setWidth(48);
        $sheet->getColumnDimension('C')->setWidth(12);
        $sheet->getColumnDimension('D')->setWidth(14);
        for ($m = 1; $m <= 12; $m++) {
            $sheet->getColumnDimension(OfficialMonthlyTargetsExcelSupport::columnLetter($monthStartCol + $m))->setWidth(9);
        }

        $fySlug = OfficialMonthlyTargetsExcelSupport::fySlug($fiscalYear?->name ?? 'FY');

        return DeliverablesExcelSupport::streamDownload(
            $spreadsheet,
            'state-targets-'.$fySlug.'-'.now()->format('Ymd-His').'.xlsx',
        );
    }
}
