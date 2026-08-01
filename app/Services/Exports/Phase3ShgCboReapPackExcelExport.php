<?php

namespace App\Services\Exports;

use App\Services\Deliverables\Exports\DeliverablesExcelSupport;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class Phase3ShgCboReapPackExcelExport
{
    /**
     * @param  array<string, mixed>  $pack
     */
    public function download(array $pack, string $fileName): StreamedResponse
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

        $shg = $pack['shg'] ?? [];
        $cbo = $pack['cbo'] ?? [];
        $reap82 = $pack['reap82'] ?? [];

        $this->addCountsSheet($spreadsheet, 'SHG Counts', (string) ($shg['label'] ?? 'SHG members'), $shg['summary_rows'] ?? [], $meta);
        $this->addDetailSheet($spreadsheet, 'SHG Detail', $shg['details'] ?? []);
        $this->addCountsSheet($spreadsheet, 'CBO Counts', (string) ($cbo['label'] ?? 'CBO'), $cbo['summary_rows'] ?? [], $meta);
        $this->addDetailSheet($spreadsheet, 'CBO Detail', $cbo['details'] ?? []);
        $this->addCountsSheet(
            $spreadsheet,
            '8.2 Counts',
            (string) ($reap82['label'] ?? '8.2 Support to MUY Incubatee through REAP'),
            $reap82['summary_rows'] ?? [],
            $meta,
        );
        $this->addReap82DetailSheet($spreadsheet, $reap82['details'] ?? []);

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
        $sheet->setCellValue('A1', (string) ($meta['title'] ?? 'SHG / CBO pack'));
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->mergeCells('A1:B1');

        $r = 3;
        foreach ([
            ['Period from', $meta['period_from'] ?? ''],
            ['Period to', $meta['period_to'] ?? ''],
            ['Generated at', $meta['as_of'] ?? ''],
            ['Fiscal year', $meta['fiscal_year'] ?? ''],
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
        $sheet->getColumnDimension('B')->setWidth(80);
    }

    /**
     * @param  list<array{section: string, metric: string, count: int|string}>  $rows
     * @param  array<string, mixed>  $meta
     */
    private function addCountsSheet(Spreadsheet $spreadsheet, string $title, string $label, array $rows, array $meta): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle($this->safeTitle($title));

        $sheet->setCellValue('A1', $label.' — counts only');
        $sheet->mergeCells('A1:C1');
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 13, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => DeliverablesExcelSupport::HEADER_FILL]],
        ]);

        $sheet->setCellValue('A2', 'Period: '.($meta['period_from'] ?? '').' → '.($meta['period_to'] ?? ''));
        $sheet->mergeCells('A2:C2');

        $headers = ['Section', 'Metric', 'Count'];
        foreach ($headers as $i => $h) {
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
                $r++; // blank row between sections
            }
            $sheet->setCellValue('A'.$r, DeliverablesExcelSupport::sanitizeCell($section));
            $sheet->setCellValue('B'.$r, DeliverablesExcelSupport::sanitizeCell($row['metric'] ?? ''));
            $sheet->setCellValue('C'.$r, is_numeric($row['count'] ?? null) ? (int) $row['count'] : DeliverablesExcelSupport::sanitizeCell($row['count'] ?? ''));
            if ($section !== $prevSection) {
                $sheet->getStyle('A'.$r)->getFont()->setBold(true);
            }
            $prevSection = $section;
            $r++;
        }

        $sheet->getColumnDimension('A')->setWidth(40);
        $sheet->getColumnDimension('B')->setWidth(55);
        $sheet->getColumnDimension('C')->setWidth(14);
        if ($rows !== []) {
            $sheet->setAutoFilter('A4:C'.max($r - 1, 4));
        }
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function addDetailSheet(Spreadsheet $spreadsheet, string $title, array $rows): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle($this->safeTitle($title));

        $headers = [
            'S.N.', 'CFA ID', 'Application No', 'Applicant', 'Category', 'Member of SHG/CBO',
            'SHG/CBO Name', 'Phone', 'Sector', 'District', 'Hub', 'Batch', 'Onboarding Date',
            'Services count', 'Services taken', 'Market linkage mode', 'Market partners', 'Partner count',
            '4.2 Legal & Licensing',
        ];
        $keys = [
            'sn', 'cfa_id', 'application_no', 'applicant', 'category', 'is_member',
            'shg_cbo_name', 'phone', 'sector', 'district', 'hub', 'batch', 'onboarding_date',
            'services_count', 'services', 'market_linkage_mode', 'market_partners', 'market_partner_count',
            'legal_4_2',
        ];

        foreach ($headers as $i => $h) {
            $sheet->setCellValue(DeliverablesExcelSupport::columnLetter($i).'1', $h);
        }
        $last = DeliverablesExcelSupport::columnLetter(count($headers) - 1);
        $sheet->getStyle('A1:'.$last.'1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => DeliverablesExcelSupport::HEADER_FILL]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'wrapText' => true],
        ]);
        $sheet->freezePane('A2');

        $r = 2;
        foreach ($rows as $row) {
            foreach ($keys as $i => $key) {
                $val = $row[$key] ?? '';
                $sheet->setCellValue(
                    DeliverablesExcelSupport::columnLetter($i).$r,
                    is_numeric($val) && ! in_array($key, ['application_no', 'phone'], true)
                        ? $val
                        : DeliverablesExcelSupport::sanitizeCell($val)
                );
            }
            $r++;
        }

        for ($i = 0; $i < count($headers); $i++) {
            // Fixed widths — autosize on 1k+ rows exhausts memory on 128M hosts.
            $sheet->getColumnDimension(DeliverablesExcelSupport::columnLetter($i))->setWidth(match ($i) {
                0 => 8,
                1 => 10,
                2 => 16,
                3 => 22,
                14 => 55,
                15, 16, 18 => 20,
                default => 14,
            });
        }
        if ($rows !== []) {
            $sheet->setAutoFilter('A1:'.$last.(count($rows) + 1));
        }

        $sheet->setCellValue('A'.(count($rows) + 3), 'Detail rows: '.count($rows).' (must equal onboarded count on Counts sheet)');
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function addReap82DetailSheet(Spreadsheet $spreadsheet, array $rows): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle($this->safeTitle('8.2 Detail'));

        $headers = [
            'S.N.', 'Case ID', 'CFA ID', 'Application No', 'Incubatee', 'District', 'Hub',
            'Service', 'Code', 'Sector', 'Amount', 'Bucket', 'Activity', 'Status', 'Date',
        ];
        $keys = [
            'sn', 'case_id', 'cfa_id', 'application_no', 'applicant', 'district', 'hub',
            'service', 'service_code', 'sector', 'amount', 'bucket', 'activity', 'status', 'date',
        ];

        foreach ($headers as $i => $h) {
            $sheet->setCellValue(DeliverablesExcelSupport::columnLetter($i).'1', $h);
        }
        $last = DeliverablesExcelSupport::columnLetter(count($headers) - 1);
        $sheet->getStyle('A1:'.$last.'1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => DeliverablesExcelSupport::HEADER_FILL]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'wrapText' => true],
        ]);
        $sheet->freezePane('A2');

        $r = 2;
        foreach ($rows as $row) {
            foreach ($keys as $i => $key) {
                $val = $row[$key] ?? '';
                $sheet->setCellValue(
                    DeliverablesExcelSupport::columnLetter($i).$r,
                    is_numeric($val) && ! in_array($key, ['application_no'], true)
                        ? $val
                        : DeliverablesExcelSupport::sanitizeCell($val)
                );
            }
            $r++;
        }

        for ($i = 0; $i < count($headers); $i++) {
            $sheet->getColumnDimension(DeliverablesExcelSupport::columnLetter($i))->setWidth(match ($i) {
                0 => 8,
                4 => 24,
                default => 14,
            });
        }
        if ($rows !== []) {
            $sheet->setAutoFilter('A1:'.$last.(count($rows) + 1));
        }
        $sheet->setCellValue('A'.(count($rows) + 3), 'Detail rows: '.count($rows).' (must equal 8.2 Total cases on Counts sheet)');
    }

    private function safeTitle(string $title): string
    {
        $title = preg_replace('/[\\\\\/\*\?\:\[\]]+/', ' ', $title) ?? $title;
        $title = trim($title);

        return mb_substr($title !== '' ? $title : 'Sheet', 0, 31);
    }
}
