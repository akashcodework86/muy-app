<?php

namespace App\Services\Targets\Exports;

use App\Services\Deliverables\Exports\DeliverablesExcelSupport;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

final class OfficialMonthlyTargetsExcelSupport
{
    public const HEADER_FILL = '9A3412';

    public const MONTH_HEADER_FILL = 'FFEDD5';

    public const SAVED_HEADER_FILL = 'FEF3C7';

    public const STATE_HEADER_FILL = 'DBEAFE';

    public const STATE_SECTION_FILL = '1E3A8A';

    public const UNMAPPED_FILL = 'FEF2F2';

    public const FOOTER_FILL = 'FFEDD5';

    public static function isAvailable(): bool
    {
        return DeliverablesExcelSupport::isAvailable();
    }

    public static function columnLetter(int $zeroBasedIndex): string
    {
        return DeliverablesExcelSupport::columnLetter($zeroBasedIndex);
    }

    public static function sanitize(mixed $value): string
    {
        return DeliverablesExcelSupport::sanitizeCell($value);
    }

    /**
     * @param  list<string>  $headers
     */
    public static function writeHeaderRow(Worksheet $sheet, int $row, array $headers, string $fillRgb = self::HEADER_FILL, bool $whiteText = true): string
    {
        $lastCol = self::columnLetter(max(count($headers) - 1, 0));
        foreach ($headers as $colIndex => $label) {
            $sheet->setCellValue(self::columnLetter($colIndex).$row, $label);
        }

        $font = ['bold' => true];
        if ($whiteText) {
            $font['color'] = ['rgb' => 'FFFFFF'];
        }

        $sheet->getStyle('A'.$row.':'.$lastCol.$row)->applyFromArray([
            'font' => $font,
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $fillRgb]],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'E7E5E4'],
                ],
            ],
        ]);
        $sheet->getRowDimension($row)->setRowHeight(22);

        return $lastCol;
    }

    public static function writeBlockTitle(Worksheet $sheet, int $row, string $title, string $lastCol): void
    {
        $sheet->setCellValue('A'.$row, self::sanitize($title));
        $sheet->mergeCells('A'.$row.':'.$lastCol.$row);
        $sheet->getStyle('A'.$row.':'.$lastCol.$row)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::HEADER_FILL]],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension($row)->setRowHeight(24);
    }

    public static function writeSectionTitle(Worksheet $sheet, int $row, string $title, string $lastCol, string $fillRgb = self::STATE_SECTION_FILL): void
    {
        $sheet->setCellValue('A'.$row, self::sanitize($title));
        $sheet->mergeCells('A'.$row.':'.$lastCol.$row);
        $sheet->getStyle('A'.$row.':'.$lastCol.$row)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $fillRgb]],
        ]);
        $sheet->getRowDimension($row)->setRowHeight(22);
    }

    public static function applyDataBorders(Worksheet $sheet, string $range): void
    {
        DeliverablesExcelSupport::applyDataRowBorders($sheet, $range);
    }

    public static function applyHeadingRow(Worksheet $sheet, string $range): void
    {
        DeliverablesExcelSupport::applyHeadingRowStyle($sheet, $range);
    }

    public static function applyRowFill(Worksheet $sheet, string $range, string $fillRgb, bool $bold = false): void
    {
        $style = [
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $fillRgb]],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'E7E5E4'],
                ],
            ],
        ];
        if ($bold) {
            $style['font'] = ['bold' => true];
        }
        $sheet->getStyle($range)->applyFromArray($style);
    }

    public static function setNumericCell(Worksheet $sheet, string $cell, int $value): void
    {
        $sheet->setCellValue($cell, $value);
        $sheet->getStyle($cell)->getNumberFormat()->setFormatCode('#,##0');
        $sheet->getStyle($cell)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    }

    /**
     * @param  array<int, string>  $monthLabels
     * @return list<string>
     */
    public static function districtTableHeaders(array $monthLabels): array
    {
        $headers = ['District'];
        foreach ($monthLabels as $label) {
            $headers[] = $label;
        }
        $headers[] = 'Total';

        return $headers;
    }

    public static function fySlug(string $fiscalYearName): string
    {
        return str_replace([' ', '/'], '-', trim($fiscalYearName));
    }
}
