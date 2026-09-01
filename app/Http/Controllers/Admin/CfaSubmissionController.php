<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\CfaSubmission;
use App\Models\Designation;
use App\Models\District;
use App\Models\DistrictBlock;
use App\Models\User;
use App\Services\Cfa\CfaFyOnboardingStatsService;
use App\Services\Cfa\CfaSubmissionListQuery;
use App\Services\CfaSubmissionAuditSnapshot;
use App\Services\LegacyPhase1ApplicationDetailService;
use App\Services\LegacyPhase2ApplicationDetailService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CfaSubmissionController extends Controller
{
    public function index(Request $request): View
    {
        $districts = District::orderBy('name')->get(['id', 'name']);
        $filters = $this->extractFilters($request);
        $scopeCounts = CfaSubmissionListQuery::scopeCounts($filters);
        $fyOnboarding = CfaFyOnboardingStatsService::breakdown(
            ! empty($filters['district_id']) ? (int) $filters['district_id'] : null
        );

        $submissions = CfaSubmissionListQuery::applyFilters(CfaSubmission::query(), $filters)
            ->with(['district', 'referralUser.designationRecord', 'fiscalYear', 'onboardingBatchMembership'])
            ->orderByDesc('created_at')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (CfaSubmission $row) => CfaSubmissionListQuery::enrichSubmission($row));

        $showDateWiseSummary = $filters['from'] !== '' && $filters['to'] !== '';
        $dateWiseCounts = $showDateWiseSummary
            ? CfaSubmissionListQuery::dateWiseCounts($filters, includeZeroDates: true)
            : [];

        return view('admin.cfa.index', [
            'submissions' => $submissions,
            'districts' => $districts,
            'blocks' => $this->blocksForFilter($filters['district_id'] ?? null),
            'sectors' => config('cfa.business_categories'),
            'designations' => Designation::query()->orderBy('sort_order')->orderBy('name')->get(['id', 'name']),
            'submitters' => User::query()
                ->whereHas('cfaSubmissions', fn (Builder $query) => CfaSubmissionListQuery::applyPhase3DashboardScope($query))
                ->orderBy('name')
                ->get(['id', 'name']),
            'filters' => $filters,
            'scopeCounts' => $scopeCounts,
            'fyOnboarding' => $fyOnboarding,
            'showDateWiseSummary' => $showDateWiseSummary,
            'dateWiseCounts' => $dateWiseCounts,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $filters = $this->extractFilters($request);
        $query = CfaSubmissionListQuery::applyFilters(CfaSubmission::query(), $filters)
            ->with(['district:id,name', 'referralUser:id,name,designation_id', 'referralUser.designationRecord:id,name', 'fiscalYear:id,code,name', 'onboardingBatchMembership']);

        $payloadColumnsMap = $this->discoverPayloadColumns((clone $query)->reorder());
        $payloadHeaders = array_keys($payloadColumnsMap);

        $baseHeaders = [
            'application_no',
            'submitted_at_ist',
            'applicant_name',
            'phone',
            'district',
            'block',
            'lgd_state_code',
            'lgd_district_code',
            'lgd_block_code',
            'source',
            'referral_staff',
            'referral_designation',
            'fiscal_year',
            'onboard_status',
        ];
        $headers = array_merge($baseHeaders, $payloadHeaders);

        $dateWiseCounts = CfaSubmissionListQuery::dateWiseCounts(
            $filters,
            includeZeroDates: $filters['from'] !== '' && $filters['to'] !== '',
        );
        $filename = 'cfa-applications-'.now()->format('Ymd_His').'.xlsx';

        return response()->streamDownload(function () use ($query, $headers, $payloadColumnsMap, $dateWiseCounts, $filters): void {
            $spreadsheet = new Spreadsheet;
            $summary = $spreadsheet->getActiveSheet();
            $summary->setTitle('Date-wise Summary');
            $summary->mergeCells('A1:B1');
            $summary->setCellValue('A1', 'CFA applications — Date-wise Summary');
            $period = $filters['from'] !== '' || $filters['to'] !== ''
                ? trim(($filters['from'] ?: 'Beginning').' to '.($filters['to'] ?: 'Till date'))
                : 'Current filtered scope';
            $summary->mergeCells('A2:B2');
            $summary->setCellValue('A2', $period);
            $summary->fromArray(['Submitted date', 'Forms received'], null, 'A4');

            $summaryRow = 5;
            foreach ($dateWiseCounts as $daily) {
                $summary->setCellValue('A'.$summaryRow, $daily['label']);
                $summary->setCellValue('B'.$summaryRow, $daily['count']);
                $summaryRow++;
            }
            if ($dateWiseCounts === []) {
                $summary->setCellValue('A'.$summaryRow, 'No applications');
                $summary->setCellValue('B'.$summaryRow, 0);
                $summaryRow++;
            }
            $summary->setCellValue('A'.$summaryRow, 'Total');
            $summary->setCellValue('B'.$summaryRow, array_sum(array_column($dateWiseCounts, 'count')));
            $summary->getStyle('A1:B1')->getFont()->setBold(true)->setSize(16)->getColor()->setARGB('FFFFFFFF');
            $summary->getStyle('A1:B1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF0F766E');
            $summary->getStyle('A1:B1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $summary->getStyle('A4:B4')->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
            $summary->getStyle('A4:B4')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF2563EB');
            $summary->getStyle('A4:B'.$summaryRow)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('FFD1D5DB');
            $summary->getStyle('A'.$summaryRow.':B'.$summaryRow)->getFont()->setBold(true);
            $summary->getStyle('A'.$summaryRow.':B'.$summaryRow)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFECFDF5');
            $summary->getColumnDimension('A')->setWidth(24);
            $summary->getColumnDimension('B')->setWidth(18);
            $summary->freezePane('A5');

            $dataSheet = $spreadsheet->createSheet();
            $dataSheet->setTitle('Applications');
            foreach ($headers as $index => $header) {
                $cell = Coordinate::stringFromColumnIndex($index + 1).'1';
                $dataSheet->setCellValueExplicit($cell, $header, DataType::TYPE_STRING);
            }

            $excelRow = 2;
            (clone $query)->reorder()->chunkById(500, function ($rows) use ($dataSheet, $payloadColumnsMap, &$excelRow): void {
                foreach ($rows as $row) {
                    $payload = is_array($row->payload) ? $row->payload : (array) $row->payload;
                    $record = [
                        $row->application_no ?? '',
                        optional($row->created_at)->timezone('Asia/Kolkata')->format('Y-m-d H:i:s') ?? '',
                        $row->applicant_name ?? '',
                        (string) ($row->phone ?? ''),
                        $row->district?->name ?? '',
                        $this->blockFromPayload($row),
                        $row->lgd_state_code ?? '',
                        $row->lgd_district_code ?? '',
                        $row->lgd_block_code ?? '',
                        $row->source ?? '',
                        $row->referralUser?->name ?? '',
                        $row->referralUser?->designationRecord?->name ?? '',
                        $row->fiscalYear?->code ?? $row->fiscalYear?->name ?? '',
                        $row->onboardingBatchMembership !== null ? 'Onboarded' : 'Non onboarded',
                    ];
                    foreach ($payloadColumnsMap as $originalPayloadKey) {
                        $record[] = $this->toCsvValue($payload[$originalPayloadKey] ?? null);
                    }
                    foreach ($record as $index => $value) {
                        $cell = Coordinate::stringFromColumnIndex($index + 1).$excelRow;
                        $dataSheet->setCellValueExplicit($cell, (string) ($value ?? ''), DataType::TYPE_STRING);
                    }
                    $excelRow++;
                }
            }, 'id');

            $lastColumn = Coordinate::stringFromColumnIndex(count($headers));
            $dataSheet->getStyle('A1:'.$lastColumn.'1')->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
            $dataSheet->getStyle('A1:'.$lastColumn.'1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF0F766E');
            $dataSheet->getStyle('A1:'.$lastColumn.'1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $dataSheet->freezePane('A2');
            $dataSheet->setAutoFilter('A1:'.$lastColumn.'1');
            foreach (range(1, count($headers)) as $columnIndex) {
                $width = match ($columnIndex) {
                    1 => 18,
                    2 => 21,
                    3 => 28,
                    4 => 16,
                    5, 6 => 20,
                    10, 11, 12 => 24,
                    default => 18,
                };
                $dataSheet->getColumnDimension(Coordinate::stringFromColumnIndex($columnIndex))->setWidth($width);
            }

            $spreadsheet->setActiveSheetIndex(0);
            (new Xlsx($spreadsheet))->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    private function toCsvValue(mixed $value): mixed
    {
        if (is_array($value) || is_object($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return $value;
    }

    /**
     * Union of all top-level JSON keys in `payload` across the filtered export scope.
     *
     * @return array<string, string> header column => original payload key
     */
    private function discoverPayloadColumns(Builder $query): array
    {
        $keys = [];

        $query->select(['id', 'payload'])->chunkById(1000, function ($rows) use (&$keys): void {
            foreach ($rows as $row) {
                if (! is_array($row->payload)) {
                    continue;
                }
                foreach (array_keys($row->payload) as $key) {
                    $key = trim((string) $key);
                    if ($key === '') {
                        continue;
                    }
                    $safe = preg_replace('/[^a-zA-Z0-9_]+/', '_', $key) ?: 'field';
                    $column = 'payload_'.$safe;
                    $suffix = 2;
                    while (isset($keys[$column]) && $keys[$column] !== $key) {
                        $column = 'payload_'.$safe.'_'.$suffix;
                        $suffix++;
                    }
                    $keys[$column] = $key;
                }
            }
        }, 'id');

        ksort($keys);

        return $keys;
    }

    /**
     * @return array{name: string, application_no: string, district_id: int|null, block: string, sector: string, caste: string, submitted_by: int|null, designation_id: int|null, from: string, to: string, onboard: string}
     */
    private function extractFilters(Request $request): array
    {
        $name = trim((string) $request->query('name', ''));
        $applicationNo = trim((string) $request->query('application_no', ''));
        $districtId = $request->query('district_id');
        $block = trim((string) $request->query('block', ''));
        $sector = trim((string) $request->query('sector', ''));
        $caste = CfaSubmissionListQuery::normalizeCasteParam($request);
        $submittedBy = $request->query('submitted_by');
        $designationId = $request->query('designation_id');
        $from = trim((string) $request->query('from', ''));
        $to = trim((string) $request->query('to', ''));
        $onboard = CfaSubmissionListQuery::normalizeOnboardParam($request);

        $v = Validator::make(
            ['from' => $from, 'to' => $to],
            [
                'from' => ['nullable', 'date_format:Y-m-d'],
                'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            ]
        );
        if ($v->fails()) {
            $from = '';
            $to = '';
        }

        return [
            'name' => $name,
            'application_no' => $applicationNo,
            'district_id' => $districtId ? (int) $districtId : null,
            'block' => $block,
            'sector' => $sector,
            'caste' => $caste,
            'submitted_by' => $submittedBy ? (int) $submittedBy : null,
            'designation_id' => $designationId ? (int) $designationId : null,
            'from' => $from,
            'to' => $to,
            'onboard' => $onboard,
        ];
    }

    /**
     * @return list<string>
     */
    private function blocksForFilter(?int $districtId): array
    {
        if ($districtId) {
            $blocks = DistrictBlock::orderedNamesForDistrict($districtId);
            if ($blocks !== []) {
                return $blocks;
            }

            $district = District::query()->find($districtId);
            if ($district) {
                return config('cfa.blocks_by_district.'.$district->name, []);
            }

            return [];
        }

        return DistrictBlock::query()
            ->orderBy('name')
            ->pluck('name')
            ->unique()
            ->values()
            ->all();
    }

    private function blockFromPayload(CfaSubmission $row): string
    {
        $payload = is_array($row->payload) ? $row->payload : (array) $row->payload;
        $block = trim((string) ($payload['block'] ?? ''));

        return $block !== '' ? $block : '';
    }

    public function show(CfaSubmission $cfa_submission): View
    {
        $cfa_submission->load(['district', 'referralUser', 'fiscalYear']);

        $cfaEditLogs = AuditLog::query()
            ->where('subject_type', CfaSubmission::class)
            ->where('subject_id', $cfa_submission->id)
            ->where('action', CfaSubmissionAuditSnapshot::ACTION_UPDATED)
            ->with('user')
            ->orderByDesc('created_at')
            ->get();

        $phase1Detail = app(LegacyPhase1ApplicationDetailService::class)->tryBuild($cfa_submission);
        if (is_array($phase1Detail) && isset($phase1Detail['viewRow'])) {
            return view('admin.cfa.phase1-legacy-detail', [
                'submission' => $cfa_submission,
                'legacyDetail' => $phase1Detail,
                'cfaIndexUrl' => route('admin.cfa.index', array_filter([
                    'fiscal_year_id' => $cfa_submission->fiscal_year_id,
                ])),
            ]);
        }

        $legacyDetail = app(LegacyPhase2ApplicationDetailService::class)->tryBuild($cfa_submission);
        if (is_array($legacyDetail) && isset($legacyDetail['viewRow'])) {
            return view('admin.cfa.legacy-detail', [
                'submission' => $cfa_submission,
                'legacyDetail' => $legacyDetail,
                'cfaIndexUrl' => route('admin.cfa.index', array_filter([
                    'fiscal_year_id' => $cfa_submission->fiscal_year_id,
                ])),
            ]);
        }

        return view('admin.cfa.show', [
            'submission' => $cfa_submission,
            'cfaIndexUrl' => route('admin.cfa.index', array_filter([
                'fiscal_year_id' => $cfa_submission->fiscal_year_id,
            ])),
            'cfaEditUrl' => null,
            'cfaEditLogs' => $cfaEditLogs,
        ]);
    }
}
