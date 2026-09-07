<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AccelerationServiceSession;
use App\Models\District;
use App\Models\DistrictServiceSpoc;
use App\Models\FiscalYear;
use App\Models\MarketLinkageSubmission;
use App\Models\Service;
use App\Models\ServiceCase;
use App\Models\ServiceCaseAttachment;
use App\Models\User;
use App\Services\Admin\Phase3UnifiedMarketLinkageListBuilder;
use App\Services\LegacyApplicationServiceCaseSupport;
use App\Support\AccelerationServicesApproval;
use App\Support\ApplicantCategoryShgSupport;
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

        if ($this->isAccelerationFilter($filters)) {
            $listResult = $this->buildCombinedServiceCaseAndAccelerationList($filters, $request, includeServiceCases: false);
            $cases = $listResult['items'];
            $summary = $listResult['summary'];
            $unifiedMarketLinkage = false;
            $uniqueIncubateesView = false;
        } elseif ($this->shouldMirrorSpocQueueWithMarketLinkages($filters)) {
            $listResult = $this->buildSpocAlignedCombinedList($filters, $request);
            $cases = $listResult['items'];
            $summary = $listResult['summary'];
            $unifiedMarketLinkage = false;
            $uniqueIncubateesView = false;
        } elseif ($this->shouldIncludeAcceleration($filters)
            && ! MarketLinkageUnifiedListingSupport::isMarketLinkServiceId(
                is_numeric($filters['service_id'] ?? '') ? (int) $filters['service_id'] : 0
            )) {
            $listResult = $this->buildCombinedServiceCaseAndAccelerationList($filters, $request, includeServiceCases: true);
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
            } elseif ($this->isAccelerationFilter($filters)) {
                $districtCounts = District::query()
                    ->orderBy('name')
                    ->get(['id', 'name'])
                    ->map(fn (District $district): array => [
                        'id' => (int) $district->id,
                        'name' => (string) $district->name,
                        'total' => 0,
                    ]);
                $districtCounts = $this->addAccelerationDistrictCounts($districtCounts, $filters);
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
                if ($this->shouldIncludeAcceleration($filters)) {
                    $districtCounts = $this->addAccelerationDistrictCounts($districtCounts, $filters);
                }
            }
        }

        $legacyPreviews = ($unifiedMarketLinkage || $this->shouldMirrorSpocQueueWithMarketLinkages($filters) || $this->shouldIncludeAcceleration($filters))
            ? $this->buildLegacyPreviewMapFromUnifiedRows($cases->getCollection())
            : $this->buildLegacyPreviewMap($cases->getCollection());

        $givenByStaff = null;
        $givenByBreakdown = null;
        if ($filters['given_by_id'] > 0) {
            $givenByStaff = User::query()->find($filters['given_by_id'], ['id', 'name']);
            $givenByBreakdown = $this->buildGivenByServiceBreakdown($filters);
        }

        $fiscalYear = FiscalYear::phase3Default();

        return view('admin.phase3-services.index', [
            'cases' => $cases,
            'summary' => $summary,
            'filters' => $filters,
            'fiscalYear' => $fiscalYear,
            'monthOptions' => $this->monthFilterOptions($fiscalYear),
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

        if ($this->isAccelerationFilter($filters)) {
            $matrix = [];
            $sn = 0;
            foreach ($this->buildAccelerationQuery($filters)->orderByDesc('updated_at')->get() as $session) {
                $sn++;
                $matrix[] = $this->exportAccelerationRow($session, $sn);
            }

            return $this->downloadExcelOrCsv($matrix, 'phase3-acceleration-services-'.now()->format('Ymd_His'));
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

        if ($this->shouldIncludeAcceleration($filters)) {
            foreach ($this->buildAccelerationQuery($filters)->orderByDesc('updated_at')->get() as $session) {
                $sn++;
                $matrix[] = $this->exportAccelerationRow($session, $sn);
            }
        }

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
     * @param  array<int, array<string, mixed>|null>  $legacyPreviews
     * @return list<string|int>
     */
    private function exportUnifiedRowValues(array $row, int $sn, array $legacyPreviews): array
    {
        $case = $row['service_case'] ?? null;
        $ml = $row['market_linkage'] ?? null;
        $partner = $row['partner'] ?? null;
        $accel = $row['acceleration'] ?? null;

        if ($accel instanceof AccelerationServiceSession) {
            return $this->exportAccelerationRow($accel, $sn);
        }

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
        $legacyId = (int) ($ml?->legacy_application_id ?? 0);
        $legacy = $legacyPreviews[$legacyId] ?? null;
        $applicantPayload = is_array($ml?->cfaSubmission?->payload) ? $ml->cfaSubmission->payload : [];
        $applicantCategory = ApplicantCategoryShgSupport::categoryLabel(
            $applicantPayload,
            is_array($legacy) ? ($legacy['applicant_category'] ?? null) : null,
        );
        $shgMember = ApplicantCategoryShgSupport::shgMemberLabel(
            $applicantPayload,
            is_array($legacy) ? ($legacy['is_shg_member'] ?? null) : null,
        );

        if ($phone !== '' && preg_match('/^[\d\s+\-]{10,}$/', $phone)) {
            $phone = "\t".$phone;
        }

        return [
            $sn,
            (string) ($ml?->application_no ?: ''),
            $applicationNo,
            $applicantName,
            $applicantCategory,
            $shgMember,
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
            '',
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
            'Applicant Category',
            'SHG/CBO Member',
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
            'Service Date',
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
     * @param  array<string, mixed>|null  $legacyPreview
     * @param  array<string, mixed>|null  $legacyRow
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

        $applicantCategory = ApplicantCategoryShgSupport::categoryLabel(
            $payload,
            $legacyRow['applicant_category'] ?? $legacyPreview['applicant_category'] ?? null,
        );
        $shgMember = ApplicantCategoryShgSupport::shgMemberLabel(
            $payload,
            $legacyRow['is_shg_member'] ?? $legacyPreview['is_shg_member'] ?? null,
        );

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
            $applicantCategory,
            $shgMember,
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
            $case->serviceDateForReporting()?->format('Y-m-d') ?? '',
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
                'd.is_shg_member',
                'a.application_no',
                'a.product',
                'a.category as applicant_category',
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
                        'is_shg_member' => $row->is_shg_member ?? null,
                        'applicant_category' => (string) ($row->applicant_category ?? ''),
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

        $useDeliverableDates = MarketLinkageUnifiedListingSupport::isMarketLinkServiceId(
            is_numeric($filters['service_id'] ?? '') ? (int) $filters['service_id'] : 0
        );

        if ($filters['date_from'] !== '') {
            if ($useDeliverableDates) {
                $query->whereRaw('DATE(COALESCE(service_cases.approved_at, service_cases.created_at)) >= ?', [$filters['date_from']]);
            } else {
                $query->whereDate('service_cases.created_at', '>=', $filters['date_from']);
            }
        }

        if ($filters['date_to'] !== '') {
            if ($useDeliverableDates) {
                $query->whereRaw('DATE(COALESCE(service_cases.approved_at, service_cases.created_at)) <= ?', [$filters['date_to']]);
            } else {
                $query->whereDate('service_cases.created_at', '<=', $filters['date_to']);
            }
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
        if ($serviceId === AccelerationServiceSession::LIST_FILTER) {
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
                'acceleration' => $this->accelerationRowStatus($row['acceleration'] ?? null),
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
            ->with([
                'partners',
                'spoc:id,name',
                'submitter:id,name',
                'approver:id,name',
                'cfaSubmission:id,application_no,applicant_name,district_id,phone,payload',
                'cfaSubmission.district:id,name',
                'district:id,name',
            ]);
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
                    'acceleration' => null,
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
                'acceleration' => null,
                'linkage_mode' => $modes !== [] ? implode(', ', $modes) : '—',
                'partner_name' => $partnerSummary,
                'partner_count' => $partnerCount,
                'updated_at' => $submission->updated_at ?? $submission->created_at,
            ]);
        }

        if ($this->shouldIncludeAcceleration($filters)) {
            foreach ($this->buildAccelerationQuery($filters)->orderByDesc('updated_at')->get() as $session) {
                $items->push($this->accelerationListRow($session));
            }
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
            $accel = $row['acceleration'] ?? null;
            if ($accel instanceof AccelerationServiceSession) {
                $districtId = $this->laravelDistrictIdFromName((string) $accel->district_name);
            } elseif ($ml instanceof MarketLinkageSubmission) {
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
            $query->whereRaw('DATE(COALESCE(submitted_at, created_at)) >= ?', [$filters['date_from']]);
        }
        if (($filters['date_to'] ?? '') !== '') {
            $query->whereRaw('DATE(COALESCE(submitted_at, created_at)) <= ?', [$filters['date_to']]);
        }
    }

    /**
     * @param  Collection<int, array<string, mixed>>|\Illuminate\Support\Collection<int, ServiceCase>  $cases
     * @return array<int, array<string, mixed>>
     */
    private function buildLegacyPreviewMapFromUnifiedRows($cases): array
    {
        $ids = [];
        foreach ($cases as $row) {
            $case = is_array($row) ? ($row['service_case'] ?? null) : $row;
            $marketLinkage = is_array($row) ? ($row['market_linkage'] ?? null) : null;
            $legacyId = (int) ($case?->legacy_application_id ?? $marketLinkage?->legacy_application_id ?? 0);
            if ($legacyId > 0) {
                $ids[] = $legacyId;
            }
        }

        return app(LegacyApplicationServiceCaseSupport::class)
            ->incubateePreviewMap(array_values(array_unique($ids)));
    }

    /**
     * @param  Collection<int, ServiceCase>|\Illuminate\Database\Eloquent\Collection<int, ServiceCase>  $cases
     * @return array<int, array<string, mixed>>
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
            if ($lid > 0) {
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
        $monthRaw = $request->query('month', '');
        $month = ($monthRaw !== null && $monthRaw !== '') ? (int) $monthRaw : 0;
        if ($month < 1 || $month > 12) {
            $month = 0;
        }

        $filters = [
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
            'month' => $month,
            'date_from' => trim((string) $request->query('date_from', '')),
            'date_to' => trim((string) $request->query('date_to', '')),
            'unique_incubatees' => $request->query('unique_incubatees') === '1',
        ];

        return $this->applyMonthDateRange($filters);
    }

    /**
     * When a calendar month is selected, fill date_from/date_to for the matching
     * month inside the active Phase 3 fiscal year (same ladder as deliverables).
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function applyMonthDateRange(array $filters): array
    {
        $month = (int) ($filters['month'] ?? 0);
        if ($month < 1 || $month > 12) {
            return $filters;
        }

        $fy = FiscalYear::phase3Default();
        $fiscalStartYear = (int) ($fy?->starts_on?->year ?? now()->year);
        $fiscalStartMonth = (int) ($fy?->starts_on?->month ?? 4);
        $year = $month >= $fiscalStartMonth ? $fiscalStartYear : $fiscalStartYear + 1;

        $from = Carbon::create($year, $month, 1)->startOfDay();
        $filters['date_from'] = $from->toDateString();
        $filters['date_to'] = $from->copy()->endOfMonth()->toDateString();

        return $filters;
    }

    /**
     * Direct month chips: FY calendar months with year (Apr 2026 … Mar 2027).
     * Value is the calendar month number used by applyMonthDateRange().
     *
     * @return list<array{value: int, label: string}>
     */
    private function monthFilterOptions(?FiscalYear $fy): array
    {
        $options = [];
        if ($fy?->starts_on === null) {
            for ($m = 1; $m <= 12; $m++) {
                $options[] = [
                    'value' => $m,
                    'label' => Carbon::create(null, $m, 1)->format('M'),
                ];
            }

            return $options;
        }

        for ($i = 0; $i < 12; $i++) {
            $start = $fy->starts_on->copy()->startOfMonth()->addMonths($i);
            $options[] = [
                'value' => (int) $start->month,
                'label' => $start->format('M Y'),
            ];
        }

        return $options;
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

        if ($this->shouldIncludeAcceleration($breakdownFilters) || $this->isAccelerationFilter($filters)) {
            $accelSummary = $this->accelerationSummary($breakdownFilters);
            if ((int) ($accelSummary['total'] ?? 0) > 0) {
                $rows[] = [
                    'service_id' => 0,
                    'service_name' => AccelerationServiceSession::SERVICE_LIST_LABEL,
                    'approved' => (int) ($accelSummary['approved'] ?? 0),
                    'pending' => (int) ($accelSummary['pending_approval'] ?? 0),
                    'total' => (int) ($accelSummary['total'] ?? 0),
                ];
                usort($rows, fn (array $a, array $b): int => strcasecmp((string) $a['service_name'], (string) $b['service_name']));
            }
        }

        return [
            'rows' => $rows,
            'totals' => [
                'approved' => (int) collect($rows)->sum('approved'),
                'pending' => (int) collect($rows)->sum('pending'),
                'total' => (int) collect($rows)->sum('total'),
            ],
        ];
    }

    /**
     * @return array{
     *   items: LengthAwarePaginator,
     *   summary: array{total: int, approved: int, pending_approval: int, sent_back: int, rejected: int, offline_rows: int, online_rows: int, deliverable_incubatees: int, offline_incubatees: int, online_incubatees: int}
     * }
     */
    private function buildCombinedServiceCaseAndAccelerationList(array $filters, Request $request, bool $includeServiceCases): array
    {
        $meta = collect();

        if ($includeServiceCases) {
            $caseQuery = $this->buildFilteredQuery($filters);
            $this->applyFilters($caseQuery, $filters);
            $caseQuery->setEagerLoads([]);
            foreach ((clone $caseQuery)->reorder()->select('service_cases.id', 'service_cases.updated_at', 'service_cases.created_at')->get() as $row) {
                $meta->push([
                    'type' => 'service_case',
                    'id' => (int) $row->id,
                    'updated_at' => $row->updated_at ?? $row->created_at,
                ]);
            }
        }

        foreach ($this->baseAccelerationQuery($filters)->select('id', 'updated_at', 'created_at')->orderByDesc('id')->get() as $row) {
            $meta->push([
                'type' => 'acceleration',
                'id' => (int) $row->id,
                'updated_at' => $row->updated_at ?? $row->created_at,
            ]);
        }

        $sorted = $meta
            ->sortByDesc(fn (array $row) => $row['updated_at']?->timestamp ?? 0)
            ->values();

        $page = max(1, (int) $request->query('page', 1));
        $perPage = 20;
        $slice = $sorted->slice(($page - 1) * $perPage, $perPage)->values();

        $caseIds = $slice->where('type', 'service_case')->pluck('id')->map(fn ($id) => (int) $id)->all();
        $accelIds = $slice->where('type', 'acceleration')->pluck('id')->map(fn ($id) => (int) $id)->all();

        $casesById = $caseIds === []
            ? collect()
            : $this->buildFilteredQuery($filters)->whereIn('service_cases.id', $caseIds)->get()->keyBy('id');
        $accelsById = $accelIds === []
            ? collect()
            : $this->buildAccelerationQuery($filters)->whereIn('acceleration_service_sessions.id', $accelIds)->get()->keyBy('id');

        $pageItems = $slice->map(function (array $row) use ($casesById, $accelsById): array {
            if (($row['type'] ?? '') === 'acceleration') {
                $session = $accelsById->get((int) $row['id']);

                return $session instanceof AccelerationServiceSession
                    ? $this->accelerationListRow($session)
                    : ['type' => 'acceleration', 'acceleration' => null, 'service_case' => null, 'market_linkage' => null, 'updated_at' => $row['updated_at'] ?? null];
            }

            $case = $casesById->get((int) $row['id']);

            return [
                'type' => 'service_case',
                'service_case' => $case,
                'market_linkage' => null,
                'partner' => null,
                'acceleration' => null,
                'linkage_mode' => '—',
                'partner_name' => '—',
                'updated_at' => $case?->updated_at ?? $row['updated_at'] ?? null,
            ];
        })->values();

        $paginator = new LengthAwarePaginator(
            $pageItems,
            $sorted->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()],
        );

        $summary = $includeServiceCases
            ? $this->serviceCaseSummary($filters)
            : [
                'total' => 0,
                'approved' => 0,
                'pending_approval' => 0,
                'sent_back' => 0,
                'rejected' => 0,
                'offline_rows' => 0,
                'online_rows' => 0,
                'deliverable_incubatees' => 0,
                'offline_incubatees' => 0,
                'online_incubatees' => 0,
            ];

        return [
            'items' => $paginator,
            'summary' => $this->addSummaries($summary, $this->accelerationSummary($filters)),
        ];
    }

    /**
     * @return array{total: int, approved: int, pending_approval: int, sent_back: int, rejected: int, offline_rows: int, online_rows: int, deliverable_incubatees: int, offline_incubatees: int, online_incubatees: int}
     */
    private function serviceCaseSummary(array $filters): array
    {
        $summaryQuery = $this->buildFilteredQuery($filters);
        $this->applyFilters($summaryQuery, $filters, ignoreStatusFilter: true);

        $summaryRows = (clone $summaryQuery)
            ->select('service_cases.status', DB::raw('COUNT(DISTINCT service_cases.id) as total'))
            ->groupBy('service_cases.status')
            ->pluck('total', 'status');

        return [
            'total' => (int) $summaryRows->sum(),
            'approved' => (int) ($summaryRows[ServiceCase::STATUS_APPROVED] ?? 0),
            'pending_approval' => (int) ($summaryRows[ServiceCase::STATUS_PENDING_APPROVAL] ?? 0),
            'sent_back' => (int) ($summaryRows[ServiceCase::STATUS_SENT_BACK] ?? 0),
            'rejected' => (int) ($summaryRows[ServiceCase::STATUS_REJECTED] ?? 0),
            'offline_rows' => 0,
            'online_rows' => 0,
            'deliverable_incubatees' => 0,
            'offline_incubatees' => 0,
            'online_incubatees' => 0,
        ];
    }

    /**
     * @return array{total: int, approved: int, pending_approval: int, sent_back: int, rejected: int}
     */
    private function accelerationSummary(array $filters): array
    {
        $countFilters = $filters;
        $countFilters['status'] = '';
        if (! AccelerationServicesApproval::workflowReady()) {
            $total = (int) $this->baseAccelerationQuery($countFilters)->count();

            return [
                'total' => $total,
                'approved' => $total,
                'pending_approval' => 0,
                'sent_back' => 0,
                'rejected' => 0,
            ];
        }

        $counts = $this->baseAccelerationQuery($countFilters)
            ->toBase()
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $pending = (int) ($counts[AccelerationServicesApproval::STATUS_PENDING_REVIEW] ?? 0)
            + (int) ($counts[AccelerationServicesApproval::STATUS_PENDING_FINAL] ?? 0);

        return [
            'total' => (int) $counts->sum(),
            'approved' => (int) ($counts[AccelerationServicesApproval::STATUS_APPROVED] ?? 0),
            'pending_approval' => $pending,
            'sent_back' => (int) ($counts[AccelerationServicesApproval::STATUS_SENT_BACK] ?? 0),
            'rejected' => 0,
        ];
    }

    /**
     * @param  array<string, int>  $a
     * @param  array<string, int>  $b
     * @return array<string, int>
     */
    private function addSummaries(array $a, array $b): array
    {
        foreach (['total', 'approved', 'pending_approval', 'sent_back', 'rejected'] as $key) {
            $a[$key] = (int) ($a[$key] ?? 0) + (int) ($b[$key] ?? 0);
        }

        return $a;
    }

    /**
     * @param  Collection<int, array{id: int, name: string, total: int}>  $districtCounts
     * @return Collection<int, array{id: int, name: string, total: int}>
     */
    private function addAccelerationDistrictCounts(Collection $districtCounts, array $filters): Collection
    {
        $countFilters = $filters;
        $countFilters['district_id'] = 0;
        $countFilters['status'] = '';
        $rows = $this->baseAccelerationQuery($countFilters)
            ->toBase()
            ->select('district_name', DB::raw('COUNT(*) as total'))
            ->groupBy('district_name')
            ->pluck('total', 'district_name');

        $byNorm = [];
        foreach ($rows as $name => $total) {
            $key = mb_strtolower(trim((string) $name));
            if ($key === '') {
                continue;
            }
            $byNorm[$key] = ($byNorm[$key] ?? 0) + (int) $total;
        }

        return $districtCounts->map(function (array $dc) use ($byNorm): array {
            $key = mb_strtolower(trim((string) $dc['name']));
            $dc['total'] = (int) $dc['total'] + (int) ($byNorm[$key] ?? 0);

            return $dc;
        });
    }

    private function isAccelerationFilter(array $filters): bool
    {
        return ($filters['service_id'] ?? '') === AccelerationServiceSession::LIST_FILTER;
    }

    private function shouldIncludeAcceleration(array $filters): bool
    {
        if (! Schema::hasTable('acceleration_service_sessions')) {
            return false;
        }
        if ($this->isAccelerationFilter($filters)) {
            return true;
        }
        if (($filters['service_id'] ?? '') === ConvergenceReapSupport::MIS_8_2_LIST_FILTER) {
            return false;
        }
        if (is_numeric($filters['service_id'] ?? '') && (int) $filters['service_id'] > 0) {
            return false;
        }
        if (($filters['reporting_tier'] ?? '') !== '') {
            return false;
        }

        return true;
    }

    /**
     * @return Builder<AccelerationServiceSession>
     */
    private function baseAccelerationQuery(array $filters)
    {
        $query = AccelerationServiceSession::query();

        if (Schema::hasColumn('acceleration_service_sessions', 'is_draft')) {
            $query->where('is_draft', false);
        }
        if (AccelerationServicesApproval::workflowReady()) {
            $query->where('status', '!=', AccelerationServicesApproval::STATUS_DRAFT);
        }

        $statuses = $this->accelerationStatusesForFilter((string) ($filters['status'] ?? ''));
        if ($statuses !== null) {
            if ($statuses === []) {
                $query->whereRaw('1 = 0');
            } else {
                $query->whereIn('status', $statuses);
            }
        }

        if (($filters['q'] ?? '') !== '') {
            $like = '%'.$filters['q'].'%';
            $query->where(function ($q) use ($like): void {
                $q->where('applicant_name', 'like', $like)
                    ->orWhere('application_no', 'like', $like)
                    ->orWhere('district_name', 'like', $like)
                    ->orWhere('submitted_by_name', 'like', $like)
                    ->orWhere('phone', 'like', $like);
            });
        }

        if ((int) ($filters['district_id'] ?? 0) > 0) {
            $name = (string) (District::query()->whereKey((int) $filters['district_id'])->value('name') ?? '');
            if ($name !== '') {
                $query->whereRaw('LOWER(TRIM(district_name)) = ?', [mb_strtolower(trim($name))]);
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        if (($filters['spoc_id'] ?? '') === 'unassigned') {
            $query->whereRaw('1 = 0');
        } elseif (is_numeric($filters['spoc_id'] ?? '') && (int) $filters['spoc_id'] > 0) {
            $districtIds = $this->spocDistrictIds((int) $filters['spoc_id']);
            $names = $districtIds === []
                ? []
                : District::query()->whereIn('id', $districtIds)->pluck('name')
                    ->map(fn ($n) => mb_strtolower(trim((string) $n)))
                    ->filter()
                    ->values()
                    ->all();
            if ($names === []) {
                $query->whereRaw('1 = 0');
            } else {
                $query->where(function ($q) use ($names): void {
                    foreach ($names as $i => $n) {
                        if ($i === 0) {
                            $q->whereRaw('LOWER(TRIM(district_name)) = ?', [$n]);
                        } else {
                            $q->orWhereRaw('LOWER(TRIM(district_name)) = ?', [$n]);
                        }
                    }
                });
            }
        }

        if ((int) ($filters['given_by_id'] ?? 0) > 0) {
            $query->where('submitted_by_user_id', (int) $filters['given_by_id']);
        }

        if (($filters['has_docs'] ?? '') === '1') {
            $query->whereHas('items.media');
        } elseif (($filters['has_docs'] ?? '') === '0') {
            $query->whereDoesntHave('items.media');
        }

        if (($filters['date_from'] ?? '') !== '') {
            $query->whereRaw('DATE(COALESCE(service_date, created_at)) >= ?', [$filters['date_from']]);
        }
        if (($filters['date_to'] ?? '') !== '') {
            $query->whereRaw('DATE(COALESCE(service_date, created_at)) <= ?', [$filters['date_to']]);
        }

        return $query;
    }

    /**
     * @return Builder<AccelerationServiceSession>
     */
    private function buildAccelerationQuery(array $filters)
    {
        return $this->baseAccelerationQuery($filters)
            ->with([
                'items.media',
                'submitter:id,name',
            ])
            ->withCount('items');
    }

    /**
     * @return list<string>|null  null = no status constraint
     */
    private function accelerationStatusesForFilter(string $status): ?array
    {
        if ($status === '') {
            return null;
        }

        return match ($status) {
            ServiceCase::STATUS_PENDING_APPROVAL => [
                AccelerationServicesApproval::STATUS_PENDING_REVIEW,
                AccelerationServicesApproval::STATUS_PENDING_FINAL,
            ],
            ServiceCase::STATUS_SENT_BACK => [AccelerationServicesApproval::STATUS_SENT_BACK],
            ServiceCase::STATUS_APPROVED => [AccelerationServicesApproval::STATUS_APPROVED],
            default => [],
        };
    }

    private function accelerationRowStatus(mixed $session): string
    {
        $status = (string) ($session?->status ?? '');

        return match ($status) {
            AccelerationServicesApproval::STATUS_PENDING_REVIEW,
            AccelerationServicesApproval::STATUS_PENDING_FINAL => ServiceCase::STATUS_PENDING_APPROVAL,
            AccelerationServicesApproval::STATUS_SENT_BACK => ServiceCase::STATUS_SENT_BACK,
            AccelerationServicesApproval::STATUS_APPROVED => ServiceCase::STATUS_APPROVED,
            AccelerationServicesApproval::STATUS_DRAFT => ServiceCase::STATUS_DRAFT,
            default => $status,
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function accelerationListRow(AccelerationServiceSession $session): array
    {
        return [
            'type' => 'acceleration',
            'service_case' => null,
            'market_linkage' => null,
            'partner' => null,
            'acceleration' => $session,
            'linkage_mode' => '—',
            'partner_name' => '—',
            'updated_at' => $session->updated_at ?? $session->created_at,
        ];
    }

    /**
     * @return list<string|int>
     */
    private function exportAccelerationRow(AccelerationServiceSession $session, int $sn): array
    {
        $phone = (string) ($session->phone ?? '');
        if ($phone !== '' && preg_match('/^[\d\s+\-]{10,}$/', $phone)) {
            $phone = "\t".$phone;
        }

        $docs = 0;
        if ($session->relationLoaded('items')) {
            foreach ($session->items as $item) {
                $docs += $item->relationLoaded('media') ? $item->media->count() : 0;
            }
        }

        $spocRemark = (string) $session->status === AccelerationServicesApproval::STATUS_SENT_BACK
            ? (string) ($session->sent_back_remarks ?? '')
            : '';

        return [
            $sn,
            (string) ($session->application_no ?: ''),
            (string) ($session->application_no ?: ''),
            (string) ($session->applicant_name ?: ''),
            '',
            '',
            (string) ($session->district_name ?: ''),
            $phone,
            '',
            '',
            '',
            '',
            '',
            'Acceleration',
            AccelerationServiceSession::SERVICE_LIST_LABEL,
            'KEY',
            AccelerationServicesApproval::statusLabel($session->status),
            $session->service_date?->format('Y-m-d') ?? '',
            '',
            $this->fmtDate($session->created_at),
            (string) ($session->submitted_by_name ?? $session->submitter?->name ?? ''),
            (string) ($session->final_approved_by_name ?: $session->first_approved_by_name ?: 'State SPOC'),
            (string) ($session->final_approved_by_name ?: $session->first_approved_by_name ?: ''),
            $this->fmtDate($session->created_at),
            $docs > 0 ? $docs : (int) ($session->items_count ?? 0),
            $spocRemark,
        ];
    }

    private function laravelDistrictIdFromName(string $name): int
    {
        $norm = mb_strtolower(trim($name));
        if ($norm === '') {
            return 0;
        }

        static $map = null;
        if ($map === null) {
            $map = District::query()
                ->get(['id', 'name'])
                ->mapWithKeys(fn (District $district): array => [mb_strtolower(trim((string) $district->name)) => (int) $district->id])
                ->all();
        }

        return (int) ($map[$norm] ?? 0);
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
