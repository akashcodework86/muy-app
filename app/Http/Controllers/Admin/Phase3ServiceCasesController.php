<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\District;
use App\Models\Service;
use App\Models\ServiceCase;
use App\Models\ServiceCaseAttachment;
use App\Models\User;
use App\Services\LegacyApplicationServiceCaseSupport;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class Phase3ServiceCasesController extends Controller
{
    private bool $legacyPhase2JoinsApplied = false;

    private ?string $legacyPhase2DbSafe = null;

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

        $baseQuery = $this->buildFilteredQuery($filters);
        $this->applyFilters($baseQuery, $filters);

        $cases = $baseQuery
            ->orderByDesc('service_cases.created_at')
            ->paginate(20)
            ->withQueryString();

        $summaryRows = (clone $baseQuery)
            ->select('service_cases.status', DB::raw('COUNT(DISTINCT service_cases.id) as total'))
            ->groupBy('service_cases.status')
            ->pluck('total', 'status');

        $summary = [
            'total' => (int) $summaryRows->sum(),
            'approved' => (int) ($summaryRows[ServiceCase::STATUS_APPROVED] ?? 0),
            'pending_approval' => (int) ($summaryRows[ServiceCase::STATUS_PENDING_APPROVAL] ?? 0),
            'sent_back' => (int) ($summaryRows[ServiceCase::STATUS_SENT_BACK] ?? 0),
            'rejected' => (int) ($summaryRows[ServiceCase::STATUS_REJECTED] ?? 0),
        ];

        $statsQuery = $this->buildFilteredQuery($filters);
        $this->applyFilters($statsQuery, $filters, ignoreDistrictFilter: true);

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

        $legacyPreviews = $this->buildLegacyPreviewMap($cases->getCollection());

        return view('admin.phase3-services.index', [
            'cases' => $cases,
            'summary' => $summary,
            'filters' => $filters,
            'services' => Service::query()->orderBy('name')->get(['id', 'name', 'service_category_id']),
            'districts' => District::query()->orderBy('name')->get(['id', 'name']),
            'districtCounts' => $districtCounts,
            'spocs' => User::query()
                ->where('role', 'state_staff')
                ->orderBy('name')
                ->get(['id', 'name']),
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
        $query = $this->buildFilteredQuery($filters);
        $this->applyFilters($query, $filters);

        $rows = $query->orderByDesc('service_cases.created_at')->get();
        $legacyPreviews = $this->buildLegacyPreviewMap($rows);

        $spreadsheet = new Spreadsheet;
        $spreadsheet->getProperties()
            ->setCreator('MUY Admin')
            ->setTitle('Phase 3 Service Cases')
            ->setSubject('Phase 3 Service Cases Export')
            ->setDescription('Exported on '.now()->format('d M Y H:i'));

        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Service Cases');

        $headers = [
            'A' => ['label' => '#',                    'width' => 6],
            'B' => ['label' => 'Reference Number',     'width' => 22],
            'C' => ['label' => 'Application Number',   'width' => 22],
            'D' => ['label' => 'Applicant Name',       'width' => 26],
            'E' => ['label' => 'District',             'width' => 18],
            'F' => ['label' => 'Service Category',     'width' => 22],
            'G' => ['label' => 'Service Name',         'width' => 28],
            'H' => ['label' => 'Reporting Tier',       'width' => 16],
            'I' => ['label' => 'Status',               'width' => 18],
            'J' => ['label' => 'SLA Deadline',         'width' => 18],
            'K' => ['label' => 'Submitted At',         'width' => 18],
            'L' => ['label' => 'Submitted By',         'width' => 22],
            'M' => ['label' => 'SPOC',                 'width' => 22],
            'N' => ['label' => 'Approved By',          'width' => 22],
            'O' => ['label' => 'Created At',           'width' => 18],
            'P' => ['label' => 'Documents Count',      'width' => 16],
            'Q' => ['label' => 'Document Links',       'width' => 50],
        ];

        foreach ($headers as $col => $meta) {
            $sheet->setCellValue($col.'1', $meta['label']);
            $sheet->getColumnDimension($col)->setWidth($meta['width']);
        }

        $lastCol = array_key_last($headers);
        $headerRange = 'A1:'.$lastCol.'1';

        $sheet->getStyle($headerRange)->applyFromArray([
            'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E3A5F']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'FFFFFF']]],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(22);

        $statusColors = [
            'approved' => 'D1FAE5',
            'pending_approval' => 'FEF3C7',
            'sent_back' => 'FFEDD5',
            'rejected' => 'FEE2E2',
            'draft' => 'F3F4F6',
            'cancelled' => 'F1F5F9',
        ];

        $rowNum = 2;
        foreach ($rows as $case) {
            $lp = $legacyPreviews[(int) ($case->legacy_application_id ?? 0)] ?? null;
            $sheet->setCellValue('A'.$rowNum, $rowNum - 1);
            $sheet->setCellValue('B'.$rowNum, (string) ($case->reference_number ?: ''));
            $sheet->setCellValue('C'.$rowNum, (string) ($case->cfaSubmission?->application_no ?? ($lp['application_no'] ?? '')));
            $sheet->setCellValue('D'.$rowNum, (string) ($case->cfaSubmission?->applicant_name ?? ($lp['applicant_name'] ?? '')));
            $sheet->setCellValue('E'.$rowNum, (string) ($case->cfaSubmission?->district?->name ?? ($lp['district'] ?? '')));
            $sheet->setCellValue('F'.$rowNum, (string) ($case->service?->category?->name ?? ''));
            $sheet->setCellValue('G'.$rowNum, (string) ($case->service?->name ?? ''));
            $sheet->setCellValue('H'.$rowNum, strtoupper((string) ($case->service?->reporting_tier ?? 'UNSET')));
            $sheet->setCellValue('I'.$rowNum, ucfirst(str_replace('_', ' ', (string) $case->status)));
            $sheet->setCellValue('J'.$rowNum, $this->fmtDate($case->sla_deadline_at));
            $sheet->setCellValue('K'.$rowNum, $this->fmtDate($case->submitted_at));
            $sheet->setCellValue('L'.$rowNum, (string) ($case->submitter?->name ?? ''));
            $sheet->setCellValue('M'.$rowNum, (string) ($case->spoc?->name ?? 'Unassigned'));
            $sheet->setCellValue('N'.$rowNum, (string) ($case->approver?->name ?? ''));
            $sheet->setCellValue('O'.$rowNum, $this->fmtDate($case->created_at));
            $sheet->setCellValue('P'.$rowNum, (int) $case->attachments->count());

            $docParts = [];
            foreach ($case->attachments as $idx => $attachment) {
                $url = route('admin.phase3-services.attachments.view', [
                    'service_case' => $case->id,
                    'attachment' => $attachment->id,
                ]);
                $label = ($attachment->original_name ?: 'Doc '.($idx + 1));
                $docParts[] = $url.' ('.$label.')';
            }
            $sheet->setCellValue('Q'.$rowNum, implode("\n", $docParts));
            if (! empty($docParts)) {
                $sheet->getStyle('Q'.$rowNum)->getAlignment()->setWrapText(true);
            }

            $bgColor = $statusColors[$case->status] ?? 'FFFFFF';
            $sheet->getStyle('A'.$rowNum.':'.$lastCol.$rowNum)->applyFromArray([
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $bgColor]],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E5E7EB']]],
                'alignment' => ['vertical' => Alignment::VERTICAL_TOP],
            ]);

            $sheet->getStyle('A'.$rowNum)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('P'.$rowNum)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $rowNum++;
        }

        if ($rowNum > 2) {
            $sheet->setAutoFilter('A1:'.$lastCol.'1');
        }

        $sheet->freezePane('A2');

        $fileName = 'phase3-service-cases-'.now()->format('Ymd_His').'.xlsx';

        return response()->streamDownload(function () use ($spreadsheet): void {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'max-age=0',
        ]);
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

    private function applyFilters($query, array $filters, bool $ignoreDistrictFilter = false): void
    {
        if ($filters['q'] !== '') {
            $like = '%'.$filters['q'].'%';
            $query->where(function ($q) use ($like): void {
                $q->where('service_cases.reference_number', 'like', $like)
                    ->orWhere('cfa_submissions.application_no', 'like', $like)
                    ->orWhere('cfa_submissions.applicant_name', 'like', $like);
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

        if ($filters['service_id'] > 0) {
            $query->where('service_cases.service_id', $filters['service_id']);
        }

        if ($filters['spoc_id'] === 'unassigned') {
            $query->whereNull('service_cases.spoc_user_id');
        } elseif (is_numeric($filters['spoc_id']) && (int) $filters['spoc_id'] > 0) {
            $query->where('service_cases.spoc_user_id', (int) $filters['spoc_id']);
        }

        if ($filters['status'] !== '') {
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
                'service.category:id,name',
                'cfaSubmission:id,application_no,applicant_name,district_id',
                'cfaSubmission.district:id,name',
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
     * @param  Collection<int, ServiceCase>|\Illuminate\Database\Eloquent\Collection<int, ServiceCase>  $cases
     * @return array<int, array{applicant_name: string, application_no: string, district: string}|null>
     */
    private function buildLegacyPreviewMap($cases): array
    {
        $support = app(LegacyApplicationServiceCaseSupport::class);
        if (! $support->legacyDbAvailable()) {
            return [];
        }

        $out = [];
        foreach ($cases as $case) {
            $lid = (int) ($case->legacy_application_id ?? 0);
            if ($lid < 1 || array_key_exists($lid, $out)) {
                continue;
            }
            $out[$lid] = $support->incubateePreview($lid);
        }

        return $out;
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
            'service_id' => (int) $request->query('service_id', 0),
            'spoc_id' => trim((string) $request->query('spoc_id', '')),
            'status' => trim((string) $request->query('status', '')),
            'reporting_tier' => trim((string) $request->query('reporting_tier', '')),
            'has_docs' => trim((string) $request->query('has_docs', '')),
            'sla_breached' => '',
            'date_from' => trim((string) $request->query('date_from', '')),
            'date_to' => trim((string) $request->query('date_to', '')),
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
