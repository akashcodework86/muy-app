<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FiscalYear;
use App\Services\ProgramDeliverablesReportService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DeliverablesController extends Controller
{
    public function __construct(
        private readonly ProgramDeliverablesReportService $reportService,
    ) {}

    public function index(Request $request): View
    {
        $fiscalYearId = $request->query('fiscal_year_id') ? (int) $request->query('fiscal_year_id') : null;
        $report = $this->reportService->build($fiscalYearId);

        return view('admin.deliverables.index', [
            'fiscalYears' => FiscalYear::forUiDropdown(),
            'fiscalYearId' => $report['fiscalYear']?->id,
            'fiscalYear' => $report['fiscalYear'],
            'rows' => $report['rows'],
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $fiscalYearId = $request->query('fiscal_year_id') ? (int) $request->query('fiscal_year_id') : null;
        $report = $this->reportService->build($fiscalYearId);
        $fyLabel = $report['fiscalYear']?->name ?? 'all';

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Deliverables');

        $headers = ['S.N.', 'Indicator', 'Type of Indicator', 'Spoke/ Hub/ State', 'Targets', 'Achievement', 'Achievement (%)'];
        foreach ($headers as $colIndex => $label) {
            $col = chr(ord('A') + $colIndex);
            $sheet->setCellValue($col.'1', $label);
        }

        $sheet->getStyle('A1:G1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '9A3412']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        $rowNum = 2;
        foreach ($report['rows'] as $row) {
            $sheet->setCellValue('A'.$rowNum, $row['serial']);
            $sheet->setCellValue('B'.$rowNum, $row['name']);
            $sheet->setCellValue('C'.$rowNum, $row['indicator_type']);
            $sheet->setCellValue('D'.$rowNum, $row['level']);
            $sheet->setCellValue('E'.$rowNum, $row['target'] ?? '');
            $sheet->setCellValue('F'.$rowNum, $row['achievement']);
            $sheet->setCellValue('G'.$rowNum, $row['achievement_pct'] !== null ? $row['achievement_pct'].'%' : '');

            if ($row['row_type'] === 'pillar') {
                $sheet->getStyle('A'.$rowNum.':G'.$rowNum)->applyFromArray([
                    'font' => ['bold' => true],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FCE7F3']],
                ]);
            } elseif ($row['row_type'] === 'subcategory') {
                $sheet->getStyle('A'.$rowNum.':G'.$rowNum)->applyFromArray([
                    'font' => ['bold' => true],
                ]);
            }

            $rowNum++;
        }

        $fileName = 'deliverables-'.str_replace([' ', '/'], '-', $fyLabel).'-'.now()->format('Ymd').'.xlsx';

        return response()->streamDownload(function () use ($spreadsheet): void {
            (new Xlsx($spreadsheet))->save('php://output');
        }, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
