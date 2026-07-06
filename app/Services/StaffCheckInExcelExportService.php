<?php

namespace App\Services;

use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StaffCheckInExcelExportService
{
    public function __construct(
        private readonly StaffCheckInService $checkInService,
    ) {}

    /**
     * @param  array{total: int, present: int, absent: int, rows: \Illuminate\Support\Collection}  $summary
     */
    public function download(
        array $summary,
        Carbon $date,
        array $roleOptions,
        string $roleFilter,
        int $hubId,
        int $districtId,
        string $statusFilter,
    ): StreamedResponse {
        if (! class_exists(Spreadsheet::class)) {
            return $this->downloadCsv($summary, $date, $roleOptions);
        }

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Staff attendance');

        $tz = config('app.timezone');
        $presentPct = $summary['total'] > 0
            ? round(($summary['present'] / $summary['total']) * 100, 1)
            : 0;

        $sheet->setCellValue('A1', 'Staff daily attendance report');
        $sheet->mergeCells('A1:L1');
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E3A5F']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(28);

        $meta = [
            ['Report date', $date->format('d M Y')],
            ['Generated at', now()->timezone($tz)->format('d M Y, g:i A')],
            ['Total staff', (string) $summary['total']],
            ['Present', (string) $summary['present']],
            ['Absent', (string) $summary['absent']],
            ['Attendance rate', $presentPct.'%'],
        ];
        if ($roleFilter !== '') {
            $meta[] = ['Role filter', $roleOptions[$roleFilter] ?? $roleFilter];
        }
        if ($statusFilter !== '') {
            $meta[] = ['Status filter', ucfirst($statusFilter)];
        }

        $row = 3;
        foreach ($meta as [$label, $value]) {
            $sheet->setCellValue('A'.$row, $label);
            $sheet->setCellValue('B'.$row, $value);
            $sheet->getStyle('A'.$row)->getFont()->setBold(true);
            $row++;
        }

        $headerRow = $row + 1;
        $headers = [
            'S.N.',
            'Staff name',
            'Email',
            'Role',
            'Designation',
            'Hub',
            'District',
            'Status',
            'Check-in date',
            'Check-in time',
            'Absent reason',
            'Latitude',
            'Longitude',
            'Accuracy (m)',
            'Google Maps',
        ];
        foreach ($headers as $i => $label) {
            $col = chr(ord('A') + $i);
            $sheet->setCellValue($col.$headerRow, $label);
        }
        $lastCol = chr(ord('A') + count($headers) - 1);
        $sheet->getStyle('A'.$headerRow.':'.$lastCol.$headerRow)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4F46E5']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'wrapText' => true],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E2E8F0']]],
        ]);

        $dataRow = $headerRow + 1;
        $sn = 1;
        foreach ($summary['rows'] as $item) {
            $user = $item['user'];
            $checkIn = $item['check_in'];
            $present = (bool) $item['present'];

            $sheet->setCellValue('A'.$dataRow, $sn);
            $sheet->setCellValue('B'.$dataRow, $user->name);
            $sheet->setCellValue('C'.$dataRow, $user->email);
            $sheet->setCellValue('D'.$dataRow, $roleOptions[$user->role] ?? $user->role);
            $sheet->setCellValue('E'.$dataRow, $user->designationRecord?->name ?? '—');
            $sheet->setCellValue('F'.$dataRow, $user->hub?->name ?? '—');
            $sheet->setCellValue('G'.$dataRow, $user->district?->name ?? '—');
            $sheet->setCellValue('H'.$dataRow, $present ? 'Present' : 'Absent');
            $sheet->setCellValue('I'.$dataRow, $present && $checkIn
                ? $checkIn->check_in_date->format('Y-m-d')
                : '—');
            $sheet->setCellValue('J'.$dataRow, $present && $checkIn
                ? $checkIn->marked_at->timezone($tz)->format('H:i:s')
                : '—');
            $sheet->setCellValue('K'.$dataRow, ! $present ? (string) ($item['absent_reason'] ?? '') : '');
            $sheet->setCellValue('L'.$dataRow, $present && $checkIn ? (float) $checkIn->latitude : '');
            $sheet->setCellValue('M'.$dataRow, $present && $checkIn ? (float) $checkIn->longitude : '');
            $sheet->setCellValue('N'.$dataRow, $present && $checkIn && $checkIn->accuracy_m
                ? (float) $checkIn->accuracy_m
                : '');
            if ($present && $checkIn) {
                $sheet->setCellValue('O'.$dataRow, $checkIn->googleMapsUrl());
                $sheet->getCell('O'.$dataRow)->getHyperlink()->setUrl($checkIn->googleMapsUrl());
            }

            $bg = $present ? 'ECFDF5' : 'FEF2F2';
            $sheet->getStyle('A'.$dataRow.':'.$lastCol.$dataRow)->applyFromArray([
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $bg]],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E2E8F0']]],
            ]);
            $sheet->getStyle('A'.$dataRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $sn++;
            $dataRow++;
        }

        foreach (range('A', $lastCol) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $fileName = 'staff-daily-attendance-'.$date->format('Y-m-d').'-'.now()->format('His').'.xlsx';

        return response()->streamDownload(function () use ($spreadsheet): void {
            (new Xlsx($spreadsheet))->save('php://output');
        }, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * CSV fallback when PhpSpreadsheet is not installed on server.
     *
     * @param  array{total: int, present: int, absent: int, rows: \Illuminate\Support\Collection}  $summary
     */
    private function downloadCsv(array $summary, Carbon $date, array $roleOptions): StreamedResponse
    {
        $tz = config('app.timezone');
        $fileName = 'staff-daily-attendance-'.$date->format('Y-m-d').'-'.now()->format('His').'.csv';

        return response()->streamDownload(function () use ($summary, $roleOptions, $tz): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }

            // UTF-8 BOM so Excel opens Hindi/UTF-8 text correctly.
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, ['S.N.', 'Staff name', 'Email', 'Role', 'Designation', 'Hub', 'District', 'Status', 'Check-in date', 'Check-in time', 'Absent reason', 'Latitude', 'Longitude', 'Accuracy (m)', 'Google Maps']);

            $sn = 1;
            foreach ($summary['rows'] as $item) {
                $user = $item['user'];
                $checkIn = $item['check_in'];
                $present = (bool) $item['present'];

                fputcsv($out, [
                    $sn,
                    (string) $user->name,
                    (string) $user->email,
                    (string) ($roleOptions[$user->role] ?? $user->role),
                    (string) ($user->designationRecord?->name ?? '—'),
                    (string) ($user->hub?->name ?? '—'),
                    (string) ($user->district?->name ?? '—'),
                    $present ? 'Present' : 'Absent',
                    $present && $checkIn ? (string) $checkIn->check_in_date->format('Y-m-d') : '—',
                    $present && $checkIn ? (string) $checkIn->marked_at->timezone($tz)->format('H:i:s') : '—',
                    $present ? '' : (string) ($item['absent_reason'] ?? ''),
                    $present && $checkIn ? (string) $checkIn->latitude : '',
                    $present && $checkIn ? (string) $checkIn->longitude : '',
                    $present && $checkIn && $checkIn->accuracy_m ? (string) $checkIn->accuracy_m : '',
                    $present && $checkIn ? (string) $checkIn->googleMapsUrl() : '',
                ]);

                $sn++;
            }

            fclose($out);
        }, $fileName, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
