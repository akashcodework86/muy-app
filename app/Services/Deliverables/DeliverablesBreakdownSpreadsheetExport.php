<?php

namespace App\Services\Deliverables;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DeliverablesBreakdownSpreadsheetExport
{
    /** @var list<string> */
    private array $usedSheetTitles = [];

    /**
     * @param  array<string, mixed>  $breakdown
     * @param  array<string, mixed>|null  $row
     * @param  array<string, mixed>  $meta
     */
    public function download(array $breakdown, ?array $row, array $meta, string $serial): BinaryFileResponse
    {
        if (! class_exists(\ZipArchive::class)) {
            throw new \RuntimeException('Excel export requires the PHP Zip extension on the server.');
        }

        if (! class_exists(Spreadsheet::class)) {
            throw new \RuntimeException('Excel export library is not available on the server.');
        }

        $target = is_array($row) ? ($row['target'] ?? null) : null;
        $achievementPct = is_array($row) ? ($row['achievement_pct'] ?? null) : null;

        $this->usedSheetTitles = [];

        $spreadsheet = new Spreadsheet;
        $summary = $spreadsheet->getActiveSheet();
        $summary->setTitle($this->uniqueSheetTitle('Summary'));
        $summary->fromArray([
            ['Indicator', $this->cell($breakdown['name'] ?? '')],
            ['S.N.', $serial],
            ['Scope', $this->cell($meta['scope_label'] ?? '')],
            ['Period', $this->cell($meta['period_label'] ?? '')],
            ['Target', $target !== null ? $target : '-'],
            ['Achievement', (int) ($breakdown['total'] ?? 0)],
            ['Achievement %', $achievementPct !== null ? $achievementPct.'%' : '-'],
            ['Source', $this->cell($breakdown['source_type_label'] ?? '')],
        ], null, 'A1');

        $this->writeSheet($spreadsheet, 'By District', ['District', 'Hub', 'Count', 'Share %'], collect($breakdown['by_district'] ?? [])->map(fn ($item) => [
            $this->cell($item['district'] ?? ''),
            $this->cell($item['hub'] ?? ''),
            (int) ($item['count'] ?? 0),
            ((int) ($item['share_pct'] ?? 0)).'%',
        ])->all());

        $this->writeSheet($spreadsheet, 'By Month', ['Month', 'Count', 'Share %'], collect($breakdown['by_month'] ?? [])->map(fn ($item) => [
            $this->cell($item['month'] ?? ''),
            (int) ($item['count'] ?? 0),
            ((int) ($item['share_pct'] ?? 0)).'%',
        ])->all());

        if (($breakdown['by_service'] ?? []) !== []) {
            $this->writeSheet($spreadsheet, 'By Service', ['Service', 'Count', 'Share %'], collect($breakdown['by_service'])->map(fn ($item) => [
                $this->cell($item['service'] ?? ''),
                (int) ($item['count'] ?? 0),
                ((int) ($item['share_pct'] ?? 0)).'%',
            ])->all());
        }

        $this->writeSheet($spreadsheet, 'Records', ['Reference', 'Applicant', 'District', 'Hub', 'Service', 'Status', 'Date'], collect($breakdown['records'] ?? [])->map(fn ($item) => [
            $this->cell($item['reference'] ?? ''),
            $this->cell($item['applicant'] ?? ''),
            $this->cell($item['district'] ?? ''),
            $this->cell($item['hub'] ?? ''),
            $this->cell($item['service'] ?? ''),
            $this->cell($item['status'] ?? ''),
            $this->cell($item['date'] ?? ''),
        ])->all());

        $slug = str_replace('.', '-', $serial);
        $fileName = 'deliverables-breakdown-'.$slug.'-'.now()->format('Ymd').'.xlsx';
        $tempFile = $this->tempXlsxPath();

        try {
            (new Xlsx($spreadsheet))->save($tempFile);
        } finally {
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);
        }

        return response()->download($tempFile, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    /**
     * @param  list<string>  $headers
     * @param  list<list<mixed>>  $rows
     */
    private function writeSheet(Spreadsheet $spreadsheet, string $title, array $headers, array $rows): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle($this->uniqueSheetTitle($title));
        $sheet->fromArray($headers, null, 'A1');
        if ($rows !== []) {
            $sheet->fromArray($rows, null, 'A2');
        }
        $lastCol = chr(ord('A') + max(count($headers) - 1, 0));
        $sheet->getStyle('A1:'.$lastCol.'1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '9A3412']],
        ]);
    }

    private function uniqueSheetTitle(string $title): string
    {
        $base = $this->sheetTitle($title);
        $candidate = $base;
        $suffix = 2;
        while (in_array($candidate, $this->usedSheetTitles, true)) {
            $tail = (string) $suffix;
            $candidate = substr($base, 0, max(31 - strlen($tail), 1)).$tail;
            $suffix++;
        }
        $this->usedSheetTitles[] = $candidate;

        return $candidate;
    }

    private function sheetTitle(string $title): string
    {
        $title = preg_replace('/[\\\\\\/\\?\\*\\[\\]:]/', ' ', $title) ?? 'Sheet';
        $title = trim($title) !== '' ? trim($title) : 'Sheet';

        return substr($title, 0, 31);
    }

    private function cell(mixed $value): string
    {
        $value = (string) $value;
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $value) ?? '';

        if (function_exists('mb_substr')) {
            return mb_substr($value, 0, 32000);
        }

        return substr($value, 0, 32000);
    }

    private function tempXlsxPath(): string
    {
        $dir = storage_path('app/temp/deliverables-exports');
        if (! is_dir($dir) && ! mkdir($dir, 0755, true) && ! is_dir($dir)) {
            throw new \RuntimeException('Could not create export directory.');
        }

        return $dir.'/breakdown-'.uniqid('', true).'.xlsx';
    }
}
