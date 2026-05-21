<?php

namespace App\Services\Deliverables;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DeliverablesBreakdownSpreadsheetExport
{
    /**
     * @param  array<string, mixed>  $breakdown
     * @param  array<string, mixed>|null  $row
     * @param  array<string, mixed>  $meta
     */
    public function download(array $breakdown, ?array $row, array $meta, string $serial): StreamedResponse|BinaryFileResponse
    {
        if (! class_exists(\ZipArchive::class)) {
            abort(503, 'Excel export requires the PHP Zip extension on the server.');
        }

        $target = is_array($row) ? ($row['target'] ?? null) : null;
        $achievementPct = is_array($row) ? ($row['achievement_pct'] ?? null) : null;

        $spreadsheet = new Spreadsheet;
        $summary = $spreadsheet->getActiveSheet();
        $summary->setTitle('Summary');
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
        $tempPath = tempnam(sys_get_temp_dir(), 'dlv-xlsx-');
        if ($tempPath === false) {
            abort(500, 'Could not create export file.');
        }

        $tempFile = $tempPath.'.xlsx';
        @unlink($tempPath);
        (new Xlsx($spreadsheet))->save($tempFile);
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

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
        $sheet->setTitle($this->sheetTitle($title));
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

        return mb_substr($value, 0, 32000);
    }
}
