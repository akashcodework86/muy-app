<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\District;
use App\Models\FiscalYear;
use App\Services\DataCentre\DataCentreFilter;
use App\Services\DataCentre\ProgramDataCentreService;
use App\Services\Exports\DistrictFullProgressPackService;
use App\Services\Exports\HomestayDetailsPackService;
use App\Services\Exports\OnboardedShgCboDistrictPackService;
use App\Services\Exports\OnboardedTurnoverWisePackService;
use App\Services\Exports\Phase3ShgCboReapPackDataService;
use App\Services\Exports\Phase3ShgCboReapPackExcelExport;
use App\Services\Exports\YearwiseIndicatorExcelExport;
use App\Services\Exports\YearwiseIndicatorWorkbookService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DataCentreController extends Controller
{
    public function __construct(
        private readonly ProgramDataCentreService $service,
        private readonly Phase3ShgCboReapPackDataService $shgCboReapPack,
        private readonly Phase3ShgCboReapPackExcelExport $shgCboReapExcel,
        private readonly OnboardedShgCboDistrictPackService $onboardedShgCboPack,
        private readonly OnboardedTurnoverWisePackService $onboardedTurnoverWisePack,
        private readonly HomestayDetailsPackService $homestayDetailsPack,
        private readonly DistrictFullProgressPackService $fullProgressPack,
        private readonly YearwiseIndicatorWorkbookService $yearwiseIndicators,
        private readonly YearwiseIndicatorExcelExport $yearwiseExcel,
    ) {}

    public function index(Request $request): View
    {
        [$viewMode, $dataScope] = $this->resolveParams($request);
        $filter = $this->resolveFilter($request, $viewMode, $dataScope);
        $phase3Fy = FiscalYear::phase3Default();
        $data = $this->service->build($viewMode, $dataScope, $filter);

        $yiFy = trim((string) $request->query('yi_fy', ''));
        $yiDistrictId = $request->integer('yi_district_id') ?: null;
        $yiDistrictName = null;
        if ($yiDistrictId) {
            $yiDistrictName = District::query()->where('id', $yiDistrictId)->value('name');
        }
        $yearwise = $this->yearwiseIndicators->dataCentreMatrix(
            $yiFy !== '' ? $yiFy : null,
            is_string($yiDistrictName) ? $yiDistrictName : null,
        );

        return view('admin.data-centre.index', array_merge($data, [
            'filter' => $filter,
            'phase3_fy' => $phase3Fy,
            'districts' => District::query()->orderBy('sort_order')->orderBy('name')->get(['id', 'name']),
            'fiscal_month_options' => DataCentreFilter::fiscalMonthOptions($phase3Fy),
            'fy_quarter_periods' => $phase3Fy?->fiscalQuarterPeriodsForJs() ?? [],
            'filter_form_dates' => $filter->formDates($phase3Fy),
            'yearwise' => $yearwise,
            'yi_fy' => $yiFy !== '' ? $yiFy : null,
            'yi_district_id' => $yiDistrictId,
        ]));
    }

    /**
     * Bust the page-level cache and redirect back to the Data Centre.
     */
    public function refresh(Request $request): RedirectResponse
    {
        $this->service->bustCache();
        $this->yearwiseIndicators->bustDataCentreCache();

        [$viewMode, $dataScope] = $this->resolveParams($request);
        $filter = $this->resolveFilter($request, $viewMode, $dataScope);
        $params = $this->routeParams($viewMode, $dataScope, $filter);
        foreach (['yi_fy', 'yi_district_id'] as $key) {
            $val = $request->input($key, $request->query($key));
            if ($val !== null && $val !== '') {
                $params[$key] = $val;
            }
        }

        return redirect()->route('admin.data-centre.index', $params)
            ->with('flash_success', 'Data refreshed — latest counts loaded from the database.');
    }

    /**
     * Export a single section as a UTF-8 CSV (Excel-compatible with BOM).
     */
    public function export(Request $request, string $section): StreamedResponse
    {
        $allowed = ['summary', 'cfa-by-district', 'gender-state', 'gender-district', 'education-state', 'education-district', 'employment-state', 'yearwise-indicators'];
        if (! in_array($section, $allowed, true)) {
            abort(404, 'Unknown section.');
        }

        [$viewMode, $dataScope] = $this->resolveParams($request);
        $filter = $this->resolveFilter($request, $viewMode, $dataScope);

        if ($section === 'yearwise-indicators') {
            $yiFy = trim((string) $request->query('yi_fy', ''));
            $yiDistrictId = $request->integer('yi_district_id') ?: null;
            $yiDistrictName = $yiDistrictId
                ? District::query()->where('id', $yiDistrictId)->value('name')
                : null;
            $matrix = $this->yearwiseIndicators->dataCentreMatrix(
                $yiFy !== '' ? $yiFy : null,
                is_string($yiDistrictName) ? $yiDistrictName : null,
            );
            $rows = [
                ['Year', 'CFA', 'Onboarding', 'Udyam registration', 'FSSAI', 'GST', 'Market linkage', 'Convergence'],
            ];
            foreach ($matrix['rows'] as $row) {
                $rows[] = [
                    $row['year'],
                    $row['cfa'],
                    $row['onboarding'],
                    $row['udyam'],
                    $row['fssai'],
                    $row['gst'],
                    $row['market_linkage'],
                    $row['convergence'],
                ];
            }
            $t = $matrix['totals'];
            $rows[] = ['Total', $t['cfa'], $t['onboarding'], $t['udyam'], $t['fssai'], $t['gst'], $t['market_linkage'], $t['convergence']];
            $filename = 'data-centre-yearwise-indicators-'.now()->format('Ymd_His').'.csv';

            return response()->streamDownload(function () use ($rows): void {
                $out = fopen('php://output', 'w');
                if ($out === false) {
                    return;
                }
                fwrite($out, "\xEF\xBB\xBF");
                foreach ($rows as $row) {
                    fputcsv($out, $row);
                }
                fclose($out);
            }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
        }

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
     * Excel export: summary + detailed sheets per indicator (respects yi_fy / yi_district_id filters).
     */
    public function exportYearwiseIndicatorsExcel(Request $request): StreamedResponse|RedirectResponse
    {
        @ini_set('memory_limit', '512M');
        @set_time_limit(300);

        try {
            if (! class_exists(\ZipArchive::class)) {
                return redirect()
                    ->route('admin.data-centre.index', $this->yearwiseFilterQuery($request))
                    ->withErrors(['export' => 'Excel export unavailable: PHP Zip extension (ext-zip) is not enabled on the server.']);
            }

            $yiFy = trim((string) $request->query('yi_fy', ''));
            $yiDistrictId = $request->integer('yi_district_id') ?: null;
            $yiDistrictName = $yiDistrictId
                ? District::query()->where('id', $yiDistrictId)->value('name')
                : null;

            $payload = $this->yearwiseIndicators->buildExportPayload(
                $yiFy !== '' ? $yiFy : null,
                is_string($yiDistrictName) ? $yiDistrictName : null,
            );

            return $this->yearwiseExcel->download($payload);
        } catch (\Throwable $e) {
            report($e);

            return redirect()
                ->route('admin.data-centre.index', $this->yearwiseFilterQuery($request))
                ->withErrors(['export' => 'Excel export failed: '.$e->getMessage()]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function yearwiseFilterQuery(Request $request): array
    {
        $query = [];
        $yiFy = trim((string) $request->query('yi_fy', ''));
        $yiDistrictId = $request->integer('yi_district_id') ?: null;
        if ($yiFy !== '') {
            $query['yi_fy'] = $yiFy;
        }
        if ($yiDistrictId) {
            $query['yi_district_id'] = $yiDistrictId;
        }

        return $query;
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

    /**
     * Phase 3 SHG members / CBO / 8.2 REAP Excel pack (counts + detail lists).
     */
    public function exportShgCboReapPack(): \Symfony\Component\HttpFoundation\BinaryFileResponse|\Illuminate\Http\RedirectResponse
    {
        @ini_set('memory_limit', '512M');
        @set_time_limit(300);

        try {
            if (! class_exists(\ZipArchive::class)) {
                return redirect()
                    ->route('admin.data-centre.index')
                    ->withErrors(['export' => 'Excel export unavailable: PHP Zip extension (ext-zip) is not enabled on the server.']);
            }

            $pack = $this->shgCboReapPack->build();
            $fileName = 'phase3-shg-cbo-reap-pack-'.now()->format('Ymd_His').'.xlsx';
            $tempPath = storage_path('app/temp/'.$fileName);
            $this->shgCboReapExcel->writeToPath($pack, $tempPath);
            unset($pack);

            return response()
                ->download($tempPath, $fileName, [
                    'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                ])
                ->deleteFileAfterSend(true);
        } catch (\Throwable $e) {
            report($e);

            return redirect()
                ->route('admin.data-centre.index')
                ->withErrors(['export' => 'Excel export failed: '.$e->getMessage()]);
        }
    }

    /**
     * Onboarded SHG / CBO / Individual Excel pack (Phase 1+2+3), optional district filter.
     */
    public function exportOnboardedShgCboIndividual(Request $request): \Symfony\Component\HttpFoundation\BinaryFileResponse|\Illuminate\Http\RedirectResponse
    {
        @ini_set('memory_limit', '512M');
        @set_time_limit(300);

        $districtId = $request->integer('district_id') ?: null;
        $districtSlug = trim((string) $request->query('district', ''));

        try {
            if (! class_exists(\ZipArchive::class)) {
                return redirect()
                    ->route('admin.data-centre.index')
                    ->withErrors(['export' => 'Excel export unavailable: PHP Zip extension (ext-zip) is not enabled on the server.']);
            }

            $data = $this->onboardedShgCboPack->build(
                $districtId ?: null,
                $districtId ? null : ($districtSlug !== '' ? $districtSlug : null),
            );
            $slug = (string) ($data['meta']['district_slug'] ?? 'all') ?: 'all';
            $fileName = 'onboarded-shg-cbo-individual-'.$slug.'-'.now()->format('Ymd_His').'.xlsx';
            $tempPath = storage_path('app/temp/'.$fileName);
            $this->onboardedShgCboPack->writeToPath($data, $tempPath);
            unset($data);

            return response()
                ->download($tempPath, $fileName, [
                    'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                ])
                ->deleteFileAfterSend(true);
        } catch (\Throwable $e) {
            report($e);

            return redirect()
                ->route('admin.data-centre.index')
                ->withErrors(['export' => 'Excel export failed: '.$e->getMessage()]);
        }
    }

    /**
     * Onboarded turnover-wise Excel (Phase 2 FY 2025–26 + Phase 3 FY 2026–27), optional district filter.
     */
    public function exportOnboardedTurnoverWise(Request $request): \Symfony\Component\HttpFoundation\BinaryFileResponse|\Illuminate\Http\RedirectResponse
    {
        @ini_set('memory_limit', '512M');
        @set_time_limit(300);

        $districtId = $request->integer('district_id') ?: null;
        $districtSlug = trim((string) $request->query('district', ''));

        try {
            if (! class_exists(\ZipArchive::class)) {
                return redirect()
                    ->route('admin.data-centre.index')
                    ->withErrors(['export' => 'Excel export unavailable: PHP Zip extension (ext-zip) is not enabled on the server.']);
            }

            $data = $this->onboardedTurnoverWisePack->build(
                $districtId ?: null,
                $districtId ? null : ($districtSlug !== '' ? $districtSlug : null),
            );
            $slug = (string) ($data['meta']['district_slug'] ?? 'all') ?: 'all';
            $fileName = 'onboarded-turnover-wise-'.$slug.'-'.now()->format('Ymd_His').'.xlsx';
            $tempPath = storage_path('app/temp/'.$fileName);
            $this->onboardedTurnoverWisePack->writeToPath($data, $tempPath);
            unset($data);

            return response()
                ->download($tempPath, $fileName, [
                    'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                ])
                ->deleteFileAfterSend(true);
        } catch (\Throwable $e) {
            report($e);

            return redirect()
                ->route('admin.data-centre.index')
                ->withErrors(['export' => 'Excel export failed: '.$e->getMessage()]);
        }
    }

    /**
     * Homestay details Excel (Phase 1+2+3), district + onboard scope filters.
     */
    public function exportHomestayDetails(Request $request): \Symfony\Component\HttpFoundation\BinaryFileResponse|\Illuminate\Http\RedirectResponse
    {
        @ini_set('memory_limit', '512M');
        @set_time_limit(300);

        $districtId = $request->integer('district_id') ?: null;
        $districtSlug = trim((string) $request->query('district', ''));
        $onboardScope = strtolower(trim((string) $request->query('onboard_scope', $request->input('onboard_scope', 'all'))));
        if (! in_array($onboardScope, ['all', 'onboarded', 'non_onboarded'], true)) {
            $onboardScope = 'all';
        }

        try {
            if (! class_exists(\ZipArchive::class)) {
                return redirect()
                    ->route('admin.data-centre.index')
                    ->withErrors(['export' => 'Excel export unavailable: PHP Zip extension (ext-zip) is not enabled on the server.']);
            }

            $data = $this->homestayDetailsPack->build(
                $districtId ?: null,
                $districtId ? null : ($districtSlug !== '' ? $districtSlug : null),
                $onboardScope,
            );
            $slug = (string) ($data['meta']['district_slug'] ?? 'all') ?: 'all';
            $scopeSlug = (string) ($data['meta']['onboard_scope'] ?? 'all');
            $fileName = 'homestay-details-'.$slug.'-'.$scopeSlug.'-'.now()->format('Ymd_His').'.xlsx';
            $tempPath = storage_path('app/temp/'.$fileName);
            $this->homestayDetailsPack->writeToPath($data, $tempPath);
            unset($data);

            return response()
                ->download($tempPath, $fileName, [
                    'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                ])
                ->deleteFileAfterSend(true);
        } catch (\Throwable $e) {
            report($e);

            return redirect()
                ->route('admin.data-centre.index')
                ->withErrors(['export' => 'Excel export failed: '.$e->getMessage()]);
        }
    }

    /**
     * Full progress Excel (Phase 1 2021-25 + Phase 2 2025-26 + Phase 3 2026-27), optional district filter.
     */
    public function exportFullProgress(Request $request): \Symfony\Component\HttpFoundation\BinaryFileResponse|\Illuminate\Http\RedirectResponse
    {
        @ini_set('memory_limit', '512M');
        @set_time_limit(300);

        $districtId = $request->integer('district_id') ?: null;
        $districtSlug = trim((string) $request->query('district', ''));

        try {
            if (! class_exists(\ZipArchive::class)) {
                return redirect()
                    ->route('admin.data-centre.index')
                    ->withErrors(['export' => 'Excel export unavailable: PHP Zip extension (ext-zip) is not enabled on the server.']);
            }

            $data = $this->fullProgressPack->build(
                $districtId ?: null,
                $districtId ? null : ($districtSlug !== '' ? $districtSlug : null),
            );
            $slug = (string) ($data['meta']['district_slug'] ?? 'all') ?: 'all';
            $fileName = 'full-progress-'.$slug.'-'.now()->format('Ymd_His').'.xlsx';
            $tempPath = storage_path('app/temp/'.$fileName);
            $this->fullProgressPack->writeToPath($data, $tempPath);
            unset($data);

            return response()
                ->download($tempPath, $fileName, [
                    'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                ])
                ->deleteFileAfterSend(true);
        } catch (\Throwable $e) {
            report($e);

            return redirect()
                ->route('admin.data-centre.index')
                ->withErrors(['export' => 'Excel export failed: '.$e->getMessage()]);
        }
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
