<?php

namespace App\Http\Controllers;

use App\Models\FiscalYear;
use App\Models\User;
use App\Services\Deliverables\DeliverablesBreakdownPdfExport;
use App\Services\Deliverables\DeliverablesBreakdownSpreadsheetExport;
use App\Services\Deliverables\ProgramDeliverablesAchievementBreakdownService;
use App\Services\Deliverables\ProgramDeliverablesFilter;
use App\Services\Deliverables\ProgramDeliverablesScope;
use App\Services\ProgramDeliverablesReportService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
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
        private readonly ProgramDeliverablesAchievementBreakdownService $breakdownService,
        private readonly DeliverablesBreakdownSpreadsheetExport $breakdownSpreadsheetExport,
        private readonly DeliverablesBreakdownPdfExport $breakdownPdfExport,
    ) {}

    public function index(Request $request): View
    {
        $context = $this->resolveRequestContext($request);
        $payload = $this->buildReportPayload($context);

        return view('deliverables.index', $payload);
    }

    public function export(Request $request): StreamedResponse
    {
        $context = $this->resolveRequestContext($request);
        $payload = $this->buildReportPayload($context);
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

    public function breakdown(Request $request): JsonResponse
    {
        $serial = trim((string) $request->query('serial', ''));
        abort_if($serial === '', 422, 'Indicator serial is required.');

        $context = $this->resolveRequestContext($request);
        $payload = $this->buildReportPayload($context);
        $breakdown = $this->breakdownService->build($context['safeFilter'], $context['scope'], $serial);
        $row = collect($payload['rows'])->firstWhere('serial', $serial);

        return response()->json([
            ...$breakdown,
            'target' => is_array($row) ? ($row['target'] ?? null) : null,
            'achievement_pct' => is_array($row) ? ($row['achievement_pct'] ?? null) : null,
            'period_label' => $payload['periodLabel'],
            'scope_label' => $payload['scopeLabel'],
        ]);
    }

    public function breakdownExport(Request $request): StreamedResponse|\Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $context = $this->resolveBreakdownExportContext($request);

        return $this->breakdownSpreadsheetExport->download(
            $context['breakdown'],
            $context['row'],
            [
                'scope_label' => $context['payload']['scopeLabel'],
                'period_label' => $context['payload']['periodLabel'],
            ],
            $context['serial'],
        );
    }

    public function breakdownExportPdf(Request $request): \Symfony\Component\HttpFoundation\Response
    {
        $context = $this->resolveBreakdownExportContext($request);

        return $this->breakdownPdfExport->download(
            $context['breakdown'],
            $context['row'],
            $context['serial'],
            $context['payload']['scopeLabel'],
            $context['payload']['periodLabel'],
        );
    }

    /**
     * @return array{
     *     serial: string,
     *     breakdown: array<string, mixed>,
     *     row: array<string, mixed>|null,
     *     payload: array<string, mixed>
     * }
     */
    private function resolveBreakdownExportContext(Request $request): array
    {
        $serial = trim((string) $request->query('serial', ''));
        abort_if($serial === '', 422, 'Indicator serial is required.');

        $context = $this->resolveRequestContext($request);
        $payload = $this->buildReportPayload($context);
        $breakdown = $this->breakdownService->build($context['safeFilter'], $context['scope'], $serial);
        $row = collect($payload['rows'])->firstWhere('serial', $serial);

        return [
            'serial' => $serial,
            'breakdown' => $breakdown,
            'row' => is_array($row) ? $row : null,
            'payload' => $payload,
        ];
    }

  /**
     * @param  array{user: User, scope: ProgramDeliverablesScope, safeFilter: ProgramDeliverablesFilter}  $context
     * @return array<string, mixed>
     */
    private function buildReportPayload(array $context): array
    {
        $user = $context['user'];
        $scope = $context['scope'];
        $safeFilter = $context['safeFilter'];

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
            'breakdownRoute' => $this->routeNameFor($user, 'breakdown'),
            'breakdownExportRoute' => $this->routeNameFor($user, 'breakdown.export'),
            'breakdownExportPdfRoute' => $this->routeNameFor($user, 'breakdown.export.pdf'),
            'showStateTargetsLink' => $user->role === 'state_admin',
        ];
    }

    /**
     * @return array{user: User, scope: ProgramDeliverablesScope, safeFilter: ProgramDeliverablesFilter}
     */
    private function resolveRequestContext(Request $request): array
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

        return [
            'user' => $user,
            'scope' => $scope,
            'safeFilter' => $safeFilter,
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
