<?php

namespace App\Services\Deliverables\Exports;

use App\Services\Deliverables\ProgramDeliverablesFilter;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DeliverablesProgramExcelExport
{
    public function __construct(
        private readonly DeliverablesProgramCsvExport $csvExport,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    public function download(
        array $rows,
        ProgramDeliverablesFilter $filter,
        string $scopeLabel,
        string $periodLabel,
        string $fiscalYearLabel,
    ): StreamedResponse {
        if (! DeliverablesExcelSupport::isAvailable()) {
            Log::warning('Deliverables program export: using CSV fallback', [
                'reason' => DeliverablesExcelSupport::availabilityIssue(),
            ]);

            return $this->csvExport->download($rows, $filter, $scopeLabel, $periodLabel, $fiscalYearLabel);
        }

        $showCumulative = $filter->hasExplicitDateFilter();
        $lastHeaderCol = $showCumulative ? 'J' : 'G';
        $cumulLabel = $showCumulative
            ? ($filter->cumulativeThroughLabel(null) ?? 'cumulative')
            : '';

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Deliverables');

        $sheet->setCellValue('A1', 'Program deliverables report');
        $sheet->mergeCells('A1:'.$lastHeaderCol.'1');
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => DeliverablesExcelSupport::HEADER_FILL],
            ],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(28);

        $tz = config('app.timezone');
        $metaEnd = DeliverablesExcelSupport::writeMetaBlock($sheet, 3, [
            ['Fiscal year', $fiscalYearLabel],
            ['Scope', $scopeLabel],
            ['Period', $periodLabel],
            ['Generated at', now()->timezone($tz)->format('d M Y, g:i A T')],
        ]);

        $headerRow = $metaEnd + 1;
        $headers = $showCumulative
            ? [
                'S.N.',
                'Indicator',
                'Type of Indicator',
                'Spoke/ Hub/ State',
                'Target (period)',
                'Achievement (period)',
                'Achievement % (period)',
                'Target ('.$cumulLabel.')',
                'Achievement ('.$cumulLabel.')',
                'Achievement % ('.$cumulLabel.')',
            ]
            : [
                'S.N.',
                'Indicator',
                'Type of Indicator',
                'Spoke/ Hub/ State',
                'Targets',
                'Achievement',
                'Achievement (%)',
            ];
        $lastCol = DeliverablesExcelSupport::writeTableHeader($sheet, $headerRow, $headers);

        $rowNum = $headerRow + 1;
        $dataStart = $rowNum;
        foreach ($rows as $row) {
            $isHeading = in_array($row['row_type'] ?? '', ['pillar', 'subcategory'], true);
            $sheet->setCellValue('A'.$rowNum, DeliverablesExcelSupport::sanitizeCell($row['serial'] ?? ''));
            $sheet->setCellValue('B'.$rowNum, DeliverablesExcelSupport::sanitizeCell($row['name'] ?? ''));
            $sheet->setCellValue('C'.$rowNum, $isHeading ? '' : DeliverablesExcelSupport::sanitizeCell($row['indicator_type'] ?? ''));
            $sheet->setCellValue('D'.$rowNum, $isHeading ? '' : DeliverablesExcelSupport::sanitizeCell($row['level'] ?? ''));
            $sheet->setCellValue('E'.$rowNum, $isHeading ? '' : DeliverablesExcelSupport::formatTargetCell($row));
            $sheet->setCellValue('F'.$rowNum, $isHeading ? '' : ($row['achievement'] ?? ''));
            $pct = $row['achievement_pct'] ?? null;
            $sheet->setCellValue('G'.$rowNum, $isHeading ? '' : ($pct !== null ? $pct.'%' : ''));

            if ($showCumulative) {
                $sheet->setCellValue(
                    'H'.$rowNum,
                    $isHeading ? '' : DeliverablesExcelSupport::formatTargetCell($row, 'cumul_target', 'cumul_target_label'),
                );
                $sheet->setCellValue('I'.$rowNum, $isHeading ? '' : ($row['cumul_achievement'] ?? ''));
                $cumulPct = $row['cumul_achievement_pct'] ?? null;
                $sheet->setCellValue('J'.$rowNum, $isHeading ? '' : ($cumulPct !== null ? $cumulPct.'%' : ''));
            }

            if ($isHeading) {
                DeliverablesExcelSupport::applyHeadingRowStyle($sheet, 'A'.$rowNum.':'.$lastHeaderCol.$rowNum);
            }

            $rowNum++;
        }

        if ($rowNum > $dataStart) {
            DeliverablesExcelSupport::applyDataRowBorders($sheet, 'A'.$dataStart.':'.$lastHeaderCol.($rowNum - 1));
            $sheet->getStyle('A'.$dataStart.':A'.($rowNum - 1))
                ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('C'.$dataStart.':'.$lastHeaderCol.($rowNum - 1))
                ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }

        DeliverablesExcelSupport::autoSizeColumns($sheet, 'A', $lastCol);
        $sheet->getColumnDimension('B')->setWidth(42);

        return DeliverablesExcelSupport::streamDownload(
            $spreadsheet,
            $this->buildFileName($fiscalYearLabel, $filter),
        );
    }

    private function buildFileName(string $fiscalYearLabel, ProgramDeliverablesFilter $filter): string
    {
        $suffix = $filter->districtId ? '-d'.$filter->districtId : '';
        if ($filter->month) {
            $suffix .= '-m'.$filter->month;
        }

        $fySlug = str_replace([' ', '/'], '-', $fiscalYearLabel);

        return 'deliverables-'.$fySlug.$suffix.'-'.now()->format('Ymd-His').'.xlsx';
    }
}
