<?php

namespace App\Services;

use Carbon\Carbon;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\View;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StaffCheckInMonthlyExportService
{
    /**
     * @param  array{
     *     month: Carbon,
     *     days_in_month: int,
     *     rows: \Illuminate\Support\Collection,
     *     total_staff: int,
     *     total_present: int,
     *     total_absent: int,
     *     rate_pct: float,
     *     elapsed_days: int
     * }  $grid
     */
    public function downloadExcel(
        array $grid,
        array $roleOptions,
        string $roleFilter,
        int $hubId,
        int $districtId,
        ?string $hubName = null,
        ?string $districtName = null,
    ): StreamedResponse {
        if (! class_exists(Spreadsheet::class)) {
            return $this->downloadCsv($grid);
        }

        $month = $grid['month'];
        $daysInMonth = (int) $grid['days_in_month'];
        $lastDayCol = 1 + $daysInMonth;
        $presentCol = $lastDayCol + 1;
        $absentCol = $lastDayCol + 2;
        $presentColLetter = Coordinate::stringFromColumnIndex($presentCol);
        $absentColLetter = Coordinate::stringFromColumnIndex($absentCol);
        $lastColLetter = $absentColLetter;

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(substr($month->format('M Y'), 0, 31));

        $title = 'Staff monthly attendance — '.$month->format('F Y');
        $sheet->setCellValue('A1', $title);
        $sheet->mergeCells('A1:'.$lastColLetter.'1');
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4F46E5']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(30);

        $meta = [
            ['Month', $month->format('F Y')],
            ['Generated at', now()->timezone(config('app.timezone'))->format('d M Y, g:i A')],
            ['Total staff', (string) $grid['total_staff']],
            ['Present marks', (string) $grid['total_present']],
            ['Absent marks', (string) $grid['total_absent']],
            ['Attendance rate', $grid['rate_pct'].'%'],
        ];
        if ($roleFilter !== '') {
            $meta[] = ['Role filter', $roleOptions[$roleFilter] ?? $roleFilter];
        }
        if ($hubName) {
            $meta[] = ['Hub', $hubName];
        }
        if ($districtName) {
            $meta[] = ['District', $districtName];
        }
        if (! empty($grid['sunday_labels'])) {
            $meta[] = ['Sundays (weekly off)', implode(', ', $grid['sunday_labels'])];
        }

        $row = 3;
        foreach ($meta as [$label, $value]) {
            $sheet->setCellValue('A'.$row, $label);
            $sheet->setCellValue('B'.$row, $value);
            $sheet->getStyle('A'.$row)->getFont()->setBold(true);
            $row++;
        }

        $headerRow = $row + 1;
        $sheet->setCellValue('A'.$headerRow, 'Employee');
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $col = Coordinate::stringFromColumnIndex(1 + $d);
            $sheet->setCellValue($col.$headerRow, (string) $d);
        }
        $sheet->setCellValue($presentColLetter.$headerRow, 'Present');
        $sheet->setCellValue($absentColLetter.$headerRow, 'Absent');

        $sheet->getStyle('A'.$headerRow.':'.$lastColLetter.$headerRow)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0F766E']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'wrapText' => true],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CBD5E1']]],
        ]);
        $sheet->getStyle($presentColLetter.$headerRow)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '16A34A']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'wrapText' => true],
        ]);
        $sheet->getStyle($absentColLetter.$headerRow)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'DC2626']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'wrapText' => true],
        ]);
        $sheet->getColumnDimension('A')->setWidth(28);

        $dataRow = $headerRow + 1;
        $rowItems = $grid['rows']->values();
        $rowCount = $rowItems->count();
        foreach ($rowItems as $itemIndex => $item) {
            $user = $item['user'];
            $sheet->setCellValue('A'.$dataRow, $user->name);

            for ($d = 1; $d <= $daysInMonth; $d++) {
                $col = Coordinate::stringFromColumnIndex(1 + $d);
                $dayDate = $month->copy()->day($d);

                if ($dayDate->isSunday()) {
                    if ($itemIndex % 4 === 0) {
                        $span = min(4, $rowCount - $itemIndex);
                        $mergeEndRow = $dataRow + $span - 1;
                        $sheet->mergeCells($col.$dataRow.':'.$col.$mergeEndRow);
                        $sheet->setCellValue($col.$dataRow, 'Sunday Sunday');
                        $sheet->getStyle($col.$dataRow)->applyFromArray([
                            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F1F5F9']],
                            'alignment' => [
                                'horizontal' => Alignment::HORIZONTAL_CENTER,
                                'vertical' => Alignment::VERTICAL_CENTER,
                                'textRotation' => 90,
                                'wrapText' => true,
                            ],
                            'font' => ['bold' => true, 'color' => ['rgb' => '94A3B8'], 'size' => 8],
                            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E2E8F0']]],
                        ]);
                    }

                    continue;
                }

                $status = $item['days'][$d] ?? null;
                $symbol = match ($status) {
                    'present' => 'P',
                    'absent' => 'A',
                    default => '',
                };
                $sheet->setCellValue($col.$dataRow, $symbol);

                $bg = match ($status) {
                    'present' => 'DCFCE7',
                    'absent' => 'FEE2E2',
                    default => 'F8FAFC',
                };
                $sheet->getStyle($col.$dataRow)->applyFromArray([
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $bg]],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                    'font' => ['bold' => true],
                ]);
            }

            $sheet->setCellValue($presentColLetter.$dataRow, (int) $item['present_count']);
            $sheet->getStyle($presentColLetter.$dataRow)->applyFromArray([
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'DCFCE7']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                'font' => ['bold' => true, 'color' => ['rgb' => '15803D']],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E2E8F0']]],
            ]);

            $sheet->setCellValue($absentColLetter.$dataRow, (int) $item['absent_count']);
            $sheet->getStyle($absentColLetter.$dataRow)->applyFromArray([
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FEE2E2']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                'font' => ['bold' => true, 'color' => ['rgb' => 'B91C1C']],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E2E8F0']]],
            ]);

            $sheet->getStyle('A'.$dataRow)->getFont()->setBold(true);
            $dataRow++;
        }

        $sheet->getStyle('A'.$headerRow.':'.$lastColLetter.($dataRow - 1))->applyFromArray([
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E2E8F0']]],
        ]);

        for ($d = 1; $d <= $daysInMonth; $d++) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex(1 + $d))->setWidth(
                $month->copy()->day($d)->isSunday() ? 5 : 3.5,
            );
        }
        $sheet->getColumnDimension($presentColLetter)->setWidth(8);
        $sheet->getColumnDimension($absentColLetter)->setWidth(8);

        $fileName = 'staff-attendance-'.$month->format('Y-m').'-'.now()->format('His').'.xlsx';

        return response()->streamDownload(function () use ($spreadsheet): void {
            (new Xlsx($spreadsheet))->save('php://output');
        }, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * @param  array{
     *     month: Carbon,
     *     days_in_month: int,
     *     rows: \Illuminate\Support\Collection,
     *     total_staff: int,
     *     total_present: int,
     *     total_absent: int,
     *     rate_pct: float,
     *     elapsed_days: int
     * }  $grid
     */
    public function downloadPdf(
        array $grid,
        array $roleOptions,
        string $roleFilter,
        ?string $hubName = null,
        ?string $districtName = null,
    ): Response {
        if (! class_exists(Dompdf::class)) {
            abort(503, 'PDF export is not available on this server.');
        }

        $month = $grid['month'];
        $html = View::make('admin.staff-check-ins.monthly-pdf', [
            'grid' => $grid,
            'roleOptions' => $roleOptions,
            'roleFilter' => $roleFilter,
            'hubName' => $hubName,
            'districtName' => $districtName,
            'generatedAt' => now()->timezone(config('app.timezone'))->format('d M Y, g:i A'),
        ])->render();

        $options = new Options;
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        $fileName = 'staff-attendance-'.$month->format('Y-m').'-'.now()->format('His').'.pdf';

        return response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$fileName.'"',
        ]);
    }

    /**
     * @param  array{
     *     month: Carbon,
     *     days_in_month: int,
     *     rows: \Illuminate\Support\Collection,
     *     total_staff: int,
     *     total_present: int,
     *     total_absent: int,
     *     rate_pct: float,
     *     elapsed_days: int
     * }  $grid
     */
    private function downloadCsv(array $grid): StreamedResponse
    {
        $month = $grid['month'];
        $daysInMonth = (int) $grid['days_in_month'];
        $fileName = 'staff-attendance-'.$month->format('Y-m').'-'.now()->format('His').'.csv';

        return response()->streamDownload(function () use ($grid, $daysInMonth, $month): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }

            fwrite($out, "\xEF\xBB\xBF");

            $header = array_merge(['Employee'], range(1, $daysInMonth), ['Present', 'Absent']);
            fputcsv($out, $header);

            foreach ($grid['rows'] as $itemIndex => $item) {
                $row = [(string) $item['user']->name];
                for ($d = 1; $d <= $daysInMonth; $d++) {
                    $dayDate = $month->copy()->day($d);
                    if ($dayDate->isSunday()) {
                        $row[] = ($itemIndex % 4 === 0) ? 'Sunday Sunday' : '';

                        continue;
                    }
                    $row[] = match ($item['days'][$d] ?? null) {
                        'present' => 'P',
                        'absent' => 'A',
                        default => '',
                    };
                }
                $row[] = (int) $item['present_count'];
                $row[] = (int) $item['absent_count'];
                fputcsv($out, $row);
            }

            fclose($out);
        }, $fileName, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
