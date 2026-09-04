<?php

namespace App\Services\Exports;

use App\Models\District;
use App\Models\MentorshipRequest;
use App\Services\Deliverables\Exports\DeliverablesExcelSupport as XL;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MentorshipRequestDashboardExcelExport
{
    /**
     * @param  Collection<int, MentorshipRequest>  $rows
     * @param  array<string, int>  $totals
     * @param  array<string, mixed>  $filters
     * @param  array<string, array{label?: string}>  $categories
     */
    public function download(Collection $rows, array $totals, array $filters, array $categories): StreamedResponse
    {
        XL::ensureAvailable();

        $spreadsheet = new Spreadsheet;
        $used = [];
        $this->buildSummarySheet($spreadsheet->getActiveSheet(), $totals, $filters, $categories, $rows->count(), $used);
        $this->buildRequestsSheet($spreadsheet, $rows, $categories, $used);

        return XL::streamDownload($spreadsheet, 'mentorship-requests-'.now()->format('Ymd_His').'.xlsx');
    }

    /**
     * @param  array<string, int>  $totals
     * @param  array<string, mixed>  $filters
     * @param  array<string, array{label?: string}>  $categories
     * @param  list<string>  $used
     */
    private function buildSummarySheet(Worksheet $sheet, array $totals, array $filters, array $categories, int $exportedRows, array &$used): void
    {
        $sheet->setTitle(XL::uniqueSheetTitle('Summary', $used));
        $sheet->setCellValue('A1', 'Mentorship requests (5.2) — dashboard export');
        $sheet->mergeCells('A1:B1');
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4F46E5']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(26);

        $status = (string) ($filters['status'] ?? '');
        $category = (string) ($filters['category'] ?? '');
        $districtId = (int) ($filters['district_id'] ?? 0);
        $districtLabel = 'All';
        if ($districtId > 0) {
            $districtLabel = (string) (District::query()->find($districtId)?->name ?? $districtId);
        }

        $next = XL::writeMetaBlock($sheet, 3, [
            ['Generated at', now()->timezone(config('app.timezone'))->format('d M Y, g:i A T')],
            ['Search', ($filters['q'] ?? '') !== '' ? (string) $filters['q'] : '—'],
            ['Status', $status !== '' ? ucfirst($status) : 'All'],
            ['Category', $category !== '' ? (string) ($categories[$category]['label'] ?? $category) : 'All'],
            ['District', $districtLabel],
            ['From', ($filters['from'] ?? '') !== '' ? (string) $filters['from'] : '—'],
            ['To', ($filters['to'] ?? '') !== '' ? (string) $filters['to'] : '—'],
            ['Unique incubatees only', ! empty($filters['unique']) ? 'Yes' : 'No'],
            ['Rows in this file', (string) $exportedRows],
        ]);

        $next += 1;
        $sheet->setCellValue('A'.$next, 'Totals (current filters)');
        $sheet->getStyle('A'.$next)->getFont()->setBold(true);
        $next++;
        XL::writeMetaBlock($sheet, $next, [
            ['Total requests', (string) (int) ($totals['total'] ?? 0)],
            ['Unique incubatees', (string) (int) ($totals['unique'] ?? 0)],
            ['Pending', (string) (int) ($totals['pending'] ?? 0)],
            ['Scheduled', (string) (int) ($totals['scheduled'] ?? 0)],
            ['Done', (string) (int) ($totals['done'] ?? 0)],
            ['Cancelled', (string) (int) ($totals['cancelled'] ?? 0)],
        ]);

        $sheet->getColumnDimension('A')->setWidth(28);
        $sheet->getColumnDimension('B')->setWidth(42);
    }

    /**
     * @param  Collection<int, MentorshipRequest>  $rows
     * @param  array<string, array{label?: string}>  $categories
     * @param  list<string>  $used
     */
    private function buildRequestsSheet(Spreadsheet $spreadsheet, Collection $rows, array $categories, array &$used): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle(XL::uniqueSheetTitle('Requests', $used));

        $headers = [
            '#', 'Requested', 'Incubatee', 'Phone', 'CFA', 'District', 'Hub',
            'Category', 'Status', 'Session type', 'Session at', 'Meeting link',
            'Done on', 'Message',
        ];
        $lastCol = XL::writeTableHeader($sheet, 1, $headers);
        $sheet->getStyle('A1:'.$lastCol.'1')->getFill()->getStartColor()->setRGB('4F46E5');

        $excelRow = 2;
        foreach ($rows as $i => $row) {
            $session = $row->session;
            $sheet->setCellValue('A'.$excelRow, $i + 1);
            $sheet->setCellValue('B'.$excelRow, XL::sanitizeCell($row->created_at?->format('d M Y, h:i A') ?? ''));
            $sheet->setCellValue('C'.$excelRow, XL::sanitizeCell($row->cfaSubmission?->applicant_name ?: $row->requestedBy?->name ?: ''));
            $sheet->setCellValueExplicit('D'.$excelRow, XL::sanitizeCell((string) ($row->cfaSubmission?->phone ?? '')), DataType::TYPE_STRING);
            $sheet->setCellValue('E'.$excelRow, XL::sanitizeCell((string) ($row->cfaSubmission?->application_no ?? '')));
            $sheet->setCellValue('F'.$excelRow, XL::sanitizeCell((string) ($row->cfaSubmission?->district?->name ?? '')));
            $sheet->setCellValue('G'.$excelRow, XL::sanitizeCell((string) ($row->cfaSubmission?->district?->hub?->name ?? '')));
            $sheet->setCellValue('H'.$excelRow, XL::sanitizeCell((string) ($categories[$row->category]['label'] ?? $row->category)));
            $sheet->setCellValue('I'.$excelRow, XL::sanitizeCell(str_replace('_', ' ', (string) $row->status)));
            $sheet->setCellValue('J'.$excelRow, XL::sanitizeCell($session ? ucfirst((string) $session->kind) : ''));
            $sheet->setCellValue('K'.$excelRow, XL::sanitizeCell($session?->scheduled_at?->format('d M Y, h:i A') ?? ''));
            $sheet->setCellValue('L'.$excelRow, XL::sanitizeCell((string) ($session?->meeting_link ?? '')));
            $sheet->setCellValue('M'.$excelRow, XL::sanitizeCell($row->done_at?->format('d M Y') ?? ''));
            $sheet->setCellValue('N'.$excelRow, XL::sanitizeCell((string) ($row->comment ?? '')));
            $excelRow++;
        }

        if ($excelRow > 2) {
            XL::applyDataRowBorders($sheet, 'A2:'.$lastCol.($excelRow - 1));
        }
        XL::autoSizeColumns($sheet, 'A', $lastCol);
        $sheet->getColumnDimension('N')->setWidth(40);
    }
}
