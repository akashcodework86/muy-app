<?php

namespace App\Services\Deliverables\Exports;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class DeliverablesExcelSupport
{
    public const HEADER_FILL = '9A3412';

    public const HEADING_ROW_FILL = 'FFEDD5';

    public const META_LABEL_FILL = 'F8FAFC';

    public static function isAvailable(): bool
    {
        return class_exists(\ZipArchive::class) && class_exists(Spreadsheet::class);
    }

    public static function availabilityIssue(): ?string
    {
        if (! class_exists(\ZipArchive::class)) {
            return 'PHP Zip extension (ext-zip) is not enabled.';
        }

        if (! class_exists(Spreadsheet::class)) {
            return 'PhpSpreadsheet is not installed (run composer install --no-dev in muy-app on the server).';
        }

        return null;
    }

    /**
     * @throws \RuntimeException
     */
    public static function ensureAvailable(): void
    {
        $issue = self::availabilityIssue();
        if ($issue !== null) {
            throw new \RuntimeException('Excel export is unavailable: '.$issue);
        }
    }

    public static function sanitizeCell(mixed $value): string
    {
        $value = (string) $value;
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $value) ?? '';

        if (function_exists('mb_substr')) {
            return mb_substr($value, 0, 32000);
        }

        return substr($value, 0, 32000);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public static function formatTargetCell(array $row): string
    {
        $label = $row['target_label'] ?? null;
        if (is_string($label) && $label !== '') {
            return self::sanitizeCell($label);
        }

        $target = $row['target'] ?? null;

        return $target !== null ? self::sanitizeCell((string) $target) : '';
    }

    public static function columnLetter(int $zeroBasedIndex): string
    {
        $index = $zeroBasedIndex;
        $letters = '';
        do {
            $letters = chr(65 + ($index % 26)).$letters;
            $index = intdiv($index, 26) - 1;
        } while ($index >= 0);

        return $letters;
    }

    /**
     * @param  list<string>  $headers
     */
    public static function writeTableHeader(Worksheet $sheet, int $row, array $headers): string
    {
        $lastCol = self::columnLetter(max(count($headers) - 1, 0));
        foreach ($headers as $colIndex => $label) {
            $col = self::columnLetter($colIndex);
            $sheet->setCellValue($col.$row, $label);
        }

        $range = 'A'.$row.':'.$lastCol.$row;
        $sheet->getStyle($range)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::HEADER_FILL]],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'FFFFFF'],
                ],
            ],
        ]);
        $sheet->getRowDimension($row)->setRowHeight(22);
        $sheet->freezePane('A'.($row + 1));

        return $lastCol;
    }

    /**
     * @param  list<array{0: string, 1: string|int|float|null}>  $pairs
     */
    public static function writeMetaBlock(Worksheet $sheet, int $startRow, array $pairs): int
    {
        $row = $startRow;
        foreach ($pairs as [$label, $value]) {
            $sheet->setCellValue('A'.$row, $label);
            $sheet->setCellValue('B'.$row, self::sanitizeCell($value ?? ''));
            $sheet->getStyle('A'.$row)->applyFromArray([
                'font' => ['bold' => true],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::META_LABEL_FILL]],
            ]);
            $row++;
        }

        return $row;
    }

    public static function applyHeadingRowStyle(Worksheet $sheet, string $range): void
    {
        $sheet->getStyle($range)->applyFromArray([
            'font' => ['bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::HEADING_ROW_FILL]],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'E7E5E4'],
                ],
            ],
        ]);
    }

    public static function applyDataRowBorders(Worksheet $sheet, string $range): void
    {
        $sheet->getStyle($range)->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'E7E5E4'],
                ],
            ],
            'alignment' => ['vertical' => Alignment::VERTICAL_TOP, 'wrapText' => true],
        ]);
    }

    public static function autoSizeColumns(Worksheet $sheet, string $firstCol, string $lastCol): void
    {
        $start = ord($firstCol);
        $end = ord($lastCol);
        for ($code = $start; $code <= $end; $code++) {
            $sheet->getColumnDimension(chr($code))->setAutoSize(true);
        }
    }

    public static function streamDownload(Spreadsheet $spreadsheet, string $fileName): StreamedResponse
    {
        return response()->streamDownload(function () use ($spreadsheet): void {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'max-age=0, no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
        ]);
    }

    /**
     * @param  list<string>  $used
     */
    public static function uniqueSheetTitle(string $title, array &$used): string
    {
        $base = self::sheetTitle($title);
        $candidate = $base;
        $suffix = 2;
        while (in_array($candidate, $used, true)) {
            $tail = (string) $suffix;
            $candidate = substr($base, 0, max(31 - strlen($tail), 1)).$tail;
            $suffix++;
        }
        $used[] = $candidate;

        return $candidate;
    }

    private static function sheetTitle(string $title): string
    {
        $title = preg_replace('/[\\\\\\/\\?\\*\\[\\]:]/', ' ', $title) ?? 'Sheet';
        $title = trim($title) !== '' ? trim($title) : 'Sheet';

        return substr($title, 0, 31);
    }
}
