<?php

namespace App\Services\Deliverables\Exports;

use App\Services\Deliverables\DeliverablesBreakdownCsvExport;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DeliverablesBreakdownExcelExport
{
    /** @var list<string> */
    private array $usedSheetTitles = [];

    public function __construct(
        private readonly DeliverablesBreakdownCsvExport $csvExport,
    ) {}

    /**
     * @param  array<string, mixed>  $breakdown
     * @param  array<string, mixed>|null  $row
     * @param  array{scope_label?: string, period_label?: string}  $meta
     */
    public function download(array $breakdown, ?array $row, array $meta, string $serial): StreamedResponse
    {
        if (! DeliverablesExcelSupport::isAvailable()) {
            Log::warning('Deliverables breakdown export: using CSV fallback', [
                'serial' => $serial,
                'reason' => DeliverablesExcelSupport::availabilityIssue(),
            ]);

            return $this->csvExport->download($breakdown, $row, $meta, $serial);
        }

        $target = is_array($row) ? ($row['target'] ?? null) : null;
        $achievementPct = is_array($row) ? ($row['achievement_pct'] ?? null) : null;

        $this->usedSheetTitles = [];

        $spreadsheet = new Spreadsheet;
        $this->buildSummarySheet(
            $spreadsheet->getActiveSheet(),
            $breakdown,
            $meta,
            $serial,
            $target,
            $achievementPct,
        );

        $this->writeDataSheet(
            $spreadsheet,
            'By District',
            ['District', 'Hub', 'Count', 'Share %'],
            collect($breakdown['by_district'] ?? [])->map(fn ($item) => [
                DeliverablesExcelSupport::sanitizeCell($item['district'] ?? ''),
                DeliverablesExcelSupport::sanitizeCell($item['hub'] ?? ''),
                (int) ($item['count'] ?? 0),
                ((int) ($item['share_pct'] ?? 0)).'%',
            ])->all(),
            centerFromColumn: 'C',
        );

        $this->writeDataSheet(
            $spreadsheet,
            'By Month',
            ['Month', 'Count', 'Share %'],
            collect($breakdown['by_month'] ?? [])->map(fn ($item) => [
                DeliverablesExcelSupport::sanitizeCell($item['month'] ?? ''),
                (int) ($item['count'] ?? 0),
                ((int) ($item['share_pct'] ?? 0)).'%',
            ])->all(),
            centerFromColumn: 'B',
        );

        if (($breakdown['by_service'] ?? []) !== []) {
            $this->writeDataSheet(
                $spreadsheet,
                'By Service',
                ['Service', 'Count', 'Share %'],
                collect($breakdown['by_service'])->map(fn ($item) => [
                    DeliverablesExcelSupport::sanitizeCell($item['service'] ?? ''),
                    (int) ($item['count'] ?? 0),
                    ((int) ($item['share_pct'] ?? 0)).'%',
                ])->all(),
                centerFromColumn: 'B',
            );
        }

        [$recordHeaders, $recordRows] = $this->recordTable($breakdown);
        $this->writeDataSheet($spreadsheet, 'Records', $recordHeaders, $recordRows, centerFromColumn: 'A');

        $slug = str_replace('.', '-', $serial);
        $fileName = 'deliverables-breakdown-'.$slug.'-'.now()->format('Ymd-His').'.xlsx';

        return DeliverablesExcelSupport::streamDownload($spreadsheet, $fileName);
    }

    /**
     * @param  array<string, mixed>  $breakdown
     * @param  array{scope_label?: string, period_label?: string}  $meta
     */
    private function buildSummarySheet(
        Worksheet $sheet,
        array $breakdown,
        array $meta,
        string $serial,
        mixed $target,
        mixed $achievementPct,
    ): void {
        $sheet->setTitle(DeliverablesExcelSupport::uniqueSheetTitle('Summary', $this->usedSheetTitles));

        $indicatorName = DeliverablesExcelSupport::sanitizeCell($breakdown['name'] ?? '');
        $sheet->setCellValue('A1', 'Achievement breakdown');
        $sheet->mergeCells('A1:B1');
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => DeliverablesExcelSupport::HEADER_FILL],
            ],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(26);

        DeliverablesExcelSupport::writeMetaBlock($sheet, 3, [
            ['Indicator', $indicatorName],
            ['S.N.', $serial],
            ['Scope', $meta['scope_label'] ?? ''],
            ['Period', $meta['period_label'] ?? ''],
            ['Target', $target !== null ? (string) $target : '-'],
            ['Achievement', (string) (int) ($breakdown['total'] ?? 0)],
            ['Achievement %', $achievementPct !== null ? $achievementPct.'%' : '-'],
            ['Source', DeliverablesExcelSupport::sanitizeCell($breakdown['source_type_label'] ?? '')],
            ['Generated at', now()->timezone(config('app.timezone'))->format('d M Y, g:i A T')],
        ]);

        DeliverablesExcelSupport::autoSizeColumns($sheet, 'A', 'B');
        $sheet->getColumnDimension('B')->setWidth(48);
    }

    /**
     * @param  list<string>  $headers
     * @param  list<list<mixed>>  $rows
     */
    private function writeDataSheet(
        Spreadsheet $spreadsheet,
        string $title,
        array $headers,
        array $rows,
        string $centerFromColumn = 'A',
    ): void {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle(DeliverablesExcelSupport::uniqueSheetTitle($title, $this->usedSheetTitles));

        $lastCol = DeliverablesExcelSupport::writeTableHeader($sheet, 1, $headers);

        if ($rows !== []) {
            $sheet->fromArray($rows, null, 'A2');
            $lastRow = count($rows) + 1;
            DeliverablesExcelSupport::applyDataRowBorders($sheet, 'A2:'.$lastCol.$lastRow);

            $centerStart = ord($centerFromColumn);
            $centerEnd = ord($lastCol);
            for ($code = $centerStart; $code <= $centerEnd; $code++) {
                $col = chr($code);
                $sheet->getStyle($col.'2:'.$col.$lastRow)
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            }
        }

        DeliverablesExcelSupport::autoSizeColumns($sheet, 'A', $lastCol);
    }

    /**
     * @param  array<string, mixed>  $breakdown
     * @return array{0: list<string>, 1: list<list<mixed>>}
     */
    private function recordTable(array $breakdown): array
    {
        $sourceType = (string) ($breakdown['source_type'] ?? '');
        $isFemaleParticipants = in_array($sourceType, ['field_work_participants', 'field_visit_participants'], true);
        $isFieldWorkshops = in_array($sourceType, ['field_work_workshops', 'field_visit_sessions'], true);
        $isBstParticipants = $sourceType === 'bst_participants';

        if ($isBstParticipants) {
            $headers = ['#', 'Incubatee Name', 'Application No.', 'District', 'Hub', 'Sessions Attended', 'Session Count'];
            $rows = collect($breakdown['records'] ?? [])->values()->map(fn ($item, $idx) => [
                $idx + 1,
                DeliverablesExcelSupport::sanitizeCell($item['applicant'] ?? ''),
                DeliverablesExcelSupport::sanitizeCell($item['reference'] ?? ''),
                DeliverablesExcelSupport::sanitizeCell($item['district'] ?? ''),
                DeliverablesExcelSupport::sanitizeCell($item['hub'] ?? ''),
                DeliverablesExcelSupport::sanitizeCell($item['service'] ?? ''),
                (int) ($item['session_count'] ?? 0),
            ])->all();
        } elseif ($isFemaleParticipants) {
            $headers = ['#', 'Participant Name', 'Gender', 'District', 'Hub', 'Gram Panchayat / Mobile', 'Workshop Ref', 'Visit Date'];
            $rows = collect($breakdown['records'] ?? [])->values()->map(fn ($item, $idx) => [
                $idx + 1,
                DeliverablesExcelSupport::sanitizeCell($item['applicant'] ?? ''),
                'Female',
                DeliverablesExcelSupport::sanitizeCell($item['district'] ?? ''),
                DeliverablesExcelSupport::sanitizeCell($item['hub'] ?? ''),
                DeliverablesExcelSupport::sanitizeCell($item['service'] ?? ''),
                DeliverablesExcelSupport::sanitizeCell($item['reference'] ?? ''),
                DeliverablesExcelSupport::sanitizeCell($item['date'] ?? ''),
            ])->all();
        } elseif ($isFieldWorkshops) {
            $headers = ['#', 'Reference', 'Type', 'Area / Block', 'District', 'Hub', 'Date'];
            $rows = collect($breakdown['records'] ?? [])->values()->map(fn ($item, $idx) => [
                $idx + 1,
                DeliverablesExcelSupport::sanitizeCell($item['reference'] ?? ''),
                DeliverablesExcelSupport::sanitizeCell($item['service'] ?? ''),
                DeliverablesExcelSupport::sanitizeCell($item['applicant'] ?? ''),
                DeliverablesExcelSupport::sanitizeCell($item['district'] ?? ''),
                DeliverablesExcelSupport::sanitizeCell($item['hub'] ?? ''),
                DeliverablesExcelSupport::sanitizeCell($item['date'] ?? ''),
            ])->all();
        } elseif ($sourceType === 'market_linkage_incubatees') {
            return $this->marketLinkageRecordTable($breakdown);
        } else {
            $headers = ['#', 'Reference', 'Applicant', 'District', 'Hub', 'Service', 'Status', 'Date'];
            $rows = collect($breakdown['records'] ?? [])->values()->map(fn ($item, $idx) => [
                $idx + 1,
                DeliverablesExcelSupport::sanitizeCell($item['reference'] ?? ''),
                DeliverablesExcelSupport::sanitizeCell($item['applicant'] ?? ''),
                DeliverablesExcelSupport::sanitizeCell($item['district'] ?? ''),
                DeliverablesExcelSupport::sanitizeCell($item['hub'] ?? ''),
                DeliverablesExcelSupport::sanitizeCell($item['service'] ?? ''),
                DeliverablesExcelSupport::sanitizeCell($item['status'] ?? ''),
                DeliverablesExcelSupport::sanitizeCell($item['date'] ?? ''),
            ])->all();
        }

        return [$headers, $rows];
    }

    /**
     * One Excel row per partner linkage so every stored field is exportable.
     *
     * @param  array<string, mixed>  $breakdown
     * @return array{0: list<string>, 1: list<list<mixed>>}
     */
    private function marketLinkageRecordTable(array $breakdown): array
    {
        $headers = [
            '#',
            'Application No.',
            'Incubatee',
            'Phone',
            'Block',
            'District',
            'Hub',
            'Source',
            'Status',
            'Submitted by',
            'Submitted on',
            'Partner name',
            'Linkage mode',
            'Linkage date',
            'Link / URL',
            'Bill',
            'Bill file',
        ];

        $rows = [];
        $serial = 0;
        foreach (array_values($breakdown['records'] ?? []) as $item) {
            $serial++;
            $partners = is_array($item['partner_rows'] ?? null) && $item['partner_rows'] !== []
                ? $item['partner_rows']
                : [[
                    'partner_name' => $item['service'] ?? '',
                    'linkage_mode_label' => $item['linkage_mode'] ?? '',
                    'linkage_date_display' => '',
                    'link_url' => '',
                    'has_document' => false,
                    'document_name' => '',
                ]];

            $phone = trim((string) ($item['phone'] ?? ''));
            if ($phone !== '' && preg_match('/^[\d\s+\-]{10,}$/', $phone)) {
                $phone = "\t".$phone;
            }

            $source = (string) ($item['source'] ?? '');
            $sourceLabel = $source === 'service_case' ? 'Service case' : 'Market linkage';
            $status = trim((string) ($item['status'] ?? ''));
            $statusLabel = $status !== '' ? ucfirst(str_replace('_', ' ', $status)) : 'Approved';

            foreach ($partners as $partner) {
                $partner = is_array($partner) ? $partner : [];
                $rows[] = [
                    $serial,
                    DeliverablesExcelSupport::sanitizeCell($item['reference'] ?? ''),
                    DeliverablesExcelSupport::sanitizeCell($item['applicant'] ?? ''),
                    DeliverablesExcelSupport::sanitizeCell($phone),
                    DeliverablesExcelSupport::sanitizeCell($item['block'] ?? ''),
                    DeliverablesExcelSupport::sanitizeCell($item['district'] ?? ''),
                    DeliverablesExcelSupport::sanitizeCell($item['hub'] ?? ''),
                    DeliverablesExcelSupport::sanitizeCell($sourceLabel),
                    DeliverablesExcelSupport::sanitizeCell($statusLabel),
                    DeliverablesExcelSupport::sanitizeCell($item['submitted_by'] ?? ''),
                    DeliverablesExcelSupport::sanitizeCell($item['date'] ?? ''),
                    DeliverablesExcelSupport::sanitizeCell($partner['partner_name'] ?? ''),
                    DeliverablesExcelSupport::sanitizeCell($partner['linkage_mode_label'] ?? ($item['linkage_mode'] ?? '')),
                    DeliverablesExcelSupport::sanitizeCell($partner['linkage_date_display'] ?? ''),
                    DeliverablesExcelSupport::sanitizeCell($partner['link_url'] ?? ''),
                    ! empty($partner['has_document']) ? 'Yes' : 'No',
                    DeliverablesExcelSupport::sanitizeCell($partner['document_name'] ?? ''),
                ];
            }
        }

        return [$headers, $rows];
    }
}
