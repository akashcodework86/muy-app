<?php

namespace App\Http\Controllers\StateStaff;

use App\Http\Controllers\Controller;
use App\Models\District;
use App\Models\DistrictServiceSpoc;
use App\Models\MarketLinkageSubmission;
use App\Models\OnboardingBatch;
use App\Models\Service;
use App\Models\ServiceCase;
use Illuminate\Support\Facades\Schema;
use App\Models\ServiceCaseEvent;
use App\Models\User;
use App\Notifications\ServiceCaseWorkflowNotification;
use App\Services\AppSettingsService;
use App\Services\LegacyApplicationServiceCaseSupport;
use App\Services\SpocServiceCaseReviewTelemetryService;
use App\Support\SpocBulkApproveAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SpocServiceCaseController extends Controller
{
    public function __construct(
        private AppSettingsService $settings,
        private LegacyApplicationServiceCaseSupport $legacyApplications,
        private SpocServiceCaseReviewTelemetryService $reviewTelemetry,
    ) {}

    public function index(Request $request): View
    {
        $this->ensureModuleOn();
        $spoc = $this->spocOrAbort($request);

        $status = (string) $request->query('status', '');
        $districtId = (int) $request->query('district_id', 0);
        $batchId = (int) $request->query('batch_id', 0);
        $serviceId = (int) $request->query('service_id', 0);
        $searchQ = trim((string) $request->query('q', ''));
        $dateFrom = trim((string) $request->query('date_from', ''));
        $dateTo = trim((string) $request->query('date_to', ''));
        $hasDocs = trim((string) $request->query('has_docs', ''));
        if (! in_array($hasDocs, ['', '1', '0'], true)) {
            $hasDocs = '';
        }
        $allowed = ['', ServiceCase::STATUS_PENDING_APPROVAL, ServiceCase::STATUS_SENT_BACK, ServiceCase::STATUS_APPROVED, ServiceCase::STATUS_REJECTED];
        if (! in_array($status, $allowed, true)) {
            $status = '';
        }

        $districtIds = $this->spocDistrictIds((int) $spoc->id);
        if ($districtId > 0 && ! in_array($districtId, $districtIds, true)) {
            $districtId = 0;
        }
        $legacyAppIds = $this->legacyApplicationIdsForSpocDistricts($districtIds);

        $scopeBase = ServiceCase::query()
            ->where(function ($outer) use ($districtIds, $legacyAppIds): void {
                $outer->whereHas('cfaSubmission', fn ($qq) => $qq->whereIn('district_id', $districtIds));
                if (ServiceCase::supportsLegacyApplicationLink() && $legacyAppIds !== []) {
                    $outer->orWhere(function ($qq) use ($legacyAppIds): void {
                        $qq->whereNotNull('legacy_application_id')
                            ->whereNull('cfa_submission_id')
                            ->whereIn('legacy_application_id', $legacyAppIds);
                    });
                }
            });

        if ($districtId > 0) {
            $legacyForOne = $this->legacyApplications->legacyApplicationIdsInLaravelDistrict($districtId);
            $scopeBase->where(function ($qq) use ($districtId, $legacyForOne): void {
                $qq->whereHas('cfaSubmission', fn ($q) => $q->where('district_id', $districtId));
                if (ServiceCase::supportsLegacyApplicationLink() && $legacyForOne !== []) {
                    $qq->orWhere(function ($q) use ($legacyForOne): void {
                        $q->whereNotNull('legacy_application_id')
                            ->whereNull('cfa_submission_id')
                            ->whereIn('legacy_application_id', $legacyForOne);
                    });
                }
            });
        }
        if ($batchId > 0) {
            $scopeBase->whereHas('cfaSubmission.onboardingBatchMembership', fn ($qq) => $qq->where('onboarding_batch_id', $batchId));
        }

        $countScope = clone $scopeBase;

        if ($serviceId > 0) {
            $scopeBase->where('service_id', $serviceId);
        }
        if ($hasDocs === '1') {
            $scopeBase->has('attachments');
        } elseif ($hasDocs === '0') {
            $scopeBase->doesntHave('attachments');
        }
        if ($dateFrom !== '') {
            $scopeBase->whereDate('updated_at', '>=', $dateFrom);
        }
        if ($dateTo !== '') {
            $scopeBase->whereDate('updated_at', '<=', $dateTo);
        }

        $tabCounts = [
            '' => (clone $countScope)->whereIn('status', [
                ServiceCase::STATUS_PENDING_APPROVAL,
                ServiceCase::STATUS_SENT_BACK,
                ServiceCase::STATUS_APPROVED,
                ServiceCase::STATUS_REJECTED,
            ])->count(),
            ServiceCase::STATUS_PENDING_APPROVAL => (clone $countScope)->where('status', ServiceCase::STATUS_PENDING_APPROVAL)->count(),
            ServiceCase::STATUS_SENT_BACK => (clone $countScope)->where('status', ServiceCase::STATUS_SENT_BACK)->count(),
            ServiceCase::STATUS_APPROVED => (clone $countScope)->where('status', ServiceCase::STATUS_APPROVED)->count(),
            ServiceCase::STATUS_REJECTED => (clone $countScope)->where('status', ServiceCase::STATUS_REJECTED)->count(),
        ];

        $q = (clone $scopeBase)
            ->with([
                'cfaSubmission:id,applicant_name,application_no,district_id',
                'cfaSubmission.district:id,name',
                'cfaSubmission.onboardingBatchMembership:id,onboarding_batch_id,cfa_submission_id',
                'cfaSubmission.onboardingBatchMembership.batch:id,name,district_id',
                'service.category.parent',
                'submitter:id,name',
                'attachments:id,service_case_id,original_name,size_bytes,disk,path',
            ]);

        $this->applySpocSearchFilter($q, $searchQ);

        if ($status !== '') {
            $q->where('status', $status);
        } else {
            $q->whereIn('status', [
                ServiceCase::STATUS_PENDING_APPROVAL,
                ServiceCase::STATUS_SENT_BACK,
                ServiceCase::STATUS_APPROVED,
                ServiceCase::STATUS_REJECTED,
            ]);
        }

        $casesCollection = $q->orderByDesc('updated_at')->get();
        $legacyIds = $casesCollection
            ->filter(fn (ServiceCase $case): bool => (int) ($case->legacy_application_id ?? 0) > 0 && ! $case->cfa_submission_id)
            ->map(fn (ServiceCase $case): int => (int) $case->legacy_application_id)
            ->unique()
            ->values()
            ->all();
        $legacyPreviewMap = $this->legacyApplications->incubateePreviewMap($legacyIds);
        $casesCollection->transform(function (ServiceCase $case) use ($legacyPreviewMap): ServiceCase {
            if ($case->legacy_application_id && ! $case->cfa_submission_id) {
                $case->legacyIncubateePreview = $legacyPreviewMap[(int) $case->legacy_application_id] ?? null;
            }

            return $case;
        });

        $districtOptions = District::query()
            ->whereIn('id', $districtIds)
            ->orderBy('name')
            ->get(['id', 'name']);

        $batchOptions = OnboardingBatch::query()
            ->whereIn('district_id', $districtIds)
            ->whereHas('batchCfas.cfaSubmission', function ($qq) use ($districtIds): void {
                $qq->whereIn('district_id', $districtIds);
            })
            ->orderByDesc('id')
            ->get(['id', 'name', 'district_id']);

        if ($batchId > 0 && ! $batchOptions->contains(fn (OnboardingBatch $batch): bool => (int) $batch->id === $batchId)) {
            $batchId = 0;
        }

        $marketLinkages = collect();
        $marketLinkageWorkflowReady = Schema::hasTable('market_linkage_submissions')
            && Schema::hasColumn('market_linkage_submissions', 'status');

        if ($marketLinkageWorkflowReady && $districtIds !== []) {
            $mlBase = MarketLinkageSubmission::query()->whereIn('district_id', $districtIds);
            if ($districtId > 0) {
                $mlBase->where('district_id', $districtId);
            }

            foreach ([
                '' => [
                    ServiceCase::STATUS_PENDING_APPROVAL,
                    ServiceCase::STATUS_SENT_BACK,
                    ServiceCase::STATUS_APPROVED,
                    ServiceCase::STATUS_REJECTED,
                ],
                ServiceCase::STATUS_PENDING_APPROVAL => [ServiceCase::STATUS_PENDING_APPROVAL],
                ServiceCase::STATUS_SENT_BACK => [ServiceCase::STATUS_SENT_BACK],
                ServiceCase::STATUS_APPROVED => [ServiceCase::STATUS_APPROVED],
                ServiceCase::STATUS_REJECTED => [ServiceCase::STATUS_REJECTED],
            ] as $tabKey => $statuses) {
                $tabCounts[$tabKey] = (int) ($tabCounts[$tabKey] ?? 0)
                    + (clone $mlBase)->whereIn('status', $statuses)->count();
            }

            $mlq = (clone $mlBase)
                ->with(['submitter:id,name', 'district:id,name', 'partners']);
            $this->applySpocMarketLinkageSearchFilter($mlq, $searchQ);
            if ($dateFrom !== '') {
                $mlq->whereDate('updated_at', '>=', $dateFrom);
            }
            if ($dateTo !== '') {
                $mlq->whereDate('updated_at', '<=', $dateTo);
            }
            if ($status !== '') {
                $mlq->where('status', $status);
            } else {
                $mlq->whereIn('status', [
                    ServiceCase::STATUS_PENDING_APPROVAL,
                    ServiceCase::STATUS_SENT_BACK,
                    ServiceCase::STATUS_APPROVED,
                    ServiceCase::STATUS_REJECTED,
                ]);
            }
            $marketLinkages = $mlq->orderByDesc('updated_at')->get();
        }

        $cases = $this->buildMergedSpocQueue($casesCollection, $marketLinkages, $request);

        $serviceScope = ServiceCase::query()
            ->where(function ($outer) use ($districtIds, $legacyAppIds): void {
                $outer->whereHas('cfaSubmission', fn ($qq) => $qq->whereIn('district_id', $districtIds));
                if (ServiceCase::supportsLegacyApplicationLink() && $legacyAppIds !== []) {
                    $outer->orWhere(function ($qq) use ($legacyAppIds): void {
                        $qq->whereNotNull('legacy_application_id')
                            ->whereNull('cfa_submission_id')
                            ->whereIn('legacy_application_id', $legacyAppIds);
                    });
                }
            });
        if ($districtId > 0) {
            $legacyForOne = $this->legacyApplications->legacyApplicationIdsInLaravelDistrict($districtId);
            $serviceScope->where(function ($qq) use ($districtId, $legacyForOne): void {
                $qq->whereHas('cfaSubmission', fn ($q) => $q->where('district_id', $districtId));
                if (ServiceCase::supportsLegacyApplicationLink() && $legacyForOne !== []) {
                    $qq->orWhere(function ($q) use ($legacyForOne): void {
                        $q->whereNotNull('legacy_application_id')
                            ->whereNull('cfa_submission_id')
                            ->whereIn('legacy_application_id', $legacyForOne);
                    });
                }
            });
        }
        $serviceIds = $serviceScope->distinct()->pluck('service_id')->map(fn ($id) => (int) $id)->filter(fn (int $id) => $id > 0)->values()->all();
        $serviceOptions = $serviceIds === []
            ? collect()
            : Service::query()->whereIn('id', $serviceIds)->orderBy('name')->get(['id', 'name']);

        return view('spoc.service-cases.index', [
            'cases' => $cases,
            'marketLinkages' => collect(),
            'marketLinkageWorkflowReady' => $marketLinkageWorkflowReady,
            'spocDistrictIds' => $districtIds,
            'filterStatus' => $status,
            'filterDistrictId' => $districtId,
            'filterBatchId' => $batchId,
            'filterServiceId' => $serviceId,
            'filterQ' => $searchQ,
            'filterDateFrom' => $dateFrom,
            'filterDateTo' => $dateTo,
            'filterHasDocs' => $hasDocs,
            'districtOptions' => $districtOptions,
            'batchOptions' => $batchOptions,
            'serviceOptions' => $serviceOptions,
            'tabCounts' => $tabCounts,
            'canBulkApprove' => SpocBulkApproveAccess::canBulkApprove($spoc),
        ]);
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<ServiceCase>  $query
     */
    private function applySpocSearchFilter($query, string $searchQ): void
    {
        if ($searchQ === '') {
            return;
        }

        $like = '%'.$searchQ.'%';
        $legacyIds = $this->legacyApplicationIdsMatchingSearch($searchQ);

        $query->where(function ($w) use ($like, $legacyIds, $searchQ): void {
            $w->where('reference_number', 'like', $like)
                ->orWhere('sent_back_note', 'like', $like)
                ->orWhere('rejected_note', 'like', $like)
                ->orWhereHas('cfaSubmission', fn ($s) => $s
                    ->where('application_no', 'like', $like)
                    ->orWhere('applicant_name', 'like', $like))
                ->orWhereHas('service', fn ($s) => $s->where('name', 'like', $like))
                ->orWhereHas('submitter', fn ($s) => $s->where('name', 'like', $like))
                ->orWhereHas('cfaSubmission.district', fn ($s) => $s->where('name', 'like', $like))
                ->orWhereHas('cfaSubmission.onboardingBatchMembership.batch', fn ($s) => $s->where('name', 'like', $like));

            $searchLower = strtolower($searchQ);
            if (str_contains($searchLower, 'pending')) {
                $w->orWhere('status', ServiceCase::STATUS_PENDING_APPROVAL);
            }
            if (str_contains($searchLower, 'sent') || str_contains($searchLower, 'back')) {
                $w->orWhere('status', ServiceCase::STATUS_SENT_BACK);
            }
            if (str_contains($searchLower, 'approv')) {
                $w->orWhere('status', ServiceCase::STATUS_APPROVED);
            }
            if (str_contains($searchLower, 'reject')) {
                $w->orWhere('status', ServiceCase::STATUS_REJECTED);
            }

            if (ServiceCase::supportsLegacyApplicationLink() && $legacyIds !== []) {
                $w->orWhere(function ($qq) use ($legacyIds): void {
                    $qq->whereNotNull('legacy_application_id')
                        ->whereNull('cfa_submission_id')
                        ->whereIn('legacy_application_id', $legacyIds);
                });
            }
        });
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<MarketLinkageSubmission>  $query
     */
    private function applySpocMarketLinkageSearchFilter($query, string $searchQ): void
    {
        if ($searchQ === '') {
            return;
        }

        $like = '%'.$searchQ.'%';
        $searchLower = strtolower($searchQ);

        $query->where(function ($w) use ($like, $searchLower): void {
            $w->where('incubatee_name', 'like', $like)
                ->orWhere('application_no', 'like', $like)
                ->orWhere('sent_back_note', 'like', $like)
                ->orWhere('rejected_note', 'like', $like)
                ->orWhereHas('district', fn ($s) => $s->where('name', 'like', $like))
                ->orWhereHas('submitter', fn ($s) => $s->where('name', 'like', $like));

            if (str_contains($searchLower, 'market') || str_contains($searchLower, 'linkage')) {
                $w->orWhereRaw('1 = 1');
            }
        });
    }

    /**
     * @return list<int>
     */
    private function legacyApplicationIdsMatchingSearch(string $search): array
    {
        if (! $this->legacyApplications->legacyDbAvailable() || mb_strlen($search) < 2) {
            return [];
        }

        $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search).'%';

        try {
            return DB::connection('legacy')
                ->table('rbi_applicant_details as d')
                ->join('rbi_applications as a', 'a.id', '=', 'd.application_id')
                ->where(function ($q) use ($like): void {
                    $q->where('d.applicant_name', 'like', $like)
                        ->orWhere('a.application_no', 'like', $like)
                        ->orWhere('d.phone', 'like', $like)
                        ->orWhere('d.district', 'like', $like);
                })
                ->pluck('d.application_id')
                ->map(fn ($id) => (int) $id)
                ->filter(fn (int $id): bool => $id > 0)
                ->unique()
                ->values()
                ->all();
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @param  Collection<int, ServiceCase>  $cases
     * @param  Collection<int, MarketLinkageSubmission>  $marketLinkages
     * @return LengthAwarePaginator<int, array{kind: string, service_case: ?ServiceCase, market_linkage: ?MarketLinkageSubmission, updated_at: ?\Illuminate\Support\Carbon}>
     */
    private function buildMergedSpocQueue(Collection $cases, Collection $marketLinkages, Request $request): LengthAwarePaginator
    {
        $items = collect();

        foreach ($marketLinkages as $ml) {
            $items->push([
                'kind' => 'market_linkage',
                'service_case' => null,
                'market_linkage' => $ml,
                'updated_at' => $ml->updated_at,
            ]);
        }

        foreach ($cases as $case) {
            $items->push([
                'kind' => 'service_case',
                'service_case' => $case,
                'market_linkage' => null,
                'updated_at' => $case->updated_at,
            ]);
        }

        $statusPriority = [
            ServiceCase::STATUS_PENDING_APPROVAL => 0,
            ServiceCase::STATUS_SENT_BACK => 1,
            ServiceCase::STATUS_APPROVED => 2,
            ServiceCase::STATUS_REJECTED => 3,
        ];

        $sorted = $items->sort(function (array $a, array $b) use ($statusPriority): int {
            $statusA = $a['kind'] === 'market_linkage'
                ? (string) ($a['market_linkage']?->status ?? '')
                : (string) ($a['service_case']?->status ?? '');
            $statusB = $b['kind'] === 'market_linkage'
                ? (string) ($b['market_linkage']?->status ?? '')
                : (string) ($b['service_case']?->status ?? '');
            $priorityA = $statusPriority[$statusA] ?? 9;
            $priorityB = $statusPriority[$statusB] ?? 9;
            if ($priorityA !== $priorityB) {
                return $priorityA <=> $priorityB;
            }

            return ($b['updated_at']?->timestamp ?? 0) <=> ($a['updated_at']?->timestamp ?? 0);
        })->values();

        $page = max(1, (int) $request->query('page', 1));
        $perPage = 20;
        $total = $sorted->count();

        return new LengthAwarePaginator(
            $sorted->forPage($page, $perPage)->values(),
            $total,
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );
    }

    public function show(Request $request, ServiceCase $service_case): View
    {
        $this->ensureModuleOn();
        $spoc = $this->spocOrAbort($request);
        $this->assertCaseInSpocDistrict($service_case, (int) $spoc->id);

        $service_case->load([
            'cfaSubmission.district',
            'service.category.parent',
            'attachments.uploader',
            'events.user',
            'submitter',
            'approver',
            'rejector',
        ]);

        $legacyIncubateePreview = null;
        if ($service_case->legacy_application_id && ! $service_case->cfa_submission_id) {
            $legacyIncubateePreview = $this->legacyApplications->incubateePreview((int) $service_case->legacy_application_id);
        }

        return view('spoc.service-cases.show', [
            'case' => $service_case,
            'legacyIncubateePreview' => $legacyIncubateePreview,
        ]);
    }

    public function approve(Request $request, ServiceCase $service_case): RedirectResponse
    {
        $this->ensureModuleOn();
        $spoc = $this->spocOrAbort($request);
        $this->assertCaseInSpocDistrict($service_case, (int) $spoc->id);

        if ($service_case->status !== ServiceCase::STATUS_PENDING_APPROVAL) {
            throw ValidationException::withMessages(['status' => 'Only pending approval cases can be approved.']);
        }

        $approvalMeta = $this->reviewTelemetry->snapshotForApproval(
            $service_case,
            (int) $spoc->id,
            $this->resolveApprovalChannel($request),
            (int) $request->input('client_review_seconds', 0),
        );

        $this->approvePendingCase($service_case, $spoc, $approvalMeta);
        $this->reviewTelemetry->clear((int) $spoc->id, (int) $service_case->id);

        return $this->redirectToQueue($request)
            ->with('status', 'Case approved.');
    }

    public function recordReviewTelemetry(Request $request, ServiceCase $service_case): JsonResponse
    {
        $this->ensureModuleOn();
        $spoc = $this->spocOrAbort($request);
        $this->assertCaseInSpocDistrict($service_case, (int) $spoc->id);

        $validated = $request->validate([
            'event' => ['required', 'string', 'in:document_viewed,full_page_visited,quick_review_opened,heartbeat'],
            'source' => ['nullable', 'string', 'max:64'],
            'seconds' => ['nullable', 'integer', 'min:0', 'max:7200'],
        ]);

        $spocId = (int) $spoc->id;
        $caseId = (int) $service_case->id;

        match ($validated['event']) {
            'document_viewed' => $this->reviewTelemetry->markDocumentViewed(
                $spocId,
                $caseId,
                (string) ($validated['source'] ?? 'ui')
            ),
            'full_page_visited' => $this->reviewTelemetry->markFullPageVisited($spocId, $caseId),
            'quick_review_opened' => $this->reviewTelemetry->markQuickReviewOpened($spocId, $caseId),
            'heartbeat' => $this->reviewTelemetry->addReviewSeconds(
                $spocId,
                $caseId,
                (int) ($validated['seconds'] ?? 0)
            ),
        };

        return response()->json(['ok' => true]);
    }

    public function bulkApprove(Request $request): RedirectResponse
    {
        $this->ensureModuleOn();
        $spoc = $this->spocOrAbort($request);
        abort_unless(SpocBulkApproveAccess::canBulkApprove($spoc), 403);

        $validated = $request->validate([
            'case_ids' => ['required', 'array', 'min:1', 'max:100'],
            'case_ids.*' => ['integer', 'min:1'],
        ]);

        $caseIds = collect($validated['case_ids'])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $cases = ServiceCase::query()
            ->whereIn('id', $caseIds)
            ->get()
            ->keyBy('id');

        $approved = 0;
        $skipped = 0;

        foreach ($caseIds as $caseId) {
            $case = $cases->get($caseId);
            if (! $case instanceof ServiceCase) {
                $skipped++;

                continue;
            }

            try {
                $this->assertCaseInSpocDistrict($case, (int) $spoc->id);
            } catch (\Symfony\Component\HttpKernel\Exception\HttpException) {
                $skipped++;

                continue;
            }

            if ($case->status !== ServiceCase::STATUS_PENDING_APPROVAL) {
                $skipped++;

                continue;
            }

            $this->approvePendingCase(
                $case,
                $spoc,
                $this->reviewTelemetry->snapshotForApproval($case, (int) $spoc->id, 'bulk')
            );
            $this->reviewTelemetry->clear((int) $spoc->id, (int) $case->id);
            $approved++;
        }

        if ($approved === 0) {
            throw ValidationException::withMessages([
                'case_ids' => 'No pending cases could be approved from your selection.',
            ]);
        }

        $message = $approved === 1
            ? '1 case approved.'
            : $approved.' cases approved.';

        if ($skipped > 0) {
            $message .= ' '.$skipped.' skipped (not pending or not in your districts).';
        }

        return $this->redirectToQueue($request)->with('status', $message);
    }

    public function sendBack(Request $request, ServiceCase $service_case): RedirectResponse
    {
        $this->ensureModuleOn();
        $spoc = $this->spocOrAbort($request);
        $this->assertCaseInSpocDistrict($service_case, (int) $spoc->id);

        if ($service_case->status !== ServiceCase::STATUS_PENDING_APPROVAL) {
            throw ValidationException::withMessages(['status' => 'Only pending approval cases can be sent back.']);
        }

        $validated = $request->validate([
            'note' => ['required', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($service_case, $spoc, $validated): void {
            $service_case->status = ServiceCase::STATUS_SENT_BACK;
            $service_case->sent_back_note = trim((string) $validated['note']);
            $service_case->approved_at = null;
            $service_case->approved_by = null;
            $service_case->rejected_at = null;
            $service_case->rejected_by = null;
            $service_case->rejected_note = null;
            $service_case->save();

            ServiceCaseEvent::query()->create([
                'service_case_id' => $service_case->id,
                'user_id' => (int) $spoc->id,
                'action' => 'spoc_sent_back',
                'meta' => ['note' => $service_case->sent_back_note],
            ]);
        });
        $this->notifyDistrictStaff($service_case, $spoc, 'sent_back');

        return $this->redirectToQueue($request)
            ->with('status', 'Case sent back to district staff.');
    }

    public function reject(Request $request, ServiceCase $service_case): RedirectResponse
    {
        $this->ensureModuleOn();
        $spoc = $this->spocOrAbort($request);
        $this->assertCaseInSpocDistrict($service_case, (int) $spoc->id);

        if ($service_case->status !== ServiceCase::STATUS_PENDING_APPROVAL) {
            throw ValidationException::withMessages(['status' => 'Only pending approval cases can be rejected.']);
        }

        $validated = $request->validate([
            'note' => ['required', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($service_case, $spoc, $validated): void {
            $service_case->status = ServiceCase::STATUS_REJECTED;
            $service_case->rejected_at = now();
            $service_case->rejected_by = (int) $spoc->id;
            $service_case->rejected_note = trim((string) $validated['note']);
            $service_case->approved_at = null;
            $service_case->approved_by = null;
            $service_case->sent_back_note = null;
            $service_case->save();

            ServiceCaseEvent::query()->create([
                'service_case_id' => $service_case->id,
                'user_id' => (int) $spoc->id,
                'action' => 'spoc_rejected',
                'meta' => ['note' => $service_case->rejected_note],
            ]);
        });
        $this->notifyDistrictStaff($service_case, $spoc, 'rejected');

        return $this->redirectToQueue($request)
            ->with('status', 'Case rejected.');
    }

    public function downloadAttachment(Request $request, ServiceCase $service_case, int $attachment)
    {
        $this->ensureModuleOn();
        $spoc = $this->spocOrAbort($request);
        $this->assertCaseInSpocDistrict($service_case, (int) $spoc->id);
        $this->reviewTelemetry->markDocumentViewed((int) $spoc->id, (int) $service_case->id, 'download');
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

    private function spocOrAbort(Request $request): User
    {
        $u = $request->user();
        abort_unless($u && $u->role === 'state_staff', 403);

        return $u;
    }

    /**
     * @return list<int>
     */
    private function spocDistrictIds(int $spocUserId): array
    {
        return DistrictServiceSpoc::query()
            ->where('state_staff_user_id', $spocUserId)
            ->pluck('district_id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->values()
            ->all();
    }

    private function assertCaseInSpocDistrict(ServiceCase $case, int $spocUserId): void
    {
        $districtId = $this->resolveCaseDistrictId($case);
        abort_unless($districtId > 0, 404);

        $allowed = DistrictServiceSpoc::query()
            ->where('state_staff_user_id', $spocUserId)
            ->where('district_id', $districtId)
            ->exists();

        abort_unless($allowed, 403);
    }

    private function resolveCaseDistrictId(ServiceCase $case): int
    {
        $case->loadMissing('cfaSubmission');
        if ($case->cfa_submission_id && $case->cfaSubmission) {
            return (int) $case->cfaSubmission->district_id;
        }

        if ($case->legacy_application_id) {
            $resolved = $this->legacyApplications->laravelDistrictIdForLegacyApplication((int) $case->legacy_application_id);

            return (int) ($resolved ?? 0);
        }

        return 0;
    }

    /**
     * @param  list<int>  $districtIds
     * @return list<int>
     */
    private function legacyApplicationIdsForSpocDistricts(array $districtIds): array
    {
        $out = [];
        foreach ($districtIds as $did) {
            foreach ($this->legacyApplications->legacyApplicationIdsInLaravelDistrict((int) $did) as $lid) {
                $out[] = $lid;
            }
        }

        return array_values(array_unique($out));
    }

    private function approvePendingCase(ServiceCase $serviceCase, User $spoc, array $approvalMeta = []): void
    {
        if ($serviceCase->status !== ServiceCase::STATUS_PENDING_APPROVAL) {
            throw ValidationException::withMessages(['status' => 'Only pending approval cases can be approved.']);
        }

        DB::transaction(function () use ($serviceCase, $spoc, $approvalMeta): void {
            $serviceCase->status = ServiceCase::STATUS_APPROVED;
            $serviceCase->approved_at = now();
            $serviceCase->approved_by = (int) $spoc->id;
            $serviceCase->completed_at = now();
            $serviceCase->sent_back_note = null;
            $serviceCase->rejected_at = null;
            $serviceCase->rejected_by = null;
            $serviceCase->rejected_note = null;
            $serviceCase->save();

            ServiceCaseEvent::query()->create([
                'service_case_id' => $serviceCase->id,
                'user_id' => (int) $spoc->id,
                'action' => 'spoc_approved',
                'meta' => $approvalMeta !== [] ? $approvalMeta : null,
            ]);
        });

        $this->notifyDistrictStaff($serviceCase, $spoc, 'approved');
    }

    private function resolveApprovalChannel(Request $request): string
    {
        $channel = trim((string) $request->input('approval_channel', ''));
        if (in_array($channel, ['queue_quick_review', 'full_page', 'bulk'], true)) {
            return $channel;
        }

        $redirect = trim((string) $request->input('redirect_to', ''));
        if ($redirect !== '' && str_contains($redirect, '/spoc/service-cases') && ! str_contains($redirect, '/service-cases/')) {
            return 'queue_quick_review';
        }

        return 'full_page';
    }

    private function notifyDistrictStaff(ServiceCase $serviceCase, User $spoc, string $action): void
    {
        $serviceCase->loadMissing(['service:id,name', 'cfaSubmission:id,application_no,applicant_name']);
        $recipientIds = collect([
            (int) ($serviceCase->submitted_by ?? 0),
            (int) ($serviceCase->created_by ?? 0),
        ])->filter(fn (int $id) => $id > 0)->unique()->values();

        if ($recipientIds->isEmpty()) {
            return;
        }

        $recipients = User::query()
            ->whereIn('id', $recipientIds->all())
            ->where('is_active', true)
            ->get();

        if ($recipients->isEmpty()) {
            return;
        }

        $statusLabel = str_replace('_', ' ', (string) $serviceCase->status);
        $serviceName = (string) ($serviceCase->service?->name ?? 'Service case');
        $appNo = (string) ($serviceCase->cfaSubmission?->application_no ?? '');
        $applicant = (string) ($serviceCase->cfaSubmission?->applicant_name ?? '');
        $note = match ($action) {
            'sent_back' => (string) ($serviceCase->sent_back_note ?? ''),
            'rejected' => (string) ($serviceCase->rejected_note ?? ''),
            default => '',
        };
        $title = match ($action) {
            'approved' => 'Service approved',
            'sent_back' => 'Service sent back',
            'rejected' => 'Service rejected',
            default => 'Service update',
        };

        $body = trim($serviceName.' is '.$statusLabel.' by '.$spoc->name.'.');
        if ($appNo !== '' || $applicant !== '') {
            $body .= ' CFA '.trim($appNo.' '.$applicant).'.';
        }

        Notification::send($recipients, new ServiceCaseWorkflowNotification([
            'title' => $title,
            'body' => $body,
            'service_case_id' => (int) $serviceCase->id,
            'cfa_submission_id' => (int) ($serviceCase->cfa_submission_id ?? 0),
            'status' => (string) $serviceCase->status,
            'spoc_name' => (string) $spoc->name,
            'service_name' => $serviceName,
            'application_no' => $appNo,
            'incubatee_name' => $applicant,
            'comment' => $note !== '' ? $note : null,
            'action' => $action,
        ]));
    }

    private function redirectToQueue(Request $request): RedirectResponse
    {
        $target = trim((string) $request->input('redirect_to', ''));
        if ($target !== '' && $this->isSafeQueueUrl($target)) {
            return redirect()->to($target);
        }

        return redirect()->route('spoc.service-cases.index');
    }

    private function isSafeQueueUrl(string $url): bool
    {
        if ($url === '') {
            return false;
        }

        // Allow relative queue URLs from forms posted within this app.
        if (str_starts_with($url, '/')) {
            return str_contains($url, '/spoc/service-cases');
        }

        $target = parse_url($url);
        $current = parse_url(url('/'));
        if (! is_array($target) || ! is_array($current)) {
            return false;
        }

        $targetHost = strtolower((string) ($target['host'] ?? ''));
        $currentHost = strtolower((string) ($current['host'] ?? ''));
        if ($targetHost === '' || $currentHost === '' || $targetHost !== $currentHost) {
            return false;
        }

        $targetPath = (string) ($target['path'] ?? '');

        return str_contains($targetPath, '/spoc/service-cases');
    }

}
