<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\District;
use App\Models\FiscalYear;
use App\Services\DataCentre\DataCentreFilter;
use App\Services\DataCentre\ProgramDataCentreService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DataCentreController extends Controller
{
    public function __construct(
        private readonly ProgramDataCentreService $service,
    ) {}

    public function index(Request $request): View
    {
        [$viewMode, $dataScope] = $this->resolveParams($request);
        $filter = $this->resolveFilter($request, $viewMode, $dataScope);
        $phase3Fy = FiscalYear::phase3Default();
        $data = $this->service->build($viewMode, $dataScope, $filter);

        return view('admin.data-centre.index', array_merge($data, [
            'filter' => $filter,
            'phase3_fy' => $phase3Fy,
            'districts' => District::query()->orderBy('sort_order')->orderBy('name')->get(['id', 'name']),
            'fiscal_month_options' => DataCentreFilter::fiscalMonthOptions($phase3Fy),
            'fy_quarter_periods' => $phase3Fy?->fiscalQuarterPeriodsForJs() ?? [],
            'filter_form_dates' => $filter->formDates($phase3Fy),
        ]));
    }

    /**
     * Bust the page-level cache and redirect back to the Data Centre.
     */
    public function refresh(Request $request): RedirectResponse
    {
        $this->service->bustCache();

        [$viewMode, $dataScope] = $this->resolveParams($request);
        $filter = $this->resolveFilter($request, $viewMode, $dataScope);

        return redirect()->route('admin.data-centre.index', $this->routeParams($viewMode, $dataScope, $filter))
            ->with('flash_success', 'Data refreshed — latest counts loaded from the database.');
    }

    /**
     * Export a single section as a UTF-8 CSV (Excel-compatible with BOM).
     */
    public function export(Request $request, string $section): StreamedResponse
    {
        $allowed = ['summary', 'cfa-by-district', 'gender-state', 'gender-district', 'education-state', 'education-district', 'employment-state'];
        if (! in_array($section, $allowed, true)) {
            abort(404, 'Unknown section.');
        }

        [$viewMode, $dataScope] = $this->resolveParams($request);
        $filter = $this->resolveFilter($request, $viewMode, $dataScope);
        $rows = $this->service->csvForSection($section, $dataScope, $filter, $viewMode);
        $scopeSuffix = $dataScope === 'onboarded' ? '-onboarded' : '';
        $filename = 'data-centre-'.$section.$scopeSuffix.'-'.now()->format('Ymd_His').'.csv';

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM for Excel
            foreach ($rows as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Export all sections as a single CSV workbook (multiple blocks separated by blank lines).
     */
    public function exportAll(Request $request): StreamedResponse
    {
        [$viewMode, $dataScope] = $this->resolveParams($request);
        $filter = $this->resolveFilter($request, $viewMode, $dataScope);
        $scopeSuffix = $dataScope === 'onboarded' ? '-onboarded' : '';
        $filename = 'data-centre-all-sections'.$scopeSuffix.'-'.now()->format('Ymd_His').'.csv';

        $sections = [
            'Program Summary' => 'summary',
            'CFA Applications by District' => 'cfa-by-district',
            'Gender - State Totals' => 'gender-state',
            'Gender - By District' => 'gender-district',
            'Education - State Totals' => 'education-state',
            'Education - By District' => 'education-district',
            'Employment Generation - State Totals' => 'employment-state',
        ];

        $note = $dataScope === 'onboarded'
            ? 'Onboarded only: P1 onboard=yes, P2 rbi_onboarded_applicants, P3 locked onboarding batches (includes legacy_phase2 onboarded via MIS).'
            : 'Combined counts exclude Phase 2 rows copied into Phase 3 (source=legacy_phase2).';

        return response()->streamDownload(function () use ($sections, $dataScope, $note, $filter, $viewMode): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, ['Program Data Centre — MUY MIS']);
            fputcsv($out, ['Generated at', now()->timezone('Asia/Kolkata')->format('d M Y, g:i A IST')]);
            fputcsv($out, ['Note:', $note]);
            fputcsv($out, []);

            foreach ($sections as $label => $key) {
                fputcsv($out, ['=== '.$label.' ===']);
                $rows = $this->service->csvForSection($key, $dataScope, $filter, $viewMode);
                foreach ($rows as $row) {
                    fputcsv($out, $row);
                }
                fputcsv($out, []);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** @return array{0: string, 1: string} */
    private function resolveParams(Request $request): array
    {
        $viewMode = $request->query('view') === 'rbiphase3' || $request->input('view') === 'rbiphase3'
            ? 'rbiphase3'
            : 'all';
        $dataScope = $request->query('scope') === 'onboarded' || $request->input('scope') === 'onboarded'
            ? 'onboarded'
            : 'all';

        return [$viewMode, $dataScope];
    }

    private function resolveFilter(Request $request, string $viewMode, string $dataScope): DataCentreFilter
    {
        if ($viewMode !== 'rbiphase3') {
            return DataCentreFilter::empty();
        }

        $filter = DataCentreFilter::fromRequest($request);

        return $filter;
    }

    /** @return array<string, int|string> */
    private function routeParams(string $viewMode, string $dataScope, ?DataCentreFilter $filter = null): array
    {
        $filter ??= DataCentreFilter::empty();
        $params = [];
        if ($viewMode === 'rbiphase3') {
            $params['view'] = 'rbiphase3';
            $params = array_merge($params, $filter->queryParams());
        }
        if ($dataScope === 'onboarded') {
            $params['scope'] = 'onboarded';
        }

        return $params;
    }
}
