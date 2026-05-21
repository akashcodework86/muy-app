<?php

namespace App\Http\Controllers;

use App\Models\FiscalYear;
use App\Models\User;
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
            'target' => $row['target'] ?? null,
            'achievement_pct' => $row['achievement_pct'] ?? null,
            'period_label' => $payload['periodLabel'],
            'scope_label' => $payload['scopeLabel'],
        ]);
    }

    public function breakdownExport(Request $request): StreamedResponse
    {
        $serial = trim((string) $request->query('serial', ''));
        abort_if($serial === '', 422, 'Indicator serial is required.');

        $context = $this->resolveRequestContext($request);
        $payload = $this->buildReportPayload($context);
        $breakdown = $this->breakdownService->build($context['safeFilter'], $context['scope'], $serial);
        $row = collect($payload['rows'])->firstWhere('serial', $serial);

        $spreadsheet = new Spreadsheet;
        $summary = $spreadsheet->getActiveSheet();
        $summary->setTitle('Summary');
        $summary->fromArray([
            ['Indicator', $breakdown['name'] ?? ''],
            ['S.N.', $serial],
            ['Scope', $payload['scopeLabel']],
            ['Period', $payload['periodLabel']],
            ['Target', $row['target'] ?? '—'],
            ['Achievement', $breakdown['total'] ?? 0],
            ['Achievement %', $row['achievement_pct'] !== null ? $row['achievement_pct'].'%' : '—'],
            ['Source', $breakdown['source_type_label'] ?? ''],
        ], null, 'A1');

        $this->writeBreakdownSheet($spreadsheet, 'By District', ['District', 'Hub', 'Count', 'Share %'], collect($breakdown['by_district'] ?? [])->map(fn ($item) => [
            $item['district'] ?? '',
            $item['hub'] ?? '',
            $item['count'] ?? 0,
            ($item['share_pct'] ?? 0).'%',
        ])->all());

        $this->writeBreakdownSheet($spreadsheet, 'By Month', ['Month', 'Count', 'Share %'], collect($breakdown['by_month'] ?? [])->map(fn ($item) => [
            $item['month'] ?? '',
            $item['count'] ?? 0,
            ($item['share_pct'] ?? 0).'%',
        ])->all());

        if (($breakdown['by_service'] ?? []) !== []) {
            $this->writeBreakdownSheet($spreadsheet, 'By Service', ['Service', 'Count', 'Share %'], collect($breakdown['by_service'])->map(fn ($item) => [
                $item['service'] ?? '',
                $item['count'] ?? 0,
                ($item['share_pct'] ?? 0).'%',
            ])->all());
        }

        $this->writeBreakdownSheet($spreadsheet, 'Records', ['Reference', 'Applicant', 'District', 'Hub', 'Service', 'Status', 'Date'], collect($breakdown['records'] ?? [])->map(fn ($item) => [
            $item['reference'] ?? '',
            $item['applicant'] ?? '',
            $item['district'] ?? '',
            $item['hub'] ?? '',
            $item['service'] ?? '',
            $item['status'] ?? '',
            $item['date'] ?? '',
        ])->all());

        $slug = str_replace('.', '-', $serial);
        $fileName = 'deliverables-breakdown-'.$slug.'-'.now()->format('Ymd').'.xlsx';

        return response()->streamDownload(function () use ($spreadsheet): void {
            (new Xlsx($spreadsheet))->save('php://output');
        }, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * @param  list<list<mixed>>  $rows
     */
    private function writeBreakdownSheet(Spreadsheet $spreadsheet, string $title, array $headers, array $rows): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle(substr($title, 0, 31));
        $sheet->fromArray($headers, null, 'A1');
        if ($rows !== []) {
            $sheet->fromArray($rows, null, 'A2');
        }
        $lastCol = chr(ord('A') + max(count($headers) - 1, 0));
        $sheet->getStyle('A1:'.$lastCol.'1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '9A3412']],
        ]);
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
