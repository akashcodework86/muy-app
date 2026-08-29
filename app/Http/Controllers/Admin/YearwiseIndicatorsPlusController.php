<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\District;
use App\Services\DataCentre\YearwiseIndicatorsPlusRecordsService;
use App\Services\DataCentre\YearwiseIndicatorsWithJitLakhpatiService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class YearwiseIndicatorsPlusController extends Controller
{
    public function __construct(
        private readonly YearwiseIndicatorsWithJitLakhpatiService $service,
        private readonly YearwiseIndicatorsPlusRecordsService $recordsService,
    ) {}

    public function index(Request $request): View
    {
        $yiFy = trim((string) $request->query('yi_fy', ''));
        $yiDistrictId = $request->integer('yi_district_id') ?: null;
        $yiDistrictName = null;
        if ($yiDistrictId) {
            $yiDistrictName = District::query()->where('id', $yiDistrictId)->value('name');
        }

        try {
            $yearwise = $this->service->matrix(
                $yiFy !== '' ? $yiFy : null,
                is_string($yiDistrictName) ? $yiDistrictName : null,
            );
        } catch (\Throwable $e) {
            report($e);
            $yearwise = [
                'generated_at' => '',
                'years' => YearwiseIndicatorsWithJitLakhpatiService::DISPLAY_YEARS,
                'fy_filter' => $yiFy !== '' ? $yiFy : null,
                'district_filter' => is_string($yiDistrictName) ? $yiDistrictName : null,
                'rows' => [],
                'totals' => [
                    'cfa' => 0,
                    'onboarding' => 0,
                    'udyam' => 0,
                    'artisan_card' => 0,
                    'fssai' => 0,
                    'gst' => 0,
                    'market_linkage' => 0,
                    'convergence' => 0,
                ],
                'extras' => [],
                'note' => 'Could not load indicators: '.$e->getMessage(),
            ];
        }

        try {
            $districtMatrix = $this->service->allDistrictsMatrix($yiFy !== '' ? $yiFy : null);
        } catch (\Throwable $e) {
            report($e);
            $districtMatrix = [
                'years' => YearwiseIndicatorsWithJitLakhpatiService::DISPLAY_YEARS,
                'metrics' => ['cfa', 'onboarding', 'udyam', 'artisan_card', 'fssai', 'gst', 'market_linkage', 'convergence'],
                'tables' => [],
            ];
        }

        try {
            $onboardingBreakdown = $this->service->onboardingJitLakhpatiBreakdown();
        } catch (\Throwable $e) {
            report($e);
            $onboardingBreakdown = null;
        }

        return view('admin.yearwise-indicators-plus.index', [
            'yearwise' => $yearwise,
            'yi_fy' => $yiFy !== '' ? $yiFy : null,
            'yi_district_id' => $yiDistrictId,
            'districts' => District::query()->orderBy('sort_order')->orderBy('name')->get(['id', 'name']),
            'districtMatrix' => $districtMatrix,
            'onboardingBreakdown' => $onboardingBreakdown,
        ]);
    }

    public function records(Request $request): View
    {
        $filters = $this->recordsFiltersFromRequest($request);

        try {
            $payload = $this->recordsService->paginate($filters);
        } catch (\Throwable $e) {
            report($e);
            $payload = [
                'metric' => $filters['metric'],
                'metric_label' => YearwiseIndicatorsPlusRecordsService::METRICS[$filters['metric']] ?? $filters['metric'],
                'registration_label' => $this->recordsService->registrationLabel($filters['metric']),
                'scope' => $filters['scope'],
                'scope_label' => YearwiseIndicatorsPlusRecordsService::SCOPES[$filters['scope']] ?? $filters['scope'],
                'year' => $filters['year'],
                'phase' => $filters['phase'],
                'phase_label' => null,
                'years' => YearwiseIndicatorsWithJitLakhpatiService::DISPLAY_YEARS,
                'district' => $filters['district'],
                'source' => $filters['source'],
                'q' => $filters['q'],
                'total' => 0,
                'records' => new \Illuminate\Pagination\LengthAwarePaginator([], 0, 50, 1),
                'filter_options' => $this->recordsService->filterOptions(),
                'error' => $e->getMessage(),
            ];
        }

        return view('admin.yearwise-indicators-plus.records', [
            'payload' => $payload,
            'filters' => $filters,
            'districts' => District::query()->orderBy('sort_order')->orderBy('name')->get(['id', 'name']),
            'queryParams' => $this->recordsQueryParams($filters),
        ]);
    }

    public function exportRecordsCsv(Request $request): StreamedResponse
    {
        $filters = $this->recordsFiltersFromRequest($request);
        $rows = $this->recordsService->exportRows($filters);
        $filename = 'yearwise-plus-records-'.$filters['metric'].'-'.now()->format('Ymd_His').'.csv';

        return response()->streamDownload(function () use ($rows, $filters): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }
            fwrite($out, "\xEF\xBB\xBF");
            $regLabel = $this->recordsService->registrationLabel($filters['metric']);
            fputcsv($out, [
                'Name', 'Application No', 'Phone', 'District', 'Block', 'Sector', 'Product',
                $regLabel, 'Links', 'Service date', 'Year', 'Source', 'Category', 'Service', 'Detail', 'Status',
                'Source DB', 'Source Table', 'Record ID',
            ]);
            foreach ($rows as $row) {
                $links = [];
                foreach (is_array($row['market_links'] ?? null) ? $row['market_links'] : [] as $link) {
                    if (! is_array($link)) {
                        continue;
                    }
                    $label = trim((string) ($link['label'] ?? ''));
                    $url = trim((string) ($link['url'] ?? ''));
                    if ($url !== '') {
                        $links[] = ($label !== '' ? $label.': ' : '').$url;
                    } elseif ($label !== '') {
                        $links[] = $label;
                    }
                }
                fputcsv($out, [
                    $row['applicant_name'] ?? '',
                    $row['application_no'] ?? '',
                    $row['phone'] ?? '',
                    $row['district'] ?? '',
                    $row['block'] ?? '',
                    $row['sector'] ?? '',
                    $row['product'] ?? '',
                    $row['service_number'] ?? '',
                    implode(' | ', $links),
                    $row['date_used'] ?? '',
                    $row['year'] ?? '',
                    $row['source_label'] ?? '',
                    $row['category'] ?? '',
                    $row['service_label'] ?? '',
                    $row['detail'] ?? '',
                    $row['status'] ?? '',
                    $row['source_db'] ?? '',
                    $row['source_table'] ?? '',
                    $row['record_id'] ?? '',
                ]);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function exportRecordsExcel(Request $request): StreamedResponse
    {
        $filters = $this->recordsFiltersFromRequest($request);
        $rows = $this->recordsService->exportRows($filters);
        $filename = 'yearwise-plus-records-'.$filters['metric'].'-'.now()->format('Ymd_His').'.xlsx';

        return response()->streamDownload(function () use ($rows, $filters): void {
            $sheet = new Spreadsheet;
            $ws = $sheet->getActiveSheet();
            $ws->setTitle(mb_substr($filters['metric'], 0, 31));
            $regLabel = $this->recordsService->registrationLabel($filters['metric']);
            $headers = [
                'Name', 'Application No', 'Phone', 'District', 'Block', 'Sector', 'Product',
                $regLabel, 'Links', 'Service date', 'Year', 'Source', 'Category', 'Service', 'Detail', 'Status',
                'Source DB', 'Source Table', 'Record ID',
            ];
            $col = 'A';
            foreach ($headers as $h) {
                $ws->setCellValue($col.'1', $h);
                $col++;
            }
            $r = 2;
            foreach ($rows as $row) {
                $links = [];
                foreach (is_array($row['market_links'] ?? null) ? $row['market_links'] : [] as $link) {
                    if (! is_array($link)) {
                        continue;
                    }
                    $label = trim((string) ($link['label'] ?? ''));
                    $url = trim((string) ($link['url'] ?? ''));
                    if ($url !== '') {
                        $links[] = ($label !== '' ? $label.': ' : '').$url;
                    } elseif ($label !== '') {
                        $links[] = $label;
                    }
                }
                $vals = [
                    $row['applicant_name'] ?? '',
                    $row['application_no'] ?? '',
                    $row['phone'] ?? '',
                    $row['district'] ?? '',
                    $row['block'] ?? '',
                    $row['sector'] ?? '',
                    $row['product'] ?? '',
                    $row['service_number'] ?? '',
                    implode(' | ', $links),
                    $row['date_used'] ?? '',
                    $row['year'] ?? '',
                    $row['source_label'] ?? '',
                    $row['category'] ?? '',
                    $row['service_label'] ?? '',
                    $row['detail'] ?? '',
                    $row['status'] ?? '',
                    $row['source_db'] ?? '',
                    $row['source_table'] ?? '',
                    $row['record_id'] ?? '',
                ];
                $col = 'A';
                foreach ($vals as $v) {
                    $ws->setCellValue($col.$r, $v);
                    $col++;
                }
                $r++;
            }
            (new Xlsx($sheet))->save('php://output');
            $sheet->disconnectWorksheets();
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function refresh(Request $request): RedirectResponse
    {
        $this->service->bustCache();

        $params = [];
        $yiFy = trim((string) $request->input('yi_fy', $request->query('yi_fy', '')));
        $yiDistrictId = $request->integer('yi_district_id') ?: null;
        if ($yiFy !== '') {
            $params['yi_fy'] = $yiFy;
        }
        if ($yiDistrictId) {
            $params['yi_district_id'] = $yiDistrictId;
        }

        return redirect()
            ->route('admin.yearwise-indicators-plus.index', $params)
            ->with('flash_success', 'Data refreshed.');
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $yiFy = trim((string) $request->query('yi_fy', ''));
        $yiDistrictId = $request->integer('yi_district_id') ?: null;
        $yiDistrictName = $yiDistrictId
            ? District::query()->where('id', $yiDistrictId)->value('name')
            : null;

        $matrix = $this->service->matrix(
            $yiFy !== '' ? $yiFy : null,
            is_string($yiDistrictName) ? $yiDistrictName : null,
        );

        $rows = [
            ['Year', 'CFA', 'Onboarding', 'Udyam registration', 'Artisan card', 'FSSAI', 'GST', 'Market linkage', 'Convergence'],
        ];
        foreach ($matrix['rows'] as $row) {
            $rows[] = [
                $row['year'],
                $row['cfa'],
                $row['onboarding'],
                $row['udyam'],
                $row['artisan_card'] ?? 0,
                $row['fssai'],
                $row['gst'],
                $row['market_linkage'],
                $row['convergence'],
            ];
        }
        $t = $matrix['totals'];
        $rows[] = [
            'Total', $t['cfa'], $t['onboarding'], $t['udyam'], $t['artisan_card'] ?? 0,
            $t['fssai'], $t['gst'], $t['market_linkage'], $t['convergence'],
        ];

        $filename = 'yearwise-indicators-plus-jit-lakhpati-'.now()->format('Ymd_His').'.csv';

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

    /**
     * @return array{
     *     metric: string,
     *     scope: string,
     *     year: ?string,
     *     phase: ?string,
     *     district: ?string,
     *     source: string,
     *     q: string,
     *     page: int
     * }
     */
    private function recordsFiltersFromRequest(Request $request): array
    {
        $metric = trim((string) $request->query('metric', 'onboarding'));
        if (! isset(YearwiseIndicatorsPlusRecordsService::METRICS[$metric])) {
            $metric = 'onboarding';
        }

        $scope = trim((string) $request->query('scope', 'grand'));
        if (! isset(YearwiseIndicatorsPlusRecordsService::SCOPES[$scope])) {
            $scope = 'grand';
        }

        $year = trim((string) $request->query('year', ''));
        $year = $year !== '' ? $year : null;

        $phase = trim((string) $request->query('phase', ''));
        $phase = $phase !== '' ? $phase : null;

        $district = trim((string) $request->query('district', ''));
        if ($district === '' && $request->integer('district_id')) {
            $district = (string) (District::query()->where('id', $request->integer('district_id'))->value('name') ?? '');
        }
        $district = $district !== '' ? $district : null;

        $source = trim((string) $request->query('source', 'all'));
        if (! isset(YearwiseIndicatorsPlusRecordsService::SOURCES[$source])) {
            $source = 'all';
        }

        return [
            'metric' => $metric,
            'scope' => $scope,
            'year' => $year,
            'phase' => $phase,
            'district' => $district,
            'source' => $source,
            'q' => trim((string) $request->query('q', '')),
            'page' => max(1, (int) $request->query('page', 1)),
            'per_page' => YearwiseIndicatorsPlusRecordsService::PER_PAGE,
            'attach_docs' => true,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, string|int>
     */
    private function recordsQueryParams(array $filters): array
    {
        return array_filter([
            'metric' => $filters['metric'] ?? null,
            'scope' => $filters['scope'] ?? null,
            'year' => $filters['year'] ?? null,
            'phase' => $filters['phase'] ?? null,
            'district' => $filters['district'] ?? null,
            'source' => ($filters['source'] ?? 'all') !== 'all' ? ($filters['source'] ?? null) : null,
            'q' => ($filters['q'] ?? '') !== '' ? $filters['q'] : null,
        ], static fn ($v) => $v !== null && $v !== '');
    }
}
