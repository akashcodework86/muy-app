<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\CfaSubmission;
use App\Models\MarketLinkagePartner;
use App\Models\MarketLinkageSubmission;
use App\Models\Service;
use App\Models\ServiceCase;
use App\Models\ServiceCaseAttachment;
use App\Models\User;
use App\Services\AppSettingsService;
use App\Services\LegacyApplicationServiceCaseSupport;
use App\Services\MisFieldActivityListService;
use App\Services\ReapIncubateeTargetProgressService;
use App\Services\SchemaValidator;
use App\Services\ServiceCaseRecorder;
use App\Support\ConvergenceReapSupport;
use App\Support\ConvergenceReapSupportDeliverablesSupport;
use App\Support\ServiceFieldTypes;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class StaffServiceCaseController extends Controller
{
    public function __construct(
        private AppSettingsService $settings,
        private ServiceCaseRecorder $recorder,
        private LegacyApplicationServiceCaseSupport $legacyApplications,
        private MisFieldActivityListService $fieldMisList,
        private ReapIncubateeTargetProgressService $reapTargetsProgress,
    ) {}

    public function index(Request $request): View
    {
        $this->ensureModuleOn();
        $staff = $this->staffOrAbort($request);

        $scope = (string) $request->query('scope', 'my');
        if (! in_array($scope, ['my', 'all'], true)) {
            $scope = 'my';
        }
        $status = (string) $request->query('status', '');
        $serviceIdRaw = (string) $request->query('service_id', '');
        $recordType = '';
        $serviceId = 0;
        if ($serviceIdRaw === 'market_linkage') {
            $recordType = 'market_linkage';
        } elseif ($serviceIdRaw === ConvergenceReapSupport::MIS_8_2_LIST_FILTER) {
            $recordType = ConvergenceReapSupport::MIS_8_2_LIST_FILTER;
        } elseif (MisFieldActivityListService::isListFilterValue($serviceIdRaw)) {
            $recordType = $serviceIdRaw;
        } else {
            $serviceId = (int) $serviceIdRaw;
        }
        $allowed = ['', ServiceCase::STATUS_DRAFT, ServiceCase::STATUS_PENDING_APPROVAL, ServiceCase::STATUS_SENT_BACK, ServiceCase::STATUS_APPROVED, ServiceCase::STATUS_REJECTED];
        if (! in_array($status, $allowed, true)) {
            $status = '';
        }

        $districtId = (int) $staff->district_id;
        $legacyIds = $this->legacyApplications->legacyApplicationIdsInLaravelDistrict($districtId);

        $listItems = collect();

        $reapOnly = $recordType === ConvergenceReapSupport::MIS_8_2_LIST_FILTER;
        $fieldMisOnly = $recordType !== '' && MisFieldActivityListService::isListFilterValue($recordType);
        $includeFieldMis = ! $reapOnly && ($fieldMisOnly || ($recordType === '' && $serviceId <= 0 && $recordType !== 'market_linkage'));

        if (! $fieldMisOnly && $recordType !== 'market_linkage') {
            $q = ServiceCase::query()
                ->where(function ($outer) use ($districtId, $legacyIds): void {
                    $outer->whereHas('cfaSubmission', fn ($qq) => $qq->where('district_id', $districtId));
                    if (ServiceCase::supportsLegacyApplicationLink() && $legacyIds !== []) {
                        $outer->orWhere(function ($qq) use ($legacyIds): void {
                            $qq->whereNotNull('legacy_application_id')
                                ->whereNull('cfa_submission_id')
                                ->whereIn('legacy_application_id', $legacyIds);
                        });
                    }
                })
                ->with([
                    'cfaSubmission:id,applicant_name,application_no,district_id',
                    'service.category',
                    'submitter:id,name',
                    'creator:id,name',
                    'spoc:id,name',
                    'approver:id,name',
                    'rejector:id,name',
                    'attachments:id,service_case_id,original_name,size_bytes,disk,path',
                ]);

            if ($scope === 'my') {
                $q->where(function ($qq) use ($staff): void {
                    $qq->where('created_by', (int) $staff->id)
                        ->orWhere('submitted_by', (int) $staff->id);
                });
            }

            if ($status !== '') {
                $q->where('status', $status);
            }

            if ($reapOnly) {
                ConvergenceReapSupportDeliverablesSupport::applyListingScope($q, 'service_cases');
            } elseif ($serviceId > 0) {
                $q->where('service_id', $serviceId);
            }

            foreach ($q->orderByDesc('updated_at')->get() as $case) {
                if (ServiceCase::supportsLegacyApplicationLink() && $case->legacy_application_id && ! $case->cfa_submission_id) {
                    $case->legacyIncubateePreview = $this->legacyApplications->incubateePreview((int) $case->legacy_application_id);
                }
                $listItems->push([
                    'type' => 'service_case',
                    'service_case' => $case,
                    'market_linkage' => null,
                    'updated_at' => $case->updated_at,
                ]);
            }
        }

        $includeMarketLinkage = ! $fieldMisOnly && ($recordType === 'market_linkage' || (! $reapOnly && $serviceId <= 0));
        if ($includeMarketLinkage && Schema::hasTable('market_linkage_submissions') && MarketLinkageSubmission::supportsWorkflow()) {
            $mlq = MarketLinkageSubmission::query()
                ->where('district_id', $districtId)
                ->with(['spoc:id,name', 'approver:id,name', 'rejector:id,name', 'submitter:id,name', 'partners']);

            if ($scope === 'my') {
                $mlq->where('submitted_by_user_id', (int) $staff->id);
            }
            if ($status !== '') {
                $mlq->where('status', $status);
            }
            if ($recordType === 'market_linkage') {
                // only market linkage rows
            }

            foreach ($mlq->orderByDesc('updated_at')->get() as $ml) {
                $listItems->push([
                    'type' => 'market_linkage',
                    'service_case' => null,
                    'market_linkage' => $ml,
                    'updated_at' => $ml->updated_at,
                ]);
            }
        }

        if ($includeFieldMis) {
            $fieldMisRecords = $this->fieldMisList->recordsForStaffList(
                $districtId,
                (int) $staff->id,
                $scope,
                $status,
                $fieldMisOnly ? $recordType : '',
                ! $fieldMisOnly,
            );

            foreach ($fieldMisRecords as $fm) {
                $listItems->push([
                    'type' => 'field_mis',
                    'service_case' => null,
                    'market_linkage' => null,
                    'field_mis' => $fm,
                    'field_mis_module' => $this->fieldMisList->moduleKeyForRecord($fm),
                    'updated_at' => $fm->updated_at,
                ]);
            }
        }

        $sorted = $listItems->sortByDesc(fn (array $row) => $row['updated_at']?->timestamp ?? 0)->values();
        $perPage = 20;
        $page = max(1, (int) $request->query('page', 1));
        $total = $sorted->count();
        $pageItems = $sorted->forPage($page, $perPage)->values();
        $cases = new LengthAwarePaginator(
            $pageItems,
            $total,
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        $services = Service::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('staff.services.index', [
            'cases' => $cases,
            'filterStatus' => $status,
            'filterServiceId' => $serviceId,
            'filterRecordType' => $recordType,
            'filterScope' => $scope,
            'services' => $services,
            'staffDeleteEnabled' => $this->settings->isEnabled('service_module.staff_delete_enabled'),
            'reapTargetsProgress' => $reapOnly
                ? $this->reapTargetsProgress->districtProgress($districtId)
                : null,
        ]);
    }

    public function create(Request $request): View
    {
        $this->ensureModuleOn();
        $staff = $this->staffOrAbort($request);

        try {
            $services = $this->pickerServices();
        } catch (Throwable $e) {
            report($e);
            $services = collect();
        }

        $eligible = $this->eligibleSubmissions($staff);
        $prefillId = (int) $request->query('cfa_submission_id', 0);
        if ($prefillId > 0 && ! (clone $eligible)->whereKey($prefillId)->exists()) {
            $prefillId = 0;
        }
        $prefillLegacyId = (int) $request->query('legacy_application_id', 0);
        try {
            $legacyRows = $this->legacyApplications->eligibleLegacyApplicationsForStaff($staff);
        } catch (Throwable $e) {
            report($e);
            $legacyRows = collect();
        }
        if ($prefillLegacyId > 0 && ! $legacyRows->contains(fn ($r) => (int) $r->id === $prefillLegacyId)) {
            $prefillLegacyId = 0;
        }

        $submissionIds = (clone $eligible)->pluck('id')->map(fn ($id) => (int) $id)->all();
        $legacyIds = $legacyRows->pluck('id')->map(fn ($id) => (int) $id)->all();
        $nonMultipleServiceIds = $services
            ->filter(fn (Service $s) => ! (bool) $s->allows_multiple)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $existingCfaPairs = ServiceCase::query()
            ->select(['cfa_submission_id', 'service_id'])
            ->when($submissionIds !== [], fn ($q) => $q->whereIn('cfa_submission_id', $submissionIds), fn ($q) => $q->whereRaw('1 = 0'))
            ->when($nonMultipleServiceIds !== [], fn ($q) => $q->whereIn('service_id', $nonMultipleServiceIds), fn ($q) => $q->whereRaw('1 = 0'))
            ->whereNotNull('cfa_submission_id')
            ->get()
            ->map(fn ($r) => 'c:'.((int) $r->cfa_submission_id).':'.((int) $r->service_id))
            ->values();

        $existingLegacyPairs = ServiceCase::supportsLegacyApplicationLink()
            ? ServiceCase::query()
                ->select(['legacy_application_id', 'service_id'])
                ->when($legacyIds !== [], fn ($q) => $q->whereIn('legacy_application_id', $legacyIds), fn ($q) => $q->whereRaw('1 = 0'))
                ->when($nonMultipleServiceIds !== [], fn ($q) => $q->whereIn('service_id', $nonMultipleServiceIds), fn ($q) => $q->whereRaw('1 = 0'))
                ->whereNotNull('legacy_application_id')
                ->get()
                ->map(fn ($r) => 'l:'.((int) $r->legacy_application_id).':'.((int) $r->service_id))
                ->values()
            : collect();

        $existingPairs = $existingCfaPairs->toBase()->merge($existingLegacyPairs)->values();

        $priorCfaCases = ServiceCase::query()
            ->with(['service:id,name', 'submitter:id,name', 'creator:id,name'])
            ->when($submissionIds !== [], fn ($q) => $q->whereIn('cfa_submission_id', $submissionIds), fn ($q) => $q->whereRaw('1 = 0'))
            ->whereNotNull('cfa_submission_id')
            ->orderByDesc('created_at')
            ->get(['id', 'cfa_submission_id', 'service_id', 'status', 'submitted_by', 'created_by', 'created_at']);

        $priorLegacyCases = ServiceCase::query()
            ->with(['service:id,name', 'submitter:id,name', 'creator:id,name'])
            ->when($legacyIds !== [], fn ($q) => $q->whereIn('legacy_application_id', $legacyIds), fn ($q) => $q->whereRaw('1 = 0'))
            ->whereNotNull('legacy_application_id')
            ->orderByDesc('created_at')
            ->get(['id', 'legacy_application_id', 'service_id', 'status', 'submitted_by', 'created_by', 'created_at']);

        $priorCasesJson = [
            'cfa' => $priorCfaCases
                ->groupBy(fn (ServiceCase $case) => (int) $case->cfa_submission_id)
                ->map(fn (Collection $rows) => $rows->map(function (ServiceCase $case): array {
                    return [
                        'service_name' => (string) ($case->service?->name ?? 'Service'),
                        'status' => (string) $case->status,
                        'staff_name' => (string) ($case->submitter?->name ?? $case->creator?->name ?? 'Unknown'),
                        'created_at' => optional($case->created_at)->timezone(config('app.timezone'))->format('d M Y H:i') ?? '',
                    ];
                })->values()->all())
                ->all(),
            'legacy' => $priorLegacyCases
                ->groupBy(fn (ServiceCase $case) => (int) $case->legacy_application_id)
                ->map(fn (Collection $rows) => $rows->map(function (ServiceCase $case): array {
                    return [
                        'service_name' => (string) ($case->service?->name ?? 'Service'),
                        'status' => (string) $case->status,
                        'staff_name' => (string) ($case->submitter?->name ?? $case->creator?->name ?? 'Unknown'),
                        'created_at' => optional($case->created_at)->timezone(config('app.timezone'))->format('d M Y H:i') ?? '',
                    ];
                })->values()->all())
                ->all(),
        ];

        try {
            $legacyPriorJson = $legacyRows->mapWithKeys(function ($row) {
                $id = (int) $row->id;

                return [
                    $id => [
                        'prior' => $this->legacyApplications->legacyAssignedServicesForDisplay($id),
                        'blocked_service_ids' => $this->legacyApplications->blockedServiceIds($id, null),
                    ],
                ];
            })->all();
        } catch (Throwable $e) {
            report($e);
            $legacyPriorJson = [];
        }

        $servicesJson = $services->map(function (Service $s) {
            $schema = [];
            try {
                $schema = ServiceFieldTypes::normalizeSchema($s->field_schema ?? []);
            } catch (Throwable $e) {
                report($e);
            }

            return [
                'id' => $s->id,
                'name' => $s->name,
                'category_name' => $s->category?->name ?? 'Other',
                'category_slug' => $s->category?->slug ?? '',
                'is_convergence' => ConvergenceReapSupport::categoryIsConvergence($s->category),
                'counts_toward_reap_support' => ConvergenceReapSupport::serviceIsReapSupportService($s),
                'requires_document' => (bool) $s->requires_document,
                'requires_approval' => (bool) $s->requires_approval,
                'allows_multiple' => (bool) $s->allows_multiple,
                'schema' => $schema,
            ];
        })->values();

        $priorMarketLinkageJson = $this->priorMarketLinkageJson($submissionIds, $legacyIds);

        return view('staff.services.create', [
            'submissions' => $eligible->get(),
            'legacyRows' => $legacyRows,
            'legacyPriorJson' => $legacyPriorJson,
            'defaultCfaSubmissionId' => $prefillId,
            'defaultLegacyApplicationId' => $prefillLegacyId,
            'services' => $services,
            'servicesJson' => $servicesJson,
            'existingNonMultiplePairs' => $existingPairs,
            'priorCasesJson' => $priorCasesJson,
            'priorMarketLinkageJson' => $priorMarketLinkageJson,
            'marketLinkageCreateUrl' => route('staff.market-linkages.create'),
            'reapTargetsProgress' => $this->reapTargetsProgress->districtProgress((int) $staff->district_id),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->ensureModuleOn();
        $staff = $this->staffOrAbort($request);

        $validated = $request->validate([
            'cfa_submission_id' => ['nullable', 'integer', 'exists:cfa_submissions,id'],
            'legacy_application_id' => ['nullable', 'integer', 'min:1'],
            'service_id' => ['required', 'integer', 'exists:services,id'],
            'reference_number' => ['nullable', 'string', 'max:191'],
            'delivered_on' => ['nullable', 'date'],
            'payload' => ['nullable', 'array'],
            'payload_files' => ['nullable', 'array'],
            'payload_files.reap_document' => ['nullable', 'file', 'max:5120'],
            'attachments' => ['nullable', 'array', 'max:3'],
            'attachments.*' => ['file', 'max:5120', 'mimes:pdf,jpg,jpeg,png,webp'],
        ]);

        $cfaId = (int) ($validated['cfa_submission_id'] ?? 0);
        $legacyId = (int) ($validated['legacy_application_id'] ?? 0);
        if (($cfaId > 0) === ($legacyId > 0)) {
            throw ValidationException::withMessages([
                'cfa_submission_id' => 'Choose exactly one incubatee: either a Phase 3 CFA or a Phase 2 legacy application.',
            ]);
        }
        if ($legacyId > 0 && ! ServiceCase::supportsLegacyApplicationLink()) {
            throw ValidationException::withMessages([
                'legacy_application_id' => 'Legacy application–linked cases are not enabled until the server database is updated (run migrations). Use a Phase 3 CFA incubatee instead.',
            ]);
        }

        $service = Service::query()->whereKey((int) $validated['service_id'])->firstOrFail();
        if (! $service->is_active) {
            return back()->withErrors(['service_id' => 'This service is inactive.'])->withInput();
        }

        $blocked = $cfaId > 0
            ? $this->legacyApplications->blockedServiceIds(null, $cfaId)
            : $this->legacyApplications->blockedServiceIds($legacyId, null);

        if (! $service->allows_multiple && in_array((int) $service->id, $blocked, true)) {
            throw ValidationException::withMessages([
                'service_id' => 'This incubatee already has this service (Phase 2 legacy or an open case in this MIS).',
            ]);
        }

        $payload = is_array($validated['payload'] ?? null) ? $validated['payload'] : [];
        $uploads = array_values($request->file('attachments', []));

        // Schema file fields upload into case attachments and store filename in payload.
        $schema = ServiceFieldTypes::normalizeSchema($service->field_schema ?? []);
        $fileKeys = collect($schema)
            ->filter(fn (array $field) => ($field['type'] ?? null) === ServiceFieldTypes::FILE)
            ->map(fn (array $field) => (string) ($field['key'] ?? ''))
            ->filter(fn (string $key) => $key !== '')
            ->values();

        foreach ($fileKeys as $key) {
            $file = $request->file('payload_files.'.$key);
            if ($file instanceof UploadedFile && $file->isValid()) {
                $payload[$key] = $file->getClientOriginalName();
                $uploads[] = $file;
            }
        }

        [$payload, $uploads, $reapDocumentUploaded] = $this->appendReapDocumentFromRequest($request, $service, $payload, $uploads);

        try {
            if ($cfaId > 0) {
                $submission = CfaSubmission::query()->findOrFail($cfaId);
                $this->assertSubmissionEligible($staff, $submission);
                $case = $this->recorder->createDraft($submission, $service, (int) $staff->id);
            } else {
                $case = $this->recorder->createLegacyDraft($legacyId, $service, $staff);
            }

            $this->recorder->submit($case, [
                'actor_id' => (int) $staff->id,
                'reference_number' => $validated['reference_number'] ?? null,
                'delivered_on' => $validated['delivered_on'] ?? null,
                'payload' => $payload,
                'reap_document_uploaded' => $reapDocumentUploaded,
            ], $uploads);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        return redirect()
            ->route('staff.services.index')
            ->with('status', 'Service case submitted.');
    }

    public function show(Request $request, ServiceCase $service_case): View
    {
        $this->ensureModuleOn();
        $staff = $this->staffOrAbort($request);
        $this->assertCaseInDistrict($staff, $service_case);

        $service_case->load([
            'cfaSubmission.district',
            'service.category',
            'attachments.uploader',
            'events.user',
        ]);

        $legacyIncubateePreview = null;
        if ($service_case->legacy_application_id && ! $service_case->cfa_submission_id) {
            $legacyIncubateePreview = $this->legacyApplications->incubateePreview((int) $service_case->legacy_application_id);
        }

        return view('staff.services.show', [
            'case' => $service_case,
            'legacyIncubateePreview' => $legacyIncubateePreview,
            'staffDeleteEnabled' => $this->settings->isEnabled('service_module.staff_delete_enabled'),
        ]);
    }

    public function edit(Request $request, ServiceCase $service_case): View
    {
        $this->ensureModuleOn();
        $staff = $this->staffOrAbort($request);
        $this->assertCaseInDistrict($staff, $service_case);
        $this->assertCaseOwnedByStaff($staff, $service_case);
        abort_unless($this->canEditByStaff($service_case), 403, 'This case cannot be edited now.');

        $service_case->load([
            'cfaSubmission:id,applicant_name,application_no,district_id',
            'service.category',
            'attachments',
        ]);

        $legacyIncubateePreview = null;
        if ($service_case->legacy_application_id && ! $service_case->cfa_submission_id) {
            $legacyIncubateePreview = $this->legacyApplications->incubateePreview((int) $service_case->legacy_application_id);
        }

        $reapTargetsProgress = null;
        if (ConvergenceReapSupport::serviceIsConvergence($service_case->service)
            || ConvergenceReapSupport::serviceIsReapSupportService($service_case->service)) {
            $reapTargetsProgress = $this->reapTargetsProgress->districtProgress((int) $staff->district_id);
        }

        return view('staff.services.edit', [
            'case' => $service_case,
            'schema' => ServiceFieldTypes::normalizeSchema($service_case->service?->field_schema ?? []),
            'payload' => is_array($service_case->payload) ? $service_case->payload : [],
            'legacyIncubateePreview' => $legacyIncubateePreview,
            'isConvergenceService' => ConvergenceReapSupport::serviceIsConvergence($service_case->service),
            'isReapSupportService' => ConvergenceReapSupport::serviceIsReapSupportService($service_case->service),
            'reapTargetsProgress' => $reapTargetsProgress,
        ]);
    }

    public function update(Request $request, ServiceCase $service_case): RedirectResponse
    {
        $this->ensureModuleOn();
        $staff = $this->staffOrAbort($request);
        $this->assertCaseInDistrict($staff, $service_case);
        $this->assertCaseOwnedByStaff($staff, $service_case);
        abort_unless($this->canEditByStaff($service_case), 403, 'This case cannot be edited now.');

        $validated = $request->validate([
            'reference_number' => ['nullable', 'string', 'max:191'],
            'delivered_on' => ['nullable', 'date'],
            'payload' => ['nullable', 'array'],
            'payload_files' => ['nullable', 'array'],
            'payload_files.reap_document' => ['nullable', 'file', 'max:5120'],
            'attachments' => ['nullable', 'array', 'max:3'],
            'attachments.*' => ['file', 'max:5120', 'mimes:pdf,jpg,jpeg,png,webp'],
        ]);

        $service_case->loadMissing('service');
        $service = $service_case->service;
        if (! $service || ! $service->is_active) {
            return back()->withErrors(['service' => 'This service is inactive.'])->withInput();
        }

        $payload = is_array($validated['payload'] ?? null) ? $validated['payload'] : [];
        $uploads = array_values($request->file('attachments', []));
        $schema = ServiceFieldTypes::normalizeSchema($service->field_schema ?? []);
        $fileKeys = collect($schema)
            ->filter(fn (array $field) => ($field['type'] ?? null) === ServiceFieldTypes::FILE)
            ->map(fn (array $field) => (string) ($field['key'] ?? ''))
            ->filter(fn (string $key) => $key !== '')
            ->values();

        foreach ($fileKeys as $key) {
            $file = $request->file('payload_files.'.$key);
            if ($file instanceof UploadedFile && $file->isValid()) {
                $payload[$key] = $file->getClientOriginalName();
                $uploads[] = $file;
            }
        }

        [$payload, $uploads, $reapDocumentUploaded] = $this->appendReapDocumentFromRequest($request, $service, $payload, $uploads);
        $existingPayload = is_array($service_case->payload) ? $service_case->payload : [];
        $existingReapDocument = trim((string) ($existingPayload[ConvergenceReapSupport::REAP_DOCUMENT_KEY] ?? ''));

        try {
            if ($service_case->status === ServiceCase::STATUS_APPROVED) {
                $this->updateApprovedCase(
                    $service_case,
                    $service,
                    $payload,
                    $validated,
                    $uploads,
                    (int) $staff->id,
                    $reapDocumentUploaded,
                    $existingReapDocument !== '' ? $existingReapDocument : null,
                );

                return redirect()
                    ->route('staff.services.index')
                    ->with('status', 'Service case updated.');
            }

            // Allow editing from any other staff-visible state by reopening into sent_back flow.
            if (! in_array($service_case->status, [ServiceCase::STATUS_DRAFT, ServiceCase::STATUS_SENT_BACK], true)) {
                $service_case->status = ServiceCase::STATUS_SENT_BACK;
                $service_case->save();
            }

            $this->recorder->submit($service_case, [
                'actor_id' => (int) $staff->id,
                'reference_number' => $validated['reference_number'] ?? null,
                'delivered_on' => $validated['delivered_on'] ?? null,
                'payload' => $payload,
                'reap_document_uploaded' => $reapDocumentUploaded,
            ], $uploads);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        return redirect()
            ->route('staff.services.index')
            ->with('status', 'Service case updated.');
    }

    public function destroy(Request $request, ServiceCase $service_case): RedirectResponse
    {
        $this->ensureModuleOn();
        abort_unless(
            $this->settings->isEnabled('service_module.staff_delete_enabled'),
            403,
            'Deleting service cases is turned off. Ask your state admin to enable it under Service module settings.'
        );
        $staff = $this->staffOrAbort($request);
        $this->assertCaseInDistrict($staff, $service_case);
        $this->assertCaseOwnedByStaff($staff, $service_case);

        try {
            $this->recorder->staffDelete($service_case, (int) $staff->id);
        } catch (ValidationException $e) {
            return redirect()
                ->route('staff.services.index')
                ->withErrors($e->errors());
        }

        return redirect()
            ->route('staff.services.index')
            ->with('status', 'Case deleted.');
    }

    public function downloadAttachment(Request $request, ServiceCase $service_case, int $attachment)
    {
        $this->ensureModuleOn();
        $staff = $this->staffOrAbort($request);
        $this->assertCaseInDistrict($staff, $service_case);
        $attachmentRecord = $service_case->attachments()->whereKey($attachment)->firstOrFail();

        $disk = Storage::disk((string) $attachmentRecord->disk);
        $stream = null;
        if ($disk->exists($attachmentRecord->path)) {
            $stream = $disk->readStream($attachmentRecord->path);
        } else {
            $path = ltrim((string) $attachmentRecord->path, '/');
            $prefixedPrivate = str_starts_with($path, 'private/') ? $path : 'private/'.$path;
            $prefixedPublic = str_starts_with($path, 'public/') ? $path : 'public/'.$path;

            foreach (['local', 'public'] as $fallbackDiskName) {
                $fallbackDisk = Storage::disk($fallbackDiskName);
                foreach ([$path, $prefixedPrivate, $prefixedPublic] as $candidate) {
                    if ($fallbackDisk->exists($candidate)) {
                        $stream = $fallbackDisk->readStream($candidate);
                        break 2;
                    }
                }
            }

            if (! is_resource($stream)) {
                $legacyPaths = [
                    storage_path('app/'.$path),
                    storage_path('app/private/'.$path),
                    storage_path('app/public/'.$path),
                    public_path('storage/'.$path),
                ];
                foreach ($legacyPaths as $legacyPath) {
                    if (is_file($legacyPath) && is_readable($legacyPath)) {
                        return response()->file($legacyPath, [
                            'Content-Disposition' => 'inline; filename="'.$attachmentRecord->original_name.'"',
                        ]);
                    }
                }
            }
        }

        abort_unless(is_resource($stream), 404);

        $ext = strtolower((string) pathinfo((string) $attachmentRecord->original_name, PATHINFO_EXTENSION));
        $contentType = match ($ext) {
            'pdf' => 'application/pdf',
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            default => 'application/octet-stream',
        };

        return response()->stream(function () use ($stream): void {
            fpassthru($stream);
            fclose($stream);
        }, 200, [
            'Content-Type' => $contentType,
            'Content-Disposition' => 'inline; filename="'.$attachmentRecord->original_name.'"',
        ]);
    }

    private function ensureModuleOn(): void
    {
        abort_unless(
            $this->settings->isEnabled('service_module.enabled'),
            403,
            'The service module is turned off. Ask your state admin to enable it under Admin → More → Service module settings.'
        );
    }

    private function staffOrAbort(Request $request): User
    {
        $u = $request->user();
        abort_unless($u && $u->role === 'district_staff' && (int) $u->district_id > 0, 403);

        return $u;
    }

    private function eligibleSubmissions(User $staff)
    {
        $q = CfaSubmission::query()
            ->where('district_id', (int) $staff->district_id)
            ->orderBy('applicant_name');

        if ($this->settings->get('service_module.eligibility', 'onboarded_only') === 'onboarded_only') {
            $q->whereHas('onboardingBatchMembership');
        }

        return $q;
    }

    private function assertSubmissionEligible(User $staff, CfaSubmission $submission): void
    {
        abort_unless((int) $submission->district_id === (int) $staff->district_id, 403);
        if ($this->settings->get('service_module.eligibility', 'onboarded_only') === 'onboarded_only'
            && ! $submission->onboardingBatchMembership()->exists()) {
            throw ValidationException::withMessages([
                'cfa_submission_id' => 'This incubatee is not in an onboarding batch yet.',
            ]);
        }
    }

    private function assertCaseInDistrict(User $staff, ServiceCase $case): void
    {
        if ($case->cfa_submission_id) {
            $case->loadMissing('cfaSubmission');
            abort_unless(
                $case->cfaSubmission && (int) $case->cfaSubmission->district_id === (int) $staff->district_id,
                403
            );

            return;
        }

        if ($case->legacy_application_id) {
            $this->legacyApplications->assertLegacyApplicationInStaffDistrict($staff, (int) $case->legacy_application_id);

            return;
        }

        abort(403);
    }

    private function assertCaseOwnedByStaff(User $staff, ServiceCase $case): void
    {
        $ownerIds = array_filter([
            (int) ($case->created_by ?? 0),
            (int) ($case->submitted_by ?? 0),
        ]);
        abort_unless(in_array((int) $staff->id, $ownerIds, true), 403, 'You can edit/delete only your own service cases.');
    }

    private function canEditByStaff(ServiceCase $case): bool
    {
        return in_array($case->status, [
            ServiceCase::STATUS_DRAFT,
            ServiceCase::STATUS_PENDING_APPROVAL,
            ServiceCase::STATUS_SENT_BACK,
            ServiceCase::STATUS_APPROVED,
            ServiceCase::STATUS_REJECTED,
        ], true);
    }

    /**
     * @return Collection<int, Service>
     */
    private function pickerServices()
    {
        return Service::query()
            ->where('is_active', true)
            ->with(['category'])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->filter(fn (Service $s) => $s->category !== null)
            ->values();
    }

    /**
     * @param  list<int>  $submissionIds
     * @param  list<int>  $legacyIds
     * @return array{cfa: array<int, list<array<string, mixed>>>, legacy: array<int, list<array<string, mixed>>>}
     */
    private function priorMarketLinkageJson(array $submissionIds, array $legacyIds): array
    {
        $empty = ['cfa' => [], 'legacy' => []];

        if (! Schema::hasTable('market_linkage_submissions')) {
            return $empty;
        }

        $format = function (MarketLinkageSubmission $submission): array {
            return [
                'id' => (int) $submission->id,
                'staff_name' => (string) $submission->submitted_by_name,
                'created_at' => optional($submission->created_at)->timezone(config('app.timezone'))->format('d M Y H:i') ?? '',
                'show_url' => route('staff.market-linkages.show', $submission),
                'partners' => $submission->partners->map(fn ($partner) => [
                    'partner_name' => (string) $partner->partner_name,
                    'linkage_mode' => MarketLinkageSubmission::linkageModeLabel((string) $partner->linkage_mode),
                    'linkage_mode_raw' => (string) $partner->linkage_mode,
                    'linkage_date' => $partner->linkage_date?->format('Y-m-d') ?? '',
                    'linkage_date_display' => $partner->linkage_date?->format('d M Y') ?? '',
                    'link_url' => is_string($partner->link_url) && $partner->link_url !== '' ? (string) $partner->link_url : null,
                    'link_href' => MarketLinkagePartner::clickableHref($partner->link_url),
                    'has_document' => $partner->hasDocument(),
                ])->values()->all(),
            ];
        };

        $cfaRows = MarketLinkageSubmission::query()
            ->approved()
            ->with('partners')
            ->when($submissionIds !== [], fn ($q) => $q->whereIn('cfa_submission_id', $submissionIds), fn ($q) => $q->whereRaw('1 = 0'))
            ->whereNotNull('cfa_submission_id')
            ->orderByDesc('created_at')
            ->get();

        $legacyRows = MarketLinkageSubmission::query()
            ->approved()
            ->with('partners')
            ->when($legacyIds !== [], fn ($q) => $q->whereIn('legacy_application_id', $legacyIds), fn ($q) => $q->whereRaw('1 = 0'))
            ->whereNotNull('legacy_application_id')
            ->orderByDesc('created_at')
            ->get();

        return [
            'cfa' => $cfaRows
                ->groupBy(fn (MarketLinkageSubmission $row) => (int) $row->cfa_submission_id)
                ->map(fn (Collection $rows) => $rows->map($format)->values()->all())
                ->all(),
            'legacy' => $legacyRows
                ->groupBy(fn (MarketLinkageSubmission $row) => (int) $row->legacy_application_id)
                ->map(fn (Collection $rows) => $rows->map($format)->values()->all())
                ->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  list<UploadedFile|array{file: UploadedFile, any_mime?: bool}>  $uploads
     * @return array{0: array<string, mixed>, 1: list<UploadedFile|array{file: UploadedFile, any_mime?: bool}>, 2: bool}
     */
    private function appendReapDocumentFromRequest(Request $request, Service $service, array $payload, array $uploads): array
    {
        $reapDocumentUploaded = false;
        if (! ConvergenceReapSupport::serviceUsesReapWorkflow($service)) {
            return [$payload, $uploads, $reapDocumentUploaded];
        }

        $reapFile = $request->file('payload_files.reap_document');
        if ($reapFile instanceof UploadedFile && $reapFile->isValid()) {
            ConvergenceReapSupport::assertReapDocumentUpload($reapFile);
            $payload[ConvergenceReapSupport::REAP_DOCUMENT_KEY] = $reapFile->getClientOriginalName();
            $uploads[] = ['file' => $reapFile, 'any_mime' => true];
            $reapDocumentUploaded = true;
        }

        return [$payload, $uploads, $reapDocumentUploaded];
    }

    /**
     * @param  list<UploadedFile|array{file: UploadedFile, any_mime?: bool}>  $uploads
     * @return list<array{file: UploadedFile, any_mime: bool}>
     */
    private function normalizeUploadItems(array $uploads): array
    {
        $out = [];
        foreach ($uploads as $upload) {
            if ($upload instanceof UploadedFile) {
                $out[] = ['file' => $upload, 'any_mime' => false];
            } elseif (is_array($upload) && ($upload['file'] ?? null) instanceof UploadedFile) {
                $out[] = [
                    'file' => $upload['file'],
                    'any_mime' => ! empty($upload['any_mime']),
                ];
            }
        }

        return $out;
    }

    private function assertServiceDocumentUpload(Service $service, UploadedFile $file): void
    {
        $maxKb = 5120;
        if ($file->getSize() > $maxKb * 1024) {
            throw ValidationException::withMessages(['attachments' => 'Each file must be 5 MB or smaller.']);
        }

        $allowedTags = $service->effectiveAllowedDocumentTypes();
        $mime = strtolower((string) $file->getMimeType());
        $ext = strtolower((string) $file->getClientOriginalExtension());

        $okPdf = in_array('pdf', $allowedTags, true)
            && (str_contains($mime, 'pdf') || $ext === 'pdf');
        $okImage = in_array('image', $allowedTags, true)
            && (
                str_starts_with($mime, 'image/')
                || in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)
            );

        if (! $okPdf && ! $okImage) {
            throw ValidationException::withMessages([
                'attachments' => 'Only PDF and image uploads are allowed for this service.',
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $validated
     * @param  list<UploadedFile|array{file: UploadedFile, any_mime?: bool}>  $uploads
     */
    private function updateApprovedCase(
        ServiceCase $case,
        Service $service,
        array $payload,
        array $validated,
        array $uploads,
        int $actorId,
        bool $reapDocumentUploaded = false,
        ?string $existingReapDocument = null,
    ): void {
        $schema = ServiceFieldTypes::normalizeSchema($service->field_schema ?? []);
        $validatedPayload = $schema === []
            ? []
            : app(SchemaValidator::class)->validate($schema, $payload);
        $validatedPayload = ConvergenceReapSupport::mergeThroughReapIntoPayload(
            $service,
            $validatedPayload,
            $payload,
            $reapDocumentUploaded,
            $existingReapDocument,
        );
        $normalizedUploads = $this->normalizeUploadItems($uploads);

        $existingFiles = $case->attachments()->count();
        if ($existingFiles + count($normalizedUploads) > 3) {
            throw ValidationException::withMessages([
                'attachments' => 'Maximum 3 documents per case.',
            ]);
        }

        if ($service->requires_document && $existingFiles + count($normalizedUploads) < 1) {
            throw ValidationException::withMessages([
                'attachments' => 'This service requires at least one document.',
            ]);
        }

        DB::transaction(function () use ($case, $service, $validatedPayload, $validated, $normalizedUploads, $actorId): void {
            foreach ($normalizedUploads as $item) {
                $file = $item['file'];
                if (! $file->isValid()) {
                    throw ValidationException::withMessages(['attachments' => 'Invalid upload.']);
                }
                if (! ($item['any_mime'] ?? false)) {
                    $this->assertServiceDocumentUpload($service, $file);
                } else {
                    ConvergenceReapSupport::assertReapDocumentUpload($file);
                }

                $dir = 'service-case-attachments/'.$case->id;
                $path = $file->store($dir, 'local');
                ServiceCaseAttachment::query()->create([
                    'service_case_id' => $case->id,
                    'disk' => 'local',
                    'path' => $path,
                    'original_name' => $file->getClientOriginalName() ?: 'upload',
                    'mime_type' => $file->getMimeType(),
                    'size_bytes' => (int) $file->getSize(),
                    'uploaded_by' => $actorId,
                ]);
            }

            if ($service->requires_document && $case->attachments()->count() < 1) {
                throw ValidationException::withMessages([
                    'attachments' => 'This service requires at least one document.',
                ]);
            }

            $case->payload = $validatedPayload === [] ? null : $validatedPayload;
            ConvergenceReapSupport::syncThroughReapColumn($case, $validatedPayload);
            $case->reference_number = $validated['reference_number'] ?? $case->reference_number;
            if (! $service->requires_approval && ! empty($validated['delivered_on'])) {
                $case->delivered_on = Carbon::parse((string) $validated['delivered_on'])->startOfDay();
            }
            $case->save();
        });
    }
}
