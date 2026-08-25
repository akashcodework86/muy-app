<?php

namespace App\Services\Exports;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class YearwiseIndicatorExcelExport
{
    private const HEADER_FILL = '1F4E79';

    /** @var list<string> */
    private const METRICS = ['cfa', 'onboarding', 'udyam', 'fssai', 'gst', 'market_linkage', 'convergence'];

    /**
     * @param  array<string, mixed>  $payload  From YearwiseIndicatorWorkbookService::buildExportPayload()
     */
    public function writeToPath(array $payload, string $absolutePath): void
    {
        if (! class_exists(\ZipArchive::class) || ! class_exists(Spreadsheet::class)) {
            throw new \RuntimeException('Excel export is unavailable: PHP Zip extension or PhpSpreadsheet missing.');
        }

        $spreadsheet = $this->buildSpreadsheet($payload);
        $dir = dirname($absolutePath);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $writer = new Xlsx($spreadsheet);
        $writer->setPreCalculateFormulas(false);
        $writer->save($absolutePath);
        $spreadsheet->disconnectWorksheets();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function buildSpreadsheet(array $payload): Spreadsheet
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getProperties()
            ->setTitle('MUY year-wise indicators')
            ->setCreator('Mukhyamantri Udyamshala Yojana MIS')
            ->setDescription('CFA, onboarding, Udyam, FSSAI, GST, market linkage, convergence — summary and detailed lists.');

        $this->writeSummarySheet($spreadsheet->getActiveSheet(), $payload);
        $this->writeNotesSheet($spreadsheet->createSheet());
        $this->writeListSheet($spreadsheet->createSheet(), 'CFA', $this->cfaHeaders(), $payload['cfa'] ?? [], 'cfa');
        $this->writeListSheet($spreadsheet->createSheet(), 'Onboarding', $this->onboardingHeaders(), $payload['onboarding'] ?? [], 'onboarding');
        $this->writeListSheet($spreadsheet->createSheet(), 'Udyam', $this->serviceHeaders(), $payload['udyam'] ?? [], 'service');
        $this->writeListSheet($spreadsheet->createSheet(), 'FSSAI', $this->serviceHeaders(), $payload['fssai'] ?? [], 'fssai');
        $this->writeListSheet($spreadsheet->createSheet(), 'GST', $this->serviceHeaders(), $payload['gst'] ?? [], 'gst');
        $this->writeListSheet($spreadsheet->createSheet(), 'Market_Linkage', $this->marketHeaders(), $payload['market_linkage'] ?? [], 'market');
        $this->writeListSheet($spreadsheet->createSheet(), 'Convergence', $this->serviceHeaders(), $payload['convergence'] ?? [], 'service');

        return $spreadsheet;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function download(array $payload): StreamedResponse
    {
        if (! class_exists(\ZipArchive::class) || ! class_exists(Spreadsheet::class)) {
            throw new \RuntimeException('Excel export is unavailable: PHP Zip extension or PhpSpreadsheet missing.');
        }

        $spreadsheet = $this->buildSpreadsheet($payload);
        $fileName = $this->fileName($payload);

        return response()->streamDownload(function () use ($spreadsheet): void {
            $writer = new Xlsx($spreadsheet);
            $writer->setPreCalculateFormulas(false);
            $writer->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'max-age=0, no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function fileName(array $payload): string
    {
        $parts = ['yearwise-indicators'];
        if (! empty($payload['fy_filter'])) {
            $parts[] = str_replace('/', '-', (string) $payload['fy_filter']);
        }
        if (! empty($payload['district_filter'])) {
            $parts[] = preg_replace('/[^a-z0-9]+/i', '-', strtolower((string) $payload['district_filter'])) ?: 'district';
        }
        $parts[] = now()->format('Ymd_His');

        return implode('-', $parts).'.xlsx';
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function writeSummarySheet($sheet, array $payload): void
    {
        $sheet->setTitle('Summary');
        $generatedAt = (string) ($payload['generated_at'] ?? '');
        $note = (string) ($payload['note'] ?? '');

        $sheet->setCellValue('A1', 'MUY year-wise indicators');
        $sheet->mergeCells('A1:H1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        $sheet->setCellValue('A2', 'Generated: '.$generatedAt);
        $sheet->setCellValue('A3', $note);

        $headers = ['Year', 'CFA', 'Onboarding', 'Udyam registration', 'FSSAI', 'GST', 'Market linkage', 'Convergence'];
        $r = 5;
        foreach ($headers as $i => $h) {
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($i + 1).$r, $h);
        }
        $this->styleHeaderRow($sheet, 'A5:H5');

        $totals = array_fill_keys(self::METRICS, 0);
        $r = 6;
        foreach ($payload['rows'] ?? [] as $row) {
            $sheet->setCellValue('A'.$r, $row['year'] ?? '');
            $sheet->setCellValue('B'.$r, $row['cfa'] ?? 0);
            $sheet->setCellValue('C'.$r, $row['onboarding'] ?? 0);
            $sheet->setCellValue('D'.$r, $row['udyam'] ?? 0);
            $sheet->setCellValue('E'.$r, $row['fssai'] ?? 0);
            $sheet->setCellValue('F'.$r, $row['gst'] ?? 0);
            $sheet->setCellValue('G'.$r, $row['market_linkage'] ?? 0);
            $sheet->setCellValue('H'.$r, $row['convergence'] ?? 0);
            foreach (self::METRICS as $m) {
                $totals[$m] += (int) ($row[$m] ?? 0);
            }
            $r++;
        }

        $payloadTotals = $payload['totals'] ?? [];
        $sheet->setCellValue('A'.$r, 'Total');
        $sheet->getStyle('A'.$r.':H'.$r)->getFont()->setBold(true);
        $sheet->setCellValue('B'.$r, $payloadTotals['cfa'] ?? $totals['cfa']);
        $sheet->setCellValue('C'.$r, $payloadTotals['onboarding'] ?? $totals['onboarding']);
        $sheet->setCellValue('D'.$r, $payloadTotals['udyam'] ?? $totals['udyam']);
        $sheet->setCellValue('E'.$r, $payloadTotals['fssai'] ?? $totals['fssai']);
        $sheet->setCellValue('F'.$r, $payloadTotals['gst'] ?? $totals['gst']);
        $sheet->setCellValue('G'.$r, $payloadTotals['market_linkage'] ?? $totals['market_linkage']);
        $sheet->setCellValue('H'.$r, $payloadTotals['convergence'] ?? $totals['convergence']);

        $unknown = $payload['unknown'] ?? [];
        $unknownTotal = array_sum(array_map('intval', is_array($unknown) ? $unknown : []));
        if ($unknownTotal > 0) {
            $r += 2;
            $sheet->setCellValue('A'.$r, 'Unknown FY (no parsable date — see detail sheets)');
            $sheet->getStyle('A'.$r)->getFont()->setBold(true);
            $r++;
            $sheet->setCellValue(
                'A'.$r,
                'CFA '.($unknown['cfa'] ?? 0)
                .' | Onboarding '.($unknown['onboarding'] ?? 0)
                .' | Udyam '.($unknown['udyam'] ?? 0)
                .' | FSSAI '.($unknown['fssai'] ?? 0)
                .' | GST '.($unknown['gst'] ?? 0)
                .' | Market '.($unknown['market_linkage'] ?? 0)
                .' | Convergence '.($unknown['convergence'] ?? 0),
            );
        }

        foreach (range(1, 8) as $col) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($col))->setWidth(22);
        }
        $sheet->freezePane('A6');
    }

    private function writeNotesSheet($sheet): void
    {
        $sheet->setTitle('Notes');
        $lines = [
            ['Field', 'Rule'],
            ['CFA 2025-26', 'Same as april/24.php Achieved (FY): DATE(rbi_applications.submission_date) between 2025-04-01 and 2026-03-31.'],
            ['CFA 2026-27', 'Same as MIS Deliverables 1.1 Call for Application for FY 2026-27.'],
            ['Onboarding 2025-26', 'Same as 24.php: DATE(rbi_onboarded_applicants.onboarded_at) in window.'],
            ['Onboarding 2026-27', 'Same as MIS Deliverables 2.1 Incubatees Onboarded.'],
            ['Udyam 2025-26', 'Same as 24.php Business Registration bifurcation (Udyam / Already Registered).'],
            ['Udyam 2026-27', 'Same as MIS Deliverables 4.1.1 Udyam bifurcation rows.'],
            ['FSSAI / GST 2025-26', 'Same as 24.php akey:fssai / akey:gst from rbi_services_assigned.'],
            ['FSSAI / GST 2026-27', 'Same as MIS Deliverables 4.2.2 / 4.2.4.'],
            ['Market linkage 2025-26', 'Same as 24.php: COUNT rbi_service_partners by DATE(added_at).'],
            ['Market linkage 2026-27', 'Same as MIS Deliverables 6.3 incubatees linked.'],
            ['Convergence 2025-26', 'Same as 24.php COMPOSITE_ATF Access to finance through convergence.'],
            ['Convergence 2026-27', 'Same as MIS Deliverables 8.1 Schematic Convergence.'],
            ['CFA 2020-21 to 2024-25', 'ukrbiin_rbi.tblapplication. Blank ApplicationDate uses onboard_date / onboarding_date.'],
            ['Date parsing', 'FY = 1 Apr–31 Mar. Output dates as Y-m-d where available.'],
            ['Filters', 'District and FY filters apply to Summary and all detail sheets.'],
        ];
        $r = 1;
        foreach ($lines as $i => $line) {
            $sheet->setCellValue('A'.$r, $line[0]);
            $sheet->setCellValue('B'.$r, $line[1]);
            if ($i === 0) {
                $this->styleHeaderRow($sheet, 'A1:B1');
            }
            $r++;
        }
        $sheet->getColumnDimension('A')->setWidth(36);
        $sheet->getColumnDimension('B')->setWidth(120);
        $sheet->getStyle('B:B')->getAlignment()->setWrapText(true);
    }

    /**
     * @param  list<string>  $headers
     * @param  list<array<string, mixed>>  $rows
     */
    private function writeListSheet($sheet, string $title, array $headers, array $rows, string $kind): void
    {
        $sheet->setTitle($title);
        $colCount = count($headers);
        foreach ($headers as $i => $h) {
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($i + 1).'1', $h);
        }
        $lastCol = Coordinate::stringFromColumnIndex($colCount);
        $this->styleHeaderRow($sheet, 'A1:'.$lastCol.'1');
        $sheet->freezePane('A2');
        $sheet->setAutoFilter('A1:'.$lastCol.'1');

        $r = 2;
        foreach ($rows as $row) {
            $vals = $this->rowValues($row, $kind);
            foreach ($vals as $i => $v) {
                $cell = Coordinate::stringFromColumnIndex($i + 1).$r;
                $sheet->setCellValueExplicit($cell, (string) $v, DataType::TYPE_STRING);
            }
            $r++;
        }

        foreach (range(1, $colCount) as $i) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($i))->setWidth(18);
        }
        if ($colCount >= 4) {
            $sheet->getColumnDimension('D')->setWidth(28);
        }
        if ($colCount >= 8) {
            $sheet->getColumnDimension('H')->setWidth(36);
        }
    }

