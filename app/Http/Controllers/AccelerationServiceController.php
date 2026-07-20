<?php

namespace App\Http\Controllers;

use App\Models\AccelerationServiceItemMedia;
use App\Models\AccelerationServiceSession;
use App\Models\FiscalYear;
use App\Models\User;
use App\Services\AccelerationServiceRecorder;
use App\Services\AccelerationServicesIncubateeService;
use App\Support\AccelerationServicesAccess;
use App\Support\AccelerationServicesApproval;
use App\Support\AccelerationServicesDeliverablesSupport;
use App\Support\AccelerationServicesOptions;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AccelerationServiceController extends Controller
{
    public function __construct(
        private readonly AccelerationServiceRecorder $recorder,
        private readonly AccelerationServicesIncubateeService $incubatees,
    ) {}

    public function create(Request $request): View
    {
        $user = $this->submitterOrAbort($request);

        $migrationMissing = ! Schema::hasTable('acceleration_service_sessions');

        $prefillApplicant = null;
        $editingSession = null;
        $prefillPayloads = [];
        $existingMedia = [];
        $prefillFromSessionId = (int) $request->query('from_session', 0);
        if ($prefillFromSessionId > 0 && ! $migrationMissing) {
            $fromSession = AccelerationServiceSession::query()->find($prefillFromSessionId);
            if ($fromSession) {
                $prefillApplicant = $this->incubatees->findPhase1Applicant((int) $fromSession->legacy_phase1_application_id);
                if ($prefillApplicant !== null
                    && AccelerationServicesAccess::applicantEligibilityError($user, $prefillApplicant) !== null) {
                    $prefillApplicant = null;
                }
            }
        }

        return view('acceleration-services.create', $this->formViewData(
            $user,
            $migrationMissing,
            $prefillApplicant,
            $editingSession,
            $prefillPayloads,
            $existingMedia,
        ));
    }

    public function edit(Request $request, AccelerationServiceSession $accelerationSession): View|RedirectResponse
    {
        $user = $this->submitterOrAbort($request);
        $this->assertCanModifySession($user, $accelerationSession);

        if ($accelerationSession->isLocked()) {
            $prefix = AccelerationServicesAccess::routePrefixForUser($user);

            return redirect()
                ->route($prefix.'acceleration-services.show', $accelerationSession)
                ->with('status', 'This entry is approved and locked. Use “Add more services” to log a new entry.');
        }

        $accelerationSession->load(['items.media']);
        $prefillApplicant = $this->incubatees->findPhase1Applicant((int) $accelerationSession->legacy_phase1_application_id)
            ?: [
                'legacy_phase1_application_id' => (int) $accelerationSession->legacy_phase1_application_id,
                'incubatee_key' => (string) $accelerationSession->incubatee_key,
                'applicant_name' => (string) $accelerationSession->applicant_name,
                'application_no' => (string) ($accelerationSession->application_no ?? ''),
                'phone' => (string) ($accelerationSession->phone ?? ''),
                'district_name' => (string) ($accelerationSession->district_name ?? ''),
                'onboard_label' => (string) ($accelerationSession->onboard_label ?? ''),
            ];

        $prefillPayloads = [];
        $existingMedia = [];
        $checked = [
            'service_detail' => [],
            'cross_cutting' => [],
            'partnership' => [],
        ];
        foreach ($accelerationSession->items as $item) {
            $section = (string) $item->section;
            if (isset($checked[$section])) {
                $checked[$section][] = (string) $item->item_key;
            }
            $prefillPayloads[(string) $item->item_key] = is_array($item->payload) ? $item->payload : [];
            $existingMedia[(string) $item->item_key] = $item->media->map(fn ($m) => [
                'id' => $m->id,
                'name' => $m->original_name,
            ])->all();
        }

        return view('acceleration-services.create', array_merge(
            $this->formViewData(
                $user,
                ! Schema::hasTable('acceleration_service_sessions'),
                $prefillApplicant,
                $accelerationSession,
                $prefillPayloads,
                $existingMedia,
            ),
            ['checkedKeys' => $checked],
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $this->submitterOrAbort($request);

        $existingId = (int) $request->input('session_id', 0);
        $existing = null;
        if ($existingId > 0) {
            $existing = AccelerationServiceSession::query()->findOrFail($existingId);
            $this->assertCanModifySession($user, $existing);
            $result = $this->recorder->update($request, $user, $existing);
        } else {
            $result = $this->recorder->store($request, $user);
        }

        $session = $result['session'];
        $count = (int) $result['item_count'];

        $message = $count.' service(s) logged.';
        if (AccelerationServicesApproval::workflowReady()) {
            $message .= ' Entry sent for approval — it counts towards 7.2 once fully approved.';
        } elseif ($session->counts_for_7_2) {
            $message = $count.' service(s) logged. Counted as new 7.2 initiation for this FY.';
        }

        $prefix = AccelerationServicesAccess::routePrefixForUser($user);

        return redirect()
            ->route($prefix.'acceleration-services.dashboard')
            ->with('status', $message);
    }

    public function update(Request $request, AccelerationServiceSession $accelerationSession): RedirectResponse
    {
        $user = $this->submitterOrAbort($request);
        $this->assertCanModifySession($user, $accelerationSession);

        $result = $this->recorder->update($request, $user, $accelerationSession);
        $count = (int) $result['item_count'];
        $prefix = AccelerationServicesAccess::routePrefixForUser($user);

        $message = $count.' service(s) updated.';
        if (AccelerationServicesApproval::workflowReady()) {
            $message .= ' Entry sent for approval again.';
        }

        return redirect()
            ->route($prefix.'acceleration-services.dashboard')
            ->with('status', $message);
    }

    public function autosave(Request $request): JsonResponse
    {
        $user = $this->submitterOrAbort($request);

        $existingId = (int) $request->input('session_id', 0);
        $existing = null;
        if ($existingId > 0) {
            $existing = AccelerationServiceSession::query()->find($existingId);
            if ($existing) {
                $this->assertCanModifySession($user, $existing);
            }
        }

        try {
            $result = $this->recorder->autosave($request, $user, $existing);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'ok' => false,
                'errors' => $e->errors(),
            ], 422);
        }

        $session = $result['session']->load(['items.media']);
        $existingMedia = [];
        foreach ($session->items as $item) {
            $existingMedia[(string) $item->item_key] = $item->media->map(fn ($m) => [
                'id' => (int) $m->id,
                'name' => (string) $m->original_name,
            ])->values()->all();
        }

        $prefix = AccelerationServicesAccess::routePrefixForUser($user);

        return response()->json([
            'ok' => true,
            'session_id' => (int) $session->id,
            'item_count' => (int) $result['item_count'],
            'is_draft' => (bool) ($session->is_draft ?? true),
            'existing_media' => $existingMedia,
            'edit_url' => route($prefix.'acceleration-services.edit', $session),
            'update_url' => route($prefix.'acceleration-services.update', $session),
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $prefillApplicant
     * @param  array<string, array<string, mixed>>  $prefillPayloads
     * @param  array<string, list<array{id:int,name:string}>>  $existingMedia
     * @return array<string, mixed>
     */
    private function formViewData(
        User $user,
        bool $migrationMissing,
        ?array $prefillApplicant,
        ?AccelerationServiceSession $editingSession,
        array $prefillPayloads,
        array $existingMedia,
    ): array {
        $prefix = AccelerationServicesAccess::routePrefixForUser($user);
        $allowedSections = AccelerationServicesAccess::allowedSections($user);
        $catalog = AccelerationServicesOptions::allSections();
        $catalog = array_filter(
            $catalog,
            static fn ($_, string $section) => in_array($section, $allowedSections, true),
            ARRAY_FILTER_USE_BOTH,
        );

        return [
            'migrationMissing' => $migrationMissing,
            'canSubmit' => true,
            'catalog' => $catalog,
            'allowedSections' => $allowedSections,
            'inHouseOnly' => AccelerationServicesAccess::isInHouseOnlySubmitter($user),
            'legacyPhase1Available' => (string) config('database.connections.legacy_phase1.database', '') !== '',
            'prefillApplicant' => $prefillApplicant,
            'editingSession' => $editingSession,
            'prefillPayloads' => $prefillPayloads,
            'existingMedia' => $existingMedia,
            'checkedKeys' => [
                'service_detail' => [],
                'cross_cutting' => [],
                'partnership' => [],
            ],
            'storeRoute' => $prefix.'acceleration-services.store',
            'updateRoute' => $editingSession ? $prefix.'acceleration-services.update' : null,
            'autosaveRoute' => $prefix.'acceleration-services.autosave',
            'dashboardRoute' => $prefix.'acceleration-services.dashboard',
            'searchRoute' => $prefix.'acceleration-services.incubatees.search',
            'historyRoute' => $prefix.'acceleration-services.incubatees.history',
            'mediaRoute' => $prefix.'acceleration-services.media',
            'showRoute' => $prefix.'acceleration-services.show',
        ];
    }

    public function show(Request $request, AccelerationServiceSession $accelerationSession): View
    {
        $user = $request->user();
        abort_unless(AccelerationServicesAccess::canViewDashboard($user), 403);
        $this->assertCanViewSession($user, $accelerationSession);

        $accelerationSession->load(['items.media', 'submitter:id,name']);

        $events = collect();
        if (Schema::hasTable('acceleration_service_session_events')) {
            $events = $accelerationSession->events()->orderBy('id')->get();
        }

        $isAdmin = $user->role === 'state_admin';
        $prefix = AccelerationServicesAccess::routePrefixForUser($user);
        $canSubmit = AccelerationServicesAccess::canSubmit($user);
        $isMaker = (int) $accelerationSession->submitted_by_user_id === (int) $user->id;
        $canEdit = $canSubmit && $isMaker && ! $accelerationSession->isLocked();
        $canApprove = AccelerationServicesApproval::canApprove($user, $accelerationSession);

        return view('acceleration-services.show', [
            'session' => $accelerationSession,
            'events' => $events,
            'workflowReady' => AccelerationServicesApproval::workflowReady(),
            'isAdminView' => $isAdmin,
            'canSubmit' => $canSubmit,
            'canDelete' => AccelerationServicesAccess::canDelete($user, $accelerationSession),
            'canApprove' => $canApprove,
            'dashboardRoute' => $prefix.'acceleration-services.dashboard',
            'destroyRoute' => $isAdmin ? null : $prefix.'acceleration-services.destroy',
            'editRoute' => $canEdit ? $prefix.'acceleration-services.edit' : null,
            'addServicesRoute' => $canSubmit && $isMaker ? $prefix.'acceleration-services.create' : null,
            'mediaRoute' => $prefix.'acceleration-services.media',
            'approveRoute' => $canApprove ? 'spoc.acceleration-services.approve' : null,
            'sendBackRoute' => $canApprove ? 'spoc.acceleration-services.send-back' : null,
        ]);
    }

    public function approve(Request $request, AccelerationServiceSession $accelerationSession): RedirectResponse
    {
        $user = $request->user();
        abort_unless(AccelerationServicesApproval::canApprove($user, $accelerationSession), 403);

        $isFinalStep = (string) $accelerationSession->status === AccelerationServicesApproval::STATUS_PENDING_FINAL;

        if ($isFinalStep) {
            $accelerationSession->fill([
                'status' => AccelerationServicesApproval::STATUS_APPROVED,
                'final_approved_by_user_id' => (int) $user->id,
                'final_approved_by_name' => (string) $user->name,
                'final_approved_at' => now(),
            ])->save();
            AccelerationServicesApproval::log($accelerationSession, $user, 'final_approved');
            $this->recomputeFirstInitiationFlags($accelerationSession->incubatee_key, $accelerationSession->fiscal_year_id);
            $message = 'Entry approved. It now counts towards the 7.2 deliverable.';
        } else {
            $accelerationSession->fill([
                'status' => AccelerationServicesApproval::STATUS_PENDING_FINAL,
                'first_approved_by_user_id' => (int) $user->id,
                'first_approved_by_name' => (string) $user->name,
                'first_approved_at' => now(),
            ])->save();
            AccelerationServicesApproval::log($accelerationSession, $user, 'first_approved');
            $message = 'Entry reviewed and forwarded for final approval.';
        }

        return redirect()
            ->route('spoc.acceleration-services.show', $accelerationSession)
            ->with('status', $message);
    }

    public function sendBack(Request $request, AccelerationServiceSession $accelerationSession): RedirectResponse
    {
        $user = $request->user();
        abort_unless(AccelerationServicesApproval::canSendBack($user, $accelerationSession), 403);

        $validated = $request->validate([
            'remarks' => ['required', 'string', 'max:2000'],
        ], [
            'remarks.required' => 'Please add remarks explaining what needs to change.',
        ]);

        $accelerationSession->fill([
            'status' => AccelerationServicesApproval::STATUS_SENT_BACK,
            'sent_back_by_user_id' => (int) $user->id,
            'sent_back_by_name' => (string) $user->name,
            'sent_back_at' => now(),
            'sent_back_remarks' => trim((string) $validated['remarks']),
        ])->save();
        AccelerationServicesApproval::log($accelerationSession, $user, 'sent_back', (string) $validated['remarks']);

        return redirect()
            ->route('spoc.acceleration-services.show', $accelerationSession)
            ->with('status', 'Entry sent back to '.$accelerationSession->submitted_by_name.' with remarks.');
    }

    public function dashboard(Request $request): View
    {
        $user = $request->user();
        abort_unless(AccelerationServicesAccess::canViewDashboard($user), 403);

        $migrationMissing = ! Schema::hasTable('acceleration_service_sessions');
        $canSubmit = AccelerationServicesAccess::canSubmit($user);
        $isAdmin = $user->role === 'state_admin';

        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'from' => (string) $request->query('from', ''),
            'to' => (string) $request->query('to', ''),
            'status' => (string) $request->query('status', ''),
        ];

        $workflowReady = AccelerationServicesApproval::workflowReady();
        $isApprover = AccelerationServicesApproval::isApprover($user);

        $rows = collect();
        $totals = ['sessions' => 0, 'initiations_fy' => 0, 'buyer_seller_ticks' => 0, 'pending_mine' => 0];

        if (! $migrationMissing) {
            $query = AccelerationServiceSession::query()
                ->with([
                    'items' => fn ($q) => $q
                        ->orderBy('id')
                        ->select(['id', 'session_id', 'section', 'item_key', 'item_label']),
                ])
                ->withCount('items');
            if (in_array($user->role, ['state_staff', 'district_staff'], true)) {
                if ($isApprover) {
                    // Checkers see their own entries plus every submitted (non-draft) entry.
                    $query->where(function ($q) use ($user): void {
                        $q->where('submitted_by_user_id', (int) $user->id);
                        if (Schema::hasColumn('acceleration_service_sessions', 'is_draft')) {
                            $q->orWhere('is_draft', false);
                        }
                    });
                } else {
                    $query->where('submitted_by_user_id', (int) $user->id);
                }
            }

            if ($workflowReady && $filters['status'] !== '') {
                $query->where('status', $filters['status']);
            }

            if ($filters['q'] !== '') {
                $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $filters['q']).'%';
                $query->where(function ($q) use ($like): void {
                    $q->where('applicant_name', 'like', $like)
                        ->orWhere('application_no', 'like', $like)
                        ->orWhere('phone', 'like', $like)
                        ->orWhere('district_name', 'like', $like)
                        ->orWhere('submitted_by_name', 'like', $like);
                });
            }
            if ($filters['from'] !== '') {
                $query->whereDate('service_date', '>=', $filters['from']);
            }
            if ($filters['to'] !== '') {
                $query->whereDate('service_date', '<=', $filters['to']);
            }

            $totals['sessions'] = (int) (clone $query)->count();

            $activeFy = FiscalYear::query()->where('is_active', true)->orderByDesc('starts_on')->first();
            $periodFrom = $activeFy?->starts_on ? Carbon::parse($activeFy->starts_on) : null;
            $periodTo = $activeFy?->ends_on ? Carbon::parse($activeFy->ends_on) : null;

            $totals['initiations_fy'] = AccelerationServicesDeliverablesSupport::countUniqueInitiations($periodFrom, $periodTo);
            $totals['buyer_seller_ticks'] = AccelerationServicesDeliverablesSupport::countBuyerSellerMeets($periodFrom, $periodTo);

            if ($workflowReady && $isApprover) {
                $pendingStatuses = AccelerationServicesApproval::pendingStatusesFor($user);
                if ($pendingStatuses !== []) {
                    $totals['pending_mine'] = (int) AccelerationServiceSession::query()
                        ->whereIn('status', $pendingStatuses)
                        ->where('submitted_by_user_id', '!=', (int) $user->id)
                        ->count();
                }
            }

            $rows = $query
                ->orderByDesc('service_date')
                ->orderByDesc('id')
                ->paginate(25)
                ->withQueryString();
        }

        $prefix = AccelerationServicesAccess::routePrefixForUser($user);

        return view('acceleration-services.dashboard', [
            'rows' => $rows,
            'migrationMissing' => $migrationMissing,
            'isAdminView' => $isAdmin,
            'canSubmit' => $canSubmit,
            'inHouseOnly' => AccelerationServicesAccess::isInHouseOnlySubmitter($user),
            'currentRole' => (string) $user->role,
            'currentUserId' => (int) $user->id,
            'isApprover' => $isApprover,
            'workflowReady' => $workflowReady,
            'filters' => $filters,
            'totals' => $totals,
            'dashboardRoute' => $prefix.'acceleration-services.dashboard',
            'createRoute' => $canSubmit ? $prefix.'acceleration-services.create' : null,
            'exportRoute' => $prefix.'acceleration-services.export',
            'showRoute' => $prefix.'acceleration-services.show',
            'editRoute' => $canSubmit ? $prefix.'acceleration-services.edit' : null,
            'mediaRoute' => $prefix.'acceleration-services.media',
        ]);
    }

    public function destroy(Request $request, AccelerationServiceSession $accelerationSession): RedirectResponse
    {
        $user = $request->user();
        abort_unless(AccelerationServicesAccess::canDelete($user, $accelerationSession), 403);

        DB::transaction(function () use ($accelerationSession): void {
            $accelerationSession->load(['items.media']);
            foreach ($accelerationSession->items as $item) {
                foreach ($item->media as $media) {
                    Storage::disk((string) $media->disk)->delete((string) $media->path);
                    $media->delete();
                }
                $item->delete();
            }
            $accelerationSession->delete();
        });

        $this->recomputeFirstInitiationFlags($accelerationSession->incubatee_key, $accelerationSession->fiscal_year_id);

        $prefix = AccelerationServicesAccess::routePrefixForUser($user);

        return redirect()
            ->route($prefix.'acceleration-services.dashboard')
            ->with('status', 'Acceleration session deleted.');
    }

    public function export(Request $request): StreamedResponse
    {
        $user = $request->user();
        abort_unless(AccelerationServicesAccess::canViewDashboard($user), 403);

        $query = AccelerationServiceSession::query()->with('items');
        if (in_array($user->role, ['state_staff', 'district_staff'], true)) {
            if (AccelerationServicesApproval::isApprover($user)) {
                $query->where(function ($q) use ($user): void {
                    $q->where('submitted_by_user_id', (int) $user->id);
                    if (Schema::hasColumn('acceleration_service_sessions', 'is_draft')) {
                        $q->orWhere('is_draft', false);
                    }
                });
            } else {
                $query->where('submitted_by_user_id', (int) $user->id);
            }
        }

        if ($request->filled('from')) {
            $query->whereDate('service_date', '>=', (string) $request->query('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('service_date', '<=', (string) $request->query('to'));
        }

        $rows = $query->orderByDesc('service_date')->orderByDesc('id')->get();
        $filename = 'acceleration-services-'.now()->format('Ymd_His').'.csv';

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }

            fputcsv($out, [
                'Session ID',
                'Service date',
                'Applicant',
                'Application no',
                'District',
                'Onboarding',
                '7.2 initiation',
                'Services',
                'Submitted by',
                'Submitted at',
                'Approval status',
                'State review by',
                'State review at',
                'Final approval by',
                'Final approval at',
            ]);

            foreach ($rows as $row) {
                $labels = $row->items->pluck('item_label')->implode('; ');
                fputcsv($out, [
                    $row->id,
                    $row->service_date?->format('Y-m-d'),
                    $row->applicant_name,
                    $row->application_no,
                    $row->district_name,
                    $row->onboard_label,
                    $row->counts_for_7_2 ? 'Yes' : 'Follow-up',
                    $labels,
                    $row->submitted_by_name,
                    $row->created_at?->format('Y-m-d H:i'),
                    $row->statusLabel(),
                    $row->first_approved_by_name ?: '',
                    $row->first_approved_at?->format('Y-m-d H:i') ?: '',
                    $row->final_approved_by_name ?: '',
                    $row->final_approved_at?->format('Y-m-d H:i') ?: '',
                ]);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function searchIncubatees(Request $request): JsonResponse
    {
        $user = $this->submitterOrAbort($request);
        $districtScope = AccelerationServicesAccess::applicantDistrictScope($user);
        if ($user->role === 'district_staff' && $districtScope === '') {
            abort(403, 'Your district is not assigned. Please contact an administrator.');
        }

        $searchRequest = Request::create('/', 'GET', ['search' => $request->query('q', '')]);

        return response()->json([
            'ok' => true,
            'applicants' => $this->incubatees->search($searchRequest, $districtScope, true),
            'legacy_available' => (string) config('database.connections.legacy_phase1.database', '') !== '',
        ]);
    }

    public function incubateeHistory(Request $request): JsonResponse
    {
        abort_unless(AccelerationServicesAccess::canViewDashboard($request->user()), 403);

        $legacyPhase1ApplicationId = (int) $request->query('legacy_phase1_application_id', 0);
        $key = trim((string) $request->query('incubatee_key', ''));

        if ($legacyPhase1ApplicationId <= 0 && preg_match('/^p1:(\d+)$/', $key, $matches)) {
            $legacyPhase1ApplicationId = (int) $matches[1];
        }

        if ($legacyPhase1ApplicationId <= 0) {
            return response()->json([
                'ok' => true,
                'services' => [],
            ]);
        }

        $applicant = $this->incubatees->findPhase1Applicant($legacyPhase1ApplicationId);
        if ($applicant === null
            || AccelerationServicesAccess::applicantEligibilityError($request->user(), $applicant) !== null) {
            abort(404);
        }

        if ($key === '') {
            $key = \App\Support\AccelerationServicesOptions::incubateeKey($legacyPhase1ApplicationId);
        }

        $excludeSessionId = (int) $request->query('exclude_session_id', 0);

        return response()->json([
            'ok' => true,
            'services' => $this->incubatees->allServicesGiven($legacyPhase1ApplicationId, $key),
            'prior_form_items' => $this->incubatees->priorAccelerationFormItems(
                $key,
                $excludeSessionId > 0 ? $excludeSessionId : null,
            ),
        ]);
    }

    public function downloadMedia(Request $request, AccelerationServiceItemMedia $accelerationMedia)
    {
        $user = $request->user();
        abort_unless(AccelerationServicesAccess::canViewDashboard($user), 403);

        $accelerationMedia->load('item.session');
        $session = $accelerationMedia->item?->session;
        if (! $session) {
            abort(404);
        }
        $this->assertCanViewSession($user, $session);

        $disk = Storage::disk((string) $accelerationMedia->disk);
        if (! $disk->exists((string) $accelerationMedia->path)) {
            abort(404);
        }

        return $disk->response(
            (string) $accelerationMedia->path,
            (string) $accelerationMedia->original_name,
            $request->boolean('inline') ? ['Content-Disposition' => 'inline'] : []
        );
    }

    private function submitterOrAbort(Request $request): User
    {
        $user = $request->user();
        abort_unless(AccelerationServicesAccess::canSubmit($user), 403);

        return $user;
    }

    private function assertCanViewSession(User $user, AccelerationServiceSession $session): void
    {
        if ($user->role === 'state_admin') {
            return;
        }

        if (in_array($user->role, ['state_staff', 'district_staff'], true)
            && (int) $session->submitted_by_user_id === (int) $user->id) {
            return;
        }

        // Checkers (state SPOC + final approver) can view every submitted entry.
        if (AccelerationServicesApproval::isApprover($user) && ! (bool) $session->is_draft) {
            return;
        }

        // District staff can view submitted entries from their own district.
        if ($user->role === 'district_staff' && ! (bool) $session->is_draft) {
            $districtName = trim((string) ($user->district?->name ?? ''));
            if ($districtName !== '' && strcasecmp($districtName, trim((string) $session->district_name)) === 0) {
                return;
            }
        }

        abort(403);
    }

    private function assertCanModifySession(User $user, AccelerationServiceSession $session): void
    {
        if (in_array($user->role, ['state_staff', 'district_staff'], true)
            && (int) $session->submitted_by_user_id === (int) $user->id) {
            return;
        }

        abort(403);
    }

    private function recomputeFirstInitiationFlags(string $incubateeKey, ?int $fiscalYearId): void
    {
        if ($incubateeKey === '' || ! $fiscalYearId || ! Schema::hasTable('acceleration_service_sessions')) {
            return;
        }

        DB::table('acceleration_service_sessions')
            ->where('incubatee_key', $incubateeKey)
            ->where('fiscal_year_id', $fiscalYearId)
            ->update(['counts_for_7_2' => false]);

        // Prefer the first fully approved session; fall back to the first overall.
        $firstId = null;
        if (Schema::hasColumn('acceleration_service_sessions', 'status')) {
            $firstId = DB::table('acceleration_service_sessions')
                ->where('incubatee_key', $incubateeKey)
                ->where('fiscal_year_id', $fiscalYearId)
                ->where('status', AccelerationServicesApproval::STATUS_APPROVED)
                ->orderBy('service_date')
                ->orderBy('id')
                ->value('id');
        }
        if (! $firstId) {
            $firstId = DB::table('acceleration_service_sessions')
                ->where('incubatee_key', $incubateeKey)
                ->where('fiscal_year_id', $fiscalYearId)
                ->orderBy('service_date')
                ->orderBy('id')
                ->value('id');
        }

        if ($firstId) {
            DB::table('acceleration_service_sessions')
                ->where('id', $firstId)
                ->update(['counts_for_7_2' => true]);
        }
    }
}
