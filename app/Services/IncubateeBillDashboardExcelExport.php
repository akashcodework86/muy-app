<?php

namespace App\Services;

use App\Models\IncubateeBill;
use App\Services\Deliverables\Exports\DeliverablesExcelSupport as XL;
use App\Support\IndianRupees;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Symfony\Component\HttpFoundation\StreamedResponse;

class IncubateeBillDashboardExcelExport
{
    /**
     * @param  Collection<int, IncubateeBill>  $rows
     * @param  array{q: string, hub_id: int, district_id: int, from: string, to: string}  $filters
     */
    public function download(Collection $rows, array $filters): StreamedResponse
    {
        if (XL::isAvailable()) {
            return $this->xlsx($rows, $filters);
        }

        return $this->csv($rows);
    }

    /**
     * @param  Collection<int, IncubateeBill>  $rows
     * @param  array{q: string, hub_id: int, district_id: int, from: string, to: string}  $filters
     */
    private function xlsx(Collection $rows, array $filters): StreamedResponse
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Bills');
        $sheet->setCellValue('A1', 'Incubatee bills');
        $sheet->mergeCells('A1:L1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

        $metaRow = XL::writeMetaBlock($sheet, 3, [
            ['Exported', now()->format('d M Y H:i')],
            ['Bills', (string) $rows->count()],
            ['Total amount', IndianRupees::format($rows->sum(fn (IncubateeBill $b) => (float) $b->amount))],
            ['Search', $filters['q'] !== '' ? $filters['q'] : 'All'],
            ['From', $filters['from'] !== '' ? $filters['from'] : 'All'],
            ['To', $filters['to'] !== '' ? $filters['to'] : 'All'],
        ]);

        $headerRow = $metaRow + 1;
        $headers = [
            '#', 'Date', 'Bill number', 'Amount (INR)', 'Amount in words',
            'Applicant', 'Application no', 'Phone', 'District', 'Hub', 'Batch', 'Added by',
        ];
        $lastCol = XL::writeTableHeader($sheet, $headerRow, $headers);

        $r = $headerRow + 1;
        foreach ($rows->values() as $i => $bill) {
            $sheet->setCellValue('A'.$r, $i + 1);
            $sheet->setCellValue('B'.$r, $bill->bill_date?->format('Y-m-d') ?? '');
            $sheet->setCellValue('C'.$r, XL::sanitizeCell($bill->bill_number));
            $sheet->setCellValue('D'.$r, (float) $bill->amount);
            $sheet->setCellValue('E'.$r, XL::sanitizeCell(IndianRupees::inWords($bill->amount)));
            $sheet->setCellValue('F'.$r, XL::sanitizeCell($bill->cfaSubmission?->applicant_name ?? ''));
            $sheet->setCellValue('G'.$r, XL::sanitizeCell($bill->cfaSubmission?->application_no ?? ''));
            $sheet->setCellValue('H'.$r, XL::sanitizeCell($bill->cfaSubmission?->phone ?? ''));
            $sheet->setCellValue('I'.$r, XL::sanitizeCell($bill->district?->name ?? ''));
            $sheet->setCellValue('J'.$r, XL::sanitizeCell($bill->district?->hub?->name ?? ''));
            $sheet->setCellValue('K'.$r, XL::sanitizeCell($bill->batch?->name ?? ''));
            $sheet->setCellValue('L'.$r, XL::sanitizeCell($bill->creator?->name ?? ''));
            $r++;
        }

        if ($rows->isNotEmpty()) {
            XL::applyDataRowBorders($sheet, 'A'.($headerRow + 1).':'.$lastCol.($r - 1));
        }
        XL::autoSizeColumns($sheet, 'A', $lastCol);

        return XL::streamDownload($spreadsheet, 'incubatee-bills-'.now()->format('Ymd-His').'.xlsx');
    }

    /**
     * @param  Collection<int, IncubateeBill>  $rows
     */
    private function csv(Collection $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, [
                '#', 'Date', 'Bill number', 'Amount (INR)', 'Amount in words',
                'Applicant', 'Application no', 'Phone', 'District', 'Hub', 'Batch', 'Added by',
            ]);
            foreach ($rows->values() as $i => $bill) {
                fputcsv($out, [
                    $i + 1,
                    $bill->bill_date?->format('Y-m-d') ?? '',
                    $bill->bill_number,
                    number_format((float) $bill->amount, 2, '.', ''),
                    IndianRupees::inWords($bill->amount),
                    $bill->cfaSubmission?->applicant_name ?? '',
                    $bill->cfaSubmission?->application_no ?? '',
                    $bill->cfaSubmission?->phone ?? '',
                    $bill->district?->name ?? '',
                    $bill->district?->hub?->name ?? '',
                    $bill->batch?->name ?? '',
                    $bill->creator?->name ?? '',
                ]);
            }
            fclose($out);
        }, 'incubatee-bills-'.now()->format('Ymd-His').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