    /**
     * @param  array<string, mixed>  $row
     * @return list<string>
     */
    private function rowValues(array $row, string $kind): array
    {
        $fy = (string) ($row['year'] ?? '');
        $sourceDb = (string) ($row['source_db'] ?? '');
        $appNo = (string) ($row['application_no'] ?? '');
        $name = (string) ($row['applicant_name'] ?? '');
        $district = (string) ($row['district'] ?? '');
        $phone = (string) ($row['phone'] ?? '');
        $eventDate = $this->dateOnly((string) ($row['date_used'] ?? ''));
        $dateBasis = (string) ($row['date_source'] ?? '');

        return match ($kind) {
            'cfa' => [
                $fy, $sourceDb, $appNo, $name, $district, $phone, $eventDate, $dateBasis,
                (string) ($row['application_date'] ?? ''), (string) ($row['onboard_date'] ?? ''),
                (string) ($row['onboard_flag'] ?? ''),
            ],
            'onboarding' => [
                $fy, $sourceDb, $appNo, $name, $district, $phone, $eventDate, $dateBasis,
                (string) ($row['onboard_date'] ?? ''), (string) ($row['onboard_flag'] ?? ''),
                (string) ($row['batch_name'] ?? ''),
            ],
            'market' => [
                $fy, $sourceDb, $appNo, $name, $district, $phone, $eventDate,
                (string) ($row['service_label'] ?? ''), (string) ($row['detail'] ?? ''), $dateBasis,
            ],
            default => [
                $fy, $sourceDb, $appNo, $name, $district, $phone, $eventDate,
                (string) ($row['category'] ?? ''), (string) ($row['service_label'] ?? ''),
                (string) ($row['detail'] ?? ''), $dateBasis, (string) ($row['status'] ?? ''),
            ],
        };
    }

