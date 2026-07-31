<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\District;
use App\Models\DistrictServiceSpoc;
use App\Models\MarketLinkageSubmission;
use App\Models\Service;
use App\Models\ServiceCase;
use App\Models\ServiceCaseAttachment;
use App\Models\User;
use App\Services\Admin\Phase3UnifiedMarketLinkageListBuilder;
use App\Services\LegacyApplicationServiceCaseSupport;
use App\Support\ConvergenceReapSupport;
use App\Support\ConvergenceReapSupportDeliverablesSupport;
use App\Support\MarketLinkageUnifiedListingSupport;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class Phase3ServiceCasesController extends Controller
{
    private bool $legacyPhase2JoinsApplied = false;

    private ?string $legacyPhase2DbSafe = null;

    public function __construct(
        private readonly Phase3UnifiedMarketLinkageListBuilder $unifiedMarketLinkageList,
    ) {}

    public function index(Request $request): View
    {
        $filters = $this->validatedFilters($request);

        $allowedStatuses = [
            ServiceCase::STATUS_DRAFT,
            ServiceCase::STATUS_PENDING_APPROVAL,
            ServiceCase::STATUS_APPROVED,
            ServiceCase::STATUS_SENT_BACK,
            ServiceCase::STATUS_REJECTED,
            ServiceCase::STATUS_CANCELLED,
        ];
        if (! in_array($filters['status'], $allowedStatuses, true)) {
            $filters['status'] = '';
        }

        $allowedTiers = [Service::REPORTING_UNSET, Service::REPORTING_KEY, Service::REPORTING_NON_KEY];
        if (! in_array($filters['reporting_tier'], $allowedTiers, true)) {
            $filters['reporting_tier'] = '';
        }

        if (! in_array($filters['has_docs'], ['', '1', '0'], true)) {
            $filters['has_docs'] = '';
        }

        if ($this->shouldMirrorSpocQueueWithMarketLinkages($filters)) {
            $listResult = $this->buildSpocAlignedCombinedList($filters, $request);
            $cases = $listResult['items'];
            $summary = $listResult['summary'];
            $unifiedMarketLinkage = false;
            $uniqueIncubateesView = false;
        } else {
            $listResult = $this->unifiedMarketLinkageList->build(
                $filters,
                fn (array $activeFilters) => $this->buildFilteredQuery($activeFilters),
                function ($query, array $activeFilters, bool $ignoreDistrictFilter = false, bool $ignoreStatusFilter = false): void {
                    $this->applyFilters(
                        $query,
                        $activeFilters,
                        $ignoreDistrictFilter,
                        $ignoreStatusFilter,
                    );
                },
            );

            $cases = $listResult['items'];
            $summary = $listResult['summary'];
            $unifiedMarketLinkage = $listResult['unified'];
            $uniqueIncubateesView = (bool) ($listResult['unique_incubatees'] ?? $filters['unique_incubatees']);

            if (! $unifiedMarketLinkage) {
                $summaryQuery = $this->buildFilteredQuery($filters);
                $this->applyFilters($summaryQuery, $filters, ignoreStatusFilter: true);

                $summaryRows = (clone $summaryQuery)
                    ->select('service_cases.status', DB::raw('COUNT(DISTINCT service_cases.id) as total'))
                    ->groupBy('service_cases.status')
                    ->pluck('total', 'status');

                $summary = array_merge($summary, [
                    'total' => (int) $summaryRows->sum(),
                    'approved' => (int) ($summaryRows[ServiceCase::STATUS_APPROVED] ?? 0),
                    'pending_approval' => (int) ($summaryRows[ServiceCase::STATUS_PENDING_APPROVAL] ?? 0),
                    'sent_back' => (int) ($summaryRows[ServiceCase::STATUS_SENT_BACK] ?? 0),
                    'rejected' => (int) ($summaryRows[ServiceCase::STATUS_REJECTED] ?? 0),
                ]);
            }
        }

        $statsQuery = $this->buildFilteredQuery($filters);
        $this->applyFilters($statsQuery, $filters, ignoreDistrictFilter: true, ignoreStatusFilter: true);

        if ($this->shouldMirrorSpocQueueWithMarketLinkages($filters)) {
            $districtCounts = $this->buildSpocAlignedDistrictCounts($filters);
        } else {
            $unifiedDistrictCounts = $this->unifiedMarketLinkageList->districtCounts(
                $filters,
                fn (array $activeFilters) => $this->buildFilteredQuery($activeFilters),
                function ($query, array $activeFilters, bool $ignoreDistrictFilter = false, bool $ignoreStatusFilter = false): void {
                    $this->applyFilters(
                        $query,
                        $activeFilters,
                        $ignoreDistrictFilter,
                        $ignoreStatusFilter,
                    );
                },
            );

            if ($unifiedDistrictCounts !== null) {
                $districtCounts = collect($unifiedDistrictCounts);
            } else {
                $districtCounts = District::query()
                    ->orderBy('name')
                    ->get(['id', 'name'])
                    ->map(function (District $district) use ($statsQuery): array {
                        $row = clone $statsQuery;
                        $this->constrainToLaravelDistrict($row, (int) $district->id);
                        $total = $this->countDistinctServiceCases($row);

                        return [
                            'id' => (int) $district->id,
                            'name' => (string) $district->name,
                            'total' => $total,
                        ];
                    });
            }
        }

        $legacyPreviews = ($unifiedMarketLinkage || $this->shouldMirrorSpocQueueWithMarketLinkages($filters))
            ? $this->buildLegacyPreviewMapFromUnifiedRows($cases->getCollection())
            : $this->buildLegacyPreviewMap($cases->getCollection());

        $givenByStaff = null;
        $givenByBreakdown = null;
        if ($filters['given_by_id'] > 0) {
            $givenByStaff = User::query()->find($filters['given_by_id'], ['id', 'name']);
            $givenByBreakdown = $this->buildGivenByServiceBreakdown($filters);
        }

        return view('admin.phase3-services.index', [
            'cases' => $cases,
            'summary' => $summary,
            'filters' => $filters,
            'unifiedMarketLinkage' => $unifiedMarketLinkage,
            'uniqueIncubateesView' => $uniqueIncubateesView,
            'services' => Service::query()->orderBy('name')->get(['id', 'name', 'service_category_id']),
            'districts' => District::query()->orderBy('name')->get(['id', 'name']),
            'districtCounts' => $districtCounts,
            'spocs' => User::query()
                ->where('role', 'state_staff')
                ->orderBy('name')
                ->get(['id', 'name']),
            'districtStaff' => User::query()
                ->where('role', 'district_staff')
                ->orderBy('name')
                ->get(['id', 'name']),
            'givenByStaff' => $givenByStaff,
            'givenByBreakdown' => $givenByBreakdown,
            'legacyPreviews' => $legacyPreviews,
        ]);
    }

    public function show(ServiceCase $service_case): View
    {
        $service_case->load([
            'service.category',
            'cfaSubmission.district',
            'submitter:id,name',
            'creator:id,name',
            'spoc:id,name',
            'approver:id,name',
            'attachments',
            'events.user',
        ]);

        $legacyIncubateePreview = null;
        if ($service_case->legacy_application_id && ! $service_case->cfa_submission_id) {
            $legacyIncubateePreview = app(LegacyApplicationServiceCaseSupport::class)
                ->incubateePreview((int) $service_case->legacy_application_id);
        }

        return view('admin.phase3-services.show', [
            'case' => $service_case,
            'legacyIncubateePreview' => $legacyIncubateePreview,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $filters = $this->validatedFilters($request);

        @ini_set('memory_limit', '256M');
        @set_time_limit(120);

        $unifiedRows = $this->unifiedMarketLinkageList->allRowsForExport(
            $filters,
            fn (array $activeFilters) => $this->buildFilteredQuery($activeFilters),
            function ($query, array $activeFilters, bool $ignoreDistrictFilter = false, bool $ignoreStatusFilter = false): void {
                $this->applyFilters($query, $activeFilters, $ignoreDistrictFilter, $ignoreStatusFilter);
            },
        );

        if ($unifiedRows === null && $this->shouldMirrorSpocQueueWithMarketLinkages($filters)) {
            $unifiedRows = $this->buildSpocAlignedCombinedRows($filters);
        }

        if ($unifiedRows !== null) {
            $legacyPreviews = $this->buildLegacyPreviewMapFromUnifiedRows($unifiedRows);
            $matrix = [];
            $sn = 0;
            foreach ($unifiedRows as $row) {
                $sn++;
                $matrix[] = $this->exportUnifiedRowValues($row, $sn, $legacyPreviews);
            }

            return $this->downloadExcelOrCsv($matrix, 'phase3-market-linkage-'.now()->format('Ymd_His'));
        }

        $query = $this->buildFilteredQuery($filters);
        $this->applyFilters($query, $filters);
        $query->setEagerLoads([])
            ->with([
                'service.category:id,name,slug',
                'cfaSubmission:id,application_no,applicant_name,district_id,phone,payload',
                'cfaSubmission.district:id,name',
                'submitter:id,name',
                'spoc:id,name',
                'approver:id,name',
            ])
            ->withCount('attachments');

        // Collect filtered rows in chunks so joins + filters still return every matching case.
        $matrix = [];
        $sn = 0;
        (clone $query)
            ->reorder()
            ->orderBy('service_cases.id')
            ->chunkById(250, function ($rows) use (&$matrix, &$sn): void {
                $legacyPreviews = $this->buildLegacyPreviewMap($rows);
                $legacyDetails = $this->buildLegacyPhase2ExportMap($rows);

                foreach ($rows as $case) {
                    $sn++;
                    $lp = $legacyPreviews[(int) ($case->legacy_application_id ?? 0)] ?? null;
                    $legacyId = (int) ($case->legacy_application_id ?? 0);
                    $legacyRow = ($legacyId > 0 && ! $case->cfa_submission_id)
                        ? ($legacyDetails[$legacyId] ?? null)
                        : null;

                    $matrix[] = $this->exportRowValues($case, $sn, $lp, $legacyRow);
                }
            }, 'service_cases.id', 'id');

        return $this->downloadExcelOrCsv($matrix, 'phase3-service-cases-'.now()->format('Ymd_His'));
    }

    /**
     * @param  list<list<string|int>>  $matrix
     */
    private function downloadExcelOrCsv(array $matrix, string $baseName): StreamedResponse
    {
        $headers = $this->exportColumnLabels();

        if (class_exists(\PhpOffice\PhpSpreadsheet\Spreadsheet::class)) {
            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet;
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Export');
            $sheet->fromArray($headers, null, 'A1');

            $rowNum = 2;
            foreach (array_chunk($matrix, 500) as $chunk) {
                $sheet->fromArray($chunk, null, 'A'.$rowNum);
                $rowNum += count($chunk);
            }

            $sheet->freezePane('A2');
            $fileName = $baseName.'.xlsx';

            return response()->streamDownload(function () use ($spreadsheet): void {
                $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
                $writer->setPreCalculateFormulas(false);
                $writer->save('php://output');
            }, $fileName, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Cache-Control' => 'max-age=0',
            ]);
        }

        $fileName = $baseName.'.csv';

        return response()->streamDownload(function () use ($headers, $matrix): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $headers, ',', '"', '\\');
            foreach ($matrix as $row) {
                fputcsv($out, $row, ',', '"', '\\');
            }
            fclose($out);
        }, $fileName, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'max-age=0',
        ]);
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<int, array{applicant_name: string, application_no: string, district: string}|null>  $legacyPreviews
     * @return list<string|int>
     */
    private function exportUnifiedRowValues(array $row, int $sn, array $legacyPreviews): array
    {
        $case = $row['service_case'] ?? null;
        $ml = $row['market_linkage'] ?? null;
        $partner = $row['partner'] ?? null;

        if ($case instanceof ServiceCase) {
            $lp = $legacyPreviews[(int) ($case->legacy_application_id ?? 0)] ?? null;

            return $this->exportRowValues($case, $sn, $lp, null);
        }

        $applicationNo = (string) ($ml?->application_no ?? '');
        $applicantName = (string) ($ml?->incubatee_name ?? '');
        $district = (string) ($ml?->district_name ?? $ml?->cfaSubmission?->district?->name ?? '');
        $phone = (string) ($ml?->cfaSubmission?->phone ?? '');
        $partnerName = (string) ($row['partner_name'] ?? ($partner?->partner_name ?? ''));
        $linkageMode = (string) ($row['linkage_mode'] ?? '');
        $status = (string) ($ml?->status ?? '');

        if ($phone !== '' && preg_match('/^[\d\s+\-]{10,}$/', $phone)) {
            $phone = "\t".$phone;
        }

        return [
            $sn,
            (string) ($ml?->application_no ?: ''),
            $applicationNo,
            $applicantName,
            $district,
            $phone,
            '',
            '',
            $partnerName,
            '',
            '',
            'Market Linkage',
            MarketLinkageSubmission::SERVICE_LIST_LABEL.($linkageMode !== '' && $linkageMode !== '—' ? ' ('.$linkageMode.')' : ''),
            'KEY',
            ucfirst(str_replace('_', ' ', $status)),
            $this->fmtDate($ml?->sla_deadline_at),
            $this->fmtDate($ml?->submitted_at),
            (string) ($ml?->submitted_by_name ?? $ml?->submitter?->name ?? ''),
            (string) ($ml?->spoc?->name ?? 'Unassigned'),
            (string) ($ml?->approver?->name ?? ''),
            $this->fmtDate($ml?->created_at),
            0,
            match ($status) {
                ServiceCase::STATUS_SENT_BACK => (string) ($ml?->sent_back_note ?? ''),
                ServiceCase::STATUS_REJECTED => (string) ($ml?->rejected_note ?? ''),
                default => '',
            },
        ];
    }

    /**
     * @return list<string>
     */
    private function exportColumnLabels(): array
    {
        return [
            '#',
            'Reference Number',
            'Application Number',
            'Applicant Name',
            'District',
            'Applicant Phone',
            'Block',
            'Sector',
            'Product',
            'Village',
            'Pincode',
            'Service Category',
            'Service Name',
            'Reporting Tier',
            'Status',
            'SLA Deadline',
            'Submitted At',
            'Submitted By',
            'SPOC',
            'Approved By',
            'Created At',
            'Documents Count',
            'SPOC remark',
        ];
    }

    /**
     * @param  array{applicant_name: string, application_no: string, district: string}|null  $legacyPreview
     * @param  array<string, string>|null  $legacyRow
     * @return list<string|int>
     */
    private function exportRowValues(ServiceCase $case, int $sn, ?array $legacyPreview, ?array $legacyRow): array
    {
        $payload = is_array($case->cfaSubmission?->payload) ? $case->cfaSubmission->payload : [];

        if (is_array($legacyRow)) {
            $applicationNo = (string) ($legacyRow['application_no'] ?? '');
            $applicantName = (string) ($legacyRow['applicant_name'] ?? '');
            $district = (string) ($legacyRow['district'] ?? '');
            $phone = (string) ($legacyRow['phone'] ?? '');
            $block = (string) ($legacyRow['block'] ?? '');
            $village = (string) ($legacyRow['village'] ?? '');
            $sector = (string) ($legacyRow['business_category'] ?? '');
            $product = (string) ($legacyRow['product'] ?? '');
            $pincode = '';
        } else {
            $applicationNo = (string) ($case->cfaSubmission?->application_no ?? ($legacyPreview['application_no'] ?? ''));
            $applicantName = (string) ($case->cfaSubmission?->applicant_name ?? ($legacyPreview['applicant_name'] ?? ''));
            $district = (string) ($case->cfaSubmission?->district?->name ?? ($legacyPreview['district'] ?? ''));
            $phone = (string) ($case->cfaSubmission?->phone ?? '');
            $block = (string) ($payload['block'] ?? '');
            $sector = (string) ($payload['business_category'] ?? '');
            $product = trim((string) ($payload['product'] ?? ''));
            if ($product === 'Others') {
                $product = trim((string) ($payload['other_product'] ?? ''));
            }
            $village = (string) ($payload['village'] ?? '');
            $pincode = (string) ($payload['pincode'] ?? '');
        }

        if ($phone !== '' && preg_match('/^[\d\s+\-]{10,}$/', $phone)) {
            $phone = "\t".$phone;
        }

        $spocRemark = match ($case->status) {
            ServiceCase::STATUS_SENT_BACK => (string) ($case->sent_back_note ?? ''),
            ServiceCase::STATUS_REJECTED => (string) ($case->rejected_note ?? ''),
            default => '',
        };

        return [
            $sn,
            (string) ($case->reference_number ?: ''),
            $applicationNo,
            $applicantName,
            $district,
            $phone,
            $block,
            $sector,
            $product,
            $village,
            $pincode,
            (string) ($case->service?->category?->name ?? ''),
            (string) ($case->service?->name ?? ''),
            strtoupper((string) ($case->service?->reporting_tier ?? 'UNSET')),
            ucfirst(str_replace('_', ' ', (string) $case->status)),
            $this->fmtDate($case->sla_deadline_at),
            $this->fmtDate($case->submitted_at),
            (string) ($case->submitter?->name ?? ''),
            (string) ($case->spoc?->name ?? 'Unassigned'),
            (string) ($case->approver?->name ?? ''),
            $this->fmtDate($case->created_at),
            (int) ($case->attachments_count ?? $case->attachments->count()),
            $spocRemark,
        ];
    }

    /**
     * @param  Collection<int, ServiceCase>|\Illuminate\Database\Eloquent\Collection<int, ServiceCase>  $rows
     * @return array<int, array<string, string>>
     */
    private function buildLegacyPhase2ExportMap($rows): array
    {
        $support = app(LegacyApplicationServiceCaseSupport::class);
        if (! $support->legacyDbAvailable() || ! ServiceCase::supportsLegacyApplicationLink()) {
            return [];
        }

        $ids = [];
        foreach ($rows as $case) {
            $legacyId = (int) ($case->legacy_application_id ?? 0);
            if ($legacyId > 0 && ! $case->cfa_submission_id) {
                $ids[] = $legacyId;
            }
        }
        $ids = array_values(array_unique($ids));
        if ($ids === []) {
            return [];
        }

        return DB::connection('legacy')
            ->table('rbi_applicant_details as d')
            ->leftJoin('rbi_applications as a', 'a.id', '=', 'd.application_id')
            ->whereIn('d.application_id', $ids)
            ->orderByDesc('d.id')
            ->get([
                'd.application_id',
                'd.applicant_name',
                'd.phone',
                'd.district',
                'd.block',
                'd.village',
                'a.application_no',
                'a.product',
                'a.business_category',
            ])
            ->mapWithKeys(function ($row): array {
                $id = (int) ($row->application_id ?? 0);
                if ($id < 1) {
                    return [];
                }
                return [
                    $id => [
                        'application_no' => (string) ($row->application_no ?? ''),
                        'applicant_name' => (string) ($row->applicant_name ?? ''),
                        'phone' => (string) ($row->phone ?? ''),
                        'district' => (string) ($row->district ?? ''),
                        'block' => (string) ($row->block ?? ''),
                        'village' => (string) ($row->village ?? ''),
                        'business_category' => (string) ($row->business_category ?? ''),
                        'product' => (string) ($row->product ?? ''),
                    ],
                ];
            })
            ->all();
    }

    public function viewAttachment(Request $request, ServiceCase $service_case, ServiceCaseAttachment $attachment): StreamedResponse
    {
        abort_unless((int) $attachment->service_case_id === (int) $service_case->id, 404);

        $disk = Storage::disk((string) $attachment->disk);
        abort_unless($disk->exists((string) $attachment->path), 404);

        $stream = $disk->readStream((string) $attachment->path);
        abort_unless(is_resource($stream), 404);

        $fileName = (string) ($attachment->original_name ?: 'attachment');
        $mimeType = (string) ($attachment->mime_type ?: 'application/octet-stream');

        return response()->stream(function () use ($stream): void {
            fpassthru($stream);
            fclose($stream);
        }, 200, [
            'Content-Type' => $mimeType,
            'Content-Disposition' => 'inline; filename="'.$fileName.'"',
        ]);
    }

    private function applyFilters($query, array $filters, bool $ignoreDistrictFilter = false, bool $ignoreStatusFilter = false): void
    {
        if ($filters['q'] !== '') {
            $like = '%'.$filters['q'].'%';
            $query->where(function ($q) use ($like): void {
                $q->where('service_cases.reference_number', 'like', $like)
                    ->orWhere('service_cases.sent_back_note', 'like', $like)
                    ->orWhere('service_cases.rejected_note', 'like', $like)
                    ->orWhere('cfa_submissions.application_no', 'like', $like)
                    ->orWhere('cfa_submissions.applicant_name', 'like', $like)
                    ->orWhereHas('service', fn ($s) => $s->where('name', 'like', $like))
                    ->orWhereHas('submitter', fn ($s) => $s->where('name', 'like', $like))
                    ->orWhereHas('spoc', fn ($s) => $s->where('name', 'like', $like));
                if ($this->legacyPhase2JoinsApplied) {
                    $q->orWhere('legacy_phase2_app.application_no', 'like', $like);
                }
                if ($this->legacyPhase2DbSafe !== null) {
                    $db = $this->legacyPhase2DbSafe;
                    $q->orWhereExists(function ($sub) use ($like, $db): void {
                        $sub->from(DB::raw("`{$db}`.`rbi_applicant_details` as d"))
                            ->whereColumn('d.application_id', 'service_cases.legacy_application_id')
                            ->where('d.applicant_name', 'like', $like);
                    });
                }
            });
        }

        if (! $ignoreDistrictFilter && $filters['district_id'] > 0) {
            $this->constrainToLaravelDistrict($query, $filters['district_id']);
        }

        if ($filters['service_id'] === ConvergenceReapSupport::MIS_8_2_LIST_FILTER) {
            ConvergenceReapSupportDeliverablesSupport::applyListingScope($query, 'service_cases');
        } elseif (is_numeric($filters['service_id']) && (int) $filters['service_id'] > 0) {
            $query->where('service_cases.service_id', (int) $filters['service_id']);
        }

        if ($filters['spoc_id'] === 'unassigned') {
            $query->whereNull('service_cases.spoc_user_id');
        } elseif (is_numeric($filters['spoc_id']) && (int) $filters['spoc_id'] > 0) {
            $spocUserId = (int) $filters['spoc_id'];
            $districtIds = $this->spocDistrictIds($spocUserId);
            if ($districtIds !== []) {
                $this->constrainToSpocDistricts($query, $districtIds);
            } else {
                $query->where('service_cases.spoc_user_id', $spocUserId);
            }
        }

        if ($filters['given_by_id'] > 0) {
            $staffId = (int) $filters['given_by_id'];
            $query->where(function ($q) use ($staffId): void {
                $q->where('service_cases.submitted_by', $staffId)
                    ->orWhere('service_cases.created_by', $staffId);
            });
        }

        if (! $ignoreStatusFilter && $filters['status'] !== '') {
            $query->where('service_cases.status', $filters['status']);
        }

        if ($filters['reporting_tier'] !== '') {
            $query->whereHas('service', function ($q) use ($filters): void {
                $q->where('reporting_tier', $filters['reporting_tier']);
            });
        }

        if ($filters['has_docs'] === '1') {
            $query->has('attachments');
        } elseif ($filters['has_docs'] === '0') {
            $query->doesntHave('attachments');
        }

        if ($filters['date_from'] !== '') {
            $query->whereDate('service_cases.created_at', '>=', $filters['date_from']);
        }

        if ($filters['date_to'] !== '') {
            $query->whereDate('service_cases.created_at', '<=', $filters['date_to']);
        }
    }

    private function buildFilteredQuery(array $filters)
    {
        $this->legacyPhase2JoinsApplied = false;
        $this->legacyPhase2DbSafe = null;

        $support = app(LegacyApplicationServiceCaseSupport::class);
        $query = ServiceCase::query()
            ->select('service_cases.*')
            ->leftJoin('cfa_submissions', 'cfa_submissions.id', '=', 'service_cases.cfa_submission_id');

        $legacyDb = (string) config('database.connections.legacy.database', '');
        if ($legacyDb !== '' && $support->legacyDbAvailable() && ServiceCase::supportsLegacyApplicationLink()) {
            $this->legacyPhase2JoinsApplied = true;
            $this->legacyPhase2DbSafe = str_replace('`', '``', $legacyDb);
            $query->leftJoin(
                DB::raw("`{$this->legacyPhase2DbSafe}`.`rbi_applications` as legacy_phase2_app"),
                'legacy_phase2_app.id',
                '=',
                'service_cases.legacy_application_id'
            );
        }

        return $query
            ->with([
                'service.category:id,name,slug',
                'cfaSubmission:id,application_no,applicant_name,district_id,phone,payload',
                'cfaSubmission.district:id,name',
                'cfaSubmission.onboardingBatchMembership:id,onboarding_batch_id,cfa_submission_id',
                'cfaSubmission.onboardingBatchMembership.batch:id,name',
                'submitter:id,name',
                'creator:id,name',
                'spoc:id,name',
                'approver:id,name',
                'attachments:id,service_case_id,disk,path,original_name,mime_type,size_bytes',
            ]);
    }

    private function constrainToLaravelDistrict($query, int $laravelDistrictId): void
    {
        $support = app(LegacyApplicationServiceCaseSupport::class);
        $names = $support->legacyDistrictNameCandidatesForLaravelDistrictId($laravelDistrictId);
        $validNames = [];
        foreach ($names as $n) {
            $norm = mb_strtolower(trim($n));
            if ($norm !== '') {
                $validNames[] = $norm;
            }
        }

        $db = $this->legacyPhase2DbSafe;

        $query->where(function ($w) use ($laravelDistrictId, $validNames, $db): void {
            $w->where('cfa_submissions.district_id', $laravelDistrictId);
            if ($validNames === [] || $db === null || ! ServiceCase::supportsLegacyApplicationLink()) {
                return;
            }
            $w->orWhere(function ($inner) use ($validNames, $db): void {
                $inner->whereNotNull('service_cases.legacy_application_id');
                $inner->whereExists(function ($sub) use ($validNames, $db): void {
                    $sub->from(DB::raw("`{$db}`.`rbi_applicant_details` as d"))
                        ->whereColumn('d.application_id', 'service_cases.legacy_application_id');
                    $sub->where(function ($nameMatch) use ($validNames): void {
                        $first = true;
                        foreach ($validNames as $norm) {
                            if ($first) {
                                $nameMatch->whereRaw('LOWER(TRIM(COALESCE(d.district, ""))) = ?', [$norm]);
                                $first = false;
                            } else {
                                $nameMatch->orWhereRaw('LOWER(TRIM(COALESCE(d.district, ""))) = ?', [$norm]);
                            }
                        }
                    });
                });
            });
        });
    }

    /**
     * Match SPOC queue: cases in districts assigned to this state staff via DistrictServiceSpoc.
     *
     * @param  list<int>  $districtIds
     */
    private function constrainToSpocDistricts($query, array $districtIds): void
    {
        $districtIds = array_values(array_unique(array_filter(array_map('intval', $districtIds), fn (int $id) => $id > 0)));
        if ($districtIds === []) {
            $query->whereRaw('1 = 0');

            return;
        }

        $support = app(LegacyApplicationServiceCaseSupport::class);
        $legacyAppIds = [];
        if (ServiceCase::supportsLegacyApplicationLink() && $support->legacyDbAvailable()) {
            foreach ($districtIds as $districtId) {
                foreach ($support->legacyApplicationIdsInLaravelDistrict($districtId) as $legacyId) {
                    $legacyAppIds[] = (int) $legacyId;
                }
            }
            $legacyAppIds = array_values(array_unique(array_filter($legacyAppIds, fn (int $id) => $id > 0)));
        }

        $query->where(function ($outer) use ($districtIds, $legacyAppIds): void {
            $outer->whereIn('cfa_submissions.district_id', $districtIds);
            if ($legacyAppIds !== []) {
                $outer->orWhere(function ($qq) use ($legacyAppIds): void {
                    $qq->whereNotNull('service_cases.legacy_application_id')
                        ->whereNull('service_cases.cfa_submission_id')
                        ->whereIn('service_cases.legacy_application_id', $legacyAppIds);
                });
            }
        });
    }

    /**
     * @return list<int>
     */
    private function spocDistrictIds(int $spocUserId): array
    {
        if ($spocUserId < 1 || ! Schema::hasTable('district_service_spocs')) {
            return [];
        }

        return DistrictServiceSpoc::query()
            ->where('state_staff_user_id', $spocUserId)
            ->pluck('district_id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * When admin filters by a SPOC with "All services", include market linkages the same way
     * the SPOC approval queue does (otherwise pending/sent-back/etc. under-count).
     */
    private function shouldMirrorSpocQueueWithMarketLinkages(array $filters): bool
    {
        if (! is_numeric($filters['spoc_id'] ?? '') || (int) $filters['spoc_id'] < 1) {
            return false;
        }

        $serviceId = $filters['service_id'] ?? '';
        if ($serviceId === ConvergenceReapSupport::MIS_8_2_LIST_FILTER) {
            return false;
        }
        if (is_numeric($serviceId) && (int) $serviceId > 0) {
            return false;
        }

        return Schema::hasTable('market_linkage_submissions')
            && MarketLinkageSubmission::supportsWorkflow();
    }

    /**
     * @return array{
     *   items: LengthAwarePaginator,
     *   summary: array{total: int, approved: int, pending_approval: int, sent_back: int, rejected: int, offline_rows: int, online_rows: int, deliverable_incubatees: int, offline_incubatees: int, online_incubatees: int}
     * }
     */
    private function buildSpocAlignedCombinedList(array $filters, Request $request): array
    {
        $sorted = $this->buildSpocAlignedCombinedRows($filters);
        $page = max(1, (int) $request->query('page', 1));
        $perPage = 20;
        $total = $sorted->count();

        $paginator = new LengthAwarePaginator(
            $sorted->slice(($page - 1) * $perPage, $perPage)->values(),
            $total,
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()],
        );

        $summaryFilters = $filters;
        $summaryFilters['status'] = '';
        $summaryRows = $this->buildSpocAlignedCombinedRows($summaryFilters);

        $statusCounts = [
            ServiceCase::STATUS_APPROVED => 0,
            ServiceCase::STATUS_PENDING_APPROVAL => 0,
            ServiceCase::STATUS_SENT_BACK => 0,
            ServiceCase::STATUS_REJECTED => 0,
        ];
        foreach ($summaryRows as $row) {
            $status = match ((string) ($row['type'] ?? '')) {
                'market_linkage_partner', 'market_linkage_incubatee' => (string) ($row['market_linkage']?->status ?? ''),
                default => (string) ($row['service_case']?->status ?? ''),
            };
            if (isset($statusCounts[$status])) {
                $statusCounts[$status]++;
            }
        }

        return [
            'items' => $paginator,
            'summary' => [
                'total' => $summaryRows->count(),
                'approved' => $statusCounts[ServiceCase::STATUS_APPROVED],
                'pending_approval' => $statusCounts[ServiceCase::STATUS_PENDING_APPROVAL],
                'sent_back' => $statusCounts[ServiceCase::STATUS_SENT_BACK],
                'rejected' => $statusCounts[ServiceCase::STATUS_REJECTED],
                'offline_rows' => 0,
                'online_rows' => 0,
                'deliverable_incubatees' => 0,
                'offline_incubatees' => 0,
                'online_incubatees' => 0,
            ],
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function buildSpocAlignedCombinedRows(array $filters): Collection
    {
        $caseQuery = $this->buildFilteredQuery($filters);
        $this->applyFilters($caseQuery, $filters);
        // Match SPOC queue default: hide draft/cancelled unless a status filter is set.
        if (($filters['status'] ?? '') === '') {
            $caseQuery->whereIn('service_cases.status', [
                ServiceCase::STATUS_PENDING_APPROVAL,
                ServiceCase::STATUS_SENT_BACK,
                ServiceCase::STATUS_APPROVED,
                ServiceCase::STATUS_REJECTED,
            ]);
        }
        $serviceCases = $caseQuery->orderByDesc('service_cases.updated_at')->get();

        $mlQuery = MarketLinkageSubmission::query()
            ->with(['partners', 'spoc:id,name', 'submitter:id,name', 'approver:id,name', 'cfaSubmission.district:id,name', 'district:id,name']);
        $this->applySpocAlignedMarketLinkageFilters($mlQuery, $filters);
        if (($filters['status'] ?? '') === '') {
            $mlQuery->whereIn('status', [
                ServiceCase::STATUS_PENDING_APPROVAL,
                ServiceCase::STATUS_SENT_BACK,
                ServiceCase::STATUS_APPROVED,
                ServiceCase::STATUS_REJECTED,
            ]);
        }
        $marketLinkages = $mlQuery->orderByDesc('updated_at')->get();

        $items = collect();
        foreach ($serviceCases as $case) {
            $items->push([
                'type' => 'service_case',
                'service_case' => $case,
                'market_linkage' => null,
                'partner' => null,
                'linkage_mode' => '—',
                'partner_name' => '—',
                'updated_at' => $case->updated_at ?? $case->created_at,
            ]);
        }

        foreach ($marketLinkages as $submission) {
            $modes = [];
            $partnerNames = [];
            foreach ($submission->partners as $partner) {
                $modeLabel = MarketLinkageUnifiedListingSupport::linkageModeLabelFromPartnerMode((string) $partner->linkage_mode);
                if ($modeLabel !== '' && ! in_array($modeLabel, $modes, true)) {
                    $modes[] = $modeLabel;
                }
                $name = trim((string) $partner->partner_name);
                if ($name !== '') {
                    $partnerNames[] = $name;
                }
            }

            $partnerCount = count($partnerNames);
            $partnerSummary = $partnerCount === 0
                ? '—'
                : ($partnerCount === 1
                    ? $partnerNames[0]
                    : $partnerCount.' partners');

            $items->push([
                'type' => 'market_linkage_incubatee',
                'service_case' => null,
                'market_linkage' => $submission,
                'partner' => null,
                'linkage_mode' => $modes !== [] ? implode(', ', $modes) : '—',
                'partner_name' => $partnerSummary,
                'partner_count' => $partnerCount,
                'updated_at' => $submission->updated_at ?? $submission->created_at,
            ]);
        }

        return $items
            ->sortByDesc(fn (array $row) => $row['updated_at']?->timestamp ?? 0)
            ->values();
    }

    /**
     * @return Collection<int, array{id: int, name: string, total: int}>
     */
    private function buildSpocAlignedDistrictCounts(array $filters): Collection
    {
        $countFilters = $filters;
        $countFilters['district_id'] = 0;
        $countFilters['status'] = '';
        $rows = $this->buildSpocAlignedCombinedRows($countFilters);

        $totals = [];
        foreach ($rows as $row) {
            $ml = $row['market_linkage'] ?? null;
            if ($ml instanceof MarketLinkageSubmission) {
                $districtId = (int) ($ml->district_id ?? 0);
            } else {
                $case = $row['service_case'] ?? null;
                $districtId = (int) ($case?->cfaSubmission?->district_id ?? 0);
                if ($districtId < 1 && $case instanceof ServiceCase) {
                    $legacyId = (int) ($case->legacy_application_id ?? 0);
                    if ($legacyId > 0 && ! $case->cfa_submission_id) {
                        $districtId = (int) (app(LegacyApplicationServiceCaseSupport::class)
                            ->laravelDistrictIdForLegacyApplication($legacyId) ?? 0);
                    }
                }
            }
            if ($districtId < 1) {
                continue;
            }
            $totals[$districtId] = ($totals[$districtId] ?? 0) + 1;
        }

        return District::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (District $district): array => [
                'id' => (int) $district->id,
                'name' => (string) $district->name,
                'total' => (int) ($totals[(int) $district->id] ?? 0),
            ]);
    }

    /**
     * @param  Builder<MarketLinkageSubmission>  $query
     */
    private function applySpocAlignedMarketLinkageFilters(Builder $query, array $filters): void
    {
        $spocUserId = (int) ($filters['spoc_id'] ?? 0);
        $districtIds = $this->spocDistrictIds($spocUserId);
        if ($districtIds !== []) {
            $query->whereIn('district_id', $districtIds);
        } else {
            $query->where('spoc_user_id', $spocUserId);
        }

        if (($filters['q'] ?? '') !== '') {
            $like = '%'.$filters['q'].'%';
            $query->where(function ($q) use ($like): void {
                $q->where('incubatee_name', 'like', $like)
                    ->orWhere('application_no', 'like', $like)
                    ->orWhere('submitted_by_name', 'like', $like)
                    ->orWhereHas('partners', fn ($p) => $p->where('partner_name', 'like', $like))
                    ->orWhereHas('spoc', fn ($s) => $s->where('name', 'like', $like));
            });
        }

        if ((int) ($filters['district_id'] ?? 0) > 0) {
            $query->where('district_id', (int) $filters['district_id']);
        }

        if (($filters['given_by_id'] ?? 0) > 0) {
            $query->where('submitted_by_user_id', (int) $filters['given_by_id']);
        }

        if (($filters['status'] ?? '') !== '') {
            $query->where('status', $filters['status']);
        }

        if (($filters['date_from'] ?? '') !== '') {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }
        if (($filters['date_to'] ?? '') !== '') {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }
    }

    /**
     * @param  Collection<int, array<string, mixed>>|\Illuminate\Support\Collection<int, ServiceCase>  $cases
     * @return array<int, array{applicant_name: string, application_no: string, district: string, onboarding_batch_name: string}>
     */
    private function buildLegacyPreviewMapFromUnifiedRows($cases): array
    {
        $serviceCases = $cases
            ->map(fn ($row) => is_array($row) ? ($row['service_case'] ?? null) : $row)
            ->filter(fn ($case) => $case instanceof ServiceCase);

        return $this->buildLegacyPreviewMap($serviceCases);
    }

    /**
     * @param  Collection<int, ServiceCase>|\Illuminate\Database\Eloquent\Collection<int, ServiceCase>  $cases
     * @return array<int, array{applicant_name: string, application_no: string, district: string, onboarding_batch_name: string}>
     */
    private function buildLegacyPreviewMap($cases): array
    {
        $support = app(LegacyApplicationServiceCaseSupport::class);
        if (! $support->legacyDbAvailable()) {
            return [];
        }

        $ids = [];
        foreach ($cases as $case) {
            $lid = (int) ($case->legacy_application_id ?? 0);
            if ($lid > 0 && ! $case->cfa_submission_id) {
                $ids[] = $lid;
            }
        }

        return $support->incubateePreviewMap(array_values(array_unique($ids)));
    }

    /**
     * @param  Builder<ServiceCase>  $query
     */
    private function countDistinctServiceCases($query): int
    {
        $q = clone $query;
        $base = $q->getQuery();
        $base->columns = null;
        $base->orders = null;
        $base->unionOrders = null;
        $base->limit = null;
        $base->offset = null;

        return (int) $q->selectRaw('COUNT(DISTINCT service_cases.id) as aggregate')->value('aggregate');
    }

    private function validatedFilters(Request $request): array
    {
        return [
            'q' => trim((string) $request->query('q', '')),
            'district_id' => (int) $request->query('district_id', 0),
            'category_id' => 0,
            'service_id' => trim((string) $request->query('service_id', '')),
            'spoc_id' => trim((string) $request->query('spoc_id', '')),
            'given_by_id' => (int) $request->query('given_by_id', 0),
            'status' => trim((string) $request->query('status', '')),
            'reporting_tier' => trim((string) $request->query('reporting_tier', '')),
            'has_docs' => trim((string) $request->query('has_docs', '')),
            'sla_breached' => '',
            'date_from' => trim((string) $request->query('date_from', '')),
            'date_to' => trim((string) $request->query('date_to', '')),
            'unique_incubatees' => $request->query('unique_incubatees') === '1',
        ];
    }

    /**
     * @return array{
     *   rows: list<array{service_id: int, service_name: string, approved: int, pending: int, total: int}>,
     *   totals: array{approved: int, pending: int, total: int}
     * }
     */
    private function buildGivenByServiceBreakdown(array $filters): array
    {
        $breakdownFilters = $filters;
        $breakdownFilters['status'] = '';
        $breakdownFilters['service_id'] = '';

        $query = $this->buildFilteredQuery($breakdownFilters);
        $this->applyFilters($query, $breakdownFilters, ignoreStatusFilter: true);

        $rawRows = (clone $query)
            ->join('services', 'services.id', '=', 'service_cases.service_id')
            ->select(
                'service_cases.service_id',
                'services.name as service_name',
                'service_cases.status',
                DB::raw('COUNT(DISTINCT service_cases.id) as total'),
            )
            ->groupBy('service_cases.service_id', 'services.name', 'service_cases.status')
            ->orderBy('services.name')
            ->get();

        $byService = [];
        foreach ($rawRows as $row) {
            $serviceId = (int) $row->service_id;
            if (! isset($byService[$serviceId])) {
                $byService[$serviceId] = [
                    'service_id' => $serviceId,
                    'service_name' => (string) $row->service_name,
                    'approved' => 0,
                    'pending' => 0,
                    'total' => 0,
                ];
            }

            $count = (int) $row->total;
            $byService[$serviceId]['total'] += $count;

            if ($row->status === ServiceCase::STATUS_APPROVED) {
                $byService[$serviceId]['approved'] += $count;
            } elseif ($row->status === ServiceCase::STATUS_PENDING_APPROVAL) {
                $byService[$serviceId]['pending'] += $count;
            }
        }

        $rows = collect($byService)
            ->sortBy('service_name')
            ->values()
            ->all();

        return [
            'rows' => $rows,
            'totals' => [
                'approved' => (int) collect($rows)->sum('approved'),
                'pending' => (int) collect($rows)->sum('pending'),
                'total' => (int) collect($rows)->sum('total'),
            ],
        ];
    }

    private function fmtDate($value): string
    {
        if (! $value) {
            return '';
        }
        $dt = $value instanceof Carbon ? $value : Carbon::parse((string) $value);

        return $dt->format('Y-m-d H:i');
    }
}
