<?php

namespace App\Services;

use App\Services\Deliverables\Exports\DeliverablesExcelSupport;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class StaffServiceCasesExcelExport
{
    /**
     * @param  list<list<string|int>>  $rows
     * @param  array{staff:string,district:string,scope:string,search:string,status:string,service:string,total:int}  $meta
     */
    public function download(array $rows, array $meta): BinaryFileResponse
    {
        DeliverablesExcelSupport::ensureAvailable();

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Service Records');
        $sheet->setShowGridlines(false);

        $headers = [
            'S.No.', 'Record Type', 'Incubatee / Activity', 'Application No.', 'District',
            'Service', 'Service Given By', 'Assigned SPOC', 'Responded By', 'Response / Remark',
            'Status', 'Service Date', 'Submitted At', 'Updated At', 'Reference No.',
        ];
        $lastColumn = 'O';

        $sheet->mergeCells('A1:'.$lastColumn.'1');
        $sheet->setCellValue('A1', 'MUY — Staff Service Records');
        $sheet->getStyle('A1:'.$lastColumn.'1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 16, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4338CA']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(30);

        $sheet->mergeCells('A2:'.$lastColumn.'2');
        $sheet->setCellValue('A2', 'Filtered export · generated '.now()->timezone(config('app.timezone'))->format('d M Y, h:i A'));
        $sheet->getStyle('A2:'.$lastColumn.'2')->applyFromArray([
            'font' => ['italic' => true, 'color' => ['rgb' => '475569']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'EEF2FF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        $metaRows = [
            ['Staff', $meta['staff']],
            ['District', $meta['district']],
            ['Scope', $meta['scope']],
            ['Search', $meta['search'] !== '' ? $meta['search'] : 'All'],
            ['Service filter', $meta['service']],
            ['Status filter', $meta['status'] !== '' ? str_replace('_', ' ', ucfirst($meta['status'])) : 'All statuses'],
            ['Total matching records', number_format($meta['total'])],
        ];

        $metaStart = 4;
        foreach ($metaRows as $index => [$label, $value]) {
            $row = $metaStart + $index;
            $sheet->setCellValue('A'.$row, $label);
            $sheet->mergeCells('B'.$row.':E'.$row);
            $sheet->setCellValueExplicit('B'.$row, DeliverablesExcelSupport::sanitizeCell($value), DataType::TYPE_STRING);
            $sheet->getStyle('A'.$row)->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => '334155']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F8FAFC']],
            ]);
        }

        $headerRow = 12;
        DeliverablesExcelSupport::writeTableHeader($sheet, $headerRow, $headers);
        $sheet->getStyle('A'.$headerRow.':'.$lastColumn.$headerRow)->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('0F766E');

        $dataRow = $headerRow + 1;
        foreach ($rows as $rowIndex => $values) {
            $excelRow = $dataRow + $rowIndex;
            foreach ($values as $columnIndex => $value) {
                $column = DeliverablesExcelSupport::columnLetter($columnIndex);
                if ($columnIndex === 0) {
                    $sheet->setCellValue($column.$excelRow, (int) $value);
                } else {
                    $sheet->setCellValueExplicit(
                        $column.$excelRow,
                        DeliverablesExcelSupport::sanitizeCell($value),
                        DataType::TYPE_STRING,
                    );
                }
            }

            if ($rowIndex % 2 === 1) {
                $sheet->getStyle('A'.$excelRow.':'.$lastColumn.$excelRow)->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('F8FAFC');
            }

            $status = mb_strtolower((string) ($values[10] ?? ''));
            $statusColor = match (true) {
                str_contains($status, 'approved') => 'DCFCE7',
                str_contains($status, 'pending') => 'FEF3C7',
                str_contains($status, 'sent back') => 'FFEDD5',
                str_contains($status, 'rejected') => 'FEE2E2',
                default => 'F1F5F9',
            };
            $sheet->getStyle('K'.$excelRow)->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB($statusColor);
        }

        $lastDataRow = max($headerRow, $dataRow + count($rows) - 1);
        if ($lastDataRow > $headerRow) {
            $sheet->getStyle('A'.$dataRow.':'.$lastColumn.$lastDataRow)->applyFromArray([
                'borders' => [
                    'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E2E8F0']],
                ],
                'alignment' => ['vertical' => Alignment::VERTICAL_TOP, 'wrapText' => true],
            ]);
        }
        $sheet->setAutoFilter('A'.$headerRow.':'.$lastColumn.$lastDataRow);
        $sheet->freezePane('A'.($headerRow + 1));

        foreach ([
            'A' => 8, 'B' => 19, 'C' => 34, 'D' => 19, 'E' => 18,
            'F' => 34, 'G' => 23, 'H' => 23, 'I' => 22, 'J' => 34,
            'K' => 18, 'L' => 16, 'M' => 23, 'N' => 23, 'O' => 18,
        ] as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }

        $sheet->getPageSetup()->setOrientation('landscape')->setFitToWidth(1)->setFitToHeight(0);
        $sheet->getPageMargins()->setTop(0.4)->setRight(0.3)->setBottom(0.4)->setLeft(0.3);
        $sheet->getHeaderFooter()->setOddFooter('&LGenerated by MUY&CPage &P of &N&R'.now()->format('d M Y'));

        $fileName = 'staff-service-records-'.now()->format('Ymd-His').'.xlsx';

        $tempPath = tempnam(sys_get_temp_dir(), 'muy-staff-services-').'.xlsx';
        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet))->save($tempPath);
        $spreadsheet->disconnectWorksheets();

        return response()->download($tempPath, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'max-age=0, no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
        ])->deleteFileAfterSend(true);
    }
}