    private function dateOnly(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $value, $m) === 1) {
            return $m[1];
        }

        return $value;
    }

    private function styleHeaderRow($sheet, string $range): void
    {
        $sheet->getStyle($range)->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle($range)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::HEADER_FILL);
    }

    /** @return list<string> */
    private function cfaHeaders(): array
    {
        return ['FY', 'Source DB', 'Application No', 'Applicant Name', 'District', 'Phone', 'CFA Date (Y-m-d)', 'Date basis', 'Raw ApplicationDate', 'Raw onboard_date', 'Onboarded'];
    }

    /** @return list<string> */
    private function onboardingHeaders(): array
    {
        return ['FY', 'Source DB', 'Application No', 'Applicant Name', 'District', 'Phone', 'Onboard Date (Y-m-d)', 'Date basis', 'Raw onboard date', 'Onboard flag', 'Batch name'];
    }

    /** @return list<string> */
    private function serviceHeaders(): array
    {
        return ['FY', 'Source DB', 'Application No', 'Applicant Name', 'District', 'Phone', 'Service Date (Y-m-d)', 'Category', 'Service', 'Detail', 'Date basis', 'Status'];
    }

    /** @return list<string> */
    private function marketHeaders(): array
    {
        return ['FY', 'Source DB', 'Application No', 'Applicant Name', 'District', 'Phone', 'Linkage Date (Y-m-d)', 'Partners / channel', 'Linkage mode', 'Date basis'];
    }
}
