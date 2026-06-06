<?php

namespace App\Http\Controllers;

use App\Models\CfaSubmission;
use App\Models\District;
use App\Models\MarketLinkagePartner;
use App\Models\MarketLinkageSubmission;
use App\Models\ServiceCase;
use App\Models\User;
use App\Services\AppSettingsService;
use App\Services\LegacyApplicationServiceCaseSupport;
use App\Services\MarketLinkagePartnerCatalogService;
use App\Services\MarketLinkageWorkflowService;
use App\Support\MarketLinkageAccess;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MarketLinkageController extends Controller
{
    public function __construct(
        private LegacyApplicationServiceCaseSupport $legacyApplications,
        private AppSettingsService $settings,
        private MarketLinkagePartnerCatalogService $partnerCatalog,
        private MarketLinkageWorkflowService $workflow,
    ) {}

    public function create(Request $request): View
    {
        $staff = $this->staffOrAbort($request);
        $submissions = $this->eligibleSubmissions($staff)->get();
        $legacyRows = $this->legacyRowsForStaff($staff);
        $submissionIds = $submissions->pluck('id')->map(fn ($id) => (int) $id)->all();
        $legacyIds = $legacyRows->pluck('id')->map(fn ($id) => (int) $id)->all();

        return view('market-linkages.form', [
            'submissions' => $submissions,
            'legacyRows' => $legacyRows,
            'defaultCfaSubmissionId' => (int) $request->query('cfa_submission_id', 0),
            'defaultLegacyApplicationId' => (int) $request->query('legacy_application_id', 0),
            'migrationMissing' => ! Schema::hasTable('market_linkage_submissions'),
            'storeRoute' => 'staff.market-linkages.store',
            'dashboardRoute' => 'staff.market-linkages.dashboard',
            'showRoute' => 'staff.market-linkages.show',
            'priorMarketLinkageJson' => $this->priorMarketLinkageJson($submissionIds, $legacyIds),
            'partnerNameOptions' => $this->partnerCatalog->options(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $staff = $this->staffOrAbort($request);

        if (! Schema::hasTable('market_linkage_submissions')) {
            return redirect()
                ->route('staff.market-linkages.create')
                ->withErrors(['partners' => 'Market linkage tables are missing. Please run migrations first.']);
        }

        $validated = $request->validate([
            'cfa_submission_id' => ['nullable', 'integer', 'required_without:legacy_application_id'],
            'legacy_application_id' => ['nullable', 'integer', 'required_without:cfa_submission_id'],
            'partners' => ['required', 'array', 'min:1', 'max:20'],
            'partners.*.partner_name' => ['required', 'string', 'max:191'],
            'partners.*.linkage_mode' => ['required', 'string', Rule::in([
                MarketLinkageSubmission::LINKAGE_ONLINE,
                MarketLinkageSubmission::LINKAGE_OFFLINE,
            ])],
            'partners.*.linkage_date' => ['required', 'date'],
            'partners.*.link_url' => ['nullable', 'string', 'max:2048'],
            'partners.*.link' => ['nullable', 'string', 'max:2048'],
            'partners.*.bill_document' => ['nullable', 'file', 'max:5120', 'mimes:pdf,jpg,jpeg,png,webp'],
        ]);

        $validated['partners'] = $this->normalizeAndValidatePartnerLinkUrls($validated['partners']);

        $cfaId = (int) ($validated['cfa_submission_id'] ?? 0);
        $legacyId = (int) ($validated['legacy_application_id'] ?? 0);
        if ($cfaId > 0 && $legacyId > 0) {
            throw ValidationException::withMessages([
                'cfa_submission_id' => 'Choose either a Phase 3 incubatee or a legacy application, not both.',
            ]);
        }

        $incubatee = $this->resolveIncubateeSnapshot($staff, $cfaId, $legacyId);
        $districtId = (int) ($staff->district_id ?: 0);
        abort_unless($districtId > 0, 422, 'District assignment is required.');

        $submission = DB::transaction(function () use ($staff, $validated, $incubatee, $districtId, $cfaId, $legacyId, $request): MarketLinkageSubmission {
            $submission = MarketLinkageSubmission::query()->create([
                'submitted_by_user_id' => (int) $staff->id,
                'submitted_by_name' => (string) $staff->name,
                'district_id' => $districtId,
                'district_name' => (string) ($staff->district?->name ?? ''),
                'cfa_submission_id' => $cfaId > 0 ? $cfaId : null,
                'legacy_application_id' => $legacyId > 0 ? $legacyId : null,
                'incubatee_name' => $incubatee['name'],
                'application_no' => $incubatee['application_no'],
            ]);

            foreach (array_values($validated['partners']) as $index => $row) {
                $partner = MarketLinkagePartner::query()->create([
                    'market_linkage_submission_id' => (int) $submission->id,
                    'partner_name' => trim((string) $row['partner_name']),
                    'linkage_mode' => (string) $row['linkage_mode'],
                    'linkage_date' => (string) $row['linkage_date'],
                    'link_url' => $row['link_url'] ?? null,
                    'sort_order' => $index,
                ]);

                /** @var UploadedFile|null $file */
                $file = $request->file('partners.'.$index.'.bill_document');
                if ($file instanceof UploadedFile) {
                    $stored = $this->storePartnerDocument($file, $submission, $partner);
                    $partner->update($stored);
                }
            }

            return $submission->load('partners');
        });

        $this->workflow->submitForApproval($submission, (int) $staff->id);

        $this->partnerCatalog->registerNames(
            array_map(fn (array $row) => trim((string) ($row['partner_name'] ?? '')), $validated['partners']),
            (int) $staff->id,
        );

        return redirect()
            ->route('staff.services.index', ['status' => ServiceCase::STATUS_PENDING_APPROVAL])
            ->with('status', 'Market linkage submitted for SPOC approval ('.count($validated['partners']).' partner(s)).');
    }

    public function edit(Request $request, MarketLinkageSubmission $marketLinkage): View|RedirectResponse
    {
        $staff = $this->staffOrAbort($request);
        MarketLinkageAccess::canAccessSubmission($staff, $marketLinkage);
        abort_unless($marketLinkage->canBeEditedByStaff(), 403, 'This submission cannot be edited now.');
        abort_unless((int) $marketLinkage->submitted_by_user_id === (int) $staff->id, 403);

        $marketLinkage->load('partners');

        return view('market-linkages.form', [
            'submissions' => collect(),
            'legacyRows' => collect(),
            'defaultCfaSubmissionId' => (int) ($marketLinkage->cfa_submission_id ?? 0),
            'defaultLegacyApplicationId' => (int) ($marketLinkage->legacy_application_id ?? 0),
            'migrationMissing' => false,
            'storeRoute' => 'staff.market-linkages.update',
            'dashboardRoute' => 'staff.services.index',
            'showRoute' => 'staff.market-linkages.show',
            'priorMarketLinkageJson' => ['cfa' => [], 'legacy' => []],
            'partnerNameOptions' => $this->partnerCatalog->options(),
            'editingSubmission' => $marketLinkage,
            'isEdit' => true,
        ]);
    }

    public function update(Request $request, MarketLinkageSubmission $marketLinkage): RedirectResponse
    {
        $staff = $this->staffOrAbort($request);
        MarketLinkageAccess::canAccessSubmission($staff, $marketLinkage);
        abort_unless($marketLinkage->canBeEditedByStaff(), 403, 'This submission cannot be edited now.');
        abort_unless((int) $marketLinkage->submitted_by_user_id === (int) $staff->id, 403);

        $validated = $request->validate([
            'partners' => ['required', 'array', 'min:1', 'max:20'],
            'partners.*.partner_name' => ['required', 'string', 'max:191'],
            'partners.*.linkage_mode' => ['required', 'string', Rule::in([
                MarketLinkageSubmission::LINKAGE_ONLINE,
                MarketLinkageSubmission::LINKAGE_OFFLINE,
            ])],
            'partners.*.linkage_date' => ['required', 'date'],
            'partners.*.link_url' => ['nullable', 'string', 'max:2048'],
            'partners.*.link' => ['nullable', 'string', 'max:2048'],
            'partners.*.bill_document' => ['nullable', 'file', 'max:5120', 'mimes:pdf,jpg,jpeg,png,webp'],
        ]);

        $validated['partners'] = $this->normalizeAndValidatePartnerLinkUrls($validated['partners']);

        DB::transaction(function () use ($marketLinkage, $validated, $request): void {
            foreach ($marketLinkage->partners as $partner) {
                if ($partner->hasDocument()) {
                    $disk = Storage::disk((string) ($partner->document_disk ?: 'local'));
                    if ($disk->exists((string) $partner->document_path)) {
                        $disk->delete((string) $partner->document_path);
                    }
                }
            }
            $marketLinkage->partners()->delete();

            foreach (array_values($validated['partners']) as $index => $row) {
                $partner = MarketLinkagePartner::query()->create([
                    'market_linkage_submission_id' => (int) $marketLinkage->id,
                    'partner_name' => trim((string) $row['partner_name']),
                    'linkage_mode' => (string) $row['linkage_mode'],
                    'linkage_date' => (string) $row['linkage_date'],
                    'link_url' => $row['link_url'] ?? null,
                    'sort_order' => $index,
                ]);

                /** @var UploadedFile|null $file */
                $file = $request->file('partners.'.$index.'.bill_document');
                if ($file instanceof UploadedFile) {
                    $stored = $this->storePartnerDocument($file, $marketLinkage, $partner);
                    $partner->update($stored);
                }
            }
        });

        $marketLinkage->refresh()->load('partners');
        $this->workflow->submitForApproval($marketLinkage, (int) $staff->id);

        $this->partnerCatalog->registerNames(
            array_map(fn (array $row) => trim((string) ($row['partner_name'] ?? '')), $validated['partners']),
            (int) $staff->id,
        );

        return redirect()
            ->route('staff.services.index', ['status' => ServiceCase::STATUS_PENDING_APPROVAL])
            ->with('status', 'Market linkage resubmitted for SPOC approval.');
    }

    public function dashboard(Request $request): View
    {
        $user = $request->user();
        abort_unless(MarketLinkageAccess::canViewDashboard($user), 403);

        if (! Schema::hasTable('market_linkage_submissions')) {
            return $this->dashboardView($request, collect(), true, $this->defaultFilters($request), [], $this->emptyDashboardStats());
        }

        $filters = $this->validatedFilters($request);
        $query = MarketLinkageSubmission::query()
            ->approved()
            ->with(['partners', 'submitter:id,name', 'district:id,name']);

        $this->scopeDashboardQuery($query, $user);

        if ($filters['district_id'] > 0) {
            $query->where('district_id', $filters['district_id']);
        }
        if ($filters['linkage_mode'] !== '') {
            $query->whereHas('partners', fn ($q) => $q->where('linkage_mode', $filters['linkage_mode']));
        }
        if ($filters['q'] !== '') {
            $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $filters['q']).'%';
            $query->where(function ($q) use ($like): void {
                $q->where('incubatee_name', 'like', $like)
                    ->orWhere('application_no', 'like', $like)
                    ->orWhere('submitted_by_name', 'like', $like)
                    ->orWhereHas('partners', fn ($pq) => $pq->where('partner_name', 'like', $like));
            });
        }
        if ($filters['from'] !== '') {
            $query->whereHas('partners', fn ($q) => $q->whereDate('linkage_date', '>=', $filters['from']));
        }
        if ($filters['to'] !== '') {
            $query->whereHas('partners', fn ($q) => $q->whereDate('linkage_date', '<=', $filters['to']));
        }

        $submissions = $query
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        $grouped = $this->groupSubmissionsByIncubatee($submissions, $filters, $user);
        $stats = $this->computeDashboardStats($submissions, $filters);
        $rows = $this->paginateGroupedIncubatees($request, $grouped, 25);

        $districtCounts = $this->districtCounts($user, $filters);

        return $this->dashboardView($request, $rows, false, $filters, $districtCounts, $stats);
    }

    public function show(Request $request, MarketLinkageSubmission $marketLinkage): View
    {
        $user = $request->user();
        abort_unless(MarketLinkageAccess::canViewDashboard($user), 403);
        MarketLinkageAccess::canAccessSubmission($user, $marketLinkage);

        $marketLinkage->load(['partners', 'submitter:id,name', 'district:id,name', 'spoc:id,name', 'approver:id,name', 'rejector:id,name']);

        $isAdmin = $user->role === 'state_admin';
        $staffListRoute = $user->role === 'district_staff' ? 'staff.services.index' : null;

        return view('market-linkages.show', [
            'submission' => $marketLinkage,
            'currentRole' => (string) $user->role,
            'dashboardRoute' => $isAdmin ? 'admin.market-linkages.dashboard' : 'staff.market-linkages.dashboard',
            'createRoute' => MarketLinkageAccess::canSubmit($user) ? 'staff.market-linkages.create' : null,
            'documentRoutePrefix' => $isAdmin ? 'admin.market-linkages.document' : 'staff.market-linkages.document',
            'staffListRoute' => $staffListRoute,
            'editRoute' => MarketLinkageAccess::canSubmit($user) && $marketLinkage->canBeEditedByStaff()
                && (int) $marketLinkage->submitted_by_user_id === (int) $user->id
                ? 'staff.market-linkages.edit'
                : null,
            'canDelete' => MarketLinkageAccess::canSubmit($user)
                && (int) $marketLinkage->submitted_by_user_id === (int) $user->id
                && $marketLinkage->canBeDeletedByStaff(),
            'deleteRoute' => 'staff.market-linkages.destroy',
        ]);
    }

    public function destroy(Request $request, MarketLinkageSubmission $marketLinkage): RedirectResponse
    {
        $staff = $this->staffOrAbort($request);
        MarketLinkageAccess::canAccessSubmission($staff, $marketLinkage);
        abort_unless((int) $marketLinkage->submitted_by_user_id === (int) $staff->id, 403);
        abort_unless($marketLinkage->canBeDeletedByStaff(), 403, 'This market linkage cannot be deleted in its current state.');

        $marketLinkage->load('partners');

        DB::transaction(function () use ($marketLinkage): void {
            foreach ($marketLinkage->partners as $partner) {
                if ($partner->hasDocument()) {
                    $disk = Storage::disk((string) ($partner->document_disk ?: 'local'));
                    $path = (string) $partner->document_path;
                    if ($path !== '' && $disk->exists($path)) {
                        $disk->delete($path);
                    }
                }
            }

            $marketLinkage->delete();
        });

        return redirect()
            ->route('staff.services.index')
            ->with('status', 'Market linkage deleted.');
    }

    public function export(Request $request): StreamedResponse
    {
        $user = $request->user();
        abort_unless(MarketLinkageAccess::canViewDashboard($user), 403);

        $filters = $this->validatedFilters($request);
        $query = MarketLinkageSubmission::query()->approved()->with(['partners', 'district:id,name']);
        $this->scopeDashboardQuery($query, $user);

        if ($filters['district_id'] > 0) {
            $query->where('district_id', $filters['district_id']);
        }
        if ($filters['linkage_mode'] !== '') {
            $query->whereHas('partners', fn ($q) => $q->where('linkage_mode', $filters['linkage_mode']));
        }
        if ($filters['q'] !== '') {
            $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $filters['q']).'%';
            $query->where(function ($q) use ($like): void {
                $q->where('incubatee_name', 'like', $like)
                    ->orWhere('application_no', 'like', $like)
                    ->orWhereHas('partners', fn ($pq) => $pq->where('partner_name', 'like', $like));
            });
        }

        $submissions = $query->orderByDesc('created_at')->orderByDesc('id')->get();

        $filename = 'market-linkages-'.now()->format('Ymd_His').'.csv';

        return response()->streamDownload(function () use ($submissions): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, [
                'Submission ID',
                'District',
                'Incubatee',
                'Application no',
                'Submitted by',
                'Submitted at',
                'Partner name',
                'Linkage mode',
                'Linkage date',
                'Link URL',
                'Has document',
            ]);

            foreach ($submissions as $submission) {
                foreach ($submission->partners as $partner) {
                    fputcsv($out, [
                        $submission->id,
                        $submission->district_name ?? $submission->district?->name ?? '',
                        $submission->incubatee_name,
                        $submission->application_no ?? '',
                        $submission->submitted_by_name,
                        optional($submission->created_at)->timezone(config('app.timezone'))->format('Y-m-d H:i'),
                        $partner->partner_name,
                        MarketLinkageSubmission::linkageModeLabel($partner->linkage_mode),
                        $partner->linkage_date?->format('Y-m-d') ?? '',
                        $partner->link_url ?? '',
                        $partner->hasDocument() ? 'yes' : 'no',
                    ]);
                }
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function downloadDocument(
        Request $request,
        MarketLinkageSubmission $marketLinkage,
        MarketLinkagePartner $partner,
    ) {
        $user = $request->user();
        abort_unless(MarketLinkageAccess::canViewDashboard($user), 403);
        MarketLinkageAccess::canAccessSubmission($user, $marketLinkage);
        abort_unless((int) $partner->market_linkage_submission_id === (int) $marketLinkage->id, 404);
        abort_unless($partner->hasDocument(), 404);

        $disk = Storage::disk((string) ($partner->document_disk ?: 'local'));
        $path = (string) $partner->document_path;
        abort_unless($disk->exists($path), 404);

        return $disk->download($path, (string) ($partner->document_original_name ?: 'document'));
    }

    private function staffOrAbort(Request $request): User
    {
        $user = $request->user();
        abort_unless(MarketLinkageAccess::canSubmit($user), 403);

        return $user->load('district');
    }

    /**
     * @return Builder<CfaSubmission>
     */
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

    private function legacyRowsForStaff(User $staff): Collection
    {
        try {
            return $this->legacyApplications->eligibleLegacyApplicationsForStaff($staff);
        } catch (\Throwable $e) {
            report($e);

            return collect();
        }
    }

    /**
     * @return array{name: string, application_no: ?string}
     */
    private function resolveIncubateeSnapshot(User $staff, int $cfaId, int $legacyId): array
    {
        if ($cfaId > 0) {
            $submission = $this->eligibleSubmissions($staff)->whereKey($cfaId)->first();
            if ($submission === null) {
                throw ValidationException::withMessages([
                    'cfa_submission_id' => 'Invalid incubatee for your district.',
                ]);
            }

            return [
                'name' => (string) $submission->applicant_name,
                'application_no' => $submission->application_no ? (string) $submission->application_no : null,
            ];
        }

        if ($legacyId > 0) {
            $legacyRows = $this->legacyRowsForStaff($staff);
            $row = $legacyRows->first(fn ($r) => (int) $r->id === $legacyId);
            if ($row === null) {
                throw ValidationException::withMessages([
                    'legacy_application_id' => 'Invalid legacy application for your district.',
                ]);
            }

            return [
                'name' => (string) $row->applicant_name,
                'application_no' => $row->application_no ? (string) $row->application_no : null,
            ];
        }

        throw ValidationException::withMessages([
            'cfa_submission_id' => 'Select an incubatee.',
        ]);
    }

    /**
     * @return array{document_disk: string, document_path: string, document_original_name: string}
     */
    private function storePartnerDocument(
        UploadedFile $file,
        MarketLinkageSubmission $submission,
        MarketLinkagePartner $partner,
    ): array {
        $dir = 'market-linkages/'.$submission->id.'/'.$partner->id;
        $path = $file->store($dir, 'local');

        return [
            'document_disk' => 'local',
            'document_path' => $path,
            'document_original_name' => $file->getClientOriginalName(),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $partners
     * @return list<array<string, mixed>>
     */
    private function normalizeAndValidatePartnerLinkUrls(array $partners): array
    {
        $errors = [];
        $normalized = [];

        foreach (array_values($partners) as $index => $row) {
            $mode = (string) ($row['linkage_mode'] ?? '');
            $linkInput = MarketLinkagePartner::linkInputFromRow($row);

            if ($mode === MarketLinkageSubmission::LINKAGE_ONLINE && $linkInput === '') {
                $errors["partners.{$index}.link_url"] = 'Link or URL is required for online market linkage.';

                continue;
            }

            $row['link_url'] = MarketLinkagePartner::normalizeLinkForStorage($linkInput);
            unset($row['link']);
            $normalized[] = $row;
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $normalized;
    }

    private function scopeDashboardQuery($query, User $user): void
    {
        if ($user->role === 'district_staff') {
            $query->where('district_id', (int) ($user->district_id ?: 0));
        }
    }

    /**
     * @param  Builder<MarketLinkageSubmission>  $query
     */
    public static function applyApprovedScopeIfSupported($query)
    {
        if (MarketLinkageSubmission::supportsWorkflow()) {
            return $query->where('status', ServiceCase::STATUS_APPROVED);
        }

        return $query;
    }

    private function incubateeKey(MarketLinkageSubmission $submission): string
    {
        if ($submission->cfa_submission_id) {
            return 'c:'.(int) $submission->cfa_submission_id;
        }
        if ($submission->legacy_application_id) {
            return 'l:'.(int) $submission->legacy_application_id;
        }

        return 's:'.(int) $submission->id;
    }

    /**
     * @param  array{q: string, from: string, to: string, district_id: int, linkage_mode: string}  $filters
     * @return Collection<int, object{
     *   key: string,
     *   incubatee_name: string,
     *   application_no: ?string,
     *   district_id: int,
     *   district_name: ?string,
     *   cfa_submission_id: ?int,
     *   legacy_application_id: ?int,
     *   partner_count: int,
     *   submission_count: int,
     *   last_recorded_at: ?Carbon,
     *   partners: list<array<string, mixed>>
     * }>
     */
    private function groupSubmissionsByIncubatee(Collection $submissions, array $filters, User $user): Collection
    {
        $showRoute = $user->role === 'state_admin' ? 'admin.market-linkages.show' : 'staff.market-linkages.show';
        $documentRoute = $user->role === 'state_admin' ? 'admin.market-linkages.document' : 'staff.market-linkages.document';
        $groups = [];

        foreach ($submissions as $submission) {
            $key = $this->incubateeKey($submission);
            if (! isset($groups[$key])) {
                $groups[$key] = (object) [
                    'key' => $key,
                    'incubatee_name' => (string) $submission->incubatee_name,
                    'application_no' => $submission->application_no ? (string) $submission->application_no : null,
                    'district_id' => (int) $submission->district_id,
                    'district_name' => $submission->district_name ?? $submission->district?->name,
                    'cfa_submission_id' => $submission->cfa_submission_id ? (int) $submission->cfa_submission_id : null,
                    'legacy_application_id' => $submission->legacy_application_id ? (int) $submission->legacy_application_id : null,
                    'partner_count' => 0,
                    'submission_count' => 0,
                    'last_recorded_at' => $submission->created_at,
                    'partners' => [],
                ];
            }

            $group = $groups[$key];
            $group->submission_count++;

            if ($submission->created_at && (! $group->last_recorded_at || $submission->created_at->gt($group->last_recorded_at))) {
                $group->last_recorded_at = $submission->created_at;
            }

            $recordedAt = optional($submission->created_at)->timezone(config('app.timezone'))->format('d M Y H:i') ?? '';

            foreach ($submission->partners as $partner) {
                if (! $this->partnerMatchesFilters($partner, $filters)) {
                    continue;
                }

                $group->partners[] = [
                    'partner_name' => (string) $partner->partner_name,
                    'linkage_mode' => (string) $partner->linkage_mode,
                    'linkage_mode_label' => MarketLinkageSubmission::linkageModeLabel((string) $partner->linkage_mode),
                    'linkage_date' => $partner->linkage_date?->format('Y-m-d') ?? '',
                    'linkage_date_display' => $partner->linkage_date?->format('d M Y') ?? '',
                    'link_url' => is_string($partner->link_url) && $partner->link_url !== '' ? (string) $partner->link_url : null,
                    'link_href' => MarketLinkagePartner::clickableHref($partner->link_url),
                    'has_document' => $partner->hasDocument(),
                    'document_url' => $partner->hasDocument()
                        ? route($documentRoute, [$submission, $partner])
                        : null,
                    'recorded_at' => $recordedAt,
                    'recorded_by' => (string) $submission->submitted_by_name,
                    'submission_id' => (int) $submission->id,
                    'show_url' => route($showRoute, $submission),
                ];
                $group->partner_count++;
            }
        }

        return collect($groups)
            ->filter(fn ($g) => $g->partner_count > 0)
            ->sortByDesc(fn ($g) => $g->last_recorded_at?->timestamp ?? 0)
            ->values();
    }

    /**
     * @param  array{q: string, from: string, to: string, district_id: int, linkage_mode: string}  $filters
     */
    private function partnerMatchesFilters(MarketLinkagePartner $partner, array $filters): bool
    {
        if ($filters['linkage_mode'] !== '' && $partner->linkage_mode !== $filters['linkage_mode']) {
            return false;
        }
        if ($filters['from'] !== '' && $partner->linkage_date && $partner->linkage_date->format('Y-m-d') < $filters['from']) {
            return false;
        }
        if ($filters['to'] !== '' && $partner->linkage_date && $partner->linkage_date->format('Y-m-d') > $filters['to']) {
            return false;
        }

        return true;
    }

    /**
     * @param  Collection<int, object>  $grouped
     */
    private function paginateGroupedIncubatees(Request $request, Collection $grouped, int $perPage): LengthAwarePaginator
    {
        $page = max(1, (int) $request->query('page', 1));
        $total = $grouped->count();
        $items = $grouped->forPage($page, $perPage)->values();

        return new LengthAwarePaginator(
            $items,
            $total,
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );
    }

    /**
     * @return array{q: string, from: string, to: string, district_id: int, linkage_mode: string}
     */
    private function validatedFilters(Request $request): array
    {
        $districtId = (int) $request->query('district_id', 0);
        $linkageMode = (string) $request->query('linkage_mode', '');
        if (! in_array($linkageMode, ['', MarketLinkageSubmission::LINKAGE_ONLINE, MarketLinkageSubmission::LINKAGE_OFFLINE], true)) {
            $linkageMode = '';
        }

        return [
            'q' => trim((string) $request->query('q', '')),
            'from' => (string) $request->query('from', ''),
            'to' => (string) $request->query('to', ''),
            'district_id' => $districtId,
            'linkage_mode' => $linkageMode,
        ];
    }

    /**
     * @return array{q: string, from: string, to: string, district_id: int, linkage_mode: string}
     */
    private function defaultFilters(Request $request): array
    {
        return $this->validatedFilters($request);
    }

    /**
     * @param  array{unique_partners: int, linked_incubatees: int, partner_records: int, online_partners: int, offline_partners: int}  $stats
     * @param  list<array{id: int, name: string, total: int}>  $districtCounts
     */
    private function dashboardView(
        Request $request,
        mixed $rows,
        bool $migrationMissing,
        array $filters,
        array $districtCounts = [],
        array $stats = [],
    ): View {
        $user = $request->user();
        $isAdmin = $user->role === 'state_admin';

        return view('market-linkages.dashboard', [
            'rows' => $rows,
            'stats' => $stats !== [] ? $stats : $this->emptyDashboardStats(),
            'migrationMissing' => $migrationMissing,
            'isPaginated' => ! $migrationMissing,
            'isAdminView' => $isAdmin,
            'filters' => $filters,
            'districtCounts' => $districtCounts,
            'districts' => $isAdmin ? District::query()->orderBy('name')->get(['id', 'name']) : collect(),
            'dashboardRoute' => $isAdmin ? 'admin.market-linkages.dashboard' : 'staff.market-linkages.dashboard',
            'exportRoute' => $isAdmin ? 'admin.market-linkages.export' : 'staff.market-linkages.export',
            'showRoute' => $isAdmin ? 'admin.market-linkages.show' : 'staff.market-linkages.show',
            'createRoute' => MarketLinkageAccess::canSubmit($user) ? 'staff.market-linkages.create' : null,
            'documentRoutePrefix' => $isAdmin ? 'admin.market-linkages.document' : 'staff.market-linkages.document',
        ]);
    }

    /**
     * @param  Collection<int, MarketLinkageSubmission>  $submissions
     * @param  array{q: string, from: string, to: string, district_id: int, linkage_mode: string}  $filters
     * @return array{unique_partners: int, linked_incubatees: int, partner_records: int, online_partners: int, offline_partners: int}
     */
    private function computeDashboardStats(Collection $submissions, array $filters): array
    {
        $uniquePartners = [];
        $incubateeKeys = [];
        $partnerRecords = 0;
        $onlinePartners = 0;
        $offlinePartners = 0;

        foreach ($submissions as $submission) {
            $hasMatchingPartner = false;

            foreach ($submission->partners as $partner) {
                if (! $this->partnerMatchesFilters($partner, $filters)) {
                    continue;
                }

                $hasMatchingPartner = true;
                $partnerRecords++;
                $partnerKey = $this->partnerCatalog->normalizePartnerKey((string) $partner->partner_name);
                if ($partnerKey !== '') {
                    $uniquePartners[$partnerKey] = true;
                }

                if ($partner->linkage_mode === MarketLinkageSubmission::LINKAGE_ONLINE) {
                    $onlinePartners++;
                } elseif ($partner->linkage_mode === MarketLinkageSubmission::LINKAGE_OFFLINE) {
                    $offlinePartners++;
                }
            }

            if ($hasMatchingPartner) {
                $incubateeKeys[$this->incubateeKey($submission)] = true;
            }
        }

        return [
            'unique_partners' => count($uniquePartners),
            'linked_incubatees' => count($incubateeKeys),
            'partner_records' => $partnerRecords,
            'online_partners' => $onlinePartners,
            'offline_partners' => $offlinePartners,
        ];
    }

    /**
     * @return array{unique_partners: int, linked_incubatees: int, partner_records: int, online_partners: int, offline_partners: int}
     */
    private function emptyDashboardStats(): array
    {
        return [
            'unique_partners' => 0,
            'linked_incubatees' => 0,
            'partner_records' => 0,
            'online_partners' => 0,
            'offline_partners' => 0,
        ];
    }

    /**
     * @return list<array{id: int, name: string, total: int}>
     */
    private function districtCounts(User $user, array $filters): array
    {
        if ($user->role !== 'state_admin') {
            return [];
        }

        $query = MarketLinkageSubmission::query()->approved();
        if ($filters['q'] !== '') {
            $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $filters['q']).'%';
            $query->where(function ($q) use ($like): void {
                $q->where('incubatee_name', 'like', $like)
                    ->orWhere('application_no', 'like', $like);
            });
        }

        $totals = $query
            ->get(['district_id', 'cfa_submission_id', 'legacy_application_id', 'id'])
            ->groupBy('district_id')
            ->map(fn (Collection $rows) => $rows->unique(fn (MarketLinkageSubmission $s) => $this->incubateeKey($s))->count());

        return District::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (District $d) => [
                'id' => (int) $d->id,
                'name' => (string) $d->name,
                'total' => (int) ($totals->get((int) $d->id) ?? 0),
            ])
            ->all();
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
                    'document_url' => $partner->hasDocument()
                        ? route('staff.market-linkages.document', [$submission, $partner])
                        : null,
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
}
