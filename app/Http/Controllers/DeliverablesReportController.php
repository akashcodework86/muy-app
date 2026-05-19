<?php

namespace App\Http\Controllers;

use App\Models\FiscalYear;
use App\Models\User;
use App\Services\Deliverables\ProgramDeliverablesFilter;
use App\Services\Deliverables\ProgramDeliverablesScope;
use App\Services\ProgramDeliverablesReportService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DeliverablesReportController extends Controller
{
    public function __construct(
        private readonly ProgramDeliverablesReportService $reportService,
    ) {}

    public function index(Request $request): View
    {
        $payload = $this->buildReportPayload($request);

        return view('deliverables.index', $payload);
    }

    public function export(Request $request): StreamedResponse
    {
        $payload = $this->buildReportPayload($request);
        $report = $payload['report'];
        $fyLabel = $report['fiscalYear']?->name ?? 'all';
        $filter = $payload['filter'];

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
            $isHeading = in_array($row['row_type'] ?? '', ['pillar', 'subcategory'], true);
            $sheet->setCellValue('A'.$rowNum, $row['serial']);
            $sheet->setCellValue('B'.$rowNum, $row['name']);
            $sheet->setCellValue('C'.$rowNum, $isHeading ? '' : ($row['indicator_type'] ?? ''));
            $sheet->setCellValue('D'.$rowNum, $isHeading ? '' : ($row['level'] ?? ''));
            $sheet->setCellValue('E'.$rowNum, $isHeading ? '' : ($row['target'] ?? ''));
            $sheet->setCellValue('F'.$rowNum, $isHeading ? '' : ($row['achievement'] ?? ''));
            $sheet->setCellValue('G'.$rowNum, $isHeading ? '' : ($row['achievement_pct'] !== null ? $row['achievement_pct'].'%' : ''));

            if ($isHeading) {
                $sheet->getStyle('A'.$rowNum.':G'.$rowNum)->applyFromArray([
                    'font' => ['bold' => true],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFEDD5']],
                ]);
            }

            $rowNum++;
        }

        $suffix = $filter->districtId ? '-d'.$filter->districtId : '';
        if ($filter->month) {
            $suffix .= '-m'.$filter->month;
        }
        $fileName = 'deliverables-'.str_replace([' ', '/'], '-', $fyLabel).$suffix.'-'.now()->format('Ymd').'.xlsx';

        return response()->streamDownload(function () use ($spreadsheet): void {
            (new Xlsx($spreadsheet))->save('php://output');
        }, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

  /**
     * @return array<string, mixed>
     */
    private function buildReportPayload(Request $request): array
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $scope = ProgramDeliverablesScope::forUser($user);
        $filter = ProgramDeliverablesFilter::fromRequest($request);

        $allowedDistrictId = $scope->effectiveDistrictIds($filter->districtId);
        if ($allowedDistrictId === []) {
            abort(403, 'No district scope for deliverables.');
        }

        $safeFilter = new ProgramDeliverablesFilter(
            fiscalYearId: $filter->fiscalYearId,
            districtId: $filter->districtId && ($scope->districtIds === null || in_array($filter->districtId, $scope->districtIds, true))
                ? $filter->districtId
                : null,
            month: $filter->month,
            year: $filter->year,
            dateFrom: $filter->dateFrom,
            dateTo: $filter->dateTo,
        );

        $report = $this->reportService->build($safeFilter, $scope);
        [$periodFrom, $periodTo] = $safeFilter->resolvePeriod($report['fiscalYear']);

        return [
            'report' => $report,
            'filter' => $safeFilter,
            'scope' => $scope,
            'fiscalYears' => FiscalYear::forUiDropdown(),
            'fiscalYearId' => $report['fiscalYear']?->id,
            'fiscalYear' => $report['fiscalYear'],
            'rows' => $report['rows'],
            'districts' => $scope->districtsForDropdown(),
            'canPickDistrict' => $scope->canPickDistrict(),
            'scopeLabel' => $scope->scopeLabel($safeFilter->districtId),
            'periodLabel' => $this->periodLabel($periodFrom, $periodTo, $safeFilter),
            'indexRoute' => $this->routeNameFor($user, 'index'),
            'exportRoute' => $this->routeNameFor($user, 'export'),
            'showStateTargetsLink' => $user->role === 'state_admin',
        ];
    }

    private function periodLabel(?Carbon $from, ?Carbon $to, ProgramDeliverablesFilter $filter): string
    {
        if ($from && $to) {
            if ($filter->month) {
                return $from->format('F Y').' ('.$from->format('d M').' – '.$to->format('d M Y').')';
            }

            return $from->format('d M Y').' – '.$to->format('d M Y');
        }

        return 'Full fiscal year';
    }

    private function routeNameFor(User $user, string $action): string
    {
        return match ($user->role) {
            'hub_admin' => 'hub.deliverables.'.$action,
            'district_staff' => 'staff.deliverables.'.$action,
            'state_staff' => 'spoc.deliverables.'.$action,
            default => 'admin.deliverables.'.$action,
        };
    }
}
