<?php

namespace App\Http\Controllers;

use App\Models\FiscalYear;
use App\Models\User;
use App\Services\Deliverables\DeliverablesBreakdownCsvExport;
use App\Services\Deliverables\DeliverablesBreakdownPdfExport;
use App\Services\Deliverables\Exports\DeliverablesBreakdownExcelExport;
use App\Services\Deliverables\Exports\DeliverablesProgramExcelExport;
use App\Services\Deliverables\ProgramDeliverablesAchievementBreakdownService;
use App\Services\Deliverables\ProgramDeliverablesFilter;
use App\Services\Deliverables\ProgramDeliverablesScope;
use App\Services\ProgramDeliverablesReportService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DeliverablesReportController extends Controller
{
    public function __construct(
        private readonly ProgramDeliverablesReportService $reportService,
        private readonly ProgramDeliverablesAchievementBreakdownService $breakdownService,
        private readonly DeliverablesProgramExcelExport $programExcelExport,
        private readonly DeliverablesBreakdownExcelExport $breakdownExcelExport,
        private readonly DeliverablesBreakdownPdfExport $breakdownPdfExport,
        private readonly DeliverablesBreakdownCsvExport $breakdownCsvExport,
    ) {}

    public function index(Request $request): View
    {
        $context = $this->resolveRequestContext($request);
        $payload = $this->buildReportPayload($context);

        return view('deliverables.index', $payload);
    }

    public function export(Request $request): StreamedResponse
    {
        try {
            $context = $this->resolveRequestContext($request);
            $payload = $this->buildReportPayload($context);
            $report = $payload['report'];

            return $this->programExcelExport->download(
                $report['rows'],
                $payload['filter'],
                $payload['scopeLabel'],
                $payload['periodLabel'],
                $report['fiscalYear']?->name ?? 'all',
            );
        } catch (\RuntimeException $e) {
            abort(503, $e->getMessage());
        }
    }

    public function breakdown(Request $request): JsonResponse
    {
        $serial = trim((string) $request->query('serial', ''));
        abort_if($serial === '', 422, 'Indicator serial is required.');

        $context = $this->resolveRequestContext($request);
        $payload = $this->buildReportPayload($context);
        $breakdown = $this->breakdownService->build($context['safeFilter'], $context['scope'], $serial);
        $row = collect($payload['rows'])->firstWhere('serial', $serial);
        $breakdownTotal = (int) ($breakdown['total'] ?? 0);
        $target = is_array($row) ? ($row['target'] ?? null) : null;
        $achievementPct = is_array($row) ? ($row['achievement_pct'] ?? null) : null;
        if ($breakdownTotal > 0 && is_numeric($target) && (int) $target > 0) {
            $achievementPct = (int) round(($breakdownTotal / (int) $target) * 100);
        }

        return response()->json([
            ...$breakdown,
            'target' => $target,
            'achievement_pct' => $achievementPct,
            'period_label' => $payload['periodLabel'],
            'scope_label' => $payload['scopeLabel'],
        ]);
    }

    public function breakdownExport(Request $request): StreamedResponse
    {
        try {
            $context = $this->resolveBreakdownExportContext($request);

            return $this->breakdownExcelExport->download(
                $context['breakdown'],
                $context['row'],
                [
                    'scope_label' => $context['payload']['scopeLabel'],
                    'period_label' => $context['payload']['periodLabel'],
                ],
                $context['serial'],
            );
        } catch (\RuntimeException $e) {
            abort(503, $e->getMessage());
        } catch (\Throwable $e) {
            Log::error('Deliverables breakdown Excel export failed', [
                'serial' => $request->query('serial'),
                'message' => $e->getMessage(),
            ]);

            abort(500, 'Could not export breakdown to Excel. Try Download CSV or contact support.');
        }
    }

    public function breakdownExportCsv(Request $request): StreamedResponse
    {
        $context = $this->resolveBreakdownExportContext($request);

        return $this->breakdownCsvExport->download(
            $context['breakdown'],
            $context['row'],
            [
                'scope_label' => $context['payload']['scopeLabel'],
                'period_label' => $context['payload']['periodLabel'],
            ],
            $context['serial'],
        );
    }

    public function breakdownExportPdf(Request $request): Response
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
        $breakdown = $this->breakdownService->build($context['safeFilter'], $context['scope'], $serial);

        $payload = $this->buildExportMetaPayload($context, $serial);

        return [
            'serial' => $serial,
            'breakdown' => $breakdown,
            'row' => $payload['row'],
            'payload' => $payload,
        ];
    }

    /**
     * @param  array{user: User, scope: ProgramDeliverablesScope, safeFilter: ProgramDeliverablesFilter}  $context
     * @return array{scopeLabel: string, periodLabel: string, row: array<string, mixed>|null}
     */
    private function buildExportMetaPayload(array $context, string $serial): array
    {
        $scope = $context['scope'];
        $safeFilter = $context['safeFilter'];
        $scopeLabel = $scope->scopeLabel($safeFilter->districtId);

        try {
            $report = $this->reportService->build($safeFilter, $scope);
            [$periodFrom, $periodTo] = $safeFilter->resolvePeriod($report['fiscalYear']);
            $row = collect($report['rows'] ?? [])->firstWhere('serial', $serial);

            return [
                'scopeLabel' => $scopeLabel,
                'periodLabel' => $this->periodLabel($periodFrom, $periodTo, $safeFilter),
                'row' => is_array($row) ? $row : null,
            ];
        } catch (\Throwable $e) {
            Log::warning('Deliverables export meta build failed; continuing with breakdown only', [
                'serial' => $serial,
                'message' => $e->getMessage(),
            ]);

            $fiscalYears = FiscalYear::forUiDropdown();
            [$resolvedFyId] = FiscalYear::resolveIdForUi($safeFilter->fiscalYearId);
            $fiscalYear = $fiscalYears->firstWhere('id', $resolvedFyId);
            [$periodFrom, $periodTo] = $safeFilter->resolvePeriod($fiscalYear);

            return [
                'scopeLabel' => $scopeLabel,
                'periodLabel' => $this->periodLabel($periodFrom, $periodTo, $safeFilter),
                'row' => null,
            ];
        }
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
            'breakdownExportCsvRoute' => $this->routeNameFor($user, 'breakdown.export.csv'),
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
