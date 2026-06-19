<?php

namespace App\Services;

use App\Models\FieldCoordinatorAttendanceReport;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class FieldCoordinatorVisitReportExport
{
    private const COLOR_HEADER = 'FF4F46E5';

    private const COLOR_HEADER_TEXT = 'FFFFFFFF';

    private const COLOR_GROUP = 'FFEEF2FF';

    private const COLOR_SUBTOTAL = 'FFE0E7FF';

    private const COLOR_TITLE = 'FF312E81';

    private const COLOR_GRAND = 'FFC7D2FE';

    /**
     * @param  Collection<int, FieldCoordinatorAttendanceReport>  $reports
     */
    public function download(Collection $reports, string $filterSummary, string $filename): BinaryFileResponse
    {
        $spreadsheet = new Spreadsheet;

        $grouped = $this->groupByCoordinator($reports);

        $this->buildSummarySheet($spreadsheet->getActiveSheet(), $grouped, $filterSummary);
        $this->buildDetailSheet($spreadsheet->createSheet(), $grouped, $filterSummary);

        $spreadsheet->setActiveSheetIndex(0);

        $tempPath = tempnam(sys_get_temp_dir(), 'fc_export_').'.xlsx';
        (new Xlsx($spreadsheet))->save($tempPath);
        $spreadsheet->disconnectWorksheets();

        return response()->download($tempPath, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    /**
     * @param  Collection<int, FieldCoordinatorAttendanceReport>  $reports
     * @return list<array{
     *     name: string,
     *     designation: string,
     *     districts: list<string>,
     *     blocks: list<string>,
     *     gramPanchayats: list<string>,
     *     villages: list<string>,
     *     villageCount: int,
     *     male: int,
     *     female: int,
     *     total: int,
     *     visits: list<FieldCoordinatorAttendanceReport>
     * }>
     */
    private function groupByCoordinator(Collection $reports): array
    {
        $groups = [];

        foreach ($reports as $report) {
            $name = trim((string) $report->field_coordinator_name) !== ''
                ? (string) $report->field_coordinator_name
                : (string) ($report->coordinator?->name ?? 'Unknown');

            $key = $report->field_coordinator_user_id
                ? 'u:'.$report->field_coordinator_user_id
                : 'n:'.mb_strtolower($name);

            if (! isset($groups[$key])) {
                $groups[$key] = [
                    'name' => $name,
                    'designation' => (string) ($report->coordinator?->designationRecord?->name ?? ''),
                    'districts' => [],
                    'blocks' => [],
                    'gramPanchayats' => [],
                    'villages' => [],
                    'villageCount' => 0,
                    'male' => 0,
                    'female' => 0,
                    'total' => 0,
                    'visits' => [],
                ];
            }

            $district = trim((string) ($report->district?->name ?? ''));
            if ($district !== '' && ! in_array($district, $groups[$key]['districts'], true)) {
                $groups[$key]['districts'][] = $district;
            }

            $block = trim((string) ($report->block ?? ''));
            if ($block !== '' && ! in_array($block, $groups[$key]['blocks'], true)) {
                $groups[$key]['blocks'][] = $block;
            }

            $gp = trim((string) ($report->gramPanchayat?->name ?? ''));
            if ($gp !== '' && ! in_array($gp, $groups[$key]['gramPanchayats'], true)) {
                $groups[$key]['gramPanchayats'][] = $gp;
            }

            foreach ($this->villageNames($report) as $village) {
                if (! in_array($village, $groups[$key]['villages'], true)) {
                    $groups[$key]['villages'][] = $village;
                }
            }
            $groups[$key]['villageCount'] += (int) $report->villages_visited_total;

            $groups[$key]['male'] += (int) $report->participants_male_count;
            $groups[$key]['female'] += (int) $report->participants_female_count;
            $groups[$key]['total'] += (int) $report->participants_total;
            $groups[$key]['visits'][] = $report;
        }

        $result = array_values($groups);

        foreach ($result as &$group) {
            // When explicit village names exist they are more reliable than the counter.
            if ($group['villages'] !== []) {
                $group['villageCount'] = count($group['villages']);
            }
        }
        unset($group);

        usort($result, static fn ($a, $b): int => strcasecmp($a['name'], $b['name']));

        return $result;
    }

    /**
     * @return list<string>
     */
    private function villageNames(FieldCoordinatorAttendanceReport $report): array
    {
        $raw = $report->villages_covered;
        if (! is_array($raw)) {
            return [];
        }

        $names = [];
        foreach ($raw as $item) {
            if (is_string($item)) {
                $name = trim($item);
            } elseif (is_array($item)) {
                $name = trim((string) ($item['name'] ?? $item['village'] ?? ''));
            } else {
                $name = '';
            }

            if ($name !== '') {
                $names[] = $name;
            }
        }

        return $names;
    }

    /**
     * @param  list<array<string, mixed>>  $grouped
     */
    private function buildSummarySheet(Worksheet $sheet, array $grouped, string $filterSummary): void
    {
        $sheet->setTitle('Summary');

        $headers = [
            'S.No', 'Field Coordinator', 'Designation', 'District(s)',
            'Total Visits', 'Blocks Visited', 'Block Names',
            'Gram Panchayats', 'Gram Panchayat Names',
            'Villages Visited', 'Village Names',
            'Male', 'Female', 'Total Participants',
        ];
        $lastCol = 'N';

        $this->writeTitleBlock($sheet, $lastCol, 'Field Coordinator Visit Report — Summary', $filterSummary);

        $headerRow = 4;
        $sheet->fromArray($headers, null, 'A'.$headerRow);
        $this->styleHeaderRow($sheet, $headerRow, $lastCol);

        $row = $headerRow + 1;
        $sr = 0;
        $grandVisits = 0;
        $grandMale = 0;
        $grandFemale = 0;
        $grandTotal = 0;

        foreach ($grouped as $group) {
            $sr++;
            $visitCount = count($group['visits']);
            $grandVisits += $visitCount;
            $grandMale += $group['male'];
            $grandFemale += $group['female'];
            $grandTotal += $group['total'];

            $sheet->setCellValue('A'.$row, $sr);
            $sheet->setCellValue('B'.$row, $group['name']);
            $sheet->setCellValue('C'.$row, $group['designation'] !== '' ? $group['designation'] : '—');
            $sheet->setCellValue('D'.$row, $this->joinList($group['districts']));
            $sheet->setCellValue('E'.$row, $visitCount);
            $sheet->setCellValue('F'.$row, count($group['blocks']));
            $sheet->setCellValue('G'.$row, $this->joinList($group['blocks']));
            $sheet->setCellValue('H'.$row, count($group['gramPanchayats']));
            $sheet->setCellValue('I'.$row, $this->joinList($group['gramPanchayats']));
            $sheet->setCellValue('J'.$row, $group['villageCount']);
            $sheet->setCellValue('K'.$row, $this->joinList($group['villages']));
            $sheet->setCellValue('L'.$row, $group['male']);
            $sheet->setCellValue('M'.$row, $group['female']);
            $sheet->setCellValue('N'.$row, $group['total']);

            $row++;
        }

        if ($grouped === []) {
            $sheet->setCellValue('A'.$row, 'No records found for the selected filters.');
            $sheet->mergeCells('A'.$row.':'.$lastCol.$row);
            $sheet->getStyle('A'.$row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $row++;
        } else {
            $sheet->setCellValue('A'.$row, '');
            $sheet->setCellValue('B'.$row, 'GRAND TOTAL');
            $sheet->setCellValue('E'.$row, $grandVisits);
            $sheet->setCellValue('L'.$row, $grandMale);
            $sheet->setCellValue('M'.$row, $grandFemale);
            $sheet->setCellValue('N'.$row, $grandTotal);
            $this->styleGrandRow($sheet, $row, $lastCol);
            $row++;
        }

        $this->finishSheet($sheet, $headerRow, $row - 1, $lastCol, [
            'A' => 6, 'B' => 26, 'C' => 22, 'D' => 22, 'E' => 11, 'F' => 13,
            'G' => 34, 'H' => 14, 'I' => 38, 'J' => 13, 'K' => 34,
            'L' => 9, 'M' => 9, 'N' => 15,
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $grouped
     */
    private function buildDetailSheet(Worksheet $sheet, array $grouped, string $filterSummary): void
    {
        $sheet->setTitle('Visit Details');

        $headers = [
            'S.No', 'Field Coordinator', 'Designation', 'District',
            'Visit Date', 'Block', 'Gram Panchayat', 'Area',
            'Villages', 'Male', 'Female', 'Total',
        ];
        $lastCol = 'L';

        $this->writeTitleBlock($sheet, $lastCol, 'Field Coordinator Visit Report — Visit Details', $filterSummary);

        $headerRow = 4;
        $sheet->fromArray($headers, null, 'A'.$headerRow);
        $this->styleHeaderRow($sheet, $headerRow, $lastCol);

        $row = $headerRow + 1;

        if ($grouped === []) {
            $sheet->setCellValue('A'.$row, 'No records found for the selected filters.');
            $sheet->mergeCells('A'.$row.':'.$lastCol.$row);
            $sheet->getStyle('A'.$row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $row++;
            $this->finishSheet($sheet, $headerRow, $row - 1, $lastCol, [
                'A' => 6, 'B' => 24, 'C' => 20, 'D' => 18, 'E' => 13, 'F' => 18,
                'G' => 22, 'H' => 20, 'I' => 10, 'J' => 9, 'K' => 9, 'L' => 11,
            ]);

            return;
        }

        $sr = 0;
        foreach ($grouped as $group) {
            $groupStart = $row;

            // Coordinator banner row.
            $sheet->setCellValue('A'.$row, $group['name']
                .($group['designation'] !== '' ? ' ('.$group['designation'].')' : '')
                .' — '.$this->joinList($group['districts']));
            $sheet->mergeCells('A'.$row.':'.$lastCol.$row);
            $this->styleGroupRow($sheet, $row, $lastCol);
            $row++;

            $visits = $group['visits'];
            usort($visits, static function ($a, $b): int {
                $da = $a->visit_date?->timestamp ?? 0;
                $db = $b->visit_date?->timestamp ?? 0;

                return $da <=> $db;
            });

            foreach ($visits as $visit) {
                $sr++;
                $sheet->setCellValue('A'.$row, $sr);
                $sheet->setCellValue('B'.$row, $group['name']);
                $sheet->setCellValue('C'.$row, $group['designation'] !== '' ? $group['designation'] : '—');
                $sheet->setCellValue('D'.$row, (string) ($visit->district?->name ?? '—'));
                $sheet->setCellValue('E'.$row, $visit->visit_date?->format('d M Y') ?? '—');
                $sheet->setCellValue('F'.$row, trim((string) $visit->block) !== '' ? $visit->block : '—');
                $sheet->setCellValue('G'.$row, (string) ($visit->gramPanchayat?->name ?? '—'));
                $sheet->setCellValue('H'.$row, trim((string) $visit->area) !== '' ? $visit->area : '—');
                $sheet->setCellValue('I'.$row, (int) $visit->villages_visited_total);
                $sheet->setCellValue('J'.$row, (int) $visit->participants_male_count);
                $sheet->setCellValue('K'.$row, (int) $visit->participants_female_count);
                $sheet->setCellValue('L'.$row, (int) $visit->participants_total);
                $row++;
            }

            // Per-coordinator subtotal.
            $sheet->setCellValue('B'.$row, 'Subtotal — '.count($group['blocks']).' block(s), '
                .count($group['gramPanchayats']).' GP(s)');
            $sheet->setCellValue('I'.$row, $group['villageCount']);
            $sheet->setCellValue('J'.$row, $group['male']);
            $sheet->setCellValue('K'.$row, $group['female']);
            $sheet->setCellValue('L'.$row, $group['total']);
            $this->styleSubtotalRow($sheet, $row, $lastCol);
            $row++;

            $sheet->getRowDimension($groupStart)->setRowHeight(18);
        }

        $this->finishSheet($sheet, $headerRow, $row - 1, $lastCol, [
            'A' => 6, 'B' => 24, 'C' => 20, 'D' => 18, 'E' => 13, 'F' => 18,
            'G' => 22, 'H' => 20, 'I' => 10, 'J' => 9, 'K' => 9, 'L' => 11,
        ]);
    }

    private function writeTitleBlock(Worksheet $sheet, string $lastCol, string $title, string $filterSummary): void
    {
        $sheet->setCellValue('A1', $title);
        $sheet->mergeCells('A1:'.$lastCol.'1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(15)->getColor()->setARGB(self::COLOR_TITLE);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $sheet->getRowDimension(1)->setRowHeight(22);

        $sheet->setCellValue('A2', $filterSummary !== '' ? $filterSummary : 'All records');
        $sheet->mergeCells('A2:'.$lastCol.'2');
        $sheet->getStyle('A2')->getFont()->setItalic(true)->setSize(10)->getColor()->setARGB('FF64748B');

        $sheet->setCellValue('A3', 'Generated on '.now()->format('d M Y, h:i A'));
        $sheet->mergeCells('A3:'.$lastCol.'3');
        $sheet->getStyle('A3')->getFont()->setSize(9)->getColor()->setARGB('FF94A3B8');
    }

    private function styleHeaderRow(Worksheet $sheet, int $rowNum, string $lastCol): void
    {
        $range = 'A'.$rowNum.':'.$lastCol.$rowNum;
        $style = $sheet->getStyle($range);
        $style->getFont()->setBold(true)->getColor()->setARGB(self::COLOR_HEADER_TEXT);
        $style->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::COLOR_HEADER);
        $style->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
        $sheet->getRowDimension($rowNum)->setRowHeight(28);
    }

    private function styleGroupRow(Worksheet $sheet, int $rowNum, string $lastCol): void
    {
        $range = 'A'.$rowNum.':'.$lastCol.$rowNum;
        $style = $sheet->getStyle($range);
        $style->getFont()->setBold(true)->getColor()->setARGB(self::COLOR_TITLE);
        $style->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::COLOR_GROUP);
    }

    private function styleSubtotalRow(Worksheet $sheet, int $rowNum, string $lastCol): void
    {
        $range = 'A'.$rowNum.':'.$lastCol.$rowNum;
        $style = $sheet->getStyle($range);
        $style->getFont()->setBold(true);
        $style->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::COLOR_SUBTOTAL);
    }

    private function styleGrandRow(Worksheet $sheet, int $rowNum, string $lastCol): void
    {
        $range = 'A'.$rowNum.':'.$lastCol.$rowNum;
        $style = $sheet->getStyle($range);
        $style->getFont()->setBold(true);
        $style->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::COLOR_GRAND);
    }

    /**
     * @param  array<string, int>  $columnWidths
     */
    private function finishSheet(Worksheet $sheet, int $headerRow, int $lastRow, string $lastCol, array $columnWidths): void
    {
        foreach ($columnWidths as $col => $width) {
            $sheet->getColumnDimension($col)->setWidth($width);
        }

        if ($lastRow >= $headerRow) {
            $bordered = 'A'.$headerRow.':'.$lastCol.$lastRow;
            $sheet->getStyle($bordered)->getBorders()->getAllBorders()
                ->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('FFCBD5E1');
            $sheet->getStyle('A'.($headerRow + 1).':'.$lastCol.$lastRow)
                ->getAlignment()->setVertical(Alignment::VERTICAL_TOP)->setWrapText(true);
        }

        $sheet->freezePane('A'.($headerRow + 1));
    }

    /**
     * @param  list<string>  $items
     */
    private function joinList(array $items): string
    {
        return $items === [] ? '—' : implode(', ', $items);
    }
}
