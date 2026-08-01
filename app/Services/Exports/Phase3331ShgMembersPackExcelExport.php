<?php

namespace App\Services\Exports;

use App\Services\Deliverables\Exports\DeliverablesExcelSupport;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

final class Phase3331ShgMembersPackExcelExport
{
    /**
     * @param  array<string, mixed>  $pack
     */
    public function download(array $pack, string $fileName): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        DeliverablesExcelSupport::ensureAvailable();

        return DeliverablesExcelSupport::streamDownload($this->buildSpreadsheet($pack), $fileName);
    }

    /**
     * @param  array<string, mixed>  $pack
     */
    public function writeToPath(array $pack, string $absolutePath): void
    {
        DeliverablesExcelSupport::ensureAvailable();

        $spreadsheet = $this->buildSpreadsheet($pack);
        $dir = dirname($absolutePath);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save($absolutePath);
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);
    }

    /**
     * @param  array<string, mixed>  $pack
     */
    private function buildSpreadsheet(array $pack): Spreadsheet
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->removeSheetByIndex(0);

        $meta = $pack['meta'] ?? [];
        $this->addReadme($spreadsheet, $meta);
        $this->addCountsSheet($spreadsheet, $pack['summary_rows'] ?? [], $meta);
        $this->addSessionsSheet($spreadsheet, $pack['sessions'] ?? []);
        $this->addParticipantsSheet($spreadsheet, $pack['participants'] ?? []);

        $spreadsheet->setActiveSheetIndex(1);

        return $spreadsheet;
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function addReadme(Spreadsheet $spreadsheet, array $meta): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('README');
        $sheet->setCellValue('A1', (string) ($meta['title'] ?? '3.3.1 SHG members pack'));
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(13);
        $sheet->mergeCells('A1:B1');

        $r = 3;
        foreach ([
            ['Period from', $meta['period_from'] ?? ''],
            ['Period to', $meta['period_to'] ?? ''],
            ['Generated at', $meta['as_of'] ?? ''],
        ] as [$k, $v]) {
            $sheet->setCellValue('A'.$r, $k);
            $sheet->setCellValue('B'.$r, (string) $v);
            $r++;
        }
        $r++;
        $sheet->setCellValue('A'.$r, 'Rules');
        $sheet->getStyle('A'.$r)->getFont()->setBold(true);
        $r++;
        foreach (($meta['rules'] ?? []) as $rule) {
            $sheet->setCellValue('A'.$r, '• '.DeliverablesExcelSupport::sanitizeCell($rule));
            $sheet->mergeCells('A'.$r.':B'.$r);
            $r++;
        }
        $sheet->getColumnDimension('A')->setWidth(22);
        $sheet->getColumnDimension('B')->setWidth(90);
    }

    /**
     * @param  list<array{section: string, metric: string, count: int|string}>  $rows
     * @param  array<string, mixed>  $meta
     */
    private function addCountsSheet(Spreadsheet $spreadsheet, array $rows, array $meta): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Counts');

        $sheet->setCellValue('A1', '3.3.1 — SHG members audience (counts only)');
        $sheet->mergeCells('A1:C1');
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 13, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => DeliverablesExcelSupport::HEADER_FILL]],
        ]);

        $sheet->setCellValue('A2', 'Period: '.($meta['period_from'] ?? '').' → '.($meta['period_to'] ?? ''));
        $sheet->mergeCells('A2:C2');

        foreach (['Section', 'Metric', 'Count'] as $i => $h) {
            $sheet->setCellValue(DeliverablesExcelSupport::columnLetter($i).'4', $h);
        }
        $sheet->getStyle('A4:C4')->applyFromArray([
            'font' => ['bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => DeliverablesExcelSupport::HEADING_ROW_FILL]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $sheet->freezePane('A5');

        $r = 5;
        $prevSection = null;
        foreach ($rows as $row) {
            $section = (string) ($row['section'] ?? '');
            if ($prevSection !== null && $section !== $prevSection) {
                $r++;
            }
            $sheet->setCellValue('A'.$r, DeliverablesExcelSupport::sanitizeCell($section));
            $sheet->setCellValue('B'.$r, DeliverablesExcelSupport::sanitizeCell($row['metric'] ?? ''));
            $sheet->setCellValue('C'.$r, is_numeric($row['count'] ?? null) ? (int) $row['count'] : DeliverablesExcelSupport::sanitizeCell($row['count'] ?? ''));
            $prevSection = $section;
            $r++;
        }

        $sheet->getColumnDimension('A')->setWidth(28);
        $sheet->getColumnDimension('B')->setWidth(48);
        $sheet->getColumnDimension('C')->setWidth(12);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function addSessionsSheet(Spreadsheet $spreadsheet, array $rows): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Sessions Detail');

        $headers = [
            'Sr', 'Session ID', 'Date', 'Title', 'Brief', 'Agency', 'Mode',
            'District', 'Hub', 'Block', 'Gram panchayat', 'Venue',
            'Male', 'Female', 'Total', 'Attendance files', 'Workshop photos',
            'Submitted by', 'Status', 'Approved at',
        ];
        $this->writeHeaderRow($sheet, $headers, '3.3.1 sessions — SHG members audience (approved, excl. CBO Network)');

        $r = 5;
        $sr = 0;
        foreach ($rows as $row) {
            $sr++;
            $vals = [
                $sr,
                $row['session_id'] ?? '',
                $row['session_date'] ?? '',
                $row['session_title'] ?? '',
                $row['session_brief'] ?? '',
                $row['requesting_agency'] ?? '',
                $row['workshop_mode'] ?? '',
                $row['district'] ?? '',
                $row['hub'] ?? '',
                $row['block'] ?? '',
                $row['gram_panchayat'] ?? '',
                $row['venue'] ?? '',
                $row['male'] ?? 0,
                $row['female'] ?? 0,
                $row['participants_total'] ?? 0,
                $row['attendance_files'] ?? 0,
                $row['workshop_photos'] ?? 0,
                $row['submitted_by'] ?? '',
                $row['status'] ?? '',
                $row['approved_at'] ?? '',
            ];
            foreach ($vals as $i => $v) {
                $cell = DeliverablesExcelSupport::columnLetter($i).$r;
                $sheet->setCellValue($cell, is_int($v) || is_float($v) ? $v : DeliverablesExcelSupport::sanitizeCell($v));
            }
            $r++;
        }

        $this->autosize($sheet, count($headers));
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function addParticipantsSheet(Spreadsheet $spreadsheet, array $rows): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Participants Detail');

        $headers = [
            'Sr', 'Session ID', 'Session date', 'Session title', 'Agency',
            'Session district', 'Hub', 'Session block', 'Venue',
            'Participant #', 'Name', 'Mobile', 'Gender',
            'Participant district', 'Participant block', 'Participant GP',
        ];
        $this->writeHeaderRow($sheet, $headers, '3.3.1 participant list — SHG members audience sessions');

        $r = 5;
        $sr = 0;
        foreach ($rows as $row) {
            $sr++;
            $vals = [
                $sr,
                $row['session_id'] ?? '',
                $row['session_date'] ?? '',
                $row['session_title'] ?? '',
                $row['requesting_agency'] ?? '',
                $row['district'] ?? '',
                $row['hub'] ?? '',
                $row['block'] ?? '',
                $row['venue'] ?? '',
                $row['sr'] ?? '',
                $row['name'] ?? '',
                $row['mobile'] ?? '',
                $row['gender'] ?? '',
                $row['participant_district'] ?? '',
                $row['participant_block'] ?? '',
                $row['participant_gp'] ?? '',
            ];
            foreach ($vals as $i => $v) {
                $cell = DeliverablesExcelSupport::columnLetter($i).$r;
                $sheet->setCellValue($cell, is_int($v) || is_float($v) ? $v : DeliverablesExcelSupport::sanitizeCell($v));
            }
            $r++;
        }

        $this->autosize($sheet, count($headers));
    }

    /**
     * @param  list<string>  $headers
     */
    private function writeHeaderRow(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, array $headers, string $title): void
    {
        $lastCol = DeliverablesExcelSupport::columnLetter(count($headers) - 1);
        $sheet->setCellValue('A1', $title);
        $sheet->mergeCells('A1:'.$lastCol.'1');
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => DeliverablesExcelSupport::HEADER_FILL]],
        ]);

        foreach ($headers as $i => $h) {
            $sheet->setCellValue(DeliverablesExcelSupport::columnLetter($i).'4', $h);
        }
        $sheet->getStyle('A4:'.$lastCol.'4')->applyFromArray([
            'font' => ['bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => DeliverablesExcelSupport::HEADING_ROW_FILL]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'wrapText' => true],
        ]);
        $sheet->freezePane('A5');
    }

    private function autosize(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, int $colCount): void
    {
        for ($i = 0; $i < $colCount; $i++) {
            $letter = DeliverablesExcelSupport::columnLetter($i);
            $sheet->getColumnDimension($letter)->setAutoSize(true);
        }
    }
}
